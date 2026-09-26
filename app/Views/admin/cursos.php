<?php
/**
 * CRUD de cursos/e-books + extração de cursos (rota admin/pages/cursos.php) — só administrador.
 * Recebe de AdminController::cursos(): $form, $extraido, $cats, $lista (página atual), $totalLista, $pagina, $paginas,
 * $porTipo, $filtroTipo, $filtroCat, $filtroSituacao, $busca, $ordem, $dir, $todos, $publicados, $imagens,
 * $promptPesquisa (prompt da pesquisa guiada), $pesquisa (formato/área/fonte/quantidade escolhidos), $cobertura
 * (conteúdo por área e por fonte, lacunas), $pesquisaAberta, $semPadrao e $importacao (fichas lidas, aguardando confirmação).
 */
$novoRotulo = ['curso' => 'Novo curso', 'ebook' => 'Novo e-book', 'video' => 'Novo vídeo'];
$botoesNovo = '';
foreach ($novoRotulo as $t => $r) $botoesNovo .= '<a class="btn btn-sm'.($t === 'curso' ? '' : ' btn-outline').'" href="'.e(url('admin/pages/cursos.php').painel_qs(['novo' => $t])).'#form-curso">+ '.e($r).'</a>';
$porTipoTotal = array_count_values(array_column($todos, 'tipo'));
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Cursos e e-books', 'Cadastre, veja, edite, publique/oculte e exclua cursos, e-books e vídeos. Cole a divulgação e a máquina de extração preenche o formulário.', $botoesNovo)?>
<div class="pn-kpis">
    <?=painel_kpi('Cursos', gf_num($porTipoTotal['curso'] ?? 0), 'cadastrados', 'cursos', 'admin/pages/cursos.php?tipo=curso#lista-cursos')?>
    <?=painel_kpi('E-books', gf_num($porTipoTotal['ebook'] ?? 0), 'cadastrados', 'ebooks', 'admin/pages/cursos.php?tipo=ebook#lista-cursos')?>
    <?=painel_kpi('Vídeos', gf_num($porTipoTotal['video'] ?? 0), 'cadastrados', 'play', 'admin/pages/cursos.php?tipo=video#lista-cursos')?>
    <?=painel_kpi('Publicados', gf_num($publicados), gf_num(count($todos) - $publicados).' oculto(s)', 'painel', 'admin/pages/cursos.php?situacao=publicado#lista-cursos')?>
