<?php
declare(strict_types=1);

/**
 * Leitura de texto em imagens (OCR) com o Tesseract instalado no servidor — sem API externa.
 * Usada pela máquina de extração de vagas (cartaz da vaga) e de currículos enviados como foto.
 *
 * Como funciona:
 *  1. a imagem é preparada com o GD (tons de cinza, correção de gama e ampliação para ~2200 px de
 *     largura — cartazes de rede social chegam com ~1080 px e letra pequena);
 *  2. o Tesseract lê a imagem duas vezes, devolvendo cada palavra com posição, altura e confiança:
 *       - leitura estruturada (psm 3): mantém as linhas na ordem ("Salário: R$ 1.900,00");
 *       - leitura de texto esparso (psm 11): acha textos soltos e letreiros decorados;
 *  3. linhas com confiança baixa (ruído de fundo, ícones, fotos) são descartadas;
 *  4. as linhas escritas com as maiores letras viram "destaques" (o título do cartaz).
 *
 * Instalação no Windows: https://github.com/UB-Mannheim/tesseract/wiki (marcar o idioma
 * português). No Linux: apt install tesseract-ocr tesseract-ocr-por.
 */
final class OcrImagem {
    /** Largura (px) para a qual a imagem é ampliada antes da leitura. */
    private const LARGURA = 2200;
    /** Confiança média mínima (0–100) para uma linha entrar no texto. */
    private const CONFIANCA_LINHA = 55;
    /** Maior imagem aceita (pixels), antes e depois da ampliação: no GD cada pixel ocupa 4 bytes de memória. */
    private const MAX_PIXELS = 40_000_000;

    /** Caminho do executável do Tesseract (null = não instalado). */
    public static function comando(): ?string {
        static $cmd = false;
        if ($cmd === false) {
            $cmd = LeitorDocumento::encontrarComando('tesseract', [
                'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
                'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
                'C:\\xampp\\tesseract\\tesseract.exe',
                '/usr/bin/tesseract', '/usr/local/bin/tesseract', '/opt/homebrew/bin/tesseract',
            ]);
        }
        return $cmd;
    }

    public static function disponivel(): bool { return self::comando() !== null && extension_loaded('gd'); }

    /**
     * Lê o texto de uma imagem (JPG, PNG, WEBP ou GIF).
     * @return array{texto:string,complemento:string[],destaques:string[],confianca:int}
     *   texto:       linhas da leitura estruturada, na ordem do cartaz;
     *   complemento: trechos que só a leitura esparsa encontrou (fora da ordem);
     *   destaques:   linhas com as maiores letras (título), da maior para a menor;
     *   confianca:   confiança média das palavras aproveitadas (0–100).
     */
    public static function ler(string $path): array {
        $leituras = self::leituras($path);
        return $leituras ? self::montar(...$leituras) : self::vazio();
    }

