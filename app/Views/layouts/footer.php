<?php
/**
 * Rodapé do layout: links de navegação, conta, links úteis e contato.
 * Incluído por View::render() depois de cada tela. Usa $portfolioLiberado (calculado no cabeçalho).
 */
?>
</main>

<footer class="cv-rodape">
  <div class="cv-wrap cv-rodape-grade">
    <div>
      <h2>Conecta Vagas DF</h2>
      <p>Conectando talentos às melhores oportunidades de emprego e capacitação profissional no Distrito Federal.</p>
      <?php if (!usuarioLogado()): ?>
        <a class="cv-btn cv-btn-verde" href="<?=url('cadastro.php')?>">Criar conta grátis</a>
      <?php elseif (isCandidato()): ?>
        <a class="cv-btn cv-btn-verde" href="<?=url('view/perfil/index.php')?>">Enviar meu currículo</a>
      <?php else: ?>
        <a class="cv-btn cv-btn-verde" href="<?=url('admin/pages/vagas.php')?>">Publicar vaga</a>
      <?php endif; ?>
    </div>
    <div>
      <h2>Navegação</h2>
      <ul>
        <li><a href="<?=url('index.php')?>">Início</a></li>
        <li><a href="<?=url('vagas.php')?>">Vagas de emprego</a></li>
        <li><a href="<?=url('cursos.php')?>">Cursos gratuitos</a></li>
        <li><a href="<?=url('cursos.php?tipo=ebook')?>">E-books</a></li>
        <li><a href="<?=url('planos.php')?>">Planos e assinaturas</a></li>
      </ul>
    </div>
    <div>
      <h2>Sua conta</h2>
      <ul>
        <?php if (usuarioLogado()): ?>
          <?php if (isCandidato()): ?>
            <li><a href="<?=url('view/perfil/index.php')?>">Meu perfil</a></li>
            <?php if (!empty($portfolioLiberado)): ?><li><a href="<?=url('view/perfil/portfolio.php')?>">Meu portfólio</a></li><?php endif; ?>
            <li><a href="<?=url('view/perfil/index.php')?>#candidaturas">Minhas candidaturas</a></li>
          <?php else: ?>
            <li><a href="<?=url('admin/index.php')?>">Painel</a></li>
            <li><a href="<?=url('admin/pages/vagas.php')?>">Minhas vagas</a></li>
            <li><a href="<?=url('admin/pages/candidaturas.php')?>">Candidaturas</a></li>
          <?php endif; ?>
          <li><a href="<?=logout_url()?>">Sair</a></li>
        <?php else: ?>
          <li><a href="<?=url('cadastro.php')?>">Cadastro</a></li>
          <li><a href="<?=url('login.php')?>">Login</a></li>
          <li><a href="<?=url('esqueci_senha.php')?>">Esqueci minha senha</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <div>
      <h2>Links úteis</h2>
      <ul>
        <?php foreach ([
            'Fundação Bradesco' => 'https://www.ev.org.br',
            'SEBRAE' => 'https://sebrae.com.br',
            'Escola Virtual.Gov' => 'https://www.escolavirtual.gov.br',
            'SENAI' => 'https://www.portaldaindustria.com.br/senai/',
            'SENAC' => 'https://www.senac.br',
            'LinkedIn Learning' => 'https://www.linkedin.com/learning',
        ] as $nomeLink => $hrefLink): ?>
          <li><a href="<?=e($hrefLink)?>" target="_blank" rel="noopener"><?=e($nomeLink)?> <?=icone('externo', 12)?><span class="sr-only"> (abre em nova aba)</span></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h2>Contato</h2>
      <ul>
        <li><?=icone('email', 16)?> suporte@conectavagas.df</li>
        <li><?=icone('local', 16)?> Brasília - DF</li>
        <li><a href="<?=url('contrato.php')?>">Termo de privacidade (LGPD)</a></li>
      </ul>
    </div>
  </div>
  <div class="cv-rodape-base">
    <div class="cv-wrap">
      <span>&copy; <?=date('Y')?> Conecta Vagas DF — Todos os direitos reservados.</span>
      <span>Plataforma desenvolvida como Trabalho de Conclusão de Curso (TCC), para fins educacionais.</span>
    </div>
  </div>
  <a class="cv-topo-btn" href="#conteudo" aria-label="Voltar ao topo"><?=icone('topo', 20)?></a>
</footer>

<script src="<?=url('assets/js/app.js')?>?v=3"></script>
</body>
</html>
