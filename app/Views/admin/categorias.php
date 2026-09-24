<?php
/**
 * CRUD de categorias de vagas e cursos (rota admin/pages/categorias.php) — só administrador.
 * Recebe de AdminController::categorias(): $cats e $edit.
 */
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Categorias', 'Áreas usadas para agrupar vagas e cursos. Excluir uma categoria deixa os itens dela sem categoria.')?>
<div class="form">
    <h3><?=$edit ? 'Editar categoria' : 'Nova categoria'?></h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($edit['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label>Nome</label><input name="nome" required maxlength="100" value="<?=e($edit['nome'] ?? '')?>"></div>
            <div><label>Usada em</label><select name="tipo"><?php foreach (CategoriaDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=($edit['tipo'] ?? 'vaga') === $t ? 'selected' : ''?>><?=$t === 'vaga' ? 'Vagas' : 'Cursos'?></option><?php endforeach; ?></select></div>
            <div><label>Ativa</label><select name="ativo"><option value="1">Sim</option><option value="0" <?=$edit && !(int)$edit['ativo'] ? 'selected' : ''?>>Não (some dos filtros)</option></select></div>
        </div>
        <div class="form-actions"><button class="btn">Salvar</button><?php if ($edit): ?><a class="btn btn-outline" href="<?=url('admin/pages/categorias.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>
<div class="pn-contagem"><h2>Categorias cadastradas</h2><span><?=gf_num(count(array_filter($cats, fn($c) => $c['tipo'] === 'vaga')))?> de vagas · <?=gf_num(count(array_filter($cats, fn($c) => $c['tipo'] === 'curso')))?> de cursos</span></div>
<div class="table-wrap"><table class="table">
    <tr><th>Nome</th><th>Usada em</th><th>Situação</th><th class="num">Itens</th><th>Ações</th></tr>
    <?php foreach ($cats as $x): ?>
    <tr>
        <td><?=e($x['nome'])?></td><td><?=$x['tipo'] === 'vaga' ? 'Vagas' : 'Cursos'?></td>
        <td><?=$x['ativo'] ? painel_status('ativa', 'Ativa') : painel_status('oculto', 'Inativa')?></td>
        <td class="num"><?=(int)$x['em_uso']?></td>
        <td><div class="actions">
            <a class="btn btn-sm btn-outline" href="?edit=<?=(int)$x['id']?>">Editar</a>
            <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?=(int)$x['id']?>"><button class="btn btn-sm btn-danger" data-confirm="<?=(int)$x['em_uso'] ? 'Esta categoria é usada por '.(int)$x['em_uso'].' item(ns), que ficarão sem categoria. Excluir?' : 'Excluir categoria?'?>">Excluir</button></form>
        </div></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php if (!$cats): ?><div class="empty">Nenhuma categoria cadastrada.</div><?php endif; ?>
</div>
