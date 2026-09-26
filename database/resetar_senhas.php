<?php
declare(strict_types=1);

/*
 * ============================================================
 * RESETAR SENHAS DAS CONTAS DE DEMONSTRAÇÃO
 * ============================================================
 * Use quando não conseguir entrar (senha esquecida, conta bloqueada ou login em pausa):
 *   C:\xampp\php\php.exe database\resetar_senhas.php
 *
 * O que faz:
 *  1. volta as 3 contas de teste às senhas do README (e reativa as contas);
 *  2. apaga as tentativas de login erradas (tira a pausa de segurança do login).
 * Só roda pelo terminal: a pasta database/ é bloqueada no navegador.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente pelo terminal.'); }
require __DIR__.'/../app/Core/bootstrap.php';

$contas = [
    'admin@conectavagas.com' => 'Admin@123',
    'empresa@conectavagas.com' => 'Empresa@123',
    'candidato@conectavagas.com' => 'Candidato@123',
];

try {
    $db = Database::getConexao();
} catch (Throwable $e) {
    fwrite(STDERR, "Não foi possível conectar ao banco: ligue o MySQL no XAMPP.\n".$e->getMessage()."\n");
    exit(1);
}

$up = $db->prepare("UPDATE usuarios SET senha=?, ativo=1 WHERE email=?");
foreach ($contas as $email => $senha) {
    $up->execute([password_hash($senha, PASSWORD_DEFAULT), $email]);
    $ok = $up->rowCount() > 0 || ($u = (new UsuarioDAO())->buscarPorEmail($email)) && password_verify($senha, $u['senha']);
    echo str_pad($email, 30).($ok ? "senha: {$senha}" : 'conta não existe (importe database/seed.sql)').PHP_EOL;
}
$n = $db->exec("DELETE FROM tentativas_login");
echo "Tentativas de login erradas apagadas: {$n}. O login está liberado.".PHP_EOL;
echo "Sessões abertas dessas contas foram encerradas (a senha mudou): entre de novo.".PHP_EOL;