</div>
<div class="form" style="max-width:none">
    <details class="extrator" id="importar" <?=$importacao || $pesquisaAberta ? 'open' : ''?>>
        <summary>Novos links: pesquisa guiada com IA (Perplexity, ChatGPT) e importação de vários de uma vez</summary>
        <ol class="imp-passos">
            <li id="pesquisa"><b>Direcione a pesquisa.</b> Escolha o que buscar; sem área escolhida, o prompt mira as áreas com <b>menos conteúdo</b> e já leva a lista dos links cadastrados para a IA não repetir.
                <form method="get" action="#pesquisa" class="filtros imp-direcao" style="grid-template-columns:1fr 1.4fr 1.4fr .7fr auto;margin:10px 0">
                    <?php foreach (['tipo', 'q', 'categoria_id', 'situacao', 'ordem', 'dir'] as $k): if (get_str($k) !== ''): ?><input type="hidden" name="<?=$k?>" value="<?=e(get_str($k))?>"><?php endif; endforeach; ?>
                    <select name="p_formato" aria-label="Formato a pesquisar"><option value="">Cursos e e-books</option><?php foreach (CursoDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=$pesquisa['formato'] === $t ? 'selected' : ''?>>Só <?=e(mb_strtolower(pt_secao_formato($t)[0]))?></option><?php endforeach; ?></select>
                    <select name="p_area" aria-label="Área a pesquisar"><option value="">Áreas com menos conteúdo</option><?php foreach ($cobertura['areas'] as $a => $n): ?><option value="<?=e($a)?>" <?=$pesquisa['area'] === $a ? 'selected' : ''?>><?=e($a)?> (<?=(int)$n['total']?>)</option><?php endforeach; ?></select>
                    <select name="p_fonte" aria-label="Fonte oficial"><option value="">Todas as fontes oficiais</option><?php foreach (FontesCursos::FONTES as $k => $f): ?><option value="<?=e($k)?>" <?=$pesquisa['fonte'] === $k ? 'selected' : ''?>><?=e($f['nome'])?></option><?php endforeach; ?></select>
                    <input name="p_qtd" type="number" min="5" max="40" value="<?=(int)$pesquisa['quantidade']?>" aria-label="Quantidade de fichas">
                    <button class="btn btn-outline">Gerar prompt</button>
                </form>
                <details class="imp-cobertura">
                    <summary>Cobertura atual por área e fontes oficiais (onde achar os links)</summary>
                    <div class="table-wrap"><table class="table">
                        <tr><th>Área</th><th class="num">Cursos</th><th class="num">E-books</th><th class="num">Vídeos</th><th class="num">Total</th><th><span class="sr-only">Pesquisar</span></th></tr>
                        <?php foreach ($cobertura['areas'] as $a => $n): $lacuna = in_array($a, $cobertura['lacunas'], true); ?>
                        <tr><td><?=e($a)?><?=$lacuna ? ' '.painel_status('pausada', 'Lacuna') : ''?></td><td class="num"><?=(int)$n['curso']?></td><td class="num"><?=(int)$n['ebook']?></td><td class="num"><?=(int)$n['video']?></td><td class="num"><b><?=(int)$n['total']?></b></td>
                            <td><a class="btn btn-sm btn-outline" href="<?=e(painel_qs(['p_area' => $a, 'p_qtd' => $pesquisa['quantidade']]))?>#pesquisa">Pesquisar<span class="sr-only"> <?=e($a)?></span></a></td></tr>
                        <?php endforeach; ?>
                    </table></div>
                    <div class="table-wrap"><table class="table">
                        <tr><th>Fonte oficial</th><th>Formatos</th><th>Onde é forte</th><th class="num">Já cadastrados</th><th>Catálogo</th></tr>
                        <?php foreach (FontesCursos::FONTES as $k => $f): ?>
                        <tr><td><?=e($f['nome'])?><br><small class="meta"><?=e($f['dica'])?></small></td>
                            <td><?=e(implode(', ', array_map(fn($t) => rotulo($t), $f['formatos'])))?></td><td class="meta"><?=e($f['areas'])?></td>
                            <td class="num"><?=(int)($cobertura['fontes'][$k] ?? 0)?></td>
                            <td><div class="actions"><a class="btn btn-sm btn-outline" href="<?=e($f['catalogo'])?>" target="_blank" rel="noopener">Abrir<span class="sr-only"> o catálogo de <?=e($f['nome'])?> (abre em nova aba)</span></a>
                                <a class="btn btn-sm btn-outline" href="<?=e(painel_qs(['p_fonte' => $k, 'p_qtd' => $pesquisa['quantidade']]))?>#pesquisa">Pesquisar<span class="sr-only"> em <?=e($f['nome'])?></span></a></div></td></tr>
                        <?php endforeach; ?>
                    </table></div>
                    <?php if ($semPadrao): ?>
                    <form method="post" class="form-actions"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="padronizar_instituicoes">
                        <button class="btn btn-sm btn-outline" data-confirm="Padronizar o nome da instituição de <?=(int)$semPadrao?> conteúdo(s) pelo link oficial?">Padronizar nomes de instituição (<?=(int)$semPadrao?>)</button>
                        <small class="meta">Ex.: "Fundação Bradesco - Escola Virtual" e "Fundação Bradesco – Escola Virtual" viram um nome só.</small></form>
                    <?php endif; ?>
                </details>
            </li>
            <li><b>Copie o prompt</b> e cole no Perplexity (ou ChatGPT com busca). Ele pesquisa nas fontes oficiais e responde em fichas no padrão da plataforma (título, tipo, instituição, modalidade, cidade, nível, carga horária, preço, área, link, <b>imagem</b> e descrição).
                <div class="imp-prompt">
                    <label for="imp-prompt" class="sr-only">Prompt de pesquisa</label>
                    <textarea id="imp-prompt" rows="8" readonly><?=e($promptPesquisa)?></textarea>
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
            <li><b>Confira a prévia</b> e cadastre os marcados. A máquina confere a imagem de cada ficha (capa do e-book ou imagem do curso); se faltar, usa a imagem de divulgação da página do conteúdo ou o banner da instituição. <b>Sem imagem, a ficha fica fora do padrão e não entra.</b></li>
        </ol>
        <?php if ($importacao): ?>
        <form method="post" class="imp-previa">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="importar_salvar">
            <div class="table-wrap"><table class="table">
                <tr><th><span class="sr-only">Importar</span></th><th>Imagem</th><th>Título</th><th>Tipo</th><th>Instituição</th><th>Modalidade</th><th>Área</th><th>Link</th><th>Situação</th></tr>
                <?php foreach ($importacao as $i => $it): $ruim = !empty($it['problemas']); $miniatura = ($it['imagem_url'] ?? '') ?: ($it['imagem'] ? url($it['imagem']) : ''); ?>
                <tr>
                    <td><input type="checkbox" name="itens[]" value="<?=(int)$i?>" id="imp-<?=(int)$i?>" <?=$ruim ? 'disabled' : 'checked'?> aria-label="Importar <?=e($it['titulo'])?>"></td>
                    <td><?=$miniatura !== '' ? '<img class="imp-miniatura" src="'.e($miniatura).'" alt="" loading="lazy" referrerpolicy="no-referrer">' : '<span class="meta">—</span>'?></td>
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
            <?=painel_decisoes_maquina($extraido['maquina'] ?? [])?>
        <?php endif; ?>
    </details>

    <h2 class="pn-form-titulo" id="form-curso"><?=!empty($form['id']) ? 'Editar '.e(mb_strtolower(rotulo((string)$form['tipo']))).' #'.(int)$form['id'] : e($novoRotulo[$form['tipo']] ?? 'Novo conteúdo')?></h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($form['id'] ?? 0)?>">
        <?php if (!empty($form['sugestao_maquina'])): ?><input type="hidden" name="sugestao_maquina" value="<?=e((string)$form['sugestao_maquina'])?>"><?php endif; ?>
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
        <div class="form-actions"><button class="btn">Salvar conteúdo</button><?php if (!empty($form['id']) || $extraido): ?><a class="btn btn-outline" href="<?=e(url('admin/pages/cursos.php').painel_qs())?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<div class="pn-contagem" id="lista-cursos"><h2>Conteúdos cadastrados</h2><span><?=gf_num($totalLista)?> <?=gf_plural($totalLista, 'conteúdo', 'conteúdos')?><?=$paginas > 1 ? ' · página '.$pagina.' de '.$paginas : ''?><?=$filtroTipo !== '' || $comFiltro ? ' · <a href="'.e(url('admin/pages/cursos.php')).'#lista-cursos">limpar filtros</a>' : ''?></span></div>
<?=painel_subabas('tipo', $filtroTipo, ['' => ['Todos', array_sum($porTipo)], 'curso' => ['Cursos', $porTipo['curso'] ?? 0], 'ebook' => ['E-books', $porTipo['ebook'] ?? 0], 'video' => ['Vídeos', $porTipo['video'] ?? 0]], 'Formato')?>
<form class="filtros" method="get" action="#lista-cursos" style="grid-template-columns:2fr 1fr 1fr auto">
    <?php if ($filtroTipo !== ''): ?><input type="hidden" name="tipo" value="<?=e($filtroTipo)?>"><?php endif; ?>
    <?php if (get_str('ordem') !== ''): ?><input type="hidden" name="ordem" value="<?=e($ordem)?>"><input type="hidden" name="dir" value="<?=e($dir)?>"><?php endif; ?>
    <input name="q" placeholder="Buscar por título, descrição ou instituição" value="<?=e($busca)?>" aria-label="Buscar por título, descrição ou instituição">
    <select name="categoria_id" aria-label="Área"><option value="">Todas as áreas</option><?php foreach ($cats as $c): ?><option value="<?=(int)$c['id']?>" <?=$filtroCat === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?></option><?php endforeach; ?></select>
    <select name="situacao" aria-label="Situação"><option value="">Publicados e ocultos</option><option value="publicado" <?=$filtroSituacao === 'publicado' ? 'selected' : ''?>>Só publicados</option><option value="oculto" <?=$filtroSituacao === 'oculto' ? 'selected' : ''?>>Só ocultos</option></select>
    <button class="btn">Filtrar</button>
</form>
<div class="table-wrap"><table class="table">
    <tr><th><span class="sr-only">Imagem</span></th><?=painel_th('titulo', 'Título', $ordem, $dir)?><?=painel_th('tipo', 'Formato', $ordem, $dir)?><?=painel_th('categoria_nome', 'Área', $ordem, $dir)?><?=painel_th('instituicao', 'Instituição', $ordem, $dir)?><?=painel_th('ativo', 'Situação', $ordem, $dir)?><?=painel_th('created_at', 'Cadastro', $ordem, $dir)?><th>Ações</th></tr>
    <?php foreach ($lista as $x): $img = trim((string)$x['imagem']); ?>
    <tr>
        <td><?=$img !== '' ? '<img class="pn-miniatura'.($x['tipo'] === 'ebook' ? ' ebook' : '').'" src="'.e(preg_match('#^https?://#i', $img) ? $img : url($img)).'" alt="" loading="lazy">' : '<span class="meta">—</span>'?></td>
        <td><?=e($x['titulo'])?><br><small class="meta">#<?=(int)$x['id']?><?=$x['duracao'] ? ' · '.e($x['duracao']) : ''?> · <?=e(pt_preco($x))?></small></td>
        <td><?=e(rotulo($x['tipo']))?></td><td><?=e($x['categoria_nome'] ?? '—')?></td><td><?=e($x['instituicao'] ?? '')?></td>
        <td><?=$x['ativo'] ? painel_status('publicado', 'Publicado') : painel_status('oculto', 'Oculto')?></td>
        <td class="meta"><?=$x['created_at'] ? date('d/m/Y', strtotime((string)$x['created_at'])) : '—'?></td>
        <td><div class="actions">
            <a class="btn btn-sm" href="<?=url('curso.php?id='.(int)$x['id'])?>" target="_blank" rel="noopener">Ver<span class="sr-only"> <?=e($x['titulo'])?> (abre em nova aba)</span></a>
            <a class="btn btn-sm btn-outline" href="<?=e(painel_qs(['edit' => (int)$x['id']]))?>#form-curso">Editar<span class="sr-only"> <?=e($x['titulo'])?></span></a>
            <?=$x['ativo'] ? painel_acao('desativar', (int)$x['id'], 'Ocultar') : painel_acao('ativar', (int)$x['id'], 'Publicar')?>
            <?=painel_acao('excluir', (int)$x['id'], 'Excluir', 'btn-danger', 'Excluir este conteúdo? Esta ação não pode ser desfeita.')?>
        </div></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?=painel_paginacao($pagina, $paginas)?>
<?php if (!$lista): ?><div class="empty"><?=$filtroTipo !== '' || $comFiltro ? 'Nenhum conteúdo com esses filtros.' : 'Nenhum curso ou e-book cadastrado ainda. Use a extração acima para publicar o primeiro.'?></div><?php endif; ?>
</div>
