# Arquitetura — Conecta Vagas DF

Como o sistema funciona por dentro. Instalação e visão geral estão no [README](../README.md).

---

## 1. Visão geral

O projeto segue o padrão **MVC** (Model – View – Controller), em PHP puro, com uma camada extra
de **Services** para as regras de negócio e um **Core** com a infraestrutura.

```
Navegador
   │  GET /vagas.php
   ▼
.htaccess (raiz) ── desvia tudo para public/ (arquivo que existe, como CSS, é entregue direto)
   ▼
public/index.php  ── FRONT CONTROLLER (porta de entrada única)
   │  1. app/Core/bootstrap.php: configuração + núcleo + carregamento automático das classes
   │  2. imagens enviadas (assets/uploads/...) → ArquivoController::imagem
   │  3. sessão, conferência da conta logada, cabeçalhos de segurança
   │  4. Router: "vagas.php" → VagaController::lista()
   ▼
Controller ──► Models (DAO) ──► MySQL          (dados)
    │      └─► Services      ──► regras        (match, extração, portfólio)
    ▼
View: layouts/header.php + vagas/lista.php + layouts/footer.php  ──► HTML
```

| Camada | Pasta | Responsabilidade | Não faz |
|---|---|---|---|
| **Controller** | `app/Controllers` | Recebe a requisição, confere permissão e CSRF, lê o formulário, chama models/services, redireciona ou mostra a tela | SQL, HTML |
| **Model** | `app/Models` | SQL com PDO e prepared statements; uma classe (DAO) por tabela | Ler `$_POST`, gerar HTML |
| **Service** | `app/Services` | Regras de negócio: match, dicionário de competências, extração, portfólio | Acessar `$_POST`, gerar HTML |
| **View** | `app/Views` | Exibir os dados prontos (HTML + PHP) | Consultar o banco |
| **Core** | `app/Core` | Infraestrutura usada por todos: rotas, sessão, login, CSRF, uploads, conexão, telas, erros | Regras de negócio |
| **DTO** | `app/DTO` | Levar os dados de um formulário até o DAO | — |

---

## 2. Arquivos, camada por camada

### app/Core — núcleo

| Arquivo | O que faz |
|---|---|
| `bootstrap.php` | Inicializa a aplicação: carrega `config/config.php`, ajusta erros e fuso, carrega o núcleo, registra o autoloader e calcula `BASE_URL`/`SITE_URL`. Usado pela web e pelos testes. |
| `Autoloader.php` | Carrega uma classe quando ela é usada pela primeira vez (`new VagaDAO()` → `app/Models/VagaDAO.php`). Por isso não há `require_once` espalhados. |
| `Router.php` | Tabela de rotas: endereço → `[Controller, ação]`. Descobre o caminho pedido e guarda a rota atual (o menu usa para marcar o item ativo). |
| `Controller.php` | Classe base dos controllers: `view('pasta/tela', get_defined_vars())`. |
| `View.php` | `View::render()` (cabeçalho + tela + rodapé), `pagina_erro()` (403/404/500/503) e o tratador global de exceções. |
| `Database.php` | Conexão PDO única (Singleton) com o MySQL; mensagens de erro em português. |
| `Session.php` | Sessão segura, mensagens "flash", login (`iniciar_sessao_usuario`), logout e revalidação da conta a cada requisição. |
| `Auth.php` | `usuarioLogado()`, `isAdmin()`, `isEmpresa()`, `isCandidato()`, `exigirLogin()`, `exigirAdmin()`, `destinoPainel()`. |
| `Csrf.php` | Token contra CSRF: `csrf_token()`, `csrf_campo()`, `validar_csrf()`. |
| `Upload.php` | Imagens enviadas, caminho real dos uploads (`caminho_upload`), limpeza de arquivos sem uso, imagens das pastas. |
| `helpers.php` | Funções gerais: `e()` (escapa HTML), `url()`, `redirect()`, leitura segura de formulários, conversões e rótulos. |

### app/Controllers — uma classe por área

