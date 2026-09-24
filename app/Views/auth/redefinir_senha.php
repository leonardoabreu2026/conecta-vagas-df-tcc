<?php
/**
 * Nova senha a partir do link de redefinição (rota redefinir_senha.php?token=).
 * Recebe de PasswordController::redefinir(): $usuario (dono do token válido, ou null) e $token.
 */
?>
<div class="form">
<h1>Definir nova senha</h1>
<?php if (!$usuario): ?>
    <div class="alert erro">Este link de redefinição é inválido, já foi utilizado ou expirou.</div>
    <div class="form-actions"><a class="btn" href="<?=url('esqueci_senha.php')?>">Gerar novo link</a><a class="btn btn-outline" href="<?=url('login.php')?>">Voltar ao login</a></div>
<?php else: ?>
    <p class="muted">Conta: <b><?=e($usuario['email'])?></b></p>
    <form method="post" action="<?=url('redefinir_senha.php')?>">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="token" value="<?=e($token)?>">
        <label>Nova senha</label><input type="password" name="senha" minlength="6" maxlength="72" required autocomplete="new-password">
        <label>Confirme a nova senha</label><input type="password" name="senha_confirmacao" minlength="6" maxlength="72" required autocomplete="new-password">
        <div class="form-actions"><button class="btn">Salvar nova senha</button></div>
    </form>
<?php endif; ?>
</div>
