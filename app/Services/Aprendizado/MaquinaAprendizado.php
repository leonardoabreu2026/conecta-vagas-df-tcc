<?php
declare(strict_types=1);

/**
 * MÁQUINA DE APRENDIZADO — a porta de entrada do aprendizado de máquina no sistema.
 *
 * As máquinas de extração (vaga, curso e currículo) funcionam com REGRAS escritas à mão
 * (listas de palavras-chave). Esta classe junta a elas um MODELO que aprende sozinho com as
 * revisões das pessoas. O ciclo completo:
 *
 *   1. EXTRAIR   a máquina lê o anúncio e preenche o formulário (regras + o que já aprendeu);
 *   2. REVISAR   a pessoa confere, corrige e salva — como sempre fez;
 *   3. APRENDER  aprenderDaRevisao() vê em qual campo a pessoa deixou cada linha (CorrecaoHumana);
 *                cada uma vira uma lição gravada no banco (AprendizadoDAO), tenha sido corrigida ou não;
 *   4. MELHORAR  na próxima leitura o modelo (NaiveBayes) já usa essas lições.
 *
 * Vocabulário: "lição" é o exemplo rotulado (um texto + a resposta certa). No código e no banco
 * o nome técnico é "exemplo", e a resposta certa é a "classe" (o campo, a seção ou a área).
 *
 * É um modelo HÍBRIDO, com a regra como rede de segurança (decidir()):
 *  - enquanto o modelo tem poucas lições, quem decide é a regra;
 *  - quando ele já viu lições suficientes E está confiante, ele decide;
 *  - na dúvida, vale a regra.
 * O modelo só passa por cima da regra quando tem histórico e confiança. Ainda assim pode errar;
 * por isso cada decisão dele aparece no relatório da extração e o painel acompanha a taxa de acerto.
 *
 * Modelos que existem hoje (MODELOS) e memórias de nomes (MEMORIAS) estão descritos abaixo.
 * E-mail, telefone, salário e CNH continuam só com regra: são dados de formato fixo, que uma
 * expressão regular reconhece melhor do que um modelo que conta palavras.
 *
 * Tolerância a falhas: na extração e no salvamento, qualquer erro aqui (banco desligado, tabela
 * faltando) é registrado no log e ignorado — tudo funciona do mesmo jeito, só sem aprender.
 */
final class MaquinaAprendizado {
    /**
     * Modelos de classificação: nome => [título, para que serve, classes com rótulo].
     * Classes vazias ([]) = as classes são as categorias cadastradas no sistema.
     */
    public const MODELOS = [
        'vaga_linha' => ['Linhas do anúncio de vaga', 'Decide em qual campo entra cada linha solta do anúncio (sem título de seção).',
                         ['descricao' => 'Descrição', 'requisitos' => 'Requisitos', 'beneficios' => 'Benefícios']],
        'vaga_categoria' => ['Área da vaga', 'Sugere a categoria da vaga pelo título, descrição e requisitos.', []],
        'curso_categoria' => ['Área do curso', 'Sugere a categoria do curso ou e-book pelo título e descrição.', []],
        'curriculo_linha' => ['Linhas do currículo', 'Decide a seção das linhas que o currículo traz sem título de seção.',
                              ['experiencias' => 'Experiências', 'formacao' => 'Formação', 'cursos' => 'Cursos', 'habilidades' => 'Habilidades',
                               'idiomas' => 'Idiomas', 'objetivo' => 'Objetivo', 'resumo' => 'Resumo']],
    ];

    /** Memórias de nomes: nomes próprios confirmados pelas pessoas, reconhecidos da próxima vez que aparecerem. */
    public const MEMORIAS = [
        'vaga_empresa' => ['Empresas anunciantes', 'Nenhuma empresa confirmada ainda. O nome aparece aqui quando alguém salvar uma vaga extraída com a empresa escrita no anúncio.'],
        'curso_instituicao' => ['Instituições de ensino', 'Nenhuma instituição confirmada ainda. O nome aparece aqui quando o administrador salvar um curso extraído com a instituição escrita no texto.'],
    ];

