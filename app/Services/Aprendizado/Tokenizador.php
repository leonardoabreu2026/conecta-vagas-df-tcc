<?php
declare(strict_types=1);

/**
 * Tokenizador: quebra um texto nas "palavras" que a máquina de aprendizado enxerga.
 *
 * O classificador (NaiveBayes) não lê frases: conta palavras. Então antes de aprender ou de
 * prever, todo texto passa por aqui e vira uma lista de pedaços comparáveis:
 *
 *   "Vale-Refeição de R$ 33,40 + Plano de Saúde"
 *     → ['vale', 'refeicao', '#dinheiro', 'plano', 'saude', 'vale refeicao', 'refeicao #dinheiro', ..., 'plano saude']
 *
 * Regras, na ordem:
 *  1. valores em dinheiro ("R$ 1.900") viram a marca #dinheiro — o valor exato não importa,
 *     importa saber que ali tem um valor;
 *  2. o texto é normalizado igual ao resto do sistema (Competencias::normalizar): minúsculas,
 *     sem acento, só letras e números; números soltos viram a marca #numero;
 *  3. palavras muito comuns ("de", "para", "com"...) saem, porque aparecem em qualquer seção e só
 *     atrapalham a conta;
 *  4. além das palavras sozinhas, entram os pares vizinhos ("plano saude"): o par diz muito mais
 *     que "plano" e "saude" separados.
 */
final class Tokenizador {
    /** Palavras que aparecem em todo tipo de frase e não ajudam a decidir nada. */
    private const PALAVRAS_VAZIAS = ['a','o','as','os','e','de','da','do','das','dos','em','no','na','nos','nas','um','uma','uns','umas',
        'para','pra','por','com','sem','que','se','ou','ao','aos','ate','mais','muito','como','sua','seu','suas','seus','nosso','nossa',
        'nossos','nossas','voce','voces','ser','ter','sao','esta','este','essa','esse','isso','ja','nao','sim','tambem','sobre','entre',
        'ai','la','aqui','pelo','pela','pelos','pelas','num','numa','the','and','of','to'];

    /** Tamanho máximo do texto lido (uma linha de anúncio ou um trecho de descrição; o resto é descartado). */
    private const MAX_CARACTERES = 2000;

    /**
     * Palavras (e pares de palavras vizinhas) de um texto.
     * @return list<string>
     */
    public static function palavras(string $texto): array {
        $texto = mb_substr($texto, 0, self::MAX_CARACTERES);
        // "R$ 1.900,00" antes de normalizar: depois disso o "R$" some e sobra só o número.
        $texto = preg_replace('/r\$\s*\d[\d.,]*/iu', ' #dinheiro ', $texto) ?? $texto;
        // A normalização apaga o "#"; por isso a marca atravessa a normalização como a palavra
        // "marcadinheiro" e volta a ser #dinheiro no laço abaixo.
        $base = Competencias::normalizar(str_replace('#dinheiro', ' marcadinheiro ', $texto));

        $soltas = [];
        foreach (explode(' ', $base) as $p) {
            if ($p === '' || in_array($p, self::PALAVRAS_VAZIAS, true)) continue;
            if ($p === 'marcadinheiro') { $soltas[] = '#dinheiro'; continue; }
            // 6x1, 12x36 e 14h ficam como estão (dizem muito sobre horário); número puro vira #numero.
            if (ctype_digit($p)) { $soltas[] = '#numero'; continue; }
            if (mb_strlen($p) < 2) continue;
            $soltas[] = mb_substr($p, 0, 40);
        }

        $pares = [];
        for ($i = 0; $i < count($soltas) - 1; $i++) {
            // "#numero #numero" (ex.: "08:00 às 17:00") não diz nada além das marcas sozinhas.
            if ($soltas[$i][0] === '#' && $soltas[$i + 1][0] === '#') continue;
            $pares[] = $soltas[$i].' '.$soltas[$i + 1];
        }
        return array_merge($soltas, $pares);
    }
}
