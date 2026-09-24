<?php
declare(strict_types=1);

/**
 * Montagem das telas (a "V" do MVC).
 *
 * Toda tela é um arquivo em app/Views/ que só EXIBE dados: as consultas e as
 * regras ficam no controller, que entrega as variáveis prontas para a view.
 */
final class View {
    /**
     * Mostra uma tela dentro do layout padrão: cabeçalho + tela + rodapé.
     *
     * @param string $__tela  caminho da view dentro de app/Views, sem ".php" (ex.: 'vagas/lista')
     * @param array  $__dados variáveis que a view vai usar (o controller passa get_defined_vars())
     *
     * Os nomes com "__" evitam conflito com as variáveis da própria tela.
     * O cabeçalho usa $title, $layoutLargo, $descricaoPagina e $ogImagem, se existirem.
     */
    public static function render(string $__tela, array $__dados = []): void {
        extract($__dados, EXTR_SKIP);
        unset($__dados);
        require APP_DIR.'/Views/layouts/header.php';
        require APP_DIR.'/Views/'.$__tela.'.php';
        require APP_DIR.'/Views/layouts/footer.php';
    }
}

/**
 * Página de erro simples (sem o layout do site) e encerra a requisição.
 * Usada para 403/404/500/503. $mensagem pode conter HTML montado pelo sistema;
 * $detalhe (técnico) só aparece com DEBUG=true.
 */
function pagina_erro(int $codigo, string $titulo, string $mensagem, string $detalhe = ''): never {
    if (!headers_sent()) {
        http_response_code($codigo);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $inicio = defined('SITE_URL') ? url('index.php') : '/';
    require APP_DIR.'/Views/errors/erro.php';
    exit;
}

/**
 * Tratador global de exceções: nenhum erro inesperado vira "Fatal error" cru na tela.
 * Registrado em app/Core/bootstrap.php. O erro completo vai para o log do PHP/Apache.
 */
function tratar_excecao(Throwable $ex): never {
    error_log('[Conecta Vagas DF] '.get_class($ex).': '.$ex->getMessage().' em '.$ex->getFile().':'.$ex->getLine());
    $detalhe = get_class($ex).': '.$ex->getMessage()."\n".$ex->getFile().':'.$ex->getLine()
        .($ex->getPrevious() ? "\nCausa: ".$ex->getPrevious()->getMessage() : '');
    if ($ex instanceof DatabaseException) {
        // Em produção (DEBUG=false) o visitante não vê nome do banco nem dicas de instalação.
        if (!DEBUG) {
            pagina_erro(503, 'Serviço indisponível', '<p>Estamos com uma instabilidade momentânea. Tente novamente em instantes.</p>');
        }
        pagina_erro(503, 'Banco de dados indisponível',
            '<p>'.e($ex->getMessage()).'</p><ul>'
            .'<li>Abra o <b>XAMPP Control Panel</b> e confirme que o <b>MySQL</b> está com o status verde (Start).</li>'
            .'<li>Confirme que o banco <b>'.e(DB_NAME).'</b> foi importado (arquivos <code>database/schema.sql</code> e <code>database/seed.sql</code>).</li>'
            .'<li>Se o MySQL tiver senha, ajuste <code>DB_PASS</code> em <code>config/config.php</code>.</li></ul>'
            .'<p>Depois, atualize esta página.</p>', $detalhe);
    }
    if ($ex instanceof PDOException) {
        pagina_erro(500, 'Erro ao acessar o banco de dados', '<p>Não foi possível concluir a operação no banco de dados. Tente novamente em instantes.</p>', $detalhe);
    }
    pagina_erro(500, 'Algo deu errado', '<p>Ocorreu um erro inesperado ao processar a página. Tente novamente; se continuar, avise o administrador.</p>', $detalhe);
}
