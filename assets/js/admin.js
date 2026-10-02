(()=>{
  'use strict';
  const d=document,$=(s,r=d)=>r.querySelector(s),$$=(s,r=d)=>[...r.querySelectorAll(s)];
  const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const formMethod=form=>String(form.getAttribute('method')||'get').toLowerCase();
  const formTarget=form=>{const raw=String(form.getAttribute('action')||'').trim();return raw?new URL(raw,location.href).href:location.href};
  let activeModal=null,globalTimer=null,globalController=null,navigating=false;

  const closeSidebar=()=>{$('#adminSidebar')?.classList.remove('open');$('[data-sidebar-backdrop]')?.classList.remove('show')};
  const closeUserMenu=()=>{$('[data-user-menu]')?.classList.remove('open')};
  const firstField=(root=d)=>root.querySelector('[autofocus]:not([disabled]):not([readonly]):not([type=search]):not([data-global-search-input]):not([data-list-search]),input:not([type=hidden]):not([type=file]):not([type=search]):not([disabled]):not([readonly]):not([data-global-search-input]):not([data-list-search]),textarea:not([disabled]):not([readonly]),select:not([disabled]):not([data-list-size])');
  const focusFirstField=(root=d,delay=70)=>setTimeout(()=>{const el=firstField(root);if(!el)return;try{el.focus({preventScroll:true})}catch{el.focus()}if(el.matches('input[type=text],input[type=email],input[type=number],input[type=password],input:not([type]),textarea')&&typeof el.setSelectionRange==='function'){const n=(el.value||'').length;try{el.setSelectionRange(n,n)}catch{}}},delay);
  const focusInvalidField=form=>{const invalid=form.querySelector(':invalid');if(!invalid)return;invalid.classList.add('field-invalid');const zone=invalid.closest?.('[data-file-drop]');if(zone){zone.classList.add('validation-error');zone.scrollIntoView({behavior:'smooth',block:'center'});try{zone.focus({preventScroll:true})}catch{zone.focus()}return}try{invalid.focus({preventScroll:true})}catch{invalid.focus()}invalid.scrollIntoView({behavior:'smooth',block:'center'})};
  const syncSelect=el=>{if(!el)return;el.dispatchEvent(new Event('change',{bubbles:true}))};
  const setSelectValue=(el,value)=>{if(!el)return;value=String(value??'');if(value&&![...el.options].some(o=>o.value===value)){const option=new Option(value+' · Actual',value,true,true);el.add(option)}el.value=value;syncSelect(el)};
  const syncSelects=root=>$$('select',root||d).forEach(syncSelect);
  const openModal=id=>{const modal=d.getElementById(id);if(!modal)return;modal.classList.add('open');modal.setAttribute('aria-hidden','false');d.body.style.overflow='hidden';activeModal=modal;focusFirstField(modal,70)};
  const closeModal=modal=>{if(!modal)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');if(activeModal===modal)activeModal=null;if(!$('.ui-modal.open'))d.body.style.overflow=''};
  const resultIcon=type=>type==='Empleado'?'EM':(type==='Pregunta'?'PR':'US');
  function initNumericBadges(root=d){
    $$('input[name="badge"]',root).forEach(input=>{
      if(input.dataset.numericBadgeReady)return;input.dataset.numericBadgeReady='1';
      input.setAttribute('inputmode','numeric');input.setAttribute('pattern','[0-9]+');
      input.addEventListener('input',()=>{const clean=input.value.replace(/\D+/g,'');if(input.value!==clean){input.value=clean;showNotify('warning','El gafete solo puede contener números.')}});
      input.addEventListener('paste',e=>{const text=(e.clipboardData||window.clipboardData)?.getData('text')||'';if(text&&!/^\d+$/.test(text.trim())){e.preventDefault();showNotify('warning','El gafete solo puede contener números.')}});
    });
  }

  function parseNotify(html){
    const m=html.match(/window\.PAGE_NOTIFY=(\{.*?\}|null);<\/script>/s);if(!m||m[1]==='null')return null;
    try{return JSON.parse(m[1])}catch{return null}
  }

  function initMail(root=d){
    const radios=$$('input[name="method"]',root);if(!radios.length)return;
    const sync=()=>{const method=$('input[name="method"]:checked',root)?.value||'SMTP';$$('.mail-group',root).forEach(x=>x.classList.toggle('hidden',x.dataset.mail!==method));};
    radios.forEach(r=>{if(r.dataset.mailReady)return;r.dataset.mailReady='1';r.addEventListener('change',sync)});sync();
  }

  function initLists(root=d){
    $$('[data-list]',root).forEach(list=>{
      if(list.dataset.listReady)return;list.dataset.listReady='1';
      const records=$$('[data-record]',list),search=$('[data-list-search]',list),clear=$('[data-list-clear]',list),sizeSelect=$('[data-list-size]',list),items=$('[data-list-items]',list),pager=$('[data-list-pagination]',list),visibleCount=$('[data-list-visible]',list),empty=$('[data-list-empty]',list),viewButtons=$$('[data-list-view]',list);
      const toolbar=$('[data-list-toolbar]',list),listKey=toolbar?.dataset.viewKey||list.dataset.list||'records';let storedView=toolbar?.dataset.defaultView||'mini';if(matchMedia('(max-width:767px)').matches)storedView='mini';if(!['mini','detail'].includes(storedView))storedView='mini';
      let page=1,size=sizeSelect?.value==='all'?Infinity:(parseInt(sizeSelect?.value||'12',10)||12),view=storedView;
      const persistView=nextView=>{const fd=new FormData();fd.set('csrf',window.CSRF||'');fd.set('action','save_view_preference');fd.set('list_key',listKey);fd.set('view',nextView);fetch(location.href,{method:'POST',headers:{'X-Requested-With':'soft-navigation','Accept':'application/json'},body:fd,keepalive:true}).then(async r=>{if(!r.ok){let msg='No se pudo guardar la vista.';try{const j=await r.json();if(j?.message)msg=j.message}catch{}showNotify('warning',msg)}}).catch(()=>showNotify('warning','No se pudo guardar la preferencia de vista.'));};
      const matches=()=>{const q=(search?.value||'').trim().toLowerCase();return records.filter(r=>!q||(r.dataset.search||r.textContent||'').toLowerCase().includes(q));};
      const buttons=pages=>{if(!pager)return;pager.innerHTML='';if(pages<=1)return;const make=(label,target,disabled=false,active=false)=>{const b=d.createElement('button');b.type='button';b.className='page-btn'+(active?' active':'');b.textContent=label;b.disabled=disabled;b.addEventListener('click',()=>{page=target;render();list.scrollIntoView({behavior:'smooth',block:'start'})});pager.appendChild(b)};make('‹',Math.max(1,page-1),page===1);const slots=[];for(let i=1;i<=pages;i++){if(i===1||i===pages||Math.abs(i-page)<=1)slots.push(i);else if(slots[slots.length-1]!=='…')slots.push('…')}slots.forEach(x=>{if(x==='…'){const s=d.createElement('span');s.className='page-ellipsis';s.textContent='…';pager.appendChild(s)}else make(String(x),x,false,x===page)});make('›',Math.min(pages,page+1),page===pages)};
      const render=()=>{const filtered=matches(),all=size===Infinity,pages=all?1:Math.max(1,Math.ceil(filtered.length/size));if(page>pages)page=pages;records.forEach(r=>r.classList.add('hidden'));const visible=all?filtered:filtered.slice((page-1)*size,page*size);visible.forEach(r=>r.classList.remove('hidden'));if(visibleCount)visibleCount.textContent=String(filtered.length);empty?.classList.toggle('show',filtered.length===0);if(search)search.closest('.list-search')?.classList.toggle('has-value',search.value.length>0);buttons(pages);items?.classList.toggle('view-detail',view==='detail');items?.classList.toggle('view-mini',view==='mini');viewButtons.forEach(b=>b.classList.toggle('active',b.dataset.listView===view));};
      search?.addEventListener('input',()=>{page=1;render()});clear?.addEventListener('click',()=>{search.value='';page=1;search.focus();render()});sizeSelect?.addEventListener('change',()=>{size=sizeSelect.value==='all'?Infinity:(parseInt(sizeSelect.value,10)||12);page=1;render()});list._applyListView=next=>{if(matchMedia('(max-width:767px)').matches)next='mini';if(!['mini','detail'].includes(next))next='mini';view=next;render();};viewButtons.forEach(b=>b.addEventListener('click',()=>{const next=b.dataset.listView||'mini';if(matchMedia('(max-width:767px)').matches&&next==='detail')return;$$('[data-list]').forEach(other=>{const otherToolbar=$('[data-list-toolbar]',other),otherKey=otherToolbar?.dataset.viewKey||other.dataset.list||'records';if(otherKey===listKey&&typeof other._applyListView==='function')other._applyListView(next);});persistView(next);}));render();
    });
  }

  function initDropZones(root=d){
    $$('[data-file-drop]',root).forEach(zone=>{
      if(zone.dataset.dropReady)return;zone.dataset.dropReady='1';
      const input=$('input[type=file]',zone),nameEl=$('[data-file-name]',zone),selectBtn=$('[data-file-select]',zone),previewId=zone.dataset.previewTarget,preview=previewId?d.getElementById(previewId):null;
      const update=()=>{const f=input?.files?.[0];if(!f)return;if(nameEl)nameEl.textContent=`${f.name} · ${(f.size/1024/1024).toFixed(f.size>1024*1024?2:3)} MB`;zone.classList.add('has-file');if(preview&&f.type.startsWith('image/')){const reader=new FileReader();reader.onload=()=>{preview.src=String(reader.result);preview.classList.remove('hidden')};reader.readAsDataURL(f)}};
      const setFiles=files=>{if(!input||!files?.length)return;const accept=(zone.dataset.accept||'').toLowerCase(),file=files[0];if(accept&&accept!=='image/*'){const ext='.'+(file.name.split('.').pop()||'').toLowerCase();if(!accept.split(',').map(x=>x.trim()).includes(ext)){showNotify('warning','El archivo no corresponde a un formato permitido.');return}}else if(accept==='image/*'&&!file.type.startsWith('image/')){showNotify('warning','Pega o selecciona una imagen válida.');return}try{const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;update()}catch{showNotify('info','El navegador no permitió pegar el archivo. Usa Seleccionar archivo.')}};
      input?.addEventListener('change',update);selectBtn?.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();input?.click()});['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.add('dragover')}));['dragleave','drop'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.remove('dragover')}));zone.addEventListener('drop',e=>setFiles(e.dataTransfer?.files));zone.addEventListener('paste',e=>{const files=[...(e.clipboardData?.files||[])];if(files.length){e.preventDefault();setFiles(files)}});zone.addEventListener('keydown',e=>{if((e.key==='Enter'||e.key===' ')&&e.target===zone){e.preventDefault();input?.click()}});
    });
  }

  function initCounters(root=d){
    $$('[data-counter]',root).forEach(el=>{if(el.dataset.counterReady)return;el.dataset.counterReady='1';const text=el.textContent.trim(),m=text.match(/^([0-9]+(?:[.,][0-9]+)?)(.*)$/);if(!m)return;const target=parseFloat(m[1].replace(',','.'));if(!Number.isFinite(target)||target>100000)return;const suffix=m[2],decimals=(m[1].split(/[.,]/)[1]||'').length,start=performance.now(),dur=620;const tick=now=>{const p=Math.min(1,(now-start)/dur),v=target*(1-Math.pow(1-p,3));el.textContent=v.toFixed(decimals)+suffix;if(p<1)requestAnimationFrame(tick)};requestAnimationFrame(tick)});
  }

  function initGameRules(root=d){
    $$('[data-game-rules]',root).forEach(box=>{
      if(box.dataset.gameRulesReady)return;box.dataset.gameRulesReady='1';
      const active=Math.max(0,parseInt(box.dataset.activeGames||'0',10)||0),get=n=>box.querySelector(`[name="${n}"]`),checked=n=>box.querySelector(`[name="${n}"]:checked`)?.value||'';
      const count=get('games_per_portal'),required=get('games_required_completion'),countField=$('[data-game-count-field]',box),requiredField=$('[data-game-required-field]',box),summary=$('[data-game-rules-text]',box);
      const bounded=(input,max,normalize=false)=>{if(!input)return 1;const limit=Math.max(1,max);input.max=String(limit);const raw=String(input.value??'').trim();if(raw==='')return 1;let v=parseInt(raw,10);if(!Number.isFinite(v))v=1;const safe=Math.max(1,Math.min(limit,v));if(normalize)input.value=String(safe);return safe};
      const sync=(normalize=false)=>{
        const display=checked('game_display_mode')||'all',order=checked('game_order_mode')||'sequential',nav=checked('game_navigation_mode')||'free',completion=checked('game_completion_mode')||'all';
        const limited=display==='limited';if(count){count.disabled=!limited||active<1;count.required=limited&&active>0}countField?.classList.toggle('is-disabled',!limited);
        const chosen=limited?bounded(count,active,normalize):active,effective=Math.max(0,Math.min(active,chosen));
        const minimum=completion==='minimum';if(required){required.disabled=!minimum||effective<1;required.required=minimum&&effective>0}requiredField?.classList.toggle('is-disabled',!minimum);const need=minimum?bounded(required,effective,normalize):effective;
        if(summary){const visible=display==='all'?`los ${active} juegos publicados`:`${effective} de ${active} juegos publicados`;const ordering=order==='random'?'una asignación aleatoria estable por participante':'el orden configurado por el administrador';const navigation=nav==='guided'?'recorrido guiado, desbloqueando un reto a la vez':'navegación libre entre sus retos';const goal=completion==='all'?`completar los ${effective} asignados`:`completar al menos ${need} de ${effective}`;summary.textContent=active?`Cada participante recibirá ${visible}, con ${ordering}, ${navigation} y deberá ${goal}. La asignación queda fija cuando inicia para evitar cambios al recargar.`:'No hay juegos publicados. Publica al menos uno para activar estas reglas.'}
      };
      $$('input[type=radio]',box).forEach(el=>el.addEventListener('change',()=>sync(true)));
      $$('input[type=number]',box).forEach(el=>{el.addEventListener('input',()=>sync(false));el.addEventListener('change',()=>sync(true));el.addEventListener('blur',()=>sync(true));el.addEventListener('focus',()=>{requestAnimationFrame(()=>{try{el.select()}catch{}})})});
      box.addEventListener('submit',()=>sync(true));sync(true);
    });
  }


  function initQuestionForm(root=d){
    const form=$('#questionForm',root);if(!form||form.dataset.questionReady)return;form.dataset.questionReady='1';
    const type=form.elements.question_type,wrap=$('[data-required-selections-wrap]',form),required=form.elements.required_selections,checks=$$('input[data-correct-option]',form),help=$('[data-question-correct-help]',form);
    const sync=()=>{const multiple=type?.value==='multiple';wrap?.classList.toggle('hidden',!multiple);if(required){required.disabled=!multiple;if(!multiple)required.value='1';else if(Number(required.value)<2)required.value='2'}checks.forEach(c=>{c.type='checkbox'});if(help)help.lastChild.textContent=multiple?' Marca exactamente '+Number(required?.value||2)+' respuestas correctas.':' Marca una sola respuesta correcta.'};
    type?.addEventListener('change',()=>{if(type.value==='single'){let kept=false;checks.forEach(c=>{if(c.checked&&!kept)kept=true;else c.checked=false})}sync()});
    required?.addEventListener('input',sync);
    checks.forEach(c=>c.addEventListener('change',()=>{if(type?.value!=='multiple'&&c.checked)checks.forEach(other=>{if(other!==c)other.checked=false});sync()}));
    sync();
  }

  function initPage(root=d){
    $$('.ui-modal',root).forEach(modal=>{if(modal.parentElement!==d.body){modal.dataset.adminDynamic='1';d.body.appendChild(modal)}});
    initMail(root);initLists(root);initDropZones(root);initCounters(root);initGameRules(root);initQuestionForm(root);window.UI?.enhanceSelects(root);window.UI?.enhancePasswords(root);window.EvalFullscreen?.sync();
    const pageForm=$('.content-wrap > form, .content-wrap .email-layout form',root);if(pageForm&&!$('.ui-modal.open',root))focusFirstField(pageForm,110);
  }

  async function applyAdminHtml(html,url,{push=true}={}){
    const doc=new DOMParser().parseFromString(html,'text/html'),incoming=doc.querySelector('.admin-shell');if(!incoming)throw new Error('La respuesta no contiene el panel administrativo.');
    $$('body > .ui-modal[data-admin-dynamic="1"]').forEach(m=>m.remove());activeModal=null;d.body.style.overflow='';
    const current=$('.admin-shell');current.replaceWith(incoming);d.title=doc.title||d.title;
    if(push)history.pushState({adminSoft:true},'',url);else history.replaceState({adminSoft:true},'',url);
    closeSidebar();closeUserMenu();initPage(d);window.scrollTo({top:0,behavior:'auto'});
    const notify=parseNotify(html);if(notify?.message)setTimeout(()=>showNotify(notify.type||'info',notify.message),80);
  }

  async function softNavigate(url,{push=true}={}){
    if(navigating)return;navigating=true;d.body.classList.add('admin-loading');
    try{const r=await fetch(url,{headers:{'X-Requested-With':'soft-navigation'}});if(!r.ok)throw new Error('No se pudo abrir la sección.');const html=await r.text();await applyAdminHtml(html,r.url,{push});}
    catch(err){showNotify('error',err.message||'No se pudo cargar la sección.');if(push)window.location.href=url;}
    finally{navigating=false;d.body.classList.remove('admin-loading')}
  }

  async function softSubmit(form,submitter=null){
    if(navigating){showNotify('info','Espera un momento: todavía se está procesando la acción anterior.');return}
    if(!form.checkValidity()){focusInvalidField(form);showNotify('danger','Completa los campos obligatorios marcados con * antes de continuar.');return}
    const actionName=String(form.elements?.action?.value||'');
    const keepEmployeeModal=actionName==='save_employee';
    const employeeSnapshot=keepEmployeeModal?{
      id:String(form.elements.id?.value||''),badge:String(form.elements.badge?.value||''),name:String(form.elements.name?.value||''),department:String(form.elements.department?.value||''),content_group:String(form.elements.content_group?.value||''),email:String(form.elements.email?.value||''),status:String(form.elements.status?.value||'active')
    }:null;
    navigating=true;d.body.classList.add('admin-loading');
    submitter=submitter||form.querySelector('button[type=submit],input[type=submit]');if(submitter){submitter.disabled=true;submitter.classList.add('is-processing')}
    try{
      const r=await fetch(formTarget(form),{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'soft-navigation','Accept':'application/json'}});
      const contentType=(r.headers.get('content-type')||'').toLowerCase();
      if(contentType.includes('application/json')){
        const j=await r.json();
        if(!j.ok){showNotify(j.type||'danger',j.message||'No se pudo completar la acción.');focusInvalidField(form);return}
        const next=new URL(j.redirect||location.href,location.href).href;
        const pageResponse=await fetch(next,{headers:{'X-Requested-With':'soft-navigation'}});
        if(!pageResponse.ok)throw new Error('La información se guardó, pero no se pudo actualizar la vista.');
        const html=await pageResponse.text();await applyAdminHtml(html,pageResponse.url,{push:true});
        if(keepEmployeeModal){
          if(employeeSnapshot?.id){
            const refreshed=$('#employeeForm');
            if(refreshed){
              refreshed.elements.id.value=employeeSnapshot.id;
              refreshed.elements.badge.value=employeeSnapshot.badge;
              refreshed.elements.name.value=employeeSnapshot.name;
              refreshed.elements.department.value=employeeSnapshot.department;
              if(refreshed.elements.content_group)setSelectValue(refreshed.elements.content_group,employeeSnapshot.content_group||'')
              refreshed.elements.email.value=employeeSnapshot.email;
              refreshed.elements.status.value=employeeSnapshot.status||'active';
              syncSelect(refreshed.elements.status);
              $('[data-employee-modal-title]')?.replaceChildren(d.createTextNode('Editar empleado'));
            }
          }else resetEmployee();
          openModal('employeeModal');
        }
        showNotify(j.type||'success',j.message||'Cambios guardados correctamente.');return
      }
      if(!r.ok)throw new Error('No se pudo guardar la información.');
      const html=await r.text();await applyAdminHtml(html,r.url,{push:true});
    }
    catch(err){showNotify('danger',err.message||'No se pudo completar la acción.');}
    finally{navigating=false;d.body.classList.remove('admin-loading');if(submitter){submitter.disabled=false;submitter.classList.remove('is-processing')}}
  }

  async function confirmForm(form){
    if(form.dataset.confirmed==='1')return true;
    const word=(form.dataset.confirmWord||'').trim();const opts={icon:'warning',title:form.dataset.confirmTitle||'Confirmar acción',text:form.dataset.confirmText||'¿Deseas continuar?',showCancelButton:true,confirmButtonText:'Sí, continuar',cancelButtonText:'Cancelar',confirmButtonIcon:'check',cancelButtonIcon:'x',showCloseButton:true,allowOutsideClick:false};if(word){opts.input='text';opts.inputLabel='Para confirmar, escribe '+word;opts.inputPlaceholder=word;opts.preConfirm=v=>{if(String(v||'').trim().toUpperCase()!==word.toUpperCase()){Swal.showValidationMessage('Debes escribir '+word+' exactamente.');return false}return true}}const result=await Swal.fire(opts);
    return result.isConfirmed;
  }

  function resetEmployee(){const form=$('#employeeForm');if(!form)return;form.reset();form.elements.id.value='';if(form.elements.content_group)form.elements.content_group.value='';if(form.elements.status)form.elements.status.value='active';syncSelects(form);$('[data-employee-modal-title]')?.replaceChildren(d.createTextNode('Nuevo empleado'))}
  function editEmployee(btn){const form=$('#employeeForm');if(!form)return;const x=JSON.parse(btn.dataset.editEmployee||'{}');form.elements.id.value=x.id||'';form.elements.badge.value=x.badge||'';form.elements.name.value=x.name||'';form.elements.department.value=x.department||'';if(form.elements.content_group)setSelectValue(form.elements.content_group,x.question_group||x.game_group||'');form.elements.email.value=x.email||'';form.elements.status.value=x.status||'active';syncSelects(form);$('[data-employee-modal-title]')?.replaceChildren(d.createTextNode('Editar empleado'));openModal('employeeModal')}
  function resetUser(){const form=$('#userForm');if(!form)return;form.reset();form.elements.id.value='';form.elements.password.required=true;$$('.new-only',form).forEach(x=>x.classList.remove('hidden'));$('[data-password-help]',form).textContent='Mínimo 8 caracteres.';$('[data-user-modal-title]')?.replaceChildren(d.createTextNode('Nuevo usuario'));syncSelects(form)}
  function editUser(btn){const form=$('#userForm');if(!form)return;const x=JSON.parse(btn.dataset.editUser||'{}');form.reset();form.elements.id.value=x.id||'';form.elements.name.value=x.name||'';form.elements.email.value=x.email||'';form.elements.role.value=x.role||'manager';form.elements.status.value=x.status||'active';form.elements.password.value='';form.elements.password.required=false;$$('.new-only',form).forEach(el=>el.classList.add('hidden'));$('[data-password-help]',form).textContent='Déjala vacía para conservar la contraseña actual.';syncSelects(form);$('[data-user-modal-title]')?.replaceChildren(d.createTextNode('Editar usuario'));openModal('userModal')}
  function resetQuestion(){const form=$('#questionForm');if(!form)return;form.reset();form.elements.id.value='';form.elements.points.value='1';form.elements.time_limit_seconds.value='0';if(form.elements.group_name)form.elements.group_name.value='';form.elements.question_type.value='single';form.elements.required_selections.value='1';form.elements.active.checked=true;$$('input[data-correct-option]',form).forEach(r=>r.checked=false);$$('input[name="options[]"]',form).forEach((o,i)=>{o.value='';o.placeholder='Opción '+(i+1)});syncSelects(form);$('[data-question-modal-title]')?.replaceChildren(d.createTextNode('Nueva pregunta'))}
  function editQuestion(btn){const form=$('#questionForm');if(!form)return;const x=JSON.parse(btn.dataset.editQuestion||'{}');resetQuestion();form.elements.id.value=x.id||'';form.elements.question_text.value=x.text||'';form.elements.points.value=x.points||1;form.elements.time_limit_seconds.value=x.time_limit_seconds||0;if(form.elements.group_name)setSelectValue(form.elements.group_name,x.group_name||'');form.elements.question_type.value=x.question_type||'single';form.elements.required_selections.value=x.required_selections||1;form.elements.active.checked=!!Number(x.active);const opts=$$('input[name="options[]"]',form),checks=$$('input[data-correct-option]',form);(x.options||[]).forEach((o,i)=>{if(opts[i])opts[i].value=o.option_text||''});(x.correct_indices||[x.correct_index??0]).forEach(i=>{if(checks[Number(i)])checks[Number(i)].checked=true});syncSelects(form);$('[data-question-modal-title]')?.replaceChildren(d.createTextNode('Editar pregunta'));openModal('questionModal')}
  function previewQuestion(btn){const x=JSON.parse(btn.dataset.previewQuestion||'{}'),root=$('[data-preview-content]'),corrects=(x.correct_indices||[x.correct_index??0]).map(Number);if(root)root.innerHTML=`<div class="preview-shell"><span class="preview-number">PREGUNTA ${esc(x.id||'')} · ${x.question_type==='multiple'?'SELECCIÓN MÚLTIPLE':'UNA RESPUESTA'}${Number(x.time_limit_seconds||0)>0?' · '+Number(x.time_limit_seconds)+' s':''}</span><h3>${esc(x.text||'')}</h3><div>${(x.options||[]).map((o,i)=>`<div class="preview-option ${corrects.includes(i)?'correct':''}"><i></i><span>${esc(o.option_text||'')}</span>${corrects.includes(i)?'<small class="preview-correct">Correcta</small>':''}</div>`).join('')}</div></div>`;openModal('questionPreviewModal')}
  function resetGame(){const form=$('#gameForm');if(!form)return;form.reset();form.querySelector('[name=id]').value='';form.querySelector('[name=active]').checked=true;form.querySelector('[name=sound_enabled]').checked=true;if(form.elements.time_limit_seconds)form.elements.time_limit_seconds.value='0';if(form.elements.category)form.elements.category.value='';if(form.elements.group_name)form.elements.group_name.value='';syncSelects(form);$('[data-game-modal-title]').textContent='Nuevo juego'}
  function editGame(btn){const form=$('#gameForm');if(!form)return;const g=JSON.parse(btn.dataset.editGame||'{}');Object.entries(g).forEach(([k,v])=>{const el=form.elements[k];if(!el)return;if(el.type==='checkbox')el.checked=String(v)==='1';else if(el.tagName==='SELECT')setSelectValue(el,v??'');else el.value=v??'';});if(form.elements.group_name)form.elements.group_name.value=form.elements.category?.value||g.group_name||'';syncSelects(form);$('[data-game-modal-title]').textContent='Editar juego';openModal('gameModal')}

  const clearActionMenuContext=()=>{$$('.action-menu-open-context').forEach(el=>el.classList.remove('action-menu-open-context'));$$('.record-action-menu.open-up').forEach(el=>el.classList.remove('open-up'));};
  const positionActionMenu=menu=>{if(!menu?.open)return;clearActionMenuContext();menu.closest('.record-card,.game-admin-card')?.classList.add('action-menu-open-context');menu.closest('[data-list-items]')?.classList.add('action-menu-open-context');menu.closest('.panel')?.classList.add('action-menu-open-context');requestAnimationFrame(()=>{const pop=$('.record-action-popover',menu);if(!pop)return;menu.classList.remove('open-up');const r=pop.getBoundingClientRect(),summary=menu.querySelector('summary')?.getBoundingClientRect();if(r.bottom>window.innerHeight-12&&summary&&summary.top-r.height>12)menu.classList.add('open-up');});};

  d.addEventListener('click',async e=>{
    const menuToggle=e.target.closest('[data-sidebar-toggle]');if(menuToggle){$('#adminSidebar')?.classList.toggle('open');$('[data-sidebar-backdrop]')?.classList.toggle('show');return}
    if(e.target.closest('[data-sidebar-backdrop]')){closeSidebar();return}
    const userTrigger=e.target.closest('[data-user-trigger]');if(userTrigger){e.stopPropagation();$('[data-user-menu]')?.classList.toggle('open');return}
    const logout=e.target.closest('.logout-link');if(logout){e.preventDefault();closeUserMenu();const result=await Swal.fire({icon:'warning',title:'¿Cerrar sesión?',text:'Tu sesión administrativa se cerrará de forma segura.',showCancelButton:true,confirmButtonText:'Sí, cerrar sesión',cancelButtonText:'Cancelar',confirmButtonIcon:'logout',cancelButtonIcon:'x',showCloseButton:true,allowOutsideClick:false});if(result.isConfirmed)window.location.href=logout.href;return}
    const eg=e.target.closest('[data-edit-group]');if(eg){const f=$('#groupForm'),x=JSON.parse(eg.dataset.editGroup||'{}');if(f){f.elements.id.value=x.id||'';f.elements.name.value=x.name||'';f.elements.description.value=x.description||'';f.elements.active.checked=!!Number(x.active);$('[data-group-modal-title]')?.replaceChildren(d.createTextNode('Editar categoría'));openModal('groupModal');}return;}
    const modalOpen=e.target.closest('[data-modal-open]');if(modalOpen){const id=modalOpen.dataset.modalOpen;if(modalOpen.matches('[data-new-employee]'))resetEmployee();if(modalOpen.matches('[data-new-user]'))resetUser();if(modalOpen.matches('[data-new-question]'))resetQuestion();if(modalOpen.matches('[data-new-game]'))resetGame();if(modalOpen.matches('[data-new-group]')){const f=$('#groupForm');if(f){f.reset();f.elements.id.value='';f.elements.active.checked=true;$('[data-group-modal-title]')?.replaceChildren(d.createTextNode('Nueva categoría'));}}openModal(id);return}
    const modalClose=e.target.closest('[data-modal-close]');if(modalClose){closeModal(modalClose.closest('.ui-modal'));return}
    const editEmp=e.target.closest('[data-edit-employee]');if(editEmp){editEmployee(editEmp);return}
    const editUsr=e.target.closest('[data-edit-user]');if(editUsr){editUser(editUsr);return}
    const editQ=e.target.closest('[data-edit-question]');if(editQ){editQuestion(editQ);return}
    const previewQ=e.target.closest('[data-preview-question]');if(previewQ){previewQuestion(previewQ);return}
    const editG=e.target.closest('[data-edit-game]');if(editG){editGame(editG);return}
    const clear=e.target.closest('[data-global-search-clear]');if(clear){const input=$('[data-global-search-input]');if(input){input.value='';input.dispatchEvent(new Event('input',{bubbles:true}));input.focus()}return}
    const adminLink=e.target.closest('a');if(adminLink&&adminLink.target!=='_blank'&&!adminLink.hasAttribute('download')){
      const href=adminLink.getAttribute('href')||'';
      if(href.startsWith('?page=')&&!href.includes('download=')&&!href.includes('export=')&&!href.includes('page=logout')){e.preventDefault();closeUserMenu();closeSidebar();softNavigate(adminLink.href,{push:true});return}
    }
    const userMenu=$('[data-user-menu]');if(userMenu&&!userMenu.contains(e.target))closeUserMenu();
    if(!e.target.closest('.record-action-menu')){$$('.record-action-menu[open]').forEach(m=>m.open=false);clearActionMenuContext();}
  });

  d.addEventListener('input',e=>{
    if(e.target.classList?.contains('field-invalid')&&e.target.checkValidity())e.target.classList.remove('field-invalid');
    if(!e.target.matches('[data-global-search-input]'))return;const input=e.target,global=input.closest('[data-global-search]'),results=$('[data-global-search-results]',global),q=input.value.trim();global?.classList.toggle('has-value',q.length>0);if(q.length<2){global?.classList.remove('open');if(results)results.innerHTML='';return}clearTimeout(globalTimer);globalTimer=setTimeout(async()=>{try{globalController?.abort();globalController=new AbortController();const r=await fetch('?ajax=global_search&q='+encodeURIComponent(q),{signal:globalController.signal,headers:{'X-Requested-With':'fetch'}}),j=await r.json();if(!r.ok)throw new Error(j.message||'No se pudo buscar');results.innerHTML=(j.results||[]).length?(j.results||[]).map(x=>`<a class="global-result" href="${esc(x.url)}"><span class="global-result-icon">${resultIcon(x.type)}</span><span><strong>${esc(x.title)}</strong><small>${esc(x.type)} · ${esc(x.meta)}</small></span></a>`).join(''):'<div class="global-empty">No encontramos coincidencias.</div>';global?.classList.add('open')}catch(err){if(err.name!=='AbortError'){results.innerHTML='<div class="global-empty">No se pudo completar la búsqueda.</div>';global?.classList.add('open')}}},260);
  });

  d.addEventListener('change',e=>{if(e.target.matches('input[type=file]')){const zone=e.target.closest('[data-file-drop]');if(e.target.files?.length){e.target.classList.remove('field-invalid');zone?.classList.remove('validation-error')}}if(e.target.matches('.premium-switch input')){const b=e.target.closest('.premium-switch')?.querySelector('b');if(b)b.textContent=e.target.checked?'Activa':'Inactiva'}});
  d.addEventListener('keydown',e=>{if(e.key==='Escape'){if(activeModal)closeModal(activeModal);$$('.record-action-menu[open]').forEach(m=>m.open=false);clearActionMenuContext()}});
  d.addEventListener('toggle',e=>{const menu=e.target.closest?.('.record-action-menu');if(!menu)return;if(menu.open){$$('.record-action-menu[open]').forEach(other=>{if(other!==menu)other.open=false});positionActionMenu(menu)}else if(!$('.record-action-menu[open]'))clearActionMenuContext()},true);

  let lastValidationNotice=0;
  d.addEventListener('invalid',e=>{
    const form=e.target?.form;if(!form||formMethod(form)!=='post')return;e.preventDefault();
    const now=Date.now();if(now-lastValidationNotice>500){lastValidationNotice=now;showNotify('danger','Completa los campos obligatorios marcados con * antes de continuar.')}
    setTimeout(()=>focusInvalidField(form),20);
  },true);

  d.addEventListener('submit',async e=>{
    const form=e.target;if(!(form instanceof HTMLFormElement)||formMethod(form)!=='post')return;e.preventDefault();
    if(!form.checkValidity()){focusInvalidField(form);showNotify('danger','Completa los campos obligatorios marcados con * antes de continuar.');return}
    if(form.id==='gameForm'&&form.elements.group_name&&form.elements.category)form.elements.group_name.value=form.elements.category.value;
    if(form.classList.contains('js-confirm-form')){const ok=await confirmForm(form);if(!ok)return;}
    await softSubmit(form,e.submitter||null);
  });

  window.addEventListener('popstate',()=>softNavigate(location.href,{push:false}));
  history.replaceState({adminSoft:true},'',location.href);
  initPage(d);
  if(window.PAGE_NOTIFY?.message)setTimeout(()=>showNotify(window.PAGE_NOTIFY.type||'info',window.PAGE_NOTIFY.message),90);
})();
