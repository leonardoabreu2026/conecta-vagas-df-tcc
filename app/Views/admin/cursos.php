<?php
/**
 * CRUD de cursos/e-books + extração de cursos (rota admin/pages/cursos.php) — só administrador.
 * Recebe de AdminController::cursos(): $form, $extraido, $cats, $lista e $imagens.
 */
?>
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<h1>Cursos e e-books</h1>
<div class="form" style="max-width:none">
    <details class="extrator" <?=$extraido ? '' : 'open'?>>
        <summary>✨ Extração de cursos: cole o texto de divulgação e o formulário é preenchido</summary>
        <form method="post" style="margin-top:10px">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="extrair"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
            <textarea name="texto_anuncio" rows="5" placeholder="Ex.: EXCEL AVANÇADO — Curso online e gratuito da Fundação Bradesco com certificado. Carga horária: 12 horas. https://www.ev.org.br/..."><?=e(post_str('texto_anuncio'))?></textarea>
            <div class="form-actions"><button class="btn btn-outline">Extrair dados do texto</button></div>
        </form>
        <?php if ($extraido): ?>
            <div class="alert info" style="margin:10px 0 0">Dados extraídos — revise abaixo antes de salvar.<?php if ($extraido['competencias']): ?> Competências que o curso desenvolve: <b><?=e(implode(', ', $extraido['competencias']))?></b>.<?php endif; ?></div>
        <?php endif; ?>
    </details>

    <h3><?=!empty($form['id']) ? 'Editar conteúdo' : 'Novo conteúdo'?></h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label>Título</label><input name="titulo" required maxlength="255" value="<?=e($form['titulo'])?>"></div>
            <div><label>Instituição</label><input name="instituicao" maxlength="255" value="<?=e($form['instituicao'])?>"></div>
            <div><label>Categoria</label><select name="categoria_id"><option value="">Sem categoria</option><?php foreach ($cats as $c): ?><option value="<?=(int)$c['id']?>" <?=(int)($form['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?><?=$c['ativo'] ? '' : ' (inativa)'?></option><?php endforeach; ?></select></div>
            <div><label>Formato</label><select name="tipo"><?php foreach (CursoDAO::TIPOS as $x): ?><option value="<?=$x?>" <?=$form['tipo'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label>Modalidade</label><select name="modalidade"><?php foreach (CursoDAO::MODALIDADES as $x): ?><option value="<?=$x?>" <?=$form['modalidade'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label>Nível</label><select name="nivel"><?php foreach (CursoDAO::NIVEIS as $x): ?><option value="<?=$x?>" <?=$form['nivel'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label>Duração / carga horária</label><input name="duracao" maxlength="50" value="<?=e($form['duracao'])?>" placeholder="Ex.: 12 horas"></div>
            <div><label>Preço (se pago)</label><input name="preco" inputmode="decimal" value="<?=e($form['preco'] !== null ? number_format((float)$form['preco'], 2, ',', '.') : '')?>" placeholder="Ex.: 49,90"></div>
            <div class="full"><label>Link oficial</label><input name="url" type="url" maxlength="500" value="<?=e($form['url'])?>" placeholder="https://"></div>
            <div><label>Imagem (caminho)</label><input name="imagem" list="imgs-curso" maxlength="255" value="<?=e($form['imagem'])?>"><datalist id="imgs-curso"><?php foreach ($imagens as $i): ?><option value="<?=e($i)?>"><?php endforeach; ?></datalist></div>
            <div><label>…ou envie uma imagem</label><input type="file" name="imagem_arquivo" accept="image/jpeg,image/png,image/webp"></div>
            <div class="full"><label>Descrição</label><textarea name="descricao" rows="4"><?=e($form['descricao'])?></textarea></div>
        </div>
        <div class="check"><input type="checkbox" name="gratuito" value="1" id="gratuito" <?=(int)$form['gratuito'] ? 'checked' : ''?>><label for="gratuito">Gratuito</label></div>
        <div class="check"><input type="checkbox" name="ativo" value="1" id="ativo" <?=(int)$form['ativo'] ? 'checked' : ''?>><label for="ativo">Publicado (aparece para os usuários)</label></div>
        <div class="form-actions"><button class="btn">Salvar conteúdo</button><?php if (!empty($form['id'])): ?><a class="btn btn-outline" href="<?=url('admin/pages/cursos.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<div class="table-wrap" style="margin-top:20px"><table class="table">
    <tr><th>Título</th><th>Formato</th><th>Categoria</th><th>Instituição</th><th>Status</th><th>Ações</th></tr>
    <?php foreach ($lista as $x): ?>
    <tr>
        <td><?=e($x['titulo'])?></td><td><?=e(rotulo($x['tipo']))?></td><td><?=e($x['categoria_nome'] ?? '—')?></td><td><?=e($x['instituicao'])?></td><td><?=$x['ativo'] ? 'Publicado' : 'Oculto'?></td>
        <td class="actions">
            <a class="btn btn-sm btn-outline" href="?edit=<?=(int)$x['id']?>">Editar</a>
            <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?=(int)$x['id']?>"><button class="btn btn-sm btn-danger" data-confirm="Excluir este conteúdo?">Excluir</button></form>
        </td>
    </tr>
    <?php endforeach; ?>
</table></div>
