<?php
declare(strict_types=1);

/**
 * Doação por Pix — a alternativa para quem não quer assinar um plano. Aparece para TODOS os visitantes:
 * faixa na página inicial, rodapé de todas as páginas e um dos painéis relâmpago.
 * O código "copia e cola" vem de Pix::doacao() (chave em config/config.php → DOACAO_PIX_CHAVE);
 * o QR é desenhado no navegador por assets/js/vendor/qrcode.js (app.js, [data-qrcode]).
 * Sem chave configurada, o espaço do QR mostra "em configuração" (o texto e o convite continuam visíveis).
 */

const DOACAO_TITULO = 'Apoie o Conecta Vagas DF';
const DOACAO_TEXTO = 'Gostou do nosso trabalho? Gostou do nosso site? Não quer fazer uma assinatura? Ajude o nosso projeto com uma doação de qualquer valor pelo QR Code Pix.';

/** QR Code (ou o aviso "em configuração") num quadro branco. $tam em px. */
function cv_doacao_qr(string $pix, int $tam = 188): string {
    if ($pix === '') {
        return '<div class="cv-doacao-codigo cv-doacao-pendente" style="width:'.$tam.'px;height:'.$tam.'px" role="img" aria-label="QR Code Pix em configuração">'
             .icone('coracao', (int)round($tam / 4)).'<span>QR Code Pix<br>em configuração</span></div>';
    }
    return '<div class="cv-doacao-codigo" style="width:'.$tam.'px;height:'.$tam.'px" data-qrcode="'.e($pix).'" role="img" aria-label="QR Code Pix para doação ao Conecta Vagas DF">'
         .'<noscript><p class="small">Ative o JavaScript para ver o QR Code ou use "Copiar código Pix".</p></noscript></div>';
}

/** Botão "Copiar código Pix" (some enquanto a chave não estiver configurada). */
function cv_doacao_copiar(string $pix, string $classe = 'cv-btn cv-btn-verde'): string {
    if ($pix === '') return '';
    return '<button type="button" class="'.e($classe).' cv-doacao-copiar" data-copiar="'.e($pix).'" data-copiado="Código Pix copiado!">'
         .icone('copiar', 16).'<span>Copiar código Pix</span></button>';
}

/** Faixa de destaque da página inicial, logo depois das assinaturas: "Não quer assinar? Doe." */
function cv_doacao_faixa(string $pix): string {
    return '<section class="cv-doacao-faixa" aria-labelledby="doacao-home-titulo"><div class="cv-wrap cv-doacao-faixa-in">'
         .'<div class="cv-doacao-faixa-txt">'
         .'<span class="cv-doacao-selo">'.icone('coracao', 14).'Sem assinatura? Tudo bem!</span>'
         .'<h2 id="doacao-home-titulo">'.e(DOACAO_TITULO).' com qualquer valor</h2>'
         .'<p>'.e(DOACAO_TEXTO).' Sua ajuda mantém o site no ar e gratuito para quem procura emprego no DF.</p>'
         .'<ol class="cv-doacao-passos"><li>Abra o app do seu banco em <b>Pix › Ler QR Code</b>.</li><li>Aponte a câmera para o código.</li><li>Escolha o valor e confirme. Obrigado!</li></ol>'
         .cv_doacao_copiar($pix)
         .'</div>'
         .'<figure class="cv-doacao-qr">'.cv_doacao_qr($pix, 200).'<figcaption>Pix · valor livre</figcaption></figure>'
         .'</div></section>';
}
