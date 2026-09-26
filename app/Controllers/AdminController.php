<?php
declare(strict_types=1);

/**
 * Painel administrativo: visão geral (também usada pela empresa) e os CRUDs
 * exclusivos do administrador — usuários, categorias e cursos/e-books.
 * As telas do painel que a empresa usa (vagas, candidaturas...) ficam em EmpresaController.
 */
final class AdminController extends Controller {
    use AprendeComRevisao;

    /**
     * admin/index.php — dashboard (estilo Power BI): indicadores + gráficos + listas recentes.
     * Administrador: o sistema todo; empresa: só as vagas e candidaturas dela.
     * Os números já vêm agrupados do banco (GROUP BY nos DAOs), sem uma consulta por linha.
     */
    public function painel(): void {
        exigirLogin();
        if (isCandidato()) redirect('view/perfil/index.php');

        $usuarioId = (int)$_SESSION['usuario_id'];
        $vagaDao = new VagaDAO();
        $candDao = new CandidaturaDAO();
        $dias = 30; // janela dos gráficos de atividade
        $perfil = isEmpresa() ? (new PerfilDAO())->obterOuCriar($usuarioId) : null;
        $pid = $perfil ? (int)$perfil['id'] : null; // null = administrador (tudo)
        if (isEmpresa() && !$pid) $pid = -1;         // sem perfil: nenhuma vaga, em vez de ver tudo

        $resumoVagas = $vagaDao->resumoPainel($pid);
        $porStatus = $candDao->contarPorStatus($pid);
        $porDia = $candDao->contarPorDia($dias, $pid);
        $matchCandidaturas = $candDao->resumoMatch($pid);
        $recentes = $candDao->listarRecentes(8, $pid);

        // Funil de seleção: cada etapa conta quem chegou nela ou passou dela (canceladas ficam de fora).
        $recebidas = array_sum($porStatus) - $porStatus['cancelada'];
        $funil = [
            'Recebidas' => $recebidas,
            'Analisadas' => $recebidas - $porStatus['enviada'],
            'Entrevista' => $porStatus['entrevista'] + $porStatus['aprovado'],
            'Aprovadas' => $porStatus['aprovado'],
        ];

        if (isAdmin()) {
            $usuariosPorTipo = (new UsuarioDAO())->contarPorTipo($dias);
            $novosUsuarios = (new UsuarioDAO())->listarRecentes(5);
            $vagasPorArea = $vagaDao->contarPorCategoria();
            $vagasPorCidade = $vagaDao->contarPorCidade();
            $matchVagas = (new MatchDAO())->resumoVagasAbertas();
            $planos = (new AssinaturaDAO())->resumoPorPlano();
            $cursos = (new CursoDAO())->listar(false);
            $cursosPublicados = count(array_filter($cursos, fn($c) => (int)$c['ativo']));
            $ebooks = count(array_filter($cursos, fn($c) => (int)$c['ativo'] && $c['tipo'] === 'ebook'));
            $vagasRecentes = array_slice($vagaDao->listar(false), 0, 6);
            $isEmpresaPremium = false;
        } else {
            $desempenho = $vagaDao->desempenhoPorEmpresa((int)$pid);
            $isEmpresaPremium = (new AssinaturaDAO())->isEmpresaPremium($usuarioId);
        }

        $title = 'Painel';
        $abaAtiva = 'painel';
        $this->view('admin/painel', get_defined_vars());
    }