| Controller | Ações (endereços) |
|---|---|
| `HomeController` | início (`index.php`), termo de privacidade (`contrato.php`) |
| `VagaController` | lista de vagas (`vagas.php`), página da vaga (`vaga.php`) |
| `CursoController` | listas separadas por formato: cursos (`cursos.php`), e-books (`?tipo=ebook`) e vídeos (`?tipo=video`); página do conteúdo (`curso.php`) |
| `CandidaturaController` | candidatar-se (`candidatar.php`), cancelar candidatura |
| `PlanosController` | planos e assinaturas (`planos.php`) |
| `AuthController` | login, cadastro, sair |
| `PasswordController` | esqueci a senha, redefinir senha |
| `PerfilController` | meu perfil, salvar perfil, portfólio, recalcular match |
| `CurriculoController` | envio do currículo (extração), aplicar dados do relatório, excluir currículo |
| `ArquivoController` | imagens enviadas (`assets/uploads/...`), download do currículo (`download.php`) |
| `AdminController` | painel (`admin/index.php`), usuários, categorias, cursos, assinaturas |
| `AprendizadoController` | painel "Aprendizado da máquina" (`admin/pages/aprendizado.php`) |
| `AprendeComRevisao` (*trait*) | usada pelos controllers com extração: guarda a sugestão da máquina e aprende quando o formulário é salvo |
| `EmpresaController` | vagas (com extração), candidaturas recebidas, banco de talentos, perfil da empresa |

### app/Models — acesso ao banco (DAO)

| Model | Tabela(s) |
|---|---|
| `UsuarioDAO` | `usuarios`, `tentativas_login`, `redefinicoes_senha` |
| `PerfilDAO` | `perfis` (candidato e empresa) + banco de talentos |
| `VagaDAO` | `vagas` |
| `CursoDAO` | `cursos` |
| `CategoriaDAO` | `categorias` |
| `CurriculoDAO` | `curriculos` |
| `CandidaturaDAO` | `candidaturas` |
| `MatchDAO` | `matches` |
| `AssinaturaDAO` | `assinaturas` + regras dos planos |
| `AprendizadoDAO` | `aprendizado_exemplos`, `aprendizado_palavras`, `aprendizado_revisoes`, `aprendizado_provas` (máquina de aprendizado) |

### app/Services — regras de negócio

| Service | O que faz |
|---|---|
| `Competencias` | Dicionário único de competências e sinônimos, usado pela extração e pelo match. |
| `MatchService` | Máquina de match candidato × vaga (nota 0–100 explicável). |
| `Portfolio` | Validação do cadastro e organização do portfólio (linha do tempo, formação, links). |
| `Extracao/LeitorDocumento` | Lê o texto de PDF, DOCX e DOC (usa `PdfTexto` e `DocxTexto`). |
| `Extracao/ExtracaoCurriculo` | Separa os dados do currículo (contato, experiências, formação...). |
| `Extracao/AplicacaoCurriculo` | Aplica os dados extraídos no perfil (preenche, mantém ou mescla). |
| `Extracao/ExtracaoVaga` | Texto de um anúncio → campos da vaga. |
| `Extracao/ExtracaoCurso` | Texto de divulgação → campos do curso. |
| `Aprendizado/MaquinaAprendizado` | Aprendizado de máquina das extrações: decisão híbrida regra × modelo, aprender com a revisão. Ver [APRENDIZADO.md](APRENDIZADO.md). |
| `Aprendizado/NaiveBayes` | O classificador (aprender, esquecer, prever e explicar). |
| `Aprendizado/Tokenizador` | Texto → palavras que o classificador conta. |
| `Aprendizado/CorrecaoHumana` | Compara a sugestão da extração com o que a pessoa salvou e tira as lições. |
| `Pix` | Código Pix "copia e cola" (BR Code do Banco Central, com CRC16) do QR Code de doação do rodapé. |

### app/Views — telas

- `layouts/` — `header.php` (menu e mensagem flash), `footer.php`, `admin_nav.php` (abas do painel).
- `partials/` — `icones.php` (ícones SVG), `componentes.php` (cartões, faixas, datas, preços),
  `relatorio_extracao.php` (relatório do currículo).
- `errors/erro.php` — página de erro sem layout (funciona mesmo com o banco fora do ar).
- Uma pasta por área: `home/`, `vagas/`, `cursos/`, `planos/`, `auth/`, `perfil/`, `admin/`.

Cada tela começa com um comentário dizendo **qual rota a exibe e quais variáveis recebe** do controller.

---

## 3. Tabela de rotas

