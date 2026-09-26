<?php
declare(strict_types=1);

/**
 * PESQUISA GUIADA DE CURSOS E E-BOOKS: de onde vêm os links dos novos cadastros.
 *
 * O fluxo (tela admin/pages/cursos.php, "Importar vários de uma vez"):
 *  1. o administrador escolhe o formato, a área e (opcional) a fonte — por padrão a pesquisa mira
 *     as áreas com MENOS conteúdo (lacunas);
 *  2. prompt() monta o pedido para a IA de pesquisa (Perplexity, ChatGPT com busca): só fontes
 *     oficiais desta lista, pesquisa por "site:domínio", e a lista dos links JÁ CADASTRADOS para não repetir;
 *  3. a resposta volta em fichas (formato de ExtracaoCurso::fichas) e passa pela prévia antes de salvar.
 *
 * FONTES: instituições oficiais, com certificado e conteúdo gratuito. 'catalogo' é o ponto de partida
 * (página de lista de cursos/materiais); 'dominios' serve para a pesquisa "site:" e para reconhecer
 * a instituição pelo link (nome padronizado, sem variações como "Fundação Bradesco - Escola Virtual").
 */
final class FontesCursos {
    public const FONTES = [
        'bradesco' => ['nome' => 'Fundação Bradesco – Escola Virtual', 'dominios' => ['ev.org.br'], 'catalogo' => 'https://www.ev.org.br/cursos',
            'formatos' => ['curso'], 'areas' => 'Informática e Excel, Administração, Finanças, Tecnologia', 'dica' => 'cursos EAD gratuitos com certificado; imagem de divulgação na página do curso'],
        'enap' => ['nome' => 'Escola Virtual.Gov (Enap)', 'dominios' => ['escolavirtual.gov.br'], 'catalogo' => 'https://www.escolavirtual.gov.br/catalogo',
            'formatos' => ['curso'], 'areas' => 'Tecnologia e IA, Gestão, Dados, Carreira', 'dica' => 'cursos gratuitos do governo federal com certificado; aceita trilhas da Microsoft e da RNP'],
        'sebrae' => ['nome' => 'SEBRAE', 'dominios' => ['sebrae.com.br'], 'catalogo' => 'https://sebrae.com.br/sites/PortalSebrae/cursosonline',
            'formatos' => ['curso', 'ebook'], 'areas' => 'Empreendedorismo e Gestão, Marketing, Finanças', 'dica' => 'cursos online gratuitos e e-books/guias para baixar'],
        'google' => ['nome' => 'Google Grow', 'dominios' => ['grow.google', 'skillshop.withgoogle.com'], 'catalogo' => 'https://grow.google/intl/pt-br/',
            'formatos' => ['curso', 'video'], 'areas' => 'Marketing digital, Dados, IA, Carreira', 'dica' => 'cursos e aulas gratuitas do Google em português'],
        'microsoft' => ['nome' => 'Microsoft Learn', 'dominios' => ['learn.microsoft.com'], 'catalogo' => 'https://learn.microsoft.com/pt-br/training/browse/',
            'formatos' => ['curso'], 'areas' => 'Tecnologia e IA, Informática e Excel, Dados', 'dica' => 'módulos e roteiros de aprendizagem gratuitos em português'],
        'fgv' => ['nome' => 'FGV Online', 'dominios' => ['educacao-executiva.fgv.br', 'fgv.br'], 'catalogo' => 'https://educacao-executiva.fgv.br/cursos/gratuitos',
            'formatos' => ['curso'], 'areas' => 'Negócios, Finanças e ESG, Gestão, Carreira', 'dica' => 'cursos gratuitos de curta duração com certificado'],
        'ifb' => ['nome' => 'IFB – Instituto Federal de Brasília', 'dominios' => ['ifb.edu.br'], 'catalogo' => 'https://www.ifb.edu.br',
            'formatos' => ['curso'], 'areas' => 'Cursos presenciais gratuitos no DF (FIC, técnicos)', 'dica' => 'editais de cursos FIC gratuitos nos campi do DF; informe o campus na Cidade'],
        'senai' => ['nome' => 'SENAI', 'dominios' => ['senai.br', 'senaidf.com.br', 'portaldaindustria.com.br'], 'catalogo' => 'https://www.portaldaindustria.com.br/senai/',
            'formatos' => ['curso'], 'areas' => 'Tecnologia, Indústria, Informática', 'dica' => 'cursos EAD gratuitos e cursos presenciais no DF'],
        'senac' => ['nome' => 'SENAC', 'dominios' => ['senac.br', 'ead.senac.br'], 'catalogo' => 'https://www.ead.senac.br/',
            'formatos' => ['curso'], 'areas' => 'Administração e Atendimento, Comércio, Saúde', 'dica' => 'cursos livres gratuitos (EAD) e vagas do Programa Senac de Gratuidade no DF'],
        'bcb' => ['nome' => 'Banco Central do Brasil', 'dominios' => ['bcb.gov.br'], 'catalogo' => 'https://www.bcb.gov.br/cidadaniafinanceira',
            'formatos' => ['ebook', 'curso'], 'areas' => 'Negócios, Finanças e ESG', 'dica' => 'cadernos de educação financeira (PDF) com capa'],
        'certbr' => ['nome' => 'CERT.br / NIC.br', 'dominios' => ['cartilha.cert.br', 'cert.br', 'nic.br'], 'catalogo' => 'https://cartilha.cert.br/',
            'formatos' => ['ebook'], 'areas' => 'Tecnologia e Inteligência Artificial', 'dica' => 'cartilha e fascículos de segurança na internet (PDF) com capa'],
        'febraban' => ['nome' => 'Febraban – Meu Bolso em Dia', 'dominios' => ['meubolsoemdia.com.br'], 'catalogo' => 'https://meubolsoemdia.com.br/',
            'formatos' => ['ebook', 'curso'], 'areas' => 'Negócios, Finanças e ESG', 'dica' => 'e-books e cursos gratuitos de finanças pessoais'],
        'mte' => ['nome' => 'Ministério do Trabalho e Emprego', 'dominios' => ['gov.br/trabalho-e-emprego'], 'catalogo' => 'https://www.gov.br/trabalho-e-emprego/pt-br',
            'formatos' => ['ebook'], 'areas' => 'Carreira e Empregabilidade (direitos trabalhistas)', 'dica' => 'cartilhas oficiais (PDF): carteira de trabalho digital, seguro-desemprego, direitos'],
        'educapes' => ['nome' => 'eduCAPES', 'dominios' => ['educapes.capes.gov.br'], 'catalogo' => 'https://educapes.capes.gov.br/',
            'repositorio' => true, 'formatos' => ['ebook', 'video'], 'areas' => 'Todas (livros e apostilas de universidades públicas)', 'dica' => 'livros digitais e vídeo-aulas abertos de universidades e institutos federais'],
        'cvm' => ['nome' => 'CVM – Portal do Investidor', 'dominios' => ['gov.br/investidor', 'gov.br/cvm'], 'catalogo' => 'https://www.gov.br/investidor/pt-br',
            'formatos' => ['ebook', 'curso'], 'areas' => 'Negócios, Finanças e ESG', 'dica' => 'cadernos e cursos gratuitos de educação financeira'],
        'cisco' => ['nome' => 'Cisco Networking Academy', 'dominios' => ['netacad.com'], 'catalogo' => 'https://www.netacad.com/pt',
            'formatos' => ['curso'], 'areas' => 'Tecnologia (redes, cibersegurança, programação)', 'dica' => 'cursos introdutórios gratuitos, vários em português'],
        'estudar' => ['nome' => 'Fundação Estudar', 'dominios' => ['estudar.org.br'], 'catalogo' => 'https://www.estudar.org.br/',
            'formatos' => ['curso', 'ebook'], 'areas' => 'Carreira e Empregabilidade', 'dica' => 'cursos e guias gratuitos de carreira, currículo e entrevista'],
    ];

