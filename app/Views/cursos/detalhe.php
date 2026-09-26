<?php
/**
 * Página do curso / e-book / vídeo (rota curso.php?id=): capa, descrição, competências desenvolvidas,
 * ficha e botão de acesso; abaixo, vagas que pedem essas competências e outros conteúdos do mesmo formato.
 * Recebe de CursoController::detalhe(): $curso, $id, $competencias, $vagasQuePedem, $outros, $mapaMatch,
 * $faltaParaMim, $formato, $secaoNome/$secaoUrl/$secaoIcone (página do formato), $instituicao, $acesso (botão Baixar/Acessar), $link e $abaTipo.
 */
?>
<?=cv_faixa($curso['titulo'], $instituicao.' · '.$formato.' · '.pt_preco($curso), $abaTipo + [$curso['titulo'] => ''])?>

<div class="cv-wrap cv-detalhe">
  <article class="cv-principal">
    <div class="cv-det-topo">
      <?=pt_midia($curso['imagem'] ?? '', 'Capa: '.$curso['titulo'], '', false, $secaoIcone)?>
      <div>
        <p class="cv-chapeu"><span><?=e($formato)?><?=$curso['categoria_nome'] ? ' · '.e($curso['categoria_nome']) : ''?></span></p>
        <?php if (empty($curso['ativo'])): ?><p class="alert erro" style="margin:0 0 8px">Conteúdo oculto — visível só para o administrador.</p><?php endif; ?>
        <h2><?=e($curso['titulo'])?></h2>
        <p class="cv-det-lide"><?=e(pt_linha_fina_curso($curso))?></p>
        <p class="cv-det-info">
          <span><?=icone('formatura', 15)?><?=e($instituicao)?></span>
          <span><?=icone('dinheiro', 15)?><?=e(pt_preco($curso))?></span>
          <?php if ($curso['duracao']): ?><span><?=icone('relogio', 15)?><?=e($curso['duracao'])?></span><?php endif; ?>
        </p>
        <div class="chips"><span class="chip"><?=e(rotulo((string)$curso['modalidade']))?></span><span class="chip"><?=e(rotulo((string)$curso['nivel']))?></span></div>
      </div>
    </div>
    <section class="cv-det-sec">
      <h2><?=icone('jornal', 18)?>Sobre o conteúdo</h2>
      <?=pt_paragrafos($curso['descricao']) ?: '<p class="cv-muted">Descrição não informada.</p>'?>
    </section>
    <?php if ($competencias): ?>
    <section class="cv-det-sec">
      <h2><?=icone('check', 18)?>Competências que você desenvolve</h2>
      <?php if ($faltaParaMim): ?><p class="cv-muted" style="margin:0 0 6px">Em laranja: o que as vagas mais compatíveis com você pedem e ainda falta no seu perfil.</p><?php endif; ?>
      <div class="chips"><?php foreach ($competencias as $c): ?><span class="chip<?=isset($faltaParaMim[$c]) ? ' falta' : ''?>"><?=e($c)?></span><?php endforeach; ?></div>
    </section>
    <?php endif; ?>
  </article>

  <aside class="cv-lateral">
    <div class="cv-bloco cv-ficha">
      <h2 class="cv-bloco-tit"><?=icone('formulario', 20)?>Ficha do <?=e(mb_strtolower($formato))?></h2>
      <dl>
        <div><dt>Instituição</dt><dd><?=e($instituicao)?></dd></div>
        <?php if ($curso['categoria_nome']): ?><div><dt>Área</dt><dd><?=e($curso['categoria_nome'])?></dd></div><?php endif; ?>
        <div><dt>Formato</dt><dd><?=e($formato)?></dd></div>
        <div><dt>Modalidade</dt><dd><?=e(rotulo((string)$curso['modalidade']))?></dd></div>
        <div><dt>Nível</dt><dd><?=e(rotulo((string)$curso['nivel']))?></dd></div>
        <div><dt>Duração</dt><dd><?=e($curso['duracao'] ?: '—')?></dd></div>
        <div><dt>Investimento</dt><dd><?=e(pt_preco($curso))?></dd></div>
      </dl>
      <div class="cv-acoes">
        <?php if ($acesso): ?><a class="cv-btn cv-btn-verde cv-btn-g" href="<?=e($acesso['href'])?>"<?=$acesso['atributos']?>><?=icone($acesso['icone'], 16)?><?=e($acesso['longo'])?><span class="sr-only"><?=$acesso['baixar'] ? ' (arquivo PDF da biblioteca)' : ' (abre em nova aba)'?></span></a><?php endif; ?>
        <?php if (isAdmin()): ?><a class="cv-btn cv-btn-azul" href="<?=url('admin/pages/cursos.php?edit='.$id)?>"><?=icone('editar', 15)?>Editar conteúdo</a><?php endif; ?>
      </div>
      <div class="cv-compartilhar">
        <a class="cv-btn cv-btn-linha" href="https://wa.me/?text=<?=rawurlencode($curso['titulo'].' — '.$link)?>" target="_blank" rel="noopener"><?=icone('whatsapp', 15)?>WhatsApp</a>
        <button type="button" class="cv-btn cv-btn-linha" data-copiar="<?=e($link)?>"><?=icone('link', 15)?><span>Copiar link</span></button>
      </div>
    </div>
  </aside>
</div>

<?php if ($vagasQuePedem): ?>
<section class="cv-secao">
  <div class="cv-wrap">
    <?=cv_titulo_secao('maleta', 'Vagas que pedem isso', 'Vagas abertas que exigem as competências deste '.mb_strtolower($formato), url('vagas.php'), 'Ver todas as vagas')?>
    <div class="cv-grade"><?php foreach ($vagasQuePedem as $v): ?><?=cv_card_vaga($v, $mapaMatch[(int)$v['id']] ?? null)?><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>
<?php if ($outros): ?>
<section class="cv-secao">
  <div class="cv-wrap">
    <?=cv_titulo_secao($secaoIcone, 'Outros '.mb_strtolower($secaoNome), '', $secaoUrl, 'Ver todos os '.mb_strtolower($secaoNome))?>
    <div class="cv-grade"><?php foreach ($outros as $c): ?><?=cv_card_curso($c)?><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>
