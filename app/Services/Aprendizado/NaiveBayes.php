<?php
declare(strict_types=1);

/**
 * Classificador Naive Bayes multinomial: é ele que faz as previsões da máquina de aprendizado.
 *
 * É o mesmo algoritmo clássico usado em filtro de spam. A ideia, em linguagem simples:
 *
 *   Cada classe (ex.: "beneficios", "requisitos") tem um caderninho onde anota quantas vezes
 *   viu cada palavra. Depois de ver "vale refeicao" 30 vezes em benefícios e nenhuma em
 *   requisitos, a máquina passa a apostar que uma linha com "vale refeicao" é benefício.
 *
 * Vocabulário: "classe" é a resposta possível (em vaga_linha, o campo; em curriculo_linha, a seção;
 * nos modelos de categoria, a área). Um "exemplo" é uma lição: um texto com a classe certa.
 *
 * A conta (para cada classe c, dada a lista de palavras p1..pn do texto; só entram as palavras que o
 * modelo já viu, as desconhecidas são ignoradas):
 *
 *   pontos(c) = log P(c) + Σ log P(pi | c)
 *
 *   P(c)      = exemplos da classe / exemplos de todas as classes          (quão comum é a classe)
 *   P(p | c)  = (vezes que p apareceu em c + 1) / (palavras de c + tamanho do vocabulário)
 *
 * O "+ 1" é a suavização de Laplace: sem ele, uma palavra que nunca apareceu nesta classe (mas
 * apareceu em outra) zeraria a conta desta classe. Usamos logaritmo porque multiplicar muitas probabilidades pequenas dá um número tão
 * perto de zero que o computador arredonda para 0; somando logaritmos isso não acontece.
 * No fim, os pontos viram porcentagens (softmax) — é a "confiança" mostrada na tela.
 *
 * Por que "ingênuo" (naive)? Porque ele supõe que as palavras são independentes entre si, dada a
 * classe. Não é verdade, mas para classificar texto curto costuma funcionar bem, e é rápido e fácil
 * de explicar.
 *
 * APRENDIZADO INCREMENTAL: aprender é só somar nos contadores (aprender()) e esquecer é
 * subtrair (esquecer()). Não existe "treinar de novo do zero": cada correção já vale na
 * próxima leitura.
 *
 * Esta classe não conhece banco de dados nem tela: recebe contadores e devolve previsões.
 * Quem guarda os contadores é o AprendizadoDAO; quem decide quando usar é a MaquinaAprendizado.
 */
final class NaiveBayes {
    /** @var array<string,array<string,int>> classe => [palavra => vezes] */
    private array $palavras = [];
    /** @var array<string,int> classe => total de palavras vistas na classe */
    private array $totalPalavras = [];
    /** @var array<string,int> classe => quantos exemplos (textos) a classe já recebeu */
    private array $exemplos = [];
    /** @var array<string,int> palavra => em quantas classes ela aparece (para saber o tamanho do vocabulário) */
    private array $vocabulario = [];

    /**
     * Monta o classificador a partir do que já foi aprendido (vem do banco).
     * @param array<string,int> $exemplos classe => quantidade de exemplos
     * @param list<array{classe:string,palavra:string,contagem:int}> $contagens
     */
    public static function deContagens(array $exemplos, array $contagens): self {
        $nb = new self();
        foreach ($exemplos as $classe => $n) if ((int)$n > 0) $nb->exemplos[(string)$classe] = (int)$n;
        foreach ($contagens as $c) $nb->somarPalavra((string)$c['classe'], (string)$c['palavra'], (int)$c['contagem']);
        return $nb;
    }

    /** Aprende um exemplo: "estas palavras pertencem a esta classe". */
    public function aprender(array $palavras, string $classe): void {
        if ($classe === '' || !$palavras) return;
        $this->exemplos[$classe] = ($this->exemplos[$classe] ?? 0) + 1;
        foreach ($palavras as $p) $this->somarPalavra($classe, $p, 1);
    }

    /** Desfaz um exemplo aprendido antes (correção errada, ou a pessoa mudou a resposta). */
    public function esquecer(array $palavras, string $classe): void {
        if (!isset($this->exemplos[$classe]) || !$palavras) return;
        $this->exemplos[$classe]--;
        foreach ($palavras as $p) $this->somarPalavra($classe, $p, -1);
        // Sem exemplos a classe deixa de existir para a previsão (as palavras dela já foram zeradas acima).
        if ($this->exemplos[$classe] <= 0) unset($this->exemplos[$classe]);
    }