    /**
     * admin/pages/usuarios.php — CRUD de usuários.
     * Travas: o administrador não exclui nem rebaixa a própria conta, e o sistema
     * sempre mantém pelo menos um administrador ativo.
     */
    public function usuarios(): void {
        exigirAdmin();
        $dao = new UsuarioDAO();
        $meuId = (int)$_SESSION['usuario_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $acao = post_str('acao');
            $id = post_int('id');

            if ($acao === 'excluir') {
                if ($id === $meuId) { flash('erro', 'Você não pode excluir a própria conta.'); redirect('admin/pages/usuarios.php'.painel_qs()); }
                $alvo = $dao->buscarPorId($id);
                if ($alvo && $alvo['tipo'] === 'admin' && (int)$alvo['ativo'] && $dao->contarAdminsAtivos() <= 1) { flash('erro', 'É preciso manter pelo menos um administrador ativo.'); redirect('admin/pages/usuarios.php'.painel_qs()); }
                // LGPD: as lições de currículo que vieram deste candidato saem da máquina de aprendizado antes da conta.
                if ($alvo) MaquinaAprendizado::esquecerDoUsuario($id);
                $ok = $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Usuário excluído (perfil, currículos, vagas e candidaturas foram removidos junto).' : 'Não foi possível excluir o usuário.');
                redirect('admin/pages/usuarios.php'.painel_qs());
            }

            if ($acao === 'ativar' || $acao === 'desativar') {
                $erro = $dao->alterarAtivo($id, $acao === 'ativar', $meuId);
                flash($erro === '' ? 'ok' : 'erro', $erro === '' ? ($acao === 'ativar' ? 'Conta ativada: o login volta a funcionar.' : 'Conta bloqueada: o login e a sessão aberta perdem o acesso.') : $erro);
                redirect('admin/pages/usuarios.php'.painel_qs());
            }

            $d = [
                'nome' => mb_substr(post_str('nome'), 0, 255),
                'email' => normalizar_email(post_str('email')),
                'tipo' => enum_val(post_str('tipo'), UsuarioDAO::TIPOS, 'candidato'),
                'telefone' => mb_substr(post_str('telefone'), 0, 30),
                'ativo' => post_int('ativo', 1) ? 1 : 0,
                'senha' => is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '',
            ];
            $erros = [];
            if ($d['nome'] === '') $erros[] = 'Informe o nome.';
            if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 255) $erros[] = 'E-mail inválido.';
            if ((!$id || $d['senha'] !== '') && strlen($d['senha']) < 6) $erros[] = 'A senha precisa ter pelo menos 6 caracteres.';
            if (strlen($d['senha']) > 72) $erros[] = 'A senha pode ter no máximo 72 caracteres.'; // limite do bcrypt
            if ($id === $meuId && ($d['tipo'] !== 'admin' || !$d['ativo'])) $erros[] = 'Você não pode remover seu próprio acesso de administrador.';
            if ($erros) { flash('erro', implode(' ', $erros)); redirect('admin/pages/usuarios.php'.painel_qs($id ? ['edit' => $id] : [])); }

            $ok = $id ? $dao->atualizar($id, $d) : (bool)$dao->cadastrar(new UsuarioDTO($d));
            flash($ok ? 'ok' : 'erro', $ok ? ($id ? 'Usuário atualizado.' : 'Usuário criado'.($d['tipo'] !== 'admin' ? ' com perfil.' : '.')) : ($dao->erro ?: 'Não foi possível salvar.'));
            redirect('admin/pages/usuarios.php'.painel_qs(!$ok && $id ? ['edit' => $id] : []));
        }

