<?php
declare(strict_types=1);

/**
 * Conversor de WordprocessingML (DOCX) para texto, com DOM:
 * - parágrafos e quebras; itens de lista ganham "• ";
 * - tabelas: linha de células curtas vira "Rótulo: valor" ou "A | B | C"; células longas saem uma após a outra;
 * - caixas de texto (mc:AlternateContent) sem duplicar o conteúdo do "Fallback";
 * - hyperlinks externos (lidos das relações) e parágrafos com a maior fonte (destaques).
 */
final class DocxTexto {
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    /** @var string[] */
    public array $destaques = [];
    /** @var string[] */
    public array $links = [];
    /** @var array<int,array{0:string,1:float}> parágrafos e tamanho de fonte (para achar o destaque) */
    private array $tamanhos = [];
    private array $rels = [];

    public function converter(string $xml, array $rels): string {
        $this->rels = $rels;
        // O "Fallback" repete o conteúdo das caixas de texto em formato antigo (VML).
        $xml = preg_replace('#<mc:Fallback\b.*?</mc:Fallback>#s', '', $xml) ?? $xml;
        $dom = new DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            // XML quebrado: extração simples por tags.
            $x = preg_replace(['#<w:tab/>#', '#<w:(br|cr)\b[^>]*/>#', '#</w:p>#'], ["\t", "\n", "\n"], $xml) ?? $xml;
            return html_entity_decode(strip_tags($x), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        $raiz = $dom->documentElement;
        $corpo = $raiz ? ($raiz->getElementsByTagNameNS(self::W, 'body')->item(0) ?? $raiz) : null;
        if (!$corpo) return '';
        $linhas = $this->blocos($corpo);
        $this->calcularDestaques();
        return implode("\n", $linhas);
    }

    /** @return string[] linhas do conteúdo de um nó que contém parágrafos/tabelas */
    private function blocos(DOMNode $no): array {
        $out = [];
        foreach ($no->childNodes as $f) {
            if (!$f instanceof DOMElement || $f->namespaceURI !== self::W) {
                if ($f instanceof DOMElement) array_push($out, ...$this->blocos($f)); // ex.: w:sdt, mc:*
                continue;
            }
            switch ($f->localName) {
                case 'p': array_push($out, ...$this->paragrafo($f)); break;
                case 'tbl': array_push($out, ...$this->tabela($f)); break;
                case 'sdt': case 'sdtContent': case 'customXml': case 'txbxContent': array_push($out, ...$this->blocos($f)); break;
            }
        }
        return $out;
    }

    /** @return string[] caixas de texto internas + a linha do próprio parágrafo */
    private function paragrafo(DOMElement $p): array {
        $antes = [];
        foreach ($p->getElementsByTagNameNS(self::W, 'txbxContent') as $cx) {
            // Só as caixas de primeiro nível (as internas são lidas recursivamente).
            if ($this->ancestral($cx, 'txbxContent', $p)) continue;
            array_push($antes, ...$this->blocos($cx));
        }
        $texto = ''; $maior = 0.0;
        $this->textoRuns($p, $texto, $maior);
        $texto = trim(preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto);
        $estilo = '';
        $ps = $p->getElementsByTagNameNS(self::W, 'pStyle')->item(0);
        if ($ps instanceof DOMElement) $estilo = strtolower($ps->getAttributeNS(self::W, 'val'));
        if ($texto === '') return $antes;
        if (in_array($estilo, ['title', 'titulo', 'título'], true)) $maior = max($maior, 40.0);
        $lista = $p->getElementsByTagNameNS(self::W, 'numPr')->length > 0 && !$this->ancestral($p->getElementsByTagNameNS(self::W, 'numPr')->item(0), 'txbxContent', $p);
        foreach (explode("\n", $texto) as $l) $this->tamanhos[] = [trim($l), $maior];
        return array_merge($antes, [($lista ? '• ' : '').$texto]);
    }

    /** Texto das execuções do parágrafo, ignorando o conteúdo de caixas de texto internas. */
    private function textoRuns(DOMNode $no, string &$texto, float &$maior): void {
        foreach ($no->childNodes as $f) {
            if (!$f instanceof DOMElement) continue;
            $nome = $f->localName;
            if ($nome === 'txbxContent' || $nome === 'Fallback' || $nome === 'pPr' || $nome === 'rPr' && $f->parentNode?->localName === 'pPr') continue;
            if ($f->namespaceURI === self::W) {
                if ($nome === 't') { $texto .= $f->textContent; continue; }
                if ($nome === 'tab' || $nome === 'ptab') { $texto .= "\t"; continue; }
                if ($nome === 'br' || $nome === 'cr') { $texto .= "\n"; continue; }
                if ($nome === 'noBreakHyphen') { $texto .= '-'; continue; }
                if ($nome === 'sym') { $texto .= ' '; continue; }
                if ($nome === 'sz') { $maior = max($maior, (float)$f->getAttributeNS(self::W, 'val') / 2); continue; }
                if ($nome === 'hyperlink') {
                    $id = $f->getAttributeNS(self::R, 'id');
                    $alvo = $this->rels[$id]['alvo'] ?? '';
                    if ($alvo !== '' && preg_match('#^(https?://|mailto:|www\.)#i', $alvo)) $this->links[] = $alvo;
                }
                if ($nome === 'instrText' && preg_match('/HYPERLINK\s+"([^"]+)"/', $f->textContent, $hm)) { $this->links[] = $hm[1]; continue; }
                if ($nome === 'delText' || $nome === 'instrText') continue;
            }
            $this->textoRuns($f, $texto, $maior);
        }
    }

    /** @return string[] */
    private function tabela(DOMElement $tbl): array {
        $out = [];
        foreach ($tbl->childNodes as $tr) {
            if (!$tr instanceof DOMElement || $tr->localName !== 'tr') continue;
            $celulas = [];
            foreach ($tr->childNodes as $tc) {
                if (!$tc instanceof DOMElement || $tc->localName !== 'tc') continue;
                $celulas[] = array_values(array_filter(array_map('trim', $this->blocos($tc)), fn($l) => $l !== ''));
            }
            $celulas = array_values(array_filter($celulas, fn($c) => $c !== []));
            if (!$celulas) continue;
            $curtas = count($celulas) >= 2 && array_reduce($celulas, fn($ok, $c) => $ok && count($c) === 1 && mb_strlen($c[0]) <= 120, true);
            if (!$curtas) { foreach ($celulas as $c) array_push($out, ...$c); continue; }
            $vals = array_map(fn($c) => trim_u($c[0], '• ', 'inicio'), $celulas);
            // "Telefone" | "(61) 9..." → "Telefone: (61) 9..."; linhas com 3+ colunas → "A | B | C".
            if (count($vals) === 2 && mb_strlen($vals[0]) <= 30 && !preg_match('/[\d@]/u', $vals[0])) {
                $out[] = rtrim($vals[0], ': ').': '.$vals[1];
            } else {
                $out[] = implode(' | ', $vals);
            }
        }
        return $out;
    }

    private function ancestral(?DOMNode $no, string $nome, DOMNode $limite): bool {
        for ($n = $no?->parentNode; $n && $n !== $limite; $n = $n->parentNode) if ($n->localName === $nome) return true;
        return false;
    }

    /** Parágrafos com a maior fonte (quando ela se destaca do texto comum). */
    private function calcularDestaques(): void {
        $tams = array_values(array_filter(array_map(fn($x) => $x[1], $this->tamanhos), fn($s) => $s > 0));
        if (!$tams) return;
        $max = max($tams);
        sort($tams); $mediana = $tams[intdiv(count($tams), 2)];
        if ($max < 16 && $max < $mediana * 1.3) return;
        foreach ($this->tamanhos as [$t, $s]) {
            if ($s >= $max * 0.98 && $t !== '') $this->destaques[] = $t;
            if (count($this->destaques) >= 3) break;
        }
    }
}