Definida em `public/index.php`. As rotas aceitam GET (mostrar) e POST (enviar formulário).

| Endereço | Controller::ação | Tela | Acesso |
|---|---|---|---|
| `/` ou `index.php` | `HomeController::index` | `home/index` | todos |
| `contrato.php` | `HomeController::contrato` | `home/contrato` | todos |
| `vagas.php` | `VagaController::lista` | `vagas/lista` | todos |
| `vaga.php?id=` | `VagaController::detalhe` | `vagas/detalhe` | todos (fechada: só dona/admin) |
| `cursos.php` | `CursoController::lista` | `cursos/lista` | todos |
| `curso.php?id=` | `CursoController::detalhe` | `cursos/detalhe` | todos (oculto: só admin) |
| `planos.php` | `PlanosController::index` | `planos/index` | todos (assinar: logado) |
| `login.php` | `AuthController::login` | `auth/login` | visitante |
| `cadastro.php` | `AuthController::cadastro` | `auth/cadastro` | visitante |
| `view/usuario/logout.php` | `AuthController::logout` | `auth/logout` | logado |
| `esqueci_senha.php` | `PasswordController::esqueci` | `auth/esqueci_senha` | visitante |
| `redefinir_senha.php?token=` | `PasswordController::redefinir` | `auth/redefinir_senha` | quem tem o link |
| `view/perfil/index.php` | `PerfilController::index` | `perfil/index` | candidato |
| `view/perfil/salvar.php` | `PerfilController::salvar` | — (redireciona) | candidato |
| `view/perfil/portfolio.php[?id=]` | `PerfilController::portfolio` | `perfil/portfolio` | dono; outros se o perfil for público |
| `view/perfil/recalcular_match.php` | `PerfilController::recalcularMatch` | — | candidato |
| `view/perfil/curriculo_upload.php` | `CurriculoController::upload` | — | candidato |
| `view/perfil/aplicar_extracao.php` | `CurriculoController::aplicarExtracao` | — | candidato |
| `view/perfil/curriculo_excluir.php` | `CurriculoController::excluir` | — | candidato |
| `candidatar.php?vaga_id=` | `CandidaturaController::candidatar` | `vagas/candidatar` | candidato |
| `view/perfil/candidatura_cancelar.php` | `CandidaturaController::cancelar` | — | candidato |
| `download.php?id=` | `ArquivoController::download` | — (arquivo) | dono, admin, empresa autorizada |
| `assets/uploads/<arquivo>` | `ArquivoController::imagem` | — (imagem) | todos (só imagens) |
| `admin/index.php` | `AdminController::painel` | `admin/painel` | admin e empresa |
| `admin/pages/usuarios.php` | `AdminController::usuarios` | `admin/usuarios` | admin |
| `admin/pages/categorias.php` | `AdminController::categorias` | `admin/categorias` | admin |
| `admin/pages/cursos.php` | `AdminController::cursos` | `admin/cursos` | admin |
| `admin/pages/assinaturas.php` | `AdminController::assinaturas` | `admin/assinaturas` | admin |
| `admin/pages/aprendizado.php` | `AprendizadoController::painel` | `admin/aprendizado` | admin |
| `admin/pages/vagas.php` | `EmpresaController::vagas` | `admin/vagas` | empresa (suas vagas) e admin |
| `admin/pages/candidaturas.php` | `EmpresaController::candidaturas` | `admin/candidaturas` | empresa e admin |
| `admin/pages/talentos.php` | `EmpresaController::talentos` | `admin/talentos` | empresa e admin |
| `admin/pages/empresa_perfil.php` | `EmpresaController::perfil` | `admin/empresa_perfil` | empresa |

> Os endereços foram mantidos iguais aos das versões anteriores para não quebrar links. Hoje eles
> são só **nomes de rota**: não existem mais arquivos `vagas.php` ou `view/perfil/index.php` no disco.

---

## 4. Fluxo principal (para a apresentação)

1. **Meu perfil** (`PerfilController::index`) é o cadastro: máquina de extração + formulário.
2. O candidato envia o currículo → `LeitorDocumento` lê o arquivo → `ExtracaoCurriculo` separa os dados.
3. `AplicacaoCurriculo` preenche o cadastro → o **relatório da extração** mostra o que foi encontrado, aplicado, mantido e o que faltou.
4. **Validação** (`Portfolio::validarCadastro`): com telefone, título profissional, cidade, resumo, objetivo,
   formação e habilidades preenchidos, o cadastro é validado e a aba **Portfólio** aparece no topo.
