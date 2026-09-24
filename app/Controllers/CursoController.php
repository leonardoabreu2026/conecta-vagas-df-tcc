<?php
declare(strict_types=1);

/**
 * Cursos, e-books e vídeos (área pública): lista (cursos.php) e página do conteúdo (curso.php).
 * O cadastro/edição fica no painel do administrador: AdminController::cursos().
 */
final class CursoController extends Controller {
    /**
     * cursos.php — cursos, e-books (?tipo=ebook) e vídeos: faixa de título, abas por formato,
     * filtros, "Recomendados para você" (candidato) e grade de cartões.
     */
    public function lista(): void {
        $tipo = enum_val(get_str('tipo'), CursoDAO::TIPOS, '');
        $filtros = ['q' => get_str('q'), 'categoria_id' => (int)get_str('categoria_id'), 'gratuito' => get_str('gratuito')];
        $cursos = []; $categorias = []; $porTipo = []; $dbErro = null;
        try {
            $cursos = (new CursoDAO())->listar(true, $filtros);
            foreach ($cursos as $c) $porTipo[$c['tipo']] = ($porTipo[$c['tipo']] ?? 0) + 1;   // contagem para as abas
            if ($tipo !== '') $cursos = array_values(array_filter($cursos, fn($c) => $c['tipo'] === $tipo));
            $categorias = (new CategoriaDAO())->listar('curso', true);
        } catch (Throwable $e) { $dbErro = mensagem_erro_banco($e); }

        // Candidato: cursos que cobrem as competências que mais faltam nas vagas com melhor match.
        $recomendados = []; $faltantesTop = [];
        if (usuarioLogado() && isCandidato() && !$dbErro) {
            $p = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']);
            if ($p) {
                $contagem = [];
                foreach (array_slice((new MatchDAO())->listarPorCandidato((int)$p['id']), 0, 5) as $m) {
                    foreach ($m['detalhes']['competencias']['faltantes'] ?? [] as $c) $contagem[$c] = ($contagem[$c] ?? 0) + 1;
                }
                arsort($contagem);
                $faltantesTop = array_slice(array_keys($contagem), 0, 6);
                $recomendados = Competencias::cursosPara($faltantesTop, (new CursoDAO())->listar(true), 3);
            }
        }

        $cabecalhos = [
            ''      => ['Cursos Gratuitos', 'Cursos, e-books e vídeos para fortalecer seu currículo e aumentar seu match com as vagas.'],
            'curso' => ['Cursos', 'Capacitação gratuita e reconhecida para conquistar melhores oportunidades.'],
            'ebook' => ['E-books', 'Guias e materiais para ler no seu ritmo e se preparar para o mercado de trabalho.'],
            'video' => ['Vídeos', 'Aulas e conteúdos em vídeo para aprender na prática.'],
        ];
        [$tituloPag, $subPag] = $cabecalhos[$tipo];
        $qsFiltros = array_filter(['q' => $filtros['q'], 'categoria_id' => $filtros['categoria_id'] ?: '', 'gratuito' => $filtros['gratuito']]);
        $abaUrl = fn(string $t) => url('cursos.php'.(($q = http_build_query($qsFiltros + ($t !== '' ? ['tipo' => $t] : []))) ? '?'.$q : ''));
        $total = array_sum($porTipo);

        $title = $tipo === 'ebook' ? 'E-books' : ($tipo === 'video' ? 'Vídeos' : 'Cursos');
        $layoutLargo = true;
        $descricaoPagina = $tituloPag.' — '.$subPag;
        $this->view('cursos/lista', get_defined_vars());
    }

    /**
     * curso.php?id= — capa + resumo, descrição, competências que o conteúdo desenvolve;
     * na lateral, a ficha e o botão de acesso. Abaixo, as vagas que pedem essas
     * competências e outros cursos.
     */
    public function detalhe(): void {
        $id = (int)get_str('id');
        $curso = null; $dbErro = null;
        try { $curso = (new CursoDAO())->buscar($id); } catch (Throwable $e) { $dbErro = mensagem_erro_banco($e); }
        // Conteúdo inativo só aparece para o administrador.
        if (!$curso || (empty($curso['ativo']) && !isAdmin())) {
            http_response_code(404);
            $title = 'Conteúdo não encontrado';
            $layoutLargo = true;
            $this->view('cursos/nao_encontrado', get_defined_vars());
            return;
        }

        $competencias = Competencias::doCurso($curso);
        $vagasAtivas = []; $outros = [];
        try {
            $vagasAtivas = (new VagaDAO())->listar(true);
            $outros = array_values(array_filter((new CursoDAO())->listar(true), fn($c) => (int)$c['id'] !== $id));
        } catch (Throwable) {}
        $vagasQuePedem = Competencias::vagasParaCurso($competencias, $vagasAtivas, 4);

        // Candidato: match das vagas e o que o curso ensina que ainda falta para ele.
        $mapaMatch = []; $faltaParaMim = [];
        if (usuarioLogado() && isCandidato()) {
            try {
                $p = (new PerfilDAO())->buscarPorUsuarioId((int)$_SESSION['usuario_id']);
                if ($p) {
                    $matchDao = new MatchDAO();
                    $mapaMatch = $matchDao->mapaPorCandidato((int)$p['id']);
                    foreach (array_slice($matchDao->listarPorCandidato((int)$p['id']), 0, 5) as $m) {
                        foreach ($m['detalhes']['competencias']['faltantes'] ?? [] as $c) $faltaParaMim[$c] = true;
                    }
                }
            } catch (Throwable) {}
        }
        // Outros conteúdos: mesmo formato primeiro.
        usort($outros, fn($a, $b) => ($b['tipo'] === $curso['tipo']) <=> ($a['tipo'] === $curso['tipo']));
        $outros = array_slice($outros, 0, 4);

        $ptMenuEbook = $curso['tipo'] === 'ebook';   // e-book acende o item "E-books" do menu
        $formato = pt_formato((string)$curso['tipo']);
        $instituicao = $curso['instituicao'] ?: 'Instituição parceira';
        $ext = pt_url_externa($curso['url'] ?? '');
        $link = url('curso.php?id='.$id);
        $abaTipo = $curso['tipo'] === 'ebook' ? ['E-books' => url('cursos.php?tipo=ebook')] : ['Cursos' => url('cursos.php')];

        $title = $curso['titulo'];
        $layoutLargo = true;
        $descricaoPagina = pt_linha_fina_curso($curso);
        $ogImagem = $curso['imagem'] ?: null;
        $this->view('cursos/detalhe', get_defined_vars());
    }
}