    /** Rótulos dos formatos no prompt. */
    private const FORMATOS = ['curso' => 'cursos', 'ebook' => 'e-books (guias, cartilhas ou livros digitais gratuitos para baixar)', 'video' => 'vídeos/aulas gravadas'];

    /** Host sem "www." (+ caminho, para fontes como gov.br/trabalho-e-emprego). */
    private static function endereco(string $url): string {
        $p = parse_url(trim($url)) ?: [];
        return strtolower(preg_replace('/^www\./', '', (string)($p['host'] ?? '')).rtrim((string)($p['path'] ?? ''), '/'));
    }

    /** Chave da fonte a que o link pertence ('' = fonte fora do catálogo). O domínio mais específico vence. */
    public static function fonteDoLink(string $url): string {
        $end = self::endereco($url);
        if ($end === '') return '';
        $melhor = ''; $tam = 0;
        foreach (self::FONTES as $k => $f) {
            foreach ($f['dominios'] as $d) {
                // Host igual ou subdomínio ("sp.senai.br" é do "senai.br"); domínio com caminho ("gov.br/investidor") confere o caminho também.
                [$dHost, $dCaminho] = array_pad(explode('/', $d, 2), 2, '');
                [$host, $caminho] = array_pad(explode('/', $end, 2), 2, '');
                $ok = ($host === $dHost || str_ends_with($host, '.'.$dHost)) && ($dCaminho === '' || $caminho === $dCaminho || str_starts_with($caminho, $dCaminho.'/'));
                if ($ok && strlen($d) > $tam) { $melhor = $k; $tam = strlen($d); }
            }
        }
        return $melhor;
    }

