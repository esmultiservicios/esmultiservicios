(()=> {
  const q=(s,r=document)=>r.querySelector(s),qa=(s,r=document)=>[...r.querySelectorAll(s)];
  const side=q('[data-sidebar]'),toggle=q('[data-sidebar-toggle]'),back=q('[data-sidebar-backdrop]');

  function closeTopbarMenus(except=null) {
    qa('.notification-bell[open],.profile-menu[open]').forEach(menu=> {
      if(menu!==except)menu.open=false;
    });
  }
  function closeAdminFind() {
    const overlay=q('[data-admin-find]');
    if(!overlay||overlay.hidden)return;
    overlay.hidden=true;
    document.body.classList.remove('admin-find-open');
    const input=q('[data-admin-find-input]');
    if(input)input.value='';
  }
  function closeSide() {
    side?.classList.remove('open');
    back?.classList.remove('show');
    toggle?.setAttribute('aria-expanded','false');
  }
  // Shared overlay manager so later admin components can close each other safely.
  window.AdminOverlayManager = {
    closeSide,
    closeTopbarMenus,
    closeAdminFind
  };
  toggle?.addEventListener('click',()=> {
    const o=!side.classList.contains('open');
    if(o) {
      closeTopbarMenus();
      closeAdminFind();
    }
    side.classList.toggle('open',o);
    back?.classList.toggle('show',o);
    toggle.setAttribute('aria-expanded',String(o));
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
      isConfirmed:false
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
// Keep admin navigation overlays mutually exclusive in both directions.
document.querySelectorAll('.notification-bell,.profile-menu').forEach(menu=>menu.addEventListener('toggle',()=> {
  if(!menu.open)return;
  const manager=window.AdminOverlayManager;
  manager?.closeSide();
  manager?.closeAdminFind();
  manager?.closeTopbarMenus(menu);
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
    appearance: 'theme colors typography fonts banner header navigation menu design', settings: 'logo favicon whatsapp maintenance contact branding', social: 'social networks instagram facebook tiktok youtube linkedin icons profiles footer floating', media: 'images videos files documents upload library', gallery: 'projects photos images portfolio', content: 'text titles paragraphs landing copy editor publish draft', users: 'accounts team staff administrators login', roles: 'permissions access security roles', estimates: 'requests quotes customers leads follow up', email: 'smtp graph messages mail', analytics: 'visits traffic statistics visitors metrics', widgets: 'floating whatsapp external chat widget',
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
    const manager=window.AdminOverlayManager;
    manager?.closeSide();
    manager?.closeTopbarMenus();
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


// Local accessible dialog helper used by modules that need a lightweight modal without external dependencies.
window.CMSDialog = (() => {
    let lastFocus = null;
    function close(dialog) {
        if (!dialog) return;
        dialog.hidden = true;
        document.body.classList.remove('cms-dialog-open');
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }
    function open(dialog) {
        if (typeof dialog === 'string') dialog = document.querySelector(dialog);
        if (!dialog) return;
        lastFocus = document.activeElement;
        dialog.hidden = false;
        document.body.classList.add('cms-dialog-open');
        const focusable = dialog.querySelector('[data-dialog-close],button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])');
        if (focusable) focusable.focus();
        return dialog;
    }
    document.addEventListener('click', (event) => {
        const closeBtn = event.target.closest('[data-dialog-close]');
        if (closeBtn) close(closeBtn.closest('[data-cms-dialog]'));
        const backdrop = event.target.matches('[data-cms-dialog-backdrop]') ? event.target : null;
        if (backdrop && backdrop.dataset.closeOnBackdrop === '1') close(backdrop.closest('[data-cms-dialog]'));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const openDialog = document.querySelector('[data-cms-dialog]:not([hidden])');
        if (openDialog) close(openDialog);
    });
    return { open, close };
})();

/* Premium action icons -----------------------------------------------------
   Adds a consistent semantic icon to text action buttons across the admin.
   This keeps every module uniform without requiring per-page icon markup. */
(() => {
  const icons = {
    save:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h11l3 3v15H5V3Zm2 2v5h8V5H7Zm1 10v4h8v-4H8Z"/></svg>',
    add:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6V5Z"/></svg>',
    delete:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10l-1 14H8L7 7Zm2-4h6l1 2h4v2H4V5h4l1-2Z"/></svg>',
    edit:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16.5-.5 4 4-.5L19 8.5 15.5 5 4 16.5Zm12.6-12.6 1.2-1.2a1.4 1.4 0 0 1 2 0l1.5 1.5a1.4 1.4 0 0 1 0 2L20.1 7.4l-3.5-3.5Z"/></svg>',
    search:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 3a7 7 0 1 1-4.9 12L2 18.1 3.9 20l3.1-3.1A7 7 0 0 1 10 3Zm0 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10Z"/></svg>',
    send:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 20 18-8L3 4v6l12 2-12 2v6Z"/></svg>',
    upload:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 16h2V8l3 3 1.4-1.4L12 4.2 6.6 9.6 8 11l3-3v8Zm-6 3h14v2H5v-2Z"/></svg>',
    download:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4h2v8l3-3 1.4 1.4L12 15.8l-5.4-5.4L8 9l3 3V4ZM5 19h14v2H5v-2Z"/></svg>',
    view:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5C6.5 5 2.3 9.2 1 12c1.3 2.8 5.5 7 11 7s9.7-4.2 11-7c-1.3-2.8-5.5-7-11-7Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
    publish:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 16.2 5.3 12l-1.4 1.4 5.6 5.6L20.1 8.4 18.7 7l-9.2 9.2Z"/></svg>',
    restore:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5a7 7 0 1 1-6.7 9H3.2A9 9 0 1 0 5 6.3V3H3v7h7V8H6.4A7 7 0 0 1 12 5Z"/></svg>',
    archive:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v4H4V4Zm1 6h14v10H5V10Zm4 3v2h6v-2H9Z"/></svg>',
    lock:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V7a5 5 0 0 1 10 0v3h2v11H5V10h2Zm2 0h6V7a3 3 0 0 0-6 0v3Z"/></svg>',
    test:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6v2l-1 1v4l5 8a2 2 0 0 1-1.7 3H6.7A2 2 0 0 1 5 18l5-8V6L9 5V3Zm1 12h4l-2-3-2 3Z"/></svg>',
    back:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 5-7 7 7 7 1.4-1.4L6.8 13H21v-2H6.8l4.6-4.6L10 5Z"/></svg>',
    next:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 5-1.4 1.4 4.6 4.6H3v2h14.2l-4.6 4.6L14 19l7-7-7-7Z"/></svg>',
    cancel:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6.3 4.9 5.7 5.7 5.7-5.7 1.4 1.4-5.7 5.7 5.7 5.7-1.4 1.4-5.7-5.7-5.7 5.7-1.4-1.4 5.7-5.7-5.7-5.7 1.4-1.4Z"/></svg>',
    approve:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 16.2 5.3 12l-1.4 1.4 5.6 5.6L20.1 8.4 18.7 7l-9.2 9.2Z"/></svg>',
    login:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4h10v16H10v-2h8V6h-8V4Zm1.4 4.6L14.8 12l-3.4 3.4L10 14l1-1H4v-2h7l-1-1 1.4-1.4Z"/></svg>',
    settings:'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.4 13a7.9 7.9 0 0 0 0-2l2-1.5-2-3.4-2.4 1a8.1 8.1 0 0 0-1.7-1L15 3.5h-4l-.3 2.6a8.1 8.1 0 0 0-1.7 1l-2.4-1-2 3.4 2 1.5a7.9 7.9 0 0 0 0 2l-2 1.5 2 3.4 2.4-1a8.1 8.1 0 0 0 1.7 1l.3 2.6h4l.3-2.6a8.1 8.1 0 0 0 1.7-1l2.4 1 2-3.4-2-1.5ZM13 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Z"/></svg>'
  };
  function actionFor(el){
    const t=(el.textContent||'').trim().toLowerCase();
    if(!t) return null;
    if(/delete|remove|eliminar|borrar|reset/.test(t)) return 'delete';
    if(/add|create|new|agregar|crear/.test(t)) return 'add';
    if(/edit|editar/.test(t)) return 'edit';
    if(/search|find|buscar/.test(t)) return 'search';
    if(/upload|subir|cargar/.test(t)) return 'upload';
    if(/download|descargar/.test(t)) return 'download';
    if(/preview|view|ver sitio|ver |vista/.test(t)) return 'view';
    if(/publish|publicar/.test(t)) return 'publish';
    if(/approve|aprobar/.test(t)) return 'approve';
    if(/restore|restaurar/.test(t)) return 'restore';
    if(/archive|archivar/.test(t)) return 'archive';
    if(/send|enviar/.test(t)) return 'send';
    if(/test|probar/.test(t)) return 'test';
    if(/login|log in|iniciar sesión|verify|verificar|enable 2fa/.test(t)) return 'login';
    if(/previous|back|atrás|volver|cancel/.test(t)) return /cancel/.test(t)?'cancel':'back';
    if(/next|continue|continuar|siguiente/.test(t)) return 'next';
    if(/save|guardar|update|actualizar|apply|aplicar|set up|configur/.test(t)) return 'save';
    if(/sign out|cerrar sesión|revoke/.test(t)) return 'lock';
    if(/request|solicitar/.test(t)) return 'send';
    return 'settings';
  }
  function decorate(root=document){
    root.querySelectorAll('button, a.button, .button').forEach(el=>{
      if(el.dataset.noActionIcon==='1' || el.classList.contains('icon-btn') || el.classList.contains('media-preview') || el.classList.contains('zoom-btn') || el.closest('.rich-toolbar') || el.querySelector('.ui-icon,.action-icon')) return;
      const txt=(el.textContent||'').trim();
      if(!txt || txt.length===1 || (/^[BIU×+−]$/i).test(txt)) return;
      const key=actionFor(el); if(!key) return;
      const span=document.createElement('span');
      span.className='action-icon';
      span.innerHTML=icons[key]||icons.settings;
      el.prepend(span);
      el.classList.add('has-action-icon');
    });
  }
  const start=()=>{
    decorate();
    new MutationObserver(m=>m.forEach(x=>x.addedNodes.forEach(n=>{if(n.nodeType===1){if(n.matches?.('button,.button,a.button')) decorate(n.parentElement||document); else decorate(n);}}))).observe(document.body,{childList:true,subtree:true});
  };
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',start,{once:true}); else start();
})();
