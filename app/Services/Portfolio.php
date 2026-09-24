<?php
declare(strict_types=1);

/**
 * Monta o portfólio profissional a partir dos dados do perfil (preenchidos
 * manualmente ou pela extração do currículo): organiza experiências em linha do
 * tempo, formação por curso/instituição/situação e listas de habilidades e cursos.
 *
 * Formatos de experiência reconhecidos (período em qualquer formato: "Jan/2020 – Atual",
 * "2019 - 2021", "03/2018 a 12/2019", "Desde 2022", "janeiro de 2019 a março de 2021"):
 *  - empresa / cargo / período em linhas separadas (período por último ou primeiro);
 *  - "Cargo – Empresa | período" ou "Empresa | Cargo | período" numa linha só;
 *  - rótulos "Empresa: ...", "Cargo: ...", "Período: ...".
 */
final class Portfolio {
    /** Campos obrigatórios para o cadastro do perfil ser validado e o portfólio ser liberado. */
    public const OBRIGATORIOS = [
        'telefone' => 'Telefone', 'titulo_profissional' => 'Título profissional', 'cidade' => 'Cidade',
        'bio' => 'Resumo profissional', 'objetivo' => 'Objetivo profissional',
        'formacao' => 'Formação acadêmica', 'habilidades' => 'Habilidades',
    ];
    /** Recomendados (não bloqueiam): deixam o portfólio mais completo. */
    public const RECOMENDADOS = [
        'experiencias' => 'Experiências', 'foto' => 'Foto', 'cursos_complementares' => 'Cursos complementares', 'idiomas' => 'Idiomas',
    ];

    /**
     * Validação do cadastro do perfil.
     * @return array{completo:bool,faltando:array<string,string>,recomendados:array<string,string>,percentual:int}
     */
    public static function validarCadastro(?array $perfil): array {
        $vazio = fn(string $c) => trim((string)($perfil[$c] ?? '')) === '';
        $faltando = array_filter(self::OBRIGATORIOS, fn($rot, $c) => $vazio($c), ARRAY_FILTER_USE_BOTH);
        $recom = array_filter(self::RECOMENDADOS, fn($rot, $c) => $vazio($c), ARRAY_FILTER_USE_BOTH);
        $total = count(self::OBRIGATORIOS) + count(self::RECOMENDADOS);
        $ok = $total - count($faltando) - count($recom);
        return [
            'completo' => $perfil !== null && !$faltando,
            'faltando' => $faltando,
            'recomendados' => $recom,
            'percentual' => (int)round($ok / $total * 100),
        ];
    }

    /** Palavras que indicam que o trecho é um cargo (e não o nome da empresa). */
    private const CARGOS = 'auxiliar|assistente|atendente|analista|gerente|supervisor|supervisora|coordenador|coordenadora|tecnico|tecnica|tecnologo|estagiario|estagiaria|estagio|vendedor|vendedora|desenvolvedor|desenvolvedora|programador|programadora|agente|operador|operadora|motorista|recepcionista|secretario|secretaria|professor|professora|enfermeiro|enfermeira|diretor|diretora|consultor|consultora|lider|encarregado|encarregada|repositor|repositora|caixa|promotor|promotora|jovem aprendiz|aprendiz|monitor|monitora|cozinheiro|cozinheira|eletricista|pedreiro|servente|domestica|diarista|cuidador|cuidadora|estoquista|conferente|comprador|compradora|designer|engenheiro|engenheira|administrador|administradora|contador|contadora|porteiro|vigilante|garcom|garconete|barista|entregador|entregadora|frentista|zelador|zeladora|bombeiro|mecanico|montador|suporte|freelancer|autonomo|autonoma|voluntario|voluntaria|balconista|executivo|executiva|especialista|instrutor|instrutora|tutor|tutora|socio|socia|proprietario|proprietaria|fundador|fundadora|trainee|chefe|gestor|gestora|arquiteto|arquiteta|redator|redatora|editor|editora|fotografo|fotografa|copywriter|social media|product|scrum|devops|web designer|ux|ui';

