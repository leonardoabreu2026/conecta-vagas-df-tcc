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
 *     e a máquina de aprendizado (Naive Bayes, correção humana, decisão híbrida) com modelos em memória;
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
foreach (['Controllers', 'Models', 'DTO', 'Services', 'Services/Extracao', 'Services/Aprendizado'] as $pasta) {
    $classes = array_map(fn($f) => basename($f, '.php'), glob(APP_DIR."/$pasta/*.php") ?: []);
    $faltando = array_filter($classes, fn($c) => !class_exists($c) && !trait_exists($c));
    confere("app/$pasta: ".count($classes).' classe(s)', !$faltando, implode(', ', $faltando));
}

// ------------------------------------------------------------
echo PHP_EOL.'2. Regras de negócio'.PHP_EOL;
// As regras são conferidas SEM o aprendizado: o que a máquina aprendeu no banco não pode mudar estes resultados.
MaquinaAprendizado::ligar(false);
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
$cartaz = ExtracaoVaga::doTexto("DOM CASERO\nVENDEDORA\nIdeal Primeiro emprego\nsaLÁário: R$ 2.700,00\nSALÁRIO: R$ 2.110,00\nRequisitos: cursando Administração\nBolsa de R$ 900 + VT\nVT (DF ou GO)\nPremiação por assiduidade\nPremiação por assiduidade.\nTemos outras vagas também!",
    ['destaques' => ['VENDEDORA', 'GERENTE'], 'complemento' => [], 'todas' => ['DOM CASERO', 'DOM CASERO', 'VENDEDORA']]);
confere('ExtracaoVaga (cartaz): empresa repetida, cargos grandes, faixa salarial, VT e sem linha repetida',
    $cartaz['anunciante'] === 'Dom Casero' && $cartaz['titulo'] === 'Vendedora / Gerente' && $cartaz['salario_minimo'] === 900.0 && $cartaz['salario_maximo'] === 2700.0
    && str_contains($cartaz['beneficios'], 'VT (DF ou GO)') && substr_count($cartaz['beneficios'], 'Premiação por assiduidade') === 1
    && str_contains($cartaz['beneficios'], 'Bolsa') && !str_contains($cartaz['requisitos'], 'Bolsa') && !str_contains($cartaz['descricao'], 'outras vagas'),
    json_encode([$cartaz['anunciante'], $cartaz['titulo'], $cartaz['salario_minimo'], $cartaz['salario_maximo'], $cartaz['beneficios'], $cartaz['requisitos']], JSON_UNESCAPED_UNICODE));
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

$lote = ExtracaoCurso::fichas("Aqui estão:\n**Título:** Excel Básico\n**Tipo:** E-book\n**Modalidade:** EAD\n**Área:** Informática e Excel\n**Link:** [ev](https://www.ev.org.br/x)\n---\nTítulo: Atendimento\nModalidade: Presencial\nCidade: Taguatinga/DF\nGratuito: Não\nPreço: R$ 120,00\nLink: https://www.senac.br/y\n---\nEspero ter ajudado!",
    ['Informática e Excel', 'Administração e Atendimento']);
confere('ExtracaoCurso::fichas: lote do Perplexity (markdown, e-book, presencial com cidade, preço, capa da instituição)', count($lote) === 2
    && $lote[0]['tipo'] === 'ebook' && $lote[0]['url'] === 'https://www.ev.org.br/x'
    && $lote[1]['modalidade'] === 'presencial' && $lote[1]['gratuito'] === 0 && $lote[1]['preco'] === 120.0 && str_contains($lote[1]['descricao'], 'Taguatinga/DF'),
    json_encode(array_map(fn($c) => [$c['titulo'], $c['tipo'], $c['modalidade'], $c['gratuito'], $c['preco'], $c['url']], $lote), JSON_UNESCAPED_UNICODE));
confere('ExtracaoCurso::capa segue a instituição (banner com marca nunca vai para outra)', ExtracaoCurso::capa('Fundação Bradesco – Escola Virtual') === 'assets/img/cursos/curso1.png'
    && ExtracaoCurso::capa('Escola Virtual.Gov (Enap)') === 'assets/img/cursos/curso3.png' && ExtracaoCurso::capa('Google (Grow with Google)') === 'assets/img/cursos/curso4.png'
    && ExtracaoCurso::capa('Banco Central do Brasil') === '' && ExtracaoCurso::capa('SENAC') === '' && $lote[1]['imagem'] === ''
    && ExtracaoCurso::capa('Sebrae', '', 'ebook') === '' && $lote[0]['imagem'] === '');   // banner anuncia "cursos": e-book fica com a capa do formato
