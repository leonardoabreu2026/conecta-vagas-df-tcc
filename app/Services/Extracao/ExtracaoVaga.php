<?php
declare(strict_types=1);

/**
 * Extração de vagas: transforma o texto de um anúncio (WhatsApp, Instagram, site) ou a
 * leitura de um CARTAZ (imagem, via OcrImagem) nos campos do cadastro de vaga.
 * O resultado só preenche o formulário — quem publica revisa antes de salvar.
 *
 * O que é extraído: título (cargo), empresa anunciante, local, salário (faixa), tipo de
 * contratação, nível, modelo (presencial/remoto/híbrido), descrição, requisitos, benefícios,
 * contato do anúncio (WhatsApp/telefone/e-mail), quantidade de vagas, competências e categoria.
 * Os "avisos" dizem o que a máquina não conseguiu confirmar e merece atenção na revisão.
 */
final class ExtracaoVaga {
    private const SECOES = [
        'descricao' => ['descricao','descricao da vaga','atividades','atribuicoes','responsabilidades','principais atividades','sobre a vaga',
                        'o que voce vai fazer','funcoes','sua missao','detalhes da vaga','responsabilidades resumidas','atividades do dia a dia'],
        'requisitos' => ['requisitos','requisito','pre requisitos','requisitos e qualificacoes','exigencias','perfil','perfil desejado','qualificacoes',
                         'o que buscamos','o que esperamos','buscamos alguem que seja','necessario','desejavel','diferenciais','principais atitudes'],
        'beneficios' => ['beneficios','oferecemos','remuneracao','remuneracao e beneficios','salario e beneficios','salario beneficios','o que oferecemos',
                         'vantagens','nossos beneficios','composicao do ganho'],
        'local' => ['local','local de trabalho','localizacao','endereco','cidade','locais de atuacao','local da vaga','vagas para as regioes','localidade dos candidatos'],
        'horario' => ['horario','horarios','jornada','escala','horario de trabalho','carga horaria','jornada de trabalho','escala de trabalho'],
        'contato' => ['interessados','contato','enviar curriculo','envie seu curriculo','como se candidatar','candidatura','como participar','entre em contato','fale conosco'],
    ];

    /** Categoria de vaga sugerida a partir das competências encontradas (as do título valem mais). */
    private const CATEGORIAS = [
        'Tecnologia da Informação' => ['PHP','JavaScript','React','HTML e CSS','Banco de dados SQL','Python','Java','Git','Desenvolvimento de software','Suporte técnico de TI','Análise de dados'],
        'Saúde' => ['Enfermagem','Saúde e cuidados'],
        'Engenharia' => ['Elétrica','Hidráulica','Construção civil'],
        'Alimentação' => ['Cozinha e alimentação'],
        'Serviços Gerais e Limpeza' => ['Serviços domésticos e limpeza'],
        'Logística e Transporte' => ['Motorista e CNH','Logística e entregas','Estoque e almoxarifado'],
        'Atendimento ao Público' => ['Atendimento ao cliente','Operação de caixa','Telemarketing'],
        'Vendas' => ['Vendas','Negociação'],
        'Recursos Humanos' => ['Recursos Humanos'],
        'Financeiro' => ['Finanças','Contabilidade'],
        'Marketing' => ['Marketing digital','UX e design'],
        'Educação' => ['Educação'],
        'Administração' => ['Rotinas administrativas','Administração','Excel','Pacote Office'],
    ];

    /**
     * Cargo no começo do texto normalizado (palavra inteira, com plural/feminino):
     * "auxiliar de cozinha", "vendedora", "operador a de loja", "jovem aprendiz".
     */
    private const CARGO_INICIO = '/^(?:jovem\s+)?(?:auxiliar|aux|ajudante|assistente|atendente|analista|vendedor|garcom|garcons|garconete|cozinheir[ao]|chapeiro|'
        .'churrasqueiro|pizzaiolo|confeiteir[ao]|padeir[ao]|salgadeir[ao]|copeir[ao]|cumim|barista|bartender|balconista|operador|motorista|motoboy|entregador|'
        .'frentista|promotor|consultor|corretor|representante|sdr|trainee|estagio|estagios|estagiari[ao]|aprendiz|zelador|diarista|domestica|faxineir[ao]|'
        .'camareir[ao]|recepcionista|secretari[ao]|gerente|supervisor|coordenador|encarregad[ao]|lider|tecnic[ao]|enfermeir[ao]|nutricionista|farmaceutic[ao]|'
        .'estoquista|repositor|conferente|embalador|embaixador|eletricista|pedreiro|servente|mecanico|montador|soldador|pintor|porteiro|vigilante|cuidador|'
        .'professor|monitor|designer|desenvolvedor|programador|contador|advogad[ao]|costureir[ao]|marceneir[ao]|jardineir[ao]|lavador|manobrista|telefonista|'
        .'cobrador|caixa|agente|dp|rh)(?:a|as|es|s)?\b/';

