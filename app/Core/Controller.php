<?php
declare(strict_types=1);

/**
 * Classe base dos controllers (a "C" do MVC).
 *
 * Um controller recebe a requisição, confere permissões, lê o formulário,
 * chama os Models (banco) e os Services (regras de negócio) e, no fim,
 * redireciona (depois de gravar) ou mostra uma tela (View).
 *
 * Cada método público é uma AÇÃO ligada a um endereço em public/index.php.
 */
abstract class Controller {
    /**
     * Mostra uma tela de app/Views com o layout do site.
     *
     * As ações terminam com $this->view('pasta/tela', get_defined_vars()):
     * get_defined_vars() entrega à tela TODAS as variáveis calculadas na ação
     * ($vagas, $filtros, $title...), sem precisar listá-las uma a uma.
     */
    protected function view(string $tela, array $dados = []): void {
        View::render($tela, $dados);
    }
}
