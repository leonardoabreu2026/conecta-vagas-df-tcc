# Fontes oficiais de cursos e e-books — de onde vêm os links novos

Referência para pesquisar **novos cursos, e-books e vídeos** com links oficiais. O **prompt padrão** das IAs de
pesquisa está em [PROMPTS_PESQUISA.md](PROMPTS_PESQUISA.md); a resposta (fichas) é colada na caixa **Extrair** do
painel (**Painel → Cursos e e-books**).

| Peça | Arquivo | Papel |
|---|---|---|
| Catálogo de fontes oficiais | `app/Services/Extracao/FontesCursos.php` (`FONTES`) | onde procurar: catálogo, domínio para `site:`, formatos, dica |
| Cobertura e lacunas | `FontesCursos::cobertura()` | conta o que já existe por área/formato e por fonte |
| Prompt em lote direcionado | `FontesCursos::prompt()` | formato, área (ou lacunas), fonte(s), quantidade e os links já cadastrados, para não repetir |
| Leitura das fichas | `ExtracaoCurso::fichas()` | limpa o Markdown da IA, separa as fichas e extrai cada campo |
| Nome da instituição | `FontesCursos::nomeOficial()` | padroniza pelo domínio do link (ao importar e ao salvar) |
| Imagem | `ImagemRemota` | confere e baixa a capa/imagem; sem imagem, entra a imagem padrão da plataforma |

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
- **imagem** oficial (capa do e-book, imagem de divulgação do curso), nunca logotipo genérico; sem ela, o item entra com a imagem padrão;
- não repetir os links já cadastrados (a lista vai no fim do prompt, filtrada pela área/fonte escolhida);
- resposta só em fichas com os rótulos exatos, separadas por `---`.

## Organização dos nomes

O nome da instituição é padronizado automaticamente pelo link oficial, ao importar e ao salvar (mantém a parceria
entre parênteses, como "… (conteúdo Microsoft)"; em repositórios como o eduCAPES, o autor informado fica).
