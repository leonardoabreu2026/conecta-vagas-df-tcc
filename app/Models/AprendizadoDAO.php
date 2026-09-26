<?php
declare(strict_types=1);

/**
 * Memória da máquina de aprendizado (tabelas aprendizado_*).
 *
 *  - aprendizado_exemplos : cada lição aprendida — um texto e a resposta certa que a pessoa confirmou
 *                           (ex.: "VT + VR" → beneficios). É o histórico que dá para conferir e apagar.
 *  - aprendizado_palavras : os contadores do Naive Bayes — quantas vezes cada palavra apareceu em
 *                           cada classe. É o "modelo" propriamente dito, atualizado a cada lição.
 *  - aprendizado_revisoes : uma linha por revisão salva (vaga, curso ou currículo) com quantos campos
 *                           a máquina acertou. É daqui que saem os gráficos de acerto do painel.
 *  - aprendizado_provas   : o "período de experiência" de cada modelo — quantas lições novas ele tentou
 *                           adivinhar antes de aprender e quantas acertou (libera o modelo para decidir).
 *  - aprendizado_calibracao: a temperatura de cada modelo (deixa a confiança honesta, ver Calibracao)
 *                           e o erro das previsões antes e depois de calibrar.
 *
 * Só SQL: quem transforma texto em palavras e decide o que aprender é a MaquinaAprendizado.
 *
 * As tabelas também estão no database/schema.sql. Se o banco foi criado antes delas existirem,
 * o DAO cria as cinco na primeira vez que precisar (instalar()) — assim nada quebra para quem
 * ainda não importou o schema novo.
 */
final class AprendizadoDAO {
    /** Valor de classe dos registros das memórias de nomes (empresas e instituições); esses registros não entram no Naive Bayes. */
    public const CLASSE_NOME = 'nome';

    // ------------------------------------------------------------------ modelo (Naive Bayes)

    /**
     * Tudo o que um modelo já aprendeu, no formato que o NaiveBayes::deContagens() recebe.
     * @return array{exemplos:array<string,int>,contagens:list<array{classe:string,palavra:string,contagem:int}>}
     */
    public function carregar(string $modelo): array {
        return $this->comTabelas(function () use ($modelo) {
            $db = Database::getConexao();
            $s = $db->prepare("SELECT classe, COUNT(*) AS n FROM aprendizado_exemplos WHERE modelo=? GROUP BY classe");
            $s->execute([$modelo]);
            $exemplos = array_map('intval', array_column($s->fetchAll(), 'n', 'classe'));
            $s = $db->prepare("SELECT classe, palavra, contagem FROM aprendizado_palavras WHERE modelo=? AND contagem>0");
            $s->execute([$modelo]);
            return ['exemplos' => $exemplos, 'contagens' => $s->fetchAll()];
        });
    }