    /** Frases de chamada que não são o cargo. */
    private const GENERICOS = '/^(estamos( contratando)?|contratando|contrata(mos|-se)?|temos( uma)?( vagas?)?|vagas?( abertas?| disponive(l|is)| de emprego| para| aberta)?|'
        .'oportunidades?( de emprego| comercial)?|trabalhe conosco|faca parte( do nosso time| da nossa equipe| do time)?|junte se a nossa equipe|venha fazer parte.*|'
        .'inicio imediato|atencao|enviar curriculo|envie seu curriculo|requisitos|beneficios|salario|local|horario|contato|whatsapp|sua missao|nosso time|time|'
        .'nossa equipe|urgente|vem ai|novas vagas|processo seletivo|seu futuro.*|aqui.*|faca parte de quem.*|precisa se de)$/';

    /** Marcas e redes comuns nos anúncios do DF (reconhecidas pelo nome no texto). */
    private const MARCAS = ["McDonald's", 'Subway', 'Giraffas', 'Burger King', "Bob's", 'KFC', "Habib's", 'Outback', 'Coco Bambu', 'Madero', 'Mandaka',
        "Sam's Club", 'Atacadão', 'Assaí', 'Carrefour', 'Big Box', 'Super Adega', 'Dona de Casa', 'Tatico', 'Leroy Merlin', 'Renner', 'Riachuelo', 'C&A',
        'Americanas', 'Magazine Luiza', 'Casas Bahia', 'World Tennis', 'Centauro', 'O Boticário', 'Natura', 'Cacau Show', 'Kopenhagen', 'Drogasil',
        'Drogaria Rosário', 'Pague Menos', 'Vivo', 'Claro', 'Cascol', 'Ipiranga', 'Correios', 'Sama', 'WePink', 'Cinco Estrelas', 'Santa Therezinha',
        'Jovi', 'Zane Estágios', 'CIEE', 'Uaço', 'Indeniza', 'Santos Beneli', 'Casa do Vovô', 'Casa Gaúcha', 'Fênix Telecom', 'Don Romano', 'Concluinte'];

    /** Tipos de negócio que costumam vir antes/depois do nome da empresa ("Restaurante Prosa di Minas"). */
    private const NEGOCIOS = 'restaurante|panificadora|papelaria|farmacia|drogaria|hotel|grupo|casa de paes|churrascaria|pizzaria|cantina|supermercado|posto|padaria|lanchonete|corretora|agencia|confeitaria|hamburgueria';

    /** Pontos conhecidos → região administrativa (quando o cartaz cita só o shopping ou setor). */
    private const PONTOS = ['conjunto nacional' => ['Asa Norte', 'DF'], 'patio brasil' => ['Asa Sul', 'DF'], 'park shopping' => ['Guará', 'DF'],
        'taguatinga shopping' => ['Taguatinga', 'DF'], 'jk shopping' => ['Taguatinga', 'DF'], 'boulevard' => ['Asa Norte', 'DF'],
        'iguatemi' => ['Lago Norte', 'DF'], 'gilberto salomao' => ['Lago Sul', 'DF'], 'sia' => ['SIA', 'DF'], 'setor de industria e abastecimento' => ['SIA', 'DF'],
        'plano piloto' => ['Brasília', 'DF'], 'gama shopping' => ['Gama', 'DF'], 'nucleo bandeirante' => ['Núcleo Bandeirante', 'DF']];

    /**
     * Lê um cartaz (imagem) e extrai a vaga.
     * @return array<string,mixed> campos de doTexto() + texto_ocr, confianca e ocr (false = OCR indisponível)
     */
    public static function doImagem(string $path): array {
        if (!OcrImagem::disponivel()) return self::doTexto('') + ['texto_ocr' => '', 'confianca' => 0, 'ocr' => false];
        $ocr = OcrImagem::ler($path);
        $r = self::doTexto($ocr['texto'], $ocr);
        if ($ocr['texto'] === '') $r['avisos'][] = 'Não foi possível ler texto nesta imagem. Preencha o formulário manualmente.';
        elseif ($ocr['confianca'] < 75) $r['avisos'][] = 'A leitura da imagem teve pouca nitidez ('.$ocr['confianca'].'% de confiança): confira cada campo.';
        return $r + ['texto_ocr' => trim($ocr['texto']."\n".implode("\n", $ocr['complemento'])), 'confianca' => $ocr['confianca'], 'ocr' => true];
    }

