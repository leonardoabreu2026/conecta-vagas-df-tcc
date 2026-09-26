# Pesquisa guiada de cursos e e-books — de onde vêm os links novos

Este é o roteiro para **cadastrar novos cursos, e-books e vídeos** com links oficiais, sem repetir o que já existe
e mirando as áreas que têm menos conteúdo. Tudo acontece em **Painel → Cursos e e-books → "Novos links: pesquisa guiada"**.

## Visão geral

```
 escolher o foco ──► prompt pronto ──► IA de pesquisa ──► colar a resposta ──► prévia ──► cadastrar marcados
 (formato, área,     (fontes oficiais,  (Perplexity ou     (fichas separadas    (link, imagem,  (baixa a imagem,
  fonte, quantidade)  lacunas, links     ChatGPT com       por ---)             repetido?)      publica)
                      já cadastrados)    busca na web)
```

| Peça | Arquivo | Papel |
|---|---|---|
| Catálogo de fontes oficiais | `app/Services/Extracao/FontesCursos.php` (`FONTES`) | onde procurar: catálogo, domínio para `site:`, formatos, dica |
| Cobertura e lacunas | `FontesCursos::cobertura()` | conta o que já existe por área/formato e por fonte; as áreas da metade de baixo são **lacunas** |
| Prompt direcionado | `FontesCursos::prompt()` | monta o pedido: formato, área (ou lacunas), fonte(s), quantidade (5–40) e os **links já cadastrados** para não repetir |
| Leitura das fichas | `ExtracaoCurso::fichas()` | limpa o Markdown da IA, separa as fichas e extrai cada campo |
| Nome da instituição | `FontesCursos::nomeOficial()` | padroniza pelo domínio do link (ex.: "Fundação Bradesco - Escola Virtual" → "Fundação Bradesco – Escola Virtual") |
| Imagem | `ImagemRemota` | confere e baixa a capa/imagem; sem imagem a ficha não entra |

## Passo a passo

1. **Direcione a pesquisa** (primeiro passo do painel):
   - **Formato**: cursos e e-books (misto), só cursos, só e-books ou só vídeos;
   - **Área**: deixe em "Áreas com menos conteúdo" para cobrir as lacunas, ou escolha uma área;
   - **Fonte**: todas as fontes oficiais que têm o formato, ou uma só (ex.: só SEBRAE);
   - **Quantidade**: 20 é um bom lote (máximo 40).
   Clique em **Gerar prompt**. Em "Cobertura atual por área e fontes oficiais" há atalhos **Pesquisar** por área
   e por fonte, e o link **Abrir** do catálogo de cada instituição (para conferir à mão).
2. **Copie o prompt** e cole no Perplexity (ou ChatGPT com busca na web).
3. **Cole a resposta inteira** em "Ler fichas". Nada é salvo ainda.
4. **Confira a prévia**: cada ficha aparece como *Pronto*, *Sem link válido*, *Sem imagem* ou *Já cadastrado (#id)*.
   Abra os links suspeitos antes de cadastrar.
5. **Cadastrar marcados**: as imagens são baixadas para `storage/uploads` e os conteúdos já entram publicados.
   Depois, na lista, dá para ordenar por qualquer coluna, filtrar por formato/área/situação, editar, ocultar ou excluir.

## Fontes oficiais do catálogo

| Fonte | Formatos | Onde é forte | Ponto de partida |
|---|---|---|---|
| Fundação Bradesco – Escola Virtual | curso | Informática/Excel, Administração, Finanças | https://www.ev.org.br/cursos |
| Escola Virtual.Gov (Enap) | curso | Tecnologia e IA, Gestão, Dados, Carreira | https://www.escolavirtual.gov.br/catalogo |
| SEBRAE | curso, e-book | Empreendedorismo, Marketing, Finanças | https://sebrae.com.br/sites/PortalSebrae/cursosonline |
| Google Grow | curso, vídeo | Marketing digital, Dados, IA | https://grow.google/intl/pt-br/ |
| Microsoft Learn | curso | Tecnologia e IA, Excel, Dados | https://learn.microsoft.com/pt-br/training/browse/ |
| FGV Online | curso | Negócios, Finanças e ESG, Gestão | https://educacao-executiva.fgv.br/cursos/gratuitos |
| IFB – Instituto Federal de Brasília | curso | presenciais gratuitos no DF (FIC) | https://www.ifb.edu.br |
| SENAI | curso | Tecnologia, Indústria | https://www.portaldaindustria.com.br/senai/ |
| SENAC | curso | Administração, Atendimento, Comércio | https://www.ead.senac.br/ |
| Banco Central do Brasil | e-book, curso | Finanças | https://www.bcb.gov.br/cidadaniafinanceira |
| CERT.br / NIC.br | e-book | Segurança na internet | https://cartilha.cert.br/ |
| Febraban – Meu Bolso em Dia | e-book, curso | Finanças pessoais | https://meubolsoemdia.com.br/ |
| Ministério do Trabalho e Emprego | e-book | Direitos trabalhistas | https://www.gov.br/trabalho-e-emprego/pt-br |
| eduCAPES (repositório) | e-book, vídeo | Todas | https://educapes.capes.gov.br/ |
| CVM – Portal do Investidor | e-book, curso | Finanças | https://www.gov.br/investidor/pt-br |
| Cisco Networking Academy | curso | Redes, cibersegurança | https://www.netacad.com/pt |
| Fundação Estudar | curso, e-book | Carreira | https://www.estudar.org.br/ |

**Adicionar uma fonte**: inclua uma entrada em `FontesCursos::FONTES` com `nome`, `dominios` (o primeiro é usado
no `site:`), `catalogo`, `formatos`, `areas` e `dica`. Em repositórios que publicam material de terceiros
(como o eduCAPES), marque `'repositorio' => true`: o nome do autor informado na ficha é mantido.

## Regras que o prompt impõe à IA

- só links do site oficial, abrindo a página do próprio curso/e-book (PDF oficial vale para e-book);
- nada encerrado ou com inscrições fechadas;
- **imagem obrigatória** (capa do e-book, imagem de divulgação do curso), nunca logotipo genérico;
- não repetir os links já cadastrados (a lista vai no fim do prompt, filtrada pela área/fonte escolhida);
- resposta só em fichas com os rótulos exatos, separadas por `---`.

## Organização dos nomes

O botão **Padronizar nomes de instituição** (dentro de "Cobertura atual…") só aparece quando há conteúdo fora do
padrão. Ele troca o nome pela forma oficial da fonte do link e mantém a parceria entre parênteses
("… (conteúdo Microsoft)"). Fichas importadas já entram com o nome padronizado.
