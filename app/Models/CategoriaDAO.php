<?php
declare(strict_types=1);

/**
 * Acesso à tabela `categorias` (áreas usadas para agrupar vagas e cursos).
 * O campo tipo diz onde a categoria é usada: 'vaga' ou 'curso'.
 */
final class CategoriaDAO {
    public const TIPOS = ['vaga','curso'];
    /** Mensagem do último erro de salvar/excluir, para exibir ao usuário. */
    public string $erro = '';

    public function listar(?string $tipo = null, bool $apenasAtivas = false): array {
        $sql = "SELECT c.*,
                  (SELECT COUNT(*) FROM vagas v WHERE v.categoria_id=c.id) + (SELECT COUNT(*) FROM cursos cu WHERE cu.categoria_id=c.id) AS em_uso
                FROM categorias c WHERE 1=1";
        $p = [];
        if ($tipo) { $sql .= " AND c.tipo=?"; $p[] = $tipo; }
        if ($apenasAtivas) $sql .= " AND c.ativo=1";
        $sql .= " ORDER BY c.tipo, c.nome";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public function salvar(array $d, int $id = 0): int|false {
        $this->erro = '';
        try {
            $db = Database::getConexao();
            if ($id) {
                $db->prepare("UPDATE categorias SET nome=?,tipo=?,ativo=? WHERE id=?")->execute([$d['nome'], $d['tipo'], (int)$d['ativo'], $id]);
                return $id;
            }
            $db->prepare("INSERT INTO categorias(nome,tipo,ativo) VALUES(?,?,?)")->execute([$d['nome'], $d['tipo'], (int)$d['ativo']]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            $this->erro = $e->getCode() === '23000' ? 'Já existe uma categoria com esse nome para esse tipo.' : 'Erro ao salvar a categoria.';
            return false;
        }
    }

    public function buscar(int $id): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM categorias WHERE id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function buscarPorNome(string $nome, string $tipo): ?array {
        $s = Database::getConexao()->prepare("SELECT * FROM categorias WHERE nome=? AND tipo=?");
        $s->execute([$nome, $tipo]);
        return $s->fetch() ?: null;
    }

    /** Exclui a categoria; vagas/cursos que a usavam ficam sem categoria (FK ON DELETE SET NULL). */
    public function excluir(int $id): bool {
        try {
            $s = Database::getConexao()->prepare("DELETE FROM categorias WHERE id=?");
            $s->execute([$id]);
            return $s->rowCount() > 0;
        } catch (Throwable) {
            $this->erro = 'Erro ao excluir a categoria.';
            return false;
        }
    }
}