    private const MES = '(?:jan(?:eiro)?|fev(?:ereiro)?|mar(?:[cç]o)?|abr(?:il)?|mai(?:o)?|jun(?:ho)?|jul(?:ho)?|ago(?:sto)?|set(?:embro)?|out(?:ubro)?|nov(?:embro)?|dez(?:embro)?)\.?';
    private const MESES = ['jan'=>1,'fev'=>2,'mar'=>3,'abr'=>4,'mai'=>5,'jun'=>6,'jul'=>7,'ago'=>8,'set'=>9,'out'=>10,'nov'=>11,'dez'=>12];

    /** Rótulos de cabeçalho de tabela ("Empresa | Cargo | Período") que não são dados. */
    private const ROTULOS = ['empresa','cargo','periodo','funcao','data','datas','instituicao','curso','ano','situacao','local','atividades','conclusao'];

    // ------------------------------------------------------------------ períodos

    /** Expressão do período (início–fim, início–atual ou "desde"). */
    private static function rxPeriodo(): string {
        $d = fn(string $k) => '(?:(?<m'.$k.'>(?<![\p{L}])'.self::MES.')\s*(?:de\s+|\/\s*|-\s*|\.\s*)?|(?<n'.$k.'>\d{1,2})\s*[\/.]\s*)?(?<a'.$k.'>(?:19|20)\d{2})(?!\d)';
        $atual = '(?<atual>atual(?:mente)?|presente|o\s+momento|hoje|os\s+dias\s+(?:de\s+hoje|atuais)|em\s+andamento|current|present|now)';
        return '/(?<!\d)(?:(?<desde>desde|a\s+partir\s+de)\s+)?'.$d('1').'(?:\s*(?:-|–|—|a|à|at[eé]|~|to)\s*(?:'.$d('2').'|'.$atual.'))?/iu';
    }

    /**
     * Procura um período na linha.
     * @return array{texto:string,inicio:int,fim:?int,resto:string}|null  (inicio/fim em meses absolutos; fim null = atual)
     */
    public static function periodo(string $linha): ?array {
        if (!preg_match_all(self::rxPeriodo(), $linha, $todos, PREG_SET_ORDER)) return null;
        // Só vale "início – fim", "início – atual" ou "desde início" (um ano solto não é período).
        $m = null;
        foreach ($todos as $c) if (!empty($c['desde']) || !empty($c['a2']) || !empty($c['atual'])) { $m = $c; break; }
        if ($m === null) return null;
        $ini = self::data($m['m1'] ?? '', $m['n1'] ?? '', $m['a1']);
        $atual = !empty($m['desde']) || !empty($m['atual']);
        $fim = $atual ? null : self::data($m['m2'] ?? '', $m['n2'] ?? '', $m['a2'] ?? '');
        if (!$ini || (!$atual && !$fim)) return null;
        if ($fim !== null && $fim['abs'] < $ini['abs']) return null;
        $texto = $ini['txt'].' – '.($atual ? 'Atual' : $fim['txt']);
        if (!$atual && $ini['txt'] === $fim['txt']) $texto = $ini['txt'];
        $resto = trim(str_replace($m[0], ' ', $linha));
        // Tira rótulos e sobras ("Período:", "()", separadores).
        $resto = preg_replace('/\b(per[ií]odo|data|admiss[aã]o)\s*:?\s*$/iu', '', $resto) ?? $resto;
        $resto = preg_replace('/\(\s*\)/u', '', $resto) ?? $resto;
        $resto = self::aparar($resto);
        return ['texto' => $texto, 'inicio' => $ini['abs'], 'fim' => $atual ? null : $fim['abs'], 'resto' => $resto];
    }

    /** @return array{txt:string,abs:int}|null */
    private static function data(string $mes, string $num, string $ano): ?array {
        if ($ano === '') return null;
        $a = (int)$ano;
        if ($mes !== '') {
            $k = substr(Competencias::normalizar($mes), 0, 3);
            $mm = self::MESES[$k] ?? 1;
            return ['txt' => ucfirst($k).'/'.$a, 'abs' => $a * 12 + $mm];
        }
        if ($num !== '' && (int)$num >= 1 && (int)$num <= 12) return ['txt' => sprintf('%02d/%d', (int)$num, $a), 'abs' => $a * 12 + (int)$num];
        return ['txt' => (string)$a, 'abs' => $a * 12 + 1];
    }

    // ------------------------------------------------------------------ experiências

