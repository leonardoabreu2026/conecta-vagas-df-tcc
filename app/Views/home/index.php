<?php
/**
 * Página inicial (rota index.php): banner com busca, vagas, assinaturas, cursos e os painéis relâmpago
 * (quem somos, objetivo, missão, valores e planos) que aparecem de tempos em tempos no canto da tela.
 * Recebe de HomeController::index(): $dbErro, $slides, $creditos, $vagasCapa, $cursosCapa,
 * $mapaMatch (% de match por vaga), $minhas (candidaturas do candidato) e $plano (assinatura ativa).
 */
?>
<?php if ($dbErro): ?>
  <div class="cv-wrap cv-flash"><div class="alert erro"><b>Não foi possível carregar os dados.</b> Verifique se o MySQL está ligado no XAMPP e se o banco <code><?=e(DB_NAME)?></code> foi importado (database/schema.sql e database/seed.sql).<br><small><?=e($dbErro)?></small></div></div>
<?php endif; ?>

<section class="cv-hero" data-carrossel aria-roledescription="carrossel" aria-label="Fotos de Brasília">
  <div class="cv-hero-slides" aria-hidden="true">
    <?php foreach ($slides as $n => $img): $cr = $creditos[basename($img)] ?? null; ?>
      <div class="cv-slide<?=$n === 0 ? ' ativo' : ''?>"
           <?=$n === 0 ? 'style="background-image:url(\''.e(url($img)).'\')"' : 'data-bg="'.e(url($img)).'"'?>
           data-credito="<?=e($cr ? 'Foto: '.$cr[0].' · '.$cr[1] : '')?>" data-fonte="<?=e($cr[2] ?? '')?>"></div>
    <?php endforeach; ?>
  </div>
  <div class="cv-wrap cv-hero-in">
    <span class="cv-hero-selo"><?=icone('local', 15)?>Distrito Federal · vagas atualizadas toda semana</span>
    <h1>Seu próximo emprego <br>começa <em>aqui no DF</em></h1>
    <p>Vagas reais de empresas do Distrito Federal, cursos gratuitos e um match que mostra o quanto você combina com cada oportunidade.</p>
    <form class="cv-hero-busca" action="<?=url('vagas.php')?>" method="get" role="search">
      <label class="sr-only" for="hero-busca">Buscar vagas</label>
      <?=icone('busca', 20)?>
      <input id="hero-busca" name="q" type="search" placeholder="Cargo, empresa ou palavra-chave" autocomplete="off">
      <button class="cv-btn cv-btn-verde">Buscar vagas</button>
    </form>
    <ul class="cv-hero-pontos">
      <li><?=icone('check', 16)?>Match com o seu currículo</li>
      <li><?=icone('check', 16)?>Cursos e e-books gratuitos</li>
      <li><?=icone('check', 16)?>Grátis para candidatos</li>
    </ul>
  </div>
  <?php if (count($slides) > 1): ?>
    <div class="cv-hero-dots">
      <?php foreach ($slides as $n => $img): ?><button type="button" class="<?=$n === 0 ? 'ativo' : ''?>" aria-label="Mostrar foto <?=$n + 1?> de <?=count($slides)?>"></button><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <a class="cv-hero-credito" href="#" target="_blank" rel="noopener" hidden></a>
</section>

<section class="cv-secao">
  <div class="cv-wrap">
    <?=cv_titulo_secao('maleta', 'Vagas de Emprego', 'Confira as oportunidades publicadas no Distrito Federal', url('vagas.php'), 'Ver todas as vagas')?>
    <?php if ($vagasCapa): ?>
      <div class="cv-grade">
        <?php foreach ($vagasCapa as $v): ?><?=cv_card_vaga($v, $mapaMatch[(int)$v['id']] ?? null, $minhas[(int)$v['id']] ?? null)?><?php endforeach; ?>
      </div>
    <?php elseif (!$dbErro): ?>
      <div class="empty">Nenhuma vaga aberta no momento.</div>
    <?php endif; ?>
  </div>
</section>

<section class="cv-assinaturas">
  <div class="cv-wrap cv-assinaturas-in">
    <div class="cv-assinaturas-tit">
      <h2><?=icone('planos', 22)?>Assinaturas</h2>
      <p><?=$plano ? 'Seu plano: <b>'.($plano['plano'] === 'empresa' ? 'Empresa Premium' : 'Candidato VIP').'</b> até '.date('d/m/Y', strtotime($plano['data_fim'])).'.' : 'Comece grátis e evolua quando quiser.'?></p>
    </div>
    <div class="cv-plano"><span>Gratuito</span><b>R$ 0</b><small>até 3 candidaturas ativas</small></div>
    <div class="cv-plano cv-plano-dest"><span>Candidato VIP</span><b>R$ 9,90<small>/mês</small></b><small>candidaturas ilimitadas e destaque</small></div>
    <div class="cv-plano"><span>Empresa Premium</span><b>R$ 49,90<small>/mês</small></b><small>vagas ilimitadas e banco de talentos</small></div>
    <a class="cv-btn cv-btn-dourado" href="<?=url('planos.php')?>">Conhecer os planos</a>
  </div>