    /**
     * Nome padronizado da instituição pelo link oficial. Mantém o nome informado quando o link não é de uma
     * fonte conhecida ou é de um REPOSITÓRIO (eduCAPES publica material de outras instituições: o autor fica).
     * Parceria em parênteses continua: "Enap - Escola Virtual.Gov (conteúdo Microsoft)" → "Escola Virtual.Gov (Enap) (conteúdo Microsoft)".
     */
    public static function nomeOficial(string $instituicao, string $url): string {
        $instituicao = trim($instituicao);
        $k = self::fonteDoLink($url);
        if ($k === '' || (!empty(self::FONTES[$k]['repositorio']) && $instituicao !== '')) return $instituicao;
        $parceria = preg_match('/\((conte[úu]do [^()]+)\)\s*$/iu', $instituicao, $m) ? ' ('.$m[1].')' : '';
        return self::FONTES[$k]['nome'].$parceria;
    }

    /**
     * Cobertura do que já está cadastrado: por área e formato, e por fonte.
     * @param list<array<string,mixed>> $cursos todos os conteúdos (CursoDAO::listar(false))
     * @param list<string> $areas nomes das categorias de curso ativas
     * @return array{areas:array<string,array<string,int>>, fontes:array<string,int>, lacunas:list<string>}
     */
    public static function cobertura(array $cursos, array $areas): array {
        $porArea = [];
        foreach ($areas as $a) $porArea[$a] = ['curso' => 0, 'ebook' => 0, 'video' => 0, 'total' => 0];
        $porFonte = array_fill_keys(array_keys(self::FONTES), 0);
        foreach ($cursos as $c) {
            $a = (string)($c['categoria_nome'] ?? '');
            if (isset($porArea[$a])) { $porArea[$a][$c['tipo']] = ($porArea[$a][$c['tipo']] ?? 0) + 1; $porArea[$a]['total']++; }
            $f = self::fonteDoLink((string)($c['url'] ?? ''));
            if ($f !== '') $porFonte[$f]++;
        }
        // Lacunas: as áreas com menos conteúdo (a metade de baixo), da menor para a maior.
        $ordem = $porArea;
        uasort($ordem, fn($x, $y) => $x['total'] <=> $y['total']);
        $lacunas = array_slice(array_keys($ordem), 0, max(1, (int)ceil(count($ordem) / 2)));
        return ['areas' => $porArea, 'fontes' => $porFonte, 'lacunas' => $lacunas];
    }