5. **Portfólio** (`PerfilController::portfolio`): montado com o cadastro + **máquina de match**
   (`MatchService` compara o candidato com cada vaga usando o dicionário de `Competencias`).
6. O candidato vê a nota e os cursos que cobrem o que falta; a empresa vê o match em cada candidatura.

A empresa faz um caminho parecido para vagas: cola o anúncio → `ExtracaoVaga` preenche → publica →
o match é recalculado com todos os candidatos.

Em todas as extrações (vaga, curso e currículo), a revisão salva ensina a **máquina de aprendizado**: as correções
feitas na revisão viram lições que a extração passa a usar. Detalhes e roteiro de demonstração: [APRENDIZADO.md](APRENDIZADO.md).

---

## 5. Máquina de extração

**Currículo** (`CurriculoController::upload`):
- **PDF**: leitor próprio em PHP (`PdfTexto`): fontes com ToUnicode/CMap, object streams, posição de cada
  trecho e detecção de duas colunas (modelos do Canva/Word). Sem mapa de caracteres, usa o `pdftotext`, se instalado.
- **DOCX**: `ZipArchive` ou leitor ZIP interno (quando a extensão zip está desligada); o XML é lido com DOM (`DocxTexto`).
- **DOC** antigo: `antiword`, se instalado; senão, leitura aproximada do binário.
- Seções reconhecidas pelo título (com variações): resumo, objetivo, experiências, formação, cursos, habilidades,
  competências, idiomas e informações adicionais (PCD, CNH...).
- Também extrai nome, e-mail, telefone, data de nascimento, cidade/UF (regiões do DF e entorno), links,
  CNH, pretensão salarial, disponibilidade, foto embutida e o nível (pelo tempo somado das experiências).
- Aplicação no perfil: campo vazio **recebe** o valor; campo preenchido é **mantido** (a não ser que o candidato
  marque "Substituir"); listas são **mescladas**; o nome da conta só muda se o candidato confirmar no relatório.

**Vagas** (painel → Vagas): envia-se o **cartaz** (imagem, lido por OCR com o Tesseract) ou cola-se o anúncio
(WhatsApp, Instagram, site) e o sistema preenche título, empresa anunciante, salário (ignora VR/VT), cidade, tipo,
nível, modelo, descrição, requisitos, benefícios, contato, quantidade de vagas e área.
- A leitura começa ao escolher o arquivo (prévia do cartaz na tela); o cartaz vira a imagem da vaga.
- **Relatório da extração**, campo a campo, como o do currículo: *lido do anúncio*, *valor padrão* (o anúncio não diz —
  ex.: nível "Júnior") ou *não encontrado*, com os avisos do que conferir.
- O texto lido no cartaz fica numa caixa editável: corrige-se o que o OCR leu errado e extrai-se de novo, mantendo o cartaz.
- A descrição ganha uma frase de abertura montada com o que foi lido ("Grupo Dourado contrata Auxiliar de Cozinha em Águas Claras.").
- Seções curtas ("Horário:", "Local:") não engolem as linhas seguintes; códigos de vaga "(cód. 1308)", prefixos
  "Temporário -" e frases "está contratando X" são tratados no título.
- Para ler imagens: Tesseract instalado (com o idioma português) e a extensão `gd` ligada no `php.ini`.

**Cursos** (painel → Cursos e e-books): cola-se a divulgação e o sistema preenche título, instituição, link,
carga horária, gratuito/preço, modalidade, nível, formato e categoria.

**Importação em lote de cursos e e-books** (painel → Cursos e e-books → "Importar vários"):
1. o painel monta um **prompt de pesquisa guiada** (`FontesCursos::prompt` — formato, área ou lacunas, fontes oficiais
   e os links já cadastrados; roteiro completo em **[PESQUISA_CURSOS.md](PESQUISA_CURSOS.md)**)
   para colar numa IA de pesquisa (Perplexity, ChatGPT); ela responde em **fichas** (Título, Tipo, Instituição,
   Modalidade, Cidade, Nível, Carga horária, Gratuito, Preço, Área, Link, Descrição), separadas por `---`;