    /**
     * O que comparar em cada tipo de revisão.
     *  campos    : campos simples conferidos (acerto da máquina)
     *  linhas    : [modelo, campos de texto onde as linhas podem ir]
     *  categoria : [modelo, campos cujo texto descreve o item]
     *  memoria   : [memória, campo com o nome]
     *  apelidos  : campo => outros nomes que ele tem na extração ou no formulário
     *              (no currículo a seção "resumo" é o campo bio; "cursos" é cursos_complementares no perfil)
     */
    private const REVISOES = [
        'vaga' => [
            'campos' => ['titulo', 'anunciante', 'cidade', 'uf', 'tipo_vaga', 'nivel_experiencia', 'remoto', 'salario_minimo', 'salario_maximo', 'contato', 'categoria'],
            'linhas' => ['vaga_linha', ['descricao', 'requisitos', 'beneficios']],
            'categoria' => ['vaga_categoria', ['titulo', 'descricao', 'requisitos']],
            'memoria' => ['vaga_empresa', 'anunciante'],
        ],
        'curso' => [
            'campos' => ['titulo', 'instituicao', 'tipo', 'modalidade', 'nivel', 'duracao', 'gratuito', 'categoria'],
            'categoria' => ['curso_categoria', ['titulo', 'descricao']],
            'memoria' => ['curso_instituicao', 'instituicao'],
        ],
        'curriculo' => [
            'campos' => ['titulo_profissional', 'cidade', 'uf', 'nivel_experiencia', 'cnh', 'disponibilidade'],
            'linhas' => ['curriculo_linha', ['experiencias', 'formacao', 'cursos', 'habilidades', 'idiomas', 'objetivo', 'resumo']],
            'apelidos' => ['resumo' => ['bio'], 'cursos' => ['cursos_complementares']],
        ],
    ];

    /** Mínimo de lições no modelo inteiro para ele começar a decidir. */
    public const MIN_LICOES = 20;
    /** Mínimo de lições na classe que o modelo quer escolher. */
    public const MIN_LICOES_CLASSE = 3;
    /** Confiança mínima (0 a 1) para o modelo decidir; abaixo disso, vale a regra. */
    public const CONFIANCA_MINIMA = 0.80;

    /*
     * PERÍODO DE EXPERIÊNCIA (avaliação prequencial, "testar antes de aprender").
     * A confiança do Naive Bayes costuma sair exagerada (quase tudo perto de 100%), então ela sozinha
     * não prova nada. Por isso, antes de aprender cada lição NOVA, o modelo tenta adivinhar a resposta
     * com o que já sabia; se estava confiante, isso conta como uma prova, e o acerto fica anotado.
     * O modelo só ganha o direito de passar por cima da regra quando já fez MIN_PROVAS provas e acertou
     * pelo menos PRECISAO_MINIMA delas. Com os dados de demonstração, isso libera as linhas do anúncio
     * de vaga (~97% nas provas) e segura a área da vaga (~65%), que ficaria pior do que a regra.
     */
    /*
     * CALIBRAÇÃO POR TEMPERATURA (ver Calibracao): as porcentagens do modelo são "esfriadas" por uma
     * temperatura T, escolhida para minimizar o erro das previsões antigas. Assim a confiança de 80%
     * passa a querer dizer algo perto de 80% de acerto. A temperatura é recalculada sozinha sempre que o
     * modelo ganha RECALIBRAR_A_CADA lições novas.
     */
    /** De quantas em quantas lições novas a temperatura é recalculada. */
    public const RECALIBRAR_A_CADA = 10;

    /** Provas necessárias antes de o modelo poder ser liberado. */
    public const MIN_PROVAS = 20;
    /** Acerto mínimo nas provas (0 a 1) para o modelo ser liberado. */
    public const PRECISAO_MINIMA = 0.90;

    /** Tamanho máximo do texto de uma lição (é o que cabe na coluna aprendizado_exemplos.texto). */
    private const MAX_TEXTO_LICAO = 500;

    /**
     * Palavras que não são nome de empresa nem de instituição. Um nome feito só delas ("Vaga", "Salário",
     * "Loja") não entra na memória de nomes: senão viraria "empresa" nos anúncios de todo mundo.
     */
    private const NOMES_GENERICOS = ['vaga','vagas','emprego','empregos','empresa','empresas','salario','beneficio','beneficios','requisito','requisitos',
        'local','horario','contato','whatsapp','curriculo','oportunidade','oportunidades','contrata','contratamos','contratando','urgente','atencao',
        'processo','seletivo','trabalho','trabalhe','conosco','equipe','time','loja','lojas','nome','teste','nao','informado','sim','brasilia','df',
        'curso','cursos','gratuito','gratuita','online','ead','instituicao','escola','anuncio','cargo','funcao','servicos','geral','gerais'];

    /** Desligada, a máquina usa só as regras (os testes das regras rodam assim). */
    private static bool $ligada = true;
    /** @var array<string,NaiveBayes> modelos já carregados nesta requisição */
    private static array $modelos = [];
    /** @var array<string,list<string>> nomes já carregados nesta requisição */
    private static array $nomes = [];
    /** @var array<string,array{provas:int,acertos:int}>|null provas dos modelos, carregadas uma vez por requisição */
    private static ?array $provas = null;
    /** @var array<string,array>|null calibração (temperatura) dos modelos, carregada uma vez por requisição */
    private static ?array $calibracoes = null;

    public static function ligar(bool $ligada = true): void { self::$ligada = $ligada; }
    public static function estaLigada(): bool { return self::$ligada; }

    /**
     * Troca o modelo em memória por outro (sem banco). Usado nos testes automáticos
     * para conferir a decisão da máquina com um modelo montado na hora.
     */
    public static function usarModelo(string $nome, NaiveBayes $modelo): void { self::$modelos[$nome] = $modelo; }

