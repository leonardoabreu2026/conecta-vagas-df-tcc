document.addEventListener('DOMContentLoaded',()=>{
  // Confirmação em ações destrutivas (painel).
  document.querySelectorAll('[data-confirm]').forEach(e=>e.addEventListener('click',ev=>{if(!confirm(e.dataset.confirm))ev.preventDefault()}));
  setTimeout(()=>document.querySelectorAll('.alert').forEach(a=>a.style.opacity='.75'),5000);

  // Portal: botão "Copiar link" das matérias/reportagens.
  document.querySelectorAll('[data-copiar]').forEach(btn=>btn.addEventListener('click',async()=>{
    const link=btn.dataset.copiar, rotulo=btn.querySelector('span');
    let ok=false;
    try{ if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(link);ok=true;} }catch(e){}
    if(!ok){ // Fallback para http://localhost sem clipboard API
      const t=document.createElement('textarea');t.value=link;t.setAttribute('readonly','');t.style.position='fixed';t.style.opacity='0';
      document.body.appendChild(t);t.select();try{ok=document.execCommand('copy');}catch(e){}t.remove();
    }
    if(!ok){window.prompt('Copie o link:',link);return;}
    btn.classList.add('copiado'); if(rotulo) rotulo.textContent='Link copiado!';
    setTimeout(()=>{btn.classList.remove('copiado'); if(rotulo) rotulo.textContent='Copiar link';},2500);
  }));

  // Carrossel de fotos do topo da página inicial.
  document.querySelectorAll('[data-carrossel]').forEach(c=>{
    const slides=[...c.querySelectorAll('.cv-slide')], dots=[...c.querySelectorAll('.cv-hero-dots button')], cred=c.querySelector('.cv-hero-credito');
    // Carrega as demais fotos depois da primeira (a página abre mais rápido).
    slides.forEach(s=>{ if(s.dataset.bg){ s.style.backgroundImage="url('"+s.dataset.bg+"')"; } });
    let i=0, timer=null;
    const mostrarCredito=()=>{ if(!cred) return; const t=slides[i].dataset.credito||''; cred.textContent=t; cred.hidden=!t; cred.href=slides[i].dataset.fonte||'#'; };
    const ir=n=>{
      slides[i].classList.remove('ativo'); if(dots[i]) dots[i].classList.remove('ativo');
      i=(n+slides.length)%slides.length;
      slides[i].classList.add('ativo'); if(dots[i]) dots[i].classList.add('ativo');
      mostrarCredito();
    };
    mostrarCredito();
    if(slides.length<2) return;
    const calmo=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const iniciar=()=>{ clearInterval(timer); if(!calmo) timer=setInterval(()=>ir(i+1),5000); };
    dots.forEach((d,n)=>d.addEventListener('click',()=>{ ir(n); iniciar(); }));
    c.addEventListener('mouseenter',()=>clearInterval(timer));
    c.addEventListener('mouseleave',iniciar);
    iniciar();
  });

  // Painéis relâmpago da página inicial: um por vez, no canto da tela, de tempos em tempos.
  // Pausam com o mouse/foco em cima; quem fecha não vê mais nesta sessão.
  document.querySelectorAll('[data-relampago]').forEach(r=>{
    const paineis=[...r.querySelectorAll('.cv-relampago-painel')], barra=r.querySelector('.cv-relampago-barra i');
    const CHAVE='cv-relampago-fechado', VISIVEL=9000, INTERVALO=14000, INICIO=3500;
    try{ if(sessionStorage.getItem(CHAVE)) return; }catch(e){}
    if(!paineis.length) return;
    let n=0, timer=null, restante=VISIVEL, desde=0, aberto=false, pausado=false;
    const agendar=(fn,ms)=>{ clearTimeout(timer); timer=setTimeout(fn,ms); };
    const esconder=()=>{
      aberto=false; r.classList.remove('visivel');
      agendar(()=>{ r.hidden=true; n=(n+1)%paineis.length; agendar(mostrar,INTERVALO); },400);
    };
    const correr=ms=>{ desde=Date.now(); restante=ms; if(barra){ barra.style.transition='none'; barra.style.width=(ms/VISIVEL*100)+'%'; void barra.offsetWidth; barra.style.transition='width '+ms+'ms linear'; barra.style.width='0%'; } agendar(esconder,ms); };
    const mostrar=()=>{
      paineis.forEach((p,k)=>p.hidden=k!==n);
      r.hidden=false; void r.offsetWidth; r.classList.add('visivel'); aberto=true;
      if(!pausado) correr(VISIVEL);
    };
    const pausar=()=>{ if(!aberto||pausado) return; pausado=true; clearTimeout(timer); restante=Math.max(1500,restante-(Date.now()-desde)); if(barra){ barra.style.transition='none'; barra.style.width=(restante/VISIVEL*100)+'%'; } };
    const retomar=()=>{ if(!pausado) return; pausado=false; if(aberto) correr(restante); };
    r.addEventListener('mouseenter',pausar); r.addEventListener('mouseleave',retomar);
    r.addEventListener('focusin',pausar); r.addEventListener('focusout',retomar);
    r.querySelector('.cv-relampago-fechar').addEventListener('click',()=>{
      clearTimeout(timer); r.classList.remove('visivel'); setTimeout(()=>{ r.hidden=true; },300);
      try{ sessionStorage.setItem(CHAVE,'1'); }catch(e){}
    });
    agendar(mostrar,INICIO);
  });

  // Mostrar/ocultar senha (botão com o ícone de olho).
  document.querySelectorAll('[data-mostrar]').forEach(b=>b.addEventListener('click',()=>{
    const i=document.getElementById(b.dataset.mostrar); if(!i) return;
    const ver=i.type==='password'; i.type=ver?'text':'password';
    b.setAttribute('aria-label',ver?'Ocultar senha':'Mostrar senha');
  }));

  // Menu do celular: fecha com Esc.
  document.addEventListener('keydown',ev=>{if(ev.key==='Escape'&&document.body.classList.contains('menu-aberto')){document.body.classList.remove('menu-aberto');const b=document.querySelector('.nav-toggle');if(b)b.setAttribute('aria-expanded','false');}});
});