    /**
     * Grava uma lição e atualiza os contadores, tudo na mesma transação.
     *
     * A mesma frase (mesma $chave) não é aprendida duas vezes:
     *  - mesma resposta de antes → só soma +1 em "vezes" (a lição foi confirmada de novo);
     *  - resposta diferente      → a correção mais recente vale: tira as palavras do texto antigo da classe
     *                              antiga e põe as do texto novo na classe nova. Exceção: se a lição já foi
     *                              confirmada 2 vezes ou mais, só troca com $podeTrocar (revisão do
     *                              administrador) — assim uma pessoa sozinha não desfaz o que várias ensinaram.
     *
     * @param callable(string):list<string> $palavrasDe como um texto vira palavras (o Tokenizador; o DAO não precisa conhecê-lo)
     * @param bool $contarVez false = lição que já existe fica como está, sem +1 (histórico rodado de novo)
     * @return array{status:string,classe_antiga:?string,palavras_antigas:list<string>}
     *         status: 'novo' | 'confirmado' | 'corrigido' | 'mantido' (a troca de resposta foi recusada)
     */
    public function registrarExemplo(string $modelo, string $classe, string $texto, string $chave, callable $palavrasDe, ?int $usuarioId,
                                     bool $podeTrocar = true, bool $contarVez = true): array {
        return $this->comTabelas(function () use ($modelo, $classe, $texto, $chave, $palavrasDe, $usuarioId, $podeTrocar, $contarVez) {
            $db = Database::getConexao();
            $db->beginTransaction();
            try {
                $s = $db->prepare("SELECT id, classe, texto, vezes FROM aprendizado_exemplos WHERE modelo=? AND chave=? FOR UPDATE");
                $s->execute([$modelo, $chave]);
                $antigo = $s->fetch() ?: null;
                $resultado = ['status' => 'novo', 'classe_antiga' => null, 'palavras_antigas' => []];

                if ($antigo && $antigo['classe'] === $classe) {
                    // Só o contador de vezes muda; os contadores de palavras não, para que uma frase
                    // repetida (a mesma linha em vinte anúncios) não domine o modelo.
                    if ($contarVez) $db->prepare("UPDATE aprendizado_exemplos SET vezes=vezes+1 WHERE id=?")->execute([$antigo['id']]);
                    $db->commit();
                    return ['status' => 'confirmado'] + $resultado;
                }
                if ($antigo && !$podeTrocar && (int)$antigo['vezes'] >= 2) {
                    $db->commit();
                    return ['status' => 'mantido'] + $resultado;
                }
                if ($antigo) {
                    // As palavras antigas saem do texto antigo: "R$ 1.900" e "R 1.900" têm a mesma chave, mas não as mesmas palavras.
                    $resultado = ['status' => 'corrigido', 'classe_antiga' => (string)$antigo['classe'], 'palavras_antigas' => $palavrasDe((string)$antigo['texto'])];
                    $this->somarPalavras($modelo, (string)$antigo['classe'], $resultado['palavras_antigas'], -1);
                    $db->prepare("UPDATE aprendizado_exemplos SET classe=?, texto=?, vezes=1, usuario_id=? WHERE id=?")
                       ->execute([$classe, mb_substr($texto, 0, 500), $usuarioId, $antigo['id']]);
                } else {
                    $db->prepare("INSERT INTO aprendizado_exemplos(modelo, classe, texto, chave, usuario_id) VALUES(?,?,?,?,?)")
                       ->execute([$modelo, $classe, mb_substr($texto, 0, 500), $chave, $usuarioId]);
                }
                $this->somarPalavras($modelo, $classe, $palavrasDe($texto), 1);
                $db->commit();
                return $resultado;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
        });
    }

    /**
     * Lições que um usuário ensinou num modelo (para apagá-las quando a conta dele é excluída).
     * @return list<array>
     */
    public function exemplosDoUsuario(int $usuarioId, string $modelo): array {
        return $this->comTabelas(function () use ($usuarioId, $modelo) {
            $s = Database::getConexao()->prepare("SELECT * FROM aprendizado_exemplos WHERE usuario_id=? AND modelo=?");
            $s->execute([$usuarioId, $modelo]);
            return $s->fetchAll();
        });
    }

    /** Uma lição pelo id (para o painel apagar). */
    public function buscarExemplo(int $id): ?array {
        return $this->comTabelas(function () use ($id) {
            $s = Database::getConexao()->prepare("SELECT * FROM aprendizado_exemplos WHERE id=?");
            $s->execute([$id]);
            return $s->fetch() ?: null;
        });
    }

    /**
     * Apaga uma lição e desconta as palavras dela do modelo.
     * @param list<string> $palavras as mesmas palavras usadas quando ela foi aprendida
     */
    public function removerExemplo(array $exemplo, array $palavras): bool {
        return $this->comTabelas(function () use ($exemplo, $palavras) {
            $db = Database::getConexao();
            $db->beginTransaction();
            try {
                if ($exemplo['classe'] !== self::CLASSE_NOME) $this->somarPalavras((string)$exemplo['modelo'], (string)$exemplo['classe'], $palavras, -1);
                $s = $db->prepare("DELETE FROM aprendizado_exemplos WHERE id=?");
                $s->execute([(int)$exemplo['id']]);
                $db->commit();
                return $s->rowCount() > 0;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                throw $e;
            }
        });
    }

    /** Esquece TUDO o que um modelo aprendeu, e as provas dele (volta a usar só as regras). As revisões ficam, são histórico. */
    public function zerar(string $modelo): int {
        return $this->comTabelas(function () use ($modelo) {
            $db = Database::getConexao();
            $db->prepare("DELETE FROM aprendizado_palavras WHERE modelo=?")->execute([$modelo]);
            $db->prepare("DELETE FROM aprendizado_provas WHERE modelo=?")->execute([$modelo]);
            $db->prepare("DELETE FROM aprendizado_calibracao WHERE modelo=?")->execute([$modelo]);
            $s = $db->prepare("DELETE FROM aprendizado_exemplos WHERE modelo=?");
            $s->execute([$modelo]);
            return $s->rowCount();
        });
    }

    // ------------------------------------------------------------------ período de experiência (provas)

    /**
     * Provas de cada modelo: quantas vezes ele tentou adivinhar uma lição nova ANTES de aprendê-la
     * (com confiança para decidir) e quantas acertou. É o que libera o modelo para decidir.
     * @return array<string,array{provas:int,acertos:int}>
     */
    public function provas(): array {
        return $this->comTabelas(function () {
            $out = [];
            foreach (Database::getConexao()->query("SELECT modelo, provas, acertos FROM aprendizado_provas")->fetchAll() as $l) {
                $out[(string)$l['modelo']] = ['provas' => (int)$l['provas'], 'acertos' => (int)$l['acertos']];
            }
            return $out;
        });
    }

    /** Soma uma prova (e um acerto, se ele acertou). */
    public function registrarProva(string $modelo, bool $acertou): void {
        $this->comTabelas(function () use ($modelo, $acertou) {
            Database::getConexao()->prepare("INSERT INTO aprendizado_provas(modelo, provas, acertos) VALUES(?, 1, ?)
                                             ON DUPLICATE KEY UPDATE provas = provas + 1, acertos = acertos + VALUES(acertos)")
                ->execute([$modelo, $acertou ? 1 : 0]);
        });
    }

    // ------------------------------------------------------------------ calibração (temperatura)

    /**
     * As lições de um modelo na ordem em que chegaram (para a calibração refazer as previsões).
     * @return list<array{texto:string,classe:string}>
     */
    public function licoesDoModelo(string $modelo): array {
        return $this->comTabelas(function () use ($modelo) {
            $s = Database::getConexao()->prepare("SELECT texto, classe FROM aprendizado_exemplos WHERE modelo=? AND classe<>? ORDER BY id");
            $s->execute([$modelo, self::CLASSE_NOME]);
            return $s->fetchAll();
        });
    }

    /** @return array<string,array{temperatura:float,licoes:int,nll_antes:float,nll_depois:float,amostras:int}> */
    public function calibracoes(): array {
        return $this->comTabelas(function () {
            $out = [];
            foreach (Database::getConexao()->query("SELECT * FROM aprendizado_calibracao")->fetchAll() as $l) {
                $out[(string)$l['modelo']] = ['temperatura' => (float)$l['temperatura'], 'licoes' => (int)$l['licoes'], 'amostras' => (int)$l['amostras'],
                                              'nll_antes' => (float)$l['nll_antes'], 'nll_depois' => (float)$l['nll_depois']];
            }
            return $out;
        });
    }

    /** Guarda (ou troca) a calibração de um modelo. */
    public function salvarCalibracao(string $modelo, float $temperatura, int $licoes, int $amostras, float $nllAntes, float $nllDepois): void {
        $this->comTabelas(function () use ($modelo, $temperatura, $licoes, $amostras, $nllAntes, $nllDepois) {
            Database::getConexao()->prepare("INSERT INTO aprendizado_calibracao(modelo, temperatura, licoes, amostras, nll_antes, nll_depois) VALUES(?,?,?,?,?,?)
                                             ON DUPLICATE KEY UPDATE temperatura=VALUES(temperatura), licoes=VALUES(licoes), amostras=VALUES(amostras),
                                             nll_antes=VALUES(nll_antes), nll_depois=VALUES(nll_depois)")
                ->execute([$modelo, $temperatura, $licoes, $amostras, $nllAntes, $nllDepois]);
        });
    }

    // ------------------------------------------------------------------ memória de nomes

    /**
     * Nomes confirmados pelas pessoas (empresas anunciantes, instituições de curso),
     * dos mais confirmados para os menos.
     * @return list<string>
     */
    public function nomes(string $modelo, int $limite = 500): array {
        return $this->comTabelas(function () use ($modelo, $limite) {
            $s = Database::getConexao()->prepare("SELECT texto FROM aprendizado_exemplos WHERE modelo=? AND classe=? ORDER BY vezes DESC, id DESC LIMIT ".max(1, $limite));
            $s->execute([$modelo, self::CLASSE_NOME]);
            return array_column($s->fetchAll(), 'texto');
        });
    }

    // ------------------------------------------------------------------ revisões (acerto da máquina)

    /**
     * Guarda o resultado de uma revisão: quantos campos e linhas a máquina acertou
     * e quantas lições saíram dela.
     * @param array{origem:string,campos:int,campos_certos:int,linhas:int,linhas_certas:int,licoes:int} $r
     */
    public function registrarRevisao(array $r, ?int $usuarioId): void {
        $this->comTabelas(function () use ($r, $usuarioId) {
            Database::getConexao()->prepare("INSERT INTO aprendizado_revisoes(origem, campos, campos_certos, linhas, linhas_certas, licoes, usuario_id) VALUES(?,?,?,?,?,?,?)")
                ->execute([$r['origem'], $r['campos'], $r['campos_certos'], $r['linhas'], $r['linhas_certas'], $r['licoes'], $usuarioId]);
        });
    }

    // ------------------------------------------------------------------ números do painel

    /**
     * Resumo de cada modelo: lições, classes e última lição.
     * @return array<string,array{licoes:int,classes:int,confirmacoes:int,ultima:?string}>
     */
    public function resumoModelos(): array {
        return $this->comTabelas(function () {
            $linhas = Database::getConexao()->query("SELECT modelo, COUNT(*) AS licoes, COUNT(DISTINCT classe) AS classes, SUM(vezes) AS confirmacoes, MAX(updated_at) AS ultima
                                                     FROM aprendizado_exemplos GROUP BY modelo")->fetchAll();
            $out = [];
            foreach ($linhas as $l) $out[$l['modelo']] = ['licoes' => (int)$l['licoes'], 'classes' => (int)$l['classes'], 'confirmacoes' => (int)$l['confirmacoes'], 'ultima' => $l['ultima']];
            return $out;
        });
    }

    /**
     * Revisões por origem: total, e a taxa de acerto das $janela primeiras e das $janela últimas (para comparar a evolução).
     * @return array<string,array{revisoes:int,acerto:?float,inicio:?float,recente:?float}>
     */
    public function resumoRevisoes(int $janela = 10): array {
        return $this->comTabelas(function () use ($janela) {
            $linhas = Database::getConexao()->query("SELECT origem, campos + linhas AS total, campos_certos + linhas_certas AS certos FROM aprendizado_revisoes ORDER BY id")->fetchAll();
            $por = [];
            foreach ($linhas as $l) $por[$l['origem']][] = $l;
            $taxa = function (array $ls): ?float {
                $t = array_sum(array_column($ls, 'total'));
                return $t > 0 ? round(100 * array_sum(array_column($ls, 'certos')) / $t, 1) : null;
            };
            $out = [];
            foreach ($por as $origem => $ls) {
                $out[$origem] = ['revisoes' => count($ls), 'acerto' => $taxa($ls), 'inicio' => $taxa(array_slice($ls, 0, $janela)), 'recente' => $taxa(array_slice($ls, -$janela))];
            }
            return $out;
        });
    }

    /**
     * Taxa de acerto de cada dia (acertos ÷ itens conferidos no dia; só os dias que tiveram revisão), em ordem de data.
     * @return array<string,float> 'Y-m-d' => % de acerto
     */
    public function acertoPorDia(int $dias = 60): array {
        return $this->comTabelas(function () use ($dias) {
            $s = Database::getConexao()->prepare("SELECT DATE(created_at) AS dia, SUM(campos_certos + linhas_certas) AS certos, SUM(campos + linhas) AS total
                                                  FROM aprendizado_revisoes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                                                  GROUP BY DATE(created_at) HAVING total > 0 ORDER BY dia");
            $s->execute([$dias]);
            $out = [];
            foreach ($s->fetchAll() as $l) $out[(string)$l['dia']] = round(100 * (int)$l['certos'] / (int)$l['total'], 1);
            return $out;
        });
    }

    /**
     * Últimas lições, com o nome de quem ensinou.
     * @return list<array>
     */
    public function ultimosExemplos(int $limite = 30, string $modelo = ''): array {
        return $this->comTabelas(function () use ($limite, $modelo) {
            $sql = "SELECT e.*, u.nome AS usuario_nome FROM aprendizado_exemplos e LEFT JOIN usuarios u ON u.id=e.usuario_id";
            $p = [];
            if ($modelo !== '') { $sql .= " WHERE e.modelo=?"; $p[] = $modelo; }
            $s = Database::getConexao()->prepare($sql." ORDER BY e.updated_at DESC, e.id DESC LIMIT ".max(1, $limite));
            $s->execute($p);
            return $s->fetchAll();
        });
    }

    // ------------------------------------------------------------------ apoio

    /**
     * +1 (ou -1) em cada palavra da classe. Várias palavras num único INSERT; o que chegar
     * a zero é apagado para a tabela não crescer à toa. Chamado dentro de uma transação.
     */
    private function somarPalavras(string $modelo, string $classe, array $palavras, int $sinal): void {
        if (!$palavras) return;
        $db = Database::getConexao();
        $vezes = array_count_values($palavras);   // a mesma palavra duas vezes na frase conta 2
        foreach (array_chunk($vezes, 200, true) as $lote) {
            $valores = []; $p = [];
            foreach ($lote as $palavra => $n) {
                $valores[] = '(?,?,?,?)';
                array_push($p, $modelo, $classe, mb_substr((string)$palavra, 0, 100), $sinal * $n);
            }
            $db->prepare("INSERT INTO aprendizado_palavras(modelo, classe, palavra, contagem) VALUES ".implode(',', $valores)."
                          ON DUPLICATE KEY UPDATE contagem = GREATEST(0, contagem + VALUES(contagem))")->execute($p);
        }
        if ($sinal < 0) $db->prepare("DELETE FROM aprendizado_palavras WHERE modelo=? AND classe=? AND contagem<=0")->execute([$modelo, $classe]);
    }

    /**
     * Executa a consulta; se a tabela ainda não existe (SQLSTATE 42S02), cria as tabelas e tenta de novo.
     * @template T
     * @param callable():T $consulta
     * @return T
     */
    private function comTabelas(callable $consulta): mixed {
        try {
            return $consulta();
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') throw $e;
            $this->instalar();
            return $consulta();
        }
    }

    /** Cria as tabelas do aprendizado (as mesmas do database/schema.sql). */
    public function instalar(): void {
        $db = Database::getConexao();
        $db->exec("CREATE TABLE IF NOT EXISTS aprendizado_exemplos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            modelo VARCHAR(40) NOT NULL,
            classe VARCHAR(100) NOT NULL,
            texto VARCHAR(500) NOT NULL,
            chave CHAR(40) NOT NULL,
            vezes INT NOT NULL DEFAULT 1,
            usuario_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_aprendizado_chave(modelo, chave),
            INDEX idx_aprendizado_classe(modelo, classe),
            FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS aprendizado_palavras (
            modelo VARCHAR(40) NOT NULL,
            classe VARCHAR(100) NOT NULL,
            palavra VARCHAR(100) NOT NULL,
            contagem INT NOT NULL DEFAULT 0,
            PRIMARY KEY(modelo, classe, palavra)
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS aprendizado_revisoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            origem VARCHAR(20) NOT NULL,
            campos INT NOT NULL DEFAULT 0,
            campos_certos INT NOT NULL DEFAULT 0,
            linhas INT NOT NULL DEFAULT 0,
            linhas_certas INT NOT NULL DEFAULT 0,
            licoes INT NOT NULL DEFAULT 0,
            usuario_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_revisao_origem(origem, created_at),
            FOREIGN KEY(usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS aprendizado_calibracao (
            modelo VARCHAR(40) NOT NULL PRIMARY KEY,
            temperatura DECIMAL(8,3) NOT NULL DEFAULT 1,
            licoes INT NOT NULL DEFAULT 0,
            amostras INT NOT NULL DEFAULT 0,
            nll_antes DECIMAL(10,4) NOT NULL DEFAULT 0,
            nll_depois DECIMAL(10,4) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS aprendizado_provas (
            modelo VARCHAR(40) NOT NULL PRIMARY KEY,
            provas INT NOT NULL DEFAULT 0,
            acertos INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    }
}
