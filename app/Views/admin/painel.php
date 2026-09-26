<?php
/**
 * Painel (rota admin/index.php): dashboard no estilo Power BI para administrador e empresa —
 * linha de indicadores (KPI), grade de cartões com gráficos SVG (partials/graficos.php) e listas recentes.
 * Recebe de AdminController::painel(): $resumoVagas, $porStatus, $porDia, $dias, $funil, $recebidas,
 * $matchCandidaturas, $recentes, $isEmpresaPremium e $perfil (empresa).
 * Só administrador: $usuariosPorTipo, $novosUsuarios, $vagasPorArea, $vagasPorCidade, $matchVagas, $planos,
 * $cursosPublicados, $ebooks e $vagasRecentes. Só empresa: $desempenho (uma linha por vaga).
 */
$noPeriodo = array_sum($porDia);
$faixasMatch = ['excelente' => ['Excelente (75+)', 'var(--gf-o4)'], 'alto' => ['Alto (55–74)', 'var(--gf-o3)'],
                'medio' => ['Médio (35–54)', 'var(--gf-o2)'], 'baixo' => ['Baixo (até 34)', 'var(--gf-o1)']];
/** Itens do gráfico de faixas de match (da melhor para a pior) a partir de ['niveis' => [...]]. */
$itensMatch = fn(array $r) => array_map(fn($k) => ['rotulo' => $faixasMatch[$k][0], 'valor' => $r['niveis'][$k], 'cor' => $faixasMatch[$k][1]], array_keys($faixasMatch));
$linhasMatch = fn(array $r) => array_map(fn($k) => [$faixasMatch[$k][0], gf_num($r['niveis'][$k]), gf_pct($r['niveis'][$k], array_sum($r['niveis']))], array_keys($faixasMatch));
$funilCores = ['var(--gf-o1)', 'var(--gf-o2)', 'var(--gf-o3)', 'var(--gf-o4)'];
$primeiroNome = explode(' ', trim((string)($_SESSION['usuario_nome'] ?? '')))[0];
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?php
$acoes = '<span class="pn-atualizado">'.icone('relogio', 15).'Dados de '.date('d/m/Y').' às '.date('H:i').'</span>';
if (isEmpresa()) {
    $acoes .= $isEmpresaPremium ? '<span class="pn-selo premium">'.icone('planos', 15).'Empresa Premium</span>'
                                : '<a href="'.url('planos.php').'" class="btn btn-sm btn-gold">Desbloquear Empresa Premium</a>';
    if (empty($perfil['nome_fantasia'])) $acoes .= '<a href="'.url('admin/pages/empresa_perfil.php').'" class="btn btn-sm btn-outline">Completar perfil da empresa</a>';
}
$acoes .= '<a class="btn btn-sm" href="'.url('admin/pages/vagas.php').'">+ Nova vaga</a>';
echo painel_cabecalho('Visão geral', 'Olá, '.$primeiroNome.'. '.(isAdmin()
    ? 'Números do sistema inteiro: usuários, vagas, candidaturas, match e assinaturas.'
    : 'Acompanhe suas vagas, as candidaturas recebidas e o match dos candidatos.'), $acoes);
?>