    /**
     * @return array<int,array{empresa:string,cargo:string,periodo:string,descricao:string[],inicio:int,fim:?int}>
     */
    public static function experiencias(string $texto): array {
        $linhas = self::linhasComMarca($texto);
        $linhas = array_values(array_filter($linhas, fn($l) => !self::ehCabecalhoTabela($l['t'])));
        if (!$linhas) return [];

        // Formato com rótulos: "Empresa: X" / "Cargo: Y" / "Período: Z".
        foreach ($linhas as $l) if (preg_match('/^(empresa|empregador|local de trabalho)\s*:/iu', $l['t'])) return self::ordenarExp(self::expRotulada($linhas));

        $per = [];
        foreach ($linhas as $i => $l) { $p = self::periodo($l['t']); if ($p) $per[$i] = $p; }
        if (!$per) return [];
        $idx = array_keys($per);
        $primeiro = $idx[0];
        // Período antes de tudo (e sem texto na mesma linha) → cabeçalho vem DEPOIS do período.
        $depois = $primeiro === 0 && $per[$primeiro]['resto'] === '';

        $itens = [];
        foreach ($idx as $k => $i) {
            $p = $per[$i];
            $limAnt = $k > 0 ? $idx[$k - 1] + 1 : 0;           // primeira linha após o período anterior
            $limProx = $idx[$k + 1] ?? count($linhas);          // próxima linha de período
            $cab = []; $usadas = [];
            if ($p['resto'] !== '') {
                $cab = [$p['resto']];
            } elseif ($depois) {
                for ($j = $i + 1; $j < $limProx && count($cab) < 2; $j++) {
                    if (!self::ehLinhaCabecalho($linhas[$j])) break;
                    $cab[] = $linhas[$j]['t']; $usadas[] = $j;
                }
            } else {
                for ($j = $i - 1; $j >= $limAnt && count($cab) < 2; $j--) {
                    if (!self::ehLinhaCabecalho($linhas[$j])) break;
                    array_unshift($cab, $linhas[$j]['t']); $usadas[] = $j;
                }
            }
            [$empresa, $cargo] = self::empresaCargo($cab);
            $itens[$i] = ['empresa' => $empresa, 'cargo' => $cargo, 'periodo' => $p['texto'], 'descricao' => [], 'inicio' => $p['inicio'], 'fim' => $p['fim'], '_usadas' => $usadas];
        }
        // Linhas restantes: descrição do item a que pertencem.
        $usadas = [];
        foreach ($itens as $it) foreach ($it['_usadas'] as $u) $usadas[$u] = true;
        $dono = null; $semDono = [];
        foreach ($linhas as $i => $l) {
            if (isset($itens[$i])) { $dono = $i; continue; }
            if (isset($usadas[$i])) continue;
            if ($depois) {
                if ($dono !== null) $itens[$dono]['descricao'][] = $l['t']; else $semDono[] = $l['t'];
            } else {
                // Período por último: linhas antes do 1º cabeçalho não têm dono; as demais são do item anterior.
                if ($dono !== null) $itens[$dono]['descricao'][] = $l['t'];
                else {
                    // Pode ser descrição posicionada entre cabeçalho e período do 1º item.
                    $semDono[] = $l['t'];
                }
            }
        }
        $itens = array_values($itens);
        if ($semDono && $itens) {
            // Sem dono e antes do primeiro item: se o 1º item ficou sem empresa/cargo, usa como cabeçalho.
            if ($itens[0]['empresa'] === '' && $itens[0]['cargo'] === '') { [$itens[0]['empresa'], $itens[0]['cargo']] = self::empresaCargo(array_slice($semDono, -2)); }
            else array_unshift($itens[0]['descricao'], ...$semDono);
        }
        // Linha curta com cara de cargo logo no início da descrição completa o item.
        foreach ($itens as &$it) {
            unset($it['_usadas']);
            if ($it['cargo'] === '' && $it['descricao'] && self::ehCargo($it['descricao'][0]) && mb_strlen($it['descricao'][0]) <= 70 && !str_ends_with($it['descricao'][0], '.')) {
                $it['cargo'] = array_shift($it['descricao']);
            }
        }
        unset($it);
        return self::ordenarExp($itens);
    }

