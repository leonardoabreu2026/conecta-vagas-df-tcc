<?php
/**
 * Candidaturas recebidas, com o match de cada candidato (rota admin/pages/candidaturas.php).
 * Recebe de EmpresaController::candidaturas(): $lista, $vagasFiltro, $vagaId, $status e $statusPermitidos.
 */
?>
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<h1>Candidaturas recebidas</h1>
<p class="muted">Ordenadas por: candidatos VIP primeiro, depois maior match com a vaga. Abra o portfólio para ver o perfil completo.</p>
<form class="filtros" method="get" style="grid-template-columns:2fr 1fr auto">
    <select name="vaga_id"><option value="">Todas as vagas</option><?php foreach ($vagasFiltro as $v): ?><option value="<?=(int)$v['id']?>" <?=$vagaId === (int)$v['id'] ? 'selected' : ''?>><?=e($v['titulo'])?><?=isAdmin() ? ' — '.e($v['empresa_nome'] ?? '') : ''?></option><?php endforeach; ?></select>
    <select name="status"><option value="">Todos os status</option><?php foreach (CandidaturaDAO::STATUS as $s): ?><option value="<?=$s?>" <?=$status === $s ? 'selected' : ''?>><?=e(rotulo($s))?></option><?php endforeach; ?></select>
    <button class="btn">Filtrar</button>
</form>

<?php foreach ($lista as $x): $d = $x['match_detalhes']; ?>
<div class="panel" style="margin-bottom:14px;<?=$x['is_vip'] ? 'border:2px solid #facc15;background:#fffdf0' : ''?>">
    <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap">
        <div style="flex:1;min-width:260px">
            <b style="font-size:17px"><?=e($x['candidato_nome'])?></b> <?=$x['is_vip'] ? '<span class="badge-vip" style="font-size:10px">⭐ VIP</span>' : ''?>
            <div class="meta"><?=e($x['titulo_profissional'] ?: 'Candidato')?> · <?=e(rotulo((string)$x['nivel_experiencia']))?> · <?=e($x['candidato_cidade'] ?? '')?></div>
            <div class="meta">Vaga: <b><?=e($x['titulo'])?></b><?=isAdmin() ? ' · '.e($x['empresa_nome']) : ''?> · enviada em <?=date('d/m/Y H:i', strtotime($x['data_candidatura']))?></div>
            <div class="meta">📧 <?=e($x['email'])?><?=$x['telefone'] ? ' · 📞 '.e($x['telefone']) : ''?></div>
            <?php if (!empty($d['competencias']['atendidas']) || !empty($d['competencias']['faltantes'])): ?>
                <div class="chips"><?php foreach ($d['competencias']['atendidas'] ?? [] as $c): ?><span class="chip ok">✓ <?=e($c)?></span><?php endforeach; ?><?php foreach ($d['competencias']['faltantes'] ?? [] as $c): ?><span class="chip falta"><?=e($c)?></span><?php endforeach; ?></div>
                <?php if (!empty($d['observacoes'])): ?><div class="small"><?php foreach ($d['observacoes'] as $o): ?><div><?=!empty($o['ok']) ? '✅' : '⚠️'?> <?=e($o['texto'] ?? '')?></div><?php endforeach; ?></div><?php endif; ?>
            <?php endif; ?>
            <?php if ($x['carta_apresentacao']): ?><details><summary class="small" style="cursor:pointer;color:#1d4ed8">Carta de apresentação</summary><p class="small" style="white-space:pre-line"><?=e($x['carta_apresentacao'])?></p></details><?php endif; ?>
            <div class="actions" style="margin-top:8px">
                <a class="btn btn-sm btn-outline" target="_blank" href="<?=url('view/perfil/portfolio.php?id='.(int)$x['candidato_perfil_id'])?>">Ver portfólio</a>
                <?php if ($x['curriculo_id']): ?><a class="btn btn-sm btn-green" target="_blank" href="<?=url('download.php?id='.(int)$x['curriculo_id'])?>">📄 Currículo (<?=e(strtoupper((string)$x['curriculo_tipo']))?>)</a><?php else: ?><span class="meta">Currículo removido pelo candidato</span><?php endif; ?>
            </div>
        </div>
        <div style="text-align:center">
            <?php if ($x['match_pontuacao'] !== null): ?>
                <span class="score-badge <?=e((string)$x['match_nivel'])?>" style="font-size:20px;padding:10px 14px"><?=number_format((float)$x['match_pontuacao'], 0)?>%</span><div class="meta">match</div>
            <?php else: ?><span class="meta">match indisponível<br>(vaga inativa)</span><?php endif; ?>
        </div>
        <?php if (!isAdmin() && $x['status'] === 'cancelada'): ?>
        <div style="min-width:260px;flex:1;max-width:380px"><div class="notice small">O candidato cancelou esta candidatura. Ela fica só como histórico e não pode mais ser alterada.</div></div>
        <?php else: ?>
        <form method="post" style="min-width:260px;flex:1;max-width:380px">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$x['id']?>"><input type="hidden" name="vaga_id" value="<?=$vagaId?>"><input type="hidden" name="filtro_status" value="<?=e($status)?>">
            <label class="small">Status</label>
            <select name="status"><?php foreach ($statusPermitidos as $s): ?><option value="<?=$s?>" <?=$x['status'] === $s ? 'selected' : ''?>><?=e(rotulo($s))?></option><?php endforeach; ?></select>
            <label class="small">Retorno para o candidato</label>
            <textarea name="observacao" rows="2" maxlength="2000" placeholder="Ex.: Entrevista na segunda às 10h."><?=e($x['observacao_empresa'])?></textarea>
            <div class="actions"><button class="btn btn-sm">Salvar</button>
            <?php if (isAdmin()): ?><button class="btn btn-sm btn-danger" name="acao" value="excluir" data-confirm="Excluir esta candidatura?">Excluir</button><?php endif; ?></div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php if (!$lista): ?><div class="empty">Nenhuma candidatura encontrada.</div><?php endif; ?>
