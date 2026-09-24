<?php
declare(strict_types=1);

/**
 * Extrai o texto de PDF, DOCX e DOC sem API externa.
 *
 * Ordem de tentativa:
 *  - PDF:  leitor PDF em PHP puro (posição de cada trecho, detecção de colunas, ToUnicode/CMap)
 *          → pdftotext (se existir) para PDFs com fontes sem mapa de caracteres.
 *  - DOCX: ZipArchive (se habilitado) ou leitor ZIP em PHP puro; o XML é lido com DOM
 *          (tabelas, cabeçalho/rodapé, caixas de texto, listas e hyperlinks).
 *  - DOC:  antiword (se existir) → leitura heurística dos trechos de texto do arquivo binário.
 *
 * Além do texto, deixa disponíveis (propriedades estáticas, preenchidas a cada extração):
 *  - $destaques: linhas escritas com a maior fonte da 1ª página (normalmente o nome do candidato);
 *  - $links:     endereços dos hyperlinks do arquivo (LinkedIn, GitHub, portfólio...).
 *
 * O trabalho pesado fica em duas classes vizinhas: PdfTexto (interpreta o PDF) e
 * DocxTexto (converte o XML do Word). Quem usa a extração chama só esta classe
 * (via ExtracaoCurriculo::extrair).
 */
final class LeitorDocumento {
    /** Último método usado, para exibir ao usuário. */
    public static string $metodo = '';
    /** @var string[] linhas com a maior fonte do documento (nome em destaque) */
    public static array $destaques = [];
    /** @var string[] URLs encontradas nos hyperlinks do arquivo */
    public static array $links = [];
    /** true quando a última leitura parou por passar do LIMITE_DESCOMPACTADO (o texto volta vazio). */
    public static bool $grandeDemais = false;

    /**
     * Teto do conteúdo descompactado por arquivo (streams do PDF, entradas do DOCX).
     * Protege contra "bombas de descompressão": poucos MB que se expandem para gigabytes
     * derrubariam o PHP por falta de memória. Um currículo normal fica bem abaixo de 1 MB.
     */
    public const LIMITE_DESCOMPACTADO = 30 * 1024 * 1024;

