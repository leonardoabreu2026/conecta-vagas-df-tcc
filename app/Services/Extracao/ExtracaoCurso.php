<?php
declare(strict_types=1);

/**
 * Extração de cursos: transforma o texto de divulgação de um curso nos campos do cadastro.
 * O resultado só preenche o formulário — o administrador revisa antes de salvar.
 */
final class ExtracaoCurso {
    private const INSTITUICOES = [
        'Fundação Bradesco – Escola Virtual' => ['fundacao bradesco','escola virtual bradesco','ev org br'],
        'Escola Virtual do Governo' => ['escola virtual do governo','escola virtual gov','escolavirtual gov','enap','evg'],
        'SEBRAE' => ['sebrae'], 'SENAI' => ['senai'], 'SENAC' => ['senac'], 'SESI' => ['sesi'], 'SENAR' => ['senar'],
        'Google Grow' => ['google grow','grow google','google ateliê digital','google'],
        'FGV Online' => ['fgv'], 'Microsoft Learn' => ['microsoft learn','microsoft'], 'Cisco Networking Academy' => ['cisco'],
        'Coursera' => ['coursera'], 'Udemy' => ['udemy'], 'Alura' => ['alura'], 'Fundação Estudar' => ['fundacao estudar'],
        'IFB – Instituto Federal de Brasília' => ['ifb','instituto federal de brasilia'], 'Senac EAD' => ['senac ead'],
        'Prime Cursos' => ['prime cursos'], 'Cursa' => ['cursa'], 'Kultivi' => ['kultivi'], 'YouTube' => ['youtube'],
    ];

    private const CATEGORIAS = [
        'Informática e Excel' => ['Excel','Pacote Office','Informática','PHP','JavaScript','React','HTML e CSS','Banco de dados SQL','Python','Java','Git','Desenvolvimento de software','Suporte técnico de TI'],
        'Empreendedorismo e Gestão' => ['Empreendedorismo','Gestão e liderança'],
        'Administração e Atendimento' => ['Rotinas administrativas','Administração','Atendimento ao cliente','Vendas','Recursos Humanos','Saúde e cuidados','Enfermagem','Telemarketing','Comunicação'],
        'Marketing, Dados e UX' => ['Marketing digital','Análise de dados','UX e design'],
        'Negócios, Finanças e ESG' => ['Finanças','Contabilidade','ESG e sustentabilidade'],
    ];

    /** @return array<string,mixed> */
    public static function doTexto(string $texto): array {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
        $r = ['titulo'=>'','descricao'=>'','tipo'=>'curso','modalidade'=>'ead','nivel'=>'iniciante','duracao'=>'','gratuito'=>1,'preco'=>null,
              'url'=>'','instituicao'=>'','categoria'=>'','competencias'=>[]];
        if ($texto === '') return $r;
        $n = Competencias::normalizar($texto);
        $linhas = array_values(array_filter(array_map(fn($l) => trim_u($l, " \t*_•·-–—>"), explode("\n", $texto)), fn($l) => $l !== ''));

        if (preg_match('/https?:\/\/[^\s<>"\')]+/i', $texto, $m)) $r['url'] = rtrim($m[0], '.,;');
        foreach ($linhas as $l) {
            if (preg_match('/^(?:curso|t[ií]tulo|nome do curso)\s*:\s*(.+)$/iu', $l, $m)) { $r['titulo'] = trim($m[1]); break; }
        }
        if ($r['titulo'] === '') {
            foreach ($linhas as $l) if (!preg_match('/https?:|@|r\$/i', $l) && mb_strlen($l) <= 100) { $r['titulo'] = $l; break; }
        }
        if ($r['titulo'] !== '' && $r['titulo'] === mb_strtoupper($r['titulo'])) $r['titulo'] = mb_convert_case(mb_strtolower($r['titulo']), MB_CASE_TITLE, 'UTF-8');

        foreach ($linhas as $l) if (preg_match('/^(?:institui[cç][aã]o|oferecido por|promovido por|realiza[cç][aã]o|plataforma)\s*:\s*(.+)$/iu', $l, $m)) { $r['instituicao'] = trim($m[1]); break; }
        if ($r['instituicao'] === '') {
            $busca = ' '.$n.' '.Competencias::normalizar($r['url']).' ';
            foreach (self::INSTITUICOES as $nome => $chaves) {
                foreach ($chaves as $c) if (str_contains($busca, ' '.$c.' ')) { $r['instituicao'] = $nome; break 2; }
            }
        }

        if (preg_match('/r\$\s*(\d{1,3}(?:\.\d{3})*(?:,\d{2})?|\d+(?:,\d{2})?)/iu', $texto, $m) && !preg_match('/\b(gratuito|gratis|gratuita|free|sem custo)\b/', $n)) {
            $r['preco'] = decimal_ou_null($m[1]); $r['gratuito'] = $r['preco'] ? 0 : 1;
        }
        if (preg_match('/(\d+)\s*(h\b|horas?|hrs?|semanas?|meses|m[eê]s|dias?|aulas?|m[oó]dulos?)/iu', $texto, $m)) {
            $u = mb_strtolower($m[2]);
            $r['duracao'] = $m[1].' '.(str_starts_with($u, 'h') ? 'horas' : $u);
        }
        $r['modalidade'] = match (true) {
            (bool)preg_match('/\b(hibrido|hibrida|semipresencial)\b/', $n) => 'hibrido',
            (bool)preg_match('/\bpresencial\b/', $n) && !preg_match('/\b(ead|online|a distancia)\b/', $n) => 'presencial',
            default => 'ead',
        };
        $r['nivel'] = match (true) {
            (bool)preg_match('/\b(avancado|avancada|especialista)\b/', $n) => 'avancado',
            (bool)preg_match('/\b(intermediario|intermediaria)\b/', $n) => 'intermediario',
            default => 'iniciante',
        };
        $r['tipo'] = match (true) {
            (bool)preg_match('/\b(e book|ebook|livro digital|apostila)\b/', $n) => 'ebook',
            (bool)preg_match('/\b(video|videoaula|youtube|webinar|live)\b/', $n) && !preg_match('/\bcurso\b/', $n) => 'video',
            default => 'curso',
        };
        $tituloN = Competencias::normalizar($r['titulo']);
        $desc = array_filter($linhas, fn($l) => Competencias::normalizar($l) !== $tituloN && !preg_match('/^https?:/i', $l));
        $r['descricao'] = implode("\n", $desc);
        $r['competencias'] = Competencias::doCurso($r);
        $r['categoria'] = self::categoria($r['competencias']);
        return $r;
    }

    public static function categoria(array $competencias): string {
        $melhor = ''; $max = 0;
        foreach (self::CATEGORIAS as $cat => $lista) {
            $q = count(array_intersect($competencias, $lista));
            if ($q > $max) { $max = $q; $melhor = $cat; }
        }
        return $melhor;
    }
}
