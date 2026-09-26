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

        $r['url'] = self::primeiroLink($texto);
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

    /** Áreas (categorias de curso) do database/seed.sql — usadas no prompt quando o banco ainda não tem categorias. */
    public const AREAS = [
        'Informática e Excel', 'Empreendedorismo e Gestão', 'Administração e Atendimento', 'Marketing, Dados e UX',
        'Negócios, Finanças e ESG', 'Tecnologia e Inteligência Artificial', 'Carreira e Empregabilidade',
    ];

    /**
     * As capas de public/assets/img/cursos são banners COM A MARCA da instituição (Fundação Bradesco, SEBRAE,
     * Escola Virtual Gov, Google, FGV). Por isso a capa segue a instituição, nunca a área: um e-book do
     * Banco Central não pode sair com o banner da FGV. Instituição sem banner → sem capa (o cartão mostra o
     * ícone do formato). A ordem importa: "Fundação Bradesco – Escola Virtual" é Bradesco, não a Escola Virtual Gov.
     */
    private const CAPAS_INSTITUICAO = [
        'bradesco|ev org br' => 'assets/img/cursos/curso1.png',
        'sebrae' => 'assets/img/cursos/capas/sebrae.jpg',   // imagem oficial do SEBRAE (a curso2.png tinha marca d'água de outro site)
        'escola virtual gov|escola virtual do governo|escolavirtual gov|enap|evg' => 'assets/img/cursos/curso3.png',
        'google' => 'assets/img/cursos/curso4.png',
        'fgv' => 'assets/img/cursos/curso5.png',
    ];

    /** Capa com a marca da instituição (ou '' quando não há banner dela). Os banners anunciam "cursos": e-book e vídeo ficam com a capa do formato. */
    public static function capa(string $instituicao, string $url = '', string $tipo = 'curso'): string {
        if ($tipo !== 'curso') return '';
        $busca = ' '.Competencias::normalizar($instituicao).' ';
        $link = ' '.Competencias::normalizar(preg_replace('#^https?://(www\.)?#i', '', $url) ?? '').' ';
        foreach (self::CAPAS_INSTITUICAO as $chaves => $img) {
            foreach (explode('|', $chaves) as $c) if (str_contains($busca, ' '.$c.' ') || str_contains($link, ' '.$c.' ')) return $img;
        }
        return '';
    }

    /**
     * Primeiro link http(s) do texto. Aceita parênteses no endereço quando estão em par
     * (ex.: ".../Cartilha%20(2)%20(1).pdf" — antes o link era cortado no primeiro ")" e ficava quebrado),
     * mas não leva junto o ")" de quem escreveu o link entre parênteses nem a pontuação do fim da frase.
     */
    public static function primeiroLink(string $texto): string {
        if (!preg_match('/https?:\/\/[^\s<>"\'\]]+/i', $texto, $m)) return '';
        $u = $m[0];
        while ($u !== '') {
            $ultimo = substr($u, -1);
            if (strpbrk($ultimo, '.,;:!?') !== false) { $u = substr($u, 0, -1); continue; }
            if ($ultimo === ')' && substr_count($u, '(') < substr_count($u, ')')) { $u = substr($u, 0, -1); continue; }
            break;
        }
        return $u;
    }

    /** Rótulos aceitos nas fichas (com ou sem acento, maiúsculas ou não) → campo. */
    private const ROTULOS = [
        'titulo' => 'titulo|nome|nome do curso|curso|e ?book',
        'tipo' => 'tipo|formato',
        'instituicao' => 'instituicao|plataforma|oferecido por|realizacao',
        'modalidade' => 'modalidade',
        'cidade' => 'cidade|local|cidade uf',
        'nivel' => 'nivel',
        'duracao' => 'carga horaria|duracao',
        'gratuito' => 'gratuito|gratis|custo',
        'preco' => 'preco|valor',
        'url' => 'link|link oficial|url|site|endereco',
        'categoria' => 'area|categoria',
        'descricao' => 'descricao|resumo|sobre',
        'imagem' => 'imagem|imagem da capa|imagem de capa|capa|link da imagem|url da imagem|foto|banner',
    ];

    /**
     * IMPORTAÇÃO EM LOTE: lê várias fichas coladas de uma vez (resposta do prompt de pesquisa, ex.: Perplexity).
     * Fichas separadas por uma linha "---" (ou por um novo "Título:"). Cada ficha passa pela extração normal
     * (doTexto) e os campos rotulados da ficha têm prioridade. Nada é gravado aqui.
     * @param string[] $categorias nomes das categorias de curso cadastradas
     * @return array<int,array<string,mixed>>
     */
    public static function fichas(string $texto, array $categorias = []): array {
        $texto = trim(str_replace(["\r\n", "\r"], "\n", $texto));
        if ($texto === '') return [];
        // Tira a formatação de Markdown que as IAs costumam usar (negrito, títulos, listas, links [texto](url)).
        // O link do Markdown pode ter parênteses em par no endereço: [pdf](https://site/arquivo%20(2).pdf).
        $texto = preg_replace(['/\*\*|__/u', '/^\s*#{1,6}\s*/mu', '/\[([^\]]*)\]\((https?:\/\/(?:[^()\s]|\([^()\s]*\))+)\)/u', '/\[\d+\]/u'], ['', '', '$2', ''], $texto) ?? $texto;
        $blocos = preg_split('/^\s*(?:-{3,}|={3,}|_{3,})\s*$/mu', $texto) ?: [];
        if (count($blocos) === 1) $blocos = preg_split('/\n(?=\s*(?:\d+[.)]\s*)?t[ií]tulo\s*:)/iu', $texto) ?: [$texto];
        $out = [];
        foreach ($blocos as $b) {
            $b = trim($b);
            if ($b === '' || !preg_match('/\S+\s*:\s*\S/u', $b)) continue;
            $campos = self::camposRotulados($b);
            if (($campos['titulo'] ?? '') === '' && ($campos['url'] ?? '') === '') continue; // texto solto (introdução/conclusão da IA)
            $out[] = self::daFicha($b, $campos, $categorias);
        }
        return $out;
    }

    /** "Título: X" → ['titulo' => 'X']; linhas sem rótulo conhecido continuam o campo anterior (descrição longa). */
    private static function camposRotulados(string $bloco): array {
        $campos = []; $atual = null;
        foreach (explode("\n", $bloco) as $l) {
            $l = trim_u(trim($l), " \t*•·-–—>");
            if ($l === '') continue;
            $achou = null;
            if (preg_match('/^(?:\d+[.)]\s*)?([\p{L} \/]{2,30}?)\s*:\s*(.*)$/u', $l, $m)) {
                $rot = Competencias::normalizar($m[1]);
                foreach (self::ROTULOS as $campo => $rx) if (preg_match('/^(?:'.$rx.')$/', $rot)) { $achou = $campo; break; }
            }
            if ($achou) { $atual = $achou; $campos[$atual] = trim($m[2]); }
            elseif ($atual === 'descricao') $campos['descricao'] .= ' '.$l;
        }
        return $campos;
    }

    private static function daFicha(string $bloco, array $c, array $categorias): array {
        $r = self::doTexto($bloco); // extração normal: preenche o que a ficha não disser com clareza
        $n = fn(string $k) => Competencias::normalizar($c[$k] ?? '');
        if (($c['titulo'] ?? '') !== '') $r['titulo'] = mb_substr(trim($c['titulo'], ' "\''), 0, 255);
        if (($c['instituicao'] ?? '') !== '') $r['instituicao'] = mb_substr($c['instituicao'], 0, 255);
        if (($link = self::primeiroLink($c['url'] ?? '')) !== '') $r['url'] = $link;
        if ($n('tipo') !== '') $r['tipo'] = preg_match('/e ?book|livro|apostila|guia|pdf/', $n('tipo')) ? 'ebook' : (preg_match('/video|webinar|aula gravada/', $n('tipo')) ? 'video' : 'curso');
        if ($n('modalidade') !== '') $r['modalidade'] = preg_match('/hibrid|semipresencial/', $n('modalidade')) ? 'hibrido' : (preg_match('/^presencial/', $n('modalidade')) ? 'presencial' : 'ead');
        if ($n('nivel') !== '') $r['nivel'] = preg_match('/avanc/', $n('nivel')) ? 'avancado' : (preg_match('/intermed/', $n('nivel')) ? 'intermediario' : 'iniciante');
        if (($c['duracao'] ?? '') !== '' && !preg_match('/^(nao informad|n\/?a|-)/i', $n('duracao'))) $r['duracao'] = mb_substr($c['duracao'], 0, 50);
        $preco = decimal_ou_null(preg_replace('/[^\d.,]/', '', $c['preco'] ?? '') ?? '');
        if ($n('gratuito') !== '') $r['gratuito'] = preg_match('/^(sim|gratuito|gratis|free|0)/', $n('gratuito')) ? 1 : 0;
        elseif ($preco !== null && $preco > 0) $r['gratuito'] = 0;
        $r['preco'] = $r['gratuito'] ? null : ($preco ?: $r['preco']);
        // Descrição: a da ficha + o local, quando for presencial (o cadastro não tem campo de cidade).
        $desc = trim($c['descricao'] ?? '') !== '' ? trim($c['descricao']) : '';
        $cidade = trim($c['cidade'] ?? '');
        if ($cidade !== '' && $r['modalidade'] !== 'ead' && !preg_match('/^(online|ead|nao se aplica|n\/?a|-)/i', Competencias::normalizar($cidade))) $desc .= ($desc !== '' ? "\n" : '').'Local: '.$cidade.'.';
        if ($desc !== '') $r['descricao'] = $desc;
        // Área: a da ficha se for uma categoria cadastrada; senão, a sugerida pelas competências.
        $r['competencias'] = Competencias::doCurso($r);
        $r['categoria'] = '';
        foreach ($categorias as $cat) if (Competencias::normalizar($cat) === $n('categoria')) $r['categoria'] = $cat;
        if ($r['categoria'] === '') $r['categoria'] = self::categoria($r['competencias']);
        $r['imagem'] = self::capa($r['instituicao'], $r['url'], $r['tipo']);   // banner da instituição (reserva)
        // Imagem que a pesquisa trouxe (capa do e-book / imagem do curso): só o link; baixa ao cadastrar (ImagemRemota).
        $r['imagem_url'] = self::primeiroLink($c['imagem'] ?? '');
        return $r;
    }

    /**
     * Prompt para a IA de pesquisa (Perplexity, ChatGPT…): pede cursos/e-books reais, gratuitos ou baratos,
     * no formato de ficha que fichas() lê. Usa as categorias de curso cadastradas no sistema.
     */
    public static function promptPesquisa(array $categorias, int $quantidade = 20): string {
        $areas = $categorias ? implode(' | ', $categorias) : implode(' | ', self::AREAS);
        return <<<TXT
Você é um pesquisador de oportunidades de capacitação profissional para uma plataforma de empregos do Distrito Federal (Brasil) chamada Conecta Vagas DF. O público são pessoas que procuram emprego, muitas no primeiro emprego.

TAREFA: pesquise na internet e liste {$quantidade} cursos e e-books REAIS, GRATUITOS (ou de baixo custo) e em português, que ajudem a conseguir emprego. Distribua entre estas áreas: {$areas}. Inclua pelo menos 5 e-books (guias ou livros digitais gratuitos para baixar) e, se houver, cursos presenciais gratuitos no Distrito Federal (SENAI, SENAC, SESI, IFB, Agência do Trabalhador, GDF).

REGRAS:
1. Só use fontes oficiais (site da instituição) e confira que o link abre a página do curso ou do e-book — não invente links.
2. Prefira instituições conhecidas: Fundação Bradesco (Escola Virtual), Escola Virtual.Gov (ENAP), SEBRAE, SENAI, SENAC, SESI, IFB, Google (Grow), Microsoft Learn, FGV, Fundação Estudar, Cisco.
3. Não repita cursos. Descrição curta e objetiva, sem propaganda.
4. IMAGEM (obrigatória — sem ela o item não é cadastrado): informe o endereço DIRETO de uma imagem oficial que identifique o item, terminando em .jpg, .jpeg, .png ou .webp. No e-book, a imagem da CAPA (miniatura da capa na página de download); no curso, a imagem de divulgação da página do próprio curso. Nunca use logotipo genérico, ícone ou imagem de outro site. Se não encontrar, escreva: Não encontrada.
5. Responda SOMENTE com as fichas abaixo, sem introdução, sem conclusão, sem tabela e sem negrito. Separe cada ficha com uma linha contendo apenas ---

FORMATO DE CADA FICHA (copie os rótulos exatamente assim):
Título: nome oficial do curso ou e-book
Tipo: Curso | E-book | Vídeo
Instituição: quem oferece
Modalidade: EAD | Presencial | Híbrido
Cidade: cidade/UF (só se for presencial ou híbrido; se for EAD escreva Online)
Nível: Iniciante | Intermediário | Avançado
Carga horária: ex.: 20 horas (ou Não informado)
Gratuito: Sim | Não
Preço: ex.: R\$ 49,90 (só se não for gratuito)
Área: uma destas: {$areas}
Link: endereço oficial completo, começando com https://
Imagem: endereço direto da imagem da capa (e-book) ou da imagem do curso, começando com https://
Descrição: 1 ou 2 frases dizendo o que a pessoa aprende e se tem certificado
---
TXT;
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