    /**
     * @param array{destaques?:string[],complemento?:string[]} $ocr dados extras da leitura de imagem
     * @return array<string,mixed>
     */
    public static function doTexto(string $texto, array $ocr = []): array {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
        $r = ['titulo'=>'','descricao'=>'','requisitos'=>'','beneficios'=>'','tipo_vaga'=>'clt','nivel_experiencia'=>'junior','remoto'=>'presencial',
              'cidade'=>'','uf'=>'','salario_minimo'=>null,'salario_maximo'=>null,'categoria'=>'','competencias'=>[],
              'anunciante'=>'','contato'=>'','quantidade'=>null,'cargos'=>[],'avisos'=>[]];
        $destaques = array_values(array_filter(array_map([self::class, 'limparLinha'], $ocr['destaques'] ?? [])));
        $complemento = array_values(array_filter(array_map([self::class, 'limparLinha'], $ocr['complemento'] ?? [])));
        if ($texto === '' && !$destaques) return $r;

        $todas = array_values(array_filter(array_map([self::class, 'limparLinha'], explode("\n", $texto))));
        $tudo = $texto."\n".implode("\n", $complemento);
        $n = Competencias::normalizar($tudo);

        // Contato do anúncio (antes de descartar as linhas de "envie seu currículo").
        $r['contato'] = self::contato($tudo);
        $linhas = array_values(array_filter($todas, fn($l) => !self::ehLinhaContato($l)));
        [$soltas, $secoes] = self::separar($linhas);

        // Cargo(s) e título.
        $r['anunciante'] = self::anunciante($tudo, $linhas, $destaques);
        $r['cargos'] = self::cargos($linhas);
        $r['titulo'] = self::titulo($linhas, $destaques, $complemento, $r['cargos'], $r['anunciante']);
        if (count($r['cargos']) >= 6) $r['avisos'][] = 'O cartaz lista '.count($r['cargos']).' cargos diferentes: confira se é uma vaga só ou uma lista de vagas de vários anunciantes.';
        [$r['salario_minimo'], $r['salario_maximo']] = self::salario($tudo);
        $r['quantidade'] = self::quantidade($n);

        $r['tipo_vaga'] = match (true) {
            (bool)preg_match('/\b(estagio|estagiario|estagiaria|estagiarios|bolsa auxilio)\b/', $n) => 'estagio',
            (bool)preg_match('/\b(pj|pessoa juridica|prestador de servico|mei)\b/', $n) => 'pj',
            (bool)preg_match('/\b(temporario|temporaria|freelancer|freela|por contrato|dias de contrato|contrato de \d+ dias|acao temporaria)\b/', $n) => 'temporario',
            default => 'clt',
        };
        $r['nivel_experiencia'] = match (true) {
            (bool)preg_match('/\b(senior|sr)\b/', $n) => 'senior',
            (bool)preg_match('/\bpleno\b/', $n) => 'pleno',
            (bool)preg_match('/\b(estagio|estagiario|estagiaria|estagiarios|jovem aprendiz|aprendiz)\b/', $n) => 'estagiario',
            default => 'junior',
        };
        $r['remoto'] = match (true) {
            (bool)preg_match('/\b(hibrido|hibrida)\b/', $n) => 'hibrido',
            (bool)preg_match('/\b(remoto|remota|home office|100 online|teletrabalho)\b/', $n) => 'remoto',
            default => 'presencial',
        };
        [$r['cidade'], $r['uf']] = self::local($tudo."\n".implode("\n", $destaques), $secoes['local'] ?? []);
        if ($r['cidade'] === '' && $r['remoto'] !== 'remoto') { $r['cidade'] = 'Brasília'; $r['uf'] = 'DF'; }

        // Linhas fora de seção: benefícios têm R$/VT/VR; requisitos têm "experiência", "CNH", "curso"...
        $desc = $secoes['descricao'] ?? []; $req = $secoes['requisitos'] ?? []; $ben = $secoes['beneficios'] ?? [];
        $ignorar = array_map([Competencias::class, 'normalizar'], array_filter([$r['titulo'], $r['anunciante'], ...$r['cargos']]));
        foreach ($soltas as $l) {
            $ln = Competencias::normalizar($l);
            $lt = Competencias::normalizar(self::limparTitulo($l));
            if ($lt === '' || in_array($ln, $ignorar, true) || in_array($lt, $ignorar, true) || preg_match(self::GENERICOS, $lt) || !self::temPalavras($l)) continue;
            if (self::ehLocalSolto($ln)) continue; // já vai para o campo cidade
            if (preg_match('/r \d|\b(vt|vr|va|vale|plano de saude|odonto|odontologico|cesta basica|comissao|comissoes|bonus|premiacao|premiacoes|day off|ajuda de custo|beneficios?|refeicao|alimentacao no local|seguro de vida|gympass|totalpass|total pass|plano de carreira|produtividade|gorjeta)\b/', $ln)) $ben[] = $l;
            elseif (preg_match('/\b(experiencia|cnh|curso|cursando|ensino|conhecimento|disponibilidade|residir|morar|morador|registro|coren|nr ?10|ter |possuir|saber|dominio|formacao|maior de|anos completos|comunicativ|proativ|organizad)\b/', $ln)) $req[] = $l;
            else $desc[] = $l;
        }
        foreach (['horario' => 'Horário', 'local' => 'Local'] as $extra => $rot) if (!empty($secoes[$extra])) $desc[] = $rot.': '.implode(' ', $secoes[$extra]);
        if (!isset($secoes['horario']) && preg_match('/\b(\d{1,2})\s*x\s*(\d{1,2})\b/', $n, $m) && in_array($m[1].'x'.$m[2], ['6x1','5x2','12x36','4x2','5x1','6x2'], true)) $desc[] = 'Escala '.$m[1].'x'.$m[2];
        if (count($r['cargos']) >= 2) $desc[] = 'Cargos: '.implode(', ', $r['cargos']).'.';
        if ($r['quantidade']) $desc[] = 'Quantidade de vagas: '.$r['quantidade'];
        $r['descricao'] = self::juntar($desc);
        $r['requisitos'] = self::juntar($req);
        $r['beneficios'] = self::juntar($ben);

        $r['competencias'] = Competencias::daVaga($r);
        $r['categoria'] = self::categoria($r['competencias'], Competencias::extrair($r['titulo']));

        if ($r['titulo'] === '') $r['avisos'][] = 'Não encontramos o cargo: preencha o título.';
        if ($r['salario_minimo'] === null) $r['avisos'][] = 'Salário não informado no anúncio (ficará "A combinar").';
        if ($r['anunciante'] === '' && ($ocr['destaques'] ?? null) !== null) $r['avisos'][] = 'Empresa anunciante não identificada: confira no cartaz.';
        return $r;
    }

