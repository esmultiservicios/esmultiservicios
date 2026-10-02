/* ES MULTISERVICIOS - deterministic local action icons for public site, admin and installer. */
(() => {
  'use strict';
  const NS='http://www.w3.org/2000/svg';
  const paths={
    save:['M5 4h12l2 2v14H5z','M8 4v6h8V4','M8 20v-6h8v6'],
    add:['M12 5v14','M5 12h14'],
    trash:['M3 6h18','M8 6V4h8v2','M19 6l-1 14H6L5 6','M10 11v5','M14 11v5'],
    edit:['M4 20l4.5-1 10-10-3.5-3.5-10 10z','M14 6l3.5 3.5'],
    search:['M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16z','M21 21l-4.35-4.35'],
    upload:['M12 16V4','M7 9l5-5 5 5','M5 20h14'],
    download:['M12 4v12','M7 11l5 5 5-5','M5 20h14'],
    send:['M22 2L11 13','M22 2l-7 20-4-9-9-4z'],
    mail:['M3 5h18v14H3z','M3 7l9 6 9-6'],
    eye:['M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z','M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6'],
    login:['M10 17l5-5-5-5','M15 12H3','M14 3h7v18h-7'],
    logout:['M14 8l4 4-4 4','M18 12H8','M10 3H3v18h7'],
    next:['M5 12h14','M13 6l6 6-6 6'],
    back:['M19 12H5','M11 18l-6-6 6-6'],
    close:['M18 6L6 18','M6 6l12 12'],
    undo:['M9 14L4 9l5-5','M4 9h9a7 7 0 0 1 7 7v1'],
    archive:['M3 5h18v4H3z','M5 9v11h14V9','M10 13h4'],
    publish:['M12 3v12','M7 8l5-5 5 5','M5 21h14'],
    check:['M20 6L9 17l-5-5'],
    shield:['M12 3l8 3v6c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6z','M9 12l2 2 4-4'],
    settings:['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7','M19.4 13a7.6 7.6 0 0 0 0-2l2-1.5-2-3.4-2.4 1a8 8 0 0 0-1.7-1L15 3.5h-4l-.3 2.6a8 8 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.5a7.6 7.6 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8 8 0 0 0 1.7 1l.3 2.6h4l.3-2.6a8 8 0 0 0 1.7-1l2.4 1 2-3.4z'],
    power:['M12 2v10','M6.3 5.3a8 8 0 1 0 11.4 0'],
    paperclip:['M21.4 11.6l-8.5 8.5a6 6 0 0 1-8.5-8.5l9-9a4 4 0 0 1 5.7 5.7l-9 9a2 2 0 1 1-2.8-2.8l8.2-8.2'],
    refresh:['M20 6v5h-5','M4 18v-5h5','M19 11a7 7 0 0 0-12-4L4 11','M5 13a7 7 0 0 0 12 4l3-4'],
    user:['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8','M4 21a8 8 0 0 1 16 0'],
    copy:['M9 9h11v11H9z','M4 15H3V4h11v1'],
    lock:['M6 10h12v10H6z','M8 10V7a4 4 0 0 1 8 0v3'],
    info:['M12 11v6','M12 7h.01','M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20'],
    link:['M10 13a5 5 0 0 0 7.1 0l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1','M14 11a5 5 0 0 0-7.1 0l-2 2A5 5 0 0 0 12 20.1l1.1-1.1'],
    whatsapp:['M20 11.5a8 8 0 0 1-11.8 7L4 20l1.4-4A8 8 0 1 1 20 11.5z','M9 8.5c.5 2.5 2 4 4.5 5']
  };
  const icon=(name)=>{
    const svg=document.createElementNS(NS,'svg'); svg.setAttribute('viewBox','0 0 24 24'); svg.setAttribute('aria-hidden','true'); svg.setAttribute('focusable','false');
    (paths[name]||paths.info).forEach(d=>{const p=document.createElementNS(NS,'path');p.setAttribute('d',d);svg.appendChild(p)}); return svg;
  };
  const textOf=el=>((el.getAttribute('aria-label')||'')+' '+(el.getAttribute('title')||'')+' '+(el.textContent||'')+' '+(el.value||'')).replace(/\s+/g,' ').trim().toLowerCase();
  const classify=el=>{
    const t=textOf(el); const action=(el.getAttribute('name')==='action' ? String(el.value||'') : '')+' '+String(el.dataset.action||'');
    const s=(t+' '+action).toLowerCase();
    if(/whatsapp/.test(s))return'whatsapp';
    if(/delete|remove|eliminar|borrar|limpiar datos|delete permanently/.test(s))return'trash';
    if(/cancel|cancelar|close|cerrar|stay signed|conservar/.test(s))return /conservar/.test(s)?'back':'close';
    if(/back|atrás|previous|anterior/.test(s))return'back';
    if(/next|siguiente|continue|continuar/.test(s))return'next';
    if(/finalizar|finish|complete install/.test(s))return'check';
    if(/save|guardar|apply|aplicar|update|actualizar/.test(s))return'save';
    if(/add|crear|create|nuevo|nueva|agregar|register/.test(s))return'add';
    if(/edit|editar|modify|modificar|request changes/.test(s))return'edit';
    if(/search|buscar|quick find|find/.test(s))return'search';
    if(/upload|subir|cargar/.test(s))return'upload';
    if(/download|descargar|export/.test(s))return'download';
    if(/send|enviar|submit|solicitar|request estimate|request consultation/.test(s))return'send';
    if(/mail|correo|email/.test(s))return'mail';
    if(/preview|view|ver |visitar|visit|zoom/.test(s))return'eye';
    if(/log in|login|iniciar sesión|sign in/.test(s))return'login';
    if(/log out|logout|cerrar sesión|sign out/.test(s))return'logout';
    if(/restore|restaurar/.test(s))return'undo';
    if(/archive|archivar/.test(s))return'archive';
    if(/publish|publicar/.test(s))return'publish';
    if(/approve|aprobar|verify|verificar|confirm|confirmar|sí,|yes,/.test(s))return'check';
    if(/security|seguridad|2fa|authenticator/.test(s))return'shield';
    if(/settings|config|setup|ajustes/.test(s))return'settings';
    if(/activate|deactivate|activar|desactivar/.test(s))return'power';
    if(/choose file|choose files|seleccionar archivo|adjuntar|attach/.test(s))return'paperclip';
    if(/reset|reiniciar|refresh|recargar/.test(s))return'refresh';
    if(/profile|usuario|user/.test(s))return'user';
    if(/copy|copiar/.test(s))return'copy';
    if(/lock|bloquear/.test(s))return'lock';
    if(/link|enlace/.test(s))return'link';
    return null;
  };
  const excluded=el=>el.matches('[data-no-action-icon],.icon-btn,.zoom-btn,.media-preview,[data-rich-command],.mobile-menu,.user-avatar,.current-avatar-preview,.notify-close') || el.closest('[data-no-action-icon],.notify-item,.notify-stack');
  function decorate(el){
    if(!(el instanceof Element)||excluded(el))return;
    const type=classify(el); if(!type)return;
    if(el.querySelector(':scope > .ui-icon, :scope > svg')){el.dataset.actionIconReady='1';return;}
    const existing=el.querySelector(':scope > .btn-icon');
    if(existing){ existing.textContent=''; existing.appendChild(icon(type)); existing.dataset.actionIconReady='1'; el.dataset.actionIconReady='1'; return; }
    if(el.querySelector(':scope > .action-icon')){el.dataset.actionIconReady='1';return;}
    const span=document.createElement('span');span.className='action-icon action-icon--'+type;span.setAttribute('aria-hidden','true');span.appendChild(icon(type));
    el.insertBefore(span,el.firstChild);el.dataset.actionIconReady='1';
  }
  function scan(root=document){
    root.querySelectorAll?.('button,a.btn,a.button,.swal2-confirm,.swal2-cancel').forEach(decorate);
  }
  const start=()=>{
    scan();
    new MutationObserver(records=>records.forEach(r=>r.addedNodes.forEach(n=>{if(n.nodeType!==1)return;decorate(n);scan(n)}))).observe(document.documentElement,{childList:true,subtree:true});
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
  window.ESActionIcons={scan,decorate};
})();
