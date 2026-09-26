<?php
declare(strict_types=1);

/*
 * Gera docs/PROMPTS_PESQUISA.md com o PROMPT PADRÃO (e o avulso), usando as áreas de curso cadastradas.
 * Uso (depois de criar ou renomear áreas de curso):  C:\xampp\php\php.exe docs\gerar_prompts.php
 * O prompt não aparece no painel: fica neste documento. O código dele está em
 * app/Services/Extracao/PromptsPesquisa.php.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente pelo terminal.'); }
require __DIR__.'/../app/Core/bootstrap.php';

try {
    $areas = array_column(array_filter((new CategoriaDAO())->listar('curso'), fn($c) => (int)$c['ativo']), 'nome');
} catch (Throwable) {
    $areas = ExtracaoCurso::AREAS;   // banco desligado: usa as áreas do seed
}

$modelo = "Título:\nTipo:\nInstituição:\nModalidade:\nCidade:\nNível:\nCarga horária:\nGratuito:\nPreço:\nÁrea:\nLink: https://...\nImagem: https://...\nDescrição:";
$md = "# Prompt padrão — pesquisa de cursos e e-books para o cadastro\n\n";
$md .= "Peça a uma IA de pesquisa (Perplexity, ChatGPT, Gemini, Copilot, Claude) que pesquise **cada link ou título** e devolva uma **ficha** com os mesmos campos do cadastro. Depois é só colar a resposta na caixa **Extrair** do painel (**Painel → Cursos e e-books**):\n\n";
$md .= "- **uma ficha** → preenche o formulário: revise e clique em **Salvar conteúdo**;\n- **várias fichas** (separadas por `---`) → prévia: confira e clique em **Cadastrar marcados**;\n";
$md .= "- **com imagem** na ficha, ela é conferida e baixada; **sem imagem**, o conteúdo entra com a **imagem padrão** da plataforma e aparece na lista como *trocar imagem* (use Editar quando tiver a imagem certa);\n";
$md .= "- **PDF na nossa biblioteca**: no cadastro, envie o PDF do e-book e o botão vira **Baixar**; conteúdo que fica na web mostra **Acessar**.\n\n";
$md .= "> Gerado em ".date('d/m/Y')." com as áreas cadastradas. Criou ou renomeou áreas? Rode `C:\\xampp\\php\\php.exe docs\\gerar_prompts.php`.\n\n";
$md .= "## Modelo da ficha\n\n```text\n{$modelo}\n```\n\n";
$md .= "## Onde colar o prompt em cada IA\n\n| IA | Como usar |\n|---|---|\n";
foreach (PromptsPesquisa::IAS as $c) $md .= '| '.$c['nome'].' | '.$c['onde']." |\n";
$md .= "\nDepois de configurado, cada mensagem é só a lista de **links e/ou títulos**, um por linha.\n\n";
$md .= "## Prompt padrão (configure uma vez)\n\n```text\n".PromptsPesquisa::mestre('chatgpt', $areas)."\n```\n\n";
$md .= "No **Microsoft Copilot** (que não guarda instruções), cole o prompt como primeira mensagem e acrescente no fim: *Agora responda apenas: Pronto.*\n\n";
$md .= "## Prompt avulso (uma conversa só, sem configurar nada)\n\nTroque os itens pelos seus links ou títulos:\n\n```text\n"
     .PromptsPesquisa::avulso(['https://cartilha.cert.br/', 'Como Elaborar um Currículo (SEBRAE)'], $areas)."\n```\n";
file_put_contents(__DIR__.'/PROMPTS_PESQUISA.md', str_replace("\r\n", "\n", $md));
echo 'docs/PROMPTS_PESQUISA.md atualizado ('.count($areas).' áreas).'.PHP_EOL;