2. cola-se a resposta inteira: `ExtracaoCurso::fichas()` limpa o Markdown, separa as fichas e passa cada uma pela
   extração normal (os campos rotulados têm prioridade); presencial guarda a cidade na descrição;
3. a **prévia** mostra cada ficha como "Pronto", "Sem link válido" ou "Já cadastrado"; só as marcadas são gravadas,
   publicadas e com o banner da instituição (`ExtracaoCurso::capa()` — as capas de `assets/img/cursos` levam a marca
   da instituição, então nunca são escolhidas pela área; sem banner, o cartão mostra o ícone do formato).

**Cursos, e-books e vídeos separados**: cada formato tem a sua página — `cursos.php` (só cursos), `cursos.php?tipo=ebook`
(só e-books) e `cursos.php?tipo=video` (só vídeos) —, o seu item no menu, a sua seção na página inicial e, na página
do conteúdo, "Outros" do mesmo formato (`pt_secao_formato()` em `partials/componentes.php`).

Nada é gravado sem revisão: a extração de vagas e cursos só preenche o formulário (ou a prévia da importação).

**Tabelas do painel (CRUD)**: usuários, categorias, cursos/e-books/vídeos, assinaturas e vagas têm colunas ordenáveis
(`painel_th()`: clicar ordena, clicar de novo inverte), filtros e paginação (`painel_paginacao()`); candidaturas e
banco de talentos têm "Ordem:" no filtro. A lógica fica em `app/Core/helpers.php` (`lista_ordem()`, `ordenar_linhas()`,
`paginar()`, `painel_qs()`) — as ações (salvar, publicar, excluir) voltam para a mesma aba, filtros, ordem e página.
Abrir "Editar"/"Ver" de um registro que não existe mais avisa e volta para a lista (`registro_encontrado()`).

**Assinaturas** (painel → Assinaturas, só administrador): concede o plano da conta (candidato → Candidato VIP,
empresa → Empresa Premium) por N dias, edita valor/datas/situação, cancela (mantém o histórico) e exclui. Cada conta
tem no máximo uma assinatura ativa; se a empresa perde o Premium, o destaque das vagas sai. Os preços ficam em
`AssinaturaDAO::PRECOS` (os mesmos de `planos.php`). A ficha do usuário liga para as assinaturas e as vagas da conta.

**Vitrine rotativa da página inicial**: vagas, cursos e e-books mostram 5 cartões e trocam um por vez com os
demais da fila (`[data-rotativo]` em `app.js`), cada seção num ritmo (4,5 s, 5,2 s e 5,9 s) para não trocarem juntas;
pausa com o mouse/foco em cima, no botão "Pausar" ou para quem prefere menos movimento.

**Aprendizado de máquina**: as regras acima são a base. Linhas soltas do anúncio, área da vaga/curso, empresa ou
instituição não reconhecida e linhas do currículo sem título de seção também passam pela `MaquinaAprendizado`
(Naive Bayes e memória de nomes), que aprende com cada revisão salva e só decide quando tem lições e confiança
suficientes e já passou no "período de experiência" (acertou 90% das provas feitas com lições que ainda não
conhecia); senão vale a regra. Nas vagas e nos cursos, o relatório da extração mostra o que a máquina decidiu e
por quê. Detalhes em [APRENDIZADO.md](APRENDIZADO.md).

### CRUD do painel

| Tela | Criar | Ver | Editar | Ativar / desativar | Excluir | Filtros da lista |
|---|---|---|---|---|---|---|
| Vagas | formulário + extração | página pública da vaga | `?edit=` | Ativar · Pausar · Encerrar (reativar respeita o limite do plano) | sim | situação e busca |
| Cursos e e-books | formulário + extração | página pública do curso | `?edit=` | Publicar · Ocultar | sim | formato e busca |
| Usuários | formulário | ficha da conta (`?ver=`) | `?edit=` | Ativar · Bloquear (nunca a própria conta nem o último admin) | sim | tipo e busca |
| Categorias | formulário | vagas/cursos da categoria | `?edit=` | Ativar · Desativar | sim | — |
| Candidaturas | (pelo candidato) | portfólio e currículo | status + retorno | — | admin | vaga e status |

