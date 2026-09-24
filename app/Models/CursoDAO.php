<?php
declare(strict_types=1);

/**
 * Acesso à tabela `cursos` (cursos, e-books e vídeos de capacitação).
 * Só os ativos (ativo=1) aparecem para os usuários; o administrador vê todos.
 */
final class CursoDAO {
    public const TIPOS = ['curso','ebook','video'];
    public const MODALIDADES = ['ead','presencial','hibrido'];
    public const NIVEIS = ['iniciante','intermediario','avancado'];

    /** Filtros opcionais: q, categoria_id, gratuito ('1'). */
    public function listar(bool $ativos = true, array $f = []): array {
        $sql = "SELECT c.*, cat.nome categoria_nome FROM cursos c LEFT JOIN categorias cat ON cat.id=c.categoria_id WHERE 1=1";
        $p = [];
        if ($ativos) $sql .= " AND c.ativo=1";
        if (($f['q'] ?? '') !== '') { $sql .= " AND (c.titulo LIKE ? OR c.descricao LIKE ? OR c.instituicao LIKE ?)"; array_push($p, ...array_fill(0, 3, like($f['q']))); }
        if (!empty($f['categoria_id'])) { $sql .= " AND c.categoria_id=?"; $p[] = (int)$f['categoria_id']; }
        if (($f['gratuito'] ?? '') === '1') $sql .= " AND c.gratuito=1";
        $sql .= " ORDER BY c.created_at DESC, c.id DESC";
        $s = Database::getConexao()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public function buscar(int $id): ?array {
        $s = Database::getConexao()->prepare("SELECT c.*,cat.nome categoria_nome FROM cursos c LEFT JOIN categorias cat ON cat.id=c.categoria_id WHERE c.id=?");
        $s->execute([$id]);
        return $s->fetch() ?: null;
    }

    public function salvar(array $d, int $id = 0): int|false {
        $v = [
            $d['categoria_id'] ?: null, $d['titulo'], $d['descricao'] ?: null, $d['tipo'], $d['modalidade'], $d['nivel'], $d['duracao'] ?: null,
            (int)$d['gratuito'], $d['gratuito'] ? null : $d['preco'], $d['url'] ?: null, $d['imagem'] ?: null, $d['instituicao'] ?: null, (int)$d['ativo'],
        ];
        try {
            $db = Database::getConexao();
            if ($id) {
                $db->prepare("UPDATE cursos SET categoria_id=?,titulo=?,descricao=?,tipo=?,modalidade=?,nivel=?,duracao=?,gratuito=?,preco=?,url=?,imagem=?,instituicao=?,ativo=? WHERE id=?")->execute([...$v, $id]);
                return $id;
            }
            $db->prepare("INSERT INTO cursos(categoria_id,titulo,descricao,tipo,modalidade,nivel,duracao,gratuito,preco,url,imagem,instituicao,ativo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($v);
            return (int)$db->lastInsertId();
        } catch (Throwable) {
            return false;
        }
    }

    /** Conteúdo já cadastrado com o mesmo link ou com o mesmo título e instituição (evita duplicar na importação). */
    public function buscarRepetido(string $url, string $titulo, string $instituicao): ?array {
        $s = Database::getConexao()->prepare("SELECT id, titulo FROM cursos WHERE (url<>'' AND TRIM(TRAILING '/' FROM url)=TRIM(TRAILING '/' FROM ?)) OR (titulo=? AND COALESCE(instituicao,'')=?) LIMIT 1");
        $s->execute([trim($url), trim($titulo), trim($instituicao)]);
        return $s->fetch() ?: null;
    }

    /** Publicar (1) ou ocultar (0) com um clique. false = conteúdo não existe. */
    public function alterarAtivo(int $id, bool $ativo): bool {
        if (!$this->buscar($id)) return false;
        Database::getConexao()->prepare("UPDATE cursos SET ativo=? WHERE id=?")->execute([$ativo ? 1 : 0, $id]);
        return true;
    }

    public function excluir(int $id): bool {
        try {
            $db = Database::getConexao();
            $img = $db->prepare("SELECT imagem FROM cursos WHERE id=?");
            $img->execute([$id]);
            $imagem = (string)($img->fetchColumn() ?: '');
            $s = $db->prepare("DELETE FROM cursos WHERE id=?");
            $s->execute([$id]);
            if ($s->rowCount() < 1) return false;
            apagar_upload_sem_uso($imagem); // só apaga se for upload e ninguém mais usar
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