<div class="pn-grade">
  <!-- Indicadores -->
  <div class="pn-kpis pn-s12">
    <?php if (isAdmin()): $totalUsuarios = array_sum(array_column($usuariosPorTipo, 'total')); $novos = array_sum(array_column($usuariosPorTipo, 'novos'));
        $vigentes = $planos['assinante']['vigentes'] + $planos['empresa']['vigentes']; $receita = $planos['assinante']['receita'] + $planos['empresa']['receita']; ?>
      <?=painel_kpi('Usuários', gf_compacto($totalUsuarios), '+'.gf_num($novos).' nos últimos '.$dias.' dias', 'grupo', 'admin/pages/usuarios.php')?>
      <?=painel_kpi('Vagas abertas', gf_compacto($resumoVagas['abertas']), 'de '.gf_num($resumoVagas['total']).' cadastradas'.($resumoVagas['vencidas'] ? ' · '.gf_num($resumoVagas['vencidas']).' vencidas' : ''), 'maleta', 'admin/pages/vagas.php')?>
      <?=painel_kpi('Candidaturas', gf_compacto($recebidas), '+'.gf_num($noPeriodo).' nos últimos '.$dias.' dias', 'formulario', 'admin/pages/candidaturas.php')?>
      <?=painel_kpi('Match médio', $matchCandidaturas['media'] !== null ? gf_num($matchCandidaturas['media']).'%' : '—', 'das '.gf_num($matchCandidaturas['com_match']).' candidaturas com nota', 'alvo')?>
      <?=painel_kpi('Assinaturas vigentes', gf_num($vigentes), 'R$ '.gf_num($receita, 2).'/mês (demonstrativo)', 'planos', 'admin/pages/assinaturas.php')?>
      <?=painel_kpi('Cursos publicados', gf_compacto($cursosPublicados), gf_num($ebooks).' '.gf_plural($ebooks, 'e-book', 'e-books').' entre eles', 'cursos', 'admin/pages/cursos.php')?>
    <?php else: $visu = $resumoVagas['visualizacoes']; ?>
      <?=painel_kpi('Vagas abertas', gf_compacto($resumoVagas['abertas']), 'de '.gf_num($resumoVagas['total']).' cadastradas'.($resumoVagas['vencidas'] ? ' · '.gf_num($resumoVagas['vencidas']).' vencidas' : ''), 'maleta', 'admin/pages/vagas.php')?>
      <?=painel_kpi('Candidaturas', gf_compacto($recebidas), '+'.gf_num($noPeriodo).' nos últimos '.$dias.' dias', 'formulario', 'admin/pages/candidaturas.php')?>
      <?=painel_kpi('Aguardando análise', gf_compacto($porStatus['enviada']), $porStatus['enviada'] ? 'clique para avaliar' : 'nenhuma pendente', 'relogio', 'admin/pages/candidaturas.php?status=enviada')?>
      <?=painel_kpi('Match médio', $matchCandidaturas['media'] !== null ? gf_num($matchCandidaturas['media']).'%' : '—', 'de quem se candidatou', 'alvo')?>
      <?=painel_kpi('Visualizações', gf_compacto($visu), 'média de '.gf_num($resumoVagas['total'] ? $visu / $resumoVagas['total'] : 0, 1).' por vaga', 'olho')?>
      <?=painel_kpi('Taxa de candidatura', $visu ? gf_pct($recebidas, $visu) : '—', 'candidaturas por visualização', 'raio')?>
    <?php endif; ?>
  </div>

  <!-- Candidaturas por dia -->
  <section class="pn-cartao pn-s8" aria-labelledby="g-dia">
    <div class="pn-cartao-cab">
      <div><h2 id="g-dia">Candidaturas por dia</h2><p class="pn-cartao-sub">Últimos <?=$dias?> dias · <?=gf_num($noPeriodo)?> <?=gf_plural($noPeriodo, 'recebida', 'recebidas')?> no período</p></div>
      <a class="pn-cartao-link" href="<?=url('admin/pages/candidaturas.php')?>">Ver todas</a>
    </div>
    <div class="pn-cartao-corpo">
      <?=grafico_linha($porDia, ['titulo' => 'Candidaturas por dia nos últimos '.$dias.' dias', 'unidade' => ['candidatura', 'candidaturas'], 'vazio' => 'Nenhuma candidatura nos últimos '.$dias.' dias'])?>
    </div>
    <?=grafico_tabela(['Dia', 'Candidaturas'], array_map(fn($d, $n) => [date('d/m/Y', strtotime($d)), gf_num($n)], array_keys($porDia), $porDia))?>
  </section>

  <!-- Funil de seleção -->
  <section class="pn-cartao pn-s4" aria-labelledby="g-funil">
    <div class="pn-cartao-cab">
      <div><h2 id="g-funil">Funil de seleção</h2><p class="pn-cartao-sub">Cada etapa inclui quem já passou dela</p></div>
    </div>
    <div class="pn-cartao-corpo">
      <?php $i = 0; $itens = []; foreach ($funil as $etapa => $n) $itens[] = ['rotulo' => $etapa, 'valor' => $n, 'texto' => gf_num($n).' · '.gf_pct($n, $recebidas), 'cor' => $funilCores[$i++]]; ?>
      <?=grafico_barras($itens, ['titulo' => 'Funil de seleção das candidaturas', 'unidade' => ['candidatura', 'candidaturas'], 'escala' => max(1, $recebidas), 'vazio' => 'Nenhuma candidatura recebida ainda.'])?>
      <p class="pn-nota">Não selecionadas: <b><?=gf_num($porStatus['rejeitado'])?></b> · Canceladas pelo candidato: <b><?=gf_num($porStatus['cancelada'])?></b></p>
    </div>
    <?=grafico_tabela(['Etapa', 'Candidaturas', '% das recebidas'], array_map(fn($e, $n) => [$e, gf_num($n), gf_pct($n, $recebidas)], array_keys($funil), $funil))?>
  </section>

