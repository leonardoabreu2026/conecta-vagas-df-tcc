<?php
declare(strict_types=1);

/*
 * ============================================================
 * TESTE RÁPIDO (smoke test) — confere se o sistema está de pé
 * ============================================================
 * Uso (na pasta do projeto, com Apache e MySQL ligados no XAMPP):
 *   C:\xampp\php\php.exe tests\smoke.php
 *   C:\xampp\php\php.exe tests\smoke.php http://localhost/outra%20pasta/   (endereço diferente)
 *
 * Verifica, sem gravar nada no banco (a única mudança é +1 no contador de visualizações da vaga 1):
 *  1. se todas as classes de app/ carregam (autoloader);
 *  2. as regras principais: funções de apoio, extração de vagas/cursos e máquina de match;
 *  3. a conexão com o banco e as contas de teste;
 *  4. as páginas pelo navegador (HTTP) e o bloqueio das pastas internas.
 * Termina com código 1 se algo falhar.
 */

require __DIR__.'/../app/Core/bootstrap.php';

$falhas = 0;
function confere(string $descricao, bool $ok, string $detalhe = ''): void {
    global $falhas;
    if (!$ok) $falhas++;
    echo ($ok ? '  OK    ' : '  FALHA ').$descricao.($ok || $detalhe === '' ? '' : " → $detalhe").PHP_EOL;
}

// ------------------------------------------------------------
echo PHP_EOL.'1. Classes (autoloader)'.PHP_EOL;
foreach (['Controllers', 'Models', 'DTO', 'Services', 'Services/Extracao'] as $pasta) {
    $classes = array_map(fn($f) => basename($f, '.php'), glob(APP_DIR."/$pasta/*.php") ?: []);
    $faltando = array_filter($classes, fn($c) => !class_exists($c));
    confere("app/$pasta: ".count($classes).' classe(s)', !$faltando, implode(', ', $faltando));
}

// ------------------------------------------------------------
echo PHP_EOL.'2. Regras de negócio'.PHP_EOL;
confere('decimal_ou_null("1.234,56") = 1234.56', decimal_ou_null('1.234,56') === 1234.56);
confere('salario_texto(1900, 2500)', salario_texto(1900, 2500) === 'R$ 1.900,00 a R$ 2.500,00');
confere('rotulo("em_analise") = "Em análise"', rotulo('em_analise') === 'Em análise');
confere('caminho_upload() aceita só uploads simples', caminho_upload('assets/uploads/foto_1.png') === UPLOAD_DIR.'foto_1.png'
    && caminho_upload('assets/uploads/../config/config.php') === null && caminho_upload('config/config.php') === null);
confere('caminho_imagem_valido() bloqueia ".."', caminho_imagem_valido('assets/img/vagas/vaga1.jpg') !== '' && caminho_imagem_valido('assets/img/../../x.png') === '');

$comp = Competencias::extrair('Experiência com vendas, atendimento ao cliente e Excel avançado');
confere('Competencias::extrair encontra Vendas, Atendimento e Excel', !array_diff(['Vendas', 'Atendimento ao cliente', 'Excel'], $comp), implode(', ', $comp));

$vaga = ExtracaoVaga::doTexto("VAGA: Vendedor Interno - Taguatinga\nSalário: R$ 3.000 a R$ 5.500 + comissões\nRequisitos: experiência com vendas e Excel\nBenefícios: VT + VR R$ 33,40");
confere('ExtracaoVaga: título, salário (ignora o VR) e cidade', $vaga['titulo'] === 'Vendedor Interno' && $vaga['salario_minimo'] === 3000.0
    && $vaga['salario_maximo'] === 5500.0 && $vaga['cidade'] === 'Taguatinga', json_encode([$vaga['titulo'], $vaga['salario_minimo'], $vaga['salario_maximo'], $vaga['cidade']], JSON_UNESCAPED_UNICODE));

$vaga2 = ExtracaoVaga::doTexto("GRUPO DOURADO\nAUXILIAR DE COZINHA\nÁguas Claras - 2 vagas\nHorário: 14h20 às 22h, CLT 6x1\nSalário a partir de R$ 1.900,00 + VT + alimentação no local");
confere('ExtracaoVaga: "Horário:" não engole o salário/benefícios da linha seguinte', $vaga2['beneficios'] !== '' && $vaga2['salario_minimo'] === 1900.0
    && $vaga2['anunciante'] === 'Grupo Dourado' && $vaga2['cidade'] === 'Águas Claras', json_encode([$vaga2['beneficios'], $vaga2['salario_minimo'], $vaga2['anunciante'], $vaga2['cidade']], JSON_UNESCAPED_UNICODE));