    /**
     * As duas leituras brutas do Tesseract (TSV: palavra, posição, altura e confiança).
     * @return array{0:string,1:string}|null [estruturada, esparsa]; null se não foi possível ler
     */
    public static function leituras(string $path): ?array {
        $cmd = self::comando();
        if ($cmd === null || !extension_loaded('gd') || !is_file($path)) return null;
        $tmp = self::preparar($path);
        if ($tmp === null) return null;
        try {
            $idioma = self::idioma($cmd);
            return [self::tsv($cmd, $tmp, 3, $idioma), self::tsv($cmd, $tmp, 11, $idioma)];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Monta o resultado a partir das duas leituras em TSV (separado para poder ser testado
     * com leituras já salvas, sem rodar o Tesseract de novo).
     */
    public static function montar(string $tsv3, string $tsv11): array {
        $estruturada = self::linhas($tsv3);
        $esparsa = self::linhas($tsv11);

        $boas = array_values(array_filter($estruturada, fn($l) => self::boa($l)));
        $texto = implode("\n", array_column($boas, 't'));

        // Complemento: o que a leitura esparsa achou e a estruturada não (títulos decorados, valores soltos).
        $base = ' '.Competencias::normalizar($texto).' ';
        $complemento = []; $vistos = [];
        foreach ($esparsa as $l) {
            $k = Competencias::normalizar($l['t']);
            if (!self::boa($l, 62) || $k === '' || isset($vistos[$k]) || str_contains($base, ' '.$k.' ')) continue;
            $vistos[$k] = true;
            $complemento[] = $l['t'];
        }

        // Destaques: as maiores letras das duas leituras (sem repetir).
        $todas = array_filter([...$boas, ...array_filter($esparsa, fn($l) => self::boa($l, 62))], fn($l) => preg_match_all('/\p{L}/u', $l['t']) >= 4);
        usort($todas, fn($a, $b) => $b['h'] <=> $a['h']);
        $destaques = []; $vistos = [];
        foreach ($todas as $l) {
            $k = Competencias::normalizar($l['t']);
            if ($k === '' || isset($vistos[$k])) continue;
            $vistos[$k] = true;
            $destaques[] = $l['t'];
            if (count($destaques) >= 10) break;
        }

        $confs = array_merge(...array_map(fn($l) => $l['confs'], $boas ?: [['confs' => []]]));
        return [
            'texto' => $texto,
            'complemento' => $complemento,
            'destaques' => $destaques,
            'confianca' => $confs ? (int)round(array_sum($confs) / count($confs)) : 0,
        ];
    }

    // ------------------------------------------------------------------

    private static function vazio(): array { return ['texto' => '', 'complemento' => [], 'destaques' => [], 'confianca' => 0]; }

    /** Imagem ampliada, em tons de cinza e com mais contraste, salva num PNG temporário. */
    private static function preparar(string $path): ?string {
        // Dimensões lidas do cabeçalho ANTES de decodificar: um PNG de poucos KB pode declarar
        // 50.000 × 50.000 px e esgotar a memória dentro do imagecreatefromstring.
        $info = @getimagesize($path);
        if (!$info || $info[0] < 20 || $info[1] < 20 || $info[0] * $info[1] > self::MAX_PIXELS) return null;
        $bin = @file_get_contents($path);
        $im = $bin !== false ? @imagecreatefromstring($bin) : false;
        if (!$im) return null;
        $w = imagesx($im); $h = imagesy($im);
        if ($w < 20 || $h < 20 || $w * $h > self::MAX_PIXELS) { imagedestroy($im); return null; }
        $f = max(1.0, min(3.0, self::LARGURA / $w));
        // A ampliação também tem teto (uma imagem estreita e muito alta seria ampliada 3×).
        $f = min($f, sqrt(self::MAX_PIXELS / ($w * $h)));
        $nw = (int)round($w * $f); $nh = (int)round($h * $f);
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // fundo branco para PNG transparente
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im);
        imagefilter($out, IMG_FILTER_GRAYSCALE);
        // Gama escurece os tons médios: letra amarela/laranja sobre fundo claro (comum em cartazes)
        // deixa de ficar quase branca em tons de cinza, sem apagar o texto claro sobre fundo escuro.
        imagegammacorrect($out, 2.2, 1.0);
        $tmp = rtrim(sys_get_temp_dir(), '\\/').DIRECTORY_SEPARATOR.'cvdf_ocr_'.bin2hex(random_bytes(6)).'.png';
        $ok = imagepng($out, $tmp, 1); // compressão mínima: o arquivo é temporário
        imagedestroy($out);
        return $ok ? $tmp : null;
    }

    /** "por" quando o português está instalado; senão o inglês (lê o alfabeto latino, sem acentos). */
    private static function idioma(string $cmd): string {
        static $idioma = null;
        if ($idioma === null) {
            $lista = (string)@shell_exec(LeitorDocumento::linhaComando(LeitorDocumento::q($cmd).' --list-langs'));
            $idioma = preg_match('/^por\s*$/m', $lista) ? 'por' : 'eng';
        }
        return $idioma;
    }

    private static function tsv(string $cmd, string $png, int $psm, string $idioma): string {
        return (string)@shell_exec(LeitorDocumento::linhaComando(
            LeitorDocumento::q($cmd).' '.LeitorDocumento::q($png).' stdout -l '.$idioma.' --psm '.$psm.' tsv'));
    }

    /**
     * Agrupa as palavras do TSV em linhas.
     * @return array<int,array{t:string,conf:float,h:float,top:int,confs:float[]}>
     */
    private static function linhas(string $tsv): array {
        $grupos = [];
        foreach (explode("\n", $tsv) as $i => $row) {
            if ($i === 0) continue;
            $c = explode("\t", rtrim($row, "\r"));
            if (count($c) < 12 || $c[0] !== '5') continue;
            $palavra = trim($c[11]); $conf = (float)$c[10];
            if ($palavra === '' || $conf < 0) continue;
            $k = $c[2].'-'.$c[3].'-'.$c[4];
            $grupos[$k][] = ['p' => $palavra, 'conf' => $conf, 'h' => (int)$c[9], 'top' => (int)$c[7]];
        }
        $out = [];
        foreach ($grupos as $palavras) {
            // Palavras quase ilegíveis nas pontas da linha costumam ser ícones ou pedaços da foto.
            while ($palavras && $palavras[0]['conf'] < 40) array_shift($palavras);
            while ($palavras && end($palavras)['conf'] < 40) array_pop($palavras);
            if (!$palavras) continue;
            $confs = array_column($palavras, 'conf');
            $alturas = array_column($palavras, 'h'); sort($alturas);
            $out[] = [
                't' => trim(preg_replace('/\s+/u', ' ', implode(' ', array_column($palavras, 'p'))) ?? ''),
                'conf' => array_sum($confs) / count($confs),
                'h' => (float)$alturas[intdiv(count($alturas), 2)],
                'top' => min(array_column($palavras, 'top')),
                'confs' => $confs,
            ];
        }
        return $out;
    }

    /** Linha aproveitável: confiança média boa e com pelo menos uma palavra de verdade. */
    private static function boa(array $l, int $minimo = self::CONFIANCA_LINHA): bool {
        if ($l['conf'] < $minimo || $l['t'] === '') return false;
        if (!preg_match('/[\p{L}\d]{3,}/u', $l['t'])) return false;
        // Muitos símbolos soltos = ruído de ícones/fundo.
        $letras = preg_match_all('/[\p{L}\d]/u', $l['t']);
        return $letras / max(1, mb_strlen(str_replace(' ', '', $l['t']))) >= 0.6;
    }
}
