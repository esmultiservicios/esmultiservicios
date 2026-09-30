const header=document.querySelector('.site-header'),menuBtn=document.querySelector('.menu-btn'),nav=document.querySelector('.main-nav'),progress=document.querySelector('.scroll-progress span');
menuBtn?.addEventListener('click',()=> {
  const open=nav.classList.toggle('open');menuBtn.setAttribute('aria-expanded',String(open));
}
);
function scrollToTarget(hash) {
  const target=document.querySelector(hash);
  if(!target)return;
  const offset=(header?.offsetHeight||0)+8;
  const top=target.getBoundingClientRect().top+window.scrollY-offset;
  window.scrollTo( {
    top,behavior:'smooth'
  }
  );
}
document.querySelectorAll('[data-scroll][href^="#"]').forEach(link=>link.addEventListener('click',event=> {
  const hash=link.getAttribute('href');if(!hash||hash==='#')return;event.preventDefault();nav?.classList.remove('open');menuBtn?.setAttribute('aria-expanded','false');scrollToTarget(hash);history.replaceState(null,'',hash);
}
));
const sections=['home','about','services','gallery','areas','estimate','contact'].map(id=>document.getElementById(id)).filter(Boolean),navLinks=[...document.querySelectorAll('.main-nav a[href^="#"]')];
const spy=new IntersectionObserver(entries=> {
  const visible=entries.filter(e=>e.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];if(!visible)return;navLinks.forEach(a=>a.classList.toggle('active',a.getAttribute('href')==='#'+visible.target.id));
}
, {
  rootMargin:'-22% 0px -58% 0px',threshold:[0,.15,.35,.6]
}
);
sections.forEach(s=>spy.observe(s));
const revealObserver=new IntersectionObserver(entries=>entries.forEach(entry=> {
  if(entry.isIntersecting) {
    entry.target.classList.add('visible');revealObserver.unobserve(entry.target);
  }
}
), {
  threshold:.08
}
);
document.querySelectorAll('.reveal').forEach(el=>revealObserver.observe(el));
function updateProgress() {
  if(!progress)return;
  const max=document.documentElement.scrollHeight-window.innerHeight;
  progress.style.width=(max>0?Math.min(100,window.scrollY/max*100):0)+'%';
}
window.addEventListener('scroll',updateProgress, {
  passive:true
}
);
updateProgress();
const upload=document.querySelector('[data-public-upload]'),fileInput=upload?.querySelector('input[type=file]'),preview=upload?.querySelector('[data-public-upload-preview]');
let selected=[];
function syncPublicFiles() {
  if(!fileInput)return;
  const dt=new DataTransfer();
  selected.forEach(f=>dt.items.add(f));
  fileInput.files=dt.files;
  renderPublicFiles()
}
function renderPublicFiles() {
  if(!preview)return;
  preview.innerHTML='';
  selected.forEach((f,i)=> {
    const u=URL.createObjectURL(f),d=document.createElement('div');
    d.className='public-upload-item';
    d.innerHTML='<img alt="Selected project image"><button type="button" aria-label="Remove image">×</button>';
    d.querySelector('img').src=u;
    d.querySelector('button').addEventListener('click',e=> {
      e.stopPropagation();selected.splice(i,1);syncPublicFiles()
    }
    );preview.appendChild(d)
  }
  )
}
function addPublicFiles(list) {
  const imgs=[...list].filter(f=>/^image\/(jpeg|png|webp)$/.test(f.type));
  selected=[...selected,...imgs].slice(0,8);
  syncPublicFiles()
}
fileInput?.addEventListener('change',()=> {
  selected=[...fileInput.files].slice(0,8);syncPublicFiles()
}
);
upload?.addEventListener('click',e=> {
  if(e.target.closest('button'))return;if(e.target!==fileInput)fileInput.click()
}
);
upload?.addEventListener('dragover',e=> {
  e.preventDefault();upload.classList.add('dragover')
}
);
upload?.addEventListener('dragleave',()=>upload.classList.remove('dragover'));
upload?.addEventListener('drop',e=> {
  e.preventDefault();upload.classList.remove('dragover');addPublicFiles(e.dataTransfer.files)
}
);
upload?.addEventListener('paste',e=> {
  const files=[...e.clipboardData.items].filter(i=>i.kind==='file').map(i=>i.getAsFile()).filter(Boolean);if(files.length) {
    e.preventDefault();addPublicFiles(files)
  }
}
);
const light=document.querySelector('[data-site-lightbox]'),lightImg=document.querySelector('[data-site-lightbox-img]'),lightCap=document.querySelector('[data-site-lightbox-caption]');
document.querySelectorAll('[data-gallery-src]').forEach(b=>b.addEventListener('click',()=> {
  lightImg.src=b.dataset.gallerySrc||'';lightCap.textContent=b.dataset.galleryTitle||'';light.classList.add('open');light.setAttribute('aria-hidden','false')
}
));
function closeLight() {
  light?.classList.remove('open');
  light?.setAttribute('aria-hidden','true');
  if(lightImg)lightImg.src=''
}
document.querySelector('[data-site-lightbox-close]')?.addEventListener('click',closeLight);
light?.addEventListener('click',e=> {
  if(e.target===light)closeLight()
}
);
document.addEventListener('keydown',e=> {
  if(e.key==='Escape')closeLight()
}
);
const form=document.getElementById('estimateForm'),toast=document.getElementById('toast');
form?.addEventListener('submit',async e=> {
  e.preventDefault();const btn=form.querySelector('button[type="submit"]'),old=btn.innerHTML;btn.disabled=true;btn.innerHTML='<span class="public-action-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 20 18-8L3 4v6l12 2-12 2v6Z"/></svg></span>Sending...';try {
    const res=await fetch('estimate-submit.php', {
      method:'POST',body:new FormData(form)
    }
    ),data=await res.json();toast.textContent=data.message||'Request received.';toast.classList.add('show');if(data.ok) {
      form.reset();selected=[];renderPublicFiles()
    }
  } catch {
    toast.textContent='We could not send the request. Please contact us by phone or WhatsApp.';toast.classList.add('show')
  } finally {
    btn.disabled=false;btn.innerHTML=old;setTimeout(()=>toast.classList.remove('show'),5200)
  }
}
);
if(location.hash&&document.querySelector(location.hash))setTimeout(()=>scrollToTarget(location.hash),100);