Toda ação que muda dados é um formulário POST com token CSRF (`painel_acao()`), confere a permissão no servidor e
volta para a mesma lista filtrada (`volta_filtros()`).

---

## 6. Máquina de match (0 a 100, explicável)

| Critério | Pontos | Como |
|---|---|---|
| Competências | 50 | competências que a vaga exige × competências do candidato (perfil + texto do currículo) |
| Cargo | 20 | título da vaga × título/objetivo do candidato (peso cheio) ou histórico (60%) |
| Localização | 15 | mesma cidade ou vaga remota = 15; mesma UF = 9; disponível para mudança = 6 |
| Nível | 15 | igual = 15; acima do pedido = 12/8; abaixo = 7/0 |

- Faixas: **excelente** ≥ 75, **alto** ≥ 55, **médio** ≥ 35, **baixo** abaixo disso.
- O detalhamento fica salvo em `matches.detalhes` (JSON) e aparece para o candidato ("Por que essa nota?")
  e para a empresa (competências atendidas e faltantes em cada candidatura).
- Observações que explicam mas não mudam a nota: CNH pedida pela vaga, vaga PCD, viagens.
- Cursos que cobrem as competências faltantes são recomendados no portfólio, na vaga e em Cursos.
- Recalcula ao enviar/excluir currículo, salvar o perfil, criar/editar vaga, ou pelo botão "Recalcular match".

---

## 7. Regras dos planos (conferidas no servidor, em `AssinaturaDAO`)

- **Candidato gratuito**: até 3 candidaturas ativas (enviada/em análise/entrevista). **VIP**: ilimitado,
  aparece primeiro para as empresas e vê 100% das vagas compatíveis (o gratuito vê as 3 melhores).
  Cancelar uma candidatura libera a vaga no limite; candidatura cancelada pode ser reenviada.
- **Empresa básica**: até 2 vagas abertas — o limite vale ao publicar, ao reativar uma vaga pausada/encerrada
  e ao renovar uma vaga expirada. **Premium**: ilimitado, destaque das vagas e banco de talentos completo.
- Ao cancelar ou expirar o Premium, o destaque das vagas é removido; as vagas abertas continuam abertas.
- Assinaturas demonstrativas: sem cobrança real.

---

## 8. Segurança

**Banco e dados**
- PDO com prepared statements em todas as consultas (nenhum valor do usuário é concatenado no SQL).
- Senhas com `password_hash`/`password_verify` (bcrypt), rehash automático e limite de 72 caracteres.
- Chaves estrangeiras com cascata: excluir um usuário remove perfil, currículos, vagas, candidaturas,
  matches, assinaturas e pedidos de redefinição; os arquivos enviados também são apagados.
- Cadastro de usuário + perfil em transação; e-mail único (também pela chave UNIQUE).

**Sessão e login**
- Cookie próprio (`CVDF_SESSAO`), restrito à pasta do projeto, HttpOnly, SameSite=Lax (Secure com HTTPS),
  modo estrito (não aceita ID de sessão inventado).
- Novo ID de sessão e novo token CSRF a cada login/cadastro (evita fixação de sessão).
- A cada requisição a conta é conferida no banco: se o administrador desativar/excluir o usuário ou mudar o
  tipo dele, a sessão aberta perde o acesso na hora.
- Limite de tentativas de login (tabela `tentativas_login`): 8 senhas erradas para o mesmo e-mail a partir do
  mesmo IP (20 somando todos os IPs, ou 60 de um mesmo IP) em 5 minutos pausam o login por 5 minutos; a tela
  avisa quando faltam 3. Mensagem única e mesmo tempo de resposta, para não revelar quais e-mails existem.
  Tolerância a erros comuns (`UsuarioDAO::variantesSenha`): espaços nas pontas, primeira letra na caixa
  trocada e Caps Lock ligado — no máximo 4 conferências, também feitas para e-mail inexistente (mesmo tempo
  de resposta). No ambiente local (DEBUG) a tela diz o motivo exato (sem conta, senha incorreta, conta
  desativada); em produção a mensagem continua única. Ajustes em `config/config.php`.
- O hash da senha só é refeito se o algoritmo mudar (nunca por custo do bcrypt): refazer muda o hash, e a
  sessão entende hash novo como "senha alterada", o que derrubaria as outras sessões abertas da conta.
