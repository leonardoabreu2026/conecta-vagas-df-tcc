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
5. Acesse `http://localhost/<pasta do projeto>/` — nesta máquina: **`http://localhost/conecta%20vagas%20df%20tcc/`**
   (espaços no nome da pasta viram `%20`).
6. Confira se está tudo certo: `C:\xampp\php\php.exe tests\smoke.php`

### Contas de teste

| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | admin@conectavagas.com | Admin@123 |
| Empresa | empresa@conectavagas.com | Empresa@123 |
| Candidato | candidato@conectavagas.com | Candidato@123 |

Troque as senhas antes de publicar o sistema.

### Não consegue entrar?

- **Confira o que foi digitado** com o botão do olho, ao lado do campo de senha. O login tolera os erros mais
  comuns: primeira letra trocada (`admin@123` entra como `Admin@123`), Caps Lock ligado (`aDMIN@123`) e
  espaços no começo ou no fim.
- **No ambiente local** (acesso pelo próprio computador) a mensagem diz o motivo exato: e-mail sem conta,
  senha incorreta ou conta desativada. Acessando de outra máquina ou com `APP_DEBUG=0`, a mensagem é única
  (não revela quais e-mails têm conta).
- **Login em pausa**: depois de **8 senhas erradas** para o mesmo e-mail (a tela avisa quando faltam 3), o
  login daquele e-mail pausa por **5 minutos** e libera sozinho. Os limites ficam em `config/config.php`
  (`LOGIN_MAX_TENTATIVAS`, `LOGIN_JANELA_MINUTOS`).
- **"Sua sessão foi encerrada porque a senha da conta foi alterada"**: a senha daquela conta mudou (pelo
  "Esqueci minha senha", pelo administrador ou pelo comando abaixo). Basta entrar de novo com a senha nova.
- **Reconectar as senhas de teste** (volta as 3 contas às senhas da tabela acima, reativa as contas e tira
  qualquer pausa do login):
  ```
  C:\xampp\php\php.exe database\resetar_senhas.php
  ```
- **"Esqueci minha senha"**: no modo de demonstração (sem e-mail configurado), o link de redefinição fica em
  `storage/logs/redefinicoes_senha.log`.

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
│   ├── seed.sql           dados de demonstração (contas, vagas, cursos)
│   └── resetar_senhas.php volta as senhas das contas de teste e libera o login (só pelo terminal)
├── docs/ARQUITETURA.md    como o sistema funciona por dentro (leia para a apresentação)
├── docs/APRENDIZADO.md    a máquina de aprendizado: ideia, algoritmo, arquivos e roteiro de demonstração
├── docs/PESQUISA_CURSOS.md  pesquisa guiada: de onde vêm os links dos novos cursos e e-books
├── docs/PROMPTS_PESQUISA.md prompt padrão para as IAs de pesquisa (gerado por docs/gerar_prompts.php)
├── .githooks/pre-commit   blindagem: antes de cada commit confere a sintaxe e roda o teste rápido
├── public/                ÚNICA pasta servida pelo Apache
│   ├── index.php          front controller: porta de entrada de todas as páginas + tabela de rotas
│   └── assets/            CSS, JavaScript e imagens (carrossel, cartazes das vagas, capas dos cursos)
├── storage/               arquivos gerados pelo sistema (inacessível pelo navegador)
│   ├── uploads/           currículos, fotos, logos e cartazes enviados
│   ├── logs/              registros internos (ex.: links de redefinição de senha)
│   └── backups/           cópias do banco e dos uploads (fora do git), com COMO_RESTAURAR.txt
└── tests/
    ├── smoke.php          teste rápido: classes, regras, banco e páginas
    ├── lint.php           confere a sintaxe de todos os arquivos PHP
    └── verificar.bat      clique duplo: sintaxe + teste rápido, com o resultado na tela
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

## Cadastrar cursos e e-books (com ajuda de outra IA)

Em **Painel → Cursos e e-books** há uma caixa só, **Extrair**:

1. peça a uma IA de pesquisa (Perplexity, ChatGPT, Gemini…) que pesquise os links ou títulos e responda no
   **modelo de ficha** (Título, Tipo, Instituição, Modalidade, Cidade, Nível, Carga horária, Gratuito, Preço, Área,
   Link, Imagem, Descrição). O prompt padrão está em **[docs/PROMPTS_PESQUISA.md](docs/PROMPTS_PESQUISA.md)**;
2. cole a resposta em **Extrair**: **uma ficha** (ou um texto de divulgação) preenche o formulário para revisar e
   salvar; **várias fichas** (separadas por `---`) abrem uma prévia para cadastrar de uma vez;
