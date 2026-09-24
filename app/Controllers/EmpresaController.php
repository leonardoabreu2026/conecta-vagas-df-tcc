<?php
declare(strict_types=1);

/**
 * Painel da empresa (o administrador também usa, vendo tudo): vagas com EXTRAÇÃO DE VAGAS,
 * candidaturas recebidas, banco de talentos e dados da empresa.
 */
final class EmpresaController extends Controller {
    /**
     * admin/pages/vagas.php — CRUD de vagas + MÁQUINA DE EXTRAÇÃO DE VAGAS:
     *  - "Ler cartaz": envia a imagem do anúncio; o OCR lê o texto e o formulário é preenchido
     *    (cargo, empresa, salário, local, requisitos, benefícios, contato) com o cartaz como imagem;
     *  - "Colar texto": o mesmo a partir do texto do anúncio (WhatsApp, Instagram, site).
     * Nada é salvo sem revisão. Empresa só mexe nas próprias vagas; o limite do plano básico
     * (2 vagas abertas) vale ao publicar e ao reativar; o mesmo anúncio não é publicado duas vezes.
     * Ao salvar, o match é recalculado.
     */
    public function vagas(): void {
        exigirLogin();
        if (!isAdmin() && !isEmpresa()) negar_acesso('Acesso negado.');

        $dao = new VagaDAO();
        $catDao = new CategoriaDAO();
        $cats = $catDao->listar('vaga');
        $usuarioId = (int)$_SESSION['usuario_id'];
        $perfil = isEmpresa() ? (new PerfilDAO())->obterOuCriar($usuarioId) : null;
        $empresas = isAdmin() ? (new PerfilDAO())->listarEmpresas() : [];
        $assinaturaDao = new AssinaturaDAO();
        $isPremium = isAdmin() || $assinaturaDao->isEmpresaPremium($usuarioId);
        $form = null; $extraido = null;

        /** Vaga que o usuário atual pode alterar (admin: qualquer; empresa: só as suas). */
        $vagaPermitida = function (int $id) use ($dao, $perfil): ?array {
            $v = $id ? $dao->buscar($id) : null;
            if (!$v) return null;
            if (isAdmin() || ($perfil && (int)$v['perfil_empresa_id'] === (int)$perfil['id'])) return $v;
            return null;
        };

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $id = post_int('id');
            $acao = post_str('acao');

            if ($acao === 'excluir') {
                $ok = $vagaPermitida($id) && $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Vaga excluída (candidaturas e matches dela também).' : 'Vaga não encontrada ou sem permissão.');
                redirect('admin/pages/vagas.php');
            }

            $existente = $id ? $vagaPermitida($id) : null;
            if ($id && !$existente) negar_acesso('Você não pode alterar esta vaga.');

            if ($acao === 'extrair' || $acao === 'ler_cartaz') {
                // Extração de vagas: preenche o formulário (sem salvar) com o texto do anúncio ou com
                // a leitura do CARTAZ enviado como imagem (OCR). O cartaz vira a imagem da vaga.
                $imagemForm = $existente['imagem'] ?? 'assets/img/vagas/vaga1.jpg';
                if ($acao === 'ler_cartaz') {
                    $cartaz = salvar_imagem_enviada('cartaz', 'cartaz', 8 * 1024 * 1024);
                    if (!$cartaz) {
                        flash('erro', $cartaz === null ? 'Selecione a imagem do cartaz.' : 'Cartaz inválido: envie JPG, PNG ou WEBP de até 8 MB.');
                        redirect('admin/pages/vagas.php'.($id ? '?edit='.$id : ''));
                    }
                    $this->descartarCartazesLidos();
                    $_SESSION['cartazes_lidos'] = [$cartaz];
                    $extraido = ExtracaoVaga::doImagem((string)caminho_upload($cartaz));
                    $imagemForm = $cartaz;
                } else {
                    $extraido = ExtracaoVaga::doTexto(post_str('texto_anuncio'));
                }
                $cat = $extraido['categoria'] ? $catDao->buscarPorNome($extraido['categoria'], 'vaga') : null;
                $parecida = $extraido['titulo'] !== '' ? $dao->buscarParecida($extraido['titulo'], $extraido['anunciante'], $extraido['cidade'], $id) : null;
                $form = $extraido + ['id' => $id, 'categoria_id' => $cat['id'] ?? null, 'perfil_empresa_id' => $existente['perfil_empresa_id'] ?? post_int('perfil_empresa_id'),
                                     'imagem' => $imagemForm, 'status' => $existente['status'] ?? 'ativa', 'destaque' => $existente['destaque'] ?? 0, 'data_expiracao' => $existente['data_expiracao'] ?? null];
            } else {
                $pid = isAdmin() ? post_int('perfil_empresa_id') : (int)($perfil['id'] ?? 0);
                $d = [
                    'perfil_empresa_id' => $pid,
                    'categoria_id' => post_int('categoria_id') ?: null,
                    'titulo' => mb_substr(post_str('titulo'), 0, 255),
                    'anunciante' => mb_substr(post_str('anunciante'), 0, 150),
                    'descricao' => post_str('descricao'),
                    'requisitos' => post_str('requisitos'),
                    'beneficios' => post_str('beneficios'),
                    'contato' => mb_substr(post_str('contato'), 0, 255),
                    'tipo_vaga' => enum_val(post_str('tipo_vaga'), VagaDAO::TIPOS, 'clt'),
                    'nivel_experiencia' => enum_val(post_str('nivel_experiencia'), VagaDAO::NIVEIS, 'junior'),
                    'remoto' => enum_val(post_str('remoto'), VagaDAO::MODELOS, 'presencial'),
                    'cidade' => mb_substr(post_str('cidade'), 0, 100),
                    'uf' => strtoupper(mb_substr(post_str('uf'), 0, 2)),
                    'salario_minimo' => decimal_ou_null(post_str('salario_minimo')),
                    'salario_maximo' => decimal_ou_null(post_str('salario_maximo')),
                    'imagem' => $this->imagemDoFormulario(mb_substr(post_str('imagem'), 0, 255), $existente),
                    'status' => enum_val(post_str('status'), VagaDAO::STATUS, 'ativa'),
                    // Destaque é recurso Premium; sem plano, mantém o que já existia.
                    'destaque' => $isPremium ? (isset($_POST['destaque']) ? 1 : 0) : (int)($existente['destaque'] ?? 0),
                    'data_expiracao' => data_ou_null(post_str('data_expiracao')),
                ];
                $erros = [];
                // Admin escolhe a empresa: precisa ser um perfil de EMPRESA ativo (não um candidato).
                if (isAdmin() && $pid && !in_array($pid, array_map(fn($ep) => (int)$ep['id'], $empresas), true)) { $pid = 0; $d['perfil_empresa_id'] = 0; }
                if (!$pid) $erros[] = isAdmin() ? 'Selecione a empresa.' : 'Complete o perfil da empresa antes de publicar.';
                if (post_str('imagem') !== '' && $d['imagem'] === '') $erros[] = 'Caminho de imagem inválido (use um arquivo de assets/img ou envie uma imagem).';
                if ($d['titulo'] === '') $erros[] = 'Informe o título da vaga.';
                if ($d['categoria_id'] && !in_array((int)$d['categoria_id'], array_map(fn($c) => (int)$c['id'], $cats), true)) $d['categoria_id'] = null; // só categorias de vaga
                foreach (['salario_minimo', 'salario_maximo'] as $campo) if ($d[$campo] !== null && ($d[$campo] < 0 || $d[$campo] > 99999999.99)) $erros[] = 'Salário fora do intervalo permitido.';
                if ($d['uf'] !== '' && !preg_match('/^[A-Z]{2}$/', $d['uf'])) $erros[] = 'UF inválida.';
                if ($d['salario_minimo'] !== null && $d['salario_maximo'] !== null && $d['salario_maximo'] < $d['salario_minimo']) $erros[] = 'O salário máximo não pode ser menor que o mínimo.';
                if ($d['data_expiracao'] && $d['data_expiracao'] < date('Y-m-d') && $d['status'] === 'ativa') $erros[] = 'A data de expiração já passou.';
                // O mesmo anúncio não é publicado duas vezes (ex.: o mesmo cartaz enviado de novo).
                $parecida = !$erros && $d['status'] === 'ativa' && !isset($_POST['publicar_duplicada'])
                    ? $dao->buscarParecida($d['titulo'], $d['anunciante'], $d['cidade'], $id) : null;
                if ($parecida) $erros[] = 'Já existe uma vaga aberta igual: #'.(int)$parecida['id'].' — '.$parecida['titulo'].'. Se for outra vaga, marque "publicar mesmo assim".';
                // Limite do plano gratuito vale ao publicar e ao reativar uma vaga
                // (inclusive vaga "ativa" que já tinha expirado e ganhou nova data).
                $estavaAberta = $existente && $dao->estaAberta($existente);
                $limite = null; // null = sem limite (admin, Premium ou vaga que já estava aberta)
                if (!$erros && !isAdmin() && $d['status'] === 'ativa' && !$estavaAberta) {
                    $perm = $assinaturaDao->podePublicarVaga($usuarioId, $pid);
                    if (!$perm['permitido']) { flash('erro', $perm['motivo']); redirect('planos.php'); }
                    if (isset($perm['limite'])) $limite = (int)$perm['limite'];
                }
                $imagemEscolhida = $d['imagem']; // o caminho do formulário (ex.: o cartaz lido), antes do arquivo enviado
                $img = salvar_imagem_enviada('imagem_arquivo', 'vaga');
                if ($img === false) $erros[] = 'Imagem inválida (use JPG, PNG ou WEBP até 3 MB).';
                elseif ($img !== null) $d['imagem'] = $img;

                if ($erros) {
                    // Não deixa arquivo órfão: a imagem recém-enviada sai e o formulário volta com a imagem
                    // escolhida antes (o cartaz lido continua no formulário e não é descartado).
                    if ($img) { apagar_upload_sem_uso($img); $d['imagem'] = $imagemEscolhida; $erros[] = 'Selecione a imagem de novo ao corrigir.'; }
                    flash('erro', implode(' ', $erros));
                    $form = $d + ['id' => $id];
                } else {
                    // Contagem do limite e gravação na mesma transação (evita passar do limite com envios simultâneos).
                    $res = $dao->salvarComLimite($d, $id, $limite);
                    if ($res === 'limite') {
                        if ($img) apagar_upload_sem_uso($img);
                        flash('erro', "Sua empresa atingiu o limite de {$limite} vagas ativas do Plano Básico Gratuito. Assine o Plano Empresa Premium para publicar vagas ilimitadas!");
                        redirect('planos.php');
                    }
                    $novoId = is_int($res) ? $res : false;
                    if (!$novoId && $img) apagar_upload_sem_uso($img); // não deixa arquivo órfão
                    if ($novoId && $existente && ($existente['imagem'] ?? '') !== $d['imagem']) apagar_upload_sem_uso((string)$existente['imagem']);
                    if ($novoId) {
                        $this->descartarCartazesLidos(); // o cartaz usado agora pertence à vaga; outros lidos e não usados saem
                        $n = 0;
                        try { $n = (new MatchService())->recalcularVaga((int)$novoId); } catch (Throwable) {}
                        flash('ok', ($id ? 'Vaga atualizada.' : 'Vaga publicada!').($d['status'] === 'ativa' ? " Match calculado com {$n} candidato(s)." : ''));
                    } else {
                        flash('erro', 'Não foi possível salvar a vaga.');
                    }
                    redirect('admin/pages/vagas.php');
                }
            }
        }

