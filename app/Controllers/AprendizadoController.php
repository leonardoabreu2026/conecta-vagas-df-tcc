<?php
declare(strict_types=1);

/**
 * Painel "Aprendizado da máquina" (rota admin/pages/aprendizado.php) — só administrador.
 *
 * Mostra o que a máquina de extração já aprendeu com as revisões das pessoas e deixa o
 * administrador cuidar disso:
 *  - números: lições por modelo, revisões, taxa de acerto no começo × agora, acerto por dia;
 *  - o que cada modelo aprendeu: lições por classe e as palavras mais típicas de cada uma;
 *  - memórias de nomes (empresas e instituições confirmadas);
 *  - "Teste a máquina": digita um texto e vê a previsão, a confiança e as palavras que pesaram;
 *  - "Aprender com o histórico": ensina com as vagas, cursos e perfis que já estão cadastrados;
 *  - esquecer uma lição errada ou zerar um modelo (volta a usar só as regras).
 */
final class AprendizadoController extends Controller {
    public function painel(): void {
        exigirAdmin();
        $dao = new AprendizadoDAO();
        $usuarioId = (int)$_SESSION['usuario_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validar_csrf();
            $acao = post_str('acao');

            if ($acao === 'esquecer') {
                $ok = MaquinaAprendizado::esquecer(post_int('id'));
                flash($ok ? 'ok' : 'erro', $ok ? 'Lição apagada: a máquina deixa de usá-la nas próximas decisões.' : 'Lição não encontrada.');
                redirect('admin/pages/aprendizado.php'.(post_str('f_modelo') !== '' ? '?modelo='.rawurlencode(post_str('f_modelo')) : '').'#licoes');
            }

            if ($acao === 'zerar') {
                $modelo = post_str('modelo');
                $n = MaquinaAprendizado::zerar($modelo);
                flash('ok', $n ? 'Aprendizado zerado: '.$n.' '.($n === 1 ? 'lição apagada' : 'lições apagadas').'. Nesse modelo, a extração volta a usar só as regras.' : 'Não havia nada aprendido nesse modelo.');
                redirect('admin/pages/aprendizado.php');
            }

            if ($acao === 'historico') {
                // Ensina com o que já foi cadastrado e revisado por pessoas (pode levar alguns segundos).
                set_time_limit(300);
                $n = MaquinaAprendizado::aprenderComHistorico('vaga', (new VagaDAO())->listar(false), $usuarioId)
                   + MaquinaAprendizado::aprenderComHistorico('curso', (new CursoDAO())->listar(false), $usuarioId)
                   + MaquinaAprendizado::aprenderComHistorico('curriculo', (new PerfilDAO())->listarCandidatos(), $usuarioId);
                flash($n ? 'ok' : 'info', $n ? 'A máquina estudou o histórico: '.$n.' '.($n === 1 ? 'lição aprendida ou confirmada' : 'lições aprendidas ou confirmadas').' com as vagas, cursos e perfis cadastrados.' : 'Não há vagas, cursos ou perfis cadastrados para aprender.');
                redirect('admin/pages/aprendizado.php');
            }
            redirect('admin/pages/aprendizado.php');
        }

        // ---- o que cada modelo aprendeu
        $resumo = $dao->resumoModelos();
        $modelos = [];
        foreach (MaquinaAprendizado::MODELOS as $nome => [$titulo, $descricao, $rotulos]) {
            $nb = MaquinaAprendizado::modelo($nome);
            $classes = [];
            foreach ($nb->exemplosPorClasse() as $classe => $qtd) {
                $classe = (string)$classe;   // categoria só com números ("2024") vira chave inteira no array
                $classes[] = ['classe' => $classe, 'rotulo' => $rotulos[$classe] ?? $classe, 'licoes' => $qtd, 'palavras' => $nb->palavrasTipicas($classe, 6)];
            }
            // "Liberado" = passou no período de experiência (MIN_PROVAS provas com PRECISAO_MINIMA de acerto).
            // Mesmo liberado, em cada texto ele só decide quando está confiante (ver MaquinaAprendizado::prever()).
            $modelos[$nome] = ['titulo' => $titulo, 'descricao' => $descricao, 'rotulos' => $rotulos, 'total' => $nb->totalExemplos(), 'classes' => $classes,
                               'experiencia' => MaquinaAprendizado::desempenho($nome), 'ultima' => $resumo[$nome]['ultima'] ?? null];
        }
        $memorias = [];
        foreach (MaquinaAprendizado::MEMORIAS as $nome => [$titulo, $descricao]) {
            $memorias[$nome] = ['titulo' => $titulo, 'descricao' => $descricao, 'total' => $resumo[$nome]['licoes'] ?? 0, 'nomes' => $dao->nomes($nome, 18)];
        }

        // ---- evolução do acerto
        $revisoes = $dao->resumoRevisoes();
        $acertoDia = $dao->acertoPorDia(60);
        $totalLicoes = array_sum(array_column($resumo, 'licoes'));
        $totalRevisoes = array_sum(array_column($revisoes, 'revisoes'));
        $modelosLiberados = count(array_filter($modelos, fn($m) => $m['experiencia']['liberado']));

        // ---- teste a máquina (GET: não muda nada)
        $testeModelo = enum_val(get_str('testar'), array_keys(MaquinaAprendizado::MODELOS), 'vaga_linha');
        $testeTexto = mb_substr(get_str('texto'), 0, 2000);
        $teste = $testeTexto !== '' ? MaquinaAprendizado::prever($testeModelo, $testeTexto) : null;

        // ---- últimas lições
        $todosModelos = MaquinaAprendizado::MODELOS + MaquinaAprendizado::MEMORIAS;
        $filtroModelo = isset($todosModelos[get_str('modelo')]) ? get_str('modelo') : '';
        $licoes = $dao->ultimosExemplos(40, $filtroModelo);

        $title = 'Aprendizado da máquina';
        $abaAtiva = 'aprendizado';
        $this->view('admin/aprendizado', get_defined_vars());
    }
}
