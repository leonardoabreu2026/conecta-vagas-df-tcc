<?php
declare(strict_types=1);

/**
 * Herança dos dados extraídos do currículo para o perfil (e daí para portfólio, match,
 * Banco de Talentos e candidaturas, que leem o perfil).
 *
 * Regras:
 *  - campo vazio no perfil → recebe o valor do currículo ("aplicado");
 *  - campo já preenchido → mantém o do candidato ("mantido"), a não ser que ele peça para substituir;
 *    o valor do currículo fica guardado para ele aplicar depois pelo relatório;
 *  - listas (habilidades, competências, informações adicionais) → união sem repetir ("mesclado");
 *  - nome da conta nunca é trocado automaticamente — só oferecido no relatório;
 *  - telefone vai para a conta (usuarios.telefone) quando ela ainda não tem.
 */
final class AplicacaoCurriculo {
    /** coluna do perfil => [rótulo, chave da extração, tipo] (tipo: texto | lista | linhas) */
    public const CAMPOS = [
        'titulo_profissional' => ['Título profissional', 'titulo_profissional', 'texto'],
        'bio' => ['Resumo profissional', 'bio', 'texto'],
        'objetivo' => ['Objetivo', 'objetivo', 'texto'],
        'experiencias' => ['Experiências', 'experiencias', 'texto'],
        'formacao' => ['Formação', 'formacao', 'texto'],
        'cursos_complementares' => ['Cursos complementares', 'cursos', 'texto'],
        'habilidades' => ['Habilidades', 'habilidades', 'lista'],
        'competencias' => ['Competências comportamentais', 'competencias', 'lista'],
        'idiomas' => ['Idiomas', 'idiomas', 'texto'],
        'informacoes_adicionais' => ['Informações adicionais / PCD', 'informacoes_adicionais', 'linhas'],
        'cidade' => ['Cidade / região', 'cidade', 'texto'],
        'uf' => ['UF', 'uf', 'texto'],
        'data_nascimento' => ['Data de nascimento', 'data_nascimento', 'texto'],
        'disponibilidade' => ['Disponibilidade', 'disponibilidade', 'texto'],
        'links' => ['LinkedIn / GitHub / site', 'links', 'linhas'],
        'cnh' => ['CNH (categoria)', 'cnh', 'texto'],
        'pretensao_salarial' => ['Pretensão salarial', 'pretensao_salarial', 'texto'],
        'nivel_experiencia' => ['Nível de experiência', 'nivel_experiencia', 'texto'],
    ];

    /** Colunas do perfil gravadas pelo PerfilDAO (para montar o DTO a partir do perfil atual). */
    private const COLUNAS_DTO = ['id','usuario_id','titulo_profissional','bio','data_nascimento','cidade','uf','nome_fantasia','cnpj','setor','site','habilidades','experiencias','formacao','cursos_complementares','informacoes_adicionais','idiomas','competencias','nivel_experiencia','disponibilidade','objetivo','foto','publico','aceite_lgpd','links','cnh','pretensao_salarial'];