    public static function extrair(string $path): string {
        self::$metodo = ''; self::$destaques = []; self::$links = []; self::$grandeDemais = false;
        if (!is_file($path)) return '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        try {
            $texto = match ($ext) {
                'pdf' => self::pdf($path),
                'docx' => self::docx($path),
                'doc' => self::doc($path),
                'txt' => (string)file_get_contents($path),
                default => '',
            };
        } catch (LengthException) {
            self::$grandeDemais = true; self::$destaques = []; self::$links = [];
            $texto = '';
        } catch (Throwable) {
            $texto = '';
        }
        $texto = self::limpar(self::utf8($texto));
        // Links que só existem no hyperlink (ex.: texto "Meu LinkedIn") entram no texto,
        // para ficarem gravados junto com o currículo e serem lidos pela extração.
        $novos = [];
        foreach (self::$links as $u) {
            $chave = self::chaveLink($u);
            if ($chave !== '' && !str_contains(mb_strtolower($texto), $chave)) $novos[$chave] = $u;
        }
        if ($novos && $texto !== '') $texto .= "\nLinks: ".implode(' ', $novos);
        return $texto;
    }

    /** Endereço sem protocolo/www/barra final, em minúsculas (para comparar links). */
    public static function chaveLink(string $u): string {
        $u = mb_strtolower(trim($u));
        $u = preg_replace('#^(https?://)?(www\.)?#', '', $u) ?? $u;
        return rtrim($u, '/');
    }

    // ------------------------------------------------------------------ PDF

    private static function pdf(string $path): string {
        // 1) Leitor interno: preserva as linhas e a ordem de leitura das colunas.
        $t = '';
        $leitor = null;
        try { $leitor = new PdfTexto((string)file_get_contents($path)); $t = $leitor->texto(); }
        catch (LengthException $e) { throw $e; } // grande demais: nem tenta o pdftotext
        catch (Throwable) {}
        if ($leitor) { self::$destaques = $leitor->destaques; self::$links = $leitor->links(); }
        if (self::textoUtil($t) && self::legivel($t)) { self::$metodo = 'leitor PDF interno'; return $t; }
        // 2) pdftotext (Poppler/Xpdf), quando instalado, para PDFs com fontes sem mapa de caracteres.
        $cmd = self::encontrarComando('pdftotext', [
            'C:\\Program Files\\Git\\mingw64\\bin\\pdftotext.exe',
            'C:\\poppler\\Library\\bin\\pdftotext.exe',
            'C:\\Program Files\\poppler\\Library\\bin\\pdftotext.exe',
            'C:\\xampp\\poppler\\bin\\pdftotext.exe',
            '/usr/bin/pdftotext', '/usr/local/bin/pdftotext',
        ]);
        if ($cmd && function_exists('shell_exec')) {
            $tmp = $path.'.extract.txt';
            @shell_exec(self::linhaComando(self::q($cmd).' -layout -enc UTF-8 '.self::q($path).' '.self::q($tmp)));
            if (is_file($tmp)) {
                $t2 = (string)@file_get_contents($tmp, false, null, 0, self::LIMITE_DESCOMPACTADO); @unlink($tmp);
                if (self::textoUtil($t2)) { self::$metodo = 'pdftotext'; self::$destaques = []; return $t2; }
            }
        }
        if (self::textoUtil($t)) self::$metodo = 'leitor PDF interno';
        return $t;
    }

    /** Texto legível: a maior parte dos caracteres são letras/espaços comuns (e não lixo de fonte sem mapa). */
    private static function legivel(string $t): bool {
        $total = max(1, mb_strlen($t));
        $bons = preg_match_all('/[\p{L}\p{N}\s.,;:()\/@\-–|•]/u', $t);
        return $bons / $total > 0.85;
    }

    // ------------------------------------------------------------------ DOCX

    private static function docx(string $path): string {
        $ler = self::abrirZip($path);
        $xml = $ler('word/document.xml');
        if ($xml === null) return '';
        $rels = self::relacoes((string)$ler('word/_rels/document.xml.rels'));
        $docx = new DocxTexto();
        $corpo = $docx->converter($xml, $rels);

        // Cabeçalho (costuma trazer nome e contato) vem antes; do rodapé, só linhas de contato.
        $cab = ''; $rod = '';
        foreach ($rels as $r) {
            if (!in_array($r['tipo'], ['header', 'footer'], true)) continue;
            $parte = $ler('word/'.ltrim($r['alvo'], '/'));
            if ($parte === null) continue;
            $relsParte = self::relacoes((string)$ler('word/_rels/'.basename($r['alvo']).'.rels'));
            $t = trim($docx->converter($parte, $relsParte));
            if ($r['tipo'] === 'header') { $cab .= $t."\n"; continue; }
            foreach (preg_split('/\R/u', $t) ?: [] as $l) {
                if (preg_match('/@|https?:|www\.|\(?\d{2}\)?\s?9?\d{4}[\s.-]?\d{4}/u', $l) && !preg_match('/p[aá]gina\s*\d/iu', $l)) $rod .= $l."\n";
            }
        }
        self::$destaques = $docx->destaques;
        self::$links = $docx->links;
        return trim($cab."\n".$corpo."\n".$rod);
    }

    /** @return array<string,array{tipo:string,alvo:string,externo:bool}> relações por Id */
    private static function relacoes(string $xml): array {
        $out = [];
        if ($xml === '' || !preg_match_all('/<Relationship\b([^>]*)\/?>/i', $xml, $ms)) return $out;
        foreach ($ms[1] as $attrs) {
            $a = [];
            if (preg_match_all('/(\w+)="([^"]*)"/', $attrs, $am, PREG_SET_ORDER)) foreach ($am as $x) $a[$x[1]] = html_entity_decode($x[2], ENT_QUOTES | ENT_XML1, 'UTF-8');
            if (!isset($a['Id'])) continue;
            $out[$a['Id']] = ['tipo' => basename((string)($a['Type'] ?? '')), 'alvo' => (string)($a['Target'] ?? ''), 'externo' => ($a['TargetMode'] ?? '') === 'External'];
        }
        return $out;
    }

    /**
     * Abre um ZIP e devolve uma função que lê uma entrada pelo nome (null se não existir).
     * As entradas lidas somam no máximo LIMITE_DESCOMPACTADO; passar disso lança LengthException.
     * @return callable(string):?string
     */
    private static function abrirZip(string $path): callable {
        $resta = self::LIMITE_DESCOMPACTADO;
        if (class_exists('ZipArchive')) {
            $z = new ZipArchive();
            if ($z->open($path) === true) {
                $mapa = [];
                for ($i = 0; $i < $z->numFiles; $i++) { $n = (string)$z->getNameIndex($i); $mapa[str_replace('\\', '/', $n)] = $n; }
                self::$metodo = 'ZipArchive';
                return function (string $nome) use ($z, $mapa, &$resta): ?string {
                    if (!isset($mapa[$nome])) return null;
                    // Confere o tamanho declarado antes de descompactar e lê no máximo esse tamanho.
                    $st = $z->statName($mapa[$nome]);
                    if ($st === false) return null;
                    $tam = (int)$st['size'];
                    if ($tam > $resta) self::grandeDemais();
                    $d = $tam > 0 ? $z->getFromName($mapa[$nome], $tam) : '';
                    if ($d === false) return null;
                    $resta -= strlen($d);
                    return $d;
                };
            }
        }
        self::$metodo = 'leitor DOCX interno';
        return function (string $nome) use ($path, &$resta): ?string {
            $d = self::lerEntradaZip($path, $nome, $resta);
            if ($d !== null) $resta -= strlen($d);
            return $d;
        };
    }

    /** Interrompe a leitura: o arquivo passou do LIMITE_DESCOMPACTADO (extrair() trata como ilegível). */
    private static function grandeDemais(): never {
        throw new LengthException('Conteúdo do arquivo grande demais para ser lido.');
    }

    /** Nomes das entradas de um ZIP (com "/" mesmo quando o compactador gravou "\"). */
    public static function listarZip(string $path): array {
        if (class_exists('ZipArchive')) {
            $z = new ZipArchive();
            if ($z->open($path) === true) {
                $out = [];
                for ($i = 0; $i < $z->numFiles; $i++) $out[] = str_replace('\\', '/', (string)$z->getNameIndex($i));
                $z->close();
                return $out;
            }
        }
        $out = [];
        foreach (self::diretorioZip((string)@file_get_contents($path)) as $e) $out[] = $e['nome'];
        return $out;
    }

    /** @return array<int,array{nome:string,metodo:int,csize:int,usize:int,local:int}> */
    private static function diretorioZip(string $bin): array {
        $eocd = strrpos($bin, "PK\x05\x06");
        if ($eocd === false) return [];
        $e = unpack('vdisk/vdiskcd/ventries/vtotal/Vsize/Voffset', substr($bin, $eocd + 4, 16));
        $pos = (int)$e['offset']; $out = [];
        for ($i = 0; $i < (int)$e['total']; $i++) {
            if (substr($bin, $pos, 4) !== "PK\x01\x02") break;
            $h = unpack('vver/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/vxlen/vclen/vdisk/vint/Vext/Vlocal', substr($bin, $pos + 4, 42));
            // Alguns compactadores do Windows (Compress-Archive) gravam "word\document.xml".
            $out[] = ['nome' => str_replace('\\', '/', substr($bin, $pos + 46, (int)$h['nlen'])), 'metodo' => (int)$h['method'], 'csize' => (int)$h['csize'], 'usize' => (int)$h['usize'], 'local' => (int)$h['local']];
            $pos += 46 + (int)$h['nlen'] + (int)$h['xlen'] + (int)$h['clen'];
        }
        return $out;
    }

    /**
     * Leitor mínimo de ZIP (diretório central + deflate), usado quando ZipArchive não está habilitado.
     * Nunca devolve mais que $limite bytes: entrada maior (declarada ou real) lança LengthException.
     */
    public static function lerEntradaZip(string $path, string $nome, int $limite = self::LIMITE_DESCOMPACTADO): ?string {
        static $cache = [];
        $chave = $path.'|'.@filemtime($path);
        if (!isset($cache[$chave])) { $cache = [$chave => (string)@file_get_contents($path)]; }
        $bin = $cache[$chave];
        foreach (self::diretorioZip($bin) as $e) {
            if ($e['nome'] !== $nome) continue;
            $lh = unpack('vnlen/vxlen', substr($bin, $e['local'] + 26, 4));
            $dados = substr($bin, $e['local'] + 30 + (int)$lh['nlen'] + (int)$lh['xlen'], $e['csize']);
            if ($e['usize'] > $limite) self::grandeDemais();
            if ($e['metodo'] === 0) { if (strlen($dados) > $limite) self::grandeDemais(); return $dados; }
            if ($e['metodo'] === 8) {
                // max_length: o tamanho declarado pode ser falso; o zlib para ao passar do teto.
                error_clear_last();
                $out = @gzinflate($dados, $limite + 1);
                if ($out === false && str_contains((string)(error_get_last()['message'] ?? ''), 'insufficient memory')) self::grandeDemais();
                if ($out !== false && strlen($out) > $limite) self::grandeDemais();
                return $out === false ? null : $out;
            }
            return null;
        }
        return null;
    }

    // ------------------------------------------------------------------ foto

    /**
     * Procura uma foto do candidato dentro do arquivo (DOCX: word/media; PDF: imagens JPEG).
     * Aceita só JPG/PNG de tamanho razoável e proporção de retrato/quadrada (descarta ícones e banners).
     * @return array{dados:string,ext:string,largura:int,altura:int}|null
     */
    public static function extrairFoto(string $path): ?array {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $candidatas = [];
        try {
            if ($ext === 'docx') {
                $ler = self::abrirZip($path);
                foreach (self::listarZip($path) as $n) {
                    if (preg_match('#^word/media/[^/]+\.(jpe?g|png)$#i', $n)) { $d = $ler($n); if ($d !== null) $candidatas[] = $d; }
                }
            } elseif ($ext === 'pdf') {
                $candidatas = (new PdfTexto((string)file_get_contents($path)))->imagensJpeg();
            }
        } catch (Throwable) {
            return null;
        }
        $melhor = null;
        foreach ($candidatas as $d) {
            $tam = strlen($d);
            if ($tam < 2048 || $tam > 5 * 1024 * 1024) continue;
            $info = @getimagesizefromstring($d);
            if (!$info || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) continue;
            [$w, $h] = [(int)$info[0], (int)$info[1]];
            if ($w < 80 || $h < 80 || $w > 6000 || $h > 6000) continue;
            $prop = $w / $h;
            if ($prop < 0.55 || $prop > 1.6) continue;
            if ($melhor === null || $w * $h > $melhor['largura'] * $melhor['altura']) {
                $melhor = ['dados' => $d, 'ext' => $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg', 'largura' => $w, 'altura' => $h];
            }
        }
        return $melhor;
    }

    // ------------------------------------------------------------------ DOC

    private static function doc(string $path): string {
        $cmd = self::encontrarComando('antiword', ['C:\\antiword\\antiword.exe', '/usr/bin/antiword']);
        if ($cmd && function_exists('shell_exec')) {
            $t = (string)@shell_exec(self::linhaComando(self::q($cmd).' '.self::q($path)));
            if (self::textoUtil($t)) { self::$metodo = 'antiword'; return $t; }
        }
        // Word 97-2003 guarda o texto como UTF-16LE ou Windows-1252 dentro do arquivo OLE.
        $bin = (string)file_get_contents($path);
        $partes16 = [];
        if (preg_match_all('/(?:[\x09\x0A\x0D\x20-\x7E\xA0-\xFF]\x00){6,}/', $bin, $m)) {
            foreach ($m[0] as $r) $partes16[] = mb_convert_encoding($r, 'UTF-8', 'UTF-16LE');
        }
        $partes8 = [];
        if (preg_match_all('/[\x09\x0A\x0D\x20-\x7E\xC0-\xFF]{12,}/', $bin, $m)) {
            foreach ($m[0] as $r) $partes8[] = mb_convert_encoding($r, 'UTF-8', 'Windows-1252');
        }
        $t16 = implode("\n", $partes16); $t8 = implode("\n", $partes8);
        $t = mb_strlen($t16) >= mb_strlen($t8) * 0.6 ? $t16 : $t8;
        // Remove nomes internos do Word (estilos/fontes) que aparecem no binário.
        $linhas = array_filter(preg_split('/\R/u', $t) ?: [], function ($l) {
            $l = trim($l);
            if (preg_match('/^(Normal|Heading \d|Título \d|Default Paragraph Font|Table Normal|No List|Times New Roman|Arial|Calibri|Symbol|Courier New|Wingdings|Microsoft Word.*|Root Entry|WordDocument|SummaryInformation|DocumentSummaryInformation|CompObj|1Table|0Table)$/i', $l)) return false;
            return preg_match_all('/\p{L}/u', $l) >= 3;
        });
        $t = implode("\n", $linhas);
        // Hyperlinks gravados no binário.
        if (preg_match_all('#HYPERLINK\s+"([^"]+)"#', $t, $hm)) self::$links = array_values(array_unique($hm[1]));
        if (self::textoUtil($t)) self::$metodo = 'leitor DOC interno (aproximado)';
        return $t;
    }

    // ------------------------------------------------------------------ utilitários

    public static function encontrarComando(string $nome, array $candidatos): ?string {
        foreach ($candidatos as $c) if (@is_file($c)) return $c;
        if (!function_exists('shell_exec')) return null;
        $win = DIRECTORY_SEPARATOR === '\\';
        $r = trim((string)@shell_exec($win ? 'where '.escapeshellarg($nome).' 2>NUL' : 'command -v '.escapeshellarg($nome).' 2>/dev/null'));
        if ($r === '') return null;
        $primeiro = preg_split('/\R/', $r)[0] ?? '';
        return $primeiro !== '' && @is_file($primeiro) ? $primeiro : null;
    }

    public static function q(string $v): string { return '"'.str_replace('"', '\\"', $v).'"'; }

    /** Descarta a saída de erro (o PHP 8 no Windows já executa via cmd /s /c "..."). */
    public static function linhaComando(string $c): string {
        return $c.(DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null');
    }

    /** Considera útil o texto com pelo menos 20 letras. */
    public static function textoUtil(string $t): bool { return preg_match_all('/\p{L}/u', $t) >= 20; }

    private static function utf8(string $t): string {
        if ($t === '' || mb_check_encoding($t, 'UTF-8')) return $t;
        return mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
    }

    public static function limpar(string $t): string {
        $t = str_replace(["\r\n", "\r", "\f"], "\n", $t);
        // Ligaduras tipográficas (fi, fl...) e espaços especiais que os PDFs costumam trazer.
        $t = strtr($t, ['ﬀ' => 'ff', 'ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬃ' => 'ffi', 'ﬄ' => 'ffl', 'ﬅ' => 'st', 'ﬆ' => 'st',
            "\u{00AD}" => '', "\u{200B}" => '', "\u{FEFF}" => '', "\u{2009}" => ' ', "\u{202F}" => ' ', "\u{2002}" => ' ', "\u{2003}" => ' ']);
        $t = preg_replace('/[\x00-\x08\x0B\x0E-\x1F\x7F]+/u', ' ', $t) ?? $t;
        // Caracteres de uso privado (ícones de fontes como Font Awesome) não são texto.
        $t = preg_replace('/[\x{E000}-\x{F8FF}]/u', ' ', $t) ?? $t;
        $t = preg_replace('/[ \t\x{00A0}]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/ *\n */u', "\n", $t) ?? $t;
        $t = preg_replace("/\n{3,}/u", "\n\n", $t) ?? $t;
        return trim(mb_substr($t, 0, 100000, 'UTF-8'));
    }
}
