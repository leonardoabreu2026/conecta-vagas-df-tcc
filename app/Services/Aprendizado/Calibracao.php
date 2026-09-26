<?php
declare(strict_types=1);

/**
 * Calibração por temperatura (temperature scaling): deixa a "confiança" do Naive Bayes honesta.
 *
 * O problema: o Naive Bayes soma a força de cada palavra como se elas fossem independentes, e por
 * isso exagera. Nos dados de demonstração, o modelo da área da vaga dizia ter 96,7% de confiança,
 * em média, mas acertava só 52,6%. Uma confiança assim não serve para decidir nada.
 *
 * A correção: dividir os pontos de cada classe por uma TEMPERATURA T antes de virar porcentagem.
 *
 *   probabilidade(c) = exp(pontos(c) / T) / Σ exp(pontos(k) / T)
 *
 *   T = 1  → nada muda;
 *   T > 1  → as porcentagens ficam menos extremas ("esfria" a certeza exagerada).
 *
 * Qual T usar? O que MINIMIZA o erro das previsões que o modelo fez antes de conhecer cada lição
 * (avaliação prequencial). O erro usado é a log-verossimilhança negativa (NLL), a medida padrão para
 * probabilidades: ela castiga muito quem diz 99% e erra.
 *
 *   NLL(T) = média de  −log( probabilidade que o modelo deu para a resposta certa, com a temperatura T )
 *
 * A busca é a da seção áurea (golden-section search) sobre log(T): a NLL tem um mínimo só nesse
 * intervalo, e a busca vai estreitando o intervalo até achar o fundo, sem precisar de derivada.
 *
 * Referência: GUO et al., "On Calibration of Modern Neural Networks", ICML 2017.
 *
 * O softmax com temperatura em si fica em NaiveBayes::softmax().
 * Só contas: recebe as lições em ordem e devolve a temperatura. Quem busca as lições no banco e guarda
 * o resultado é a MaquinaAprendizado (com o AprendizadoDAO).
 */
final class Calibracao {
    /** Com menos previsões do que isso a estimativa não é confiável: fica T = 1 (sem calibrar). */
    public const MIN_AMOSTRAS = 20;
    /** Faixa de busca da temperatura. */
    private const T_MINIMA = 1.0;
    private const T_MAXIMA = 100.0;

    /**
     * Refaz o aprendizado do zero, na ordem em que as lições chegaram, e guarda cada previsão feita
     * ANTES de o modelo conhecer a lição (os pontos de cada classe e a resposta certa).
     * Só entram previsões com o modelo já formado (mínimo de lições, 2+ classes) e cuja resposta
     * certa o modelo já conhecia (senão não há probabilidade para medir).
     *
     * @param list<array{0:list<string>,1:string}> $licoes [palavras, classe], da mais antiga para a mais nova
     * @return list<array{0:array<string,float>,1:string}> [pontos por classe, classe certa]
     */
    public static function amostrasPrequenciais(array $licoes, int $minLicoes): array {
        $nb = new NaiveBayes();
        $amostras = [];
        foreach ($licoes as [$palavras, $classe]) {
            $porClasse = $nb->exemplosPorClasse();
            if ($nb->totalExemplos() >= $minLicoes && count($porClasse) >= 2 && isset($porClasse[$classe])) {
                $p = $nb->prever($palavras);
                if ($p !== null) $amostras[] = [$p['pontos'], $classe];
            }
            $nb->aprender($palavras, $classe);
        }
        return $amostras;
    }

    /**
     * A temperatura que minimiza a NLL das amostras (1.0 quando há poucas amostras).
     * @param list<array{0:array<string,float>,1:string}> $amostras
     */
    public static function temperatura(array $amostras): float {
        if (count($amostras) < self::MIN_AMOSTRAS) return 1.0;
        // Seção áurea em log(T): a cada passo, descarta a parte do intervalo onde o mínimo não pode estar.
        $a = log(self::T_MINIMA); $b = log(self::T_MAXIMA);
        $razao = (sqrt(5) - 1) / 2;   // 0,618...
        for ($i = 0; $i < 60; $i++) {
            $c = $b - $razao * ($b - $a);
            $d = $a + $razao * ($b - $a);
            if (self::nll($amostras, exp($c)) < self::nll($amostras, exp($d))) $b = $d; else $a = $c;
        }
        return round(exp(($a + $b) / 2), 3);
    }

    /**
     * Log-verossimilhança negativa média: quanto menor, melhor as porcentagens descrevem a realidade.
     * @param list<array{0:array<string,float>,1:string}> $amostras
     */
    public static function nll(array $amostras, float $temperatura): float {
        if (!$amostras) return 0.0;
        $total = 0.0;
        foreach ($amostras as [$pontos, $certa]) {
            $prob = NaiveBayes::softmax($pontos, $temperatura);
            $total -= log(max($prob[$certa] ?? 0.0, 1e-12));
        }
        return $total / count($amostras);
    }
}
