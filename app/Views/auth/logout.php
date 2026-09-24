<?php
/**
 * Confirmação de saída (rota view/usuario/logout.php), mostrada quando o pedido de saída
 * não veio do próprio site (proteção contra links maliciosos).
 */
?>
<div class="form">
    <h1>Sair da conta?</h1>
    <p class="muted">Confirme para encerrar a sua sessão neste navegador.</p>
    <form method="post" action="<?=url('view/usuario/logout.php')?>">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <div class="form-actions"><button class="btn">Sair</button><a class="btn btn-outline" href="<?=url(destinoPainel())?>">Continuar conectado</a></div>
    </form>
</div>