<?php if (isAdmin()): ?>
  <!-- Vagas abertas por área -->
  <section class="pn-cartao pn-s6" aria-labelledby="g-area">
    <div class="pn-cartao-cab">
      <div><h2 id="g-area">Vagas abertas por área</h2><p class="pn-cartao-sub"><?=gf_num($resumoVagas['abertas'])?> vagas em <?=count($vagasPorArea)?> <?=gf_plural(count($vagasPorArea), 'área', 'áreas')?></p></div>
      <a class="pn-cartao-link" href="<?=url('admin/pages/categorias.php')?>">Categorias</a>
    </div>
    <div class="pn-cartao-corpo">
      <?=grafico_barras($vagasPorArea, ['titulo' => 'Vagas abertas por área', 'unidade' => ['vaga', 'vagas'], 'max_itens' => 9, 'rotulo_outros' => 'Outras áreas', 'vazio' => 'Nenhuma vaga aberta.'])?>
    </div>
    <?=grafico_tabela(['Área', 'Vagas', '%'], array_map(fn($r) => [$r['rotulo'], gf_num($r['valor']), gf_pct($r['valor'], $resumoVagas['abertas'])], $vagasPorArea))?>
  </section>

  <!-- Vagas abertas por região -->
  <section class="pn-cartao pn-s6" aria-labelledby="g-regiao">
    <?php $itensCidade = array_map(fn($r) => ['rotulo' => $r['cidade'].($r['uf'] !== '' && $r['uf'] !== 'DF' ? '/'.$r['uf'] : ''), 'valor' => $r['valor']], $vagasPorCidade); ?>
    <div class="pn-cartao-cab">
      <div><h2 id="g-regiao">Vagas abertas por região</h2><p class="pn-cartao-sub">Cidades e regiões administrativas · <?=count($vagasPorCidade)?> no total</p></div>
      <a class="pn-cartao-link" href="<?=url('vagas.php')?>">Ver vagas</a>
    </div>
    <div class="pn-cartao-corpo">
      <?=grafico_barras($itensCidade, ['titulo' => 'Vagas abertas por região', 'unidade' => ['vaga', 'vagas'], 'max_itens' => 9, 'rotulo_outros' => 'Outras regiões', 'vazio' => 'Nenhuma vaga aberta.'])?>
    </div>
    <?=grafico_tabela(['Região', 'Vagas', '%'], array_map(fn($r) => [$r['rotulo'], gf_num($r['valor']), gf_pct($r['valor'], $resumoVagas['abertas'])], $itensCidade))?>
  </section>

  <!-- Usuários por tipo -->
  <section class="pn-cartao pn-s4" aria-labelledby="g-usuarios">
    <div class="pn-cartao-cab">
      <div><h2 id="g-usuarios">Usuários por tipo</h2><p class="pn-cartao-sub"><?=gf_num($novos)?> <?=gf_plural($novos, 'cadastro', 'cadastros')?> nos últimos <?=$dias?> dias</p></div>
      <a class="pn-cartao-link" href="<?=url('admin/pages/usuarios.php')?>">Gerenciar</a>
    </div>
    <div class="pn-cartao-corpo">
      <?php $coresTipo = ['candidato' => 'var(--gf-s1)', 'empresa' => 'var(--gf-s2)', 'admin' => 'var(--gf-s3)']; // cor fixa por tipo ?>
      <?=grafico_rosca(array_map(fn($t) => ['rotulo' => rotulo($t).($t === 'admin' ? 'es' : 's'), 'valor' => $usuariosPorTipo[$t]['total'], 'cor' => $coresTipo[$t]], array_keys($coresTipo)),
                       ['titulo' => 'Usuários por tipo', 'centro' => gf_plural($totalUsuarios, 'usuário', 'usuários'), 'unidade' => ['usuário', 'usuários']])?>
      <?php if ($novosUsuarios): ?>
        <p class="pn-nota">Últimos cadastros: <?=implode(', ', array_map(fn($u) => '<b>'.e(explode(' ', trim($u['nome']))[0]).'</b> ('.e(mb_strtolower(rotulo($u['tipo']))).')', array_slice($novosUsuarios, 0, 3)))?></p>
      <?php endif; ?>
    </div>
    <?=grafico_tabela(['Tipo', 'Total', 'Ativos', 'Novos ('.$dias.' dias)'], array_map(fn($t) => [rotulo($t), gf_num($usuariosPorTipo[$t]['total']), gf_num($usuariosPorTipo[$t]['ativos']), gf_num($usuariosPorTipo[$t]['novos'])], array_keys($coresTipo)))?>
  </section>

  <!-- Compatibilidade (match) entre candidatos e vagas abertas -->
  <section class="pn-cartao pn-s4" aria-labelledby="g-match">
    <div class="pn-cartao-cab">
      <div><h2 id="g-match">Match candidatos × vagas</h2><p class="pn-cartao-sub"><?=gf_num($matchVagas['total'])?> combinações com vagas abertas<?=$matchVagas['media'] !== null ? ' · média '.gf_num($matchVagas['media']).'%' : ''?></p></div>
    </div>
    <div class="pn-cartao-corpo">
      <?=grafico_barras($itensMatch($matchVagas), ['titulo' => 'Combinações candidato e vaga por faixa de match', 'unidade' => ['combinação', 'combinações'], 'vazio' => 'O match ainda não foi calculado.'])?>
    </div>
    <?=grafico_tabela(['Faixa', 'Combinações', '%'], $linhasMatch($matchVagas))?>
  </section>

  <!-- Assinaturas por plano -->
  <section class="pn-cartao pn-s4" aria-labelledby="g-planos">
    <?php $nomesPlano = ['assinante' => 'Candidato VIP', 'empresa' => 'Empresa Premium']; ?>
    <div class="pn-cartao-cab">
      <div><h2 id="g-planos">Assinaturas vigentes por plano</h2><p class="pn-cartao-sub">Receita mensal demonstrativa: R$ <?=gf_num($receita, 2)?></p></div>
      <a class="pn-cartao-link" href="<?=url('admin/pages/assinaturas.php')?>">Ver todas</a>
    </div>
    <div class="pn-cartao-corpo">
      <?=grafico_barras(array_map(fn($p) => ['rotulo' => $nomesPlano[$p], 'valor' => $planos[$p]['vigentes']], array_keys($nomesPlano)),
                        ['titulo' => 'Assinaturas vigentes por plano', 'unidade' => ['assinatura', 'assinaturas'], 'vazio' => 'Nenhuma assinatura vigente.'])?>
      <p class="pn-nota">Canceladas: <b><?=gf_num($planos['assinante']['canceladas'] + $planos['empresa']['canceladas'])?></b> · Expiradas: <b><?=gf_num($planos['assinante']['expiradas'] + $planos['empresa']['expiradas'])?></b></p>
    </div>
    <?=grafico_tabela(['Plano', 'Vigentes', 'Canceladas', 'Expiradas', 'Receita/mês'], array_map(fn($p) => [$nomesPlano[$p], gf_num($planos[$p]['vigentes']), gf_num($planos[$p]['canceladas']), gf_num($planos[$p]['expiradas']), 'R$ '.gf_num($planos[$p]['receita'], 2)], array_keys($nomesPlano)))?>
  </section>
