<?php
declare(strict_types=1);

/**
 * Acesso à tabela `matches`: nota de compatibilidade candidato × vaga calculada pelo
 * MatchService. A coluna detalhes guarda, em JSON, a explicação da nota.
 */
final class MatchDAO {
    public function salvar(int $pid, int $vid, float $score, string $nivel, array $detalhes = []): void {
        $s = Database::getConexao()->prepare(
            "INSERT INTO matches(perfil_candidato_id,vaga_id,pontuacao,nivel,detalhes) VALUES(?,?,?,?,?)
             ON DUPLICATE KEY UPDATE pontuacao=VALUES(pontuacao),nivel=VALUES(nivel),detalhes=VALUES(detalhes),visualizado=0"
        );
        $s->execute([$pid, $vid, $score, $nivel, json_encode($detalhes, JSON_UNESCAPED_UNICODE)]);
    }

    public function limparPorCandidato(int $pid): void {
        Database::getConexao()->prepare('DELETE FROM matches WHERE perfil_candidato_id=?')->execute([$pid]);
    }

    public function limparPorVaga(int $vid): void {
        Database::getConexao()->prepare('DELETE FROM matches WHERE vaga_id=?')->execute([$vid]);
    }

    /** Matches do candidato com vagas ativas, do maior para o menor. */
    public function listarPorCandidato(int $pid): array {
        $sql = "SELECT m.*, v.titulo, v.cidade, v.uf, v.imagem, v.remoto, v.salario_minimo, v.salario_maximo,
                       COALESCE(NULLIF(v.anunciante,''), NULLIF(p.nome_fantasia,''), u.nome) AS empresa_nome
                FROM matches m
                JOIN vagas v ON v.id=m.vaga_id AND v.status='ativa' AND (v.data_expiracao IS NULL OR v.data_expiracao>=CURDATE())
                JOIN perfis p ON p.id=v.perfil_empresa_id
                JOIN usuarios u ON u.id=p.usuario_id
                WHERE m.perfil_candidato_id=?
                ORDER BY m.pontuacao DESC, v.destaque DESC, v.created_at DESC";
        $s = Database::getConexao()->prepare($sql);
        $s->execute([$pid]);
        return array_map([self::class, 'decodificar'], $s->fetchAll());
    }

    public function buscar(int $pid, int $vid): ?array {
        $s = Database::getConexao()->prepare('SELECT * FROM matches WHERE perfil_candidato_id=? AND vaga_id=?');
        $s->execute([$pid, $vid]);
        $r = $s->fetch();
        return $r ? self::decodificar($r) : null;
    }

    /** @return array<int,float> vaga_id => pontuação */
    public function mapaPorCandidato(int $pid): array {
        $s = Database::getConexao()->prepare('SELECT vaga_id, pontuacao FROM matches WHERE perfil_candidato_id=?');
        $s->execute([$pid]);
        $out = [];
        foreach ($s->fetchAll() as $r) $out[(int)$r['vaga_id']] = (float)$r['pontuacao'];
        return $out;
    }

    public static function decodificar(array $r): array {
        $r['detalhes'] = is_string($r['detalhes'] ?? null) ? (json_decode($r['detalhes'], true) ?: []) : ($r['detalhes'] ?? []);
        return $r;
    }

    /**
     * Painel: compatibilidade entre os candidatos e as vagas ABERTAS (todas ou de uma empresa) —
     * quantidade de pares calculados, nota média e quantidade por faixa (baixo → excelente).
     */
    public function resumoVagasAbertas(?int $perfilEmpresaId = null): array {
        $sql = "SELECT COUNT(*) AS total, AVG(m.pontuacao) AS media,
                       SUM(m.nivel='baixo') AS baixo, SUM(m.nivel='medio') AS medio, SUM(m.nivel='alto') AS alto, SUM(m.nivel='excelente') AS excelente
                FROM matches m
                JOIN vagas v ON v.id=m.vaga_id AND v.status='ativa' AND (v.data_expiracao IS NULL OR v.data_expiracao>=CURDATE())"
             .($perfilEmpresaId ? " WHERE v.perfil_empresa_id=?" : '');
        $s = Database::getConexao()->prepare($sql);
        $s->execute($perfilEmpresaId ? [$perfilEmpresaId] : []);
        $r = $s->fetch() ?: [];
        return ['total' => (int)($r['total'] ?? 0), 'media' => isset($r['media']) ? (float)$r['media'] : null,
                'niveis' => ['baixo' => (int)($r['baixo'] ?? 0), 'medio' => (int)($r['medio'] ?? 0), 'alto' => (int)($r['alto'] ?? 0), 'excelente' => (int)($r['excelente'] ?? 0)]];
    }
}
