<?php
/**
 * Formulário de cadastro (rota cadastro.php): cria conta de candidato ou empresa.
 * old()/post_str() repreenchem os campos quando o envio tem erro.
 */
?>
<div class="form">
<h1>Criar sua conta</h1><p class="muted">O cadastro cria a conta e já abre o perfil para você completar as informações.</p>
<form method="post" action="<?=url('cadastro.php')?>">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="form-grid">
<div class="full"><label>Nome completo / responsável</label><input name="nome" required maxlength="255" value="<?=old('nome')?>"></div>
<div><label>E-mail</label><input type="email" name="email" required maxlength="255" autocomplete="email" value="<?=old('email')?>"></div>
<div><label>Telefone</label><input name="telefone" maxlength="30" autocomplete="tel" value="<?=old('telefone')?>"></div>
<div><label>Senha</label><input type="password" name="senha" minlength="6" maxlength="72" required autocomplete="new-password"></div>
<div><label>Tipo de conta</label><select name="tipo"><option value="candidato" <?=post_str('tipo')!=='empresa'?'selected':''?>>Candidato (quero uma vaga)</option><option value="empresa" <?=post_str('tipo')==='empresa'?'selected':''?>>Empresa (quero contratar)</option></select></div>
</div>
<div class="check"><input type="checkbox" name="aceite_lgpd" value="1" required id="lgpd" <?=isset($_POST['aceite_lgpd'])?'checked':''?>><label for="lgpd">Li e aceito o <a href="<?=url('contrato.php')?>" target="_blank">termo de autorização e privacidade</a>.</label></div>
<div class="form-actions"><button class="btn btn-gold">Cadastrar e montar perfil</button></div>
</form></div>
