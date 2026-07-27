(() => {
  const root = document.querySelector('#tvcd-app');
  const boot = window.TVCD_BOOT;
  const state = { types: [], active: null, items: [], loading: true, loadingMore: false, page: 0, pages: 0, total: 0, requestId: 0, editor: null, settings: false, siteSettingsPage: false, siteSettings: {}, updateStatus: null, checkingUpdate: false, updatingPlugin: false, search: '', appearance: {}, selected: new Set(), sortBy: '', sortOrder: '', installPrompt: null, navOpen: false };
  const esc = (v='') => String(v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const api = async (path, options={}) => {
    const response = await fetch(boot.restUrl + path, {
      ...options,
      headers: {'X-WP-Nonce': boot.nonce, 'Content-Type':'application/json', ...(options.headers || {})}
    });
    const body = await response.json();
    if (!response.ok) throw new Error(body.message || 'Something went wrong.');
    return body;
  };
  const icon = (name) => `<span class="dashicons dashicons-${name}"></span>`;
  const notify = message => { const el=document.createElement('div'); el.className='tvcd-notice'; el.textContent=message; document.body.append(el); setTimeout(()=>el.remove(),2600); };
  const activeType = () => state.types.find(t => t.name === state.active);
  const fieldOptions = (type, selected) => type.fields.map(f => `<option value="${esc(f.key)}" ${f.key===selected?'selected':''}>${esc(f.label)}</option>`).join('');
  const mediaValue = value => {
    if (!value) return [];
    return Array.isArray(value) ? value : [value];
  };

  function render() {
    const type = activeType();
    applyAppearance();
    root.innerHTML = `<div class="tvcd-shell">
      <aside class="tvcd-sidebar ${state.navOpen?'open':''}">
        <div class="tvcd-brand">${state.appearance.logo_url?`<span class="tvcd-brand-logo-tile"><img class="tvcd-brand-logo" src="${esc(state.appearance.logo_url)}" alt=""></span>`:'<div class="tvcd-mark">TV</div>'}<div><strong>Manage site</strong><small>${esc(boot.site.name)}</small></div><button class="tvcd-drawer-close" data-close-nav aria-label="Close navigation">${icon('no-alt')}</button></div>
        <nav class="tvcd-nav"><span class="tvcd-nav-label">Content</span>${state.types.filter(t=>t.enabled).map(t=>`<button data-type="${esc(t.name)}" aria-label="${esc(t.config.menu_label||t.label)}" title="${esc(t.config.menu_label||t.label)}" class="${!state.settings&&!state.siteSettingsPage&&t.name===state.active?'active':''}"><i class="${esc(t.config.icon||'fa-solid fa-table-cells-large')}"></i><span>${esc(t.config.menu_label||t.label)}</span></button>`).join('')}${boot.canManage?`<div class="tvcd-nav-separator"></div><span class="tvcd-nav-label">Settings</span><button data-site-settings aria-label="Site Settings" title="Site Settings" class="${state.siteSettingsPage?'active':''}"><i class="fa-solid fa-globe"></i><span>Site Settings</span></button><button data-settings aria-label="Dashboard Settings" title="Dashboard Settings" class="${state.settings?'active':''}"><i class="fa-solid fa-gear"></i><span>Dashboard Settings</span></button>`:''}</nav>
        <div class="tvcd-sidebar-foot"><div class="tvcd-user"><img src="${esc(boot.user.avatar)}"><span>${esc(boot.user.name)}</span></div></div>
      </aside>
      <button class="tvcd-drawer-scrim ${state.navOpen?'show':''}" data-close-nav aria-label="Close navigation"></button>
      <main class="tvcd-main">
        <header class="tvcd-topbar"><button class="tvcd-menu-button" data-open-nav aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button><h1>${state.siteSettingsPage?'Site Settings':state.settings?'Dashboard Settings':'Content Dashboard'}</h1><div class="tvcd-top-actions">${state.installPrompt?`<button class="tvcd-btn tvcd-install" data-install-app>${icon('download')} Install app</button>`:''}<a class="tvcd-btn icon" href="${esc(boot.adminUrl)}" title="Back to WordPress Dashboard" aria-label="Back to WordPress Dashboard">${icon('dashboard')}</a><a class="tvcd-btn icon" href="${esc(boot.site.url)}" target="_blank" title="View site" aria-label="View site">${icon('external')}</a></div></header>
        <section class="tvcd-content">${state.siteSettingsPage?siteSettingsContent():state.settings?settingsContent():state.loading?'<div class="tvcd-spinner"></div>':content(type)}</section>
      </main>
    </div>${state.editor?editorMarkup():''}`;
    bind();
    requestAnimationFrame(()=>{alignFieldRows();updateMobileNavAlignment();});
  }

  function applyAppearance() {
    const a=state.appearance||{};
    const vars={brand:a.brand,brandDark:a.brand_dark,ink:a.ink,paper:a.paper,header:a.header,card:a.card,input:a.input,navText:a.nav_text};
    Object.entries(vars).forEach(([key,value])=>{if(value)document.documentElement.style.setProperty(`--${key.replace(/[A-Z]/g,m=>'-'+m.toLowerCase())}`,value);});
  }

  function content(type) {
    if (!type) return `<div class="tvcd-empty"><h2>No content types enabled</h2><p>An administrator can choose which content appears in dashboard settings.</p></div>`;
    return `<div class="tvcd-heading"><div><h2>${esc(type.label)}</h2><p>Showing ${state.items.length} of ${state.total} items</p></div><div class="tvcd-heading-actions">${type.config.show_new&&type.canCreate?`<button class="tvcd-btn primary" data-new>${icon('plus-alt2')} New ${esc(type.singular)}</button>`:''}</div></div>
      <div class="tvcd-toolbar"><label class="tvcd-select-all"><input type="checkbox" data-select-all ${state.items.length&&state.items.every(item=>state.selected.has(item.id))?'checked':''}> Select all</label><div class="tvcd-search">${icon('search')}<input value="${esc(state.search)}" placeholder="Search ${esc(type.label.toLowerCase())}…" data-search></div><select class="tvcd-sort" data-sort-by><option value="modified">Recently updated</option><option value="date">Date created</option><option value="title">Title</option><option value="menu_order">Menu order</option></select><select class="tvcd-sort" data-sort-order><option value="DESC">Descending</option><option value="ASC">Ascending</option></select></div>
      ${state.selected.size?`<div class="tvcd-bulk-bar"><strong>${state.selected.size} selected</strong><select data-bulk-action><option value="">Bulk action…</option><option value="publish">Publish</option><option value="draft">Move to draft</option><option value="trash">Move to trash</option></select><button class="tvcd-btn primary" data-apply-bulk>Apply</button><button class="tvcd-btn" data-clear-selection>Clear</button></div>`:''}
      ${state.items.length?`<div class="tvcd-grid ${type.config.view==='list'?'list':''}">${state.items.map(card).join('')}</div>${state.page<state.pages?`<div class="tvcd-load-more" data-load-more aria-live="polite">${state.loadingMore?'<div class="tvcd-spinner"></div>':'<span>Scroll to load more</span>'}</div>`:''}`:`<div class="tvcd-empty"><h3>Nothing here yet</h3><p>Create your first ${esc(type.singular.toLowerCase())} to get started.</p></div>`}`;
  }

  function card(item) {
    const actions=activeType().config.actions;
    return `<article class="tvcd-card ${state.selected.has(item.id)?'selected':''}"><label class="tvcd-card-select"><input type="checkbox" data-select-post="${item.id}" ${state.selected.has(item.id)?'checked':''}><span>Select</span></label><div class="tvcd-image">${item.image?`<img src="${esc(item.image)}" alt="" loading="lazy">`:icon('format-image')}</div><div class="tvcd-card-body"><div class="tvcd-card-meta"><i class="tvcd-status ${esc(item.status)}"></i>${esc(item.status)} · ${esc(item.modified)}</div><h3>${esc(item.title||'(Untitled)')}</h3><p>${esc(item.description||'No description')}</p></div><div class="tvcd-card-actions">${actions.includes('edit')?`<button class="tvcd-btn" data-edit="${item.id}">${icon('edit')} Edit</button>`:''}${actions.includes('view')?`<a class="tvcd-btn" href="${esc(item.viewUrl)}" target="_blank">${icon('visibility')}</a>`:''}${actions.includes('delete')&&item.canDelete?`<button class="tvcd-btn danger" data-delete="${item.id}">${icon('trash')}</button>`:''}</div></article>`;
  }

  function mediaPreviewMarkup(kind,item) {
    const id=Number(item.id||item)||0;
    const url=item.url||'';
    if(kind==='file'&&!String(item.type||item.mime||'').startsWith('image/'))return `<span class="tvcd-file-pill">${icon('media-default')} ${esc(item.filename||url||'Selected file')}</span>`;
    const image=`<img src="${esc(url)}" alt="">`;
    return kind==='gallery'?`<span class="tvcd-gallery-item" data-media-id="${id}">${image}<button type="button" data-remove-gallery="${id}" aria-label="Remove photo" title="Remove photo">${icon('no-alt')}</button></span>`:image;
  }

  function inputFor(field, value) {
    const v = value ?? '';
    if (field.type==='group') {
      const groupValue=v&&typeof v==='object'&&!Array.isArray(v)?v:{};
      return `<div class="tvcd-acf-group" data-acf-group="${esc(field.key)}"><div class="tvcd-repeater-fields">${(field.sub_fields||[]).map(sub=>`<div class="tvcd-field" data-subfield="${esc(sub.key)}" style="flex:1 1 calc(${Math.min(100,Math.max(20,parseInt(sub.wrapper?.width||100,10)))}% - 16px)"><label>${esc(sub.label)}${sub.required?' *':''}</label><small class="tvcd-instructions">${sub.instructions?esc(sub.instructions):''}</small>${inputFor(sub,groupValue[sub.key]??groupValue[sub.name]??'')}</div>`).join('')}</div></div>`;
    }
    if (field.type==='repeater') {
      const rows=Array.isArray(v)?v:[];
      return `<div class="tvcd-repeater" data-repeater="${esc(field.key)}"><div class="tvcd-repeater-rows">${rows.length?rows.map((row,index)=>repeaterRow(field,row,index)).join(''):'<div class="tvcd-repeater-empty">No rows yet</div>'}</div><div class="tvcd-repeater-foot"><button type="button" class="tvcd-btn" data-add-row="${esc(field.key)}">${icon('plus-alt2')} Add row</button></div></div>`;
    }
    if (['textarea','wysiwyg'].includes(field.type)) return `<textarea data-field="${esc(field.key)}">${esc(v)}</textarea>`;
    if (['select','radio','button_group'].includes(field.type)) return `<select data-field="${esc(field.key)}"><option value="">Choose…</option>${Object.entries(field.choices||{}).map(([k,l])=>`<option value="${esc(k)}" ${String(v)===String(k)?'selected':''}>${esc(l)}</option>`).join('')}</select>`;
    if (field.type==='checkbox') {
      const selected=Array.isArray(v)?v.map(String):[];
      return `<div data-checkbox-field="${esc(field.key)}">${Object.entries(field.choices||{}).map(([k,l])=>`<label class="tvcd-choice"><input type="checkbox" value="${esc(k)}" ${selected.includes(String(k))?'checked':''}> ${esc(l)}</label>`).join('')}</div>`;
    }
    if (field.type==='true_false') return `<select data-field="${esc(field.key)}"><option value="0" ${!v?'selected':''}>No</option><option value="1" ${v?'selected':''}>Yes</option></select>`;
    if (['image','file','gallery'].includes(field.type)) {
      const items=mediaValue(v);
      const preview=items.map(item=>mediaPreviewMarkup(field.type,item)).join('');
      const ids=items.map(item=>item.id||item).filter(Boolean).join(',');
      const pickLabel=field.type==='gallery'?'Add photos':`${items.length?'Change':'Select'} ${field.type}`;
      return `<div class="tvcd-media" data-media="${esc(field.type)}"><div class="tvcd-media-preview">${preview}</div><input type="hidden" data-field="${esc(field.key)}" value="${esc(ids)}"><button type="button" class="tvcd-btn" data-pick-media>${icon(field.type==='file'?'media-default':'format-image')} ${pickLabel}</button>${items.length?` <button type="button" class="tvcd-btn danger" data-clear-media>${field.type==='gallery'?'Remove all':'Remove'}</button>`:''}</div>`;
    }
    const type=['number','range'].includes(field.type)?'number':['url','email','date'].includes(field.type)?field.type:'text';
    return `<input type="${type}" data-field="${esc(field.key)}" value="${esc(v)}" placeholder="${esc(field.placeholder||'')}">`;
  }

  function repeaterRow(field, row={}, index=0) {
    return `<div class="tvcd-repeater-row" data-row><div class="tvcd-repeater-head"><span>Row ${index+1}</span><button type="button" class="tvcd-btn danger icon" data-remove-row title="Remove row">${icon('trash')}</button></div><div class="tvcd-repeater-fields">${(field.sub_fields||[]).map(sub=>`<div class="tvcd-field" data-subfield="${esc(sub.key)}" style="flex:1 1 calc(${Math.min(100,Math.max(20,parseInt(sub.wrapper?.width||100,10)))}% - 16px)"><label>${esc(sub.label)}${sub.required?' *':''}</label><small class="tvcd-instructions">${sub.instructions?esc(sub.instructions):''}</small>${inputFor(sub,row[sub.key]??row[sub.name]??'')}</div>`).join('')}</div></div>`;
  }

  function groupFields(fields) {
    const groups=[];
    const visible=activeType()?.config.visible_fields||[];
    const configured=!!activeType()?.config.visible_fields_configured;
    const baselineKeys=new Set((activeType()?.fields||[]).map(field=>field.key));
    fields.filter(f=>!f.key.startsWith('_')&&(!configured||visible.includes(f.key)||!baselineKeys.has(f.key))).forEach(field=>{
      let group=groups.find(g=>g.key===field.group_key);
      if(!group){group={key:field.group_key,label:field.group_label,fields:[]};groups.push(group);}
      group.fields.push(field);
    });
    return groups;
  }

  function groupMarkup(group, values) {
    let currentTab='main', tabs=[{key:'main',label:'Fields'}], chunks={main:[]};
    group.fields.forEach(field=>{
      if(field.type==='tab'){
        currentTab=field.key;
        tabs.push({key:field.key,label:field.label});
        chunks[currentTab]=[];
      } else {
        chunks[currentTab].push(field);
      }
    });
    const visibleTabs=tabs.filter(t=>chunks[t.key]?.length), hasTabs=visibleTabs.length>1;
    return `<section class="tvcd-field-group" data-field-group="${esc(group.key)}"><h3 class="tvcd-group-title">${esc(group.label)}</h3>${hasTabs?`<div class="tvcd-tabs">${visibleTabs.map((t,i)=>`<button type="button" class="tvcd-tab ${i===0?'active':''}" data-tab="${esc(t.key)}">${esc(t.label)}</button>`).join('')}</div>`:''}${visibleTabs.map((tab,i)=>`<div class="tvcd-group-fields" data-tab-panel="${esc(tab.key)}" ${hasTabs&&i>0?'hidden':''}>${chunks[tab.key].map(field=>`<div class="tvcd-field" style="flex:1 1 calc(${Math.min(100,Math.max(20,parseInt(field.wrapper?.width||100,10)))}% - 20px)"><label>${esc(field.label)}${field.required?' *':''}</label><small class="tvcd-instructions">${field.instructions?esc(field.instructions):''}</small>${inputFor(field,values[field.key])}</div>`).join('')}</div>`).join('')}</section>`;
  }

  function editorMarkup() {
    const e=state.editor, isNew=!e.id;
    const visible=activeType()?.config.visible_fields||[], configured=!!activeType()?.config.visible_fields_configured, show=key=>!configured||visible.includes(key);
    return `<div class="tvcd-modal-backdrop"><div class="tvcd-modal"><div class="tvcd-modal-head"><div><small>${isNew?'Create':'Edit'}</small><h2>${esc(e.title||`New ${activeType().singular}`)}</h2></div><button class="tvcd-btn icon" data-close>${icon('no-alt')}</button></div><div class="tvcd-modal-body">
      ${show('_post_title')?`<div class="tvcd-field"><label>Title</label><input data-core="title" value="${esc(e.title)}"></div>`:''}
      ${show('_excerpt')?`<div class="tvcd-field"><label>Excerpt</label><textarea data-core="excerpt">${esc(e.excerpt)}</textarea></div>`:''}
      ${show('_status')?`<div class="tvcd-field"><label>Status</label><select data-core="status">${['draft','publish','pending','private'].map(s=>`<option ${s===e.status?'selected':''}>${s}</option>`).join('')}</select></div>`:''}
      ${show('_featured_image')?`<div class="tvcd-field"><label>Featured image</label>${inputFor({key:'_featured_image',type:'image'},e.values?._featured_image)}</div>`:''}
      ${groupFields(e.fields).map(group=>groupMarkup(group,e.values)).join('')}
    </div><div class="tvcd-modal-foot">${!isNew&&e.editUrl?`<a class="tvcd-btn" href="${esc(e.editUrl)}">Open WordPress editor</a>`:''}<button class="tvcd-btn primary" data-save>Save changes</button></div></div></div>`;
  }

  function settingsContent() {
    const a=state.appearance;
    return `<div class="tvcd-heading"><div><h2>Dashboard settings</h2><p>Choose content, layouts, and your dashboard appearance.</p></div><div class="tvcd-heading-actions"><button class="tvcd-btn primary" data-save-settings>Save settings</button></div></div>
      <div class="tvcd-settings-section"><h3>Plugin updates</h3><p class="tvcd-section-copy">Keep the content dashboard current with stable releases.</p><div class="tvcd-update-row"><div><strong>Installed version ${esc(state.updateStatus?.current||boot.version)}</strong>${state.updateStatus?`<small>${state.updateStatus.available?`Version ${esc(state.updateStatus.latest)} is available.`:'You have the latest version.'}</small>`:'<small>Check for a newer release.</small>'}</div><div>${state.updateStatus?.available?`<button class="tvcd-btn primary" data-update-plugin ${state.updatingPlugin?'disabled':''}>${state.updatingPlugin?'Updating…':'Update now'}</button>`:`<button class="tvcd-btn" data-check-plugin-update ${state.checkingUpdate?'disabled':''}>${state.checkingUpdate?'Checking…':'Check for updates'}</button>`}</div></div><label class="tvcd-switch-row"><span><strong>Automatic updates</strong><small>WordPress will install new stable versions during its background update cycle.</small></span><input type="checkbox" data-auto-updates ${state.autoUpdates?'checked':''}></label></div>
      <div class="tvcd-settings-section"><h3>Visible content types</h3><p class="tvcd-section-copy">Only selected content types appear in the navigation.</p><div class="tvcd-checks">${state.types.map(t=>`<label class="tvcd-check"><input type="checkbox" data-enable="${esc(t.name)}" ${t.enabled?'checked':''}> ${esc(t.label)}</label>`).join('')}</div></div>
      <div class="tvcd-settings-section"><h3>Brand appearance</h3><p class="tvcd-section-copy">The Tinker Valley palette is used by default. Brand colors are also used to generate the installed app icon.</p><div class="tvcd-config-grid tvcd-color-grid">${[['brand','Accent'],['brand_dark','Dark accent'],['ink','Navigation background'],['nav_text','Navigation text'],['paper','Page background'],['header','Header background'],['card','Card background'],['input','Input background']].map(([key,label])=>`<div class="tvcd-field"><label>${label}</label><div class="tvcd-color-control"><input type="color" data-appearance="${key}" value="${esc(a[key])}"><input type="text" data-color-text="${key}" value="${esc(a[key])}"></div></div>`).join('')}</div>${logoControl('logo','Logo',a.logo_id,a.logo_url)}${logoControl('light_logo','Light logo',a.light_logo_id,a.light_logo_url)}</div>
      ${state.types.map(t=>`<div class="tvcd-settings-section tvcd-type-config" data-config="${esc(t.name)}" ${t.enabled?'':'hidden'}><h3>${esc(t.label)} layout</h3><div class="tvcd-config-grid"><div class="tvcd-field"><label>Navigation label</label><input type="text" data-setting="menu_label" value="${esc(t.config.menu_label||'')}" placeholder="${esc(t.label)}"><small>Changes only the dashboard menu text.</small></div><div class="tvcd-field"><label>Font Awesome icon classes</label><div class="tvcd-fa-input"><span class="tvcd-fa-preview"><i class="${esc(t.config.icon)}"></i></span><input type="text" data-setting="icon" data-icon-input value="${esc(t.config.icon)}" placeholder="fa-solid fa-folder-open"></div><small>Example: fa-solid fa-folder-open</small></div><div class="tvcd-field"><label>View</label><select data-setting="view"><option value="grid" ${t.config.view==='grid'?'selected':''}>Grid</option><option value="list" ${t.config.view==='list'?'selected':''}>List</option></select></div><div class="tvcd-field"><label>Default sort</label><select data-setting="sort_by">${[['modified','Recently updated'],['date','Date created'],['title','Title'],['menu_order','Menu order']].map(([v,l])=>`<option value="${v}" ${t.config.sort_by===v?'selected':''}>${l}</option>`).join('')}</select></div><div class="tvcd-field"><label>Sort direction</label><select data-setting="sort_order"><option value="DESC" ${t.config.sort_order==='DESC'?'selected':''}>Descending</option><option value="ASC" ${t.config.sort_order==='ASC'?'selected':''}>Ascending</option></select></div><div class="tvcd-field"><label>Card image</label><select data-setting="image_field">${fieldOptions(t,t.config.image_field)}</select></div><div class="tvcd-field"><label>Card title</label><select data-setting="title_field">${fieldOptions(t,t.config.title_field)}</select></div><div class="tvcd-field"><label>Card description</label><select data-setting="description_field">${fieldOptions(t,t.config.description_field)}</select></div></div><h4>Editor fields</h4><p class="tvcd-section-copy">All fields are selected by default. Uncheck any field you want to hide from editors.</p><div class="tvcd-field-choices">${[{key:'_post_title',label:'Post title'},{key:'_excerpt',label:'Excerpt'},{key:'_status',label:'Status'},{key:'_featured_image',label:'Featured image'},...t.fields.filter(f=>!f.key.startsWith('_'))].map(f=>`<label class="tvcd-check"><input type="checkbox" data-visible-field="${esc(f.key)}" ${t.config.visible_fields.includes(f.key)?'checked':''}> ${esc(f.label)}</label>`).join('')}</div><div class="tvcd-checks tvcd-setting-actions"><label class="tvcd-check"><input type="checkbox" data-setting="show_new" ${t.config.show_new?'checked':''}> Show “New” button</label>${['edit','view','delete'].map(action=>`<label class="tvcd-check"><input type="checkbox" data-action="${action}" ${t.config.actions.includes(action)?'checked':''}> ${action[0].toUpperCase()+action.slice(1)} action</label>`).join('')}</div></div>`).join('')}`;
  }

  function logoControl(key,label,id,url) {
    return `<div class="tvcd-field"><label>${label}</label><div class="tvcd-logo-control" data-logo-control="${key}"><div class="tvcd-logo-preview">${url?`<img src="${esc(url)}" alt="">`:'<span>No custom logo</span>'}</div><input type="hidden" data-logo-id="${key}" value="${esc(id||0)}"><button type="button" class="tvcd-btn" data-pick-logo="${key}">${icon('format-image')} ${url?'Change':'Select'} ${label.toLowerCase()}</button>${url?`<button type="button" class="tvcd-btn danger" data-remove-logo="${key}">Remove</button>`:''}</div></div>`;
  }

  function siteSettingsContent() {
    const site=state.siteSettings;
    return `<div class="tvcd-heading"><div><h2>Site settings</h2><p>Manage the primary identity settings used throughout WordPress.</p></div><div class="tvcd-heading-actions"><button class="tvcd-btn primary" data-save-site-settings>Save site settings</button></div></div><div class="tvcd-settings-section tvcd-site-settings-card"><h3>Site identity</h3><div class="tvcd-field"><label>Site title</label><input type="text" data-site-field="title" value="${esc(site.title||'')}"></div><div class="tvcd-field"><label>Tagline</label><input type="text" data-site-field="tagline" value="${esc(site.tagline||'')}"></div><div class="tvcd-field"><label>Site icon</label><small>The square icon used for browser tabs, bookmarks, and mobile shortcuts.</small><div class="tvcd-logo-control"><div class="tvcd-site-icon-preview">${site.site_icon_url?`<img src="${esc(site.site_icon_url)}" alt="">`:'<span>No site icon</span>'}</div><input type="hidden" data-site-icon-id value="${esc(site.site_icon_id||0)}"><button type="button" class="tvcd-btn" data-pick-site-icon>${icon('format-image')} ${site.site_icon_url?'Change':'Select'} site icon</button>${site.site_icon_url?'<button type="button" class="tvcd-btn danger" data-remove-site-icon>Remove</button>':''}</div></div></div>`;
  }

  async function loadItems(append=false) {
    if (!state.active) { state.items=[]; state.loading=false; render(); return; }
    if (append && (state.loadingMore || state.page >= state.pages)) return;
    const requestId=append?state.requestId:++state.requestId;
    const page=append?state.page+1:1;
    if(append)state.loadingMore=true;else{state.loading=true;state.loadingMore=false;state.page=0;state.pages=0;state.total=0;}
    render();
    try {
      const data=await api(`posts/${state.active}?page=${page}&search=${encodeURIComponent(state.search)}&sort_by=${encodeURIComponent(state.sortBy)}&order=${encodeURIComponent(state.sortOrder)}`);
      if(requestId!==state.requestId)return;
      const known=new Set(state.items.map(item=>item.id));
      state.items=append?[...state.items,...data.items.filter(item=>!known.has(item.id))]:data.items;
      state.page=data.page;
      state.pages=data.pages;
      state.total=data.total;
    } catch(e){ if(requestId===state.requestId)notify(e.message); }
    if(requestId===state.requestId){state.loading=false;state.loadingMore=false;render();}
  }

  function bind() {
    root.querySelector('[data-open-nav]')?.addEventListener('click',()=>{state.navOpen=true;render();});
    root.querySelectorAll('[data-close-nav]').forEach(el=>el.onclick=()=>{state.navOpen=false;render();});
    root.querySelectorAll('[data-type]').forEach(el=>el.onclick=()=>{state.navOpen=false;state.settings=false;state.siteSettingsPage=false;state.active=el.dataset.type;state.search='';state.selected.clear();const type=activeType();state.sortBy=type.config.sort_by;state.sortOrder=type.config.sort_order;loadItems();});
    root.querySelector('[data-settings]')?.addEventListener('click',()=>{state.navOpen=false;state.siteSettingsPage=false;state.settings=true;render();});
    root.querySelector('[data-site-settings]')?.addEventListener('click',()=>{state.navOpen=false;state.settings=false;state.siteSettingsPage=true;render();});
    root.querySelector('[data-new]')?.addEventListener('click',()=>{state.editor={id:0,post_type:state.active,title:'',excerpt:'',status:'draft',fields:activeType().fields,values:{}};render();});
    root.querySelectorAll('[data-edit]').forEach(el=>el.onclick=async()=>{try{state.editor=await api(`post/${el.dataset.edit}`);render();}catch(e){notify(e.message)}});
    root.querySelectorAll('[data-delete]').forEach(el=>el.onclick=async()=>{if(!confirm('Move this item to the trash?'))return;try{await api(`post/${el.dataset.delete}`,{method:'DELETE'});notify('Moved to trash');loadItems();}catch(e){notify(e.message)}});
    root.querySelector('[data-close]')?.addEventListener('click',()=>{state.editor=null;render();});
    root.querySelector('[data-save]')?.addEventListener('click',savePost);
    root.querySelector('[data-save-settings]')?.addEventListener('click',saveSettings);
    root.querySelector('[data-save-site-settings]')?.addEventListener('click',saveSiteSettings);
    root.querySelector('[data-pick-site-icon]')?.addEventListener('click',openSiteIconPicker);
    root.querySelector('[data-remove-site-icon]')?.addEventListener('click',()=>{state.siteSettings.site_icon_id=0;state.siteSettings.site_icon_url='';render();});
    root.querySelectorAll('[data-enable]').forEach(el=>el.onchange=()=>{root.querySelector(`[data-config="${el.dataset.enable}"]`).hidden=!el.checked;});
    root.querySelectorAll('[data-icon-input]').forEach(el=>el.oninput=()=>{el.closest('.tvcd-field').querySelector('.tvcd-fa-preview i').className=el.value;});
    root.querySelectorAll('[data-appearance]').forEach(el=>el.oninput=()=>{const text=root.querySelector(`[data-color-text="${el.dataset.appearance}"]`);text.value=el.value;state.appearance[el.dataset.appearance]=el.value;applyAppearance();});
    root.querySelectorAll('[data-color-text]').forEach(el=>el.onchange=()=>{if(/^#[0-9a-f]{6}$/i.test(el.value)){const picker=root.querySelector(`[data-appearance="${el.dataset.colorText}"]`);picker.value=el.value;state.appearance[el.dataset.colorText]=el.value;applyAppearance();}});
    root.querySelectorAll('[data-pick-logo]').forEach(el=>el.onclick=()=>openLogoPicker(el.dataset.pickLogo));
    root.querySelectorAll('[data-remove-logo]').forEach(el=>el.onclick=()=>{const key=el.dataset.removeLogo;state.appearance[`${key}_id`]=0;state.appearance[`${key}_url`]='';render();});
    const sortBy=root.querySelector('[data-sort-by]');if(sortBy){sortBy.value=state.sortBy;sortBy.onchange=()=>{state.sortBy=sortBy.value;state.selected.clear();loadItems();};}
    const sortOrder=root.querySelector('[data-sort-order]');if(sortOrder){sortOrder.value=state.sortOrder;sortOrder.onchange=()=>{state.sortOrder=sortOrder.value;state.selected.clear();loadItems();};}
    root.querySelectorAll('[data-select-post]').forEach(el=>el.onchange=()=>{el.checked?state.selected.add(Number(el.dataset.selectPost)):state.selected.delete(Number(el.dataset.selectPost));render();});
    root.querySelector('[data-select-all]')?.addEventListener('change',e=>{state.selected.clear();if(e.target.checked)state.items.forEach(item=>state.selected.add(item.id));render();});
    root.querySelector('[data-clear-selection]')?.addEventListener('click',()=>{state.selected.clear();render();});
    root.querySelector('[data-apply-bulk]')?.addEventListener('click',applyBulkAction);
    root.querySelector('[data-install-app]')?.addEventListener('click',installApp);
    root.querySelector('[data-check-plugin-update]')?.addEventListener('click',checkPluginUpdate);
    root.querySelector('[data-update-plugin]')?.addEventListener('click',updatePlugin);
    const loadMore=root.querySelector('[data-load-more]');
    if(loadMore){
      const observer=new IntersectionObserver(entries=>{if(entries.some(entry=>entry.isIntersecting)){observer.disconnect();loadItems(true);}}, {rootMargin:'500px 0px'});
      observer.observe(loadMore);
    }
    root.querySelectorAll('[data-tab]').forEach(el=>el.onclick=()=>{const group=el.closest('[data-field-group]');group.querySelectorAll('[data-tab]').forEach(t=>t.classList.toggle('active',t===el));group.querySelectorAll('[data-tab-panel]').forEach(panel=>panel.hidden=panel.dataset.tabPanel!==el.dataset.tab);requestAnimationFrame(alignFieldRows);});
    root.querySelectorAll('[data-pick-media]').forEach(el=>el.onclick=()=>openMedia(el.closest('[data-media]')));
    root.querySelectorAll('[data-clear-media]').forEach(el=>el.onclick=()=>{const box=el.closest('[data-media]');box.querySelector('[data-field]').value='';box.querySelector('.tvcd-media-preview').innerHTML='';el.remove();});
    root.querySelectorAll('[data-remove-gallery]').forEach(el=>bindGalleryRemove(el.closest('[data-media]')));
    bindRepeaters();
    let timer; root.querySelector('[data-search]')?.addEventListener('input',e=>{state.search=e.target.value;clearTimeout(timer);timer=setTimeout(loadItems,350);});
  }

  async function savePost() {
    const e=state.editor, data={post_type:e.post_type,fields:{}};
    root.querySelectorAll('[data-core]').forEach(el=>data[el.dataset.core]=el.value);
    root.querySelectorAll('[data-field]').forEach(el=>{if(!el.closest('[data-row]')&&!el.closest('[data-acf-group]'))data.fields[el.dataset.field]=el.value;});
    root.querySelectorAll('[data-checkbox-field]').forEach(box=>{if(!box.closest('[data-row]')&&!box.closest('[data-acf-group]'))data.fields[box.dataset.checkboxField]=[...box.querySelectorAll('input:checked')].map(el=>el.value);});
    root.querySelectorAll('[data-media]').forEach(box=>{if(box.closest('[data-row]')||box.closest('[data-acf-group]'))return;const input=box.querySelector('[data-field]'),ids=input.value.split(',').filter(Boolean).map(Number);data.fields[input.dataset.field]=box.dataset.media==='gallery'?ids:(ids[0]||'');});
    root.querySelectorAll('[data-repeater]').forEach(repeater=>{if(!repeater.closest('[data-row]')&&!repeater.closest('[data-acf-group]'))data.fields[repeater.dataset.repeater]=serializeRepeater(repeater);});
    root.querySelectorAll('[data-acf-group]').forEach(group=>{if(!group.parentElement.closest('[data-row]')&&!group.parentElement.closest('[data-acf-group]'))data.fields[group.dataset.acfGroup]=serializeGroup(group);});
    try { await api(`post${e.id?'/'+e.id:''}`,{method:'POST',body:JSON.stringify(data)}); state.editor=null;notify('Content saved');loadItems(); } catch(err){notify(err.message)}
  }

  function serializeRepeater(repeater) {
    return [...repeater.querySelectorAll(':scope > .tvcd-repeater-rows > [data-row]')].map(row=>{
      const result={};
      [...row.querySelectorAll(':scope > .tvcd-repeater-fields > [data-subfield]')].forEach(wrapper=>{
        const key=wrapper.dataset.subfield;
        const nested=wrapper.querySelector(':scope > [data-repeater]');
        if(nested){result[key]=serializeRepeater(nested);return;}
        const group=wrapper.querySelector(':scope > [data-acf-group]');
        if(group){result[key]=serializeGroup(group);return;}
        const checks=wrapper.querySelector(':scope > [data-checkbox-field]');
        if(checks){result[key]=[...checks.querySelectorAll('input:checked')].map(el=>el.value);return;}
        const media=wrapper.querySelector(':scope > [data-media]');
        if(media){const ids=media.querySelector('[data-field]').value.split(',').filter(Boolean).map(Number);result[key]=media.dataset.media==='gallery'?ids:(ids[0]||'');return;}
        const input=wrapper.querySelector(':scope > [data-field]');
        result[key]=input?input.value:'';
      });
      return result;
    });
  }

  function serializeGroup(group) {
    const result={};
    [...group.querySelectorAll(':scope > .tvcd-repeater-fields > [data-subfield]')].forEach(wrapper=>{
      const key=wrapper.dataset.subfield;
      const repeater=wrapper.querySelector(':scope > [data-repeater]');
      if(repeater){result[key]=serializeRepeater(repeater);return;}
      const nested=wrapper.querySelector(':scope > [data-acf-group]');
      if(nested){result[key]=serializeGroup(nested);return;}
      const checks=wrapper.querySelector(':scope > [data-checkbox-field]');
      if(checks){result[key]=[...checks.querySelectorAll('input:checked')].map(el=>el.value);return;}
      const media=wrapper.querySelector(':scope > [data-media]');
      if(media){const ids=media.querySelector('[data-field]').value.split(',').filter(Boolean).map(Number);result[key]=media.dataset.media==='gallery'?ids:(ids[0]||'');return;}
      const input=wrapper.querySelector(':scope > [data-field]');
      result[key]=input?input.value:'';
    });
    return result;
  }

  function findField(key, fields=state.editor?.fields||[]) {
    for(const field of fields){if(field.key===key)return field;const nested=findField(key,field.sub_fields||[]);if(nested)return nested;}
    return null;
  }

  function bindRepeaters() {
    root.querySelectorAll('[data-add-row]').forEach(button=>button.onclick=()=>{
      const field=findField(button.dataset.addRow), repeater=button.closest('[data-repeater]'), rows=repeater.querySelector('.tvcd-repeater-rows');
      if(!field)return;
      rows.querySelector('.tvcd-repeater-empty')?.remove();
      rows.insertAdjacentHTML('beforeend',repeaterRow(field,{},rows.querySelectorAll(':scope > [data-row]').length));
      bindDynamicEditorControls(rows);
    });
    root.querySelectorAll('[data-remove-row]').forEach(button=>button.onclick=()=>{
      const rows=button.closest('.tvcd-repeater-rows');
      button.closest('[data-row]').remove();
      [...rows.querySelectorAll(':scope > [data-row]')].forEach((row,index)=>row.querySelector('.tvcd-repeater-head span').textContent=`Row ${index+1}`);
      if(!rows.querySelector('[data-row]'))rows.innerHTML='<div class="tvcd-repeater-empty">No rows yet</div>';
    });
  }

  function bindDynamicEditorControls(scope) {
    scope.querySelectorAll('[data-pick-media]').forEach(el=>el.onclick=()=>openMedia(el.closest('[data-media]')));
    scope.querySelectorAll('[data-clear-media]').forEach(el=>bindMediaClear(el.closest('[data-media]')));
    scope.querySelectorAll('[data-remove-gallery]').forEach(el=>bindGalleryRemove(el.closest('[data-media]')));
    bindRepeaters();
  }

  function openMedia(box) {
    if (!window.wp?.media) { notify('The WordPress media library could not be loaded.'); return; }
    const kind=box.dataset.media, multiple=kind==='gallery';
    const frame=wp.media({button:{text:'Use selected media'},library:kind==='file'?{}:{type:'image'},multiple});
    frame.on('select',()=>{
      const selected=frame.state().get('selection').toJSON();
      const input=box.querySelector('[data-field]');
      const existing=multiple?input.value.split(',').filter(Boolean).map(Number):[];
      const additions=selected.filter(item=>!existing.includes(Number(item.id)));
      const items=multiple?additions:selected.slice(0,1);
      input.value=(multiple?[...existing,...items.map(item=>item.id)]:items.map(item=>item.id)).join(',');
      const markup=items.map(item=>mediaPreviewMarkup(kind,{...item,url:item.sizes?.medium?.url||item.icon||item.url,type:item.mime})).join('');
      if(multiple)box.querySelector('.tvcd-media-preview').insertAdjacentHTML('beforeend',markup);else box.querySelector('.tvcd-media-preview').innerHTML=markup;
      const pick=box.querySelector('[data-pick-media]');
      pick.innerHTML=`${icon(kind==='file'?'media-default':'format-image')} ${multiple?'Add photos':`Change ${kind}`}`;
      if(!box.querySelector('[data-clear-media]')) pick.insertAdjacentHTML('afterend',` <button type="button" class="tvcd-btn danger" data-clear-media>${multiple?'Remove all':'Remove'}</button>`);
      bindMediaClear(box);
      bindGalleryRemove(box);
    });
    frame.open();
  }

  function bindMediaClear(box) {
    const clear=box.querySelector('[data-clear-media]');
    if(clear) clear.onclick=()=>{box.querySelector('[data-field]').value='';box.querySelector('.tvcd-media-preview').innerHTML='';clear.remove();};
  }

  function bindGalleryRemove(box) {
    box.querySelectorAll('[data-remove-gallery]').forEach(button=>button.onclick=()=>{
      const item=button.closest('[data-media-id]');
      const removed=Number(item.dataset.mediaId);
      const input=box.querySelector('[data-field]');
      input.value=input.value.split(',').filter(Boolean).map(Number).filter(id=>id!==removed).join(',');
      item.remove();
      if(!input.value)box.querySelector('[data-clear-media]')?.remove();
    });
  }

  async function saveSettings() {
    const payload={enabled_post_types:[],post_types:{},appearance:{...state.appearance},auto_updates:!!root.querySelector('[data-auto-updates]')?.checked};
    delete payload.appearance.logo_url;
    payload.appearance.logo_id=Number(root.querySelector('[data-logo-id="logo"]')?.value||state.appearance.logo_id||0);
    payload.appearance.light_logo_id=Number(root.querySelector('[data-logo-id="light_logo"]')?.value||state.appearance.light_logo_id||0);
    root.querySelectorAll('[data-enable]:checked').forEach(el=>payload.enabled_post_types.push(el.dataset.enable));
    root.querySelectorAll('[data-config]').forEach(section=>{const c={actions:[],visible_fields:[],visible_fields_configured:true};section.querySelectorAll('[data-setting]').forEach(el=>c[el.dataset.setting]=el.type==='checkbox'?el.checked:el.value);section.querySelectorAll('[data-action]:checked').forEach(el=>c.actions.push(el.dataset.action));section.querySelectorAll('[data-visible-field]:checked').forEach(el=>c.visible_fields.push(el.dataset.visibleField));payload.post_types[section.dataset.config]=c;});
    try { await api('settings',{method:'POST',body:JSON.stringify(payload)}); const data=await api('bootstrap');state.types=data.postTypes;state.appearance=data.settings.appearance;state.autoUpdates=!!data.settings.auto_updates;state.active=state.types.find(t=>t.enabled)?.name||null;notify('Dashboard updated');render(); } catch(err){notify(err.message)}
  }

  function openLogoPicker(key) {
    if(!window.wp?.media){notify('The WordPress media library could not be loaded.');return;}
    const frame=wp.media({button:{text:'Use this logo'},library:{type:'image'},multiple:false});
    frame.on('select',()=>{const item=frame.state().get('selection').toJSON()[0];state.appearance[`${key}_id`]=item.id;state.appearance[`${key}_url`]=item.sizes?.medium?.url||item.url;render();});
    frame.open();
  }

  async function applyBulkAction() {
    const action=root.querySelector('[data-bulk-action]')?.value;
    if(!action){notify('Choose a bulk action.');return;}
    if(action==='trash'&&!confirm(`Move ${state.selected.size} items to the trash?`))return;
    try{const result=await api('bulk',{method:'POST',body:JSON.stringify({ids:[...state.selected],action})});state.selected.clear();notify(`${result.count} items updated`);loadItems();}catch(err){notify(err.message);}
  }

  async function saveSiteSettings() {
    const payload={};
    root.querySelectorAll('[data-site-field]').forEach(el=>payload[el.dataset.siteField]=el.value);
    payload.site_icon_id=Number(root.querySelector('[data-site-icon-id]')?.value||state.siteSettings.site_icon_id||0);
    try{state.siteSettings=await api('site-settings',{method:'POST',body:JSON.stringify(payload)});notify('Site settings updated');render();}catch(err){notify(err.message);}
  }

  function openSiteIconPicker() {
    if(!window.wp?.media){notify('The WordPress media library could not be loaded.');return;}
    const frame=wp.media({button:{text:'Use as site icon'},library:{type:'image'},multiple:false});
    frame.on('select',()=>{const item=frame.state().get('selection').toJSON()[0];state.siteSettings.site_icon_id=item.id;state.siteSettings.site_icon_url=item.sizes?.thumbnail?.url||item.url;render();});
    frame.open();
  }

  async function installApp() {
    if(!state.installPrompt)return;
    state.installPrompt.prompt();
    await state.installPrompt.userChoice;
    state.installPrompt=null;
    render();
  }

  async function checkPluginUpdate() {
    state.checkingUpdate=true;render();
    try{state.updateStatus=await api('update-status');}catch(err){notify(err.message);}
    state.checkingUpdate=false;render();
  }

  async function updatePlugin() {
    state.updatingPlugin=true;render();
    try{const result=await api('update-plugin',{method:'POST',body:'{}'});notify(result.message||'Plugin updated');setTimeout(()=>location.reload(),900);}
    catch(err){state.updatingPlugin=false;render();notify(err.message);}
  }

  function alignFieldRows() {
    root.querySelectorAll('.tvcd-group-fields:not([hidden]),.tvcd-repeater-fields').forEach(container=>{
      const fields=[...container.children].filter(el=>el.classList.contains('tvcd-field'));
      fields.forEach(field=>{const label=field.querySelector(':scope > label'),note=field.querySelector(':scope > .tvcd-instructions');if(label)label.style.minHeight='';if(note)note.style.minHeight='';});
      const rows={};
      fields.forEach(field=>{const key=Math.round(field.offsetTop);(rows[key]||(rows[key]=[])).push(field);});
      Object.values(rows).forEach(row=>{
        const labelHeight=Math.max(...row.map(field=>field.querySelector(':scope > label')?.scrollHeight||0));
        const noteHeight=Math.max(...row.map(field=>field.querySelector(':scope > .tvcd-instructions')?.scrollHeight||0));
        row.forEach(field=>{const label=field.querySelector(':scope > label'),note=field.querySelector(':scope > .tvcd-instructions');if(label)label.style.minHeight=`${labelHeight}px`;if(note)note.style.minHeight=`${noteHeight}px`;});
      });
    });
  }

  function updateMobileNavAlignment() {
    if(!matchMedia('(max-width:800px)').matches)return;
    const nav=root.querySelector('.tvcd-nav');
    if(!nav)return;
    const buttons=[...nav.querySelectorAll('button')];
    const styles=getComputedStyle(nav);
    const gap=parseFloat(styles.columnGap||styles.gap)||0;
    const required=buttons.reduce((width,button)=>width+button.offsetWidth,0)+Math.max(0,buttons.length-1)*gap;
    nav.classList.toggle('is-scrollable',required>nav.clientWidth+1);
    nav.querySelector('button.active')?.scrollIntoView({block:'nearest',inline:'nearest'});
  }

  window.addEventListener('resize',()=>requestAnimationFrame(()=>{alignFieldRows();updateMobileNavAlignment();}));
  window.addEventListener('keydown',event=>{if(event.key==='Escape'&&state.navOpen){state.navOpen=false;render();}});
  window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();state.installPrompt=event;render();});
  window.addEventListener('appinstalled',()=>{state.installPrompt=null;notify('Content Dashboard installed');});
  if('serviceWorker' in navigator) window.addEventListener('load',()=>navigator.serviceWorker.register(boot.swUrl,{scope:new URL(boot.swUrl).pathname.replace(/sw\.js$/,'')}).catch(()=>{}));
  api('bootstrap').then(data=>{state.types=data.postTypes;state.appearance=data.settings.appearance;state.autoUpdates=!!data.settings.auto_updates;state.siteSettings=data.siteSettings||{};state.active=state.types.find(t=>t.enabled)?.name||null;const type=activeType();state.sortBy=type?.config.sort_by||'modified';state.sortOrder=type?.config.sort_order||'DESC';loadItems();}).catch(e=>{state.loading=false;render();notify(e.message)});
})();
