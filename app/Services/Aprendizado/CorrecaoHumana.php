<?php
declare(strict_types=1);

/**
 * Correção humana: compara o que a máquina sugeriu com o que a pessoa salvou e tira disso as lições.
 *
 * É a parte "professor" do aprendizado supervisionado. Ninguém precisa ensinar a máquina de
 * propósito: a pessoa revisa o formulário como sempre fez, e o que ela deixou salvo é a resposta
 * certa. Exemplo com um anúncio de vaga:
 *
 *   linha lida do cartaz ......... "Escala 6x1, folga aos domingos"
 *   a máquina pôs em ............. Descrição
 *   a pessoa salvou a linha em ... Requisitos
 *   lição ........................ "Escala 6x1, folga aos domingos" → requisitos
 *
 * Como achar onde a linha foi parar: procuramos o texto da linha (normalizado) em cada campo salvo.
 * Linha curta (até 2 palavras, como "Vendas") só conta se for uma linha INTEIRA do campo; linha maior
 * também vale como trecho de uma linha do campo (ex.: "Horário: 14h às 22h, escala 6x1").
 * Se ela está em exatamente UM campo, isso vira lição (tenha a máquina acertado ou não). Se não está
 * em nenhum (a pessoa apagou ou reescreveu a linha) ou está em mais de um, não dá para ter certeza —
 * e na dúvida a máquina não aprende nada, para não aprender errado.
 *
 * Tudo aqui é conta com os dados recebidos: sem banco, sem sessão, sem tela.
 */
final class CorrecaoHumana {
    /** Linhas com menos caracteres que isso ("VT", "R$") são curtas demais para garantir onde foram parar. */
    private const MIN_CARACTERES = 4;
    /** A partir de quantas palavras a linha pode ser achada como trecho (e não só como linha inteira). */
    private const MIN_PALAVRAS_TRECHO = 3;

    /**
     * Em qual campo a linha está. '' quando não está em nenhum, está em mais de um ou é curta demais.
     * @param array<string,string> $campos campo => texto do campo
     */
    public static function campoDaLinha(string $linha, array $campos): string {
        $l = Competencias::normalizar($linha);
        if (mb_strlen($l) < self::MIN_CARACTERES) return '';
        $podeSerTrecho = count(explode(' ', $l)) >= self::MIN_PALAVRAS_TRECHO;
        $achou = [];
        foreach ($campos as $campo => $texto) {
            $linhasCampo = array_map([Competencias::class, 'normalizar'], preg_split('/\R/u', (string)$texto) ?: []);
            $inteira = in_array($l, $linhasCampo, true);
            // Trecho: a apagada "Vendas" não pode ser achada dentro de "Experiência em vendas".
            $trecho = $podeSerTrecho && str_contains(' '.implode(' ', $linhasCampo).' ', ' '.$l.' ');
            if ($inteira || $trecho) $achou[] = (string)$campo;
        }
        return count($achou) === 1 ? $achou[0] : '';
    }

    /**
     * Lições tiradas das linhas: cada linha que a pessoa deixou em um campo só.
     * Linhas repetidas no texto viram uma lição só.
     * @param list<string> $linhas linhas do texto original (anúncio ou currículo)
     * @param array<string,string> $salvos campo => texto salvo pela pessoa
     * @return list<array{texto:string,classe:string}>
     */
    public static function licoesDeLinhas(array $linhas, array $salvos): array {
        $licoes = []; $vistas = [];
        foreach ($linhas as $linha) {
            $k = Competencias::normalizar($linha);
            if ($k === '' || isset($vistas[$k])) continue;
            $vistas[$k] = true;
            $campo = self::campoDaLinha($linha, $salvos);
            if ($campo !== '') $licoes[] = ['texto' => $linha, 'classe' => $campo];
        }
        return $licoes;
    }

    /**
     * Quantas linhas a máquina pôs no lugar certo. Só contam as linhas que a pessoa deixou em
     * algum campo (as que viraram lição).
     * @return array{0:int,1:int} [linhas conferidas, linhas certas]
     */
    public static function conferirLinhas(array $linhas, array $previstos, array $salvos): array {
        $total = 0; $certas = 0;
        foreach (self::licoesDeLinhas($linhas, $salvos) as $l) {
            $total++;
            if (self::campoDaLinha($l['texto'], $previstos) === $l['classe']) $certas++;
        }
        return [$total, $certas];
    }

    /**
     * Quantos campos simples (título, cidade, salário...) a máquina acertou.
     * Campo vazio nos dois lados não conta (não havia nada para acertar).
     * @param list<string> $campos quais campos comparar
     * @return array{0:int,1:int,2:list<string>} [campos conferidos, campos certos, campos que a pessoa corrigiu]
     */
    public static function conferirCampos(array $previsto, array $salvo, array $campos): array {
        $total = 0; $certos = 0; $corrigidos = [];
        foreach ($campos as $c) {
            $a = self::valorComparavel($previsto[$c] ?? null);
            $b = self::valorComparavel($salvo[$c] ?? null);
            if ($a === '' && $b === '') continue;
            $total++;
            if ($a === $b) $certos++; else $corrigidos[] = $c;
        }
        return [$total, $certos, $corrigidos];
    }

    /** O nome aparece no texto como palavra(s) inteira(s)? ("Grupo Dourado" dentro do anúncio.) */
    public static function nomeNoTexto(string $nome, string $texto): bool {
        $n = Competencias::normalizar($nome);
        return mb_strlen($n) >= 3 && str_contains(' '.Competencias::normalizar($texto).' ', ' '.$n.' ');
    }

    /** "1900", "1900.00" e 1900.0 viram o mesmo número; textos são comparados sem acento e sem diferença de maiúsculas. */
    private static function valorComparavel(mixed $v): string {
        if ($v === null) return '';
        if (is_int($v) || is_float($v)) return (string)(float)$v;
        $v = trim((string)$v);
        if ($v !== '' && is_numeric($v)) return (string)(float)$v;
        return Competencias::normalizar($v);
    }
}
