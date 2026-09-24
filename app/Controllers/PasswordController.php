<?php
declare(strict_types=1);

/**
 * Recuperação de senha — VERSÃO DEMONSTRATIVA (ambiente local do TCC).
 *
 * Não há envio de e-mail real. O link gerado é:
 *  - gravado em storage/logs/redefinicoes_senha.log (pasta bloqueada para o navegador);
 *  - mostrado na tela SOMENTE com DEBUG=true e acesso pelo próprio computador (localhost).
 * Em produção, o link seria enviado por e-mail e nunca exibido na tela.
 *
 * O banco guarda só o hash SHA-256 do token; o link vale 30 minutos e pode ser usado uma vez.
 */
final class PasswordController extends Controller {
    /** esqueci_senha.php — pede o e-mail e gera o link de redefinição. */
    public function esqueci(): void {
        if (usuarioLogado()) redirect(destinoPainel());

        $linkDemo = null;
        $enviado = false;
        $localhost = in_array(ip_cliente(), ['127.0.0.1', '::1'], true);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $email = normalizar_email(post_str('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('erro', 'Informe um e-mail válido.');
                redirect('esqueci_senha.php');
            }
            $dao = new UsuarioDAO();
            // Usa o mesmo limite do login para não virar ferramenta de descobrir e-mails
            // (menos o limite da conta: o dono ainda pede o link se a conta estiver sob ataque).
            if ($dao->minutosBloqueioLogin(ip_cliente(), $email, somarIps: false) > 0) {
                flash('erro', 'Muitas tentativas. Aguarde alguns minutos e tente novamente.');
                redirect('esqueci_senha.php');
            }
            $token = $dao->criarTokenRedefinicao($email, ip_cliente());
            if ($token !== null) {
                $link = url('redefinir_senha.php?token='.$token);
                registrar_log('redefinicoes_senha.log', "Link de redefinição para {$email} (válido por ".UsuarioDAO::REDEFINICAO_MINUTOS." min): {$link}");
                if (DEBUG && $localhost) $linkDemo = $link;
            }
            // Mesma resposta exista ou não o e-mail (não revela quem tem conta).
            $enviado = true;
        }

        $title = 'Recuperar senha';
        $this->view('auth/esqueci_senha', get_defined_vars());
    }

    /** redefinir_senha.php?token= — define a nova senha (uso único, expira em 30 min). */
    public function redefinir(): void {
        $dao = new UsuarioDAO();
        $token = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_str('token') : get_str('token');
        $usuario = $dao->buscarPorTokenRedefinicao($token);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            if (!$usuario) {
                flash('erro', 'Link inválido, já utilizado ou expirado. Gere um novo.');
                redirect('esqueci_senha.php');
            }
            $senha = (string)(is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '');
            $conf = (string)(is_string($_POST['senha_confirmacao'] ?? null) ? $_POST['senha_confirmacao'] : '');
            $erro = strlen($senha) < 6 ? 'A senha precisa ter pelo menos 6 caracteres.'
                : (strlen($senha) > 72 ? 'A senha pode ter no máximo 72 caracteres.'
                : ($senha !== $conf ? 'A confirmação não confere com a nova senha.' : ''));
            if ($erro !== '') {
                flash('erro', $erro);
                redirect('redefinir_senha.php?token='.$token);
            }
            if (!$dao->redefinirSenha($token, $senha)) {
                flash('erro', 'Não foi possível redefinir a senha. Gere um novo link.');
                redirect('esqueci_senha.php');
            }
            // Se havia alguém logado neste navegador, a sessão é encerrada.
            if (usuarioLogado()) encerrar_sessao();
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            flash('ok', 'Senha alterada com sucesso. Entre com a nova senha.');
            redirect('login.php');
        }

        $title = 'Nova senha';
        $this->view('auth/redefinir_senha', get_defined_vars());
    }
}
