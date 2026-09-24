<?php
declare(strict_types=1);

/**
 * Falha ao conectar no MySQL/MariaDB. A mensagem é amigável (pode ir para a tela);
 * o erro técnico original fica em getPrevious() e só aparece com DEBUG=true.
 */
final class DatabaseException extends RuntimeException {}

/**
 * Conexão com o banco de dados (MySQL/MariaDB do XAMPP) usando PDO.
 *
 * Padrão Singleton: existe UMA conexão por requisição, criada na primeira vez
 * que alguém chama Database::getConexao() e reaproveitada nas chamadas seguintes.
 *
 * Configuração da conexão:
 *  - erros viram exceções (PDO::ERRMODE_EXCEPTION);
 *  - prepared statements reais (EMULATE_PREPARES=false), contra SQL injection;
 *  - utf8mb4 (acentos e emojis) + fuso igual ao do PHP (NOW()/CURDATE() batem com date()).
 *
 * Os dados de acesso (DB_HOST, DB_NAME...) ficam em config/config.php.
 */
final class Database {
    private static ?PDO $instance = null;
    private function __construct() {}

    public static function getConexao(): PDO {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        if (!extension_loaded('pdo_mysql') || !in_array('mysql', PDO::getAvailableDrivers(), true)) {
            throw new DatabaseException(
                'A extensão PDO MySQL não está habilitada no PHP. '
                .'No XAMPP, verifique no php.ini se a linha "extension=pdo_mysql" está ativa.'
            );
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5, // não deixa a página "pendurada" se o MySQL não responder
        ];

        // Ordem de tentativa: socket configurado (Linux) → host TCP → host alternativo → sockets comuns.
        $dsns = [];
        if (DB_SOCKET !== '' && @file_exists(DB_SOCKET)) {
            $dsns[] = 'mysql:unix_socket='.DB_SOCKET.';dbname='.DB_NAME.';charset=utf8mb4';
        }
        $hosts = [DB_HOST];
        if (DB_HOST === '127.0.0.1') $hosts[] = 'localhost';
        if (DB_HOST === 'localhost') $hosts[] = '127.0.0.1';
        foreach (array_unique($hosts) as $host) {
            $dsns[] = 'mysql:host='.$host.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            foreach (['/opt/lampp/var/mysql/mysql.sock', '/var/run/mysqld/mysqld.sock'] as $sock) {
                if ($sock !== DB_SOCKET && @file_exists($sock)) $dsns[] = 'mysql:unix_socket='.$sock.';dbname='.DB_NAME.';charset=utf8mb4';
            }
        }

        $ultimo = null;
        foreach (array_unique($dsns) as $dsn) {
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::configurarSessao($pdo);
                return self::$instance = $pdo;
            } catch (PDOException $e) {
                $ultimo = $e;
                // Banco inexistente ou senha errada: tentar outro host não resolve.
                if (in_array((int)$e->getCode(), [1049, 1044, 1045], true)) break;
            }
        }

        throw new DatabaseException(self::mensagemAmigavel($ultimo), 0, $ultimo);
    }

    /** Traduz os códigos de erro mais comuns do MySQL para uma orientação clara. */
    private static function mensagemAmigavel(?PDOException $e): string {
        $codigo = $e ? (int)$e->getCode() : 0;
        return match ($codigo) {
            1049 => 'O banco de dados "'.DB_NAME.'" não existe. Importe database/schema.sql e depois database/seed.sql pelo phpMyAdmin ou pelo terminal.',
            1044, 1045 => 'O MySQL recusou o usuário/senha configurados (DB_USER/DB_PASS em config/config.php).',
            2002, 2003, 2006 => 'Não foi possível falar com o MySQL/MariaDB: o serviço parece estar desligado.',
            default => 'Não foi possível conectar ao MySQL/MariaDB.',
        };
    }

    private static function configurarSessao(PDO $pdo): void {
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        // Usa o mesmo deslocamento do fuso do PHP (America/Sao_Paulo = -03:00).
        $pdo->exec("SET time_zone = '".(new DateTime())->format('P')."'");
    }
}
