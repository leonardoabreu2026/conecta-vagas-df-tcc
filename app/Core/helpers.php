<?php
declare(strict_types=1);

/*
 * Funções de apoio usadas em todo o sistema (controllers, views, models e services).
 * São funções simples e sem estado:
 *  - saída segura e endereços ............ e(), url(), redirect()
 *  - leitura segura de formulários ....... post_str(), post_int(), get_str(), old(), enum_val()
 *  - conversão/validação de valores ...... decimal_ou_null(), data_ou_null(), normalizar_email(), url_http_valida()
 *  - textos exibidos na tela ............. rotulo(), salario_texto(), classe_match()
 *  - informações da requisição e log ..... ip_cliente(), mensagem_erro_banco(), registrar_log()
 */

// ------------------------------------------------------------
// Saída segura e endereços
// ------------------------------------------------------------

/** Escapa um valor para exibir no HTML (evita XSS). Use em TODO texto vindo do banco ou do usuário. */
function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/** Endereço completo de uma página ou arquivo do projeto. Ex.: url('vagas.php') → http://localhost/.../vagas.php */
function url(string $path=''): string { return SITE_URL . ltrim($path, '/'); }

/** Redireciona para outra página do projeto e encerra a requisição. */
function redirect(string $path): never { header('Location: '.url($path)); exit; }

// ------------------------------------------------------------
// Leitura segura de $_POST/$_GET
// ------------------------------------------------------------
// Com strict_types, trim(null) gera TypeError; por isso campo ausente (ou
// enviado como lista) vira o valor padrão.

function post_str(string $key, string $default=''): string { $v=$_POST[$key]??$default; return is_scalar($v) ? trim((string)$v) : $default; }
function post_int(string $key, int $default=0): int { $v=$_POST[$key]??$default; return is_numeric($v) ? (int)$v : $default; }
function get_str(string $key, string $default=''): string { $v=$_GET[$key]??$default; return is_scalar($v) ? trim((string)$v) : $default; }
/** Termo de busca para LIKE: "%termo%" com % e _ digitados tratados como texto (não como curinga). */
function like(string $termo): string { return '%'.addcslashes($termo, '%_\\').'%'; }
/** "?tipo=ebook&q=excel": filtros da lista que o formulário da ação reenviou (campos f_tipo, f_q), para voltar à mesma lista. */
function volta_filtros(array $chaves): string {
    $q = [];
    foreach ($chaves as $k) { $v = mb_substr(post_str('f_'.$k), 0, 100); if ($v !== '') $q[$k] = $v; }
    return $q ? '?'.http_build_query($q) : '';
}

/** Valor digitado anteriormente (para repreencher o formulário depois de um erro), já escapado. */
function old(string $key,string $default=''): string { $v=$_POST[$key]??$default; return e(is_scalar($v) ? (string)$v : $default); }

/** Retorna o valor se estiver na lista permitida; caso contrário, o padrão. */
function enum_val(string $valor, array $permitidos, string $padrao): string { return in_array($valor, $permitidos, true) ? $valor : $padrao; }

// ------------------------------------------------------------
// Conversão e validação de valores
// ------------------------------------------------------------

