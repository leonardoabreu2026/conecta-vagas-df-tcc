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
<div class="full"><label for="cad-nome">Nome completo / responsável</label><input id="cad-nome" name="nome" required maxlength="255" autocomplete="name" value="<?=old('nome')?>"></div>
<div><label for="cad-email">E-mail</label><input id="cad-email" type="email" name="email" required maxlength="255" autocomplete="email" value="<?=old('email')?>"></div>
<div><label for="cad-tel">Telefone</label><input id="cad-tel" name="telefone" maxlength="30" autocomplete="tel" value="<?=old('telefone')?>"></div>
<div><label for="cad-senha">Senha <small class="muted">(mínimo 6 caracteres)</small></label><span class="cv-senha"><input id="cad-senha" type="password" name="senha" minlength="6" maxlength="72" required autocomplete="new-password"><button type="button" class="cv-olho" data-mostrar="cad-senha" aria-label="Mostrar senha"><?=icone('olho', 18)?></button></span></div>
<div><label for="cad-tipo">Tipo de conta</label><select id="cad-tipo" name="tipo"><option value="candidato" <?=post_str('tipo')!=='empresa'?'selected':''?>>Candidato (quero uma vaga)</option><option value="empresa" <?=post_str('tipo')==='empresa'?'selected':''?>>Empresa (quero contratar)</option></select></div>
</div>
<div class="check"><input type="checkbox" name="aceite_lgpd" value="1" required id="lgpd" <?=isset($_POST['aceite_lgpd'])?'checked':''?>><label for="lgpd">Li e aceito o <a href="<?=url('contrato.php')?>" target="_blank" rel="noopener">termo de autorização e privacidade<span class="sr-only"> (abre em nova aba)</span></a>.</label></div>
<div class="form-actions"><button class="btn btn-gold">Cadastrar e montar perfil</button></div>
</form></div>