confere('ExtracaoCurso::promptPesquisa usa as categorias do sistema', str_contains(ExtracaoCurso::promptPesquisa(['Área X']), 'Área: uma destas: Área X'));
confere('ExtracaoCurso: link com parênteses não é cortado (".../Cartilha%20(2)%20(1).pdf")',
    ExtracaoCurso::primeiroLink('Link: https://x.gov.br/Cartilha%20(2)%20(1).pdf') === 'https://x.gov.br/Cartilha%20(2)%20(1).pdf'
    && ExtracaoCurso::primeiroLink('(veja https://site.com/a).') === 'https://site.com/a');
$comImagem = ExtracaoCurso::fichas("Título: Guia X\nTipo: E-book\nLink: https://x.gov.br/guia.pdf\nImagem: https://x.gov.br/capa.jpg\n---\nTítulo: Curso Y\nLink: https://x.gov.br/y\nImagem: Não encontrada\n---");
confere('Padrão da ficha tem Imagem: o prompt pede e a máquina lê', str_contains(ExtracaoCurso::promptPesquisa([]), 'Imagem:')
    && ($comImagem[0]['imagem_url'] ?? '') === 'https://x.gov.br/capa.jpg' && ($comImagem[1]['imagem_url'] ?? null) === '');
confere('ImagemRemota: acha a imagem da página (og:image, "Imagem do curso", galeria de loja) e pula a provisória',
    ImagemRemota::daPagina('<meta content="/img/c.png" property="og:image">', 'https://s.gov.br/cursos/1') === 'https://s.gov.br/img/c.png'
    && ImagemRemota::daPagina('<img src="https://cdn.x.br/imagem_curso_1.jpg" alt="Imagem do curso: X">', 'https://x.br/') === 'https://cdn.x.br/imagem_curso_1.jpg'
    && ImagemRemota::candidatas('<meta property="og:image" content="https://l.br/media/catalog/product/placeholder/a.png"><script>{"full":"https:\/\/l.br\/media\/catalog\/product\/l\/m\/lms_img_9.png"}</script>', 'https://l.br/p') === ['https://l.br/media/catalog/product/l/m/lms_img_9.png']
    && ImagemRemota::daPagina('<meta property="og:image" content="javascript:alert(1)">', 'https://x.br/') === '');
confere('ImagemRemota: só servidor público (nada de localhost ou rede interna)', !ImagemRemota::linkPublico('http://127.0.0.1/a.jpg') && !ImagemRemota::linkPublico('http://localhost/a.jpg')
    && !ImagemRemota::linkPublico('http://192.168.0.10/a.jpg') && !ImagemRemota::linkPublico('http://[::1]/a.jpg') && !ImagemRemota::linkPublico('ftp://x.com/a.jpg'));
confere('Competencias: "construção de um ambiente", "proteção de dados" e "cobrança por consumo" não viram competência',
    Competencias::extrair('a construção de um ambiente positivo, proteção de dados e cobrança por consumo') === []
    && Competencias::extrair('Operador de cobrança') === ['Telemarketing'] && in_array('Construção civil', Competencias::extrair('Eletricista de obra'), true));
confere('Competencias::doCurso: a área só entra quando título e descrição não dizem nada',
    !in_array('UX e design', Competencias::doCurso(['titulo' => 'Power BI', 'descricao' => 'painéis e indicadores', 'categoria_nome' => 'Marketing, Dados e UX']), true)
    && Competencias::doCurso(['titulo' => 'Módulo 1', 'descricao' => '', 'categoria_nome' => 'Informática e Excel']) === ['Excel', 'Informática']);

$candidato = ['perfil' => ['cidade' => 'Taguatinga', 'uf' => 'DF', 'nivel_experiencia' => 'junior'], 'competencias' => ['Vendas', 'Excel'],
              'tokens_titulo' => ['vendedor' => true], 'tokens_historico' => [], 'mudanca' => false, 'viagens' => false, 'cnh' => '', 'pcd' => false];