    private static function expRotulada(array $linhas): array {
        $itens = []; $atual = null;
        $fechar = function () use (&$atual, &$itens) { if ($atual) $itens[] = $atual; $atual = null; };
        foreach ($linhas as $l) {
            $t = $l['t'];
            if (preg_match('/^(empresa|empregador|local de trabalho)\s*:\s*(.*)$/iu', $t, $m)) {
                $fechar();
                $atual = ['empresa' => trim($m[2]), 'cargo' => '', 'periodo' => '', 'descricao' => [], 'inicio' => 0, 'fim' => null];
                continue;
            }
            $atual ??= ['empresa' => '', 'cargo' => '', 'periodo' => '', 'descricao' => [], 'inicio' => 0, 'fim' => null];
            if (preg_match('/^(cargo|fun[cç][aã]o|posi[cç][aã]o)\s*:\s*(.*)$/iu', $t, $m)) { $atual['cargo'] = trim($m[2]); continue; }
            if ($atual['periodo'] === '' && ($p = self::periodo($t))) { $atual['periodo'] = $p['texto']; $atual['inicio'] = $p['inicio']; $atual['fim'] = $p['fim']; if (!preg_match('/^(per[ií]odo|data)/iu', $t) && $p['resto'] !== '') $atual['descricao'][] = $p['resto']; continue; }
            $atual['descricao'][] = preg_replace('/^(atividades|descri[cç][aã]o|principais atividades)\s*:\s*/iu', '', $t) ?? $t;
        }
        $fechar();
        return $itens;
    }

    /** Mais recentes primeiro ("Atual" no topo). */
    private static function ordenarExp(array $itens): array {
        usort($itens, fn($a, $b) => [$b['fim'] === null && $b['periodo'] !== '', $b['inicio']] <=> [$a['fim'] === null && $a['periodo'] !== '', $a['inicio']]);
        return $itens;
    }

    /** Linha que pode ser empresa ou cargo: curta, sem marcador, sem ponto final, sem cara de frase. */
    private static function ehLinhaCabecalho(array $l): bool {
        $t = $l['t'];
        if ($l['marcador'] || mb_strlen($t) > 90 || $t === '') return false;
        if (preg_match('/[.;:]$/u', $t) && !preg_match('/\b(S\.?A|Ltda|ME|EIRELI|Cia|Jr)\.$/iu', $t)) return false;
        if (preg_match('/^\p{Ll}/u', $t)) return false;
        return count(preg_split('/\s+/u', $t) ?: []) <= 12;
    }

    private static function ehCabecalhoTabela(string $l): bool {
        $partes = preg_split('/\s*[|\t]\s*/u', $l) ?: [];
        if (count($partes) < 2) return false;
        foreach ($partes as $p) if (!in_array(Competencias::normalizar($p), self::ROTULOS, true)) return false;
        return true;
    }

    // ------------------------------------------------------------------ formação

    /** @return array<int,array{curso:string,instituicao:string,situacao:string}> */
    public static function formacao(string $texto): array {
        $out = [];
        foreach (self::linhasComMarca($texto) as $lm) {
            $l = $lm['t'];
            if (self::ehCabecalhoTabela($l)) continue;
            [$curso, $inst, $sit] = self::linhaFormacao($l);
            $n = count($out);
            // Linha só com situação/ano ("Cursando", "2019"): pertence ao curso de cima.
            if ($curso === '' && $inst === '') { if ($n && $sit !== '') $out[$n - 1]['situacao'] = self::juntarSituacao($out[$n - 1]['situacao'], $sit); continue; }
            // Linha que é só a instituição, logo abaixo do curso.
            if ($inst === '' && $n && $out[$n - 1]['instituicao'] === '' && self::ehInstituicao($curso) && !self::ehCurso($curso)) {
                $out[$n - 1]['instituicao'] = $curso;
                if ($sit !== '') $out[$n - 1]['situacao'] = self::juntarSituacao($out[$n - 1]['situacao'], $sit);
                continue;
            }
            $out[] = ['curso' => $curso, 'instituicao' => $inst, 'situacao' => $sit];
        }
        return $out;
    }

