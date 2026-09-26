<?php
/**
 * Painel "Aprendizado da máquina" (rota admin/pages/aprendizado.php) — só administrador.
 * Recebe de AprendizadoController::painel(): $modelos, $memorias, $revisoes, $acertoDia, $totalLicoes,
 * $totalRevisoes, $modelosLiberados, $testeModelo, $testeTexto, $teste, $todosModelos, $filtroModelo e $licoes.
 *
 * Organização da tela, de cima para baixo:
 *  1. como a máquina aprende (4 passos, em linguagem simples);
 *  2. indicadores e evolução do acerto;
 *  3. o que cada modelo aprendeu (período de experiência, lições por classe e palavras típicas) e as memórias de nomes;
 *  4. "Teste a máquina" (previsão ao vivo, com a explicação);
 *  5. últimas lições, com o botão de esquecer.
 */
$origens = ['vaga' => 'Vagas', 'curso' => 'Cursos e e-books', 'curriculo' => 'Currículos'];
$pct = fn(?float $v) => $v === null ? '—' : gf_num($v, 1).'%';
// Evolução geral: acerto das primeiras revisões × das últimas (média das origens que já têm revisão).
$inicio = array_filter(array_column($revisoes, 'inicio'), fn($v) => $v !== null);
$recente = array_filter(array_column($revisoes, 'recente'), fn($v) => $v !== null);
$acertoInicio = $inicio ? array_sum($inicio) / count($inicio) : null;
$acertoRecente = $recente ? array_sum($recente) / count($recente) : null;
$rotuloClasse = function (string $modelo, string $classe): string {
    return MaquinaAprendizado::MODELOS[$modelo][2][$classe] ?? ($classe === AprendizadoDAO::CLASSE_NOME ? 'Nome confirmado' : $classe);
};
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?php
$acoes = '<form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="acao" value="historico">'
       .'<button class="btn btn-sm" data-confirm="A máquina vai estudar todas as vagas, cursos e perfis já cadastrados. Pode levar alguns segundos. Continuar?">'
       .icone('lampada', 15).' Aprender com o histórico</button></form>';
echo painel_cabecalho('Aprendizado da máquina', 'A extração aprende com as revisões salvas: quando uma correção se repete, a máquina passa a fazê-la sozinha. Aqui você acompanha o que foi aprendido, testa a máquina e apaga lições erradas.', $acoes);
?>

<!-- 1. Como funciona -->
<ol class="ml-passos" aria-label="Como a máquina aprende">
    <li><b>1. Extrair</b><span>A máquina lê o cartaz, o anúncio ou o currículo e preenche o formulário com as regras e com o que já aprendeu.</span></li>
    <li><b>2. Revisar</b><span>A pessoa confere, corrige e salva, como sempre fez. Ninguém precisa ensinar nada de propósito.</span></li>
    <li><b>3. Aprender</b><span>Ao salvar, a máquina vê em qual campo a pessoa deixou cada linha. Cada uma vira uma lição, tenha sido corrigida ou não.</span></li>
    <li><b>4. Melhorar</b><span>Antes de aprender cada lição nova, a máquina tenta adivinhar a resposta (uma prova). Só depois de acertar <?=gf_num(100 * MaquinaAprendizado::PRECISAO_MINIMA)?>% de pelo menos <?=MaquinaAprendizado::MIN_PROVAS?> provas ela passa a decidir sozinha, e só quando está confiante. Na dúvida, vale a regra.</span></li>
</ol>

