<?php
/**
 * Banco de talentos (rota admin/pages/talentos.php): completo no plano Premium, parcial no básico.
 * Recebe de EmpresaController::talentos(): $talentos, $isPremium, $termo e $cidade.
 * No básico, a busca não considera o nome e o "Ver portfólio" abre só a prévia (PerfilController::portfolio).
 */
?>
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>

<div class="section-head">
    <div>
        <h1>👥 Banco de Talentos do DF</h1>
        <p class="muted">Encontre candidatos qualificados para as suas vagas com busca por habilidades e experiências.</p>
    </div>
    <div>
        <?php if ($isPremium): ?>
            <span class="badge-pro" style="font-size: 13px; padding: 6px 14px;">💼 Empresa Premium · Acesso Completo</span>
        <?php else: ?>
            <a href="<?=url('planos.php')?>" class="btn btn-gold btn-sm">⭐ Desbloquear Acesso Completo</a>
        <?php endif; ?>
    </div>
</div>



<?php if (!$isPremium): ?>
    <div class="panel" style="border: 2px solid #3b82f6; background: linear-gradient(135deg,#eff6ff,#ffffff); margin-bottom: 24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
                <span class="badge-pro">Recurso Exclusivo Empresa Premium</span>
                <h2 style="margin: 10px 0 6px;">Pesquise e contrate os melhores talentos diretamente</h2>
                <p class="muted" style="margin:0; max-width: 650px;">
                    Com o <strong>Plano Empresa Premium</strong>, sua empresa pode visualizar perfis completos, dados de contato e fazer download imediato dos currículos de todos os candidatos do Distrito Federal.
                </p>
            </div>
            <div>
                <a href="<?=url('planos.php')?>" class="btn" style="background:#1d4ed8; padding:12px 20px;">
                    Assinar Empresa Premium (R$ 49,90/mês)
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<form class="search" method="get">
    <input name="q" placeholder="Buscar por cargo, habilidade ou competência (ex.: Excel, Vendas, TI)" value="<?=e($termo)?>">
    <input name="cidade" placeholder="Cidade (ex.: Brasília, Taguatinga)" value="<?=e($cidade)?>">
    <button class="btn">Pesquisar</button>
</form>
<?php if (!$isPremium): ?>
    <p class="small muted">No plano básico, a busca considera cargo, habilidades, experiências e cursos (não o nome do candidato).</p>
<?php endif; ?>

<?php if (!$talentos): ?>
    <div class="empty">Nenhum talento encontrado com os filtros informados.</div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($talentos as $t): ?>
            <article class="card" style="<?=$t['is_vip'] ? 'border: 2px solid #facc15;' : ''?>">
                <div class="card-body">
                    <div style="display:flex; justify-content:space-between; align-items:start; gap:8px;">
                        <div>
                            <?php if ($t['is_vip']): ?>
                                <span class="badge-vip" style="margin-bottom: 6px;">⭐ Candidato VIP</span><br>
                            <?php endif; ?>
                            <span class="tag"><?=e($t['nivel_experiencia'] ? rotulo((string)$t['nivel_experiencia']) : 'Candidato')?></span>
                        </div>
                        <span class="meta"><?=e($t['cidade'] ?: 'Brasília')?>/<?=e($t['uf'] ?: 'DF')?></span>
                    </div>

                    <h3 style="margin: 12px 0 4px;">
                        <?php if ($isPremium): ?>
                            <?=e($t['nome'])?>
                        <?php else: ?>
                            <?=e(mb_substr($t['nome'], 0, 4))?>*** (Candidato)
                        <?php endif; ?>
                    </h3>
                    <p class="meta" style="font-weight: 700; color: #1e3a8a;">
                        <?=e($t['titulo_profissional'] ?: 'Profissional em busca de oportunidades')?>
                    </p>

                    <p style="font-size: 13.5px; color: #475569; margin: 10px 0;">
                        <?=e(mb_strimwidth((string)($t['bio'] ?: $t['objetivo']), 0, 110, '...'))?>
                    </p>

                    <?php if (!empty($t['habilidades'])): ?>
                        <div style="margin: 8px 0; font-size: 12.5px; color: #334155;">
                            <b>Habilidades:</b> <?=e(mb_strimwidth((string)$t['habilidades'], 0, 80, '...'))?>
                        </div>
                    <?php endif; ?>

                    <a class="btn btn-sm btn-outline" style="width:100%;margin-top:8px" target="_blank" href="<?=url('view/perfil/portfolio.php?id='.(int)$t['id'])?>"><?=$isPremium ? 'Ver portfólio' : 'Ver prévia do portfólio'?></a>
                    <div style="margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
                        <?php if ($isPremium): ?>
                            <div>
                                <small class="muted"><?=e($t['telefone'] ?: $t['email'])?></small>
                            </div>
                            <?php if ($t['curriculo_id']): ?>
                                <a class="btn btn-sm btn-green" href="<?=url('download.php?id='.(int)$t['curriculo_id'])?>">📄 Ver currículo</a>
                            <?php else: ?>
                                <span class="meta">Sem currículo em anexo</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="meta" style="filter: blur(3px); user-select:none;">(61) 99999-9999</span>
                            <a href="<?=url('planos.php')?>" class="btn btn-sm btn-outline" style="font-size: 12px;">🔒 Liberar contato</a>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