$r = (new MatchService())->calcular($candidato, ['titulo' => 'Vendedor', 'requisitos' => 'vendas e Excel', 'cidade' => 'Taguatinga', 'uf' => 'DF', 'nivel_experiencia' => 'junior', 'remoto' => 'presencial']);
confere('MatchService: candidato ideal = 100 (excelente)', $r['pontuacao'] === 100.0 && $r['nivel'] === 'excelente', $r['pontuacao'].' / '.$r['nivel']);
confere('classe_match() usa os mesmos cortes do match', classe_match(75) === 'excelente' && classe_match(55) === 'alto' && classe_match(35) === 'medio' && classe_match(10) === 'baixo');

$exp = Portfolio::experiencias("ATACADÃO DIA A DIA\nAuxiliar Administrativo\n2021 – 2024");
confere('Portfolio::experiencias separa empresa, cargo e período', ($exp[0]['empresa'] ?? '') === 'ATACADÃO DIA A DIA' && ($exp[0]['cargo'] ?? '') === 'Auxiliar Administrativo', json_encode($exp[0] ?? null, JSON_UNESCAPED_UNICODE));

confere('pt_secao_formato: cada formato tem a sua página (cursos, e-books, vídeos)', pt_secao_formato('curso')[1] === url('cursos.php')
    && pt_secao_formato('ebook')[1] === url('cursos.php?tipo=ebook') && pt_secao_formato('video')[1] === url('cursos.php?tipo=video')
    && pt_secao_formato('xyz')[0] === 'Cursos');

// ------------------------------------------------------------
echo PHP_EOL.'2b. Máquina de aprendizado (modelos em memória, sem banco)'.PHP_EOL;
$pal = Tokenizador::palavras('Vale-Refeição de R$ 33,40 + Plano de Saúde');
confere('Tokenizador: sem acento, sem palavra vazia, dinheiro marcado e pares vizinhos', in_array('vale refeicao', $pal, true) && in_array('#dinheiro', $pal, true)
    && in_array('plano saude', $pal, true) && !in_array('de', $pal, true), implode(' | ', $pal));

$nb = new NaiveBayes();
foreach (['Vale transporte e vale refeição', 'Plano de saúde e odontológico', 'Vale alimentação de R$ 500', 'Seguro de vida', 'Uniforme fornecido pela empresa'] as $t) $nb->aprender(Tokenizador::palavras($t), 'beneficios');
foreach (['Experiência de 6 meses na função', 'Ensino médio completo', 'Disponibilidade de horário', 'Experiência com atendimento'] as $t) $nb->aprender(Tokenizador::palavras($t), 'requisitos');
foreach (['Atender clientes no balcão', 'Organizar o estoque da loja', 'Repor mercadorias nas prateleiras'] as $t) $nb->aprender(Tokenizador::palavras($t), 'descricao');
$prev = $nb->prever(Tokenizador::palavras('Plano odontológico e vale transporte'));
confere('NaiveBayes: aprende e prevê ("plano odontológico e vale transporte" → benefícios)', ($prev['classe'] ?? '') === 'beneficios' && $prev['confianca'] > 0.5
    && abs(array_sum($prev['probabilidades']) - 1) < 1e-9, json_encode($prev, JSON_UNESCAPED_UNICODE));
confere('NaiveBayes: explica a decisão pelas palavras que pesaram', array_key_exists('vale', $nb->explicar(Tokenizador::palavras('Plano odontológico e vale transporte'), 'beneficios')));
confere('NaiveBayes: texto sem nenhuma palavra conhecida → não opina (null)', $nb->prever(Tokenizador::palavras('xyzw kkkk')) === null);
$nb->esquecer(Tokenizador::palavras('Seguro de vida'), 'beneficios');
confere('NaiveBayes: esquecer desfaz a lição (incremental nos dois sentidos)', $nb->exemplosPorClasse()['beneficios'] === 4 && $nb->prever(Tokenizador::palavras('seguro vida')) === null);

$salvos = ['descricao' => 'Atender clientes', 'requisitos' => "Ensino médio\nEscala 6x1, folga aos domingos", 'beneficios' => 'VT + VR'];
confere('CorrecaoHumana: acha onde a pessoa deixou cada linha (e ignora a apagada)', CorrecaoHumana::licoesDeLinhas(['Escala 6x1, folga aos domingos', 'Linha apagada pela pessoa', 'Atender clientes'], $salvos)
    === [['texto' => 'Escala 6x1, folga aos domingos', 'classe' => 'requisitos'], ['texto' => 'Atender clientes', 'classe' => 'descricao']]);