<?php else: ?>
  <!-- Candidaturas por vaga -->
  <section class="pn-cartao pn-s6" aria-labelledby="g-porvaga">
    <?php $comCand = array_values(array_filter($desempenho, fn($v) => (int)$v['candidaturas'] > 0)); ?>
    <div class="pn-cartao-cab">
      <div><h2 id="g-porvaga">Candidaturas por vaga</h2><p class="pn-cartao-sub"><?=count($comCand)?> de <?=count($desempenho)?> <?=gf_plural(count($desempenho), 'vaga recebeu', 'vagas receberam')?> candidaturas</p></div>
      <a class="pn-cartao-link" href="<?=url('admin/pages/candidaturas.php')?>">Avaliar</a>
    </div>
    <div class="pn-cartao-corpo">
      <?=grafico_barras(array_map(fn($v) => ['rotulo' => $v['titulo'], 'valor' => (int)$v['candidaturas']], $comCand),
                        ['titulo' => 'Candidaturas recebidas por vaga', 'unidade' => ['candidatura', 'candidaturas'], 'max_itens' => 8, 'rotulo_outros' => 'Outras vagas', 'vazio' => 'Suas vagas ainda não receberam candidaturas.'])?>
    </div>
    <?=grafico_tabela(['Vaga', 'Candidaturas', 'Aguardando'], array_map(fn($v) => [$v['titulo'], gf_num((int)$v['candidaturas']), gf_num((int)$v['aguardando'])], $comCand))?>
  </section>

  <!-- Match dos candidatos -->
  <section class="pn-cartao pn-s6" aria-labelledby="g-match">
    <div class="pn-cartao-cab">
      <div><h2 id="g-match">Match dos candidatos</h2><p class="pn-cartao-sub"><?=gf_num($matchCandidaturas['com_match'])?> <?=gf_plural($matchCandidaturas['com_match'], 'candidatura', 'candidaturas')?> com nota<?=$matchCandidaturas['media'] !== null ? ' · média '.gf_num($matchCandidaturas['media']).'%' : ''?></p></div>
    </div>
    <div class="pn-cartao-corpo">
      <?=grafico_barras($itensMatch($matchCandidaturas), ['titulo' => 'Candidaturas por faixa de match', 'unidade' => ['candidatura', 'candidaturas'], 'vazio' => 'Sem candidaturas com match calculado.'])?>
      <p class="pn-nota">O match compara o perfil do candidato com os requisitos, o nível e o local da vaga (0 a 100).</p>
    </div>
    <?=grafico_tabela(['Faixa', 'Candidaturas', '%'], $linhasMatch($matchCandidaturas))?>
  </section>
