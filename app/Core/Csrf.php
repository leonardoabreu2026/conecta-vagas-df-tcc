<?php
declare(strict_types=1);

/*
 * Proteção contra CSRF (Cross-Site Request Forgery).
 *
 * Todo formulário POST leva um campo oculto "csrf" com um token aleatório guardado
 * na sessão. Ao receber o POST, validar_csrf() confere se o token bate: assim, um
 * site de terceiros não consegue enviar formulários em nome do usuário logado.
 */

/** Token da sessão atual (criado na primeira vez que é pedido). */
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }

/** Campo oculto com o token, pronto para colar dentro de um <form method="post">. */
function csrf_campo(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }

/** Confere o token enviado no POST; se não bater, mostra uma página de erro (HTTP 403) e encerra. */
function validar_csrf(): void {
    $token=$_POST['csrf']??'';
    if (!is_string($token) || $token === '' || !hash_equals((string)($_SESSION['csrf']??''),$token)) {
        // 403 (e não 419): o Apache não conhece o código 419 e o transforma em 500.
        pagina_erro(403, 'Sessão expirada', 'O token de segurança do formulário é inválido ou expirou. Volte, atualize a página e tente novamente.');
    }
}