confere('CorrecaoHumana: linha curta apagada ("Vendas") não vira lição só porque o campo tem "Experiência em vendas"',
    CorrecaoHumana::licoesDeLinhas(['Vendas', 'Atendimento'], ['requisitos' => 'Experiência em vendas', 'descricao' => 'Atendimento ao cliente no balcão']) === []);
confere('CorrecaoHumana: confere acerto por linha e por campo (1900 = "1900.00"; vazio nos dois não conta)',
    CorrecaoHumana::conferirLinhas(['Escala 6x1, folga aos domingos', 'Atender clientes'], ['descricao' => "Atender clientes\nEscala 6x1, folga aos domingos"], $salvos) === [2, 1]
    && CorrecaoHumana::conferirCampos(['salario_minimo' => 1900.0, 'titulo' => 'Vendedor', 'contato' => ''], ['salario_minimo' => '1900.00', 'titulo' => 'Vendedora', 'contato' => ''], ['salario_minimo', 'titulo', 'contato'])
       === [2, 1, ['titulo']]);

// Decisão híbrida dentro da extração de vaga: "Uniforme fornecido" não tem palavra-chave de benefício, então a
// regra põe na descrição; um modelo que aprendeu o contrário (e está pronto) muda a linha de lugar.
$pronto = new NaiveBayes();
for ($i = 0; $i < 8; $i++) {
    $pronto->aprender(Tokenizador::palavras("Uniforme e crachá fornecidos $i"), 'beneficios');
    $pronto->aprender(Tokenizador::palavras("Ensino médio completo e experiência $i"), 'requisitos');
    $pronto->aprender(Tokenizador::palavras("Atender clientes no caixa da loja $i"), 'descricao');
}
$anuncio = "ATENDENTE\nUniforme fornecido pela empresa\nVenha trabalhar na Padaria Pão Quente";
MaquinaAprendizado::ligar(true);
MaquinaAprendizado::usarModelo('vaga_linha', $pronto);
MaquinaAprendizado::usarProvas('vaga_linha', 30, 29);   // passou no período de experiência (97%)
MaquinaAprendizado::usarModelo('vaga_categoria', new NaiveBayes());
MaquinaAprendizado::usarNomes('vaga_empresa', ['Padaria Pão Quente']);
$comMaquina = ExtracaoVaga::doTexto($anuncio);
MaquinaAprendizado::ligar(false);
$soRegra = ExtracaoVaga::doTexto($anuncio);
confere('Decisão híbrida: a máquina pronta tira "Uniforme" da descrição e põe em benefícios (e registra por quê)',
    str_contains($comMaquina['beneficios'], 'Uniforme') && !str_contains($comMaquina['descricao'], 'Uniforme') && str_contains($soRegra['descricao'], 'Uniforme')
    && in_array('linha', array_column($comMaquina['maquina'], 'campo'), true), json_encode([$comMaquina['beneficios'], $comMaquina['descricao'], $comMaquina['maquina']], JSON_UNESCAPED_UNICODE));
confere('Memória de nomes: empresa confirmada antes é reconhecida quando nenhuma regra acha', $comMaquina['anunciante'] === 'Padaria Pão Quente' && $soRegra['anunciante'] === '',
    $comMaquina['anunciante'].' / '.$soRegra['anunciante']);
MaquinaAprendizado::ligar(true);
MaquinaAprendizado::usarModelo('vaga_linha', $pronto);
MaquinaAprendizado::usarProvas('vaga_linha', 30, 20);   // confiante, mas só 67% nas provas
$reprovado = MaquinaAprendizado::decidir('vaga_linha', 'Uniforme fornecido pela empresa', 'descricao');
MaquinaAprendizado::usarProvas('vaga_linha', 10, 10);   // acertou tudo, mas ainda fez poucas provas
$poucasProvas = MaquinaAprendizado::decidir('vaga_linha', 'Uniforme fornecido pela empresa', 'descricao');
confere('Período de experiência: modelo confiante mas com poucas provas ou menos de 90% de acerto não decide',
    $reprovado['origem'] === 'regra' && $poucasProvas['origem'] === 'regra' && MaquinaAprendizado::prever('vaga_linha', 'Uniforme fornecido pela empresa')['confiante'] === true);