    /**
     * Prevê a classe de um texto já quebrado em palavras.
     * Devolve null quando não dá para opinar: nada aprendido ainda, ou nenhuma palavra do
     * texto foi vista antes (aí a máquina estaria só chutando a classe mais comum).
     *
     * @return array{classe:string,confianca:float,probabilidades:array<string,float>}|null
     */
    public function prever(array $palavras): ?array {
        if (!$this->exemplos) return null;
        // Palavras que o modelo nunca viu são ignoradas: não ajudam nenhuma classe.
        $conhecidas = array_values(array_filter($palavras, fn($p) => isset($this->vocabulario[$p])));
        if (!$conhecidas) return null;

        $pontos = [];
        // (string): uma classe só com números ("2024") vira chave inteira no array do PHP.
        foreach ($this->exemplos as $classe => $_) $pontos[(string)$classe] = $this->pontos($conhecidas, (string)$classe);

        // Softmax: transforma os pontos (logaritmos) em porcentagens que somam 100%.
        // Os pontos são números negativos grandes (ex.: -800) e exp(-800) dá 0 no computador, o que faria
        // a divisão virar 0/0. Subtraindo o maior, a classe vencedora fica com exp(0) = 1 e as outras entre 0 e 1.
        $maior = max($pontos);
        $exp = array_map(fn($v) => exp($v - $maior), $pontos);
        $soma = array_sum($exp);
        $prob = array_map(fn($v) => $v / $soma, $exp);
        arsort($prob);
        $classe = (string)array_key_first($prob);
        return ['classe' => $classe, 'confianca' => $prob[$classe], 'probabilidades' => $prob];
    }

    /**
     * Explica a decisão: as palavras do texto que mais puxaram para a classe escolhida,
     * comparando com a média das outras classes. É o que a tela mostra como "por quê".
     * @return array<string,float> palavra => força (quanto maior, mais puxou para a classe)
     */
    public function explicar(array $palavras, string $classe, int $limite = 5): array {
        if (!isset($this->exemplos[$classe]) || count($this->exemplos) < 2) return [];
        $forca = [];
        foreach (array_unique($palavras) as $p) {
            if (!isset($this->vocabulario[$p])) continue;
            $outras = [];
            foreach ($this->exemplos as $c => $_) if ((string)$c !== $classe) $outras[] = $this->logChance((string)$p, (string)$c);
            $f = $this->logChance((string)$p, $classe) - array_sum($outras) / count($outras);
            if ($f > 0) $forca[(string)$p] = round($f, 2);
        }
        arsort($forca);
        return array_slice($forca, 0, $limite, true);
    }

    /**
     * Palavras mais típicas de uma classe (as que mais a diferenciam das outras).
     * O painel usa para mostrar "o que a máquina aprendeu" sobre cada campo.
     * @return list<string>
     */
    public function palavrasTipicas(string $classe, int $limite = 8): array {
        $lista = $this->palavras[$classe] ?? [];
        // Só palavras vistas mais de uma vez: uma única aparição ainda é coincidência.
        $lista = array_filter($lista, fn($n) => $n >= 2);
        return array_keys($this->explicar(array_keys($lista), $classe, $limite));
    }

    /** @return array<string,int> classe => quantos exemplos já aprendeu */
    public function exemplosPorClasse(): array {
        $e = $this->exemplos;
        arsort($e);
        return $e;
    }

    public function totalExemplos(): int {
        return array_sum($this->exemplos);
    }

    // ------------------------------------------------------------------ contas internas

    /** log P(c) + Σ log P(p|c) — ver a fórmula no topo do arquivo. */
    private function pontos(array $palavras, string $classe): float {
        $total = log($this->exemplos[$classe] / $this->totalExemplos());
        foreach ($palavras as $p) $total += $this->logChance($p, $classe);
        return $total;
    }

    /** log P(palavra | classe), com a suavização de Laplace (+1). */
    private function logChance(string $palavra, string $classe): float {
        $vezes = $this->palavras[$classe][$palavra] ?? 0;
        return log(($vezes + 1) / (($this->totalPalavras[$classe] ?? 0) + count($this->vocabulario)));
    }

    /** Soma (ou subtrai, com $n negativo) uma palavra no caderninho da classe. */
    private function somarPalavra(string $classe, string $palavra, int $n): void {
        if ($palavra === '' || $n === 0) return;
        $antes = $this->palavras[$classe][$palavra] ?? 0;
        $depois = max(0, $antes + $n);
        $this->totalPalavras[$classe] = max(0, ($this->totalPalavras[$classe] ?? 0) + ($depois - $antes));
        if ($depois > 0) {
            $this->palavras[$classe][$palavra] = $depois;
            if ($antes === 0) $this->vocabulario[$palavra] = ($this->vocabulario[$palavra] ?? 0) + 1;
        } elseif ($antes > 0) {
            unset($this->palavras[$classe][$palavra]);
            if (--$this->vocabulario[$palavra] <= 0) unset($this->vocabulario[$palavra]);
        }
    }
}
