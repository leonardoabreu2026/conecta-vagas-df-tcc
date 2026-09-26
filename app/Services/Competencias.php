<?php
declare(strict_types=1);

/**
 * Dicionário único de competências usado por toda a plataforma:
 * - extração do currículo (o que o candidato sabe);
 * - extração das vagas (o que a vaga exige);
 * - extração dos cursos (o que o curso ensina);
 * - máquina de match (compara os três).
 *
 * Cada competência tem um nome canônico e seus sinônimos já normalizados
 * (minúsculas, sem acento). A busca é por palavra/frase inteira.
 */
final class Competencias {
    public const DICIONARIO = [
        // Atendimento, comércio e escritório
        'Atendimento ao cliente' => ['frentista','posto de combustivel','operador de loja','operadora de loja','atendente de loja','experiencia do cliente','atendimento','atendente','atender clientes','atendimento ao cliente','atendimento ao publico','sac','recepcao','recepcionista'],
        'Vendas' => ['corretora','vendas','promotor de vendas','consultor comercial','televendas','porta a porta','sdr','representante comercial','corretor','corretores','prospeccao','venda','vendedor','vendedora','comercial','consultor de vendas','vendas internas','varejo'],
        'Negociação' => ['negociacao','negociar','negociador'],
        'Operação de caixa' => ['operador de caixa','operadora de caixa','operacao de caixa','frente de caixa','caixa de loja'],
        'Telemarketing' => ['cobranca','operador de cobranca','retencao','telemarketing','call center','atendimento telefonico','teleatendimento'],
        'Excel' => ['excel','planilha','planilhas','planilhas eletronicas'],
        'Pacote Office' => ['pacote office','microsoft office','ms office','word','powerpoint','office 365'],
        'Informática' => ['informatica','informatica basica','computador','digitacao','windows'],
        'Rotinas administrativas' => ['rotinas administrativas','auxiliar administrativo','assistente administrativo','administrativo','administrativa','organizacao de documentos','arquivo de documentos','controle de documentos'],
        'Administração' => ['administracao','adm','gestao administrativa'],
        'Gestão e liderança' => ['gestao','gestao de equipes','gerente','gerencia','lideranca','supervisor','supervisora','supervisao','coordenacao','coordenador','coordenadora','lider de equipe'],
        'Empreendedorismo' => ['empreendedorismo','empreendedor','empreendedora','pequenos negocios','negocio proprio','negocios'],
        'Finanças' => ['financeiro','financeira','financas','contas a pagar','contas a receber','fluxo de caixa','tesouraria','faturamento'],
        'Contabilidade' => ['legislacao tributaria','obrigacoes acessorias','icms','contabilidade','contabil','fiscal','escrita fiscal'],
        'Recursos Humanos' => ['dp','recursos humanos','rh','departamento pessoal','recrutamento','selecao de pessoas','folha de pagamento'],
        'Marketing digital' => ['marketing','marketing digital','redes sociais','midias sociais','social media','trafego pago','seo','publicidade'],
        'Análise de dados' => ['analise de dados','dados','power bi','dashboard','dashboards','indicadores','business intelligence'],
        'UX e design' => ['ux','ui','ux ui','design','designer','figma','experiencia do usuario','design grafico','canva','photoshop'],
        'ESG e sustentabilidade' => ['esg','sustentabilidade','meio ambiente'],
        'Compras' => ['compras','comprador','compradora','suprimentos'],
        'Estoque e almoxarifado' => ['estoquista','deposito','auxiliar de deposito','abastecimento de produtos','estoque','almoxarifado','reposicao','repositor','repositora','inventario','conferente','conferencia de mercadorias'],
        'Logística e entregas' => ['ajudante geral','motoboy','logistica','entregas','entrega','entregador','entregadora','expedicao','roteirizacao'],

        // Tecnologia
        'PHP' => ['php','laravel'],
        'JavaScript' => ['javascript','js','typescript','node','nodejs','node js','jquery'],
        'React' => ['react','react js','reactjs','next js'],
        'HTML e CSS' => ['html','css','html5','css3','bootstrap','tailwind'],
        'Banco de dados SQL' => ['sql','mysql','mariadb','postgresql','postgres','sql server','banco de dados','oracle','phpmyadmin'],
        'Python' => ['python','django','flask','pandas'],
        'Java' => ['java','spring','spring boot'],
        'Git' => ['git','github','gitlab','versionamento'],
        'Desenvolvimento de software' => ['desenvolvimento web','desenvolvedor','desenvolvedora','programador','programadora','programacao','sistemas web','desenvolvimento de sistemas','software','back end','front end','full stack','api','apis'],
        'Suporte técnico de TI' => ['suporte tecnico','help desk','helpdesk','manutencao de computadores','redes de computadores','infraestrutura de ti','tecnico de informatica','montagem de computadores'],

        // Saúde
        'Enfermagem' => ['enfermagem','tecnico de enfermagem','tecnica de enfermagem','auxiliar de enfermagem','enfermeiro','enfermeira','coren'],
        'Saúde e cuidados' => ['farmacia','drogaria','nutricionista','nutricao','saude','cuidador','cuidadora','cuidador de idosos','primeiros socorros','hospitalar','area da saude','atendimento hospitalar'],

        // Obras e manutenção
        'Elétrica' => ['eletricista','eletrica','eletrica predial','instalacoes eletricas','nr10','nr 10','eletrotecnica'],
        'Hidráulica' => ['hidraulica','hidraulico','encanador','bombeiro hidraulico','instalacoes hidraulicas'],
        // "construção" sozinha não entra: "a construção de um ambiente de trabalho positivo" não é obra.
        'Construção civil' => ['obra','obras','construcao civil','pedreiro','servente de obra','canteiro de obras','construtora','ajudante de construcao','auxiliar de construcao'],

        // Serviços
        'Serviços domésticos e limpeza' => ['diaristas','zelador','zeladora','copeira','copeiro','camareiro','camareira','auxiliar de limpeza','agente de limpeza','higienizacao','domestica','empregada domestica','diarista','limpeza','faxina','servicos gerais','rotina domestica','passar roupa','zeladoria'],
        'Cozinha e alimentação' => ['garcom','garconete','churrasqueiro','pizzaiolo','cumim','confeiteiro','confeiteira','padeiro','salgadeiro','auxiliar de pizzaria','self service','buffet','cozinha industrial','cozinha','cozinheiro','cozinheira','auxiliar de cozinha','alimentacao','lanchonete','fast food','manipulacao de alimentos','restaurante','chapeiro','mcdonald s','mcdonalds'],
        'Motorista e CNH' => ['caminhao','cnh c','cnh categoria c','cnh a','cnh categoria a','motorista','cnh','carteira de habilitacao','habilitacao','cnh b','cnh d','cnh ab'],
        'Educação' => ['professor','professora','docente','pedagogia','pedagogo','pedagoga','monitor escolar','auxiliar de classe'],

        // Comportamentais
        'Comunicação' => ['comunicacao','comunicativo','comunicativa','boa comunicacao','oratoria'],
        'Trabalho em equipe' => ['trabalho em equipe','trabalhar em equipe','espirito de equipe','colaborativo','colaborativa'],
        'Organização' => ['organizacao','organizado','organizada','organizar'],
        'Proatividade' => ['proatividade','proativo','proativa','iniciativa'],
        'Foco em resultados' => ['foco em resultados','metas','orientado a resultados','orientada a resultados'],

        // Idiomas
        'Inglês' => ['ingles','english'],
        'Espanhol' => ['espanhol','spanish'],
    ];