    /** @return array{0:string,1:string,2:string} [curso, instituição, situação] */
    private static function linhaFormacao(string $l): array {
        $situacao = ''; $ano = null; $previsao = false;
        $partes = preg_split('/\s+[-–—|\/]\s+|\s*[|–—]\s*/u', $l) ?: [$l];
        $curso = ''; $inst = ''; $resto = [];
        foreach ($partes as $p) {
            $p = trim($p);
            // Rótulos: "Graduação: X", "Instituição: Y", "Conclusão: 2020".
            if (preg_match('/^(institui[cç][aã]o|escola|faculdade|universidade|local)\s*:\s*(.+)$/iu', $p, $m)) { $inst = trim($m[2]); continue; }
            if (preg_match('/^(conclus[aã]o|ano de conclus[aã]o|t[eé]rmino|previs[aã]o(?: de conclus[aã]o)?)\s*:?\s*(.*)$/iu', $p, $m)) {
                if (str_starts_with(Competencias::normalizar($m[1]), 'previs')) $previsao = true;
                if (preg_match('/(19|20)\d{2}/', $m[2], $a)) $ano = (int)$a[0];
                $p = trim(preg_replace('/(19|20)\d{2}/', '', $m[2]) ?? '');
                if ($p === '') continue;
            }
            if (preg_match('/^(gradua[cç][aã]o|curso|forma[cç][aã]o|p[oó]s-gradua[cç][aã]o|n[ií]vel|escolaridade)\s*:\s*(.+)$/iu', $p, $m)) $p = trim($m[2]);
            // Anos (período ou ano final) — o último ano vale como conclusão/previsão.
            if (preg_match_all('/(?<!\d)((?:19|20)\d{2})(?!\d)/', $p, $am)) {
                $ano = (int)end($am[1]);
                if (preg_match('/previs|prevista|previsto/iu', $p)) $previsao = true;
                $p = preg_replace('/\(?\s*(?:(?:\d{1,2}\/)?(?:19|20)\d{2}\s*(?:-|–|—|a|at[eé])\s*)?(?:\d{1,2}\/)?(?:19|20)\d{2}\s*\)?/u', ' ', $p) ?? $p;
                // Sobras como "(previsão de conclusão:" ou "Concluído em" depois de tirar o ano.
                for ($k = 0; $k < 3; $k++) $p = preg_replace('/\s*\(?\s*\b(previs[aã]o de conclus[aã]o|conclus[aã]o|previs[aã]o|prevista|previsto|em|de)\s*[:(]*\s*$/iu', '', trim($p)) ?? $p;
            }
            // Situação ("cursando o 3º ano", "concluído", "completo"...).
            if (preg_match('/\b(cursando|em andamento|em curso|incomplet[oa]|trancad[oa]|conclu[ií]d[oa]|complet[oa]|formad[oa])\b/iu', $p, $sm)) {
                $s = Competencias::normalizar($sm[1]);
                $situacao = in_array($s, ['cursando', 'em andamento', 'em curso'], true) ? 'Cursando' : (str_starts_with($s, 'incomplet') || str_starts_with($s, 'trancad') ? 'Incompleto' : 'Concluído');
                // Parte curta que é só a situação ("cursando o 3º ano") some; senão tira só a palavra.
                if (count(preg_split('/\s+/u', trim($p)) ?: []) <= 5 && preg_match('/^\(?\s*(cursando|em andamento|em curso|incomplet|trancad|conclu|complet|formad)/iu', trim($p))) { $p = ''; }
                else $p = preg_replace('/\s*\(?\b(cursando|em andamento|em curso|incomplet[oa]|trancad[oa]|conclu[ií]d[oa]|complet[oa]|formad[oa])\b\)?/iu', '', $p) ?? $p;
            }
            $p = self::aparar(preg_replace('/\s+/u', ' ', $p) ?? $p);
            if ($p !== '') $resto[] = $p;
        }
        // Curso = parte com palavra de curso (ou a primeira); instituição = parte com cara de instituição.
        foreach ($resto as $k => $p) {
            if ($inst === '' && $k > 0 && self::ehInstituicao($p)) { $inst = $p; unset($resto[$k]); }
        }
        $resto = array_values($resto);
        $curso = $resto[0] ?? '';
        if ($inst === '' && isset($resto[1])) $inst = $resto[1];
        if ($ano !== null) {
            $futuro = $ano > (int)date('Y') || $previsao;
            if ($situacao === 'Cursando' || $futuro) $situacao = $futuro ? ($situacao === 'Cursando' ? 'Cursando (previsão: '.$ano.')' : 'Previsão de conclusão: '.$ano) : 'Cursando';
            elseif ($situacao === '' || $situacao === 'Concluído') $situacao = 'Concluído em '.$ano;
        }
        return [$curso, $inst, $situacao];
    }

