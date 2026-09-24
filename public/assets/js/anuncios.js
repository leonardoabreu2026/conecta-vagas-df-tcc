/* Anúncios de vagas: cartaz ampliado (vaga.php), compartilhar pelo celular e contador da carta (candidatar.php). */
document.addEventListener('DOMContentLoaded',()=>{
  // Cartaz ampliado numa janela (<dialog>). Sem suporte (ou com Ctrl/meio do mouse), o link abre a imagem em nova aba.
  document.querySelectorAll('[data-ampliar]').forEach(link=>{
    const caixa=document.getElementById(link.dataset.ampliar);
    if(!caixa||typeof caixa.showModal!=='function') return;
    const img=caixa.querySelector('img'), fechar=()=>caixa.close();
    link.addEventListener('click',ev=>{
      if(ev.ctrlKey||ev.metaKey||ev.shiftKey||ev.button===1) return;
      ev.preventDefault(); caixa.classList.remove('zoom'); caixa.showModal();
      const b=caixa.querySelector('[data-fechar]'); if(b) b.focus();
    });
    caixa.querySelectorAll('[data-fechar]').forEach(b=>b.addEventListener('click',fechar));
    caixa.addEventListener('click',ev=>{ if(ev.target===caixa) fechar(); });      // clique fora do cartaz fecha
    if(img) img.addEventListener('click',()=>caixa.classList.toggle('zoom'));      // clique no cartaz: tamanho real / ajustado
    caixa.addEventListener('close',()=>link.focus());
  });

  // Compartilhar pelo menu do aparelho (Web Share API): o botão só aparece onde existe.
  if(navigator.share) document.querySelectorAll('[data-compartilhar]').forEach(b=>{
    b.hidden=false;
    b.addEventListener('click',()=>navigator.share({title:b.dataset.titulo,text:b.dataset.titulo,url:b.dataset.compartilhar}).catch(()=>{}));
  });

  // Contador de caracteres (carta de apresentação).
  document.querySelectorAll('[data-contador]').forEach(campo=>{
    const saida=document.getElementById(campo.dataset.contador); if(!saida) return;
    const max=campo.maxLength>0?campo.maxLength:0;
    const atualizar=()=>{ saida.textContent=campo.value.length.toLocaleString('pt-BR')+(max?' / '+max.toLocaleString('pt-BR'):''); };
    campo.addEventListener('input',atualizar); atualizar();
  });
});
