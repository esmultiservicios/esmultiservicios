(function(w,d){
  const labels={success:'Correcto',error:'Error',danger:'Error',info:'Información',warning:'Atención'};
  const icons={success:'✓',error:'×',danger:'×',info:'i',warning:'!'};
  function stack(){let el=d.querySelector('.notify-stack');if(!el){el=d.createElement('div');el.className='notify-stack';el.setAttribute('aria-live','polite');el.setAttribute('aria-atomic','false');d.body.appendChild(el)}return el}
  function remove(item){if(!item||item.dataset.closing)return;item.dataset.closing='1';item.classList.add('is-leaving');setTimeout(()=>item.remove(),190)}
  w.showNotify=function(type,message,options){
    if(typeof message==='undefined'){message=type;type='info'}
    type=String(type||'info').toLowerCase();if(!labels[type])type='info';options=options||{};
    const item=d.createElement('div');item.className='notify-item '+type;item.setAttribute('role',(type==='error'||type==='danger')?'alert':'status');
    const icon=d.createElement('div');icon.className='notify-icon';icon.textContent=icons[type];
    const copy=d.createElement('div');copy.className='notify-copy';
    const title=d.createElement('div');title.className='notify-title';title.textContent=options.title||labels[type];
    const msg=d.createElement('p');msg.className='notify-message';msg.textContent=String(message??'');
    const close=d.createElement('button');close.type='button';close.className='notify-close';close.setAttribute('aria-label','Cerrar notificación');close.textContent='×';close.addEventListener('click',()=>remove(item));
    copy.append(title,msg);item.append(icon,copy,close);stack().appendChild(item);
    const duration=Number(options.duration??4200);if(duration>0)setTimeout(()=>remove(item),duration);
    return item;
  };
})(window,document);