/* Premium semantic icons for public CTAs */
(() => {
  const icons={
    contact:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm9 7 8-5H4l8 5Zm0 2.3L3 8.7V17h18V8.7l-9 5.6Z"/></svg>',
    whatsapp:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a9.5 9.5 0 0 0-8.2 14.3L2.5 21.5l5.3-1.3A9.5 9.5 0 1 0 12 2Zm0 17a7.4 7.4 0 0 1-3.8-1.1l-.4-.2-3 .7.8-2.9-.2-.4A7.5 7.5 0 1 1 12 19Zm4.1-5.6c-.2-.1-1.3-.7-1.5-.7-.2-.1-.4-.1-.6.1l-.7.9c-.2.2-.3.2-.5.1-1.5-.7-2.6-1.7-3.3-3.1-.2-.3 0-.4.1-.5l.5-.6c.1-.2.2-.4.1-.6l-.7-1.7c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5 0 1.4 1 2.8 1.2 3 .1.2 2 3.1 5 4.2 1.2.5 2.1.6 2.8.5.9-.1 1.3-.7 1.5-1.3.2-.6.2-1.1.1-1.2-.1-.2-.3-.3-.6-.4Z"/></svg>',
    site:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.9 9h-3a15 15 0 0 0-1.3-5A8 8 0 0 1 18.9 11ZM12 4c.8 1 1.6 3.2 1.9 7h-3.8C10.4 7.2 11.2 5 12 4ZM9.4 6A15 15 0 0 0 8.1 11h-3A8 8 0 0 1 9.4 6ZM5.1 13h3a15 15 0 0 0 1.3 5 8 8 0 0 1-4.3-5Zm6.9 7c-.8-1-1.6-3.2-1.9-7h3.8c-.3 3.8-1.1 6-1.9 7Zm2.6-2a15 15 0 0 0 1.3-5h3a8 8 0 0 1-4.3 5Z"/></svg>',
    support:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 0-9 9v5a3 3 0 0 0 3 3h2v-7H5v-1a7 7 0 0 1 14 0v1h-3v7h2a3 3 0 0 0 3-3v-5a9 9 0 0 0-9-9Z"/></svg>',
    send:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 20 18-8L3 4v6l12 2-12 2v6Z"/></svg>',
    arrow:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5-1.4 1.4 4.6 4.6H3v2h14.2l-4.6 4.6L14 19l7-7-7-7Z"/></svg>'
  };
  function choose(el){const t=(el.textContent||'').trim().toLowerCase();const h=(el.getAttribute('href')||'').toLowerCase();if(/whatsapp/.test(t+h))return'whatsapp';if(/visit|website|sitio|project|proyecto/.test(t))return'site';if(/support|soporte/.test(t))return'support';if(/contact|inform|demo|cotiz|request|solicitar|hablemos|tell us/.test(t))return'contact';if(el.matches('button[type="submit"]'))return'send';return'arrow';}
  function decorate(){document.querySelectorAll('.btn, a.btn, button.btn, .solution-action, .project-action').forEach(el=>{if(el.querySelector('.public-action-icon')||el.dataset.noActionIcon==='1')return;const txt=(el.textContent||'').trim();if(!txt)return;const s=document.createElement('span');s.className='public-action-icon';s.innerHTML=icons[choose(el)];el.prepend(s);el.classList.add('has-action-icon');});}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',decorate,{once:true});else decorate();
})();