<?php endif; ?>

  <!-- Candidaturas recentes -->
  <section class="pn-cartao <?=isAdmin() ? 'pn-s7' : 'pn-s12'?>" aria-labelledby="t-recentes">
    <div class="pn-cartao-cab">
      <div><h2 id="t-recentes"><?=isAdmin() ? 'Candidaturas recentes' : 'Candidatos recentes'?></h2><p class="pn-cartao-sub">As <?=count($recentes)?> últimas, da mais nova para a mais antiga</p></div>
      <a class="pn-cartao-link" href="<?=url('admin/pages/candidaturas.php')?>">Ver todas</a>
    </div>
    <?php if ($recentes): ?>
    <div class="table-wrap"><table class="table">
      <tr><th>Candidato</th><th>Vaga</th><th>Match</th><th>Status</th><th class="num">Data</th></tr>
      <?php foreach ($recentes as $c): ?>
      <tr>
        <td class="quebra"><a href="<?=url('view/perfil/portfolio.php?id='.(int)$c['candidato_perfil_id'])?>" target="_blank"><?=e($c['candidato_nome'])?></a><?=$c['is_vip'] ? ' <span class="badge-vip" style="font-size:10px">VIP</span>' : ''?></td>
        <td class="quebra"><a href="<?=url('admin/pages/candidaturas.php?vaga_id='.(int)$c['vaga_id'])?>"><?=e(mb_strimwidth((string)$c['titulo'], 0, 42, '…'))?></a><?=isAdmin() ? '<br><small class="meta">'.e($c['empresa_nome']).'</small>' : ''?></td>
        <td><?=painel_match($c['match_pontuacao'], $c['match_nivel'])?></td>
        <td><?=painel_status((string)$c['status'])?></td>
        <td class="num meta"><?=date('d/m/Y', strtotime($c['data_candidatura']))?><br><?=date('H:i', strtotime($c['data_candidatura']))?></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php else: ?><p class="gf-vazio">Nenhuma candidatura ainda.</p><?php endif; ?>
  </section>