MaquinaAprendizado::usarProvas('vaga_linha', 30, 29);
$poucas = new NaiveBayes();
$poucas->aprender(Tokenizador::palavras('Uniforme fornecido'), 'beneficios');
MaquinaAprendizado::ligar(true);
MaquinaAprendizado::usarModelo('vaga_linha', $poucas);
confere('Decisão híbrida: com poucas lições quem decide é a regra', MaquinaAprendizado::decidir('vaga_linha', 'Uniforme fornecido pela empresa', 'descricao')['origem'] === 'regra');
$numerica = new NaiveBayes();
for ($i = 0; $i < 3; $i++) { $numerica->aprender(Tokenizador::palavras("turma de verão $i"), '2024'); $numerica->aprender(Tokenizador::palavras("turma de inverno $i"), 'Outra'); }
$pNum = $numerica->prever(Tokenizador::palavras('turma de verão'));
confere('NaiveBayes: categoria só com números ("2024") não quebra a previsão nem a explicação', ($pNum['classe'] ?? '') === '2024' && is_array($numerica->explicar(Tokenizador::palavras('turma de verão'), '2024')));
confere('Memória de nomes: nome genérico ("Vaga", "Salário", "Loja") é recusado; nome de verdade entra',
    MaquinaAprendizado::nomeGenerico('Vaga') && MaquinaAprendizado::nomeGenerico('Salário') && MaquinaAprendizado::nomeGenerico('Loja') && MaquinaAprendizado::nomeGenerico('RH')
    && !MaquinaAprendizado::nomeGenerico('Padaria Pão Quente') && !MaquinaAprendizado::nomeGenerico('Grupo Dourado'));