/** Converte "1.234,56" ou "1234.56" em float; vazio vira null. */
function decimal_ou_null(string $v): ?float {
    $v=trim(str_ireplace(['R$',' '],'',$v)); if($v==='') return null;
    if(preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/',$v)) $v=str_replace(['.',','],['','.'],$v); // 3.000 / 1.234,56
    elseif(str_contains($v, ',')) $v=str_replace(['.',','],['','.'],$v);                    // 1234,56
    return is_numeric($v) ? round((float)$v, 2) : null;
}

/** Data no formato AAAA-MM-DD (campo type="date"); inválida ou vazia vira null. */
function data_ou_null(string $v): ?string { $d=DateTime::createFromFormat('Y-m-d',$v); return $d && $d->format('Y-m-d')===$v ? $v : null; }

/**
 * trim() que entende acentos e símbolos (•, –, —...). O trim() do PHP corta BYTES: com esses
 * símbolos na lista ele pode partir letras como "Ô" no fim da palavra ("VOVÔ" → texto inválido).
 */
function trim_u(string $s, string $chars = " \t\n\r\0\x0B", string $lado = 'ambos'): string {
    $c = '['.preg_quote($chars, '/').']+';
    $rx = match ($lado) { 'inicio' => "/^$c/u", 'fim' => "/$c$/u", default => "/^$c|$c$/u" };
    return preg_replace($rx, '', $s) ?? trim($s);
}

/** E-mail sempre em minúsculas e sem espaços (é assim que fica gravado no banco). */
function normalizar_email(string $email): string { return strtolower(trim($email)); }

/** URL externa segura para links (só http/https; bloqueia javascript:, data: etc.). */
function url_http_valida(string $u): bool {
    return filter_var($u, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $u) === 1;
}

// ------------------------------------------------------------
// Textos exibidos na tela
// ------------------------------------------------------------

/** Faixa salarial legível: "R$ 1.900,00 a R$ 2.500,00", "R$ 1.900,00" ou "A combinar". */
function salario_texto(mixed $min, mixed $max): string {
    $fmt=fn($x)=>'R$ '.number_format((float)$x,2,',','.');
    if(!$min && !$max) return 'A combinar';
    if($min && $max && (float)$max>(float)$min) return $fmt($min).' a '.$fmt($max);
    return $fmt($min ?: $max);
}

/** Classe de cor do selo de match (excelente/alto/medio/baixo) — mesmos cortes da máquina de match. */
function classe_match(float $p): string { return MatchService::classificar($p); }

/** Rótulo em português para os valores gravados no banco (ex.: 'em_analise' → 'Em análise'). */
function rotulo(string $v): string {
    static $map=['estagiario'=>'Estagiário','junior'=>'Júnior','pleno'=>'Pleno','senior'=>'Sênior','clt'=>'CLT','pj'=>'PJ','estagio'=>'Estágio','temporario'=>'Temporário',
        'presencial'=>'Presencial','remoto'=>'Remoto','hibrido'=>'Híbrido','ead'=>'EAD','iniciante'=>'Iniciante','intermediario'=>'Intermediário','avancado'=>'Avançado',
        'enviada'=>'Enviada','em_analise'=>'Em análise','entrevista'=>'Entrevista','aprovado'=>'Aprovado','rejeitado'=>'Não selecionado','cancelada'=>'Cancelada',
        'ativa'=>'Ativa','pausada'=>'Pausada','encerrada'=>'Encerrada','curso'=>'Curso','ebook'=>'E-book','video'=>'Vídeo','excelente'=>'Excelente','alto'=>'Alto','medio'=>'Médio','baixo'=>'Baixo',
        'admin'=>'Administrador','candidato'=>'Candidato','empresa'=>'Empresa','vaga'=>'Vaga'];
    return $map[$v] ?? ucfirst(str_replace('_',' ',$v));
}

// ------------------------------------------------------------
// Requisição e registros internos
// ------------------------------------------------------------

/** IP do cliente (REMOTE_ADDR; cabeçalhos X-Forwarded-For são ignorados por serem falsificáveis). */
function ip_cliente(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }

/**
 * Texto de uma falha do banco para mostrar na tela. O detalhe técnico (SQLSTATE, tabela...)
 * só aparece com DEBUG=true; a DatabaseException já traz uma orientação amigável.
 */
function mensagem_erro_banco(Throwable $e): string {
    $msg = DEBUG || $e instanceof DatabaseException ? trim($e->getMessage()) : '';
    return $msg !== '' ? $msg : 'Tente novamente em instantes.';
}

/** Grava uma linha num registro interno em storage/logs/ (pasta bloqueada ao navegador). */
function registrar_log(string $arquivo, string $linha): void {
    if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0775, true);
    @file_put_contents(LOG_DIR.basename($arquivo), '['.date('Y-m-d H:i:s').'] '.$linha.PHP_EOL, FILE_APPEND | LOCK_EX);
}