    /** O mesmo para as memórias de nomes: uma lista fixa no lugar da lida do banco (testes). */
    public static function usarNomes(string $memoria, array $nomes): void { self::$nomes[$memoria] = array_values($nomes); }

    /** O mesmo para as provas do período de experiência (testes). */
    public static function usarProvas(string $modelo, int $provas, int $acertos): void {
        self::$provas ??= [];
        self::$provas[$modelo] = ['provas' => $provas, 'acertos' => $acertos];
    }

    /** O mesmo para a temperatura de um modelo (testes). */
    public static function usarTemperatura(string $modelo, float $temperatura): void {
        self::$calibracoes ??= [];
        self::$calibracoes[$modelo] = ['temperatura' => $temperatura, 'licoes' => 0, 'amostras' => 0, 'nll_antes' => 0.0, 'nll_depois' => 0.0];
    }

    /** Esquece o que foi carregado nesta requisição (a próxima consulta lê o banco de novo). */
    public static function limparMemoria(): void { self::$modelos = []; self::$nomes = []; self::$provas = null; self::$calibracoes = null; }

    // ================================================================== 1. EXTRAIR — usar o que aprendeu

    /**
     * Decisão híbrida: regra ou modelo?
     * Nunca lança erro: se algo der errado aqui, vale a regra e a extração segue normal.
     *
     * @param string $palpiteRegra o que a regra escolheu ('' = a regra não soube)
     * @return array{classe:string,origem:string,confianca:?float,motivos:array<string,float>}
     *         origem 'regra' ou 'maquina'; motivos = palavras que mais pesaram (quando a máquina decidiu)
     */
    public static function decidir(string $modelo, string $texto, string $palpiteRegra): array {
        $regra = ['classe' => $palpiteRegra, 'origem' => 'regra', 'confianca' => null, 'motivos' => []];
        if (!self::$ligada) return $regra;
        try {
            $p = self::prever($modelo, $texto);
        } catch (Throwable $e) {
            self::registrarFalha('decidir com o modelo '.$modelo, $e);
            return $regra;
        }
        if ($p === null || !$p['pronta']) return $regra;
        if ($p['classe'] === $palpiteRegra) return $regra;   // concordam: fica registrado como regra
        return ['classe' => $p['classe'], 'origem' => 'maquina', 'confianca' => $p['confianca'], 'motivos' => $p['motivos']];
    }

    /**
     * Previsão pura do modelo, com a explicação. Também usada pelo "teste a máquina" do painel.
     *  confiante = passou nos critérios de lições (no modelo e na classe), tem 2+ classes e confiança mínima;
     *  pronta    = confiante E o modelo já foi liberado no período de experiência (liberado()).
     * @return array{classe:string,confianca:float,probabilidades:array<string,float>,motivos:array<string,float>,confiante:bool,pronta:bool}|null
     */
    public static function prever(string $modelo, string $texto): ?array {
        $nb = self::modelo($modelo);
        // Mesmo tamanho de texto das lições: o modelo prevê com o mesmo tipo de texto com que aprendeu.
        $palavras = Tokenizador::palavras(mb_substr($texto, 0, self::MAX_TEXTO_LICAO));
        $p = $nb->prever($palavras, self::temperatura($modelo));
        if ($p === null) return null;
        $p['confiante'] = self::confiante($nb, $p);
        $p['pronta'] = $p['confiante'] && self::liberado($modelo);
        $p['motivos'] = $nb->explicar($palavras, $p['classe']);
        return $p;
    }

    /**
     * Período de experiência de um modelo: provas feitas, acertos, precisão e se já está liberado.
     * @return array{provas:int,acertos:int,precisao:?float,liberado:bool}
     */
    public static function desempenho(string $modelo): array {
        if (self::$provas === null) {
            try {
                self::$provas = (new AprendizadoDAO())->provas();
            } catch (Throwable $e) {
                self::registrarFalha('carregar as provas dos modelos', $e);
                self::$provas = [];
            }
        }
        $d = self::$provas[$modelo] ?? ['provas' => 0, 'acertos' => 0];
        $precisao = $d['provas'] > 0 ? $d['acertos'] / $d['provas'] : null;
        return $d + ['precisao' => $precisao, 'liberado' => $d['provas'] >= self::MIN_PROVAS && $precisao >= self::PRECISAO_MINIMA];
    }

    /** O modelo já provou, nas provas do período de experiência, que acerta o suficiente para decidir? */
    public static function liberado(string $modelo): bool {
        return self::desempenho($modelo)['liberado'];
    }

    /**
     * Procura no texto um nome que as pessoas já confirmaram (empresa, instituição).
     * Os mais confirmados são testados primeiro. '' = nenhum.
     */
    public static function nomeConhecido(string $memoria, string $texto): string {
        if (!self::$ligada || $texto === '') return '';
        $n = ' '.Competencias::normalizar($texto).' ';   // o texto é normalizado uma vez só, não uma vez por nome
        foreach (self::nomes($memoria) as $nome) {
            $k = Competencias::normalizar($nome);
            if (mb_strlen($k) >= 3 && str_contains($n, ' '.$k.' ')) return $nome;
        }
        return '';
    }

