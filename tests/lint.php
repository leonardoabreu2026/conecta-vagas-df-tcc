<?php
declare(strict_types=1);

/*
 * ============================================================
 * CONFERÊNCIA DE SINTAXE — todos os arquivos PHP do projeto
 * ============================================================
 * Uso: C:\xampp\php\php.exe tests\lint.php
 * Roda "php -l" em cada .php (app, config, database, public, tests) e termina com código 1
 * se algum tiver erro. Usado pelo gancho de commit (.githooks/pre-commit) e por tests\verificar.bat.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente pelo terminal.'); }

$raiz = dirname(__DIR__);
$arquivos = [];
foreach (['app', 'config', 'database', 'public', 'tests'] as $pasta) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.DIRECTORY_SEPARATOR.$pasta, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && strtolower($f->getExtension()) === 'php') $arquivos[] = $f->getPathname();
}
$arquivos[] = $raiz.DIRECTORY_SEPARATOR.'index.php';

$erros = 0;
foreach ($arquivos as $a) {
    if (!is_file($a)) continue;
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($a).' 2>&1', $saida, $codigo);
    if ($codigo !== 0) {
        $erros++;
        echo '  ERRO  '.substr($a, strlen($raiz) + 1).PHP_EOL.'        '.implode(PHP_EOL.'        ', array_filter($saida, fn($l) => trim($l) !== '' && !str_starts_with($l, 'Errors parsing'))).PHP_EOL;
    }
    $saida = [];
}
echo $erros ? PHP_EOL."SINTAXE: {$erros} arquivo(s) com erro de ".count($arquivos).'.'.PHP_EOL : 'SINTAXE: '.count($arquivos).' arquivos PHP sem erros.'.PHP_EOL;
exit($erros ? 1 : 0);