$titulos = array_map(fn($t) => ExtracaoVaga::doTexto($t)['titulo'], [
    "ESTÁGIO EM ENFERMAGEM (cód. 1308)\nLocal: Taguatinga\nBolsa-auxílio de R$ 750,00",
    "O Giraffas está contratando atendente de lanchonete para o Shopping Boulevard",
    "Temporário - Operador de Caixa\nLocal: Taguatinga Shopping",
    "Desenvolvedor PHP Júnior\nModelo híbrido - Brasília/DF",
]);
confere('ExtracaoVaga: títulos (código da vaga, "contratando X", "Temporário -", siglas)', $titulos === ['Estágio em Enfermagem', 'Atendente de lanchonete', 'Operador de Caixa', 'Desenvolvedor PHP Júnior'], json_encode($titulos, JSON_UNESCAPED_UNICODE));
$rel = ExtracaoVaga::relatorio($vaga, 'Vendas');
confere('ExtracaoVaga::relatorio conta lidos, padrão e faltando', $rel['lidos'] + $rel['padrao'] + $rel['faltando'] === count($rel['itens']) && $rel['lidos'] >= 5
    && in_array('nivel_experiencia', array_column(array_filter($rel['itens'], fn($i) => $i['status'] === 'padrao'), 'campo'), true), json_encode([$rel['lidos'], $rel['padrao'], $rel['faltando']]));
confere('Pix: CRC16 do exemplo oficial do Banco Central = 1D3D', Pix::crc16('00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***6304') === '1D3D');
$pix = Pix::payload('teste@conectavagas.com', 'Conecta Vagas DF', 'Brasília');
confere('Pix: código copia e cola com CRC válido e cidade sem acento', str_contains($pix, '6008BRASILIA') && substr($pix, -4) === Pix::crc16(substr($pix, 0, -4)), $pix);
confere('like() trata % e _ como texto', like('50%_off') === '%50\\%\\_off%');

$curso = ExtracaoCurso::doTexto('EXCEL AVANÇADO — Curso online e gratuito da Fundação Bradesco. Carga horária: 12 horas. https://www.ev.org.br/cursos/excel');
confere('ExtracaoCurso: instituição, duração, gratuito e link', $curso['instituicao'] === 'Fundação Bradesco – Escola Virtual' && $curso['duracao'] === '12 horas'
    && $curso['gratuito'] === 1 && $curso['url'] === 'https://www.ev.org.br/cursos/excel', json_encode([$curso['instituicao'], $curso['duracao'], $curso['url']], JSON_UNESCAPED_UNICODE));

$candidato = ['perfil' => ['cidade' => 'Taguatinga', 'uf' => 'DF', 'nivel_experiencia' => 'junior'], 'competencias' => ['Vendas', 'Excel'],
              'tokens_titulo' => ['vendedor' => true], 'tokens_historico' => [], 'mudanca' => false, 'viagens' => false, 'cnh' => '', 'pcd' => false];
$r = (new MatchService())->calcular($candidato, ['titulo' => 'Vendedor', 'requisitos' => 'vendas e Excel', 'cidade' => 'Taguatinga', 'uf' => 'DF', 'nivel_experiencia' => 'junior', 'remoto' => 'presencial']);
confere('MatchService: candidato ideal = 100 (excelente)', $r['pontuacao'] === 100.0 && $r['nivel'] === 'excelente', $r['pontuacao'].' / '.$r['nivel']);
confere('classe_match() usa os mesmos cortes do match', classe_match(75) === 'excelente' && classe_match(55) === 'alto' && classe_match(35) === 'medio' && classe_match(10) === 'baixo');

$exp = Portfolio::experiencias("ATACADÃO DIA A DIA\nAuxiliar Administrativo\n2021 – 2024");
confere('Portfolio::experiencias separa empresa, cargo e período', ($exp[0]['empresa'] ?? '') === 'ATACADÃO DIA A DIA' && ($exp[0]['cargo'] ?? '') === 'Auxiliar Administrativo', json_encode($exp[0] ?? null, JSON_UNESCAPED_UNICODE));

