<?php
declare(strict_types=1);

/**
 * Ligação dos controllers com a máquina de aprendizado (usada por EmpresaController, AdminController,
 * CurriculoController e PerfilController).
 *
 * O problema que ela resolve: a sugestão da máquina acontece numa requisição (o clique em "Extrair")
 * e o salvamento em outra (o clique em "Salvar"). Entre as duas, a sugestão fica guardada na sessão.
 *
 *   extrair → guardarSugestao()  : guarda a sugestão e devolve um código;
 *             o código vai num campo escondido do formulário (name="sugestao_maquina");
 *   salvar  → aprenderComRevisao(): se o formulário salvo trouxe o MESMO código, compara e aprende.
 *
 * O código garante que a máquina só aprende com o formulário que ela mesma preencheu. Se a pessoa
 * extraiu um anúncio, desistiu e depois editou outra vaga à mão, os dois não se misturam.
 * (No currículo não há código: a sugestão e o perfil salvo são do mesmo candidato logado.)
 * Cada sugestão ensina uma vez só (é apagada da sessão depois de usada) e vence em 2 horas.
 */
trait AprendeComRevisao {
    /**
     * Guarda a sugestão da máquina até a pessoa salvar.
     * A sugestão chega como função ($montar) e é montada aqui dentro do try: se algo falhar, a página
     * segue sem aprendizado (sem código no formulário) em vez de dar erro.
     * @param callable():array $montar monta a sugestão (MaquinaAprendizado::sugestao(...))
     * @return string código da sugestão (vai no formulário); '' se não deu para guardar
     */
    protected function guardarSugestao(string $origem, callable $montar): string {
        try {
            $codigo = bin2hex(random_bytes(8));
            $_SESSION['sugestao_maquina'][$origem] = ['codigo' => $codigo, 'sugestao' => $montar()];
            return $codigo;
        } catch (Throwable $e) {
            error_log('[AprendeComRevisao] Não foi possível guardar a sugestão de '.$origem.': '.$e->getMessage());
            return '';
        }
    }

    /**
     * Depois de salvar: compara o que foi salvo com a sugestão e ensina a máquina.
     * @param string|null $codigo código que veio no formulário; null = não confere (currículo: a sugestão é do próprio candidato)
     * @return array|null resumo do aprendizado, ou null quando não havia sugestão para comparar
     */
    protected function aprenderComRevisao(string $origem, array $salvo, ?string $codigo = null): ?array {
        $guardada = $_SESSION['sugestao_maquina'][$origem] ?? null;
        if (!is_array($guardada)) return null;
        if ($codigo !== null && !hash_equals((string)$guardada['codigo'], $codigo)) return null;
        unset($_SESSION['sugestao_maquina'][$origem]);
        $sugestao = (array)$guardada['sugestao'];
        if (time() - (int)($sugestao['criada'] ?? 0) > 2 * 3600) return null;
        // Revisão do administrador é "confiável": pode trocar a resposta de uma lição que outras pessoas já confirmaram.
        return MaquinaAprendizado::aprenderDaRevisao($sugestao, $salvo, isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null, isAdmin());
    }
}