    /** O modelo carregado do banco (uma vez por requisição). Sem banco, um modelo vazio. */
    public static function modelo(string $nome): NaiveBayes {
        if (isset(self::$modelos[$nome])) return self::$modelos[$nome];
        $nb = new NaiveBayes();
        try {
            $d = (new AprendizadoDAO())->carregar($nome);
            $nb = NaiveBayes::deContagens($d['exemplos'], $d['contagens']);
        } catch (Throwable $e) {
            self::registrarFalha('carregar o modelo '.$nome, $e);
        }
        return self::$modelos[$nome] = $nb;
    }

    // ================================================================== 2. REVISAR — guardar a sugestão

    /**
     * O que precisa ser lembrado da sugestão até a pessoa salvar (o controller guarda na sessão).
     * @param array $extraido resultado da extração (campos com os nomes do formulário)
     * @param list<string> $linhas linhas do texto original lido
     * @param string $texto texto original (para conferir se um nome estava mesmo no anúncio)
     * @return array{origem:string,campos:array,linhas:list<string>,texto:string,criada:int}
     */
    public static function sugestao(string $origem, array $extraido, array $linhas, string $texto): array {
        $cfg = self::REVISOES[$origem] ?? [];
        $extraido = self::comApelidos($extraido, $cfg);
        $chaves = array_merge($cfg['campos'] ?? [], $cfg['linhas'][1] ?? []);
        $campos = [];
        foreach ($chaves as $c) $campos[$c] = is_scalar($extraido[$c] ?? null) ? $extraido[$c] : null;
        // Limites para não inchar a sessão (um currículo longo tem algumas centenas de linhas).
        // O texto inteiro só serve para a memória de nomes; quem não tem memória (o currículo, que tem
        // dados pessoais) não guarda o texto na sessão.
        return ['origem' => $origem, 'campos' => $campos, 'linhas' => array_slice(array_values($linhas), 0, 400),
                'texto' => isset($cfg['memoria']) ? mb_substr($texto, 0, 20000) : '', 'criada' => time()];
    }

    // ================================================================== 3. APRENDER — com a correção

    /**
     * Compara a sugestão com o que a pessoa salvou, grava as lições e o resultado da revisão.
     * Nunca lança erro: se algo falhar, o salvamento da vaga/curso/perfil segue normal.
     *
     * @param array $sugestao o que sugestao() devolveu na hora da extração
     * @param array $salvo    os campos que foram gravados (com os mesmos nomes da sugestão)
     * @param bool  $confiavel revisão do administrador: pode trocar a resposta de uma lição já confirmada por outros
     * @return array{licoes:int,campos:int,campos_certos:int,linhas:int,linhas_certas:int,corrigidos:list<string>}
     */
    public static function aprenderDaRevisao(array $sugestao, array $salvo, ?int $usuarioId, bool $confiavel = false): array {
        $vazio = ['licoes' => 0, 'campos' => 0, 'campos_certos' => 0, 'linhas' => 0, 'linhas_certas' => 0, 'corrigidos' => []];
        $origem = (string)($sugestao['origem'] ?? '');
        $cfg = self::REVISOES[$origem] ?? null;
        if (!$cfg) return $vazio;
        try {
            $salvo = self::comApelidos($salvo, $cfg);
            $previsto = (array)($sugestao['campos'] ?? []);
            $linhas = array_values(array_filter((array)($sugestao['linhas'] ?? []), 'is_string'));
            $r = $vazio;

            // Campos simples: quantos a máquina acertou.
            [$r['campos'], $r['campos_certos'], $r['corrigidos']] = CorrecaoHumana::conferirCampos($previsto, $salvo, $cfg['campos']);

            // Linhas: onde a pessoa deixou cada uma (lição) e quantas a máquina pôs no lugar certo.
            if (isset($cfg['linhas'])) {
                [$modelo, $camposTexto] = $cfg['linhas'];
                // Título, empresa e cidade aparecem na frase de abertura da descrição; não são lição de "seção".
                $nomesProprios = array_map([Competencias::class, 'normalizar'], array_filter([(string)($salvo['titulo'] ?? ''), (string)($salvo['anunciante'] ?? ''), (string)($salvo['cidade'] ?? '')]));
                $linhas = array_values(array_filter($linhas, fn($l) => !in_array(Competencias::normalizar($l), $nomesProprios, true)));
                $salvosTexto = self::recorte($salvo, $camposTexto);
                [$r['linhas'], $r['linhas_certas']] = CorrecaoHumana::conferirLinhas($linhas, self::recorte($previsto, $camposTexto), $salvosTexto);
                foreach (CorrecaoHumana::licoesDeLinhas($linhas, $salvosTexto) as $l) {
                    if (self::aprender($modelo, $l['texto'], $l['classe'], $usuarioId, $confiavel)) $r['licoes']++;
                }
            }

            // Categoria: o texto do item salvo → a categoria escolhida.
            if (isset($cfg['categoria']) && trim((string)($salvo['categoria'] ?? '')) !== '') {
                [$modelo, $camposTexto] = $cfg['categoria'];
                $texto = implode("\n", array_map(fn($c) => (string)($salvo[$c] ?? ''), $camposTexto));
                if (self::aprender($modelo, $texto, (string)$salvo['categoria'], $usuarioId, $confiavel)) $r['licoes']++;
            }

            // Nome próprio (empresa, instituição): só é lembrado se estava escrito no texto lido —
            // senão a máquina nunca conseguiria reconhecê-lo sozinha — e se não é o cargo nem a cidade.
            if (isset($cfg['memoria'])) {
                [$memoria, $campo] = $cfg['memoria'];
                $nome = trim((string)($salvo[$campo] ?? ''));
                $outros = array_map([Competencias::class, 'normalizar'], [(string)($salvo['titulo'] ?? ''), (string)($salvo['cidade'] ?? '')]);
                if ($nome !== '' && !in_array(Competencias::normalizar($nome), $outros, true) && CorrecaoHumana::nomeNoTexto($nome, (string)($sugestao['texto'] ?? ''))) {
                    if (self::lembrarNome($memoria, $nome, $usuarioId)) $r['licoes']++;
                }
            }

            (new AprendizadoDAO())->registrarRevisao(['origem' => $origem] + $r, $usuarioId);
            foreach ([$cfg['linhas'][0] ?? null, $cfg['categoria'][0] ?? null] as $m) if ($m) self::calibrarSeNecessario($m);
            self::limparMemoria();   // a próxima leitura já usa o que foi aprendido agora
            return $r;
        } catch (Throwable $e) {
            self::registrarFalha('aprender com a revisão de '.$origem, $e);
            return $vazio;
        }
    }