$cvPessoal = new NaiveBayes();
for ($i = 0; $i < 12; $i++) { $cvPessoal->aprender(Tokenizador::palavras("Atendente de loja no shopping solteira brasileira $i"), 'experiencias'); $cvPessoal->aprender(Tokenizador::palavras("Ensino médio completo escola $i"), 'formacao'); }
MaquinaAprendizado::usarModelo('curriculo_linha', $cvPessoal);
MaquinaAprendizado::usarProvas('curriculo_linha', 50, 50);
$cvCampos = ExtracaoCurriculo::extrairCampos("Maria Souza
Brasileira, solteira, 25 anos
Atendente de loja no shopping central
EXPERIÊNCIA
Vendedora - Loja X");
confere('Currículo: dado pessoal do cabeçalho ("Brasileira, solteira, 25 anos") nunca vai para Experiências',
    !str_contains(Competencias::normalizar($cvCampos['experiencias']), 'solteira'), $cvCampos['experiencias']);
MaquinaAprendizado::ligar(false);
MaquinaAprendizado::limparMemoria();

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
    // A máquina de aprendizado cria as tabelas dela se o banco for de antes delas.
    $aprendido = (new AprendizadoDAO())->carregar('vaga_linha');
    (new AprendizadoDAO())->provas();
    $tabelasMl = ['aprendizado_exemplos', 'aprendizado_palavras', 'aprendizado_revisoes', 'aprendizado_provas'];
    $faltamMl = array_diff($tabelasMl, $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
    confere('tabelas da máquina de aprendizado (criadas sozinhas se faltarem)', !$faltamMl && is_array($aprendido['exemplos']), implode(', ', $faltamMl));
    $contas = $db->query("SELECT email FROM usuarios WHERE email IN ('admin@conectavagas.com','empresa@conectavagas.com','candidato@conectavagas.com')")->fetchAll(PDO::FETCH_COLUMN);
    confere('contas de teste do database/seed.sql', count($contas) === 3, count($contas).' de 3 encontradas');
    confere('vagas abertas listadas pelo VagaDAO', count((new VagaDAO())->listar(true)) > 0);
    $cruzadas = (int)$db->query("SELECT COUNT(*) FROM vagas v JOIN categorias c ON c.id=v.categoria_id WHERE c.tipo<>'vaga'")->fetchColumn()
              + (int)$db->query("SELECT COUNT(*) FROM cursos cu JOIN categorias c ON c.id=cu.categoria_id WHERE c.tipo<>'curso'")->fetchColumn();
    confere('nenhuma vaga em categoria de curso (nem curso em categoria de vaga)', $cruzadas === 0, "$cruzadas item(ns) na categoria do tipo errado");
    $semFormato = (int)$db->query("SELECT COUNT(*) FROM cursos WHERE tipo NOT IN ('".implode("','", CursoDAO::TIPOS)."')")->fetchColumn();
    confere('todo conteúdo tem formato válido (curso, e-book ou vídeo)', $semFormato === 0, "$semFormato conteúdo(s) sem formato");
    // Padrão da plataforma: tudo o que está publicado aparece com a sua imagem (e o arquivo existe).
    $temArquivo = fn(string $img) => $img !== '' && (($up = caminho_upload($img)) !== null ? is_file($up) : (preg_match('#^https?://#', $img) === 1 || is_file(PUBLIC_DIR.'/'.$img)));
    $semImagem = array_map(fn($c) => '#'.$c['id'], array_filter((new CursoDAO())->listar(true), fn($c) => !$temArquivo((string)$c['imagem'])));
    confere('todo curso e e-book publicado tem imagem (e o arquivo existe)', !$semImagem, implode(', ', $semImagem));
    $vagasSemImagem = array_map(fn($v) => '#'.$v['id'], array_filter((new VagaDAO())->listar(true), fn($v) => !$temArquivo((string)$v['imagem'])));
    confere('toda vaga aberta tem imagem (e o arquivo existe)', !$vagasSemImagem, implode(', ', $vagasSemImagem));
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
$html = '';
$status = function (string $caminho) use ($base, &$html): int {
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10]]);
    $html = (string)@file_get_contents($base.$caminho, false, $ctx);
    return (int)(preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m) ? $m[1] : 0);
};
if ($status('') === 0) {
    confere('Apache respondendo', false, 'ligue o Apache no XAMPP ou informe o endereço: php tests\smoke.php http://localhost/sua%20pasta/');
} else {
    foreach (['' => 200, 'vagas.php' => 200, 'vaga.php?id=1' => 200, 'cursos.php' => 200, 'cursos.php?tipo=ebook' => 200, 'cursos.php?tipo=video' => 200, 'curso.php?id=1' => 200,
              'cursos.php?pagina=99' => 200, 'cursos.php?tipo=xyz' => 200,
              'planos.php' => 200, 'login.php' => 200, 'cadastro.php' => 200, 'esqueci_senha.php' => 200, 'contrato.php' => 200,
              'assets/css/app.css' => 200, 'vaga.php?id=999999' => 404, 'nao-existe.php' => 404,
              'view/perfil/index.php' => 302, 'admin/index.php' => 302, 'admin/pages/aprendizado.php' => 302, 'download.php?id=1' => 302] as $caminho => $esperado) {
        $s = $status($caminho);
        confere(sprintf('%-24s → %d', $caminho === '' ? '/' : $caminho, $esperado), $s === $esperado, "recebeu $s");
    }
    // Cada página de conteúdo só mostra o seu formato (o chapéu do cartão diz "Curso", "E-book" ou "Vídeo").
    foreach (['cursos.php' => 'Curso', 'cursos.php?tipo=ebook' => 'E-book', 'cursos.php?tipo=video' => 'Vídeo'] as $caminho => $formato) {
        $status($caminho);
        preg_match_all('#class="cv-chapeu"><span>([^<·]+?)(?: ·|</span>)#u', $html, $m);
        $outros = array_diff(array_unique(array_map('trim', $m[1])), [$formato]);
        confere(sprintf('%-24s só com %s', $caminho, $formato), !$outros && str_contains($html, 'class="ativo" aria-current="page"'), 'também aparece: '.implode(', ', $outros));
    }
    // E-book aparece com a capa inteira (cartão em pé); o menu não tem item "Vídeos" (os vídeos ficam junto dos e-books)
    // e a aba de vídeos só aparece quando houver algum.
    $status('cursos.php?tipo=ebook');
    confere('e-books com a capa em pé, sem cartão sem imagem', str_contains($html, 'cv-card-ebook') && str_contains($html, 'an-cartaz') && !str_contains($html, 'cv-img-vazia'));
    preg_match('#<nav id="menu-principal".*?</nav>#s', $html, $menu);
    confere('menu sem item "Vídeos" e sem aba de vídeos vazia', ($menu[0] ?? '') !== '' && !str_contains($menu[0], 'Vídeos') && !preg_match('#Vídeos <small>\(0\)#u', $html));
    foreach (['config/config.php', 'config/cacert.pem', 'app/Core/Database.php', 'database/schema.sql', 'storage/.gitkeep', 'tests/smoke.php'] as $interno) {
        $s = $status($interno);
        confere(sprintf('%-24s bloqueado', $interno), in_array($s, [403, 404], true), "recebeu $s");
    }
}

echo PHP_EOL.($falhas ? "RESULTADO: {$falhas} falha(s)." : 'RESULTADO: tudo certo.').PHP_EOL;
exit($falhas ? 1 : 0);
