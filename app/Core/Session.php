<?php
declare(strict_types=1);

/*
 * Sessão do usuário: abertura segura, mensagens "flash", login/logout e
 * revalidação da conta a cada requisição.
 */

/**
 * Abre a sessão com cookie próprio e seguro:
 *  - nome próprio (CVDF_SESSAO) e restrito à pasta do projeto: outro sistema no
 *    mesmo localhost não compartilha a sessão;
 *  - HttpOnly (JavaScript não lê o cookie) e SameSite=Lax (protege contra CSRF);
 *  - modo estrito: recusa IDs de sessão inventados; nunca aceita o ID pela URL.
 */
function iniciar_sessao(): void {
    if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('CVDF_SESSAO');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL,
        'secure' => HTTPS_ATIVO,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Confere no banco, a cada requisição, se a conta logada continua valendo.
 * Se o administrador desativar/excluir a conta ou mudar o tipo dela, a sessão
 * aberta perde o acesso imediatamente (e não só no próximo login).
 * O mesmo vale para a troca de senha (redefinição pelo link ou pelo administrador):
 * as sessões abertas com a senha antiga são encerradas.
 */
function revalidar_sessao(): void {
    if (!usuarioLogado()) return;
    try {
        $s = Database::getConexao()->prepare('SELECT nome, tipo, ativo, senha FROM usuarios WHERE id = ?');
        $s->execute([(int)$_SESSION['usuario_id']]);
        $u = $s->fetch();
        $contaAtiva = $u && (int)$u['ativo'] === 1;
        // Sessão aberta antes desta conferência existir (sem marca): adota a senha atual uma vez.
        if ($contaAtiva && !isset($_SESSION['marca_senha'])) $_SESSION['marca_senha'] = marca_senha((string)$u['senha']);
        $senhaTrocada = $contaAtiva && !hash_equals((string)$_SESSION['marca_senha'], marca_senha((string)$u['senha']));
        if (!$contaAtiva || $senhaTrocada) {
            unset($_SESSION['usuario_id'], $_SESSION['usuario_nome'], $_SESSION['usuario_tipo'], $_SESSION['login_em'], $_SESSION['marca_senha']);
            session_regenerate_id(true);
            flash('erro', $senhaTrocada
                ? 'Sua sessão foi encerrada porque a senha da conta foi alterada. Entre novamente com a nova senha.'
                : 'Sua sessão foi encerrada porque a conta foi desativada ou removida.');
        } else {
            $_SESSION['usuario_tipo'] = $u['tipo'];
            $_SESSION['usuario_nome'] = $u['nome'];
        }
    } catch (Throwable) {
        // Banco fora do ar: a própria página mostrará o aviso amigável ao consultar o banco.
    }
}

/** Guarda uma mensagem para ser exibida UMA vez, na próxima página (tipo: ok | erro | info). */
function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }

/** Retira a mensagem guardada por flash() (o cabeçalho do layout a exibe). */
function getFlash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }

/**
 * Inicia a sessão do usuário autenticado (novo ID de sessão e novo token CSRF, contra fixação de sessão).
 * $u é a linha de `usuarios`: a "marca" da senha guardada aqui é conferida por revalidar_sessao().
 */
function iniciar_sessao_usuario(array $u): void {
    session_regenerate_id(true);
    unset($_SESSION['csrf'], $_SESSION['marca_senha']);
    $_SESSION['usuario_id'] = (int)$u['id'];
    $_SESSION['usuario_nome'] = (string)$u['nome'];
    $_SESSION['usuario_tipo'] = (string)$u['tipo'];
    $_SESSION['login_em'] = time();
    if (isset($u['senha'])) $_SESSION['marca_senha'] = marca_senha((string)$u['senha']);
}

/**
 * "Marca" da senha atual: SHA-256 do hash gravado em usuarios.senha (a sessão não guarda o hash em si).
 * Se a senha mudar no banco, a marca deixa de bater e revalidar_sessao() encerra a sessão.
 */
function marca_senha(string $hashSenha): string { return hash('sha256', $hashSenha); }

/** O próprio usuário trocou a senha: atualiza a marca para ESTA sessão continuar valendo (as outras caem). */
function renovar_marca_senha(int $usuarioId, string $hashSenha): void {
    if (usuarioLogado() && (int)$_SESSION['usuario_id'] === $usuarioId) $_SESSION['marca_senha'] = marca_senha($hashSenha);
}

/** Encerra a sessão atual por completo (dados, cookie e ID). */
function encerrar_sessao(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?: 'Lax']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}