    /**
     * Uma lição: "este texto é desta classe". Atualiza os contadores na hora (aprendizado incremental).
     *
     * Passo a passo:
     *  1. o texto é cortado no tamanho que o banco guarda ANTES de virar palavras (assim, ao esquecer a
     *     lição, as palavras recalculadas do texto guardado são exatamente as que foram somadas);
     *  2. PROVA: se o modelo já estava confiante, ele tenta adivinhar a resposta antes de aprender;
     *  3. a lição é gravada (AprendizadoDAO::registrarExemplo);
     *  4. se a lição era nova, a prova conta no período de experiência; o modelo em memória também
     *     aprende, para a próxima lição desta mesma requisição já ser testada com ele atualizado.
     *
     * @param bool $confiavel  pode trocar a resposta de uma lição que outras revisões já confirmaram (administrador)
     * @param bool $contarVez  false = lição que já existe não ganha +1 em "vezes" (histórico rodado de novo)
     * @return bool true = virou lição (nova, confirmada ou corrigida)
     */
    public static function aprender(string $modelo, string $texto, string $classe, ?int $usuarioId = null, bool $confiavel = false, bool $contarVez = true): bool {
        $texto = trim(mb_substr($texto, 0, self::MAX_TEXTO_LICAO));
        $palavras = Tokenizador::palavras($texto);
        if (!$palavras || $classe === '' || !isset(self::MODELOS[$modelo])) return false;
        try {
            $nb = self::modelo($modelo);
            $palpite = $nb->prever($palavras, self::temperatura($modelo));
            $prova = $palpite !== null && self::confiante($nb, $palpite) ? (string)$palpite['classe'] : null;

            $dao = new AprendizadoDAO();
            $res = $dao->registrarExemplo($modelo, mb_substr($classe, 0, 100), $texto, self::chave($texto), [Tokenizador::class, 'palavras'], $usuarioId, $confiavel, $contarVez);

            if ($res['status'] === 'novo') {
                if ($prova !== null) {
                    $d = self::desempenho($modelo);   // lê as provas antes de gravar a nova (senão ela contaria duas vezes)
                    $dao->registrarProva($modelo, $prova === $classe);
                    self::$provas[$modelo] = ['provas' => $d['provas'] + 1, 'acertos' => $d['acertos'] + ($prova === $classe ? 1 : 0)];
                }
                $nb->aprender($palavras, $classe);
            } elseif ($res['status'] === 'corrigido') {
                $nb->esquecer($res['palavras_antigas'], (string)$res['classe_antiga']);
                $nb->aprender($palavras, $classe);
            }
            return $res['status'] !== 'mantido';
        } catch (Throwable $e) {
            self::registrarFalha('gravar uma lição de '.$modelo, $e);
            return false;
        }
    }