        $edit = get_str('edit') !== '' ? $vagaPermitida((int)get_str('edit')) : null;
        if (get_str('edit') !== '' && !$edit) negar_acesso('Vaga não encontrada ou sem permissão.');
        $parecida ??= null;
        $ocrDisponivel = OcrImagem::disponivel();
        $form ??= $edit ?? ['id' => 0, 'perfil_empresa_id' => 0, 'categoria_id' => null, 'titulo' => '', 'anunciante' => '', 'descricao' => '', 'requisitos' => '', 'beneficios' => '', 'contato' => '', 'tipo_vaga' => 'clt',
            'nivel_experiencia' => 'junior', 'remoto' => 'presencial', 'cidade' => 'Brasília', 'uf' => 'DF', 'salario_minimo' => null, 'salario_maximo' => null,
            'imagem' => 'assets/img/vagas/vaga1.jpg', 'status' => 'ativa', 'destaque' => 0, 'data_expiracao' => null];
        $lista = isAdmin() ? $dao->listar(false) : ($perfil ? $dao->listarPorEmpresa((int)$perfil['id']) : []);
        $imagens = imagens_da_pasta('assets/img/vagas');
        $dinheiro = fn($v) => $v !== null && $v !== '' ? number_format((float)$v, 2, ',', '.') : '';