    /** Tira separadores das pontas e parênteses sem par (mantém "(UnB)" e "(CONTRATO - PJ)"). */
    private static function aparar(string $p): string {
        for ($i = 0; $i < 6; $i++) {
            $p = trim_u($p, " \t-–—|,:;·•");
            $abre = substr_count($p, '('); $fecha = substr_count($p, ')');
            if (str_ends_with($p, ')') && $abre < $fecha) { $p = substr($p, 0, -1); continue; }
            if (str_starts_with($p, '(') && $abre > $fecha) { $p = substr($p, 1); continue; }
            if (str_starts_with($p, '(') && str_ends_with($p, ')') && $abre === 1) { $p = substr($p, 1, -1); continue; }
            if (str_ends_with($p, '(')) { $p = substr($p, 0, -1); continue; }
            break;
        }
        return trim($p);
    }

    private static function juntarSituacao(string $a, string $b): string {
        if ($a === '') return $b;
        if (str_starts_with($a, 'Concluído') && preg_match('/\d{4}/', $b, $m) && !preg_match('/\d{4}/', $a)) return 'Concluído em '.$m[0];
        return $b;
    }

    private static function ehInstituicao(string $s): bool {
        if (preg_match('/\b(faculdade|universidade|instituto|escola|col[eé]gio|centro|senai|senac|sesi|sesc|sebrae|ifb|if\s|unb|uniceub|ceub|iesb|anhanguera|est[aá]cio|unip|udf|unopar|uninter|unicesumar|cruzeiro do sul|fgv|puc|ced|cef|cem|etb|alura|udemy|coursera|fiap|cna|ccaa|fisk|wizard|microlins|ciee|fundacao bradesco)\b/iu', $s)) return true;
        // Sigla curta (UnB, IESB, UniCEUB).
        return (bool)preg_match('/^(?=.*\p{Lu}.*\p{Lu})[\p{L}]{2,9}$/u', $s);
    }

    private static function ehCurso(string $s): bool {
        return (bool)preg_match('/\b(gradua|bacharel|licenciatura|tecn[oó]log|t[eé]cnico em|curso|ensino|especializa|mba|mestrado|doutorado|p[oó]s)/iu', $s);
    }

    // ------------------------------------------------------------------ listas

    /** Lista de itens a partir de texto separado por vírgula, ponto e vírgula ou linha (vírgulas entre parênteses não separam). */
    public static function lista(string ...$textos): array {
        $out = []; $vistos = [];
        foreach ($textos as $t) {
            foreach (preg_split('/[\n;]+|,(?![^(]*\))/u', $t) ?: [] as $item) {
                $item = trim_u($item, " \t•·-–—.*▪►➤✓✔");
                $k = Competencias::normalizar($item);
                if ($item === '' || $k === '' || isset($vistos[$k]) || mb_strlen($item) > 110) continue;
                $vistos[$k] = true;
                $out[] = mb_strtoupper(mb_substr($item, 0, 1)).mb_substr($item, 1);
            }
        }
        return $out;
    }

    /** Links gravados no perfil (um por linha) → [href, texto curto, tipo]. */
    public static function links(string $texto): array {
        $out = [];
        foreach (preg_split('/[\s,;]+/u', $texto) ?: [] as $u) {
            $u = trim($u);
            if ($u === '' || !preg_match('#^(https?://)?[\w.-]+\.[a-z]{2,}(/\S*)?$#i', $u)) continue;
            $href = preg_match('#^https?://#i', $u) ? $u : 'https://'.$u;
            $curto = rtrim(preg_replace('#^(https?://)?(www\.)?#i', '', $u) ?? $u, '/');
            $tipo = match (true) {
                str_contains($curto, 'linkedin.') => 'LinkedIn', str_contains($curto, 'github.') => 'GitHub', str_contains($curto, 'gitlab.') => 'GitLab',
                str_contains($curto, 'behance.') => 'Behance', str_contains($curto, 'instagram.') => 'Instagram', str_contains($curto, 'lattes.') => 'Lattes',
                str_contains($curto, 'dribbble.') => 'Dribbble', default => 'Site',
            };
            $out[] = ['href' => $href, 'texto' => $curto, 'tipo' => $tipo];
        }
        return $out;
    }