<div class="pn-grade">
  <!-- 2. Indicadores -->
  <div class="pn-kpis pn-s12">
    <?=painel_kpi('Lições aprendidas', gf_compacto($totalLicoes), 'em '.count($modelos).' modelos e '.count($memorias).' memórias de nomes', 'lampada')?>
    <?=painel_kpi('Revisões conferidas', gf_compacto($totalRevisoes), 'vagas, cursos e currículos salvos', 'check')?>
    <?=painel_kpi('Acerto recente', $pct($acertoRecente), $acertoInicio !== null ? 'no começo: '.$pct($acertoInicio) : 'aparece depois da 1ª revisão', 'alvo')?>
    <?=painel_kpi('Modelos liberados', $modelosLiberados.' de '.count($modelos), 'passaram no período de experiência', 'engrenagem')?>
  </div>

  <section class="pn-cartao pn-s8" aria-labelledby="ml-evolucao">
    <div class="pn-cartao-cab">
      <div><h2 id="ml-evolucao">Acerto da extração por dia</h2><p class="pn-cartao-sub">% de campos e linhas que a pessoa não precisou corrigir (regras e aprendizado juntos) · últimos 60 dias</p></div>
    </div>
    <div class="pn-cartao-corpo">
      <?php if (count($acertoDia) >= 2): ?>
        <?=grafico_linha($acertoDia, ['titulo' => 'Acerto da extração por dia', 'unidade' => ['% de acerto', '% de acerto']])?>
      <?php else: ?>
        <p class="gf-vazio">O gráfico aparece depois de revisões em pelo menos dois dias diferentes. Extraia um anúncio, revise e salve.</p>
      <?php endif; ?>
    </div>
    <?=grafico_tabela(['Dia', 'Acerto'], array_map(fn($d, $v) => [date('d/m/Y', strtotime($d)), $pct($v)], array_keys($acertoDia), $acertoDia))?>
  </section>

  <section class="pn-cartao pn-s4" aria-labelledby="ml-origens">
    <div class="pn-cartao-cab">
      <div><h2 id="ml-origens">Acerto por tipo de extração</h2><p class="pn-cartao-sub">Primeiras 10 revisões × últimas 10</p></div>
    </div>
    <div class="pn-cartao-corpo">
      <?php if ($revisoes): ?>
      <table class="ml-tabela">
        <thead><tr><th scope="col">Extração</th><th scope="col" class="num">Revisões</th><th scope="col" class="num">Começo</th><th scope="col" class="num">Agora</th></tr></thead>
        <tbody>
        <?php foreach ($origens as $k => $nome): $r = $revisoes[$k] ?? null; ?>
          <tr><th scope="row"><?=e($nome)?></th><td class="num"><?=gf_num($r['revisoes'] ?? 0)?></td><td class="num"><?=$pct($r['inicio'] ?? null)?></td><td class="num"><b><?=$pct($r['recente'] ?? null)?></b></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p class="gf-vazio">Nenhuma revisão ainda. A primeira acontece quando alguém salvar uma vaga ou um curso extraído, ou um perfil depois de enviar o currículo.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- 3. O que cada modelo aprendeu -->
  <?php foreach ($modelos as $nome => $m): ?>
  <section class="pn-cartao pn-s6" aria-labelledby="ml-<?=e($nome)?>">
    <div class="pn-cartao-cab">
      <div><h2 id="ml-<?=e($nome)?>"><?=e($m['titulo'])?></h2><p class="pn-cartao-sub"><?=e($m['descricao'])?></p></div>
      <?=$m['experiencia']['liberado'] ? painel_status('ativo', 'Liberado') : painel_status('pausada', 'Em experiência')?>
    </div>
    <div class="pn-cartao-corpo">
      <?php $ex = $m['experiencia']; ?>
      <p class="ml-experiencia"><b>Período de experiência:</b>
        <?php if ($ex['provas'] === 0): ?>nenhuma prova ainda<?=$m['total'] < MaquinaAprendizado::MIN_LICOES ? ' (as provas começam depois de '.MaquinaAprendizado::MIN_LICOES.' lições; agora são '.gf_num($m['total']).')' : ''?>.
        <?php else: ?>acertou <b><?=gf_num($ex['acertos'])?> de <?=gf_num($ex['provas'])?></b> provas (<?=gf_num(100 * $ex['precisao'], 1)?>%).
          <?=$ex['liberado'] ? 'Já decide sozinho quando está confiante.' : ($ex['provas'] < MaquinaAprendizado::MIN_PROVAS ? 'Faltam '.(MaquinaAprendizado::MIN_PROVAS - $ex['provas']).' provas para avaliar.' : 'Precisa de '.gf_num(100 * MaquinaAprendizado::PRECISAO_MINIMA).'% para decidir; até lá, vale a regra.')?>
        <?php endif; ?></p>
      <?php if ($m['classes']): ?>
        <?=grafico_barras(array_map(fn($c) => ['rotulo' => $c['rotulo'], 'valor' => $c['licoes']], $m['classes']), ['titulo' => 'Lições por resposta em '.$m['titulo'], 'unidade' => ['lição', 'lições'], 'max_itens' => 8, 'rotulo_outros' => 'Outras'])?>
        <dl class="ml-palavras">
          <?php foreach (array_slice($m['classes'], 0, 8) as $c): if (!$c['palavras']) continue; ?>
            <dt><?=e($c['rotulo'])?></dt><dd><?php foreach ($c['palavras'] as $p): ?><span class="ml-chip"><?=e($p)?></span><?php endforeach; ?></dd>
          <?php endforeach; ?>
        </dl>
        <p class="pn-nota">As palavras acima (sem acento, do jeito que a máquina guarda) são as que mais diferenciam cada resposta das outras.</p>
      <?php else: ?>
        <p class="gf-vazio">Nada aprendido ainda: por enquanto a extração usa só as regras.</p>
      <?php endif; ?>
    </div>
    <?php if ($m['total']): ?>
    <div class="ml-rodape">
      <a class="pn-cartao-link" href="?modelo=<?=e($nome)?>#licoes">Ver lições</a>
      <?=painel_acao('zerar', 0, 'Zerar este modelo', 'btn-danger', 'A máquina vai esquecer as '.$m['total'].' lições de "'.$m['titulo'].'" e voltar a usar só as regras nessa parte da extração. Continuar?', [], ['modelo' => $nome])?>
    </div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <section class="pn-cartao pn-s12" aria-labelledby="ml-memorias">
    <div class="pn-cartao-cab">
      <div><h2 id="ml-memorias">Memória de nomes</h2><p class="pn-cartao-sub">Nomes próprios confirmados nas revisões. Quando as regras não acham a empresa ou a instituição, a máquina procura estes nomes no texto.</p></div>
    </div>
    <div class="pn-cartao-corpo ml-memorias">
      <?php foreach ($memorias as $nome => $mem): ?>
        <div>
          <h3><?=e($mem['titulo'])?> <span class="ml-cont"><?=gf_num($mem['total'])?></span></h3>
          <?php if ($mem['nomes']): ?>
            <p><?php foreach ($mem['nomes'] as $n): ?><span class="ml-chip"><?=e($n)?></span><?php endforeach; ?></p>
            <a class="pn-cartao-link" href="?modelo=<?=e($nome)?>#licoes">Ver todos</a>
          <?php else: ?>
            <p class="gf-vazio"><?=e($mem['descricao'])?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 4. Teste a máquina -->
  <section class="pn-cartao pn-s12" id="teste" aria-labelledby="ml-teste">
    <div class="pn-cartao-cab">
      <div><h2 id="ml-teste">Teste a máquina</h2><p class="pn-cartao-sub">Escreva uma linha de anúncio, o nome de um curso ou uma linha de currículo e veja o que a máquina responde, sem salvar nada.</p></div>
    </div>
    <div class="pn-cartao-corpo">
      <form method="get" action="#teste" class="ml-teste-form">
        <div><label for="ml-modelo">Modelo</label>
          <select id="ml-modelo" name="testar"><?php foreach (MaquinaAprendizado::MODELOS as $k => [$t]): ?><option value="<?=e($k)?>" <?=$testeModelo === $k ? 'selected' : ''?>><?=e($t)?></option><?php endforeach; ?></select></div>
        <div class="ml-teste-texto"><label for="ml-texto">Texto</label>
          <input id="ml-texto" name="texto" maxlength="2000" value="<?=e($testeTexto)?>" placeholder="Ex.: Vale-refeição de R$ 30 por dia e plano odontológico"></div>
        <div class="ml-teste-botao"><button class="btn">Perguntar à máquina</button></div>
      </form>
      <?php if ($testeTexto !== ''): ?>
        <?php if ($teste === null): ?>
          <p class="ml-resposta"><b>A máquina não sabe responder.</b> Nenhuma palavra desse texto apareceu nas lições de "<?=e(MaquinaAprendizado::MODELOS[$testeModelo][0])?>". Na extração, quem decide aqui é a regra.</p>
        <?php else: ?>
          <div class="ml-resposta">
            <p>Resposta: <b><?=e($rotuloClasse($testeModelo, $teste['classe']))?></b> com <b><?=gf_num(100 * $teste['confianca'])?>%</b> de confiança.
              <?=$teste['pronta'] ? painel_status('ativo', 'Decidiria sozinha') : painel_status('pausada', 'Não decidiria sozinha: vale a regra')?></p>
            <?php if (!$teste['pronta']): ?><p class="pn-nota"><?=$teste['confiante'] ? 'Ela está confiante neste texto, mas o modelo ainda não passou no período de experiência.' : 'Ela não está confiante o bastante neste texto (ou ainda tem poucas lições).'?></p><?php endif; ?>
            <?php if ($teste['motivos']): ?><p>Palavras que mais pesaram: <?php foreach (array_keys($teste['motivos']) as $p): ?><span class="ml-chip"><?=e($p)?></span><?php endforeach; ?></p><?php endif; ?>
            <?=grafico_barras(array_map(fn($c, $p) => ['rotulo' => $rotuloClasse($testeModelo, (string)$c), 'valor' => round(100 * $p, 1), 'texto' => gf_num(100 * $p, 1).'%'], array_keys(array_slice($teste['probabilidades'], 0, 6, true)), array_slice($teste['probabilidades'], 0, 6, true)), ['titulo' => 'Probabilidade de cada resposta', 'escala' => 100])?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- 5. Últimas lições -->