    /** Competências comportamentais (usadas para o campo "competências" do perfil). */
    public const COMPORTAMENTAIS = ['Comunicação','Trabalho em equipe','Organização','Proatividade','Foco em resultados','Negociação'];

    /** Expressões que geram falso positivo e devem ser ignoradas antes da busca. */
    private const RUIDO = ['dados pessoais','plano de saude','seguro saude','vale alimentacao','vale refeicao','auxilio alimentacao','cesta basica','day off','experiencia do usuario final',
        // descrições de cursos e e-books: "proteção de dados" não é análise de dados; "cobrança por consumo" e
        // "retenção de profissionais" não são telemarketing (que continua valendo para "operador de cobrança" e "retenção").
        'protecao de dados','protecao dos dados','vazamento de dados','cobranca por consumo','cobranca por uso','modelo de cobranca','modelos de cobranca',
        'retencao de profissionais','retencao de talentos','retencao de pessoas','retencao de colaboradores','retencao de funcionarios'];

    /** Minúsculas, sem acento, apenas letras/números separados por um espaço. */
    public static function normalizar(string $s): string {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n','’'=>' ',"'"=>' ',
        ]);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;
        return trim($s);
    }

    /**
     * Retorna as competências canônicas encontradas no texto, na ordem do dicionário.
     * @return string[]
     */
    public static function extrair(string $texto): array {
        $n = ' '.self::normalizar($texto).' ';
        foreach (self::RUIDO as $r) $n = str_replace(' '.$r.' ', ' ', $n);
        if (trim($n) === '') return [];
        $achadas = [];
        foreach (self::DICIONARIO as $nome => $sinonimos) {
            foreach ($sinonimos as $sin) {
                if (str_contains($n, ' '.$sin.' ')) { $achadas[] = $nome; break; }
            }
        }
        return $achadas;
    }

    /** Competências exigidas por uma vaga (título, descrição e requisitos — benefícios não contam). */
    public static function daVaga(array $v): array {
        return self::extrair(implode("\n", [$v['titulo'] ?? '', $v['descricao'] ?? '', $v['requisitos'] ?? '']));
    }

    /**
     * Competências ensinadas por um curso: as do título e da descrição. A categoria só entra quando os dois não
     * dizem nada — o nome da área junta vários temas ("Marketing, Dados e UX") e daria a todo curso dela
     * competências que ele não ensina (ex.: "UX e design" para um curso de Power BI).
     */
    public static function doCurso(array $c): array {
        return self::extrair(implode("\n", [$c['titulo'] ?? '', $c['descricao'] ?? ''])) ?: self::extrair((string)($c['categoria_nome'] ?? ''));
    }

    /**
     * Competências do candidato: todos os campos do perfil (inclusive os herdados da extração do
     * currículo — cursos complementares, informações adicionais, CNH, disponibilidade) + texto do currículo mais recente.
     */
    public static function doPerfil(array $p, string $textoCurriculo = ''): array {
        return self::extrair(implode("\n", [
            $p['titulo_profissional'] ?? '', $p['bio'] ?? '', $p['objetivo'] ?? '', $p['habilidades'] ?? '',
            $p['competencias'] ?? '', $p['experiencias'] ?? '', $p['formacao'] ?? '', $p['idiomas'] ?? '',
            $p['cursos_complementares'] ?? '', $p['informacoes_adicionais'] ?? '', $p['disponibilidade'] ?? '',
            !empty($p['cnh']) ? 'CNH '.$p['cnh'] : '', $textoCurriculo,
        ]));
    }

    /**
     * Cursos que ensinam alguma das competências faltantes, ordenados pela quantidade coberta.
     * @return array<int,array> cada curso recebe a chave 'cobre' com as competências cobertas.
     */
    public static function cursosPara(array $faltantes, array $cursos, int $limite = 3): array {
        if (!$faltantes) return [];
        $out = [];
        foreach ($cursos as $c) {
            $cobre = array_values(array_intersect(self::doCurso($c), $faltantes));
            if ($cobre) { $c['cobre'] = $cobre; $out[] = $c; }
        }
        usort($out, fn($a, $b) => count($b['cobre']) <=> count($a['cobre']));
        return array_slice($out, 0, $limite);
    }

    /**
     * Cursos que ensinam competências pedidas pela vaga (página da vaga, quando o
     * candidato não tem recomendação própria). Cada curso recebe 'em_comum'.
     */
    public static function cursosParaVaga(array $competenciasVaga, array $cursos, int $limite = 3): array {
        if (!$competenciasVaga) return [];
        $out = [];
        foreach ($cursos as $c) {
            $comum = array_values(array_intersect(self::doCurso($c), $competenciasVaga));
            if ($comum) { $c['em_comum'] = $comum; $out[] = $c; }
        }
        usort($out, fn($a, $b) => count($b['em_comum']) <=> count($a['em_comum']));
        return array_slice($out, 0, $limite);
    }

    /**
     * Vagas ativas que pedem competências ensinadas pelo curso ("Vagas que pedem isso").
     * Cada vaga recebe 'em_comum' (competências compartilhadas); ordena pela quantidade.
     */
    public static function vagasParaCurso(array $competenciasCurso, array $vagas, int $limite = 6): array {
        if (!$competenciasCurso) return [];
        $out = [];
        foreach ($vagas as $v) {
            $comum = array_values(array_intersect(self::daVaga($v), $competenciasCurso));
            if ($comum) { $v['em_comum'] = $comum; $out[] = $v; }
        }
        usort($out, fn($a, $b) => count($b['em_comum']) <=> count($a['em_comum']));
        return array_slice($out, 0, $limite);
    }
}
