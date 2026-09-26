# Conecta Vagas DF

Plataforma de vagas de emprego e capacitação profissional do Distrito Federal —
Trabalho de Conclusão de Curso (TCC).

**PHP 8.2 puro (padrão MVC)** · **MySQL/MariaDB (PDO)** · **XAMPP** · sem frameworks e sem dependências externas.

## O que o sistema faz

| Perfil | Recursos |
|---|---|
| Visitante | Vagas e cursos/e-books com busca e filtros; página de cada vaga e curso; planos. |
| Candidato | Envia o currículo (PDF/DOCX/DOC) e a **máquina de extração** preenche o perfil; com o cadastro completo, ganha um **portfólio** automático e a **máquina de match** (nota de 0 a 100, explicada, com cada vaga); candidata-se e acompanha o retorno das empresas. |
| Empresa | Publica vagas (cola o anúncio e a **extração de vagas** preenche o formulário), recebe candidaturas ordenadas pelo match e consulta o banco de talentos. |
| Administrador | Gerencia usuários, categorias, cursos/e-books (com **extração de cursos**), vagas e candidaturas, e acompanha a **máquina de aprendizado**. |

Planos demonstrativos (sem cobrança real): **Candidato VIP** e **Empresa Premium**.

## Instalação (XAMPP no Windows)

1. Copie a pasta do projeto para `C:\xampp\htdocs\` (qualquer nome, inclusive com espaços).
2. No **XAMPP Control Panel**, inicie **Apache** e **MySQL**.
   O `mod_rewrite` do Apache (já ativo no XAMPP) é necessário.
3. Importe o banco — primeiro a estrutura, depois os dados de demonstração:
   - phpMyAdmin → Importar → `database/schema.sql` e depois `database/seed.sql`; **ou**
   - no terminal, dentro da pasta do projeto:
     ```
     C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\schema.sql
     C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 < database\seed.sql
     ```
   O `schema.sql` apaga e recria **só** o banco `conecta_vagas_df_v2`.
4. Se o MySQL tiver senha, ajuste `DB_PASS` em `config/config.php`.
5. Acesse `http://localhost/<pasta do projeto>/` — ex.: `http://localhost/TCC%20v2/TCC_GUSTAVO/`.
6. Confira se está tudo certo: `C:\xampp\php\php.exe tests\smoke.php`

### Contas de teste

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | admin@conectavagas.com | Admin@123 |
| Empresa | empresa@conectavagas.com | Empresa@123 |
| Candidato | candidato@conectavagas.com | Candidato@123 |

Troque as senhas antes de publicar o sistema.

## Estrutura de pastas

```
TCC_GUSTAVO/
├── .htaccess              manda todas as requisições para public/ (o endereço no navegador não muda)
├── index.php              reserva: sem mod_rewrite, redireciona para public/
├── README.md              este arquivo
├── app/                   CÓDIGO DA APLICAÇÃO (inacessível pelo navegador)
│   ├── Controllers/       recebem a requisição, conferem permissões e escolhem a tela (C do MVC)
│   ├── Core/              núcleo: inicialização, rotas, sessão, login, CSRF, uploads, banco, telas
│   ├── DTO/               objetos que levam os dados do formulário até o banco
│   ├── Models/            acesso ao banco, uma classe por tabela (M do MVC)
│   ├── Services/          regras de negócio: match, competências, portfólio e extração
│   │   ├── Extracao/      leitura de PDF/DOCX/DOC e extração de currículo, vaga e curso
│   │   └── Aprendizado/   aprendizado de máquina das extrações (Naive Bayes que aprende com as revisões)
│   └── Views/             telas em HTML + PHP (V do MVC): layouts, partes reutilizáveis e páginas
├── config/config.php      configurações (banco, depuração, limites, pastas)
├── database/
│   ├── schema.sql         estrutura do banco (tabelas, chaves, índices)
│   └── seed.sql           dados de demonstração (contas, vagas, cursos)
├── docs/ARQUITETURA.md    como o sistema funciona por dentro (leia para a apresentação)
├── docs/APRENDIZADO.md    a máquina de aprendizado: ideia, algoritmo, arquivos e roteiro de demonstração
├── docs/PESQUISA_CURSOS.md  pesquisa guiada: de onde vêm os links dos novos cursos e e-books
├── public/                ÚNICA pasta servida pelo Apache
│   ├── index.php          front controller: porta de entrada de todas as páginas + tabela de rotas
│   └── assets/            CSS, JavaScript e imagens (carrossel, cartazes das vagas, capas dos cursos)
├── storage/               arquivos gerados pelo sistema (inacessível pelo navegador)
│   ├── uploads/           currículos, fotos, logos e cartazes enviados
│   └── logs/              registros internos (ex.: links de redefinição de senha)
└── tests/smoke.php        teste rápido: classes, regras, banco e páginas
```

## Como uma página é montada

Exemplo: o navegador pede `/vagas.php`.

1. O `.htaccess` da raiz desvia o pedido para `public/index.php` (o **front controller**).
2. `app/Core/bootstrap.php` carrega as configurações, o núcleo e o carregamento automático das classes.
3. A sessão é aberta e a conta logada é conferida no banco.
4. O **roteador** (`app/Core/Router.php`) procura `vagas.php` na tabela de rotas e chama `VagaController::lista()`.
5. O controller lê os filtros, busca as vagas no **model** `VagaDAO` e o match no `MatchDAO`.
6. A **view** `app/Views/vagas/lista.php` é exibida entre o cabeçalho e o rodapé do layout.

Os endereços são os mesmos das versões anteriores (`vaga.php?id=3`, `view/perfil/index.php`,
`admin/pages/vagas.php`...): links e favoritos antigos continuam funcionando.

Detalhes — camadas, tabela completa de rotas, máquinas de extração e de match, regras dos planos,
segurança e o mapa "onde estava → onde está": **[docs/ARQUITETURA.md](docs/ARQUITETURA.md)**.

## Depuração e produção

- Sem a variável de ambiente `APP_DEBUG`, o `DEBUG` fica ligado só no terminal e para quem acessa pelo
  próprio computador (`127.0.0.1`/`::1`): erros mostram o detalhe técnico. Acessos de outras máquinas
  veem só mensagens amigáveis; o detalhe vai para o log do Apache/PHP.
- `APP_DEBUG=0` (ou `1`) sempre prevalece — use `APP_DEBUG=0` ao publicar, principalmente atrás de um proxy
  no mesmo servidor (todo acesso chegaria como `127.0.0.1`).
