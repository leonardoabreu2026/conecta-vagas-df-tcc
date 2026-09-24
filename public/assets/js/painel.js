// Painéis: dica (tooltip) dos gráficos. Cada marca tem data-valor e data-rotulo (partials/graficos.php);
// a dica mostra o valor em destaque e o rótulo embaixo, seguindo o ponteiro. Texto via textContent.
(()=>{
  const dica=document.createElement('div'), valor=document.createElement('b'), rotulo=document.createElement('span');
  dica.className='gf-dica'; dica.hidden=true; dica.setAttribute('aria-hidden','true');
  dica.append(valor,rotulo); document.body.appendChild(dica);
  const alvo=ev=>ev.target instanceof Element ? ev.target.closest('.gf [data-valor]') : null;
  const posicionar=(x,y)=>{
    const w=dica.offsetWidth, h=dica.offsetHeight;
    let l=x+14, t=y-h-12;
    if(l+w>innerWidth-8) l=Math.max(8,x-w-14);
    if(t<8) t=y+18;
    dica.style.left=l+'px'; dica.style.top=t+'px';
  };
  document.addEventListener('pointerover',ev=>{
    const el=alvo(ev); if(!el) return;
    valor.textContent=el.dataset.valor||''; rotulo.textContent=el.dataset.rotulo||'';
    dica.hidden=false; posicionar(ev.clientX,ev.clientY);
  });
  document.addEventListener('pointermove',ev=>{ if(!dica.hidden && alvo(ev)) posicionar(ev.clientX,ev.clientY); });
  document.addEventListener('pointerout',ev=>{ const el=alvo(ev); if(el && !el.contains(ev.relatedTarget)) dica.hidden=true; });
  addEventListener('scroll',()=>{ dica.hidden=true; },{passive:true});
})();
