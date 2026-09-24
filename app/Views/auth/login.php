<?php
/**
 * Formulário de login (rota login.php). Mostra as contas de teste do ambiente local.
 */
?>
<div class="form">
<h1>Entrar na plataforma</h1><p class="muted">Use seu e-mail e senha cadastrados.</p>
<div class="notice small"><b>Contas de teste:</b><br>Admin: admin@conectavagas.com / Admin@123<br>Empresa: empresa@conectavagas.com / Empresa@123<br>Candidato: candidato@conectavagas.com / Candidato@123</div>
<form method="post" action="<?=url('login.php')?>">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<label>E-mail</label><input type="email" name="email" required maxlength="255" autocomplete="email" value="<?=old('email')?>">
<label>Senha</label><input type="password" name="senha" required autocomplete="current-password">
<div class="form-actions"><button class="btn">Entrar</button><a class="btn btn-outline" href="<?=url('cadastro.php')?>">Criar conta</a></div>
<p class="small" style="margin-top:12px"><a href="<?=url('esqueci_senha.php')?>">Esqueci minha senha</a></p>
</form></div>