    /**
     * Prompt direcionado para a IA de pesquisa.
     * @param array{formato?:string,area?:string,fonte?:string,quantidade?:int} $op formato '' = misto; área '' = lacunas
     * @param list<string> $areas categorias de curso ativas (os nomes que a ficha deve usar em "Área")
     * @param list<string> $lacunas áreas com menos conteúdo (cobertura()['lacunas'])
     * @param list<string> $jaCadastrados links já cadastrados (não repetir)
     */
    public static function prompt(array $op, array $areas, array $lacunas = [], array $jaCadastrados = []): string {
        $areas = $areas ?: ExtracaoCurso::AREAS;
        $qtd = max(5, min(40, (int)($op['quantidade'] ?? 20)));
        $formato = (string)($op['formato'] ?? '');
        $area = in_array($op['area'] ?? '', $areas, true) ? (string)$op['area'] : '';
        $fonte = isset(self::FONTES[$op['fonte'] ?? '']) ? (string)$op['fonte'] : '';

        $oQue = isset(self::FORMATOS[$formato]) ? self::FORMATOS[$formato] : 'cursos e e-books (pelo menos 5 e-books: guias ou livros digitais gratuitos para baixar)';
        $foco = $area !== '' ? "Todos da área \"{$area}\"." : ($lacunas ? 'Priorize as áreas com MENOS conteúdo na plataforma: '.implode(', ', $lacunas).'. Pode completar com as demais áreas.' : 'Distribua entre as áreas.');

        $fontes = $fonte !== '' ? [$fonte => self::FONTES[$fonte]] : array_filter(self::FONTES, fn($f) => $formato === '' || in_array($formato, $f['formatos'], true));
        if (!$fontes) $fontes = self::FONTES;
        $listaFontes = '';
        foreach ($fontes as $f) $listaFontes .= '- '.$f['nome'].' — comece por '.$f['catalogo'].' (pesquise também com site:'.$f['dominios'][0].'); '.$f['dica'].".\n";
        $ondeBuscar = $fonte !== '' ? "Pesquise SOMENTE nesta fonte oficial:\n".$listaFontes : "Pesquise nestas fontes oficiais (use o catálogo e a busca site:domínio). Outras instituições públicas ou reconhecidas só se o link for do site oficial delas:\n".$listaFontes;

        $jaCadastrados = array_values(array_unique(array_filter(array_map('trim', $jaCadastrados))));
        $excluir = $jaCadastrados ? "\nJÁ CADASTRADOS (NÃO repita nenhum destes links nem o mesmo conteúdo com outro link):\n".implode("\n", array_slice($jaCadastrados, 0, 150))."\n" : '';
        $ficha = PromptsPesquisa::formatoFicha($areas);   // os mesmos rótulos do prompt mestre e do formulário

        return <<<TXT
Você é um pesquisador de oportunidades de capacitação profissional para uma plataforma de empregos do Distrito Federal (Brasil) chamada Conecta Vagas DF. O público são pessoas que procuram emprego, muitas no primeiro emprego.

TAREFA: pesquise na internet e liste {$qtd} {$oQue} REAIS, GRATUITOS (ou de baixo custo) e em português, que ajudem a conseguir emprego. {$foco} Se houver, inclua cursos presenciais gratuitos no Distrito Federal.

ONDE PESQUISAR:
{$ondeBuscar}
REGRAS:
1. Só links do site oficial da instituição, abrindo a página do próprio curso ou e-book (não a página inicial nem o resultado de busca). Abra o link antes de responder — não invente links. Conteúdo encerrado ou com inscrições fechadas fica de fora.
2. Link direto do PDF é aceito para e-book, desde que seja do site oficial.
3. Não repita conteúdos entre si nem os já cadastrados (lista no fim). Descrição curta e objetiva, sem propaganda.
4. IMAGEM: endereço DIRETO de uma imagem oficial do item, terminando em .jpg, .jpeg, .png ou .webp. No e-book, a CAPA; no curso, a imagem de divulgação da página do curso. Nunca logotipo genérico, ícone ou imagem de outro site. Se não encontrar, escreva: Não encontrada (o item entra com a imagem padrão da plataforma).
5. Instituição: use o nome como está na lista de fontes acima.
6. Responda SOMENTE com as fichas abaixo, sem introdução, sem conclusão, sem tabela e sem negrito. Separe cada ficha com uma linha contendo apenas ---

FORMATO DE CADA FICHA (copie os rótulos exatamente assim):
{$ficha}
{$excluir}
TXT;
    }
}
