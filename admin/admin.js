(()=> {
  const q=(s,r=document)=>r.querySelector(s),qa=(s,r=document)=>[...r.querySelectorAll(s)]; const side=q('[data-sidebar]'),toggle=q('[data-sidebar-toggle]'),back=q('[data-sidebar-backdrop]');function closeSide() {
    side?.classList.remove('open');back?.classList.remove('show');toggle?.setAttribute('aria-expanded','false')
  }
  toggle?.addEventListener('click',()=> {
    const o=!side.classList.contains('open');side.classList.toggle('open',o);back?.classList.toggle('show',o);toggle.setAttribute('aria-expanded',String(o))
  }
  );back?.addEventListener('click',closeSide);qa('.admin-sidebar a').forEach(a=>a.addEventListener('click',closeSide)); qa('.profile-menu').forEach(d=>document.addEventListener('click',e=> {
    if(d.open&&!d.contains(e.target))d.open=false
  }
  )); qa('.action-menu').forEach(menu=> {
    menu.addEventListener('toggle',()=> {
      if(!menu.open)return;qa('.action-menu[open]').forEach(other=> {
        if(other!==menu)other.open=false
      }
      );const nav=q('nav',menu);menu.classList.remove('drop-up');if(nav) {
        const rect=nav.getBoundingClientRect();if(rect.bottom>window.innerHeight-12)menu.classList.add('drop-up');
      }
    }
    );q('nav',menu)?.addEventListener('click',e=> {
      if(e.target.closest('a,button'))menu.open=false;
    }
    );
  }
  ); qa('[data-stat]').forEach(el=> {
    const target=parseInt(el.dataset.stat||el.textContent,10)||0;let start=0;const dur=500,t0=performance.now();function tick(t) {
      const p=Math.min(1,(t-t0)/dur);el.textContent=Math.round(target*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(tick)
    }
    requestAnimationFrame(tick)
  }
  ); qa('.animate-in').forEach((el,i)=> {
    el.style.animationDelay=Math.min(i*45,360)+'ms'
  }
  ); const flash=q('[data-flash-message]');if(flash&&window.showNotify) {
    showNotify(flash.dataset.flashMessage||'',flash.dataset.flashType||'info');
  }
  const modal=q('[data-image-modal]'),mimg=q('[data-modal-image]'),mcap=q('[data-modal-caption]');function openModal(src,cap='') {
    if(!modal||!mimg)return;mimg.src=src;mcap.textContent=cap;modal.classList.add('open');modal.setAttribute('aria-hidden','false')
  }
  function closeModal() {
    modal?.classList.remove('open');modal?.setAttribute('aria-hidden','true');if(mimg)mimg.src=''
  }
  qa('[data-preview-src]').forEach(b=>b.addEventListener('click',()=>openModal(b.dataset.previewSrc||'',b.dataset.previewCaption||'')));q('[data-modal-close]')?.addEventListener('click',closeModal);modal?.addEventListener('click',e=> {
    if(e.target===modal)closeModal()
  }
  );document.addEventListener('keydown',e=> {
    if(e.key==='Escape')closeModal()
  }
  ); function initUpload(zone) {
    const input=q('input[type=file]',zone),preview=q('[data-upload-preview]',zone),name=q('[data-upload-name]',zone);
    if(!input)return;
    const multiple=input.multiple;
    let files=[];
    const accepts=(input.getAttribute('accept')||'').split(',').map(x=>x.trim()).filter(Boolean);
    function allowed(f) {
      if(!accepts.length)return true;return accepts.some(a=>a.endsWith('/*')?f.type.startsWith(a.slice(0,-1)):f.type===a)
    }
    function render() {
      if(!preview)return;preview.innerHTML='';files.forEach((f,i)=> {
        const url=URL.createObjectURL(f),card=document.createElement('div');
        card.className='upload-preview-item';
        if(f.type.startsWith('image/'))card.innerHTML='<img alt="Selected image"><button type="button" aria-label="Remove">×</button>';
        else if(f.type.startsWith('video/'))card.innerHTML='<video muted playsinline preload="metadata"></video><button type="button" aria-label="Remove">×</button>';
        else card.innerHTML='<div class="upload-file-badge">PDF</div><button type="button" aria-label="Remove">×</button>';
        const media=card.querySelector('img,video');
        if(media)media.src=url;
        card.querySelector('button').addEventListener('click',()=> {
          files.splice(i,1);sync();
        }
        );preview.appendChild(card)
      }
      );if(name)name.textContent=files.length?(files.length+' file'+(files.length>1?'s':'')+' selected'):(input.dataset.emptyLabel||'Drop, paste or choose file'+(multiple?'s':''));
    }
    function sync() {
      const dt=new DataTransfer();files.forEach(f=>dt.items.add(f));input.files=dt.files;render()
    }
    function add(list) {
      const incoming=[...list].filter(allowed);files=multiple?[...files,...incoming].slice(0,12):(incoming.length?[incoming[0]]:files);sync()
    }
    input.addEventListener('change',()=> {
      files=[...input.files].filter(allowed);render()
    }
    );zone.addEventListener('dragover',e=> {
      e.preventDefault();zone.classList.add('dragover')
    }
    );zone.addEventListener('dragleave',()=>zone.classList.remove('dragover'));zone.addEventListener('drop',e=> {
      e.preventDefault();zone.classList.remove('dragover');add(e.dataTransfer.files)
    }
    );zone.addEventListener('paste',e=> {
      const fs=[...e.clipboardData.items].filter(x=>x.kind==='file').map(x=>x.getAsFile()).filter(Boolean);if(fs.length) {
        e.preventDefault();add(fs)
      }
    }
    );zone.tabIndex=0;zone.addEventListener('click',e=> {
      if(e.target.closest('button'))return;if(e.target!==input)input.click()
    }
    );render();
  }
  qa('[data-upload-zone]').forEach(initUpload); qa('[data-method-select]').forEach(sel=> {
    const form=sel.closest('form');const update=()=> {
      qa('[data-method]',form).forEach(el=>el.hidden=el.dataset.method!==sel.value)
    }
    ;sel.addEventListener('change',update);update()
  }
  ); qa('form[data-swal-confirm]').forEach(form=>form.addEventListener('submit',async e=> {
    if(form.dataset.swalApproved==='1')return;e.preventDefault();const result=window.Swal?await Swal.fire( {
      icon:'warning',title:form.dataset.swalConfirm||'Confirm action',text:form.dataset.swalText||'Please confirm this action.',showCancelButton:true,confirmButtonText:form.dataset.swalConfirmText||'Yes, continue',cancelButtonText:'Cancel',allowOutsideClick:false
    }
    ): {
      isConfirmed:false
    }
    ;if(result.isConfirmed) {
      form.dataset.swalApproved='1';if(form.requestSubmit)form.requestSubmit();else form.submit();
    }
  }
  )); qa('[data-confirm-text]').forEach(form=>form.addEventListener('submit',e=> {
    const expected=form.dataset.confirmText,input=q('[name=confirmation]',form);if(input&&input.value.trim()!==expected) {
      e.preventDefault();input.focus();if(window.showNotify)showNotify('Type '+expected+' exactly to continue.','warning');else input.setCustomValidity('Type '+expected+' exactly to continue.');
    }
  }
  )); // Live content editor preview while typing.
  qa('.cms-form [name]').forEach(field=>field.addEventListener('input',()=> {
    const iframe=q('.live-preview-panel iframe');if(!iframe||!field.name)return;try {
      const doc=iframe.contentDocument;doc?.querySelectorAll('[data-content-key="'+CSS.escape(field.name)+'"]').forEach(el=>el.textContent=field.value);
    } catch(err) {
    }
  }
  )); // Secure logout confirmation.
  qa('[data-logout-confirm]').forEach(link=>link.addEventListener('click',async e=> {
    e.preventDefault();const href=link.getAttribute('href');const result=window.Swal?await Swal.fire( {
      icon:'question',title:'Log out of the administrator?',text:'Your current admin session will be closed.',showCancelButton:true,confirmButtonText:'Yes, log out',cancelButtonText:'Stay signed in',allowOutsideClick:false
    }
    ): {
      isConfirmed:window.confirm('Log out of the administrator?')
    }
    ;if(result.isConfirmed)window.location.href=href;
  }
  )); qa('[data-sortable-list]').forEach(list=> {
    let dragged=null;const sync=()=>qa('.section-sort-card',list).forEach((card,i)=> {
      const value=(i+1)*10;const input=q('[data-sort-order]',card),label=q('[data-order-label]',card);if(input)input.value=value;if(label)label.textContent=value;
    }
    );qa('.section-sort-card',list).forEach(card=> {
      card.addEventListener('dragstart',()=> {
        dragged=card;card.classList.add('dragging')
      }
      );card.addEventListener('dragend',()=> {
        card.classList.remove('dragging');dragged=null;sync()
      }
      );card.addEventListener('dragover',e=> {
        e.preventDefault();if(!dragged||dragged===card)return;const r=card.getBoundingClientRect();list.insertBefore(dragged,e.clientY<r.top+r.height/2?card:card.nextSibling)
      }
      );
    }
    );
  }
  );
}
)();
// Premium custom select UI. The original <select> remains the submitted value.
(()=> {
  const all=[...document.querySelectorAll('select:not([multiple])')]; const closeAll=(except=null)=>document.querySelectorAll('.cr-select.open').forEach(w=> {
    if(w!==except) {
      w.classList.remove('open');w.querySelector('.cr-select-button')?.setAttribute('aria-expanded','false')
    }
  }
  ); all.forEach((select,index)=> {
    if(select.dataset.customSelectReady==='1')return;
    select.dataset.customSelectReady='1';
    select.classList.add('cr-native-select');
    const wrap=document.createElement('div');
    wrap.className='cr-select';
    const btn=document.createElement('button');
    btn.type='button';
    btn.className='cr-select-button';
    btn.setAttribute('aria-haspopup','listbox');
    btn.setAttribute('aria-expanded','false');
    const list=document.createElement('div');
    list.className='cr-select-list';
    list.setAttribute('role','listbox');
    list.id='cr-select-'+index;
    btn.setAttribute('aria-controls',list.id);
    select.insertAdjacentElement('afterend',wrap);
    wrap.append(btn,list);
    const sync=()=> {
      const selected=select.options[select.selectedIndex]; btn.textContent=selected?selected.textContent:'Select an option'; [...list.children].forEach((o,i)=>o.setAttribute('aria-selected',String(i===select.selectedIndex)));
    }
    ; [...select.options].forEach((opt,i)=> {
      const item=document.createElement('button');item.type='button';item.className='cr-select-option';item.setAttribute('role','option');item.textContent=opt.textContent;item.disabled=opt.disabled; item.addEventListener('click',()=> {
        if(opt.disabled)return;select.selectedIndex=i;select.dispatchEvent(new Event('change', {
          bubbles:true
        }
        ));sync();wrap.classList.remove('open');btn.setAttribute('aria-expanded','false');btn.focus();
      }
      ); list.appendChild(item);
    }
    ); btn.addEventListener('click',e=> {
      e.stopPropagation();const willOpen=!wrap.classList.contains('open');closeAll(wrap);wrap.classList.toggle('open',willOpen);btn.setAttribute('aria-expanded',String(willOpen));if(willOpen) {
        const current=list.children[select.selectedIndex];current?.focus();
      }
    }
    ); btn.addEventListener('keydown',e=> {
      if(e.key==='ArrowDown'||e.key==='ArrowUp') {
        e.preventDefault();if(!wrap.classList.contains('open'))btn.click();
      }
    }
    ); select.addEventListener('change',sync);sync();
  }
  ); document.addEventListener('click',e=> {
    if(!e.target.closest('.cr-select'))closeAll();
  }
  ); document.addEventListener('keydown',e=> {
    if(e.key==='Escape')closeAll();
  }
  );
}
)();
// Simple progressive disclosure panels used by user/approval forms.
document.querySelectorAll('[data-toggle-panel]').forEach(btn=>btn.addEventListener('click',()=> {
  const el=document.getElementById(btn.dataset.togglePanel||'');if(!el)return;el.classList.toggle('is-collapsed');if(!el.classList.contains('is-collapsed'))el.scrollIntoView( {
    behavior:'smooth',block:'start'
  }
  );
}
));
// Keep topbar menus mutually exclusive.
document.querySelectorAll('.notification-bell,.profile-menu').forEach(menu=>menu.addEventListener('toggle',()=> {
  if(!menu.open)return;document.querySelectorAll('.notification-bell[open],.profile-menu[open]').forEach(other=> {
    if(other!==menu)other.open=false;
  }
  );
}
));
// Appearance mini live preview.
(()=> {
  const form=document.querySelector('[data-appearance-form]'),box=document.querySelector('[data-style-preview]');
  if(!form||!box)return;
  const heading=box.querySelector('h2'),para=box.querySelector('p');
  const fontStack=v=>v==='System'?'system-ui, sans-serif':'"'+v+'", sans-serif';
  const update=()=> {
    const g=n=>form.querySelector('[name="'+n+'"]')?.value;if(heading) {
      heading.style.fontFamily=fontStack(g('font_heading_family')||'Manrope');heading.style.fontSize=Math.max(24,Math.min(80,Number(g('font_h1_desktop')||40)))+'px';heading.style.fontWeight=g('heading_weight')||800
    }
    if(para) {
      para.style.fontFamily=fontStack(g('font_body_family')||'DM Sans');para.style.fontSize=Math.max(14,Math.min(24,Number(g('font_body_size')||16)))+'px';para.style.lineHeight=g('line_height_body')||1.7
    }
  }
  ;form.querySelectorAll('input,select').forEach(el=>el.addEventListener('input',update));update()
}
)();
// Quick Find: helps non-technical users jump to the right admin area.
(() => {
  const overlay = document.querySelector('[data-admin-find]');
  const input = document.querySelector('[data-admin-find-input]');
  const results = document.querySelector('[data-admin-find-results]');
  if (!overlay || !input || !results) return;
  const links = [...document.querySelectorAll('.admin-sidebar a[href]')].map((link) => ( {
    href: link.getAttribute('href'), label: (link.textContent || '').replace(/\s+/g, ' ').trim(),
  }
  )); const aliases = {
    appearance: 'theme colors typography fonts banner header navigation menu design', settings: 'logo favicon whatsapp maintenance contact social branding', media: 'images videos files documents upload library', gallery: 'projects photos images portfolio', content: 'text titles paragraphs landing copy editor publish draft', users: 'accounts team staff administrators login', roles: 'permissions access security roles', estimates: 'requests quotes customers leads follow up', email: 'smtp graph messages mail',
  }
  ; const render = (query = '') => {
    const term = query.trim().toLowerCase(); const matches = links.filter((item) => {
      const key = (item.href || '').replace('.php', '').toLowerCase(); const searchable = `${item.label} ${key} ${aliases[key] || ''}`.toLowerCase(); return term === '' || searchable.includes(term);
    }
    ); results.innerHTML = ''; if (!matches.length) {
      const empty = document.createElement('div'); empty.className = 'admin-find-empty'; empty.textContent = 'No matching admin area. Try a simpler word.'; results.appendChild(empty); return;
    }
    matches.slice(0, 10).forEach((item) => {
      const link = document.createElement('a'); link.href = item.href; link.innerHTML = `<span>${item.label}</span><b>Open →</b>`; results.appendChild(link);
    }
    );
  }
  ; const open = () => {
    overlay.hidden = false; document.body.classList.add('admin-find-open'); render(''); window.setTimeout(() => input.focus(), 30);
  }
  ; const close = () => {
    overlay.hidden = true; document.body.classList.remove('admin-find-open'); input.value = '';
  }
  ; document.querySelectorAll('[data-admin-find-open]').forEach((button) => {
    button.addEventListener('click', open);
  }
  ); document.querySelectorAll('[data-admin-find-close]').forEach((button) => {
    button.addEventListener('click', close);
  }
  ); input.addEventListener('input', () => render(input.value)); document.addEventListener('keydown', (event) => {
    const editable = event.target.matches('input, textarea, select, [contenteditable="true"]'); if (event.key === '/' && !editable) {
      event.preventDefault(); open();
    }
    if (event.key === 'Escape' && !overlay.hidden) {
      close();
    }
  }
  );
}
)();
// Warn users before leaving a form with unsaved changes.
(() => {
  const forms = [...document.querySelectorAll('[data-unsaved-form]')]; if (!forms.length) return; let dirty = false; forms.forEach((form) => {
    const markDirty = () => {
      dirty = true; form.classList.add('has-unsaved-changes');
    }
    ; form.querySelectorAll('input, textarea, select').forEach((field) => {
      if (field.type === 'hidden') return; field.addEventListener('input', markDirty); field.addEventListener('change', markDirty);
    }
    ); form.addEventListener('submit', () => {
      dirty = false; form.classList.remove('has-unsaved-changes');
    }
    );
  }
  ); window.addEventListener('beforeunload', (event) => {
    if (!dirty) return; event.preventDefault(); event.returnValue = '';
  }
  );
}
)();
// Keep color picker and HEX input synchronized in Appearance.
(() => {
  document.querySelectorAll('.color-input-wrap').forEach((wrap) => {
    const picker = wrap.querySelector('[data-color-picker]'); const text = wrap.querySelector('[data-color-text]'); if (!picker || !text) return; picker.addEventListener('input', () => {
      text.value = picker.value.toLowerCase(); text.dispatchEvent(new Event('input', {
        bubbles: true
      }
      ));
    }
    ); text.addEventListener('input', () => {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
        picker.value = text.value;
      }
    }
    );
  }
  );
}
)();