    /** Guarda um nome próprio confirmado (empresa, instituição). Nomes genéricos ("Vaga", "Loja") são recusados. */
    public static function lembrarNome(string $memoria, string $nome, ?int $usuarioId = null, bool $contarVez = true): bool {
        $nome = trim(mb_substr($nome, 0, 150));
        if (!isset(self::MEMORIAS[$memoria]) || self::nomeGenerico($nome)) return false;
        try {
            (new AprendizadoDAO())->registrarExemplo($memoria, AprendizadoDAO::CLASSE_NOME, $nome, self::chave($nome), fn() => [], $usuarioId, false, $contarVez);
            return true;
        } catch (Throwable $e) {
            self::registrarFalha('lembrar o nome '.$nome, $e);
            return false;
        }
    }

    /** Nome curto demais ou feito só de palavras genéricas (NOMES_GENERICOS)? */
    public static function nomeGenerico(string $nome): bool {
        $palavras = array_filter(explode(' ', Competencias::normalizar($nome)));
        if (mb_strlen(implode(' ', $palavras)) < 3) return true;
        return !array_diff($palavras, self::NOMES_GENERICOS);
    }

    /**
     * APRENDER COM O HISTÓRICO: ensina a máquina com o que já está cadastrado (vagas publicadas, cursos
     * do catálogo, perfis de candidatos), que em geral passou pela revisão de uma pessoa. Serve para a
     * máquina não começar do zero numa instalação nova. Rodar de novo não duplica nada: lição que já
     * existe fica como está. Cada lição nova passa pela prova do período de experiência, então o
     * histórico também mostra se cada modelo merece ser liberado.
     *
     *  vaga      : cada linha da descrição/requisitos/benefícios → vaga_linha; texto → vaga_categoria;
     *              empresa anunciante → memória de empresas
     *  curso     : texto → curso_categoria; instituição → memória de instituições
     *  curriculo : cada linha de experiências, formação, cursos, habilidades, idiomas, objetivo e resumo
     *              do perfil → curriculo_linha. Só perfis PÚBLICOS (o candidato escolheu mostrar), e a
     *              lição fica no nome do próprio candidato: se a conta for excluída, ela é esquecida.
     *              Limite conhecido: um perfil preenchido pelo currículo e nunca revisado também entra,
     *              e aí a máquina aprende a própria saída.
     *
     * @param list<array> $registros linhas do banco (VagaDAO::listar, CursoDAO::listar, PerfilDAO::listarCandidatos)
     * @param int|null $usuarioId quem mandou estudar o histórico (o administrador)
     * @return int quantas lições foram gravadas (novas ou confirmadas)
     */
    public static function aprenderComHistorico(string $origem, array $registros, ?int $usuarioId = null): int {
        $n = 0;
        // Dados já cadastrados valem como revisão do administrador (true) e não somam "vezes" de novo (false).
        $aprender = fn(string $modelo, string $texto, string $classe, ?int $quem = null) => (int)self::aprender($modelo, $texto, $classe, $quem ?? $usuarioId, true, false);
        foreach ($registros as $reg) {
            if ($origem === 'vaga') {
                foreach (['descricao', 'requisitos', 'beneficios'] as $campo) {
                    foreach (self::linhasDoCampo((string)($reg[$campo] ?? '')) as $l) $n += $aprender('vaga_linha', $l, $campo);
                }
                if (($reg['categoria_nome'] ?? '') !== '') $n += $aprender('vaga_categoria', implode("\n", [$reg['titulo'] ?? '', $reg['descricao'] ?? '', $reg['requisitos'] ?? '']), (string)$reg['categoria_nome']);
                if (trim((string)($reg['anunciante'] ?? '')) !== '') $n += (int)self::lembrarNome('vaga_empresa', (string)$reg['anunciante'], $usuarioId, false);
            } elseif ($origem === 'curso') {
                if (($reg['categoria_nome'] ?? '') !== '') $n += $aprender('curso_categoria', ($reg['titulo'] ?? '')."\n".($reg['descricao'] ?? ''), (string)$reg['categoria_nome']);
                if (trim((string)($reg['instituicao'] ?? '')) !== '') $n += (int)self::lembrarNome('curso_instituicao', (string)$reg['instituicao'], $usuarioId, false);
            } elseif ($origem === 'curriculo' && (int)($reg['publico'] ?? 0) === 1) {
                $secoes = ['experiencias' => 'experiencias', 'formacao' => 'formacao', 'cursos_complementares' => 'cursos', 'habilidades' => 'habilidades',
                           'idiomas' => 'idiomas', 'objetivo' => 'objetivo', 'bio' => 'resumo'];
                $candidato = isset($reg['usuario_id']) ? (int)$reg['usuario_id'] : null;
                foreach ($secoes as $coluna => $secao) {
                    foreach (self::linhasDoCampo((string)($reg[$coluna] ?? '')) as $l) $n += $aprender('curriculo_linha', $l, $secao, $candidato);
                }
            }
        }
        // Com o histórico estudado, cada modelo desta origem ganha a sua temperatura.
        $modelosDaOrigem = ['vaga' => ['vaga_linha', 'vaga_categoria'], 'curso' => ['curso_categoria'], 'curriculo' => ['curriculo_linha']][$origem] ?? [];
        foreach ($modelosDaOrigem as $m) self::calibrar($m);
        self::limparMemoria();
        return $n;
    }