        $edit = get_str('edit') !== '' ? $dao->buscarPorId((int)get_str('edit')) : null;
        $ver = get_str('ver') !== '' ? $dao->resumo((int)get_str('ver')) : null;
        $filtroTipo = enum_val(get_str('tipo'), UsuarioDAO::TIPOS, '');
        $busca = get_str('q');
        $filtroSituacao = enum_val(get_str('situacao'), ['ativo', 'bloqueado'], '');
        $usuarios = array_values(array_filter($dao->listar($filtroTipo ?: null, $busca),
            fn($u) => $filtroSituacao === '' || (int)$u['ativo'] === ($filtroSituacao === 'ativo' ? 1 : 0)));
        [$ordem, $dir] = lista_ordem(['id', 'nome', 'email', 'tipo', 'ativo', 'ultimo_acesso'], 'id', 'desc');
        $totalUsuarios = count($usuarios);
        [$usuarios, $pagina, $paginas] = paginar(ordenar_linhas($usuarios, $ordem, $dir), 25);
        $title = 'Usuários';
        $abaAtiva = 'usuarios';
        $this->view('admin/usuarios', get_defined_vars());
    }

    /** admin/pages/categorias.php — CRUD de categorias (áreas de vagas e de cursos). */
    public function categorias(): void {
        exigirAdmin();
        $dao = new CategoriaDAO();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $id = post_int('id');
            if (post_str('acao') === 'excluir') {
                $ok = $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Categoria excluída. Vagas e cursos que a usavam ficaram sem categoria.' : ($dao->erro ?: 'Categoria não encontrada.'));
                redirect('admin/pages/categorias.php'.painel_qs());
            }
            if (in_array(post_str('acao'), ['ativar', 'desativar'], true)) {
                $ativar = post_str('acao') === 'ativar';
                $ok = $dao->alterarAtivo($id, $ativar);
                flash($ok ? 'ok' : 'erro', $ok ? ($ativar ? 'Categoria ativada.' : 'Categoria desativada: some dos filtros, mas os itens continuam com ela.') : 'Categoria não encontrada.');
                redirect('admin/pages/categorias.php'.painel_qs());
            }
            $d = ['nome' => mb_substr(post_str('nome'), 0, 100), 'tipo' => enum_val(post_str('tipo'), CategoriaDAO::TIPOS, 'vaga'), 'ativo' => post_int('ativo', 1) ? 1 : 0];
            if ($d['nome'] === '') { flash('erro', 'Informe o nome da categoria.'); redirect('admin/pages/categorias.php'.painel_qs()); }
            if ($id && !$dao->buscar($id)) { flash('erro', 'Categoria não encontrada (pode ter sido excluída).'); redirect('admin/pages/categorias.php'.painel_qs()); }
            $ok = $dao->salvar($d, $id);
            flash($ok ? 'ok' : 'erro', $ok ? 'Categoria salva.' : $dao->erro);
            redirect('admin/pages/categorias.php'.painel_qs(!$ok && $id ? ['edit' => $id] : []));
        }

        $edit = get_str('edit') !== '' ? $dao->buscar((int)get_str('edit')) : null;
        $filtroTipo = enum_val(get_str('tipo'), CategoriaDAO::TIPOS, '');
        $busca = get_str('q');
        $buscaN = Competencias::normalizar($busca);
        $todasCats = $dao->listar();
        $cats = array_values(array_filter($todasCats, fn($c) => ($filtroTipo === '' || $c['tipo'] === $filtroTipo)
            && ($buscaN === '' || str_contains(Competencias::normalizar((string)$c['nome']), $buscaN))));
        [$ordem, $dir] = lista_ordem(['nome', 'tipo', 'ativo', 'em_uso'], 'nome', 'asc');
        $totalCats = count($cats);
        [$cats, $pagina, $paginas] = paginar(ordenar_linhas($cats, $ordem, $dir), 30);
        $title = 'Categorias';
        $abaAtiva = 'categorias';
        $this->view('admin/categorias', get_defined_vars());
    }

    /**
     * admin/pages/cursos.php — CRUD de cursos/e-books + EXTRAÇÃO DE CURSOS:
     * o administrador cola o texto de divulgação e o formulário é preenchido (nada é salvo sem revisão).
     * Ao salvar um formulário que veio da extração, a máquina de aprendizado aprende com a revisão.
     */
    public function cursos(): void {
        exigirAdmin();
        $dao = new CursoDAO();
        $catDao = new CategoriaDAO();
        $cats = $catDao->listar('curso');
        $form = null; $extraido = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $id = post_int('id');
            $acao = post_str('acao');

            if ($acao === 'excluir') {
                $ok = $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Conteúdo excluído.' : 'Conteúdo não encontrado.');
                redirect('admin/pages/cursos.php'.painel_qs());
            }

            if ($acao === 'ativar' || $acao === 'desativar') {
                $ok = $dao->alterarAtivo($id, $acao === 'ativar');
                flash($ok ? 'ok' : 'erro', $ok ? ($acao === 'ativar' ? 'Conteúdo publicado: já aparece para os usuários.' : 'Conteúdo ocultado: saiu da área pública.') : 'Conteúdo não encontrado.');
                redirect('admin/pages/cursos.php'.painel_qs());
            }

            if ($acao === 'padronizar_instituicoes') {
                $n = $dao->padronizarInstituicoes();
                flash('ok', $n ? "{$n} conteúdo(s) com o nome da instituição padronizado pelo link oficial." : 'Os nomes das instituições já estavam padronizados.');
                redirect('admin/pages/cursos.php'.painel_qs().'#pesquisa');
            }

            // IMPORTAÇÃO EM LOTE (resposta do prompt de pesquisa): 1) ler as fichas e mostrar a prévia; 2) cadastrar as marcadas.
            // Padrão da plataforma: título, link oficial e IMAGEM (capa do e-book / imagem do curso) — sem imagem, não entra.
            if ($acao === 'importar_ler') {
                set_time_limit(300);   // confere as imagens na internet
                $nomesCat = array_column($cats, 'nome');
                $itens = ImagemRemota::completar(array_slice(ExtracaoCurso::fichas(post_str('texto_lote'), $nomesCat), 0, 100));
                foreach ($itens as &$it) {
                    $it['problemas'] = [];
                    if ($it['titulo'] === '') $it['problemas'][] = 'sem título';
                    if (!url_http_valida($it['url'])) $it['problemas'][] = 'sem link válido';
                    if ($it['imagem_url'] === '' && $it['imagem'] === '') $it['problemas'][] = 'sem imagem';
                    $rep = $dao->buscarRepetido($it['url'], $it['titulo'], $it['instituicao']);
                    if ($rep) $it['problemas'][] = 'já cadastrado (#'.(int)$rep['id'].')';
                    $it['alerta'] = !$it['gratuito'] && !$it['preco'] ? 'pago sem preço: entra como gratuito, revise depois' : '';
                }
                unset($it);
                $_SESSION['import_cursos'] = $itens;
                flash($itens ? 'info' : 'erro', $itens ? count($itens).' ficha(s) lida(s). Confira a prévia abaixo e cadastre as marcadas.' : 'Nenhuma ficha encontrada. Cole a resposta completa da pesquisa (fichas com "Título:" e "Link:", separadas por ---).');
                redirect('admin/pages/cursos.php#importar');
            }
            if ($acao === 'importar_salvar') {
                set_time_limit(300);   // baixa as imagens
                $itens = (array)($_SESSION['import_cursos'] ?? []);
                $marcados = array_map('intval', array_filter((array)($_POST['itens'] ?? []), 'is_scalar'));
                $catPorNome = array_column($cats, 'id', 'nome');
                // Mesmas regras do formulário: título, link http(s), sem repetir.
                $validos = [];
                foreach ($marcados as $i) {
                    $it = $itens[$i] ?? null;
                    if ($it && $it['titulo'] !== '' && url_http_valida($it['url']) && !$dao->buscarRepetido($it['url'], $it['titulo'], $it['instituicao'])) $validos[] = $it;
                }
                // Imagem de cada um: a da pesquisa/página (baixada para storage/uploads) ou, na falta, o banner da instituição.
                $baixadas = ImagemRemota::baixar(array_map(fn($it) => (string)($it['imagem_url'] ?? ''), $validos), 'curso');
                $ok = 0; $pulados = count($marcados) - count($validos);
                foreach ($validos as $it) {
                    $imagem = $baixadas[$it['imagem_url'] ?? ''] ?? caminho_imagem_valido((string)$it['imagem']);
                    // Fora do padrão (sem imagem) ou repetido dentro do próprio lote: não entra.
                    if ($imagem === '' || $dao->buscarRepetido($it['url'], $it['titulo'], $it['instituicao'])) { $pulados++; continue; }
                    $d = [
                        'categoria_id' => $catPorNome[$it['categoria']] ?? null, 'titulo' => mb_substr($it['titulo'], 0, 255), 'descricao' => (string)$it['descricao'],
                        'tipo' => enum_val($it['tipo'], CursoDAO::TIPOS, 'curso'), 'modalidade' => enum_val($it['modalidade'], CursoDAO::MODALIDADES, 'ead'),
                        'nivel' => enum_val($it['nivel'], CursoDAO::NIVEIS, 'iniciante'), 'duracao' => mb_substr((string)$it['duracao'], 0, 50),
                        'gratuito' => (int)$it['gratuito'] ? 1 : 0, 'preco' => (int)$it['gratuito'] ? null : $it['preco'], 'url' => mb_substr($it['url'], 0, 500),
                        'imagem' => $imagem, 'instituicao' => mb_substr((string)$it['instituicao'], 0, 255), 'ativo' => 1,
                    ];
                    if (!$d['gratuito'] && ($d['preco'] === null || $d['preco'] <= 0)) { $d['gratuito'] = 1; $d['preco'] = null; } // pago sem preço: publica como gratuito para revisar
                    $dao->salvar($d) ? $ok++ : $pulados++;
                }
                foreach ($baixadas as $img) apagar_upload_sem_uso($img);   // imagem baixada de item que acabou não entrando
                unset($_SESSION['import_cursos']);
                flash($ok ? 'ok' : 'erro', $ok ? "{$ok} conteúdo(s) cadastrado(s) e publicado(s), cada um com a sua imagem".($pulados ? "; {$pulados} pulado(s) (sem link, sem título, sem imagem ou repetido)." : '.') : 'Nenhum conteúdo cadastrado. Marque as fichas que quer importar (só entram as que têm imagem).');
                redirect('admin/pages/cursos.php');
            }
            if ($acao === 'importar_cancelar') { unset($_SESSION['import_cursos']); redirect('admin/pages/cursos.php'); }

            if ($acao === 'extrair') {
                // Extração de cursos: preenche o formulário para revisão, sem salvar.
                $extraido = ExtracaoCurso::doTexto(post_str('texto_anuncio'));
                $cat = $extraido['categoria'] ? $catDao->buscarPorNome($extraido['categoria'], 'curso') : null;
                $form = $extraido + ['id' => $id, 'categoria_id' => $cat['id'] ?? null, 'imagem' => ExtracaoCurso::capa($extraido['instituicao'], $extraido['url'], $extraido['tipo']), 'ativo' => 1];
                $form['sugestao_maquina'] = $this->guardarSugestao('curso', fn() => MaquinaAprendizado::sugestao('curso', $extraido, [], post_str('texto_anuncio')));
            } else {
                $d = [
                    'categoria_id' => post_int('categoria_id') ?: null,
                    'titulo' => mb_substr(post_str('titulo'), 0, 255),
                    'descricao' => post_str('descricao'),
                    'tipo' => enum_val(post_str('tipo'), CursoDAO::TIPOS, 'curso'),
                    'modalidade' => enum_val(post_str('modalidade'), CursoDAO::MODALIDADES, 'ead'),
                    'nivel' => enum_val(post_str('nivel'), CursoDAO::NIVEIS, 'iniciante'),
                    'duracao' => mb_substr(post_str('duracao'), 0, 50),
                    'gratuito' => isset($_POST['gratuito']) ? 1 : 0,
                    'preco' => decimal_ou_null(post_str('preco')),
                    'url' => mb_substr(post_str('url'), 0, 500),
                    'imagem' => caminho_imagem_valido(mb_substr(post_str('imagem'), 0, 255)),
                    'instituicao' => mb_substr(post_str('instituicao'), 0, 255),
                    'ativo' => isset($_POST['ativo']) ? 1 : 0,
                ];
                $existente = $id ? $dao->buscar($id) : null;
                $erros = [];
                if ($id && !$existente) $erros[] = 'Conteúdo não encontrado (pode ter sido excluído).';
                if ($d['titulo'] === '') $erros[] = 'Informe o título.';
                // Só http/https: impede links "javascript:" no botão do curso.
                if ($d['url'] !== '' && !url_http_valida($d['url'])) $erros[] = 'O link oficial precisa ser um endereço válido começando com http:// ou https://.';
                if (post_str('imagem') !== '' && $d['imagem'] === '') $erros[] = 'Caminho de imagem inválido (use um arquivo de assets/img ou envie uma imagem).';
                if ($d['categoria_id'] && !in_array((int)$d['categoria_id'], array_map(fn($c) => (int)$c['id'], $cats), true)) $d['categoria_id'] = null; // só categorias de curso
                if (!$d['gratuito'] && ($d['preco'] === null || $d['preco'] <= 0)) $erros[] = 'Informe o preço do conteúdo pago (ou marque como gratuito).';
                if ($d['preco'] !== null && $d['preco'] > 99999999.99) $erros[] = 'Preço fora do intervalo permitido.';
                $img = salvar_imagem_enviada('imagem_arquivo', 'curso');
                if ($img === false) $erros[] = 'Imagem inválida (use JPG, PNG ou WEBP até 3 MB).';
                elseif ($img !== null) $d['imagem'] = $img;
                // Padrão da plataforma: todo curso, e-book e vídeo aparece com a sua imagem.
                if ($img !== false && $d['imagem'] === '' && post_str('imagem') === '') $erros[] = 'Informe a imagem do conteúdo: escolha um caminho (ex.: a capa do e-book) ou envie uma imagem.';
                if ($erros) {
                    if ($img) { apagar_upload_sem_uso($img); $d['imagem'] = $existente['imagem'] ?? ''; } // não deixa arquivo órfão
                    flash('erro', implode(' ', $erros));
                    $form = $d + ['id' => $id, 'sugestao_maquina' => post_str('sugestao_maquina')];
                } else {
                    $ok = $dao->salvar($d, $id);
                    if ($ok && $existente && ($existente['imagem'] ?? '') !== $d['imagem']) apagar_upload_sem_uso((string)$existente['imagem']);
                    // Aprendizado: o curso salvo é a resposta certa para a sugestão da extração.
                    $aprendeu = $ok ? $this->aprenderComRevisao('curso', $d + ['categoria' => array_column($cats, 'nome', 'id')[(int)$d['categoria_id']] ?? ''], post_str('sugestao_maquina')) : null;
                    flash($ok ? 'ok' : 'erro', $ok ? 'Conteúdo salvo.'.(!empty($aprendeu['licoes']) ? ' A máquina de extração aprendeu '.$aprendeu['licoes'].' '.($aprendeu['licoes'] === 1 ? 'lição' : 'lições').' com a sua revisão.' : '') : 'Não foi possível salvar.');
                    redirect('admin/pages/cursos.php'.painel_qs());   // volta para a mesma aba, filtros e ordem
                }
            }
        }

        $edit = get_str('edit') !== '' ? $dao->buscar((int)get_str('edit')) : null;
        // "Novo e-book" / "Novo vídeo" (?novo=ebook): o formulário já vem no formato escolhido.
        $novoTipo = enum_val(get_str('novo') ?: get_str('tipo'), CursoDAO::TIPOS, 'curso');
        $form ??= $edit ?? ['id' => 0, 'categoria_id' => null, 'titulo' => '', 'descricao' => '', 'tipo' => $novoTipo, 'modalidade' => 'ead', 'nivel' => 'iniciante', 'duracao' => '', 'gratuito' => 1, 'preco' => null, 'url' => '', 'imagem' => '', 'instituicao' => '', 'ativo' => 1];

        // LISTA: abas por formato (Cursos · E-books · Vídeos), filtros, ordenação por coluna e paginação.
        $filtroTipo = enum_val(get_str('tipo'), CursoDAO::TIPOS, '');
        $busca = get_str('q');
        $filtroCat = (int)get_str('categoria_id');
        $filtroSituacao = enum_val(get_str('situacao'), ['publicado', 'oculto'], '');
        $todos = $dao->listar(false);
        $base = array_values(array_filter($dao->listar(false, ['q' => $busca, 'categoria_id' => $filtroCat]),
            fn($c) => $filtroSituacao === '' || (int)$c['ativo'] === ($filtroSituacao === 'publicado' ? 1 : 0)));
        $porTipo = array_count_values(array_column($base, 'tipo'));   // contagem das abas já com os outros filtros
        $lista = $filtroTipo === '' ? $base : array_values(array_filter($base, fn($c) => $c['tipo'] === $filtroTipo));
        [$ordem, $dir] = lista_ordem(['titulo', 'tipo', 'categoria_nome', 'instituicao', 'ativo', 'created_at', 'id'], 'created_at', 'desc');
        $totalLista = count($lista);
        [$lista, $pagina, $paginas] = paginar(ordenar_linhas($lista, $ordem, $dir), 25);
        $comFiltro = $busca !== '' || $filtroCat || $filtroSituacao !== '';
        $publicados = count(array_filter($todos, fn($c) => (int)$c['ativo']));
        $imagens = array_merge(imagens_da_pasta('assets/img/cursos'), imagens_da_pasta('assets/img/cursos/capas'), imagens_da_pasta('assets/img/ebooks'));
        // PESQUISA GUIADA (FontesCursos): de onde vêm os links novos. O prompt mira as áreas com menos conteúdo
        // e leva os links já cadastrados, para a IA não repetir.
        $areasAtivas = array_column(array_filter($cats, fn($c) => (int)$c['ativo']), 'nome');
        $cobertura = FontesCursos::cobertura($todos, $areasAtivas);
        $pesquisa = [
            'formato' => enum_val(get_str('p_formato'), CursoDAO::TIPOS, ''),
            'area' => in_array(get_str('p_area'), $areasAtivas, true) ? get_str('p_area') : '',
            'fonte' => isset(FontesCursos::FONTES[get_str('p_fonte')]) ? get_str('p_fonte') : '',
            'quantidade' => max(5, min(40, (int)(get_str('p_qtd') ?: 20))),
        ];
        $linksCadastrados = array_values(array_filter(array_map(fn($c) => (string)$c['url'], array_filter($todos,
            fn($c) => ($pesquisa['fonte'] === '' || FontesCursos::fonteDoLink((string)$c['url']) === $pesquisa['fonte'])
                && ($pesquisa['area'] === '' || ($c['categoria_nome'] ?? '') === $pesquisa['area'])))));
        $promptPesquisa = FontesCursos::prompt($pesquisa, $areasAtivas, $cobertura['lacunas'], $linksCadastrados);
        $pesquisaAberta = get_str('p_qtd') !== '';
        $semPadrao = count(array_filter($todos, fn($c) => FontesCursos::nomeOficial((string)$c['instituicao'], (string)$c['url']) !== (string)$c['instituicao']));
        $importacao = (array)($_SESSION['import_cursos'] ?? []);
        $title = 'Cursos e e-books';
        $abaAtiva = 'cursos';
        $this->view('admin/cursos', get_defined_vars());
    }
}
