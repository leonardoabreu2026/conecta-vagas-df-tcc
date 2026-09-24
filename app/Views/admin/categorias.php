<?php
/**
 * CRUD de categorias de vagas e cursos (rota admin/pages/categorias.php) — só administrador.
 * Recebe de AdminController::categorias(): $cats e $edit.
 */
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Categorias', 'Áreas usadas para agrupar vagas e cursos. Desativar esconde a categoria dos filtros; excluir deixa os itens dela sem categoria.')?>
<div class="form">
    <h2 class="pn-form-titulo" id="form-categoria"><?=$edit ? 'Editar categoria' : 'Nova categoria'?></h2>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($edit['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label for="cat-nome">Nome</label><input id="cat-nome" name="nome" required maxlength="100" value="<?=e($edit['nome'] ?? '')?>"></div>
            <div><label for="cat-tipo">Usada em</label><select id="cat-tipo" name="tipo"><?php foreach (CategoriaDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=($edit['tipo'] ?? 'vaga') === $t ? 'selected' : ''?>><?=$t === 'vaga' ? 'Vagas' : 'Cursos'?></option><?php endforeach; ?></select></div>
            <div><label for="cat-ativo">Ativa</label><select id="cat-ativo" name="ativo"><option value="1">Sim</option><option value="0" <?=$edit && !(int)$edit['ativo'] ? 'selected' : ''?>>Não (some dos filtros)</option></select></div>
        </div>
        <div class="form-actions"><button class="btn">Salvar</button><?php if ($edit): ?><a class="btn btn-outline" href="<?=url('admin/pages/categorias.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>
<div class="pn-contagem"><h2>Categorias cadastradas</h2><span><?=gf_num(count(array_filter($cats, fn($c) => $c['tipo'] === 'vaga')))?> de vagas · <?=gf_num(count(array_filter($cats, fn($c) => $c['tipo'] === 'curso')))?> de cursos</span></div>
<div class="table-wrap"><table class="table">
    <tr><th>Nome</th><th>Usada em</th><th>Situação</th><th class="num">Itens</th><th>Ações</th></tr>
    <?php foreach ($cats as $x): $linkPublico = $x['tipo'] === 'vaga' ? 'vagas.php?categoria_id='.(int)$x['id'] : 'cursos.php?categoria_id='.(int)$x['id']; ?>
    <tr>
        <td><?=e($x['nome'])?></td><td><?=$x['tipo'] === 'vaga' ? 'Vagas' : 'Cursos'?></td>
        <td><?=$x['ativo'] ? painel_status('ativa', 'Ativa') : painel_status('oculto', 'Inativa')?></td>
        <td class="num"><?=(int)$x['em_uso']?></td>
        <td><div class="actions">
            <a class="btn btn-sm" href="<?=url($linkPublico)?>" target="_blank" rel="noopener">Ver<span class="sr-only"> <?=$x['tipo'] === 'vaga' ? 'vagas' : 'cursos'?> de <?=e($x['nome'])?> (abre em nova aba)</span></a>
            <a class="btn btn-sm btn-outline" href="?edit=<?=(int)$x['id']?>#form-categoria">Editar</a>
            <?=$x['ativo'] ? painel_acao('desativar', (int)$x['id'], 'Desativar') : painel_acao('ativar', (int)$x['id'], 'Ativar')?>
            <?=painel_acao('excluir', (int)$x['id'], 'Excluir', 'btn-danger', (int)$x['em_uso'] ? 'Esta categoria é usada por '.(int)$x['em_uso'].' item(ns), que ficarão sem categoria. Excluir?' : 'Excluir categoria?')?>
        </div></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php if (!$cats): ?><div class="empty">Nenhuma categoria cadastrada.</div><?php endif; ?>
</div>