</section>

<?php require_once __DIR__.'/../partials/doacao.php'; $pixDoacao = Pix::doacao(); ?>
<?=cv_doacao_faixa($pixDoacao)?>

<section class="cv-secao">
  <div class="cv-wrap">
    <?=cv_titulo_secao('formatura', 'Cursos e E-books', 'Capacitação gratuita para aumentar o seu match com as vagas', url('cursos.php'), 'Ver todos os cursos')?>
    <?php if ($cursosCapa): ?>
      <div class="cv-grade"><?php foreach ($cursosCapa as $c): ?><?=cv_card_curso($c)?><?php endforeach; ?></div>
    <?php elseif (!$dbErro): ?>
      <div class="empty">Nenhum curso publicado ainda.</div>
    <?php endif; ?>
  </div>
</section>

<?php
// Painéis relâmpago: um por vez, no canto da tela, de tempos em tempos (app.js, bloco [data-relampago]).
// [ícone, cor, título, texto, link]. A doação abre a sequência e volta depois dos planos (quem não assina, doa).
$relampagos = [
  ['coracao', 'doacao', 'Apoie com um Pix', 'Não quer assinar? Doe qualquer valor e ajude a manter o Conecta Vagas DF no ar e gratuito.', ['#apoie', 'Ver como doar']],
  ['grupo', 'azul', 'Quem somos', 'Uma plataforma do Distrito Federal que reúne, num só lugar, vagas de emprego, cursos gratuitos e qualificação profissional.', ['vagas.php', 'Ver as vagas']],
  ['alvo', 'verde', 'Nosso objetivo', 'Centralizar as oportunidades de emprego e capacitação do DF e mostrar, com o match, quais vagas combinam com o seu perfil.', ['cadastro.php', 'Criar conta grátis']],
  ['seta', 'roxo', 'Nossa missão', 'Conectar talentos às oportunidades, promovendo inclusão profissional, qualificação e crescimento de carreira.', ['cursos.php', 'Ver cursos gratuitos']],
  ['check', 'azul', 'Nossos valores', 'Inclusão, transparência, respeito a quem procura e a quem contrata — e a certeza de que educação transforma vidas.', null],
  ['planos', 'dourado', 'Planos', 'Comece grátis. Candidato VIP por R$ 9,90/mês e Empresa Premium por R$ 49,90/mês, sem fidelidade.', ['planos.php', 'Conhecer os planos']],
  ['coracao', 'doacao', 'Gostou do nosso site?', 'Em vez de assinar, você pode apoiar o projeto com uma doação pelo QR Code Pix. Qualquer valor ajuda!', ['#apoie', 'Doar pelo Pix']],
];
?>
<aside class="cv-relampago" data-relampago aria-label="Conheça e apoie o Conecta Vagas DF" hidden>
  <button type="button" class="cv-relampago-fechar" aria-label="Fechar os painéis">×</button>
  <?php foreach ($relampagos as $i => [$ic, $cor, $tit, $txt, $link]): ?>
    <div class="cv-relampago-painel cv-rl-<?=$cor?>">
      <span class="cv-relampago-ic"><?=icone($ic, 24)?></span>
      <div class="cv-relampago-txt">
        <small><?=icone('raio', 12)?><?=$cor === 'doacao' ? 'Apoie o projeto' : 'Conecta Vagas DF'?> <em><?=$i + 1?>/<?=count($relampagos)?></em></small>
        <b><?=e($tit)?></b>
        <p><?=e($txt)?></p>
        <?php if ($cor === 'doacao'): ?>
          <div class="cv-relampago-pix"><?=cv_doacao_qr($pixDoacao, 104)?><?=cv_doacao_copiar($pixDoacao, 'cv-btn cv-btn-verde cv-btn-p')?></div>
        <?php endif; ?>
        <?php if ($link): ?><a class="cv-relampago-link" href="<?=str_starts_with($link[0], '#') ? e($link[0]) : url($link[0])?>"><?=e($link[1])?> →</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <button type="button" class="cv-relampago-prox" data-relampago-prox aria-label="Próximo painel">›</button>
  <span class="cv-relampago-barra" aria-hidden="true"><i></i></span>
</aside>