        $title = 'Vagas';
        $abaAtiva = 'vagas';
        $this->view('admin/vagas', get_defined_vars());
    }

    /**
     * admin/pages/candidaturas.php — candidaturas recebidas (VIP primeiro, depois maior match),
     * com mudança de status e retorno para o candidato. Empresa não marca "cancelada" (ação do
     * candidato) nem altera candidatura cancelada; só o administrador exclui.
     */
    public function candidaturas(): void {
        exigirLogin();
        if (!isEmpresa() && !isAdmin()) negar_acesso('Acesso negado.');

        $perfil = isEmpresa() ? (new PerfilDAO())->obterOuCriar((int)$_SESSION['usuario_id']) : null;
        $dao = new CandidaturaDAO();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $id = post_int('id');
            // Empresa não pode marcar "cancelada" (é ação do candidato); admin pode tudo.
            $status = enum_val(post_str('status'), isAdmin() ? CandidaturaDAO::STATUS : CandidaturaDAO::STATUS_EMPRESA, '');
            $obs = mb_substr(post_str('observacao'), 0, 2000);
            if (post_str('acao') === 'excluir') {
                $ok = isAdmin() && $dao->excluir($id);
                flash($ok ? 'ok' : 'erro', $ok ? 'Candidatura excluída.' : (isAdmin() ? 'Candidatura não encontrada.' : 'Somente o administrador pode excluir candidaturas.'));
            } else {
                $ok = $status !== '' && (isAdmin() ? $dao->atualizarStatus($id, $status, $obs) : ($perfil && $dao->atualizarStatusPorEmpresa($id, (int)$perfil['id'], $status, $obs)));
                flash($ok ? 'ok' : 'erro', $ok ? 'Candidatura atualizada. O candidato vê o novo status e o retorno no perfil dele.' : 'Não foi possível atualizar (candidatura inexistente, de outra empresa ou cancelada pelo candidato).');
            }
            $volta = array_filter(['vaga_id' => post_int('vaga_id') ?: null, 'status' => enum_val(post_str('filtro_status'), CandidaturaDAO::STATUS, '') ?: null]);
            redirect('admin/pages/candidaturas.php'.($volta ? '?'.http_build_query($volta) : ''));
        }

        $vagaId = (int)get_str('vaga_id');
        $status = enum_val(get_str('status'), CandidaturaDAO::STATUS, '');
        $statusPermitidos = isAdmin() ? CandidaturaDAO::STATUS : CandidaturaDAO::STATUS_EMPRESA;
        $lista = isAdmin() ? $dao->listarTodas($vagaId, $status) : ($perfil ? $dao->listarPorEmpresa((int)$perfil['id'], $vagaId, $status) : []);
        $vagasFiltro = isAdmin() ? (new VagaDAO())->listar(false) : ($perfil ? (new VagaDAO())->listarPorEmpresa((int)$perfil['id']) : []);

        $title = 'Candidaturas';
        $abaAtiva = 'candidaturas';
        $this->view('admin/candidaturas', get_defined_vars());
    }

    /**
     * admin/pages/talentos.php — Banco de Talentos: candidatos com perfil público.
     * Completo (nome, contato, currículo) só no plano Premium; no básico, os dados ficam ocultos.
     */
    public function talentos(): void {
        exigirLogin();
        if (!isEmpresa() && !isAdmin()) negar_acesso('Acesso restrito a empresas e administradores.');

        $assinaturaDao = new AssinaturaDAO();
        $usuarioId = (int)$_SESSION['usuario_id'];
        $isPremium = isAdmin() || $assinaturaDao->isEmpresaPremium($usuarioId);

        $termo = get_str('q');
        $cidade = get_str('cidade');
        $talentos = (new PerfilDAO())->listarTalentos($termo, $cidade);
        // Sem Premium o nome fica oculto, então a busca não pode confirmar o nome de ninguém: só valem os
        // candidatos encontrados pelo cargo, habilidades, experiências ou cursos (não pelo nome nem pelos links).
        if (!$isPremium && $termo !== '') {
            $busca = Competencias::normalizar($termo);
            $talentos = array_values(array_filter($talentos, fn($t) => str_contains(Competencias::normalizar(implode(' ', [
                $t['titulo_profissional'] ?? '', $t['habilidades'] ?? '', $t['competencias'] ?? '', $t['experiencias'] ?? '', $t['cursos_complementares'] ?? '',
            ])), $busca)));
        }

        $title = 'Banco de Talentos';
        $abaAtiva = 'talentos';
        $this->view('admin/talentos', get_defined_vars());
    }

    /** admin/pages/empresa_perfil.php — dados da empresa (o nome fantasia aparece nas vagas) e logo. */
    public function perfil(): void {
        exigirLogin();
        if (!isEmpresa()) negar_acesso('Acesso restrito a empresas.');
        $dao = new PerfilDAO();
        $p = $dao->obterOuCriar((int)$_SESSION['usuario_id']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $d = $p; // mantém os campos que não são da empresa
            foreach (['nome_fantasia','cnpj','setor','site','cidade','uf','bio'] as $c) $d[$c] = post_str($c);
            $erros = [];
            if ($d['nome_fantasia'] === '') $erros[] = 'Informe o nome fantasia.';
            $cnpj = preg_replace('/\D/', '', $d['cnpj']) ?? '';
            if ($cnpj !== '' && strlen($cnpj) !== 14) $erros[] = 'CNPJ deve ter 14 dígitos.';
            // Só http/https: o site vira link no portfólio e nas vagas (bloqueia "javascript:").
            if ($d['site'] !== '' && !url_http_valida($d['site'])) $erros[] = 'O site precisa ser um endereço válido começando com http:// ou https://.';
            if ($d['uf'] !== '' && !preg_match('/^[A-Za-z]{2}$/', $d['uf'])) $erros[] = 'UF inválida.';
            if ($erros) { flash('erro', implode(' ', $erros)); redirect('admin/pages/empresa_perfil.php'); }
            if ($cnpj !== '') $d['cnpj'] = vsprintf('%s.%s.%s/%s-%s', [substr($cnpj, 0, 2), substr($cnpj, 2, 3), substr($cnpj, 5, 3), substr($cnpj, 8, 4), substr($cnpj, 12, 2)]);
            $logo = salvar_imagem_enviada('logo', 'logo');
            if ($logo === false) { flash('erro', 'Logo inválida (JPG, PNG ou WEBP até 3 MB).'); redirect('admin/pages/empresa_perfil.php'); }
            if ($logo) $d['foto'] = $logo;
            $d['publico'] = 1; $d['aceite_lgpd'] = 1;
            $ok = $dao->salvar(new PerfilDTO($d));
            if ($ok && $logo && !empty($p['foto']) && $p['foto'] !== $logo) apagar_upload_sem_uso((string)$p['foto']); // logo antiga
            if (!$ok && $logo) apagar_upload_sem_uso($logo);
            $tel = mb_substr(post_str('telefone'), 0, 30);
            (new UsuarioDAO())->atualizarTelefone((int)$_SESSION['usuario_id'], $tel ?: null);
            flash($ok ? 'ok' : 'erro', $ok ? 'Perfil da empresa atualizado. Esse nome aparece nas suas vagas.' : 'Não foi possível salvar.');
            redirect('admin/pages/empresa_perfil.php');
        }

        $title = 'Perfil da empresa';
        $abaAtiva = 'empresa';
        $this->view('admin/empresa_perfil', get_defined_vars());
    }

    /**
     * Imagem informada no formulário da vaga: arquivo de assets/img, a imagem atual da vaga ou um
     * cartaz lido (assets/uploads/cartaz_*) que ainda existe. Outros uploads não são aceitos. '' = inválida.
     */
    private function imagemDoFormulario(string $caminho, ?array $existente): string {
        $caminho = caminho_imagem_valido($caminho);
        if ($caminho === '' || !str_starts_with($caminho, 'assets/uploads/') || $caminho === ($existente['imagem'] ?? null)) return $caminho;
        $arquivo = caminho_upload($caminho);
        return $arquivo !== null && str_starts_with(basename($caminho), 'cartaz_') && is_file($arquivo) ? $caminho : '';
    }

    /**
     * Apaga os cartazes lidos nesta sessão que não viraram vaga (nova leitura ou vaga já salva).
     * Só arquivos enviados pela própria sessão — nunca um caminho vindo do formulário.
     */
    private function descartarCartazesLidos(): void {
        foreach ((array)($_SESSION['cartazes_lidos'] ?? []) as $c) if (is_string($c)) apagar_upload_sem_uso($c);
        unset($_SESSION['cartazes_lidos']);
    }
}
