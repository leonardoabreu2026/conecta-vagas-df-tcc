<?php
/**
 * Cursos, e-books (?tipo=ebook) e vídeos (rota cursos.php): abas por formato, filtros,
 * "Recomendados para você" (candidato) e grade de cartões.
 * Recebe de CursoController::lista(): $tipo, $filtros, $cursos, $categorias, $porTipo, $total, $recomendados,
 * $faltantesTop, $tituloPag, $subPag, $abaUrl e $dbErro.
 */
?>
<?=cv_faixa($tituloPag, $subPag, $tipo === '' ? ['Cursos' => ''] : ['Cursos' => url('cursos.php'), $tituloPag => ''])?>

<div class="cv-wrap">
  <?php if ($dbErro): ?>
    <div class="alert erro" style="margin-top:16px"><b>Não foi possível carregar os cursos.</b> Verifique se o MySQL está ligado e se o banco <code><?=e(DB_NAME)?></code> foi importado.<br><small><?=e($dbErro)?></small></div>
  <?php endif; ?>

  <form class="cv-filtros" method="get" role="search" aria-label="Buscar cursos" style="grid-template-columns:2fr 1.3fr 1fr auto">
    <?php if ($tipo !== ''): ?><input type="hidden" name="tipo" value="<?=e($tipo)?>"><?php endif; ?>
    <input name="q" placeholder="Curso, tema ou instituição" value="<?=e($filtros['q'])?>" aria-label="Curso, tema ou instituição">
    <select name="categoria_id" aria-label="Área"><option value="">Todas as áreas</option><?php foreach ($categorias as $c): ?><option value="<?=(int)$c['id']?>" <?=$filtros['categoria_id'] === (int)$c['id'] ? 'selected' : ''?>><?=e($c['nome'])?></option><?php endforeach; ?></select>
    <select name="gratuito" aria-label="Preço"><option value="">Gratuitos e pagos</option><option value="1" <?=$filtros['gratuito'] === '1' ? 'selected' : ''?>>Só gratuitos</option></select>
    <button class="cv-btn cv-btn-azul"><?=icone('busca', 16)?>Buscar</button>
  </form>

  <nav class="cv-abas" aria-label="Formato">
    <a href="<?=$abaUrl('')?>" class="<?=$tipo === '' ? 'ativo' : ''?>">Todos <small>(<?=$total?>)</small></a>
    <a href="<?=$abaUrl('curso')?>" class="<?=$tipo === 'curso' ? 'ativo' : ''?>">Cursos <small>(<?=$porTipo['curso'] ?? 0?>)</small></a>
    <a href="<?=$abaUrl('ebook')?>" class="<?=$tipo === 'ebook' ? 'ativo' : ''?>">E-books <small>(<?=$porTipo['ebook'] ?? 0?>)</small></a>
    <a href="<?=$abaUrl('video')?>" class="<?=$tipo === 'video' ? 'ativo' : ''?>">Vídeos <small>(<?=$porTipo['video'] ?? 0?>)</small></a>
  </nav>

  <?php if ($recomendados): ?>
    <div class="cv-recomendados">
      <h2><?=icone('alvo', 18)?> Recomendados para você</h2>
      <p>As vagas mais compatíveis com você pedem: <?=e(implode(', ', $faltantesTop))?>. Estes cursos cobrem essas competências:</p>
      <ul class="cv-rel">
        <?php foreach ($recomendados as $c): ?>
          <li><a href="<?=url('curso.php?id='.(int)$c['id'])?>"><?=e($c['titulo'])?></a><small><?=e($c['instituicao'] ?: '')?> · cobre: <?=e(implode(', ', $c['cobre']))?></small></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="cv-barra"><span><b><?=count($cursos)?></b> conteúdo(s)<?=array_filter($filtros) ? ' · <a href="'.e(url('cursos.php'.($tipo ? '?tipo='.$tipo : ''))).'">limpar filtros</a>' : ''?></span></div>

  <?php if ($cursos): ?>
    <div class="cv-grade"><?php foreach ($cursos as $c): ?><?=cv_card_curso($c)?><?php endforeach; ?></div>
  <?php elseif (!$dbErro): ?>
    <div class="empty"><?=$tipo === 'ebook' ? 'Nenhum e-book publicado ainda. Em breve, novos materiais por aqui.' : 'Nenhum conteúdo encontrado com esses filtros.'?> <a href="<?=url('cursos.php')?>">Ver todos os cursos</a></div>
  <?php endif; ?>
  <div style="height:28px"></div>
</div>
