<?php
declare(strict_types=1);

/**
 * Acesso à conta: login, cadastro e saída.
 * A recuperação de senha fica em PasswordController.
 */
final class AuthController extends Controller {
    /**
     * login.php — formulário (GET) e autenticação (POST).
     * Proteção contra força-bruta: muitas senhas erradas para o mesmo e-mail (do mesmo IP ou
     * somando vários IPs) ou do mesmo IP bloqueiam por alguns minutos (limites em config/config.php).
     */
    public function login(): void {
        if (usuarioLogado()) redirect(destinoPainel());
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $title = 'Entrar';
            $this->view('auth/login', get_defined_vars());
            return;
        }
        validar_csrf();

        $email = normalizar_email(post_str('email'));
        $senha = (string)(is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '');
        $ip = ip_cliente();
        $dao = new UsuarioDAO();

        $minutos = $dao->minutosBloqueioLogin($ip, $email);
        if ($minutos > 0) {
            flash('erro', "Muitas tentativas de login sem sucesso. Por segurança, aguarde {$minutos} minuto(s) e tente novamente, ou use \"Esqueci minha senha\".");
            redirect('login.php');
        }

        $u = ($email !== '' && $senha !== '') ? $dao->autenticar($email, $senha) : false;
        if (!$u) {
            $dao->registrarFalhaLogin($ip, $email);
            // Mensagem única: não revela se o e-mail existe.
            flash('erro', 'E-mail ou senha inválidos ou conta desativada.');
            redirect('login.php');
        }

        $dao->limparFalhasLogin($ip, $email);
        iniciar_sessao_usuario($u); // novo ID de sessão (evita fixação de sessão) e novo token CSRF
        redirect(destinoPainel());
    }

    /**
     * cadastro.php — cria a conta (candidato ou empresa) e o perfil na mesma transação,
     * já entra no sistema e leva ao perfil (candidato) ou aos dados da empresa.
     */
    public function cadastro(): void {
        if (usuarioLogado()) redirect(destinoPainel());
        $title = 'Criar conta';
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->view('auth/cadastro', get_defined_vars()); return; }
        validar_csrf();

        $d = [
            'nome' => mb_substr(post_str('nome'), 0, 255),
            'email' => normalizar_email(post_str('email')),
            'senha' => (string)(is_string($_POST['senha'] ?? null) ? $_POST['senha'] : ''),
            'telefone' => mb_substr(post_str('telefone'), 0, 30),
            // Só candidato ou empresa: ninguém se cadastra como administrador pelo site.
            'tipo' => enum_val(post_str('tipo'), ['candidato', 'empresa'], 'candidato'),
            'ativo' => 1,
        ];
        $erros = [];
        if ($d['nome'] === '') $erros[] = 'Informe o nome.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 255) $erros[] = 'Informe um e-mail válido.';
        if (strlen($d['senha']) < 6) $erros[] = 'A senha precisa ter pelo menos 6 caracteres.';
        if (strlen($d['senha']) > 72) $erros[] = 'A senha pode ter no máximo 72 caracteres.'; // limite do bcrypt
        if (!isset($_POST['aceite_lgpd'])) $erros[] = 'É preciso aceitar o termo de autorização e privacidade.';
        if ($erros) {
            flash('erro', implode(' ', $erros));
            $this->view('auth/cadastro', get_defined_vars()); return;
        }

        $dao = new UsuarioDAO();
        $uid = $dao->cadastrar(new UsuarioDTO($d));
        if (!$uid) {
            flash('erro', $dao->erro === 'Este e-mail já está cadastrado.' ? 'Este e-mail já está cadastrado. Faça login ou use outro e-mail.' : 'Não foi possível concluir o cadastro. Tente novamente.');
            $this->view('auth/cadastro', get_defined_vars()); return;
        }
        // Linha completa do banco (inclui o hash da senha, de onde sai a "marca" conferida a cada requisição).
        iniciar_sessao_usuario($dao->buscarPorId($uid) ?? ['id' => $uid, 'nome' => $d['nome'], 'tipo' => $d['tipo']]);
        flash('ok', 'Cadastro realizado com sucesso!');
        redirect($d['tipo'] === 'empresa' ? 'admin/pages/empresa_perfil.php' : 'view/perfil/index.php?novo=1');
    }

    /**
     * view/usuario/logout.php — sair da conta. Aceito quando:
     *  - POST com token CSRF válido; ou
     *  - GET com o token na URL (?t=..., ver logout_url()); ou
     *  - GET vindo de uma página do próprio site (cabeçalho Sec-Fetch-Site: same-origin,
     *    enviado pelos navegadores atuais) — é o caso do link "Sair" do menu.
     * Qualquer outro caso (ex.: link/imagem em outro site tentando deslogar o usuário)
     * mostra uma confirmação em vez de sair direto.
     */
    public function logout(): void {
        $token = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['csrf'] ?? '') : ($_GET['t'] ?? '');
        $tokenOk = is_string($token) && $token !== '' && hash_equals((string)($_SESSION['csrf'] ?? ''), $token);
        $mesmaOrigem = ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'same-origin' && $_SERVER['REQUEST_METHOD'] === 'GET';

        if (!usuarioLogado()) redirect('index.php');

        if ($tokenOk || $mesmaOrigem) {
            encerrar_sessao();
            redirect('index.php');
        }

        $title = 'Sair';
        $this->view('auth/logout', get_defined_vars());
    }
}
