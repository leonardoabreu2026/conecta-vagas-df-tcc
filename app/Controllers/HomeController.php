<?php
declare(strict_types=1);

/**
 * Páginas institucionais: início (index.php) e termo de privacidade (contrato.php).
 */
final class HomeController extends Controller {
    /**
     * index.php — página inicial:
     *  1. boas-vindas com carrossel de fotos de Brasília (imagens em public/assets/img/brasilia/);
     *  2. quem somos;
     *  3. vagas de emprego (cartões no formato de notícia curta);
     *  4. assinaturas (faixa no meio da página);
     *  5. cursos, e-books e vídeos — cada formato na sua seção, com o mesmo cartão e a mesma vitrine rotativa das vagas.
     */
    public function index(): void {
        $vagas = []; $cursos = []; $dbErro = null;
        try {
            $vagas = (new VagaDAO())->listar(true);        // destaque primeiro, depois as mais novas
            $cursos = (new CursoDAO())->listar(true);
        } catch (Throwable $e) {
            $dbErro = mensagem_erro_banco($e);
        }

        // Candidato logado: % de match em cada vaga e candidaturas já enviadas.
        $mapaMatch = []; $minhas = []; $plano = null;
        if (!$dbErro && usuarioLogado()) {
            try {
                $plano = (new AssinaturaDAO())->buscarAtivaPorUsuario((int)$_SESSION['usuario_id']);
                if (isCandidato() && ($perfil = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']))) {
                    $mapaMatch = (new MatchDAO())->mapaPorCandidato((int)$perfil['id']);
                    foreach ((new CandidaturaDAO())->listarPorCandidato((int)$perfil['id']) as $c) if ($c['status'] !== 'cancelada') $minhas[(int)$c['vaga_id']] = $c['status'];
                }
            } catch (Throwable) {}
        }
        // Vitrine rotativa: 5 cartões na tela; as demais vagas abertas ficam na fila e vão entrando uma a uma (app.js).
        $vagasCapa = array_slice($vagas, 0, 5);
        $vagasFila = array_slice($vagas, 5);
        $totalVagas = count($vagas);
        // Conteúdos separados por formato (cursos, e-books, vídeos), cada um com a MESMA vitrine rotativa das vagas:
        // 5 cartões na tela (os mais novos) e os demais na fila, entrando um a um.
        $conteudosCapa = []; $conteudosFila = []; $totalConteudos = [];
        foreach (CursoDAO::TIPOS as $t) {
            $doFormato = array_values(array_filter($cursos, fn($c) => $c['tipo'] === $t));
            $conteudosCapa[$t] = array_slice($doFormato, 0, 5);
            $conteudosFila[$t] = array_slice($doFormato, 5);
            $totalConteudos[$t] = count($doFormato);
        }

        // Carrossel: todas as imagens da pasta public/assets/img/brasilia/ (basta trocar os arquivos).
        // Créditos das fotos de teste (Wikimedia Commons, licenças livres).
        $creditos = [
            'brasilia2.jpg' => ['Catedral Metropolitana — Agência Brasília', 'CC BY 2.0', 'https://commons.wikimedia.org/wiki/File:Catedral_Metropolitana_de_Bras%C3%ADlia_(16425039893).jpg'],
            'brasilia3.jpg' => ['Eixo Monumental — Cayambe', 'CC BY-SA 3.0', 'https://commons.wikimedia.org/wiki/File:Brasilia_Eixo_Monumental_Nat_Congress_Ministries_from_TV_Tower.jpg'],
            'brasilia4.jpg' => ['Ponte JK — Marinelson Almeida', 'CC BY 2.0', 'https://commons.wikimedia.org/wiki/File:Ponte_JK_-_Lago_Parano%C3%A1_-_Brasilia._(15352509527).jpg'],
            'brasilia5.jpg' => ['Esplanada à noite — Dasfour2022', 'CC BY-SA 4.0', 'https://commons.wikimedia.org/wiki/File:Esplanada_dos_Ministerios_a_noite.jpg'],
        ];
        $slides = imagens_da_pasta('assets/img/brasilia') ?: ['assets/img/header-bg.png'];

        $title = 'Início';
        $layoutLargo = true;
        $descricaoPagina = 'Vagas de emprego, cursos gratuitos e e-books no Distrito Federal, em um só lugar.';
        $this->view('home/index', get_defined_vars());
    }

    /** contrato.php — termo de autorização e privacidade (LGPD). */
    public function contrato(): void {
        $title = 'Termo de autorização';
        $this->view('home/contrato', get_defined_vars());
    }
}
