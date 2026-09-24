document.addEventListener('DOMContentLoaded',()=>{
  // Confirmação em ações destrutivas (painel).
  document.querySelectorAll('[data-confirm]').forEach(e=>e.addEventListener('click',ev=>{if(!confirm(e.dataset.confirm))ev.preventDefault()}));
  // Só a mensagem "flash" (resultado da última ação) esmaece; avisos permanentes da tela continuam fortes.
  setTimeout(()=>document.querySelectorAll('[data-flash]').forEach(a=>a.style.opacity='.75'),5000);

  // Botões "Copiar" (link das matérias, código Pix da doação). data-copiado = texto de confirmação.
  document.querySelectorAll('[data-copiar]').forEach(btn=>btn.addEventListener('click',async()=>{
    const link=btn.dataset.copiar, rotulo=btn.querySelector('span'), original=rotulo?rotulo.textContent:'';
    let ok=false;
    try{ if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(link);ok=true;} }catch(e){}
    if(!ok){ // Fallback para http://localhost sem clipboard API
      const t=document.createElement('textarea');t.value=link;t.setAttribute('readonly','');t.style.position='fixed';t.style.opacity='0';
      document.body.appendChild(t);t.select();try{ok=document.execCommand('copy');}catch(e){}t.remove();
    }
    if(!ok){window.prompt('Copie:',link);return;}
    btn.classList.add('copiado'); if(rotulo) rotulo.textContent=btn.dataset.copiado||'Link copiado!';
    setTimeout(()=>{btn.classList.remove('copiado'); if(rotulo) rotulo.textContent=original;},2500);
  }));

  // QR Code Pix de doação (rodapé): desenhado em SVG a partir do código "copia e cola".
  document.querySelectorAll('[data-qrcode]').forEach(el=>{
    if(typeof qrcode!=='function') return;
    try{ const q=qrcode(0,'M'); q.addData(el.dataset.qrcode); q.make(); el.innerHTML=q.createSvgTag({cellSize:4,margin:8,scalable:true}); }catch(e){}
  });

  // Máquina de extração: ao escolher o cartaz, mostra a prévia e já envia para leitura (sem clique extra).
  document.querySelectorAll('[data-auto-envio]').forEach(inp=>inp.addEventListener('change',()=>{
    const f=inp.files&&inp.files[0], form=inp.form; if(!f||!form) return;
    const prev=form.querySelector('[data-previa]');
    if(prev&&f.type.startsWith('image/')){ prev.src=URL.createObjectURL(f); prev.hidden=false; }
    const aviso=form.querySelector('[data-lendo]'); if(aviso) aviso.hidden=false;
    const btn=form.querySelector('button'); if(btn){ btn.disabled=true; btn.textContent='Lendo o cartaz…'; }
    form.submit();
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

  // Painel relâmpago da página inicial: um balão de ideia que mostra uma mensagem por vez, em rodízio
  // (quem somos, objetivo, missão, valores, Pix). Pausa com o mouse/foco em cima; quem fecha fica 3 minutos sem vê-lo.
  document.querySelectorAll('[data-relampago]').forEach(r=>{
    const paineis=[...r.querySelectorAll('.cv-relampago-painel')], barra=r.querySelector('.cv-relampago-barra i');
    const CHAVE='cv-relampago-fechado', VISIVEL=9000, INTERVALO=7000, INICIO=4000, PAUSA_FECHADO=180000;
    try{ const f=+sessionStorage.getItem(CHAVE); if(f && Date.now()-f<PAUSA_FECHADO) return; }catch(e){}
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
      r.classList.remove('visivel'); r.hidden=false; void r.offsetWidth; r.classList.add('visivel'); aberto=true; // reinicia a animação de entrada
      if(!pausado) correr(VISIVEL);
    };
    const pausar=()=>{ if(!aberto||pausado) return; pausado=true; clearTimeout(timer); restante=Math.max(1500,restante-(Date.now()-desde)); if(barra){ barra.style.transition='none'; barra.style.width=(restante/VISIVEL*100)+'%'; } };
    const retomar=()=>{ if(!pausado) return; pausado=false; if(aberto) correr(restante); };
    r.addEventListener('mouseenter',pausar); r.addEventListener('mouseleave',retomar);
    r.addEventListener('focusin',pausar); r.addEventListener('focusout',retomar);
    r.querySelector('.cv-relampago-fechar').addEventListener('click',()=>{
      clearTimeout(timer); r.classList.remove('visivel'); setTimeout(()=>{ r.hidden=true; },300);
      try{ sessionStorage.setItem(CHAVE,String(Date.now())); }catch(e){}
    });
    const prox=r.querySelector('[data-relampago-prox]');
    if(prox) prox.addEventListener('click',()=>{ const p=pausado; n=(n+1)%paineis.length; pausado=false; mostrar(); if(p) pausar(); });
    // Clicar em "Ver como doar" leva ao rodapé e deixa o painel da doação parado na tela.
    r.querySelectorAll('a[href^="#"]').forEach(a=>a.addEventListener('click',()=>pausar()));
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
