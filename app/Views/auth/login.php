<?php
/**
 * Formulário de login (rota login.php). As contas de teste aparecem só no ambiente local (DEBUG).
 */
?>
<div class="form">
<h1>Entrar na plataforma</h1><p class="muted">Use seu e-mail e senha cadastrados.</p>
<?php if (DEBUG): ?><div class="notice small"><b>Contas de teste (ambiente local):</b><br>Admin: admin@conectavagas.com / Admin@123<br>Empresa: empresa@conectavagas.com / Empresa@123<br>Candidato: candidato@conectavagas.com / Candidato@123</div><?php endif; ?>
<form method="post" action="<?=url('login.php')?>">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label for="login-email">E-mail</label><input id="login-email" type="email" name="email" required maxlength="255" autocomplete="email" value="<?=old('email')?>">
<label for="login-senha">Senha</label>
<span class="cv-senha"><input id="login-senha" type="password" name="senha" required autocomplete="current-password"><button type="button" class="cv-olho" data-mostrar="login-senha" aria-label="Mostrar senha"><?=icone('olho', 18)?></button></span>
<div class="form-actions"><button class="btn">Entrar</button><a class="btn btn-outline" href="<?=url('cadastro.php')?>">Criar conta</a></div>
<p class="small" style="margin-top:12px"><a href="<?=url('esqueci_senha.php')?>">Esqueci minha senha</a></p>
</form></div>
