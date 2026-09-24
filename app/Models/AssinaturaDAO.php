<?php
declare(strict_types=1);

/**
 * Acesso à tabela `assinaturas` e às REGRAS DOS PLANOS (conferidas no servidor):
 *  - Candidato gratuito: até 3 candidaturas ativas; VIP ('assinante'): ilimitado e no topo.
 *  - Empresa básica: até 2 vagas abertas; Premium ('empresa'): ilimitado, destaque e banco de talentos.
 * Cobrança demonstrativa: a assinatura é gravada após a confirmação em planos.php.
 * Ao ser criado, o DAO marca como expiradas as assinaturas vencidas (uma vez por requisição).
 */
final class AssinaturaDAO {
    /** Já conferiu as assinaturas vencidas nesta requisição? (vários DAOs são criados por página) */
    private static bool $expiracaoConferida = false;

    public function __construct() {
        $this->expirarAssinaturas();
    }

    /**
     * Marca automaticamente assinaturas vencidas. Roda só na primeira instância da requisição.
     */
    private function expirarAssinaturas(): void {
        if (self::$expiracaoConferida) return;
        self::$expiracaoConferida = true;
        try {
            $db = Database::getConexao();
            // Empresas cujo Premium vai expirar agora: perdem o destaque das vagas.
            $venc = $db->query("SELECT DISTINCT usuario_id FROM assinaturas
                                WHERE status = 'ativa' AND plano = 'empresa' AND data_fim < CURDATE()")->fetchAll(PDO::FETCH_COLUMN);
            $db->exec("UPDATE assinaturas
                       SET status = 'expirada'
                       WHERE status = 'ativa'
                         AND data_fim IS NOT NULL
                         AND data_fim < CURDATE()");
            foreach ($venc as $uid) $this->removerDestaqueSemPremium((int)$uid);
        } catch (Throwable) {
            // A consulta de assinatura também verifica a data, evitando liberar acesso vencido.
        }
    }

    /**
     * Busca a assinatura ativa e ainda válida do usuário.
     */
    public function buscarAtivaPorUsuario(int $usuarioId): ?array {
        try {
            $sql = "SELECT *
                    FROM assinaturas
                    WHERE usuario_id = ?
                      AND status = 'ativa'
                      AND data_fim >= CURDATE()
                    ORDER BY id DESC
                    LIMIT 1";
            $s = Database::getConexao()->prepare($sql);
            $s->execute([$usuarioId]);
            $ass = $s->fetch();
            return $ass ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Verifica se o usuário tem o plano VIP de candidato.
     */
    public function isCandidatoVip(int $usuarioId): bool {
        $ass = $this->buscarAtivaPorUsuario($usuarioId);
        return $ass !== null && $ass['plano'] === 'assinante';
    }

    /**
     * Verifica se a empresa tem o plano Premium.
     */
    public function isEmpresaPremium(int $usuarioId): bool {
        $ass = $this->buscarAtivaPorUsuario($usuarioId);
        return $ass !== null && $ass['plano'] === 'empresa';
    }

    /**
     * Cria uma nova assinatura.
     *
     * Nesta versão a cobrança é local/demonstrativa: a assinatura é registrada
     * no banco após a confirmação do usuário em planos.php.
     */
    public function assinar(int $usuarioId, string $plano, float $valor, int $dias = 30): bool {
        $planosPermitidos = ['assinante', 'empresa'];
        if (!in_array($plano, $planosPermitidos, true) || $dias < 1 || $valor < 0) {
            return false;
        }

        try {
            $db = Database::getConexao();
            $db->beginTransaction();

            $usuario = $db->prepare("SELECT tipo FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1");
            $usuario->execute([$usuarioId]);
            $tipoUsuario = $usuario->fetchColumn();

            if ($tipoUsuario === false) {
                $db->rollBack();
                return false;
            }

            if (($plano === 'assinante' && !in_array($tipoUsuario, ['candidato', 'admin'], true)) ||
                ($plano === 'empresa' && !in_array($tipoUsuario, ['empresa', 'admin'], true))) {
                $db->rollBack();
                return false;
            }

            // Não deixa duas assinaturas pagas ativas para a mesma conta.
            $cancelar = $db->prepare("UPDATE assinaturas
                                      SET status = 'cancelada'
                                      WHERE usuario_id = ?
                                        AND status = 'ativa'");
            $cancelar->execute([$usuarioId]);

            $ins = $db->prepare("INSERT INTO assinaturas
                (usuario_id, plano, valor, data_inicio, data_fim, status)
                VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'ativa')");
            $ins->execute([$usuarioId, $plano, number_format($valor, 2, '.', ''), $dias]);

            $db->commit();
            return true;
        } catch (Throwable) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    /**
     * Cancela a assinatura ativa do usuário sem apagar o histórico.
     */
    public function cancelar(int $usuarioId): bool {
        try {
            $sql = "UPDATE assinaturas
                    SET status = 'cancelada'
                    WHERE usuario_id = ?
                      AND status = 'ativa'";
            $s = Database::getConexao()->prepare($sql);
            $s->execute([$usuarioId]);
            $ok = $s->rowCount() > 0;
            if ($ok) $this->removerDestaqueSemPremium($usuarioId);
            return $ok;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Destaque é recurso do Premium: sem assinatura vigente, as vagas da empresa
     * deixam de ficar no topo. (As vagas continuam ativas; só novas publicações
     * passam a respeitar o limite do plano básico.)
     */
    private function removerDestaqueSemPremium(int $usuarioId): void {
        try {
            if ($this->isEmpresaPremium($usuarioId)) return;
            Database::getConexao()->prepare("UPDATE vagas v JOIN perfis p ON p.id = v.perfil_empresa_id
                                            SET v.destaque = 0 WHERE p.usuario_id = ? AND v.destaque = 1")->execute([$usuarioId]);
        } catch (Throwable) {}
    }

    /**
     * Histórico de assinaturas da conta, do mais recente para o mais antigo.
     */
    public function listarPorUsuario(int $usuarioId): array {
        try {
            $s = Database::getConexao()->prepare(
                "SELECT * FROM assinaturas WHERE usuario_id = ? ORDER BY id DESC"
            );
            $s->execute([$usuarioId]);
            return $s->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Conta candidaturas ativas de um candidato.
     */
    public function contarCandidaturasAtivas(int $perfilCandidatoId): int {
        try {
            $sql = "SELECT COUNT(*) FROM candidaturas
                    WHERE perfil_candidato_id = ?
                      AND status IN ('enviada','em_analise','entrevista')";
            $s = Database::getConexao()->prepare($sql);
            $s->execute([$perfilCandidatoId]);
            return (int)$s->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Conta vagas ativas de uma empresa.
     */
    public function contarVagasAtivas(int $perfilEmpresaId): int {
        try {
            $sql = "SELECT COUNT(*) FROM vagas
                    WHERE perfil_empresa_id = ?
                      AND status = 'ativa'
                      AND (data_expiracao IS NULL OR data_expiracao >= CURDATE())";
            $s = Database::getConexao()->prepare($sql);
            $s->execute([$perfilEmpresaId]);
            return (int)$s->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Valida se candidato pode se candidatar.
     * Plano gratuito: até 3 candidaturas ativas.
     */
    public function podeCandidatar(int $usuarioId, int $perfilCandidatoId): array {
        if ($this->isCandidatoVip($usuarioId)) {
            return ['permitido' => true, 'motivo' => 'Candidato VIP (Ilimitado)'];
        }

        $ativas = $this->contarCandidaturasAtivas($perfilCandidatoId);
        $limite = 3;

        if ($ativas >= $limite) {
            return [
                'permitido' => false,
                'motivo' => "Você atingiu o limite de {$limite} candidaturas ativas do Plano Gratuito. Torne-se Candidato VIP para enviar candidaturas ilimitadas!",
                'ativas' => $ativas,
                'limite' => $limite
            ];
        }

        return [
            'permitido' => true,
            'motivo' => 'Dentro do limite gratuito',
            'ativas' => $ativas,
            'limite' => $limite
        ];
    }

    /**
     * Valida se empresa pode cadastrar vaga.
     * Plano básico: até 2 vagas ativas; Premium: ilimitado.
     */
    public function podePublicarVaga(int $usuarioId, int $perfilEmpresaId): array {
        if ($this->isEmpresaPremium($usuarioId)) {
            return ['permitido' => true, 'motivo' => 'Empresa Premium (Ilimitado)'];
        }

        $ativas = $this->contarVagasAtivas($perfilEmpresaId);
        $limite = 2;

        if ($ativas >= $limite) {
            return [
                'permitido' => false,
                'motivo' => "Sua empresa atingiu o limite de {$limite} vagas ativas do Plano Básico Gratuito. Assine o Plano Empresa Premium para publicar vagas ilimitadas!",
                'ativas' => $ativas,
                'limite' => $limite
            ];
        }

        return [
            'permitido' => true,
            'motivo' => 'Dentro do limite gratuito',
            'ativas' => $ativas,
            'limite' => $limite
        ];
    }

    /**
     * Painel do administrador: assinaturas por plano — vigentes (ativas e no prazo), canceladas,
     * expiradas e a receita mensal das vigentes (valores demonstrativos, sem cobrança real).
     * @return array<string,array{vigentes:int,canceladas:int,expiradas:int,receita:float}> 'assinante' e 'empresa'
     */
    public function resumoPorPlano(): array {
        $out = [];
        foreach (['assinante', 'empresa'] as $p) $out[$p] = ['vigentes' => 0, 'canceladas' => 0, 'expiradas' => 0, 'receita' => 0.0];
        $sql = "SELECT plano, SUM(status='ativa' AND data_fim>=CURDATE()) AS vigentes, SUM(status='cancelada') AS canceladas,
                       SUM(status='expirada' OR (status='ativa' AND data_fim<CURDATE())) AS expiradas,
                       SUM(CASE WHEN status='ativa' AND data_fim>=CURDATE() THEN valor ELSE 0 END) AS receita
                FROM assinaturas GROUP BY plano";
        foreach (Database::getConexao()->query($sql)->fetchAll() as $r) {
            if (!isset($out[$r['plano']])) continue;
            $out[$r['plano']] = ['vigentes' => (int)$r['vigentes'], 'canceladas' => (int)$r['canceladas'], 'expiradas' => (int)$r['expiradas'], 'receita' => (float)$r['receita']];
        }
        return $out;
    }
}
