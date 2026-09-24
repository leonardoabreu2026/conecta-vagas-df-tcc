<?php
/**
 * CRUD de cursos/e-books + extração de cursos (rota admin/pages/cursos.php) — só administrador.
 * Recebe de AdminController::cursos(): $form, $extraido, $cats, $lista, $porTipo, $filtroTipo, $busca, $imagens,
 * $promptPesquisa (prompt para a IA de pesquisa) e $importacao (fichas lidas, aguardando confirmação).
 */
$filtrosLista = ['tipo' => $filtroTipo, 'q' => $busca];
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Cursos e e-books', 'Cursos, e-books e vídeos gratuitos. Cole a divulgação e a máquina de extração preenche o formulário; publique ou oculte pela lista.')?>
<div class="form" style="max-width:none">
    <details class="extrator" id="importar" <?=$importacao ? 'open' : ''?>>
        <summary>Importar vários de uma vez: pesquise com IA (Perplexity, ChatGPT) e cole a resposta aqui</summary>
        <ol class="imp-passos">
            <li><b>Copie o prompt</b> e cole no Perplexity. Ele pesquisa cursos e e-books reais e responde em fichas (título, tipo, instituição, modalidade, cidade, nível, carga horária, preço, área, link e descrição).
                <div class="imp-prompt">
                    <label for="imp-prompt" class="sr-only">Prompt de pesquisa</label>
                    <textarea id="imp-prompt" rows="6" readonly><?=e($promptPesquisa)?></textarea>
                    <button type="button" class="btn btn-sm" data-copiar="<?=e($promptPesquisa)?>" data-copiado="Prompt copiado!"><span>Copiar prompt</span></button>
                </div>
            </li>
            <li><b>Cole a resposta inteira</b> abaixo e clique em "Ler fichas". A máquina de extração lê cada ficha; nada é salvo ainda.
                <form method="post">
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="importar_ler">
                    <label for="imp-texto" class="sr-only">Resposta da pesquisa</label>
                    <textarea id="imp-texto" name="texto_lote" rows="8" placeholder="Título: Excel Básico&#10;Tipo: Curso&#10;Instituição: Fundação Bradesco&#10;Modalidade: EAD&#10;...&#10;Link: https://www.ev.org.br/...&#10;---&#10;Título: ..."></textarea>
                    <div class="form-actions"><button class="btn btn-outline">Ler fichas</button></div>
                </form>
            </li>
            <li><b>Confira a prévia</b> e cadastre os marcados. Cada um entra publicado, com a capa padrão da sua área.</li>
        </ol>
        <?php if ($importacao): ?>
        <form method="post" class="imp-previa">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="importar_salvar">
            <div class="table-wrap"><table class="table">
                <tr><th><span class="sr-only">Importar</span></th><th>Título</th><th>Tipo</th><th>Instituição</th><th>Modalidade</th><th>Área</th><th>Link</th><th>Situação</th></tr>
                <?php foreach ($importacao as $i => $it): $ruim = !empty($it['problemas']); ?>
                <tr>
                    <td><input type="checkbox" name="itens[]" value="<?=(int)$i?>" id="imp-<?=(int)$i?>" <?=$ruim ? 'disabled' : 'checked'?> aria-label="Importar <?=e($it['titulo'])?>"></td>
                    <td><label for="imp-<?=(int)$i?>"><?=e($it['titulo'] ?: '—')?></label><?php if ($it['duracao'] !== ''): ?><br><small class="meta"><?=e($it['duracao'])?> · <?=e(rotulo($it['nivel']))?></small><?php endif; ?></td>
                    <td><?=e(rotulo($it['tipo']))?></td>
                    <td><?=e($it['instituicao'] ?: '—')?></td>
                    <td><?=e(rotulo($it['modalidade']))?><?=$it['gratuito'] ? '' : '<br><small class="meta">pago</small>'?></td>
                    <td><?=e($it['categoria'] ?: 'Sem categoria')?></td>
                    <td class="meta"><?=$it['url'] !== '' ? '<a href="'.e($it['url']).'" target="_blank" rel="noopener">'.e(parse_url($it['url'], PHP_URL_HOST) ?: $it['url']).'<span class="sr-only"> (abre em nova aba)</span></a>' : '—'?></td>
                    <td><?=$ruim ? painel_status('bloqueado', ucfirst(implode(', ', $it['problemas']))) : ($it['alerta'] !== '' ? painel_status('pausada', ucfirst($it['alerta'])) : painel_status('ativa', 'Pronto'))?></td>
                </tr>
                <?php endforeach; ?>
            </table></div>
            <div class="form-actions"><button class="btn">Cadastrar marcados</button>
                <button class="btn btn-outline" name="acao" value="importar_cancelar" formnovalidate>Descartar prévia</button></div>
        </form>
        <?php endif; ?>
    </details>

    <details class="extrator" <?=$extraido || !empty($form['id']) || $importacao ? '' : 'open'?>>
        <summary>Máquina de extração: cole o texto de divulgação e o formulário é preenchido</summary>
        <form method="post" style="margin-top:10px">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="extrair"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
            <label for="c-texto">Texto de divulgação do curso ou e-book</label>
            <textarea id="c-texto" name="texto_anuncio" rows="5" placeholder="Ex.: EXCEL AVANÇADO — Curso online e gratuito da Fundação Bradesco com certificado. Carga horária: 12 horas. https://www.ev.org.br/..."><?=e(post_str('texto_anuncio'))?></textarea>
            <div class="form-actions"><button class="btn btn-outline">Extrair dados do texto</button></div>
        </form>
        <?php if ($extraido): ?>
            <div class="alert info" style="margin:10px 0 0">Dados extraídos — revise abaixo antes de salvar.<?php if ($extraido['competencias']): ?> Competências que o curso desenvolve: <b><?=e(implode(', ', $extraido['competencias']))?></b>.<?php endif; ?></div>
        <?php endif; ?>
    </details>

    <h2 class="pn-form-titulo" id="form-curso"><?=!empty($form['id']) ? 'Editar conteúdo #'.(int)$form['id'] : 'Novo conteúdo'?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label for="c-titulo">Título</label><input id="c-titulo" name="titulo" required maxlength="255" value="<?=e($form['titulo'])?>"></div>
            <div><label for="c-inst">Instituição</label><input id="c-inst" name="instituicao" maxlength="255" value="<?=e($form['instituicao'])?>"></div>
            <div><label for="c-cat">Categoria</label><select id="c-cat" name="categoria_id"><option value="">Sem categoria</option><?php foreach ($cats as $c): ?><option value="<?=(int)$c['id']?>" <?=(int)($form['categoria_id'] ?? 0) === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?><?=$c['ativo'] ? '' : ' (inativa)'?></option><?php endforeach; ?></select></div>
            <div><label for="c-tipo">Formato</label><select id="c-tipo" name="tipo"><?php foreach (CursoDAO::TIPOS as $x): ?><option value="<?=$x?>" <?=$form['tipo'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="c-mod">Modalidade</label><select id="c-mod" name="modalidade"><?php foreach (CursoDAO::MODALIDADES as $x): ?><option value="<?=$x?>" <?=$form['modalidade'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="c-nivel">Nível</label><select id="c-nivel" name="nivel"><?php foreach (CursoDAO::NIVEIS as $x): ?><option value="<?=$x?>" <?=$form['nivel'] === $x ? 'selected' : ''?>><?=e(rotulo($x))?></option><?php endforeach; ?></select></div>
            <div><label for="c-dur">Duração / carga horária</label><input id="c-dur" name="duracao" maxlength="50" value="<?=e($form['duracao'])?>" placeholder="Ex.: 12 horas"></div>
            <div><label for="c-preco">Preço (se pago)</label><input id="c-preco" name="preco" inputmode="decimal" value="<?=e($form['preco'] !== null && $form['preco'] !== '' ? number_format((float)$form['preco'], 2, ',', '.') : '')?>" placeholder="Ex.: 49,90"></div>
            <div class="full"><label for="c-url">Link oficial</label><input id="c-url" name="url" type="url" maxlength="500" value="<?=e($form['url'])?>" placeholder="https://"></div>
            <div><label for="c-img">Imagem (caminho)</label><input id="c-img" name="imagem" list="imgs-curso" maxlength="255" value="<?=e($form['imagem'])?>"><datalist id="imgs-curso"><?php foreach ($imagens as $i): ?><option value="<?=e($i)?>"><?php endforeach; ?></datalist></div>
            <div><label for="c-arq">…ou envie uma imagem</label><input id="c-arq" type="file" name="imagem_arquivo" accept="image/jpeg,image/png,image/webp"></div>
            <div class="full"><label for="c-desc">Descrição</label><textarea id="c-desc" name="descricao" rows="4"><?=e($form['descricao'])?></textarea></div>
        </div>
        <div class="check"><input type="checkbox" name="gratuito" value="1" id="gratuito" <?=(int)$form['gratuito'] ? 'checked' : ''?>><label for="gratuito">Gratuito</label></div>
        <div class="check"><input type="checkbox" name="ativo" value="1" id="ativo" <?=(int)$form['ativo'] ? 'checked' : ''?>><label for="ativo">Publicado (aparece para os usuários)</label></div>
        <div class="form-actions"><button class="btn">Salvar conteúdo</button><?php if (!empty($form['id']) || $extraido): ?><a class="btn btn-outline" href="<?=url('admin/pages/cursos.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<form class="filtros" method="get" style="grid-template-columns:2fr 1fr auto">
    <input name="q" placeholder="Buscar por título, descrição ou instituição" value="<?=e($busca)?>" aria-label="Buscar por título, descrição ou instituição">
    <select name="tipo" aria-label="Formato"><option value="">Todos os formatos</option><?php foreach (CursoDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=$filtroTipo === $t ? 'selected' : ''?>><?=e(rotulo($t))?> (<?=(int)($porTipo[$t] ?? 0)?>)</option><?php endforeach; ?></select>
    <button class="btn">Filtrar</button>
</form>
<div class="pn-contagem"><h2>Conteúdos cadastrados</h2><span><?=gf_num(count($lista))?> <?=gf_plural(count($lista), 'conteúdo', 'conteúdos')?><?=$filtroTipo !== '' || $busca !== '' ? ' · <a href="'.e(url('admin/pages/cursos.php')).'">limpar filtros</a>' : ''?></span></div>
<div class="table-wrap"><table class="table">
    <tr><th>Título</th><th>Formato</th><th>Categoria</th><th>Instituição</th><th>Situação</th><th>Ações</th></tr>
    <?php foreach ($lista as $x): ?>
    <tr>
        <td><?=e($x['titulo'])?></td><td><?=e(rotulo($x['tipo']))?></td><td><?=e($x['categoria_nome'] ?? '—')?></td><td><?=e($x['instituicao'] ?? '')?></td>
        <td><?=$x['ativo'] ? painel_status('publicado', 'Publicado') : painel_status('oculto', 'Oculto')?></td>
        <td><div class="actions">
            <a class="btn btn-sm" href="<?=url('curso.php?id='.(int)$x['id'])?>" target="_blank" rel="noopener">Ver<span class="sr-only"> <?=e($x['titulo'])?> (abre em nova aba)</span></a>
            <a class="btn btn-sm btn-outline" href="?edit=<?=(int)$x['id']?>#form-curso">Editar</a>
            <?=$x['ativo'] ? painel_acao('desativar', (int)$x['id'], 'Ocultar', 'btn-outline', '', $filtrosLista) : painel_acao('ativar', (int)$x['id'], 'Publicar', 'btn-outline', '', $filtrosLista)?>
            <?=painel_acao('excluir', (int)$x['id'], 'Excluir', 'btn-danger', 'Excluir este conteúdo? Esta ação não pode ser desfeita.', $filtrosLista)?>
        </div></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php if (!$lista): ?><div class="empty"><?=$filtroTipo !== '' || $busca !== '' ? 'Nenhum conteúdo com esses filtros.' : 'Nenhum curso ou e-book cadastrado ainda. Use a extração acima para publicar o primeiro.'?></div><?php endif; ?>
</div>