/* ==========================================================
   PHASE 1 - Focused landing page section editor
   ========================================================== */
(() => {
  const switcher = document.querySelector('[data-section-switcher]');
  if (!switcher) return;

  const tabs = Array.from(switcher.querySelectorAll('[data-section-tab]'));
  const contentEditors = Array.from(document.querySelectorAll('[data-content-editor]'));
  const moduleEditors = Array.from(document.querySelectorAll('[data-module-editor]'));
  const title = document.querySelector('[data-active-section-title]');
  const description = document.querySelector('[data-active-section-description]');
  const previewName = document.querySelector('[data-preview-section-name]');
  const previewFrame = document.querySelector('[data-section-preview-frame]');
  const previewOpen = document.querySelector('[data-preview-open]');
  const returnInputs = Array.from(document.querySelectorAll('[data-return-section], [data-return-section-copy]'));
  const previousButton = document.querySelector('[data-section-previous]');
  const nextButton = document.querySelector('[data-section-next]');
  const savebar = document.querySelector('[data-content-savebar]');
  const aboutArtworkPanel = document.querySelector('[data-about-artwork-panel]');
  const deviceButtons = Array.from(document.querySelectorAll('[data-preview-device]'));
  const previewStage = document.querySelector('[data-preview-stage]');

  let activeKey = tabs.find(tab => tab.classList.contains('is-active'))?.dataset.sectionTab || tabs[0]?.dataset.sectionTab || 'home';

  const activeTabIndex = () => tabs.findIndex(tab => tab.dataset.sectionTab === activeKey);

  const focusPreviewSection = (anchor) => {
    if (!previewFrame || !anchor) return;

    const base = '../?preview=1&draft=1#' + encodeURIComponent(anchor);
    previewFrame.src = base;
    if (previewOpen) previewOpen.href = base;
  };

  const selectSection = (key, updateUrl = true) => {
    const tab = tabs.find(item => item.dataset.sectionTab === key);
    if (!tab) return;

    // Keep the administrator exactly where they are while changing sections.
    // Different editor heights must not make the page jump and lose context.
    const preservedScrollY = window.scrollY;

    activeKey = key;

    tabs.forEach(item => {
      const isActive = item === tab;
      item.classList.toggle('is-active', isActive);
      item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    contentEditors.forEach(editor => {
      const isActive = editor.dataset.contentEditor === key;
      editor.hidden = !isActive;
      editor.classList.toggle('is-active', isActive);
    });

    moduleEditors.forEach(editor => {
      const isActive = editor.dataset.moduleEditor === key;
      editor.hidden = !isActive;
      editor.classList.toggle('is-active', isActive);
    });

    const isEditableContent = contentEditors.some(editor => editor.dataset.contentEditor === key);
    if (savebar) savebar.hidden = !isEditableContent;
    if (aboutArtworkPanel) aboutArtworkPanel.hidden = key !== 'about';

    const sectionTitle = tab.dataset.sectionTitle || '';
    const sectionDescription = tab.dataset.sectionDescription || '';
    const sectionAnchor = tab.dataset.sectionAnchor || key;

    if (title) title.textContent = sectionTitle;
    if (description) description.textContent = sectionDescription;
    if (previewName) previewName.textContent = sectionTitle;
    returnInputs.forEach(input => { input.value = key; });

    focusPreviewSection(sectionAnchor);

    if (updateUrl && window.history?.replaceState) {
      const url = new URL(window.location.href);
      url.searchParams.set('section', key);
      window.history.replaceState({}, '', url.toString());
    }

    requestAnimationFrame(() => {
      window.scrollTo({ top: preservedScrollY, left: 0, behavior: 'auto' });
    });
  };

  tabs.forEach(tab => {
    tab.addEventListener('click', () => selectSection(tab.dataset.sectionTab || 'home'));
  });

  previousButton?.addEventListener('click', () => {
    const index = activeTabIndex();
    const nextIndex = index <= 0 ? tabs.length - 1 : index - 1;
    selectSection(tabs[nextIndex].dataset.sectionTab || 'home');
  });

  nextButton?.addEventListener('click', () => {
    const index = activeTabIndex();
    const nextIndex = index >= tabs.length - 1 ? 0 : index + 1;
    selectSection(tabs[nextIndex].dataset.sectionTab || 'home');
  });

  deviceButtons.forEach(button => {
    button.addEventListener('click', () => {
      const device = button.dataset.previewDevice || 'desktop';
      deviceButtons.forEach(item => {
        const isActive = item === button;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      if (previewStage) previewStage.dataset.previewStage = device;
    });
  });

  document.querySelectorAll('[data-section-content-form] input, [data-section-content-form] textarea').forEach(field => {
    field.addEventListener('input', () => {
      const state = document.querySelector('[data-section-state="' + CSS.escape(activeKey) + '"]');
      if (!state) return;
      state.textContent = 'Unsaved';
      state.classList.remove('published', 'managed');
      state.classList.add('draft');
    });
  });

  selectSection(activeKey, false);
})();
