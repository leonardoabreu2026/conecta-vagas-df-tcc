<?php
/**
 * CRUD de vagas + extração de vagas (rota admin/pages/vagas.php) — empresa (suas vagas) e administrador.
 * Recebe de EmpresaController::vagas(): $form, $extraido, $cats, $empresas, $lista, $imagens,
 * $isPremium e $dinheiro (formata valores).
 */
?>
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?php if (isEmpresa()): ?>
    <div style="margin-bottom: 16px;">
        <?php if ($isPremium): ?><span class="badge-pro" style="font-size:13px; padding:6px 14px;">💼 Empresa Premium — vagas ilimitadas e destaque</span>
        <?php else: ?><span class="tag" style="background:#e2e8f0; color:#475569;">Plano Básico: até 2 vagas ativas</span> <a href="<?=url('planos.php')?>" style="margin-left: 10px; font-weight:700; color:var(--blue);">Fazer upgrade</a><?php endif; ?>
    </div>
<?php endif; ?>
<h1>Vagas</h1>
<div class="form" style="max-width:none">
    <details class="extrator" <?=$extraido || !empty($form['id']) ? '' : 'open'?>>
        <summary>✨ Máquina de extração: envie o cartaz da vaga (imagem) ou cole o anúncio — o formulário é preenchido</summary>
        <form method="post" enctype="multipart/form-data" style="margin-top:10px" class="cartaz-form">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="ler_cartaz"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
            <?php if (isAdmin()): ?><input type="hidden" name="perfil_empresa_id" value="<?=(int)($form['perfil_empresa_id'] ?? 0)?>"><?php endif; ?>
            <label>📷 Cartaz da vaga (JPG, PNG ou WEBP, até 8 MB)</label>
            <input type="file" name="cartaz" accept="image/jpeg,image/png,image/webp" required>
            <?php if (!$ocrDisponivel): ?><p class="small" style="color:#b45309">Leitura de imagem indisponível neste servidor (Tesseract OCR não encontrado): o cartaz será salvo, mas os campos precisam ser preenchidos à mão.</p><?php endif; ?>
            <div class="form-actions"><button class="btn">Ler cartaz e preencher</button></div>
        </form>
        <p class="small muted" style="margin:6px 0 0">…ou cole o texto do anúncio:</p>
        <form method="post" style="margin-top:6px">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="extrair"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
            <?php if (isAdmin()): ?><input type="hidden" name="perfil_empresa_id" value="<?=(int)($form['perfil_empresa_id'] ?? 0)?>"><?php endif; ?>
            <textarea name="texto_anuncio" rows="6" placeholder="Ex.: VAGA: Vendedor Interno — Taguatinga&#10;Salário: R$ 3.000 a R$ 5.500 + comissões&#10;Requisitos: experiência com vendas, ensino médio&#10;Benefícios: VT + VR"><?=e(post_str('texto_anuncio'))?></textarea>
            <div class="form-actions"><button class="btn btn-outline">Extrair dados do anúncio</button></div>
        </form>
        <?php if ($extraido): ?>
            <div class="alert info" style="margin:10px 0 0" id="relatorio-extracao">
                <b>Dados extraídos<?=isset($extraido['confianca']) ? ' do cartaz (leitura com '.(int)$extraido['confianca'].'% de confiança)' : ''?></b> — revise abaixo e clique em salvar.
                <?php if ($extraido['competencias']): ?><br>Competências que a vaga exige (usadas no match): <b><?=e(implode(', ', $extraido['competencias']))?></b>.<?php endif; ?>
                <?php foreach ($extraido['avisos'] ?? [] as $a): ?><br>⚠️ <?=e($a)?><?php endforeach; ?>
                <?php if (!empty($extraido['texto_ocr'])): ?><details style="margin-top:6px"><summary>Texto lido no cartaz</summary><pre class="small" style="white-space:pre-wrap;max-height:220px;overflow:auto"><?=e($extraido['texto_ocr'])?></pre></details><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($parecida): ?>
            <div class="alert erro" style="margin:10px 0 0">Já existe uma vaga aberta igual: <a href="<?=url('vaga.php?id='.(int)$parecida['id'])?>" target="_blank">#<?=(int)$parecida['id']?> — <?=e($parecida['titulo'])?> (<?=e($parecida['empresa_nome'] ?? '')?>)</a>. Confira antes de publicar.</div>
        <?php endif; ?>
    </details>

    <h3><?=!empty($form['id']) ? 'Editar vaga' : 'Cadastrar vaga'?></h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
        <div class="form-grid">
            <?php if (isAdmin()): ?>
                <div class="full"><label>Empresa</label><select name="perfil_empresa_id" required><option value="">Selecione</option><?php foreach ($empresas as $ep): ?><option value="<?=(int)$ep['id']?>" <?=(int)($form['perfil_empresa_id'] ?? 0) === (int)$ep['id'] ? 'selected' : ''?>><?=e($ep['nome_fantasia'] ?: $ep['nome'])?></option><?php endforeach; ?></select></div>
            <?php endif; ?>
            <div><label>Título</label><input name="titulo" required maxlength="255" value="<?=e($form['titulo'])?>"></div>
            <div><label>Empresa anunciante <small class="muted">(se a vaga for de outra empresa)</small></label><input name="anunciante" maxlength="150" value="<?=e($form['anunciante'] ?? '')?>"></div>
            <div class="full"><label>Contato do anúncio <small class="muted">(WhatsApp, telefone ou e-mail)</small></label><input name="contato" maxlength="255" value="<?=e($form['contato'] ?? '')?>"></div>
            <div><label>Área (categoria)</label><select name="categoria_id"><option value="">Sem categoria</option><?php foreach ($cats as $c): ?><option value="<?=(int)$c['id']?>" <?=(int)($form['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?></option><?php endforeach; ?></select></div>
            <div><label>Contratação</label><select name="tipo_vaga"><?php foreach (VagaDAO::TIPOS as $x): ?><option value="<?=$x?>" <?=$form['tipo_vaga'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label>Nível</label><select name="nivel_experiencia"><?php foreach (VagaDAO::NIVEIS as $x): ?><option value="<?=$x?>" <?=$form['nivel_experiencia'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label>Modelo</label><select name="remoto"><?php foreach (VagaDAO::MODELOS as $x): ?><option value="<?=$x?>" <?=$form['remoto'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label>Status</label><select name="status"><?php foreach (VagaDAO::STATUS as $x): ?><option value="<?=$x?>" <?=$form['status'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label>Cidade</label><input name="cidade" maxlength="100" value="<?=e($form['cidade'])?>"></div>
            <div><label>UF</label><input name="uf" maxlength="2" value="<?=e($form['uf'])?>"></div>
            <div><label>Salário mínimo (R$)</label><input name="salario_minimo" inputmode="decimal" value="<?=e($dinheiro($form['salario_minimo']))?>" placeholder="Ex.: 1.900,00"></div>
            <div><label>Salário máximo (R$)</label><input name="salario_maximo" inputmode="decimal" value="<?=e($dinheiro($form['salario_maximo']))?>" placeholder="Vazio = a combinar"></div>
            <div><label>Inscrições até</label><input type="date" name="data_expiracao" value="<?=e($form['data_expiracao'])?>"></div>
            <div><label>Imagem (caminho)</label><input name="imagem" list="imgs-vaga" maxlength="255" value="<?=e($form['imagem'])?>"><datalist id="imgs-vaga"><?php foreach ($imagens as $i): ?><option value="<?=e($i)?>"><?php endforeach; ?></datalist></div>
            <div class="full"><label>…ou envie uma imagem</label><input type="file" name="imagem_arquivo" accept="image/jpeg,image/png,image/webp"></div>
            <div class="full check" style="background: <?=$isPremium ? '#fefce8' : '#f8fafc'?>; border: 1px solid <?=$isPremium ? '#fde047' : 'var(--line)'?>; padding: 14px; border-radius: 12px;">
                <input type="checkbox" name="destaque" value="1" id="chk_destaque" <?=!empty($form['destaque']) ? 'checked' : ''?> <?=$isPremium ? '' : 'disabled'?>>
                <label for="chk_destaque" style="margin:0"><strong>⭐ Destacar esta vaga no topo (recurso Premium)</strong><br>
                <span class="muted" style="font-size: 13px;"><?=$isPremium ? 'A vaga ganha borda dourada e aparece primeiro na busca.' : 'Disponível no Plano Empresa Premium. <a href="'.url('planos.php').'">Ativar</a>'?></span></label>
            </div>
            <div class="full"><label>Descrição / atividades</label><textarea name="descricao" rows="4"><?=e($form['descricao'])?></textarea></div>
            <div class="full"><label>Requisitos <small class="muted">— as competências daqui alimentam o match</small></label><textarea name="requisitos" rows="4"><?=e($form['requisitos'])?></textarea></div>
            <div class="full"><label>Benefícios</label><textarea name="beneficios" rows="3"><?=e($form['beneficios'])?></textarea></div>
        </div>
        <?php if (!empty($form['imagem']) && str_starts_with((string)$form['imagem'], 'assets/uploads/')): ?><p class="small muted"><img src="<?=e(url($form['imagem']))?>" alt="Cartaz enviado" style="max-height:160px;border-radius:8px;vertical-align:middle"> Cartaz enviado (será a imagem da vaga).</p><?php endif; ?>
        <?php if ($parecida): ?><div class="check"><input type="checkbox" name="publicar_duplicada" value="1" id="dup"><label for="dup">Publicar mesmo assim (é outra vaga)</label></div><?php endif; ?>
        <div class="form-actions"><button class="btn">Salvar vaga</button><?php if (!empty($form['id'])): ?><a class="btn btn-outline" href="<?=url('admin/pages/vagas.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<div class="table-wrap" style="margin-top:20px"><table class="table">
    <tr><th>Vaga</th><?php if (isAdmin()): ?><th>Empresa</th><?php endif; ?><th>Status</th><th>Candidaturas</th><th>Visualizações</th><th>Ações</th></tr>
    <?php foreach ($lista as $x): ?>
    <tr>
        <td><?=e($x['titulo'])?><?=$x['destaque'] ? ' <span class="badge-vip" style="font-size:10px;">⭐</span>' : ''?><br><small class="meta"><?=e($x['categoria_nome'] ?? 'Sem categoria')?> · <?=e($x['cidade'])?>/<?=e($x['uf'])?></small></td>
        <?php if (isAdmin()): ?><td><?=e($x['empresa_nome'] ?? '')?></td><?php endif; ?>
        <td><?=e(rotulo($x['status']))?><?=$x['data_expiracao'] && $x['data_expiracao'] < date('Y-m-d') ? ' <small style="color:#dc2626">(expirada)</small>' : ''?></td>
        <td><?php if (isset($x['total_candidaturas'])): ?><a href="<?=url('admin/pages/candidaturas.php?vaga_id='.(int)$x['id'])?>"><?=(int)$x['total_candidaturas']?></a><?php else: ?><a href="<?=url('admin/pages/candidaturas.php?vaga_id='.(int)$x['id'])?>">ver</a><?php endif; ?></td>
        <td><?=(int)$x['visualizacoes']?></td>
        <td class="actions">
            <a class="btn btn-sm btn-outline" href="?edit=<?=(int)$x['id']?>">Editar</a>
            <a class="btn btn-sm" href="<?=url('vaga.php?id='.(int)$x['id'])?>">Ver</a>
            <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?=(int)$x['id']?>"><button class="btn btn-sm btn-danger" data-confirm="Excluir a vaga e todas as candidaturas dela?">Excluir</button></form>
        </td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php if (!$lista): ?><div class="empty">Nenhuma vaga cadastrada ainda. Use a extração acima para publicar a primeira em segundos.</div><?php endif; ?>
