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
  e.preventDefault();const btn=form.querySelector('button[type="submit"]'),old=btn.textContent;btn.disabled=true;btn.textContent='Sending...';try {
    const res=await fetch('estimate-submit.php', {
      method:'POST',body:new FormData(form)
    }
    ),data=await res.json();toast.textContent=data.message||'Request received.';toast.classList.add('show');if(data.ok) {
      form.reset();selected=[];renderPublicFiles()
    }
  } catch {
    toast.textContent='We could not send the request. Please contact us by phone or WhatsApp.';toast.classList.add('show')
  } finally {
    btn.disabled=false;btn.textContent=old;setTimeout(()=>toast.classList.remove('show'),5200)
  }
}
);
if(location.hash&&document.querySelector(location.hash))setTimeout(()=>scrollToTarget(location.hash),100);