3. **com imagem** na ficha, ela é conferida e baixada; **sem imagem**, o conteúdo entra com a **imagem padrão** da
   plataforma e a lista mostra *trocar imagem* (filtro "Com imagem padrão") — edite quando tiver a imagem certa;
4. **Baixar × Acessar**: envie o PDF do e-book no cadastro e ele fica na **biblioteca** da plataforma (botão
   **Baixar**, baixa direto); conteúdo que fica em outro site mostra **Acessar** (abre em nova aba).

Fontes oficiais para pesquisar: [docs/PESQUISA_CURSOS.md](docs/PESQUISA_CURSOS.md).

## Manutenção automática (ninguém precisa calibrar nada)

Ao abrir a **visão geral** do painel, no máximo uma vez por dia, o sistema sozinho:
- faz a **máquina de aprendizado estudar** o que foi cadastrado e revisado (vagas, cursos e perfis públicos),
  recalibrar a confiança e conferir o desempenho **recente**: se ela começar a errar, volta a valer a regra até ela
  provar de novo. Correção contraditória também se resolve sozinha (vale a mais recente). Ela não aparece no menu;
- **limpa arquivos órfãos** de `storage/uploads` (sem registro que os use e com mais de 24 h).

**Foto do currículo**: ao enviar o currículo (PDF ou DOCX), a foto é encontrada pelo padrão de foto de currículo
(tons de pele, fotografia, proporção de retrato — ignora logotipos, ícones e página escaneada) e vira a foto do
perfil; se o perfil já tiver foto, a do currículo aparece no relatório para o candidato escolher trocar.

## Blindagem do código (antes e depois de mexer)

- **Clique duplo em `tests\verificar.bat`**: confere a sintaxe de todos os PHP e roda o teste rápido. No fim
  aparece **TUDO CERTO** ou o que quebrou (com arquivo e linha). Faça isso depois de cada alteração.
- **Commit protegido**: o gancho `.githooks/pre-commit` roda a mesma verificação antes de cada commit (pelo
  terminal ou pelo VS Code). Se algo quebrou, o commit é **barrado** e o motivo aparece; o relatório completo
  fica em `.git/smoke_ultimo.txt`. Precisa do Apache e do MySQL ligados. Emergência: `git commit --no-verify`.
- O gancho já está ligado nesta máquina. Em uma cópia nova do projeto, ligue com:
  ```
  git config core.hooksPath .githooks
  ```
- Quebrou e não sabe onde? Volte ao último ponto estável: `git checkout teste-cliente-2026-09-26`
  (o banco volta pelo backup, ver "Backup e restauração").

## Segurança (resumo)

- Senhas com `password_hash` (bcrypt); pausa automática do login contra força-bruta (ver acima).
- Token CSRF em todo formulário; SQL sempre com parâmetros (`?`); todo texto na tela passa por `e()`.
- Cada ação confere a permissão: candidato só mexe no que é dele, empresa só nas próprias vagas e
  candidaturas, e o currículo só abre para o dono, para a empresa que o recebeu ou para empresa Premium.
- Só `public/` é servida e, dentro dela, só o `index.php` executa PHP. Configuração, banco, backups,
  documentos, `.git` e arquivos ocultos respondem 403.
- Cabeçalhos: `Content-Security-Policy` (formulários só para o próprio site), `X-Frame-Options`,
  `X-Content-Type-Options`, `Referrer-Policy` e `Permissions-Policy`; a versão do PHP não é anunciada.
- Detalhes em [docs/ARQUITETURA.md](docs/ARQUITETURA.md) (seção de segurança).

## Backup e restauração

- Ponto de restauração do código: tag `teste-cliente-2026-09-26` (`git checkout teste-cliente-2026-09-26`).
- Banco e arquivos enviados: `storage/backups/<data>/` tem o `.sql` do banco, o `uploads.zip` e o passo a
  passo (`COMO_RESTAURAR.txt`). Para voltar o banco, dentro da pasta do backup:
  ```
  C:\xampp\mysql\bin\mysql.exe -u root < banco_conecta_vagas_df_v2.sql
  ```
- Fazer um backup novo:
  ```
  C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --databases conecta_vagas_df_v2 > storage\backups\banco.sql
  ```

## Depuração e produção

- Sem a variável de ambiente `APP_DEBUG`, o `DEBUG` fica ligado só no terminal e para quem acessa pelo
  próprio computador (`127.0.0.1`/`::1`): erros mostram o detalhe técnico. Acessos de outras máquinas
  veem só mensagens amigáveis; o detalhe vai para o log do Apache/PHP.
- `APP_DEBUG=0` (ou `1`) sempre prevalece — use `APP_DEBUG=0` ao publicar, principalmente atrás de um proxy
  no mesmo servidor (todo acesso chegaria como `127.0.0.1`).