    /** Categoria pelas competências; as competências do título contam em dobro. */
    public static function categoria(array $competencias, array $doTitulo = []): string {
        $melhor = ''; $max = 0;
        foreach (self::CATEGORIAS as $cat => $lista) {
            $q = count(array_intersect($competencias, $lista)) + 2 * count(array_intersect($doTitulo, $lista));
            if ($q > $max) { $max = $q; $melhor = $cat; }
        }
        return $melhor;
    }

    // ------------------------------------------------------------------ linhas e seções

    /** Tira emojis, marcadores e restos de ícones que o OCR lê como letras soltas nas pontas. */
    private static function limparLinha(string $l): string {
        $l = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\x{2B50}\x{2705}\x{274C}\x{E000}-\x{F8FF}]/u', '', $l) ?? $l;
        $l = str_replace(['“', '”', '«', '»', '‘', '’'], ['', '', '', '', '', "'"], $l);
        // Pedaços de ícone no começo: "(O) ", "Q ", "e ", "4) ", "] ", "O) " ...
        for ($i = 0; $i < 3; $i++) {
            $l = preg_replace('/^\s*(?:[\(\[]?[\p{L}\d]{1,2}[\)\]]|[\p{L}](?=\s+\p{Lu})|[^\p{L}\d\s(+$]+)\s*/u', '', $l, 1, $c) ?? $l;
            if (!$c) break;
        }
        $l = trim_u($l, " \t*_•·-–—>|=:;,~\"'`´^");
        $l = preg_replace('/\s+[|=\]\[~]+(\s+|$)/u', ' ', $l) ?? $l;
        return trim(preg_replace('/\s{2,}/u', ' ', $l) ?? $l);
    }

    private static function temPalavras(string $l): bool {
        return preg_match_all('/[\p{L}]{3,}/u', $l) >= 1 && preg_match_all('/\p{L}/u', $l) >= 4;
    }

    private static function ehLinhaContato(string $l): bool {
        $n = Competencias::normalizar($l);
        if (preg_match('/@|\b\d{4,5}[\s.-]?\d{4}\b/', $l) && !preg_match('/r\$\s*\d/i', $l)) return true;
        return (bool)preg_match('/\b(whatsapp|whats|zap|wa)\b|\b(envie|enviar|mande|mandar|entregar)\b.*\b(curriculo|cv)\b|\binteressad[oa]s?\b|\b(assunto|informe|e mail|email)\b|\bfale (conosco|com)\b|\bentre em contato\b|\blink na bio\b|\bacesse\b/', $n);
    }

    private static function ehLocalSolto(string $ln): bool {
        return (bool)preg_match('/^(local|regioes|localizacao)\b/', $ln) || (bool)preg_match('/^(asa (sul|norte)|aguas claras|taguatinga( sul| norte)?|ceilandia( norte| sul)?|guara( i+| 1| 2)?|samambaia|brasilia|gama|sia|vicente pires|cruzeiro( novo| velho)?|lago (sul|norte)|sobradinho|planaltina|recanto das emas|riacho fundo|santa maria|sao sebastiao|paranoa|itapoa|nucleo bandeirante)( df)?$/', $ln);
    }

    private static function separar(array $linhas): array {
        $soltas = []; $sec = []; $atual = null;
        foreach ($linhas as $l) {
            $partes = preg_split('/\s*:\s*/u', $l, 2) ?: [$l];
            $cab = Competencias::normalizar($partes[0]);
            $achou = null;
            if (mb_strlen($partes[0]) <= 45) {
                foreach (self::SECOES as $k => $titulos) if (in_array($cab, $titulos, true)) { $achou = $k; break; }
            }
            if ($achou) { $atual = $achou; if (trim($partes[1] ?? '') !== '') $sec[$atual][] = trim($partes[1]); continue; }
            if ($atual === null) $soltas[] = $l; else $sec[$atual][] = $l;
        }
        return [$soltas, $sec];
    }

    private static function juntar(array $linhas): string {
        $out = []; $vistos = [];
        foreach ($linhas as $l) {
            $l = trim($l);
            $k = Competencias::normalizar($l);
            if ($k === '' || isset($vistos[$k]) || !self::temPalavras($l)) continue;
            $vistos[$k] = true;
            $out[] = $l;
        }
        return implode("\n", $out);
    }

    // ------------------------------------------------------------------ título e cargos

    private static function ehCargo(string $t): bool {
        $n = Competencias::normalizar($t);
        return $n !== '' && (bool)preg_match(self::CARGO_INICIO, $n) && !preg_match(self::GENERICOS, $n);
    }

    /** Tira o lixo antes do cargo ("Su? AUXILIAR…", "VAGAS DISPONÍVEIS: 62 Copeira"): até 3 palavras curtas/sujas. */
    private static function cortarAteCargo(string $t): string {
        $pal = preg_split('/\s+/u', trim($t)) ?: [];
        for ($i = 1; $i <= 3 && $i < count($pal); $i++) {
            $sujo = fn($w) => mb_strlen($w) <= 3 || preg_match('/[^\p{L}]/u', $w) || preg_match('/^(vagas?|disponiveis|abertas?|para|contrata)$/', Competencias::normalizar($w));
            if (!array_filter(array_slice($pal, 0, $i), fn($w) => !$sujo($w)) && preg_match(self::CARGO_INICIO, Competencias::normalizar(implode(' ', array_slice($pal, $i))))) {
                return implode(' ', array_slice($pal, $i));
            }
        }
        return $t;
    }

    /** Linhas curtas que são só um cargo (listas "VAGAS: > Garçom > Operador de caixa"). */
    private static function cargos(array $linhas): array {
        $out = [];
        foreach ($linhas as $l) {
            $t = self::limparTitulo($l);
            $n = Competencias::normalizar($t);
            if ($n === '' || mb_strlen($t) > 42 || str_word_count($n) > 5 || preg_match('/[,;]$/', $t) || preg_match('/\b(de|da|do|em|e|para)$/', $n)
                || preg_match('/\d{3}|r \d|\b(experiencia|conhecimento|curso|ensino|com|para|nas|sua|seu|voce|nosso|nossa)\b/', $n) || !self::ehCargo($t)) continue;
            $out[$n] = self::maiuscula(preg_replace('/\s*\(\d+\)$/', '', $t) ?? $t);
        }
        // "Estágios" = "Estágio"; "Vendedor(a)" dentro de "Vendedor(a) Conjunto Nacional": fica o mais curto.
        $chaves = array_keys($out);
        foreach ($chaves as $a) foreach ($chaves as $b) {
            if ($a === $b || !isset($out[$a], $out[$b])) continue;
            if (rtrim($a, 's') === rtrim($b, 's') || str_starts_with($b, $a.' ')) unset($out[$b]);
        }
        return array_values($out);
    }

    private static function titulo(array $linhas, array $destaques, array $complemento, array $cargos, string $anunciante = ''): string {
        // 1) Rótulo explícito: "Vaga: X", "Cargo: X" (ou "CARGO:" com o cargo na linha de baixo).
        foreach ($linhas as $i => $l) {
            if (preg_match('/^(?:vaga|cargo|fun[cç][aã]o|oportunidade|posi[cç][aã]o)\s*(?:de|para)?\s*:\s*(.{3,80})$/iu', $l, $m)) return self::maiuscula(self::limparTitulo($m[1]));
            if (preg_match('/^(?:cargo|vaga|fun[cç][aã]o)\s*:?$/iu', $l) && isset($linhas[$i + 1]) && self::ehCargo($linhas[$i + 1])) return self::maiuscula(self::limparTitulo($linhas[$i + 1]));
        }
        // 2) Lista de cargos no cartaz: até 3 no título; lista grande vira "Vagas abertas — Empresa".
        if (count($cargos) > 5) return $anunciante !== '' ? 'Vagas abertas — '.$anunciante : implode(' / ', array_slice($cargos, 0, 3)).' e outras';
        if (count($cargos) >= 2) return implode(' / ', array_slice($cargos, 0, 3));

        // 3) O maior texto do cartaz que tenha um cargo (juntando "AUXILIAR DE" + "COZINHA").
        $candidatos = [];
        foreach ($destaques as $k => $d) $candidatos[] = [$d, 30 - $k * 2];
        foreach (array_slice($linhas, 0, 18) as $k => $l) $candidatos[] = [$l, 18 - $k];
        foreach (array_slice($complemento, 0, 25) as $k => $l) $candidatos[] = [$l, 8 - $k * 0.3];
        $melhor = ''; $pontos = -INF;
        foreach ($candidatos as [$c, $p]) {
            $t = self::completarTitulo(self::limparTitulo($c), $linhas, $destaques);
            if ($t === '' || mb_strlen($t) > 60 || !self::ehCargo($t) || preg_match('/r\$|\d{4}|@|whats|http/iu', $t)) continue;
            $palavras = str_word_count(Competencias::normalizar($t));
            if ($palavras > 7) continue;
            $p += $palavras >= 2 && $palavras <= 5 ? 4 : 0;
            if ($p > $pontos) { $pontos = $p; $melhor = $t; }
        }
        if ($melhor !== '') return self::maiuscula($melhor);
        if (count($cargos) === 1) return $cargos[0];
        // 4) Cartaz sem cargo legível: "Vagas abertas — Empresa" (ou vazio, para a revisão preencher).
        if ($anunciante !== '') return 'Vagas abertas — '.$anunciante;
        if ($destaques) return '';
        // 5) Anúncio de texto: a primeira linha com cara de título.
        foreach (array_slice($linhas, 0, 4) as $l) {
            $t = self::limparTitulo($l);
            if (preg_match_all('/\p{L}/u', $t) >= 5 && mb_strlen($t) <= 80 && !preg_match(self::GENERICOS, Competencias::normalizar($t)) && !preg_match('/r\$|\d{4}|@|whats/iu', $t)) return self::maiuscula($t);
        }
        return '';
    }

    private static function maiuscula(string $t): string { return mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1); }

    /** "Auxiliar de" + "Cozinha", "Consultor" + "Interno": completa com a linha vizinha (depois; nos destaques, também antes). */
    private static function completarTitulo(string $t, array $linhas, array $destaques): string {
        $n = Competencias::normalizar($t);
        $incompleto = preg_match('/\b(de|da|do|em|e)$/', $n) || (str_word_count($n) === 1 && preg_match('/^(consultor|consultora|auxiliar|assistente|promotor|operador|operadora|analista|tecnico|atendente)$/', $n));
        if (!$incompleto) return $t;
        $serve = function (string $l): ?string {
            $prox = self::limparTitulo($l);
            $pn = Competencias::normalizar($prox);
            // 1 a 3 palavras, sem números, sem frase em minúsculas, sem ser local ou chamada.
            if ($pn === '' || str_word_count($pn) > 3 || preg_match('/\d|\//', $prox) || preg_match(self::GENERICOS, $pn) || self::ehLocalSolto($pn)) return null;
            if (preg_match('/(^|\s)(?!(?:de|da|do|e|em)\b)\p{Ll}/u', $prox)) return null;
            return $prox;
        };
        foreach ([[$linhas, [1]], [$destaques, [1, -1]]] as [$fonte, $passos]) {
            foreach ($fonte as $i => $l) {
                if (Competencias::normalizar(self::limparTitulo($l)) !== $n) continue;
                foreach ($passos as $d) if (isset($fonte[$i + $d]) && ($prox = $serve($fonte[$i + $d])) !== null) return self::limparTitulo($t.' '.$prox);
            }
        }
        return preg_match('/\b(de|da|do|em|e)$/', $n) ? '' : $t;
    }


    private static function limparTitulo(string $t): string {
        $t = self::limparLinha($t);
        $t = preg_replace('/^(?:vaga(?:s)?(?: de emprego)?(?: abertas?| dispon[ií]veis)?(?: para| de)?|contrata(?:-se|mos)?|estamos contratando|oportunidade(?: de emprego)?(?: para)?|precisa-se de|urgente|temos vagas?(?: para)?|contratamos)\s*[:!\-–—]?\s*/iu', '', trim($t)) ?? $t;
        $t = self::cortarAteCargo($t);
        $t = preg_replace('/\s*[-–|]\s*(?:'.implode('|', ['Brasília','DF','Taguatinga','Ceilândia','Guará','Águas Claras','Samambaia','Asa Norte','Asa Sul','Gama']).')\b.*$/iu', '', $t) ?? $t;
        $t = preg_replace('/^\d\s+(?=\p{Lu})/u', '', $t) ?? $t;                 // número de ícone antes do cargo
        $t = preg_replace('/\s*\(\d+\)$|\s*\([\p{L}\d]{0,2}$/u', '', $t) ?? $t;  // "(3)" ou "(a" sem fechar, no fim
        if (substr_count($t, '(') < substr_count($t, ')')) $t = preg_replace('/\)\s*$/u', '', $t) ?? $t; // ")" sem par
        $t = trim_u($t, " !:-–—.,;|/");
        return self::caixa($t);
    }

    /** Palavras em CAIXA ALTA viram "Auxiliar de Cozinha" (preposições minúsculas, siglas mantidas). */
    private static function caixa(string $t): string {
        if ($t === '') return $t;
        $siglas = ['RH', 'DP', 'SDR', 'PAP', 'TI', 'CLT', 'PJ', 'SIA', 'DF', 'GO', 'SESC', 'SAC', 'CNH', 'EAD', 'UX', 'UI', 'MEI', 'II', 'III'];
        $out = [];
        foreach (preg_split('/(\s+|\/|-)/u', $t, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $i => $p) {
            if (trim($p) === '' || in_array($p, ['/', '-'], true)) { $out[] = $p; continue; }
            if ($i > 0 && in_array(mb_strtolower($p), ['de', 'da', 'do', 'das', 'dos', 'di', 'e', 'em'], true)) { $out[] = mb_strtolower($p); continue; }
            if ($p !== mb_strtoupper($p) || !preg_match('/\p{L}{2}/u', $p)) { $out[] = $p; continue; } // já em caixa mista: mantém
            if (in_array($p, $siglas, true)) { $out[] = $p; continue; }
            $p = mb_strtolower($p);
            if ($i > 0 && in_array($p, ['de', 'da', 'do', 'das', 'dos', 'di', 'e', 'em', 'para', 'com', 'a', 'o', 'na', 'no', 'ao'], true)) { $out[] = $p; continue; }
            $out[] = mb_strtoupper(mb_substr($p, 0, 1)).mb_substr($p, 1);
        }
        $t = implode('', $out);
        return preg_replace_callback('/\((\p{Lu}{1,2})\)/u', fn($m) => '('.mb_strtolower($m[1]).')', $t) ?? $t; // Operador(A) → Operador(a)
    }

    // ------------------------------------------------------------------ empresa, contato, salário, local

    private static function anunciante(string $tudo, array $linhas, array $destaques): string {
        $n = ' '.Competencias::normalizar($tudo).' ';
        // 1) Marcas conhecidas.
        foreach (self::MARCAS as $m) if (str_contains($n, ' '.Competencias::normalizar($m).' ')) return $m;
        // 2) Frases: "O Giraffas está contratando", "Somos a Cinco Estrelas", "Casa do Vovô - Vicente Pires/DF está com vaga".
        $pad = [
            '/\b(?:[OA]s?\s+)([\p{Lu}][\p{L}\'’&.]*(?:\s+(?:d[aeo]s?\s+)?[\p{Lu}][\p{L}\'’&.]*){0,3})\s+(?:est[aá]|estão)\s+(?:contratando|com vagas?|aumentando|com oportunidades)/u',
            '/\bSomos\s+(?:a|o|os|as)\s+([\p{Lu}][\p{L}\'’&.]*(?:\s+(?:d[aeo]s?\s+)?[\p{Lu}][\p{L}\'’&.]*){0,3})/u',
            '/^([\p{Lu}][\p{L}\'’&.]*(?:\s+(?:d[aeo]s?\s+)?[\p{Lu}][\p{L}\'’&.]*){0,3})\s+-\s+[\p{L}\s]+\/(?:DF|GO)\s+est[aá]/mu',
        ];
        foreach ($pad as $rx) if (preg_match($rx, $tudo, $m)) { $nome = self::nomeEmpresa($m[1]); if ($nome !== '') return $nome; }
        // 3) Linha com o tipo do negócio: "Restaurante Prosa di Minas em", "Kairós Restaurante", "Grupo Viver Bem Seguros".
        foreach (array_merge($linhas, $destaques) as $l) {
            $ln = Competencias::normalizar($l);
            if (mb_strlen($l) > 60 || !preg_match('/\b('.self::NEGOCIOS.')\b/', $ln) || self::ehCargo($l) || preg_match('/^(de|da|do|em|e|ao|a|o|no|na)\b/', $ln)
                || preg_match('/\b(experiencia|vaga|vagas|fazer parte|equipe|time|refeicao|alimentacao|vale|contratando|aumentando|desconto|convenio|plano|saude|parceria|seguro)\b/', $ln)) continue;
            $nome = self::nomeEmpresa(preg_replace('/\s+(?:em|no|na|de)$/iu', '', self::limparLinha($l)) ?? $l);
            if ($nome !== '' && str_word_count(Competencias::normalizar($nome)) >= 2) return $nome;
        }
        // 4) Domínio do e-mail que aparece escrito no cartaz (rh@santosbeneli.com.br + "SANTOS BENELI").
        if (preg_match_all('/@([a-z0-9-]+)\.(?:com|net|org|adv)/i', self::corrigirEmails($tudo), $ms)) {
            foreach ($ms[1] as $dom) {
                if (preg_match('/^(gmail|hotmail|outlook|yahoo|live|icloud|bol|uol|terra)$/i', $dom)) continue;
                foreach (array_merge($destaques, $linhas) as $l) {
                    $junto = str_replace(' ', '', Competencias::normalizar($l));
                    if (strlen($junto) >= 4 && str_starts_with(strtolower($dom), $junto)) return self::nomeEmpresa($l);
                }
            }
        }
        return '';
    }

    private static function nomeEmpresa(string $s): string {
        $s = trim_u(preg_replace('/\s+/u', ' ', self::limparLinha($s)) ?? $s, " .,-–");
        // Corta onde a frase continua: "Cantón Restaurante está…" → "Cantón Restaurante".
        $s = preg_replace('/\s+(?!(?:de|da|do|das|dos|di|e)\b)\p{Ll}.*$/u', '', $s) ?? $s;
        if ($s === '' || mb_strlen($s) > 50 || str_contains($s, ':') || self::ehCargo($s) || preg_match(self::GENERICOS, Competencias::normalizar($s))) return '';
        return self::caixa($s);
    }

    /** OCR costuma trocar o @ por G, Q, O ou "(o": "vagasQgmail.com" → "vagas@gmail.com". */
    private static function corrigirEmails(string $t): string {
        $t = preg_replace('/\b([a-z0-9._-]{3,})(?:\(o|[GQO©@])((?:gmail|hotmail|outlook|yahoo|live|icloud)\.com(?:\.br)?)\b/i', '$1@$2', $t) ?? $t;
        return preg_replace('/\b([a-z0-9._-]{2,})\(o([a-z0-9-]+\.(?:com|net|org)(?:\.br)?)\b/i', '$1@$2', $t) ?? $t;
    }

    /** "WhatsApp (61) 98549-9498 · vagas@empresa.com". */
    private static function contato(string $t): string {
        $t = self::corrigirEmails($t);
        $partes = []; $vistos = [];
        if (preg_match_all('/(?<![\d,.])(?:\+?\s*55[\s-]*)?\(?\s*(\d{2})\s*\)?[\s.-]*(9?)[\s.-]*(\d{4})[\s.-]*(\d{4})(?![\d,])/u', $t, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $ddd = (int)$m[1];
                if ($ddd < 11 || $ddd > 99 || $ddd % 10 === 0) continue;
                $num = $m[2].$m[3].$m[4];
                $fmt = '('.$m[1].') '.(strlen($num) === 9 ? substr($num, 0, 5).'-'.substr($num, 5) : substr($num, 0, 4).'-'.substr($num, 4));
                if (isset($vistos[$fmt])) continue;
                $vistos[$fmt] = true;
                $partes[] = (strlen($num) === 9 && preg_match('/whats|wa\b|zap/i', $t) ? 'WhatsApp ' : 'Telefone ').$fmt;
            }
        }
        if (preg_match_all('/\b[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}\b/i', $t, $ms)) {
            foreach ($ms[0] as $e) { $e = strtolower($e); if (!isset($vistos[$e])) { $vistos[$e] = true; $partes[] = $e; } }
        }
        if (preg_match('/\b(?:assunto|informe|t[ií]tulo)\s*:\s*([^\n|]{3,60})/iu', $t, $m)) $partes[] = 'Assunto: '.self::caixa(trim($m[1], ' .'));
        return mb_substr(implode(' · ', array_slice($partes, 0, 4)), 0, 255);
    }

    /** @return array{0:?float,1:?float} */
    private static function salario(string $t): array {
        $t = preg_replace('/\bR\s?S\s?(?=\d)/u', 'R$ ', $t) ?? $t;            // OCR: "RS 1.750,00"
        $candidatos = [];
        if (preg_match_all('/(?:R\$|sal[aá]rio(?:\s+de)?:?|bolsa(?:-aux[ií]lio)?:?|remunera[cç][aã]o:?)\s*(\d{1,3}(?:\.\d{3})+(?:,\d{2})?|\d{3,6}(?:,\d{2})?)/iu', $t, $ms, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $antes = Competencias::normalizar(substr($t, max(0, $m[0][1] - 45), min(45, $m[0][1])));
                $depois = Competencias::normalizar(substr($t, $m[1][1] + strlen($m[1][0]), 25));
                // Valores que não são o salário: benefícios, adicionais, metas e "pode chegar a".
                // (a palavra-chave vale se não houver outro valor "R$ ..." entre ela e este)
                if (preg_match_all('/\b(vr|vt|va|vale|refeicao|alimentacao|comissao|comissoes|bonus|ajuda|diaria|por dia|hora|periculosidade|adicional|auxilio|reembolso|premiacao|premio|indicacao|chegar a|chegar ate|ganhar|mobilidade|transporte|plano|passagem|desconto|cerca de)\b/', $antes, $km, PREG_OFFSET_CAPTURE)
                    && !preg_match('/\br \d/', substr($antes, (int)end($km[0])[1]))) continue;
                if (preg_match('/^\s*(por dia|dia|ao dia|por km|mes|mensal de vale|de vale|ou mais)\b/', $depois)) continue;
                $v = decimal_ou_null($m[1][0]);
                if ($v === null || $v < 500 || $v > 30000) continue;
                $candidatos[] = ['v' => $v, 'forte' => (bool)preg_match('/\b(salario|remuneracao|bolsa|fixo|base|ganhos?|mensal)\b/', $antes.' '.Competencias::normalizar($m[0][0]))];
            }
        }
        if (!$candidatos) return [null, null];
        $fortes = array_filter($candidatos, fn($c) => $c['forte']);
        $valores = array_column($fortes ?: $candidatos, 'v');
        return [min($valores), max($valores)];
    }

    private static function quantidade(string $n): ?int {
        if (preg_match('/\b(\d{1,3})\s+vagas?\b/', $n, $m) && (int)$m[1] > 0 && (int)$m[1] <= 500) return (int)$m[1];
        if (preg_match('/\bquantidade de vagas\s*(\d{1,3})\b/', $n, $m)) return (int)$m[1];
        return null;
    }

    /** @return array{0:string,1:string} */
    private static function local(string $tudo, array $linhasLocal): array {
        foreach ([implode("\n", $linhasLocal), $tudo] as $fonte) {
            if (trim($fonte) === '') continue;
            [$c, $uf] = ExtracaoCurriculo::local($fonte);
            // Descarta o que não é cidade (o padrão "Nome - UF" às vezes pega pedaços de outras linhas).
            if ($c !== '' && (str_contains($c, "\n") || self::ehCargo($c) || str_word_count(Competencias::normalizar($c)) > 4)) $c = '';
            $c = self::caixa($c);
            if ($c !== '' && $c !== 'Brasília') return [$c, $uf];
            $n = ' '.Competencias::normalizar($fonte).' ';
            foreach (self::PONTOS as $k => $v) if (str_contains($n, ' '.$k.' ')) return $v;
            if ($c !== '') return [$c, $uf];
        }
        return ['', ''];
    }
}
