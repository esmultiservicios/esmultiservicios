(function(w,d){
  function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
  const glyph={warning:'!',success:'✓',error:'×',info:'i',question:'?'};
  const icons={
    check:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
    x:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    logout:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>',
    trash:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6"/></svg>'
  };
  function buttonContent(icon,text){return `<span class="swal2-local-btn-icon">${icons[icon]||icons.check}</span><span>${esc(text)}</span>`}
  function fire(input){const o=typeof input==='string'?{title:input}:(input||{});return new Promise(resolve=>{
    const back=d.createElement('div');back.className='swal2-local-backdrop';
    const pop=d.createElement('div');pop.className='swal2-local-popup';pop.setAttribute('role','dialog');pop.setAttribute('aria-modal','true');
    const icon=String(o.icon||'info');let html=`<div class="swal2-local-icon ${esc(icon)}">${esc(glyph[icon]||'i')}</div><h2 class="swal2-local-title">${esc(o.title||'')}</h2>`;
    if(o.showCloseButton){html+=`<button type="button" class="swal2-local-close" aria-label="Cerrar" title="Cerrar">${icons.x}</button>`}
    if(o.html)html+=`<div class="swal2-local-text">${o.html}</div>`;else if(o.text)html+=`<div class="swal2-local-text">${esc(o.text)}</div>`;
    html+='<div class="swal2-local-actions"></div>';pop.innerHTML=html;back.appendChild(pop);d.body.appendChild(back);
    const actions=pop.querySelector('.swal2-local-actions');let settled=false;
    const key=e=>{if(e.key==='Escape'&&o.allowEscapeKey!==false)done(false)};
    function done(isConfirmed){if(settled)return;settled=true;d.removeEventListener('keydown',key);back.remove();resolve({isConfirmed,isDismissed:!isConfirmed})}
    pop.querySelector('.swal2-local-close')?.addEventListener('click',()=>done(false));
    if(o.showCancelButton){const cancel=d.createElement('button');cancel.type='button';cancel.className='swal2-local-btn swal2-local-cancel';cancel.innerHTML=buttonContent(o.cancelButtonIcon||'x',o.cancelButtonText||'Cancelar');cancel.onclick=()=>done(false);actions.appendChild(cancel)}
    if(o.showConfirmButton!==false){const ok=d.createElement('button');ok.type='button';ok.className='swal2-local-btn swal2-local-confirm';ok.innerHTML=buttonContent(o.confirmButtonIcon||'check',o.confirmButtonText||'Aceptar');ok.onclick=()=>done(true);actions.appendChild(ok);setTimeout(()=>ok.focus(),20)}
    if(o.reverseButtons&&actions.children.length>1)actions.insertBefore(actions.lastElementChild,actions.firstElementChild);
    if(o.allowOutsideClick!==false)back.addEventListener('mousedown',e=>{if(e.target===back)done(false)});
    d.addEventListener('keydown',key);
  })}
  w.Swal={fire};
})(window,document);
