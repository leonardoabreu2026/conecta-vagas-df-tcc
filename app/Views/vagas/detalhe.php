<?php
/**
 * Página da vaga (rota vaga.php?id=) no formato de anúncio, em três colunas no computador:
 *  - lado: o cartaz (clique para ampliar), o match do candidato e os cursos relacionados;
 *  - centro: área, título, empresa, local, salário, chips, contato e as seções (descrição,
 *    requisitos com as competências, benefícios, como se candidatar);
 *  - direita: a caixa de candidatura, fixa ao rolar, com a ficha e o compartilhamento.
 * No celular vira uma coluna só: título → cartaz → candidatura → seções. Abaixo, outras vagas.
 * Recebe de VagaController::detalhe(): $vaga, $id, $aberta, $ehDona, $competencias, $match, $candidatura,
 * $recom, $cursosRel, $relacionadas, $empresa, $quando, $atendidas, $linkVaga, $textoShare, $trilha,
 * $salario, $requisitos, $beneficios, $contatos e $expira.
 */
$chips = pt_chips_vaga($vaga);
?>
<div class="an-pagina">
<div class="cv-wrap">
  <nav class="an-trilha" aria-label="Você está em">
    <a href="<?=e(url('index.php'))?>">Início</a>
    <?php foreach ($trilha as $rot => $link): ?><span aria-hidden="true">›</span>
      <?php if ($link !== ''): ?><a href="<?=e($link)?>"><?=e($rot)?></a><?php else: ?><span aria-current="page"><?=e($rot)?></span><?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <?php if (!$aberta): $motivoFechada = $vaga['status'] !== 'ativa' ? 'Vaga '.mb_strtolower(rotulo($vaga['status'])) : (!(int)($vaga['empresa_ativa'] ?? 1) ? 'Conta da empresa bloqueada' : 'Vaga com o prazo de inscrição vencido'); ?><div class="alert erro an-aviso"><?=e($motivoFechada)?> — visível só para a empresa e o administrador.</div><?php endif; ?>

  <div class="an-det">
    <div class="an-det-lado">
      <figure class="an-det-cartaz">
        <?php if (!empty($vaga['imagem'])): ?>
          <a class="an-ampliar" href="<?=e(url($vaga['imagem']))?>" target="_blank" rel="noopener" data-ampliar="an-lightbox">
            <?=cv_cartaz_vaga($vaga['imagem'], 'Cartaz da vaga de '.$vaga['titulo'].' — '.$empresa, false)?>
            <span class="an-ampliar-dica"><?=icone('busca', 15)?>Ampliar cartaz</span>
          </a>
        <?php else: ?>
          <?=cv_cartaz_vaga('', 'Vaga de '.$vaga['titulo'])?>
        <?php endif; ?>
      </figure>

      <?php if ($match && !empty($match['detalhes'])): $d = $match['detalhes']; ?>
        <section class="cv-bloco an-match" aria-labelledby="an-match-tit">
          <h2 class="cv-bloco-tit" id="an-match-tit"><?=icone('alvo', 20)?>Seu match <span class="score-badge <?=e($match['nivel'])?>"><?=number_format((float)$match['pontuacao'], 0)?>%</span></h2>
          <?php foreach ([['Competências', $d['competencias']['pontos'], 50], [$d['cargo']['texto'], $d['cargo']['pontos'], 20], [$d['local']['texto'], $d['local']['pontos'], 15], [$d['nivel']['texto'], $d['nivel']['pontos'], 15]] as [$rot, $pts, $max]): ?>
            <div class="an-match-linha">
              <span><?=e((string)$rot)?></span><b><?=e((string)$pts)?> / <?=$max?></b>
              <span class="an-barra-pts" aria-hidden="true"><i style="width:<?=max(0, min(100, round((float)$pts / $max * 100)))?>%"></i></span>
            </div>
          <?php endforeach; ?>
          <?php foreach ($d['observacoes'] ?? [] as $o): ?><p class="an-match-obs"><?=!empty($o['ok']) ? '✅' : '⚠️'?> <?=e($o['texto'] ?? '')?></p><?php endforeach; ?>
        </section>
      <?php elseif (usuarioLogado() && isCandidato()): ?>
        <div class="cv-bloco an-match"><p class="cv-muted" style="margin:0">Envie seu currículo no <a href="<?=url('view/perfil/index.php')?>">perfil</a> para ver seu match com esta vaga.</p></div>
      <?php endif; ?>

      <?php if ($cursosRel): ?>
        <section class="cv-bloco an-cursos" aria-labelledby="an-cursos-tit">
          <h2 class="cv-bloco-tit" id="an-cursos-tit"><?=icone('formatura', 20)?><?=$recom ? 'Cursos para aumentar seu match' : 'Cursos relacionados'?></h2>
          <ul class="cv-rel">
            <?php foreach ($cursosRel as $c): $cobre = $c['cobre'] ?? $c['em_comum'] ?? []; ?>
              <li><a href="<?=url('curso.php?id='.(int)$c['id'])?>"><?=e($c['titulo'])?></a><small><?=e($c['instituicao'] ?: 'Instituição parceira')?> · <?=e(pt_preco($c))?><?=$cobre ? ' · '.e(implode(', ', $cobre)) : ''?></small></li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endif; ?>
    </div>

    <article class="an-det-principal" aria-labelledby="an-titulo">
      <header class="an-det-cab">
        <p class="cv-chapeu">
          <?php if (!empty($vaga['categoria_nome'])): ?><a href="<?=e(url('vagas.php?categoria_id='.(int)$vaga['categoria_id']))?>"><?=e($vaga['categoria_nome'])?></a><?php else: ?><span>Vaga</span><?php endif; ?>
          <?=pt_time($quando, 'cv-hora')?>
        </p>
        <h1 id="an-titulo"><?=e($vaga['titulo'])?></h1>
        <p class="an-det-empresa"><?=icone('maleta', 18)?><span><?=e($empresa)?></span><?=pt_selo_premium($vaga)?></p>
        <ul class="an-det-meta">
          <li><?=icone('local', 16)?><?=e(pt_local($vaga))?></li>
          <li><?=icone('calendario', 16)?>Publicada em <?=e(pt_data_hora($quando) ?: '—')?></li>
          <li><?=icone('olho', 16)?><?=e(pt_views($vaga['visualizacoes']))?></li>
        </ul>
        <div class="an-det-salario<?=$salario === 'A combinar' ? ' an-combinar' : ''?>">
          <span class="an-det-salario-ic"><?=icone('dinheiro', 22)?></span>
          <span><small>Salário</small><b><?=e($salario)?></b></span>
        </div>
        <ul class="an-chips an-chips-g" aria-label="Contratação, nível e modelo">
          <?php foreach ($chips as $c): ?><li><?=e($c)?></li><?php endforeach; ?>
          <?php if ($vaga['destaque']): ?><li class="an-chip-destaque">★ Destaque</li><?php endif; ?>
        </ul>
        <?php if ($contatos): ?>
          <div class="an-det-contato">
            <h2>Contato do anúncio</h2>
            <?=cv_contatos_vaga($contatos)?>
          </div>
        <?php endif; ?>
      </header>

      <div class="an-det-texto">
        <section class="an-sec" aria-labelledby="an-sec-desc">
          <h2 id="an-sec-desc"><?=icone('jornal', 18)?>Descrição da vaga</h2>
          <p class="an-lide"><?=e(pt_lide_vaga($vaga))?></p>
          <?=pt_texto_vaga($vaga['descricao']) ?: '<p class="cv-muted">A empresa não detalhou as atividades.</p>'?>
        </section>

        <section class="an-sec" aria-labelledby="an-sec-req">
          <h2 id="an-sec-req"><?=icone('check', 18)?>Requisitos</h2>
          <?php if ($requisitos): ?>
            <ul class="an-lista an-lista-req"><?php foreach ($requisitos as $i): ?><li><?=e($i)?></li><?php endforeach; ?></ul>
          <?php else: ?><p class="cv-muted">Requisitos não informados.</p><?php endif; ?>
          <?php if ($competencias): ?>
            <p class="an-sec-nota">Competências identificadas<?=$match ? ' (verde = você tem)' : ''?>:</p>
            <div class="chips"><?php foreach ($competencias as $c): $tem = in_array($c, $atendidas, true); ?><span class="chip<?=$match ? ($tem ? ' ok' : ' falta') : ''?>"><?=$tem ? '✓ ' : ''?><?=e($c)?></span><?php endforeach; ?></div>
          <?php endif; ?>
        </section>

        <section class="an-sec" aria-labelledby="an-sec-ben">
          <h2 id="an-sec-ben"><?=icone('dinheiro', 18)?>Salário e benefícios</h2>
          <p class="an-sec-salario"><b><?=e($salario)?></b><?=$salario === 'A combinar' ? ' — a empresa combina o valor com os selecionados.' : ''?></p>
          <?php if ($beneficios): ?>
            <ul class="an-lista an-lista-ben"><?php foreach ($beneficios as $i): ?><li><?=e($i)?></li><?php endforeach; ?></ul>
          <?php else: ?><p class="cv-muted">Benefícios não informados no anúncio.</p><?php endif; ?>
        </section>

        <section class="an-sec" aria-labelledby="an-sec-como">
          <h2 id="an-sec-como"><?=icone('seta', 18)?>Como se candidatar</h2>
          <p>Candidate-se pelo Conecta Vagas DF: a empresa recebe seu currículo, seu portfólio e o seu percentual de match com a vaga.<?=$expira ? ' Inscrições até '.$expira.'.' : ''?></p>
          <?php if ($contatos): ?><p>Se preferir, fale direto com a empresa pelo contato do anúncio, informado acima.</p><?php endif; ?>
        </section>
      </div>
    </article>

    <aside class="an-det-acao" aria-label="Candidatura">
      <div class="cv-bloco an-caixa">
        <p class="an-caixa-salario<?=$salario === 'A combinar' ? ' an-combinar' : ''?>"><small>Salário</small><b><?=e($salario)?></b></p>
        <dl class="an-ficha">
          <div><dt>Empresa</dt><dd><?=e($empresa)?></dd></div>
          <?php if (!empty($vaga['anunciante']) && ($vaga['publicado_por'] ?? '') !== '' && $vaga['publicado_por'] !== $empresa): ?><div><dt>Publicado por</dt><dd><?=e($vaga['publicado_por'])?></dd></div><?php endif; ?>
          <div><dt>Local</dt><dd><?=e(pt_local($vaga))?></dd></div>
          <div><dt>Contratação</dt><dd><?=e(rotulo($vaga['tipo_vaga']))?></dd></div>
          <div><dt>Nível</dt><dd><?=e(rotulo($vaga['nivel_experiencia']))?></dd></div>
          <div><dt>Modelo</dt><dd><?=e(rotulo($vaga['remoto']))?></dd></div>
          <?php if ($expira): ?><div><dt>Inscrições até</dt><dd><?=$expira?></dd></div><?php endif; ?>
        </dl>
        <div class="cv-acoes an-acoes">
          <?php if (!usuarioLogado()): ?>
            <a class="cv-btn cv-btn-verde cv-btn-g" href="<?=url('login.php')?>"><?=icone('usuario', 16)?>Entrar para se candidatar</a>
            <a class="cv-link-peq" href="<?=url('cadastro.php')?>">Ainda não tem conta? Cadastre-se grátis</a>
          <?php elseif (isCandidato()): ?>
            <?php if ($candidatura && $candidatura['status'] !== 'cancelada'): ?>
              <div class="alert ok">✓ Você se candidatou em <?=date('d/m/Y', strtotime($candidatura['data_candidatura']))?>.<br>Status: <b><?=e(rotulo($candidatura['status']))?></b></div>
            <?php elseif ($aberta): ?>
              <a class="cv-btn cv-btn-verde cv-btn-g" href="<?=url('candidatar.php?vaga_id='.$id)?>"><?=icone('check', 16)?><?=$candidatura ? 'Candidatar-se novamente' : 'Candidatar-se'?></a>
            <?php endif; ?>
          <?php elseif ($ehDona || isAdmin()): ?>
            <a class="cv-btn cv-btn-azul" href="<?=url('admin/pages/vagas.php?edit='.$id)?>"><?=icone('editar', 15)?>Editar vaga</a>
            <a class="cv-btn cv-btn-linha" href="<?=url('admin/pages/candidaturas.php?vaga_id='.$id)?>">Ver candidaturas</a>
          <?php endif; ?>
        </div>
        <div class="an-compartilhar">
          <p>Compartilhe esta vaga</p>
          <div>
            <a class="cv-btn cv-btn-linha" href="https://wa.me/?text=<?=rawurlencode($textoShare.' '.$linkVaga)?>" target="_blank" rel="noopener"><?=icone('whatsapp', 15)?>WhatsApp<span class="sr-only"> (abre em nova aba)</span></a>
            <button type="button" class="cv-btn cv-btn-linha" data-copiar="<?=e($linkVaga)?>"><?=icone('link', 15)?><span>Copiar link</span></button>
            <button type="button" class="cv-btn cv-btn-linha" data-compartilhar="<?=e($linkVaga)?>" data-titulo="<?=e($textoShare)?>" hidden><?=icone('externo', 15)?>Enviar</button>
          </div>
        </div>
      </div>
    </aside>
  </div>
