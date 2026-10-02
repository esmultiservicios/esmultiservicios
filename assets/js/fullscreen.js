(()=>{
  'use strict';
  const KEY='evaluacion:fullscreen-preference';
  const buttons=()=>[...document.querySelectorAll('[data-fullscreen-toggle]')];
  const preferred=()=>{try{return localStorage.getItem(KEY)==='1'}catch{return false}};
  const save=v=>{try{localStorage.setItem(KEY,v?'1':'0')}catch{}};
  const active=()=>!!document.fullscreenElement;
  const sync=()=>buttons().forEach(btn=>{
    const on=active();
    btn.classList.toggle('is-active',on);
    btn.setAttribute('aria-pressed',on?'true':'false');
    btn.title=on?'Salir de pantalla completa':'Pantalla completa';
    const label=btn.querySelector('[data-fullscreen-label]');
    if(label)label.textContent=on?'Salir de pantalla completa':(preferred()?'Reanudar pantalla completa':'Pantalla completa');
  });
  document.addEventListener('click',async e=>{
    const btn=e.target.closest('[data-fullscreen-toggle]');if(!btn)return;
    e.preventDefault();
    try{
      if(active()){save(false);await document.exitFullscreen();}
      else{save(true);await document.documentElement.requestFullscreen({navigationUI:'hide'});}
    }catch(err){save(false);if(window.showNotify)showNotify('warning','El navegador no permitió activar pantalla completa.');}
    sync();
  });
  document.addEventListener('fullscreenchange',()=>{if(active())save(true);sync()});
  sync();
  window.EvalFullscreen={sync,preferred,active};
})();
