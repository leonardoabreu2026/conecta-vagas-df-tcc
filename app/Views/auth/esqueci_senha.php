<?php
/**
 * Recuperação de senha (rota esqueci_senha.php) — versão demonstrativa, sem envio de e-mail.
 * Recebe de PasswordController::esqueci(): $enviado e $linkDemo (link exibido só com DEBUG em localhost).
 */
?>
<div class="form">
<h1>Recuperar senha</h1>
<div class="notice small"><b>Recurso demonstrativo:</b> este ambiente local não envia e-mails. O link de redefinição fica registrado no servidor, em <code>storage/logs/redefinicoes_senha.log</code><?=DEBUG ? ', e é mostrado nesta tela quando o acesso é feito pelo próprio computador (modo DEBUG)' : ''?>. Em produção, ele seria enviado para o e-mail da conta.</div>
<?php if ($enviado): ?>
    <div class="alert ok">Se o e-mail informado estiver cadastrado e ativo, um link de redefinição válido por <?=(int)UsuarioDAO::REDEFINICAO_MINUTOS?> minutos foi gerado.</div>
    <?php if ($linkDemo): ?>
        <div class="alert info">Link gerado (demonstração): <a href="<?=e($linkDemo)?>">redefinir minha senha</a></div>
    <?php endif; ?>
    <div class="form-actions"><a class="btn btn-outline" href="<?=url('login.php')?>">Voltar ao login</a></div>
<?php else: ?>
    <p class="muted">Informe o e-mail da sua conta para gerar um link de redefinição.</p>
    <form method="post" action="<?=url('esqueci_senha.php')?>">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <label for="esq-email">E-mail</label><input id="esq-email" type="email" name="email" required maxlength="255" autocomplete="email">
        <div class="form-actions"><button class="btn">Gerar link de redefinição</button><a class="btn btn-outline" href="<?=url('login.php')?>">Voltar</a></div>
    </form>
<?php endif; ?>
</div>