    // ================================================================== calibração (temperatura)

    /** A temperatura do modelo (1 = ainda não calibrado). */
    public static function temperatura(string $modelo): float {
        return (float)(self::calibracoes()[$modelo]['temperatura'] ?? 1.0);
    }

    /**
     * Dados da calibração de um modelo para o painel (temperatura e erro antes/depois), ou null.
     * @return array{temperatura:float,licoes:int,amostras:int,nll_antes:float,nll_depois:float}|null
     */
    public static function calibracao(string $modelo): ?array {
        return self::calibracoes()[$modelo] ?? null;
    }

    /**
     * Recalcula a temperatura do modelo: refaz as previsões na ordem em que as lições chegaram
     * (Calibracao::amostrasPrequenciais) e escolhe a temperatura que minimiza o erro delas.
     * Nunca lança erro; devolve a calibração gravada ou null.
     */
    public static function calibrar(string $modelo): ?array {
        if (!isset(self::MODELOS[$modelo])) return null;
        try {
            $dao = new AprendizadoDAO();
            $licoes = array_map(fn($l) => [Tokenizador::palavras((string)$l['texto']), (string)$l['classe']], $dao->licoesDoModelo($modelo));
            $amostras = Calibracao::amostrasPrequenciais($licoes, self::MIN_LICOES);
            $t = Calibracao::temperatura($amostras);
            $c = ['temperatura' => $t, 'licoes' => count($licoes), 'amostras' => count($amostras),
                  'nll_antes' => round(Calibracao::nll($amostras, 1.0), 4), 'nll_depois' => round(Calibracao::nll($amostras, $t), 4)];
            $dao->salvarCalibracao($modelo, $t, $c['licoes'], $c['amostras'], $c['nll_antes'], $c['nll_depois']);
            self::calibracoes();
            self::$calibracoes[$modelo] = $c;
            return $c;
        } catch (Throwable $e) {
            self::registrarFalha('calibrar o modelo '.$modelo, $e);
            return null;
        }
    }

    /** Recalibra só se o modelo ganhou RECALIBRAR_A_CADA lições desde a última calibração. */
    public static function calibrarSeNecessario(string $modelo): void {
        $ultima = self::calibracao($modelo);
        $agora = self::modelo($modelo)->totalExemplos();
        if ($agora >= self::MIN_LICOES && $agora - (int)($ultima['licoes'] ?? 0) >= self::RECALIBRAR_A_CADA) self::calibrar($modelo);
    }

    /** @return array<string,array> calibrações carregadas uma vez por requisição (vazio se o banco falhar) */
    private static function calibracoes(): array {
        if (self::$calibracoes === null) {
            try {
                self::$calibracoes = (new AprendizadoDAO())->calibracoes();
            } catch (Throwable $e) {
                self::registrarFalha('carregar a calibração dos modelos', $e);
                self::$calibracoes = [];
            }
        }
        return self::$calibracoes;
    }

    // ================================================================== manutenção AUTOMÁTICA

    /** De quantas em quantas horas a máquina estuda sozinha o que já está cadastrado. */
    public const ESTUDO_A_CADA_HORAS = 24;

    /**
     * MANUTENÇÃO AUTOMÁTICA — ninguém precisa abrir painel nem calibrar nada:
     *  - estuda sozinha, no máximo uma vez a cada ESTUDO_A_CADA_HORAS, tudo o que foi cadastrado e revisado por
     *    pessoas (vagas, cursos e perfis públicos) — aprende o que é novo e só confirma o que já sabia;
     *  - cada estudo recalibra a temperatura dos modelos (confiança honesta);
     *  - o período de experiência olha o desempenho RECENTE (AprendizadoDAO::registrarProva guarda uma janela):
     *    se a máquina começar a errar, ela perde o direito de decidir e a regra volta a valer até ela provar de novo;
     *  - lição contraditória se corrige sozinha: a correção mais recente vale (AprendizadoDAO::registrarExemplo).
     * Chamada ao abrir a visão geral do painel. Nunca lança erro: falhou, fica para a próxima.
     * @return int lições estudadas agora (0 = ainda não era hora ou máquina desligada)
     */
    public static function manutencaoAutomatica(?int $usuarioId = null): int {
        if (!self::$ligada) return 0;
        $marca = LOG_DIR.'aprendizado_ultimo_estudo.txt';
        $ultima = is_file($marca) ? (int)@file_get_contents($marca) : 0;
        if (time() - $ultima < self::ESTUDO_A_CADA_HORAS * 3600) return 0;
        @file_put_contents($marca, (string)time(), LOCK_EX);   // marca antes: duas abas abertas não estudam em dobro
        try {
            @set_time_limit(300);
            return self::aprenderComHistorico('vaga', (new VagaDAO())->listar(false), $usuarioId)
                 + self::aprenderComHistorico('curso', (new CursoDAO())->listar(false), $usuarioId)
                 + self::aprenderComHistorico('curriculo', (new PerfilDAO())->listarCandidatos(), $usuarioId);
        } catch (Throwable $e) {
            self::registrarFalha('fazer a manutenção automática', $e);
            return 0;
        }
    }