    /**
     * Calcula os novos valores do perfil e o relatório, campo a campo.
     * @param array $p perfil atual (PerfilDAO::buscarPorUsuarioId)
     * @param array $c campos extraídos (ExtracaoCurriculo::extrairCampos)
     * @return array{dados:array,itens:array,pendentes:array}
     */
    public static function calcular(array $p, array $c, bool $substituir): array {
        $dados = self::dtoDe($p);
        $itens = []; $pendentes = [];
        foreach (self::CAMPOS as $col => [$rotulo, $chave, $tipo]) {
            $novo = trim((string)($c[$chave] ?? ''));
            $atual = trim((string)($p[$col] ?? ''));
            // Valores padrão de um perfil recém-criado contam como vazios.
            $padrao = ($col === 'cidade' && $atual === 'Brasília') || ($col === 'uf' && $atual === 'DF' && trim((string)($c['uf'] ?? '')) !== 'DF')
                || ($col === 'nivel_experiencia' && trim((string)($p['experiencias'] ?? '')) === '');
            if ($novo === '') {
                $itens[] = ['campo' => $col, 'rotulo' => $rotulo, 'valor' => '', 'status' => 'nao_encontrado', 'atual' => $atual];
                continue;
            }
            if ($tipo !== 'texto' && $atual !== '' && !$substituir) {
                $unido = $tipo === 'lista'
                    ? ExtracaoCurriculo::juntarLista(self::separar($atual, ','), self::separar($novo, ','))
                    : implode("\n", self::unirLinhas(self::separar($atual, "\n"), self::separar($novo, "\n")));
                $dados[$col] = $unido;
                $itens[] = ['campo' => $col, 'rotulo' => $rotulo, 'valor' => $novo, 'status' => $unido === $atual ? 'igual' : 'mesclado', 'atual' => $atual];
                continue;
            }
            if (self::iguais($novo, $atual)) { $itens[] = ['campo' => $col, 'rotulo' => $rotulo, 'valor' => $novo, 'status' => 'igual', 'atual' => $atual]; continue; }
            if ($atual === '' || $padrao || $substituir) {
                $dados[$col] = $novo;
                $itens[] = ['campo' => $col, 'rotulo' => $rotulo, 'valor' => $novo, 'status' => 'aplicado', 'atual' => $atual];
            } else {
                $pendentes[$col] = $novo;
                $itens[] = ['campo' => $col, 'rotulo' => $rotulo, 'valor' => $novo, 'status' => 'mantido', 'atual' => $atual];
            }
        }
        return ['dados' => $dados, 'itens' => $itens, 'pendentes' => $pendentes];
    }

    /** Dados do DTO a partir do perfil atual (para salvar sem perder nada). */
    public static function dtoDe(array $p): array {
        $d = [];
        foreach (self::COLUNAS_DTO as $k) $d[$k] = $p[$k] ?? null;
        $d['aceite_lgpd'] = 1;
        $d['publico'] = (int)($p['publico'] ?? 1);
        $d['nivel_experiencia'] = $p['nivel_experiencia'] ?? 'junior';
        return $d;
    }

    public static function salvar(array $dados): bool {
        return (new PerfilDAO())->salvar(new PerfilDTO($dados));
    }

    /**
     * Salva a foto encontrada no arquivo (JPG/PNG validado pelo conteúdo) e devolve o caminho relativo.
     * @param array{dados:string,ext:string} $foto
     */
    public static function salvarFoto(array $foto, int $usuarioId): ?string {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($foto['dados']) ?: '';
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if ($ext === null || strlen($foto['dados']) > 3 * 1024 * 1024 || !@getimagesizefromstring($foto['dados'])) return null;
        $nome = 'foto_'.$usuarioId.'_'.bin2hex(random_bytes(5)).'.'.$ext;
        if (@file_put_contents(UPLOAD_DIR.$nome, $foto['dados']) === false) return null;
        return 'assets/uploads/'.$nome;
    }

    /** Texto curto para exibir no relatório. */
    public static function resumo(string $v, int $max = 140): string {
        $v = trim(preg_replace('/\s*\n+\s*(?:[•▪\-]\s*)?/u', ' · ', $v) ?? $v);
        return mb_strlen($v) > $max ? rtrim(mb_substr($v, 0, $max - 1)).'…' : $v;
    }

    private static function separar(string $t, string $sep): array {
        $partes = $sep === ',' ? (preg_split('/[\n;]+|,(?![^(]*\))/u', $t) ?: []) : (preg_split('/\R/u', $t) ?: []);
        return array_values(array_filter(array_map('trim', $partes), fn($x) => $x !== ''));
    }

    /** União de linhas sem repetir (comparação sem acento/caixa). */
    private static function unirLinhas(array ...$listas): array {
        $vistos = []; $out = [];
        foreach ($listas as $l) foreach ($l as $x) { $k = Competencias::normalizar($x); if ($k === '' || isset($vistos[$k])) continue; $vistos[$k] = true; $out[] = $x; }
        return $out;
    }

    private static function iguais(string $a, string $b): bool {
        return Competencias::normalizar($a) === Competencias::normalizar($b);
    }
}