<?php if (isAdmin()): ?>
  <!-- Vagas recentes -->
  <section class="pn-cartao pn-s5" aria-labelledby="t-vagas">
    <div class="pn-cartao-cab">
      <div><h2 id="t-vagas">Vagas cadastradas</h2><p class="pn-cartao-sub">As mais recentes (destaques primeiro)</p></div>
      <a class="pn-cartao-link" href="<?=url('admin/pages/vagas.php')?>">Gerenciar</a>
    </div>
    <?php if ($vagasRecentes): ?>
    <div class="table-wrap"><table class="table">
      <tr><th>Vaga</th><th>Status</th><th>Ações</th></tr>
      <?php foreach ($vagasRecentes as $x): ?>
      <tr>
        <td><?=e(mb_strimwidth((string)$x['titulo'], 0, 34, '…'))?><?=$x['destaque'] ? ' <span class="badge-vip" style="font-size:10px">DESTAQUE</span>' : ''?><br><small class="meta"><?=e(mb_strimwidth((string)($x['empresa_nome'] ?? ''), 0, 34, '…'))?></small></td>
        <td><?=painel_status($x['data_expiracao'] && $x['data_expiracao'] < date('Y-m-d') && $x['status'] === 'ativa' ? 'expirada' : (string)$x['status'], $x['data_expiracao'] && $x['data_expiracao'] < date('Y-m-d') && $x['status'] === 'ativa' ? 'Vencida' : '')?></td>
        <td><div class="actions"><a class="btn btn-sm btn-outline" href="<?=url('vaga.php?id='.(int)$x['id'])?>">Ver</a><a class="btn btn-sm btn-outline" href="<?=url('admin/pages/vagas.php?edit='.(int)$x['id'])?>">Editar</a></div></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php else: ?><p class="gf-vazio">Nenhuma vaga cadastrada.</p><?php endif; ?>
  </section>
<?php else: ?>
  <!-- Desempenho das vagas -->
  <section class="pn-cartao pn-s12" aria-labelledby="t-desempenho">
    <div class="pn-cartao-cab">
      <div><h2 id="t-desempenho">Desempenho das vagas</h2><p class="pn-cartao-sub">As <?=min(10, count($desempenho))?> com mais candidaturas e visualizações</p></div>
      <a class="pn-cartao-link" href="<?=url('admin/pages/vagas.php')?>">Gerenciar as <?=count($desempenho)?> vagas</a>
    </div>
    <?php if ($desempenho): ?>
    <div class="table-wrap"><table class="table">
      <tr><th>Vaga</th><th>Status</th><th class="num">Visualizações</th><th class="num">Candidaturas</th><th class="num">Taxa</th><th class="num">Aguardando</th><th class="num">Match médio</th><th>Ações</th></tr>
      <?php foreach (array_slice($desempenho, 0, 10) as $x): $vencida = $x['status'] === 'ativa' && $x['data_expiracao'] && $x['data_expiracao'] < date('Y-m-d'); ?>
      <tr>
        <td><?=e(mb_strimwidth((string)$x['titulo'], 0, 44, '…'))?><?=$x['destaque'] ? ' <span class="badge-vip" style="font-size:10px">DESTAQUE</span>' : ''?><br><small class="meta"><?=e(trim(($x['cidade'] ?? '').'/'.($x['uf'] ?? ''), '/'))?></small></td>
        <td><?=painel_status($vencida ? 'expirada' : (string)$x['status'], $vencida ? 'Vencida' : '')?></td>
        <td class="num"><?=gf_num((int)$x['visualizacoes'])?></td>
        <td class="num"><a href="<?=url('admin/pages/candidaturas.php?vaga_id='.(int)$x['id'])?>"><?=gf_num((int)$x['candidaturas'])?></a></td>
        <td class="num"><?=(int)$x['visualizacoes'] ? gf_pct((int)$x['candidaturas'], (int)$x['visualizacoes']) : '—'?></td>
        <td class="num"><?=gf_num((int)$x['aguardando'])?></td>
        <td class="num"><?=$x['match_medio'] !== null ? gf_num((float)$x['match_medio']).'%' : '—'?></td>
        <td><div class="actions"><a class="btn btn-sm btn-outline" href="<?=url('vaga.php?id='.(int)$x['id'])?>">Ver</a><a class="btn btn-sm btn-outline" href="<?=url('admin/pages/vagas.php?edit='.(int)$x['id'])?>">Editar</a></div></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php else: ?><p class="gf-vazio">Nenhuma vaga cadastrada ainda. <a href="<?=url('admin/pages/vagas.php')?>">Publique a primeira</a>.</p><?php endif; ?>
  </section>
<?php endif; ?>
</div>
</div>
<script src="<?=url('assets/js/painel.js')?>?v=1"></script>
