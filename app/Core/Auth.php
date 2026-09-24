<?php
declare(strict_types=1);

/*
 * Autenticação e permissões: quem está logado, qual o tipo da conta
 * e as travas de acesso usadas no começo das ações dos controllers.
 *
 * Tipos de conta (usuarios.tipo): 'admin', 'empresa' e 'candidato'.
 * Os dados do usuário logado ficam na sessão: usuario_id, usuario_nome, usuario_tipo
 * (e marca_senha, conferida a cada requisição por revalidar_sessao(), em Session.php).
 */

function usuarioLogado(): bool { return isset($_SESSION['usuario_id']); }
function isAdmin(): bool { return ($_SESSION['usuario_tipo'] ?? '') === 'admin'; }
function isEmpresa(): bool { return ($_SESSION['usuario_tipo'] ?? '') === 'empresa'; }
function isCandidato(): bool { return ($_SESSION['usuario_tipo'] ?? '') === 'candidato'; }

/**
 * Exige login: sem sessão, volta para a tela de login com um aviso.
 * Não troca um aviso já pendente (ex.: "sessão encerrada porque a senha foi alterada", de revalidar_sessao()).
 */
function exigirLogin(): void { if (!usuarioLogado()) { if (empty($_SESSION['flash'])) flash('erro','Faça login para continuar.'); redirect('login.php'); } }

/** Exige login de administrador (outros tipos recebem 403). */
function exigirAdmin(): void { exigirLogin(); if (!isAdmin()) negar_acesso('Acesso restrito ao administrador.'); }

/** Recusa a requisição com a página de erro padrão do site (403; ou 404 para o que não existe). */
function negar_acesso(string $mensagem, int $codigo = 403): never {
    pagina_erro($codigo, $codigo === 404 ? 'Página não encontrada' : 'Acesso restrito', '<p>'.e($mensagem).'</p>');
}

/** Página inicial de cada tipo de conta depois do login: candidato → perfil; empresa/admin → painel. */
function destinoPainel(): string { return isAdmin() ? 'admin/index.php' : (isCandidato() ? 'view/perfil/index.php' : 'admin/index.php'); }

/** Link de saída com token (para usar em <a href>; o logout também aceita POST). */
function logout_url(): string { return url('view/usuario/logout.php?t='.csrf_token()); }