$_SERVER['REQUEST_URI'] = BASE_URL.'vaga.php?id=3';
confere('Router::caminhoPedido() → "vaga.php"', Router::caminhoPedido() === 'vaga.php', Router::caminhoPedido());

// ------------------------------------------------------------
echo PHP_EOL.'3. Banco de dados ('.DB_NAME.')'.PHP_EOL;
try {
    $db = Database::getConexao();
    confere('conexão PDO', true);
    $tabelas = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $esperadas = ['assinaturas','candidaturas','categorias','curriculos','cursos','matches','perfis','redefinicoes_senha','tentativas_login','usuarios','vagas'];
    confere('11 tabelas do database/schema.sql', !array_diff($esperadas, $tabelas), implode(', ', array_diff($esperadas, $tabelas)));
    $contas = $db->query("SELECT email FROM usuarios WHERE email IN ('admin@conectavagas.com','empresa@conectavagas.com','candidato@conectavagas.com')")->fetchAll(PDO::FETCH_COLUMN);
    confere('contas de teste do database/seed.sql', count($contas) === 3, count($contas).' de 3 encontradas');
    confere('vagas abertas listadas pelo VagaDAO', count((new VagaDAO())->listar(true)) > 0);
    $cruzadas = (int)$db->query("SELECT COUNT(*) FROM vagas v JOIN categorias c ON c.id=v.categoria_id WHERE c.tipo<>'vaga'")->fetchColumn()
              + (int)$db->query("SELECT COUNT(*) FROM cursos cu JOIN categorias c ON c.id=cu.categoria_id WHERE c.tipo<>'curso'")->fetchColumn();
    confere('nenhuma vaga em categoria de curso (nem curso em categoria de vaga)', $cruzadas === 0, "$cruzadas item(ns) na categoria do tipo errado");
} catch (Throwable $e) {
    confere('conexão com o banco', false, $e->getMessage());
}

// ------------------------------------------------------------
$base = $argv[1] ?? null;
if ($base === null) {
    // Padrão: a pasta do projeto dentro do htdocs (ex.: http://localhost/TCC%20v2/TCC_GUSTAVO/).
    $rel = preg_split('#[\\\\/]htdocs[\\\\/]#i', ROOT_DIR)[1] ?? '';
    $base = 'http://localhost/'.implode('/', array_map('rawurlencode', preg_split('#[\\\\/]#', $rel) ?: [])).'/';
}
$base = rtrim($base, '/').'/';
echo PHP_EOL."4. Páginas (HTTP) em $base".PHP_EOL;
$status = function (string $caminho) use ($base): int {
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    @file_get_contents($base.$caminho, false, $ctx);
    return (int)(preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m) ? $m[1] : 0);
};
if ($status('') === 0) {
    confere('Apache respondendo', false, 'ligue o Apache no XAMPP ou informe o endereço: php tests\smoke.php http://localhost/sua%20pasta/');
} else {
    foreach (['' => 200, 'vagas.php' => 200, 'vaga.php?id=1' => 200, 'cursos.php' => 200, 'cursos.php?tipo=ebook' => 200, 'curso.php?id=1' => 200,
              'planos.php' => 200, 'login.php' => 200, 'cadastro.php' => 200, 'esqueci_senha.php' => 200, 'contrato.php' => 200,
              'assets/css/app.css' => 200, 'vaga.php?id=999999' => 404, 'nao-existe.php' => 404,
              'view/perfil/index.php' => 302, 'admin/index.php' => 302, 'download.php?id=1' => 302] as $caminho => $esperado) {
        $s = $status($caminho);
        confere(sprintf('%-24s → %d', $caminho === '' ? '/' : $caminho, $esperado), $s === $esperado, "recebeu $s");
    }
    foreach (['config/config.php', 'app/Core/Database.php', 'database/schema.sql', 'storage/.gitkeep', 'tests/smoke.php'] as $interno) {
        $s = $status($interno);
        confere(sprintf('%-24s bloqueado', $interno), in_array($s, [403, 404], true), "recebeu $s");
    }
}

echo PHP_EOL.($falhas ? "RESULTADO: {$falhas} falha(s)." : 'RESULTADO: tudo certo.').PHP_EOL;
exit($falhas ? 1 : 0);