- `database/resetar_senhas.php` (só terminal) volta as contas de teste às senhas do README e libera o login.
- Sair: aceita POST com token, link com token (`logout_url()`) ou o clique no menu do próprio site
  (cabeçalho `Sec-Fetch-Site`). Um link vindo de outro site mostra uma confirmação.

**Formulários e autorização**
- Token CSRF em todos os formulários POST; token inválido → página amigável (HTTP 403).
- Autorização em cada ação: empresa só altera as próprias vagas e as candidaturas delas; candidato só mexe no
  que é dele; o administrador não exclui nem rebaixa a própria conta e sempre sobra um administrador ativo.
- Cadastro público só cria candidato ou empresa (nunca administrador) e exige o aceite do termo.
- Todo texto exibido passa por `e()` (htmlspecialchars). Links externos só aceitam http/https.

**Arquivos e pastas**
- Só a pasta `public/` é servida. `app/`, `config/`, `database/`, `docs/`, `storage/` e `tests/` ficam
  inacessíveis (o `.htaccess` da raiz desvia tudo para `public/`, e cada uma dessas pastas tem um
  `.htaccess` com `Require all denied` como segunda barreira).
- Uploads ficam em `storage/uploads/`. Imagens: JPG/PNG/WEBP até 3 MB, conferidas pelo conteúdo, com nome
  aleatório, entregues por `ArquivoController::imagem` (só tipos de imagem). Currículos: só por `download.php`,
  para o dono, o administrador, a empresa que recebeu a candidatura ou empresa Premium (perfil público).
- Cabeçalhos: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`.
- Empresa bloqueada: as vagas dela saem da área pública e deixam de receber candidaturas.
- Candidatura cancelada: a empresa perde o acesso ao contato e ao currículo daquele candidato (LGPD).
- Buscas com `LIKE` tratam `%` e `_` digitados como texto (`like()`); visualização de vaga conta 1 vez por visitante.

**Doação (rodapé)**: com `DOACAO_PIX_CHAVE` preenchida em `config/config.php`, o rodapé mostra o QR Code Pix
(`Pix::doacao()` monta o código; `assets/js/vendor/qrcode.js`, licença MIT, desenha o QR no navegador, sem internet)
e o botão "Copiar código Pix". Nenhuma API externa é chamada.

**Recuperação de senha (demonstrativa)**
- Não há envio de e-mail. O link (válido por 30 minutos, uso único) é gravado em
  `storage/logs/redefinicoes_senha.log` e, com `DEBUG=true` e acesso pelo próprio computador, aparece na tela.
- O banco guarda só o hash SHA-256 do token. A resposta é a mesma exista ou não o e-mail; no máximo
  3 pedidos por hora por conta; ao trocar a senha, todos os links pendentes deixam de valer.

---

## 9. Arquivos enviados

- Gravados em `storage/uploads/` com nome aleatório (ex.: `foto_3_a1b2c3.png`, `cv_3_20260923_ab12.pdf`).
- No banco fica o caminho **lógico** `assets/uploads/<nome>` — o mesmo endereço usado no navegador.
- `caminho_upload()` (`app/Core/Upload.php`) converte o caminho do banco no caminho real do disco.
- `apagar_upload_sem_uso()` só apaga um arquivo quando nenhuma vaga, curso, perfil ou currículo o usa mais.

---

## 10. Banco de dados

`database/schema.sql` (estrutura) + `database/seed.sql` (demonstração). Banco: `conecta_vagas_df_v2`.

```
usuarios 1──1 perfis 1──N curriculos
                  │  └──N vagas (empresa) ──N candidaturas N── perfis (candidato)
                  │                     └──N matches      N── perfis (candidato)
