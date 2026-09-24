<?php
/**
 * Página de erro (403, 404, 500, 503...). Não usa o layout do site, para funcionar
 * mesmo quando o banco está fora do ar.
 * Recebe de pagina_erro(): $codigo, $titulo, $mensagem (HTML montado pelo sistema),
 * $detalhe (texto técnico, só com DEBUG=true) e $inicio (link "Voltar ao início").
 */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($titulo)?> | <?=e(SITE_NAME)?></title>
<style>
  body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:#f1f5f9;color:#0f172a;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:16px}
  .caixa{background:#fff;max-width:620px;width:100%;border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.12);padding:32px;border-top:6px solid #1d4ed8}
  h1{margin:0 0 8px;font-size:24px}.cod{color:#64748b;font-size:13px;letter-spacing:.08em;text-transform:uppercase}
  p{line-height:1.55}ul{line-height:1.7}a.btn{display:inline-block;margin-top:12px;background:#1d4ed8;color:#fff;text-decoration:none;padding:10px 18px;border-radius:10px;font-weight:600}
  pre{white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#e2e8f0;padding:12px;border-radius:10px;font-size:12.5px}
</style>
</head>
<body>
<div class="caixa">
  <div class="cod"><?=e(SITE_NAME)?> · erro <?=(int)$codigo?></div>
  <h1><?=e($titulo)?></h1>
  <div><?=$mensagem?></div>
  <?php if (DEBUG && $detalhe !== ''): ?>
    <p><b>Detalhe técnico</b> <small>(visível porque DEBUG=true em config/config.php)</small></p>
    <pre><?=e($detalhe)?></pre>
  <?php endif; ?>
  <a class="btn" href="<?=e($inicio)?>">Voltar ao início</a>
</div>
</body>
</html>