    /** Texto canônico das experiências (o que vai para o perfil e é relido pelo portfólio). */
    public static function serializarExperiencias(array $itens): string {
        $blocos = [];
        foreach ($itens as $x) {
            $b = array_filter([$x['empresa'], $x['cargo'], $x['periodo']], fn($v) => $v !== '');
            foreach ($x['descricao'] as $d) $b[] = '• '.trim_u($d, '•▪►➤- ', 'inicio');
            $blocos[] = implode("\n", $b);
        }
        return implode("\n\n", $blocos);
    }

    /** Texto canônico da formação: "Curso – Instituição (situação)". */
    public static function serializarFormacao(array $itens): string {
        $out = [];
        foreach ($itens as $f) $out[] = implode(' – ', array_filter([$f['curso'], $f['instituicao']])).($f['situacao'] !== '' ? ' ('.$f['situacao'].')' : '');
        return implode("\n", $out);
    }

    // ------------------------------------------------------------------

    /** @return array<int,array{t:string,marcador:bool}> linhas não vazias, sem marcador, lembrando se tinham marcador */
    private static function linhasComMarca(string $t): array {
        $out = [];
        foreach (preg_split('/\R/u', $t) ?: [] as $l) {
            // Marcadores: pontos, traços, setas e qualquer símbolo/emoji no início da linha (📌, ✔, ➤...).
            $marca = (bool)preg_match('/^\s*[•·▪■●◦○►▶➢➤✓✔\-–—*>]/u', $l);
            $l = trim(preg_replace('/^[\s•·▪■●◦○►▶➢➤✓✔\-–—*>\p{So}\p{Co}\x{FE0F}\x{200D}]+/u', '', $l) ?? $l);
            if ($l !== '') $out[] = ['t' => $l, 'marcador' => $marca];
        }
        return $out;
    }

    /** Divide "Cargo – Empresa", "Empresa | Cargo", "Cargo na Empresa" (sem quebrar dentro de parênteses). */
    private static function partes(string $s): array {
        $p = preg_split('/(?:\s+[-–—|]\s+|\s*[–—|]\s*|\s*,\s+(?=[\p{Lu}]))(?![^(]*\))/u', $s) ?: [$s];
        $p = array_values(array_filter(array_map('trim', $p), fn($x) => $x !== ''));
        if (count($p) === 1 && preg_match('/^(.+?)\s+(?:na|no|em)\s+(\p{Lu}.+)$/u', $p[0], $m) && self::ehCargo($m[1])) return [$m[1], $m[2]];
        return $p;
    }

    private static function ehCargo(string $s): bool {
        return (bool)preg_match('/\b('.self::CARGOS.')\b/', Competencias::normalizar($s));
    }

    /**
     * @param string[] $cab linhas do cabeçalho do item (1 linha: é dividida; 2 linhas: empresa e cargo)
     * @return array{0:string,1:string} [empresa, cargo]
     */
    private static function empresaCargo(array $cab): array {
        $cab = array_values(array_filter(array_map('trim', $cab), fn($x) => $x !== ''));
        if (!$cab) return ['', ''];
        $partes = count($cab) === 1 ? self::partes($cab[0]) : $cab;
        if (count($partes) === 1) return self::ehCargo($partes[0]) ? ['', $partes[0]] : [$partes[0], ''];
        [$a, $b] = [$partes[0], implode(' – ', array_slice($partes, 1))];
        // Quem tem palavra de cargo é o cargo; empate: texto todo em maiúsculas costuma ser a empresa.
        $ca = self::ehCargo($a); $cb = self::ehCargo($b);
        if ($ca && !$cb) return [$b, $a];
        if ($cb && !$ca) return [$a, $b];
        $maiA = $a === mb_strtoupper($a); $maiB = $b === mb_strtoupper($b);
        if ($maiB && !$maiA) return [$b, $a];
        return [$a, $b];
    }
}