usuarios 1──N assinaturas · tentativas_login · redefinicoes_senha
categorias 1──N vagas / cursos
usuarios 1──N aprendizado_exemplos · aprendizado_revisoes   (quem ensinou; aprendizado_palavras são os contadores)
```

Categorias de vaga do seed: TI, Administração, Marketing, Vendas, RH, Financeiro, Engenharia, Saúde, Educação,
Alimentação, Serviços Gerais e Limpeza, Logística e Transporte e Atendimento ao Público (as quatro últimas são as que a
extração de vagas sugere). Uma categoria em uso não troca entre "Vagas" e "Cursos".

Conexão (`app/Core/Database.php`): uma conexão por requisição, erros como exceção, prepared statements reais,
timeout de 5 s, utf8mb4 e o fuso do MySQL igual ao do PHP (America/Sao_Paulo). Falhas viram
`DatabaseException` com mensagem em português (serviço desligado, banco inexistente, senha recusada).

---

## 11. Mapa da reorganização (onde estava → onde está)

Útil para atualizar a monografia e os diagramas.

| Antes | Agora |
|---|---|
| páginas na raiz (`vagas.php`, `login.php`...), `admin/`, `view/` | lógica em `app/Controllers/`, HTML em `app/Views/` (os endereços continuam os mesmos) |
| `config/config.php` (configuração + funções + sessão) | `config/config.php` (só configuração) + `app/Core/` (funções por assunto) |
| `config/Conexao.php` — classe `Conexao` | `app/Core/Database.php` — classe `Database` |
| `model/dao/*.php` | `app/Models/*.php` |
| `model/dto/*.php` | `app/DTO/*.php` |
| `controller/match/MatchController.php` | `app/Services/MatchService.php` (classe `MatchService`) |
| `controller/match/Competencias.php` | `app/Services/Competencias.php` |
| `controller/portfolio/Portfolio.php` | `app/Services/Portfolio.php` |
| `controller/extracao/*.php` | `app/Services/Extracao/*.php` (`LeitorDocumento.php` dividido em `LeitorDocumento`, `PdfTexto` e `DocxTexto`) |
| `view/layout/portal.php` | `app/Views/partials/componentes.php` |
| `view/layout/icones.php` | `app/Views/partials/icones.php` |
| `view/perfil/_relatorio_extracao.php` | `app/Views/partials/relatorio_extracao.php` |
| `download.php` | `ArquivoController::download` |
| `assets/` | `public/assets/` |
| `assets/uploads/` | `storage/uploads/` (fora da pasta pública) |
| `logs/` | `storage/logs/` |
| `banco/ScriptBD.sql` | `database/schema.sql` + `database/seed.sql` |
| `docs/ESTRUTURA.txt`, `docs/INSTALACAO.txt` | `README.md` + este documento |

Removidos por não serem usados: as funções `perfilAtual()`, `pt_data_extenso()`, `pt_leitura()`,
`pt_ascii()` e `pt_cor_editoria()`; os métodos `CandidaturaDAO::cadastrar()`, `jaCandidatou()` e `reenviar()`
(substituídos por `enviarComLimite()`); a pasta `assets/img/vagas/originais/` (cópias dos cartazes antes do recorte).

---

## 12. Diagnóstico

Se o banco falhar, o sistema mostra "Banco de dados indisponível" (HTTP 503) com a orientação certa. Confira:
- Apache e MySQL iniciados (XAMPP Control Panel, status verde).
- O banco `conecta_vagas_df_v2` existe (importe `database/schema.sql` e `database/seed.sql`).
- `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS` corretos (`config/config.php` ou variáveis de ambiente).
- A pasta `storage/uploads` permite gravação.
- Só a página inicial abre e as outras dão "Not Found" do Apache: o `mod_rewrite` está desligado
  (no `httpd.conf`, a linha `LoadModule rewrite_module` não pode estar comentada e a pasta precisa de `AllowOverride All`).
- Com `DEBUG=true`, o detalhe técnico aparece abaixo da mensagem amigável.
- Teste automático: `C:\xampp\php\php.exe tests\smoke.php`.

---

## 13. Fotos do carrossel

Todas as imagens de `public/assets/img/brasilia/` entram no carrossel da página inicial, em ordem de nome.
Fotos de teste (Wikimedia Commons, licenças livres); os créditos ficam em `HomeController::index`:

| Arquivo | Foto | Licença |
|---|---|---|
| `brasilia1.jpg` | imagem original do projeto | — |
| `brasilia2.jpg` | Catedral Metropolitana — Agência Brasília | CC BY 2.0 |
| `brasilia3.jpg` | Eixo Monumental — Cayambe | CC BY-SA 3.0 |
| `brasilia4.jpg` | Ponte JK — Marinelson Almeida | CC BY 2.0 |
| `brasilia5.jpg` | Esplanada à noite — Dasfour2022 | CC BY-SA 4.0 |