</div>
</div>

<?php if ($relacionadas): ?>
<section class="cv-secao an-relacionadas">
  <div class="cv-wrap">
    <?=cv_titulo_secao('maleta', 'Outras vagas', 'Oportunidades parecidas abertas agora', url('vagas.php'), 'Ver todas as vagas')?>
    <div class="cv-grade an-grade"><?php foreach ($relacionadas as $v): ?><?=cv_card_vaga($v)?><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($vaga['imagem'])): ?>
<dialog class="an-lightbox" id="an-lightbox" aria-label="Cartaz da vaga ampliado">
  <div class="an-lightbox-barra">
    <span><?=e($vaga['titulo'])?></span>
    <a href="<?=e(url($vaga['imagem']))?>" target="_blank" rel="noopener"><?=icone('externo', 15)?>Tamanho real<span class="sr-only"> (abre em nova aba)</span></a>
    <button type="button" class="an-lightbox-fechar" data-fechar aria-label="Fechar o cartaz ampliado">✕</button>
  </div>
  <img src="<?=e(url($vaga['imagem']))?>" alt="Cartaz da vaga de <?=e($vaga['titulo'])?> — <?=e($empresa)?>" loading="lazy" decoding="async">
</dialog>
<?php endif; ?>
<script src="<?=url('assets/js/anuncios.js')?>?v=1" defer></script>
