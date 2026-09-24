<?php
/**
 * CRUD de usuários (rota admin/pages/usuarios.php) — só administrador.
 * Recebe de AdminController::usuarios(): $usuarios, $edit, $filtroTipo, $busca e $meuId.
 */
?>
<div class="pn">
<?php require __DIR__.'/../layouts/admin_nav.php'; ?>
<?=painel_cabecalho('Usuários', 'Cadastre, edite e bloqueie contas. Você não pode excluir nem rebaixar a própria conta, e o sistema mantém pelo menos um administrador ativo.')?>
<div class="form">
    <h3><?= $edit ? 'Editar usuário #'.(int)$edit['id'] : 'Novo usuário' ?></h3>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="id" value="<?=(int)($edit['id'] ?? 0)?>">
        <div class="form-grid">
            <div><label>Nome</label><input name="nome" required maxlength="255" value="<?=e($edit['nome'] ?? '')?>"></div>
            <div><label>E-mail</label><input type="email" name="email" required maxlength="255" value="<?=e($edit['email'] ?? '')?>"></div>
            <div><label>Tipo</label><select name="tipo"><?php foreach (UsuarioDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=($edit['tipo'] ?? 'candidato') === $t ? 'selected' : ''?>><?=e(rotulo($t))?></option><?php endforeach; ?></select></div>
            <div><label>Telefone</label><input name="telefone" maxlength="30" value="<?=e($edit['telefone'] ?? '')?>"></div>
            <div><label>Senha <?= $edit ? '<small class="muted">(deixe vazio para manter)</small>' : '' ?></label><input type="password" name="senha" minlength="6" maxlength="72" <?=$edit ? '' : 'required'?> autocomplete="new-password"></div>
            <div><label>Ativo</label><select name="ativo"><option value="1" <?=(!$edit || (int)$edit['ativo']) ? 'selected' : ''?>>Sim</option><option value="0" <?=$edit && !(int)$edit['ativo'] ? 'selected' : ''?>>Não (bloqueia o login)</option></select></div>
        </div>
        <div class="form-actions"><button class="btn">Salvar</button><?php if ($edit): ?><a class="btn btn-outline" href="<?=url('admin/pages/usuarios.php')?>">Cancelar</a><?php endif; ?></div>
    </form>
</div>

<form class="filtros" method="get" style="grid-template-columns:2fr 1fr auto">
    <input name="q" placeholder="Buscar por nome ou e-mail" value="<?=e($busca)?>" aria-label="Buscar por nome ou e-mail">
    <select name="tipo" aria-label="Tipo de conta"><option value="">Todos os tipos</option><?php foreach (UsuarioDAO::TIPOS as $t): ?><option value="<?=$t?>" <?=$filtroTipo === $t ? 'selected' : ''?>><?=e(rotulo($t))?></option><?php endforeach; ?></select>
    <button class="btn">Filtrar</button>
</form>
<div class="pn-contagem"><h2>Contas</h2><span><?=gf_num(count($usuarios))?> <?=gf_plural(count($usuarios), 'usuário encontrado', 'usuários encontrados')?></span></div>
<div class="table-wrap"><table class="table">
    <tr><th class="num">ID</th><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Situação</th><th>Último acesso</th><th>Ações</th></tr>
    <?php foreach ($usuarios as $x): ?>
    <tr>
        <td class="num meta"><?=(int)$x['id']?></td><td><?=e($x['nome'])?><?=(int)$x['id'] === $meuId ? ' <small class="meta">(você)</small>' : ''?></td><td><?=e($x['email'])?></td>
        <td><?=painel_status((string)$x['tipo'])?></td>
        <td><?=$x['ativo'] ? painel_status('ativo', 'Ativo') : painel_status('bloqueado', 'Bloqueado')?></td>
        <td class="meta"><?=$x['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($x['ultimo_acesso'])) : '—'?></td>
        <td><div class="actions">
            <a class="btn btn-sm btn-outline" href="?edit=<?=(int)$x['id']?>">Editar</a>
            <?php if ((int)$x['id'] !== $meuId): ?>
            <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?=(int)$x['id']?>"><button class="btn btn-sm btn-danger" data-confirm="Excluir este usuário e todos os dados dele?">Excluir</button></form>
            <?php endif; ?>
        </div></td>
    </tr>
    <?php endforeach; ?>
</table></div>
<?php if (!$usuarios): ?><div class="empty">Nenhum usuário encontrado.</div><?php endif; ?>
</div>