<div class="pn-contagem" id="licoes"><h2>Últimas lições<?=$filtroModelo !== '' ? ' — '.e($todosModelos[$filtroModelo][0]) : ''?></h2>
  <form method="get" action="#licoes" class="ml-filtro"><label for="ml-filtro" class="sr-only">Filtrar por modelo</label>
    <select id="ml-filtro" name="modelo" onchange="this.form.submit()"><option value="">Todos os modelos</option><?php foreach ($todosModelos as $k => [$t]): ?><option value="<?=e($k)?>" <?=$filtroModelo === $k ? 'selected' : ''?>><?=e($t)?></option><?php endforeach; ?></select>
    <noscript><button class="btn btn-sm btn-outline">Filtrar</button></noscript></form>
</div>
<?php if ($licoes): ?>
<div class="table-wrap"><table class="table">
    <tr><th>Texto</th><th>Resposta certa</th><th>Modelo</th><th class="num">Vezes</th><th>Quem ensinou</th><th>Quando</th><th>Ações</th></tr>
    <?php foreach ($licoes as $l): ?>
    <tr>
        <td class="ml-texto-licao" title="<?=e($l['texto'])?>"><?=e(mb_strimwidth((string)$l['texto'], 0, 70, '…'))?></td>
        <td><b><?=e($rotuloClasse((string)$l['modelo'], (string)$l['classe']))?></b></td>
        <td class="meta"><?=e($todosModelos[$l['modelo']][0] ?? $l['modelo'])?></td>
        <td class="num"><?=(int)$l['vezes']?>×</td>
        <td class="meta"><?=e($l['usuario_nome'] ?? '—')?></td>
        <td class="meta"><?=e(pt_data_hora($l['updated_at']))?></td>
        <td><?=painel_acao('esquecer', (int)$l['id'], 'Esquecer', 'btn-outline', 'Apagar esta lição? A máquina deixa de usar este exemplo.', ['modelo' => $filtroModelo])?></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php else: ?><div class="empty">Nenhuma lição ainda. Clique em "Aprender com o histórico" ou revise e salve uma vaga extraída.</div><?php endif; ?>
</div>