    // ================================================================== manutenção (painel técnico, fora do menu)

    /** Apaga uma lição (e desconta as palavras dela do modelo). */
    public static function esquecer(int $id): bool {
        $dao = new AprendizadoDAO();
        $ex = $dao->buscarExemplo($id);
        if (!$ex) return false;
        $palavras = $ex['classe'] === AprendizadoDAO::CLASSE_NOME ? [] : Tokenizador::palavras((string)$ex['texto']);
        $ok = $dao->removerExemplo($ex, $palavras);
        self::limparMemoria();
        return $ok;
    }

    /**
     * LGPD: apaga as lições de currículo que vieram de um candidato (linhas com empregadores, escolas,
     * resumo pessoal). Chamado antes de excluir a conta; as palavras delas também saem do modelo.
     * @return int quantas lições foram apagadas
     */
    public static function esquecerDoUsuario(int $usuarioId): int {
        $n = 0;
        try {
            foreach ((new AprendizadoDAO())->exemplosDoUsuario($usuarioId, 'curriculo_linha') as $ex) $n += (int)self::esquecer((int)$ex['id']);
        } catch (Throwable $e) {
            self::registrarFalha('esquecer as lições do usuário '.$usuarioId, $e);
        }
        return $n;
    }

    /** Apaga tudo o que um modelo (ou uma memória de nomes) aprendeu: nessa parte da extração, voltam a valer só as regras. */
    public static function zerar(string $modelo): int {
        if (!isset(self::MODELOS[$modelo]) && !isset(self::MEMORIAS[$modelo])) return 0;
        $n = (new AprendizadoDAO())->zerar($modelo);
        self::limparMemoria();
        return $n;
    }

    // ================================================================== apoio

    /**
     * O modelo arriscaria esta resposta? Tem lições suficientes (no total e na classe escolhida),
     * pelo menos duas classes (com uma só, qualquer texto sairia com 100%) e a confiança mínima.
     * Não olha o período de experiência: é justamente o que as provas medem.
     */
    private static function confiante(NaiveBayes $nb, array $previsao): bool {
        $porClasse = $nb->exemplosPorClasse();
        return $nb->totalExemplos() >= self::MIN_LICOES
            && count($porClasse) >= 2
            && ($porClasse[$previsao['classe']] ?? 0) >= self::MIN_LICOES_CLASSE
            && $previsao['confianca'] >= self::CONFIANCA_MINIMA;
    }

    /**
     * Identidade de um texto: a mesma frase, mesmo com diferença de acento, maiúsculas ou pontuação, gera a mesma chave.
     * O texto guardado é o original, mas a chave evita aprender a mesma frase duas vezes.
     */
    private static function chave(string $texto): string {
        return sha1(Competencias::normalizar($texto));
    }

    /** @return list<string> nomes da memória, carregados uma vez por requisição */
    private static function nomes(string $memoria): array {
        if (isset(self::$nomes[$memoria])) return self::$nomes[$memoria];
        try {
            return self::$nomes[$memoria] = (new AprendizadoDAO())->nomes($memoria);
        } catch (Throwable $e) {
            self::registrarFalha('carregar a memória '.$memoria, $e);
            return self::$nomes[$memoria] = [];
        }
    }

    /**
     * Linhas de um campo de texto já cadastrado, sem marcadores ("• ", "- ", "✓ ") e sem as muito curtas.
     * @return list<string>
     */
    private static function linhasDoCampo(string $texto): array {
        $out = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $l) {
            $l = trim_u(trim($l), " •·▪-–—*>✓✔");
            if (mb_strlen(Competencias::normalizar($l)) >= 4) $out[] = $l;
        }
        return $out;
    }

    /** Preenche o campo pelo outro nome dele, quando só o outro nome veio (ver 'apelidos' em REVISOES). */
    private static function comApelidos(array $dados, array $cfg): array {
        foreach ($cfg['apelidos'] ?? [] as $campo => $outros) {
            if (isset($dados[$campo])) continue;
            foreach ($outros as $o) if (isset($dados[$o])) { $dados[$campo] = $dados[$o]; break; }
        }
        return $dados;
    }

    /** @return array<string,string> só os campos pedidos, como texto */
    private static function recorte(array $dados, array $campos): array {
        $out = [];
        foreach ($campos as $c) $out[$c] = (string)($dados[$c] ?? '');
        return $out;
    }

    /** O aprendizado nunca derruba a página: o erro vai para o log do PHP e o sistema continua funcionando. */
    private static function registrarFalha(string $oQue, Throwable $e): void {
        error_log('[MaquinaAprendizado] Não foi possível '.$oQue.': '.$e->getMessage());
    }
}
