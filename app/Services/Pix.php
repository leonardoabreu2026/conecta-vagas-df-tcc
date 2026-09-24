<?php
declare(strict_types=1);

/**
 * Código Pix "copia e cola" (BR Code do Banco Central) para o QR Code de doação do rodapé.
 * Só monta o texto — nenhum dado sai do servidor e nenhuma API é chamada. O mesmo texto vira
 * o QR Code (desenhado no navegador por assets/js/vendor/qrcode.js) e o botão "Copiar código Pix".
 *
 * Formato EMV: cada campo é ID (2 dígitos) + tamanho (2 dígitos) + valor; termina com o CRC16
 * (CCITT-FALSE, polinômio 0x1021, início 0xFFFF) do texto todo, como o app do banco confere.
 * A chave, o nome e a cidade vêm de config/config.php (DOACAO_PIX_CHAVE, DOACAO_NOME, DOACAO_CIDADE).
 */
final class Pix {
    /** Código pronto, ou '' se a chave Pix ainda não foi configurada. */
    public static function doacao(): string {
        $chave = defined('DOACAO_PIX_CHAVE') ? trim((string)DOACAO_PIX_CHAVE) : '';
        if ($chave === '') return '';
        return self::payload($chave, (string)(defined('DOACAO_NOME') ? DOACAO_NOME : SITE_NAME), (string)(defined('DOACAO_CIDADE') ? DOACAO_CIDADE : 'Brasilia'), null, 'Apoio ao projeto');
    }

    /**
     * @param ?float $valor null = quem doa escolhe o valor no app do banco
     */
    public static function payload(string $chave, string $nome, string $cidade, ?float $valor = null, string $descricao = '', string $txid = '***'): string {
        $conta = self::campo('00', 'br.gov.bcb.pix').self::campo('01', $chave);
        $descricao = mb_substr(self::ascii($descricao), 0, 40);
        // O campo 26 inteiro tem no máximo 99 caracteres: a descrição entra só se couber.
        if ($descricao !== '' && strlen($conta.self::campo('02', $descricao)) <= 99) $conta .= self::campo('02', $descricao);
        $txid = preg_replace('/[^A-Za-z0-9*]/', '', $txid) ?: '***';
        $p = self::campo('00', '01')
           .self::campo('26', $conta)
           .self::campo('52', '0000')                     // categoria do recebedor: não informada
           .self::campo('53', '986')                      // moeda: real
           .($valor !== null && $valor > 0 ? self::campo('54', number_format($valor, 2, '.', '')) : '')
           .self::campo('58', 'BR')
           .self::campo('59', substr(self::ascii($nome), 0, 25) ?: 'CONECTA VAGAS DF')
           .self::campo('60', substr(self::ascii($cidade), 0, 15) ?: 'BRASILIA')
           .self::campo('62', self::campo('05', substr($txid, 0, 25)))
           .'6304';
        return $p.self::crc16($p);
    }

    private static function campo(string $id, string $valor): string {
        return $id.str_pad((string)strlen($valor), 2, '0', STR_PAD_LEFT).$valor;
    }

    /** O BR Code aceita só ASCII: tira acentos ("Brasília" → "BRASILIA"). */
    private static function ascii(string $s): string {
        $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','î'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ü'=>'u','ç'=>'c',
                        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C']);
        return strtoupper(trim(preg_replace('/[^\x20-\x7E]/', '', $s) ?? ''));
    }

    public static function crc16(string $s): string {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $crc ^= ord($s[$i]) << 8;
            for ($b = 0; $b < 8; $b++) $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
