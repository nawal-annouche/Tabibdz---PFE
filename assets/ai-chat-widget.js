(function () {
  var allowedPaths = {
    '/': true,
    '/index.html': true,
    '/tabibdz-homepage.html': true,
    '/tabibdz-recherche.html': true,
    '/tabibdz-a-propos.html': true,
    '/tabibdz-contact.html': true,
    '/tabibdz-comment-ca-marche.html': true
  };
  var currentPath = String(window.location.pathname || '/');
  if (currentPath.length > 1) {
    currentPath = currentPath.replace(/\/+$/, '');
  }
  if (!allowedPaths[currentPath]) {
    return;
  }

  if (window.TabibDZAIChatLoaded) {
    return;
  }
  window.TabibDZAIChatLoaded = true;

  /* ── FONTS ── */
  var fontLink = document.createElement('link');
  fontLink.rel = 'stylesheet';
  fontLink.href = 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Syne:wght@500;600;700&display=swap';
  document.head.appendChild(fontLink);

  /* ── STYLES ── */
  var style = document.createElement('style');
  style.textContent = [
    ':root {',
    '  --tbc-jade: #0f9b6e;',
    '  --tbc-jade-dk: #0b7654;',
    '  --tbc-jade-lt: #e0f5ee;',
    '  --tbc-ink: #0d1b14;',
    '  --tbc-ink2: #2a3d34;',
    '  --tbc-muted: #94a89e;',
    '  --tbc-surface: #f5faf7;',
    '  --tbc-white: #ffffff;',
    '  --tbc-border: rgba(15,155,110,0.14);',
    '  --tbc-border-hd: rgba(15,155,110,0.28);',
    '  --tbc-shadow: 0 24px 72px rgba(7,32,19,0.22);',
    '  --tbc-launcher-right: 22px;',
    '  --tbc-launcher-bottom: 22px;',
    '  --tbc-panel-right: 22px;',
    '  --tbc-panel-bottom: 90px;',
    '  --tbc-sans: "DM Sans", "Inter", sans-serif;',
    '  --tbc-display: "Syne", "Plus Jakarta Sans", sans-serif;',
    '}',

    /* LAUNCHER */
    '.tbc-launcher {',
    '  position: fixed;',
    '  left: auto;',
    '  right: var(--tbc-launcher-right);',
    '  bottom: var(--tbc-launcher-bottom);',
    '  z-index: 1200;',
    '  border: 0;',
    '  border-radius: 999px;',
    '  background: var(--tbc-ink);',
    '  color: #fff;',
    '  padding: 13px 20px;',
    '  min-width: 64px;',
    '  box-shadow: 0 12px 38px rgba(13,27,20,0.32);',
    '  display: inline-flex;',
    '  align-items: center;',
    '  gap: 9px;',
    '  font: 600 13.5px/1 var(--tbc-sans);',
    '  cursor: pointer;',
    '  transition: box-shadow .18s ease, background .18s ease, right .18s ease, bottom .18s ease;',
    '}',
    '.tbc-launcher:hover {',
    '  background: var(--tbc-jade);',
    '  box-shadow: 0 18px 48px rgba(15,155,110,0.35);',
    '}',
    '.tbc-launcher svg { width: 19px; height: 19px; flex: 0 0 auto; }',
    '.tbc-launcher-label { white-space: nowrap; }',

    /* PANEL */
    '.tbc-panel {',
    '  position: fixed;',
    '  left: auto;',
    '  right: var(--tbc-panel-right);',
    '  bottom: var(--tbc-panel-bottom);',
    '  width: min(400px, calc(100vw - 28px));',
    '  height: min(630px, calc(100vh - 116px));',
    '  z-index: 1200;',
    '  display: flex;',
    '  flex-direction: column;',
    '  background: var(--tbc-white);',
    '  border: 1px solid var(--tbc-border);',
    '  border-radius: 22px;',
    '  box-shadow: var(--tbc-shadow);',
    '  overflow: hidden;',
    '  transform: translateY(16px) scale(.97);',
    '  opacity: 0;',
    '  pointer-events: none;',
    '  transition: opacity .2s ease, transform .2s ease, right .18s ease, bottom .18s ease;',
    '}',
    '.tbc-panel.open { opacity: 1; pointer-events: auto; transform: translateY(0) scale(1); }',
    '.tbc-launcher-inline {',
    '  position: relative;',
    '  top: auto;',
    '  right: auto;',
    '  bottom: auto;',
    '  z-index: 1;',
    '  min-width: 0;',
    '  height: 40px;',
    '  padding: 0 14px;',
    '  border-radius: 12px;',
    '  box-shadow: none;',
    '  background: var(--tbc-white);',
    '  color: var(--tbc-ink);',
    '  border: 1px solid var(--tbc-border);',
    '}',
    '.tbc-launcher-inline:hover {',
    '  background: var(--tbc-jade-lt);',
    '  color: var(--tbc-jade-dk);',
    '  box-shadow: none;',
    '}',
    '.tbc-launcher-inline .tbc-launcher-label { display: inline; }',
    '.tbc-panel-navbar { top: 74px; right: 22px; }',

    /* HEADER */
    '.tbc-header {',
    '  padding: 20px 20px 18px;',
    '  background: var(--tbc-ink);',
    '  position: relative;',
    '  overflow: hidden;',
    '  flex-shrink: 0;',
    '}',
    '.tbc-header::before {',
    '  content: "";',
    '  position: absolute;',
    '  top: -30px; right: -30px;',
    '  width: 120px; height: 120px;',
    '  border-radius: 50%;',
    '  background: rgba(15,155,110,0.18);',
    '  pointer-events: none;',
    '}',
    '.tbc-header::after {',
    '  content: "";',
    '  position: absolute;',
    '  bottom: -22px; left: 50px;',
    '  width: 80px; height: 80px;',
    '  border-radius: 50%;',
    '  background: rgba(15,155,110,0.10);',
    '  pointer-events: none;',
    '}',
    '.tbc-head-top {',
    '  display: flex;',
    '  align-items: center;',
    '  justify-content: space-between;',
    '  margin-bottom: 14px;',
    '}',
    '.tbc-brand {',
    '  display: flex;',
    '  align-items: center;',
    '  gap: 11px;',
    '}',
    '.tbc-brand-icon {',
    '  width: 38px; height: 38px;',
    '  border-radius: 13px;',
    '  background: var(--tbc-jade);',
    '  display: flex; align-items: center; justify-content: center;',
    '  flex-shrink: 0;',
    '}',
    '.tbc-brand-icon svg { width: 18px; height: 18px; }',
    '.tbc-brand-name {',
    '  font: 600 15px/1.1 var(--tbc-display);',
    '  color: #fff;',
    '  letter-spacing: -0.2px;',
    '}',
    '.tbc-brand-sub {',
    '  font: 400 11px/1.2 var(--tbc-sans);',
    '  color: rgba(255,255,255,0.5);',
    '  margin-top: 2px;',
    '}',
    '.tbc-close {',
    '  width: 32px; height: 32px;',
    '  border: none;',
    '  border-radius: 10px;',
    '  background: rgba(255,255,255,0.08);',
    '  color: rgba(255,255,255,0.7);',
    '  font-size: 17px;',
    '  cursor: pointer;',
    '  display: flex; align-items: center; justify-content: center;',
    '  transition: background .15s;',
    '  line-height: 1;',
    '}',
    '.tbc-close:hover { background: rgba(255,255,255,0.15); }',
    '.tbc-status {',
    '  display: flex; align-items: center; gap: 7px;',
    '  margin-bottom: 13px;',
    '}',
    '.tbc-dot {',
    '  width: 7px; height: 7px;',
    '  border-radius: 50%;',
    '  background: #4ade80;',
    '  box-shadow: 0 0 0 2px rgba(74,222,128,0.25);',
    '  animation: tbc-pulse 2s infinite;',
    '}',
    '@keyframes tbc-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }',
    '.tbc-status-txt { font: 400 11.5px/1 var(--tbc-sans); color: rgba(255,255,255,0.45); }',
    '.tbc-chips { display: flex; gap: 6px; flex-wrap: wrap; }',
    '.tbc-chip {',
    '  font: 500 10.5px/1 var(--tbc-sans);',
    '  color: rgba(255,255,255,0.6);',
    '  background: rgba(255,255,255,0.08);',
    '  border: 1px solid rgba(255,255,255,0.12);',
    '  border-radius: 999px;',
    '  padding: 4px 10px;',
    '}',

    /* BODY */
    '.tbc-body {',
    '  flex: 1;',
    '  overflow-y: auto;',
    '  padding: 18px 16px;',
    '  background: var(--tbc-surface);',
    '  display: flex;',
    '  flex-direction: column;',
    '  gap: 12px;',
    '  scroll-behavior: smooth;',
    '}',
    '.tbc-body::-webkit-scrollbar { width: 4px; }',
    '.tbc-body::-webkit-scrollbar-track { background: transparent; }',
    '.tbc-body::-webkit-scrollbar-thumb { background: var(--tbc-border-hd); border-radius: 99px; }',

    /* MESSAGES */
    '.tbc-msg { display: flex; align-items: flex-end; gap: 8px; }',
    '.tbc-msg.bot { justify-content: flex-start; }',
    '.tbc-msg.user { justify-content: flex-end; }',
    '.tbc-avatar {',
    '  width: 28px; height: 28px;',
    '  border-radius: 10px;',
    '  background: var(--tbc-ink);',
    '  flex-shrink: 0;',
    '  display: flex; align-items: center; justify-content: center;',
    '}',
    '.tbc-avatar svg { width: 13px; height: 13px; }',
    '.tbc-bubble {',
    '  max-width: 82%;',
    '  border-radius: 16px;',
    '  padding: 11px 14px;',
    '  font: 400 13.5px/1.55 var(--tbc-sans);',
    '  word-break: break-word;',
    '  white-space: pre-wrap;',
    '}',
    '.tbc-msg.bot .tbc-bubble {',
    '  background: var(--tbc-white);',
    '  color: var(--tbc-ink);',
    '  border: 1px solid var(--tbc-border);',
    '  border-bottom-left-radius: 4px;',
    '}',
    '.tbc-msg.user .tbc-bubble {',
    '  background: var(--tbc-jade);',
    '  color: #fff;',
    '  border-bottom-right-radius: 4px;',
    '}',

    /* TYPING */
    '.tbc-typing { display: inline-flex; gap: 5px; align-items: center; padding: 2px 0; }',
    '.tbc-typing span {',
    '  width: 6px; height: 6px;',
    '  border-radius: 50%;',
    '  background: var(--tbc-jade);',
    '  opacity: 0.4;',
    '  animation: tbc-bounce 1.1s infinite ease-in-out;',
    '}',
    '.tbc-typing span:nth-child(2) { animation-delay: .18s; }',
    '.tbc-typing span:nth-child(3) { animation-delay: .36s; }',
    '@keyframes tbc-bounce { 0%,80%,100%{transform:translateY(0);opacity:.4} 40%{transform:translateY(-5px);opacity:1} }',

    /* SUGGESTIONS */
    '.tbc-suggestions { display: flex; flex-direction: column; gap: 6px; }',
    '.tbc-suggestion {',
    '  display: flex; align-items: center; gap: 10px;',
    '  background: var(--tbc-white);',
    '  border: 1px solid var(--tbc-border);',
    '  border-radius: 13px;',
    '  padding: 10px 13px;',
    '  cursor: pointer;',
    '  font: 500 12.5px/1.3 var(--tbc-sans);',
    '  color: var(--tbc-ink2);',
    '  text-align: left;',
    '  transition: border-color .15s, background .15s, color .15s;',
    '}',
    '.tbc-suggestion:hover {',
    '  border-color: var(--tbc-jade);',
    '  background: var(--tbc-jade-lt);',
    '  color: var(--tbc-jade-dk);',
    '}',
    '.tbc-sugg-icon {',
    '  width: 26px; height: 26px;',
    '  border-radius: 8px;',
    '  background: var(--tbc-surface);',
    '  display: flex; align-items: center; justify-content: center;',
    '  flex-shrink: 0;',
    '  font-size: 13px;',
    '}',

    /* SOURCES */
    '.tbc-sources {',
    '  margin-top: 8px;',
    '  padding-top: 8px;',
    '  border-top: 1px solid var(--tbc-border);',
    '  display: flex; flex-direction: column; gap: 5px;',
    '}',
    '.tbc-source {',
    '  font: 600 11px/1.4 var(--tbc-sans);',
    '  color: var(--tbc-jade-dk);',
    '  text-decoration: none;',
    '  display: flex; align-items: center; gap: 5px;',
    '}',
    '.tbc-source::before {',
    '  content: "";',
    '  display: inline-block;',
    '  width: 4px; height: 4px;',
    '  border-radius: 50%;',
    '  background: var(--tbc-jade);',
    '  flex-shrink: 0;',
    '}',

    /* FOOTER */
    '.tbc-footer {',
    '  padding: 13px 15px 15px;',
    '  background: var(--tbc-white);',
    '  border-top: 1px solid var(--tbc-border);',
    '  flex-shrink: 0;',
    '}',
    '.tbc-quick-bar {',
    '  display: flex; gap: 6px;',
    '  margin-bottom: 10px;',
    '  overflow-x: auto;',
    '  scrollbar-width: none;',
    '}',
    '.tbc-quick-bar::-webkit-scrollbar { display: none; }',
    '.tbc-quick-pill {',
    '  font: 500 11.5px/1 var(--tbc-sans);',
    '  color: var(--tbc-jade-dk);',
    '  background: var(--tbc-jade-lt);',
    '  border: 1px solid rgba(15,155,110,0.2);',
    '  border-radius: 999px;',
    '  padding: 5px 11px;',
    '  cursor: pointer;',
    '  white-space: nowrap;',
    '  flex-shrink: 0;',
    '  transition: background .15s;',
    '}',
    '.tbc-quick-pill:hover { background: rgba(15,155,110,0.2); }',
    '.tbc-form { display: flex; gap: 9px; align-items: flex-end; }',
    '.tbc-input {',
    '  flex: 1;',
    '  min-height: 46px;',
    '  max-height: 112px;',
    '  resize: none;',
    '  border: 1.5px solid var(--tbc-border-hd);',
    '  border-radius: 14px;',
    '  padding: 12px 14px;',
    '  outline: none;',
    '  font: 400 13px/1.45 var(--tbc-sans);',
    '  color: var(--tbc-ink);',
    '  background: var(--tbc-surface);',
    '  transition: border-color .15s, box-shadow .15s, background .15s;',
    '}',
    '.tbc-input::placeholder { color: var(--tbc-muted); }',
    '.tbc-input:focus {',
    '  border-color: var(--tbc-jade);',
    '  box-shadow: 0 0 0 3px rgba(15,155,110,0.1);',
    '  background: var(--tbc-white);',
    '}',
    '.tbc-submit {',
    '  width: 46px; height: 46px;',
    '  border: none;',
    '  border-radius: 14px;',
    '  background: var(--tbc-jade);',
    '  color: #fff;',
    '  cursor: pointer;',
    '  display: flex; align-items: center; justify-content: center;',
    '  flex-shrink: 0;',
    '  transition: background .15s, transform .1s;',
    '}',
    '.tbc-submit:hover { background: var(--tbc-jade-dk); }',
    '.tbc-submit:active { transform: scale(0.95); }',
    '.tbc-submit[disabled] { opacity: .55; cursor: wait; }',
    '.tbc-submit svg { width: 17px; height: 17px; }',
    '.tbc-meta {',
    '  margin-top: 9px;',
    '  display: flex; align-items: center; gap: 5px;',
    '  font: 400 10.5px/1.4 var(--tbc-sans);',
    '  color: var(--tbc-muted);',
    '}',

    /* MOBILE */
    '@media (max-width: 640px) {',
    '  .tbc-launcher { right: 14px; bottom: 14px; padding: 14px; }',
    '  .tbc-launcher-label { display: none; }',
    '  .tbc-panel { right: 10px; left: 10px; width: auto; bottom: 80px; height: min(72vh, 630px); }',
    '}'
  ].join('\n');
  document.head.appendChild(style);

  /* ── LAUNCHER ── */
  var launcher = document.createElement('button');
  launcher.type = 'button';
  launcher.className = 'tbc-launcher';
  launcher.setAttribute('aria-label', 'Ouvrir le chat IA');
  launcher.style.position = 'fixed';
  launcher.style.right = '22px';
  launcher.style.bottom = '22px';
  launcher.style.left = 'auto';
  launcher.style.top = 'auto';
  launcher.style.zIndex = '1200';
  launcher.innerHTML =
    '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true">' +
      '<path d="M4 7.5C4 5.567 5.567 4 7.5 4h9C18.433 4 20 5.567 20 7.5v5C20 14.433 18.433 16 16.5 16H10l-4 4v-4.3C4.836 15.085 4 13.87 4 12.5z" fill="currentColor" opacity=".25"/>' +
      '<path d="M8 9.5h8M8 12.5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>' +
    '</svg>' +
    '<span class="tbc-launcher-label">Chat IA</span>';

  /* ── PANEL ── */
  var panel = document.createElement('aside');
  panel.className = 'tbc-panel';
  panel.setAttribute('aria-hidden', 'true');
  panel.style.position = 'fixed';
  panel.style.right = '22px';
  panel.style.bottom = '90px';
  panel.style.left = 'auto';
  panel.style.top = 'auto';
  panel.style.zIndex = '1200';
  panel.style.display = 'flex';
  panel.style.opacity = '0';
  panel.style.pointerEvents = 'none';
  panel.innerHTML =
    '<div class="tbc-header">' +
      '<div class="tbc-head-top">' +
        '<div class="tbc-brand">' +
          '<div class="tbc-brand-icon">' +
            '<svg viewBox="0 0 24 24" fill="none">' +
              '<circle cx="12" cy="12" r="3" fill="white"/>' +
              '<path d="M12 3v18M6 9h12" stroke="white" stroke-width="1.8" stroke-linecap="round"/>' +
            '</svg>' +
          '</div>' +
          '<div>' +
            '<div class="tbc-brand-name">TabibDZ</div>' +
            '<div class="tbc-brand-sub">Assistant médical IA</div>' +
          '</div>' +
        '</div>' +
        '<button type="button" class="tbc-close" aria-label="Fermer">&#x2715;</button>' +
      '</div>' +
      '<div class="tbc-status">' +
        '<div class="tbc-dot"></div>' +
        '<span class="tbc-status-txt">En ligne &middot; Répond instantanément</span>' +
      '</div>' +
      '<div class="tbc-chips">' +
        '<span class="tbc-chip">Rendez-vous</span>' +
        '<span class="tbc-chip">Médecins</span>' +
        '<span class="tbc-chip">Spécialités</span>' +
        '<span class="tbc-chip">Aide</span>' +
      '</div>' +
    '</div>' +
    '<div class="tbc-body" id="tbcBody">' +
      '<div class="tbc-msg bot">' +
        '<div class="tbc-avatar">' +
          '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" fill="white"/><path d="M12 3v18M6 9h12" stroke="white" stroke-width="1.8" stroke-linecap="round"/></svg>' +
        '</div>' +
        '<div class="tbc-bubble">Bonjour 👋 Je peux vous aider à trouver un médecin, comprendre une page ou préparer votre prise de rendez-vous.</div>' +
      '</div>' +
      '<div class="tbc-suggestions" id="tbcSuggestions">' +
        '<button type="button" class="tbc-suggestion"><span class="tbc-sugg-icon">🫀</span>Je cherche un cardiologue</button>' +
        '<button type="button" class="tbc-suggestion"><span class="tbc-sugg-icon">📅</span>Comment prendre un rendez-vous ?</button>' +
        '<button type="button" class="tbc-suggestion"><span class="tbc-sugg-icon">🔬</span>Expliquez-moi les spécialités</button>' +
      '</div>' +
    '</div>' +
    '<div class="tbc-footer">' +
      '<div class="tbc-quick-bar" id="tbcQuickBar">' +
        '<button type="button" class="tbc-quick-pill">Urgence</button>' +
        '<button type="button" class="tbc-quick-pill">Généraliste</button>' +
        '<button type="button" class="tbc-quick-pill">Pédiatre</button>' +
        '<button type="button" class="tbc-quick-pill">Dermatologue</button>' +
        '<button type="button" class="tbc-quick-pill">Ophtalmologue</button>' +
      '</div>' +
      '<form class="tbc-form">' +
        '<textarea class="tbc-input" placeholder="Écrivez votre question..." rows="1"></textarea>' +
        '<button type="submit" class="tbc-submit" aria-label="Envoyer">' +
          '<svg viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
        '</button>' +
      '</form>' +
      '<div class="tbc-meta">&#9679; Les réponses IA peuvent contenir des erreurs. Vérifiez les informations sensibles avant décision.</div>' +
    '</div>';

  document.body.appendChild(panel);
  document.body.appendChild(launcher);

  function findNavbarMount() {
    var actionSelectors = ['.nav-actions', '.nav-right', '.top-actions'];
    var notificationSelectors = ['.nav-notif-btn', '.notif-btn'];
    var actions = null;
    for (var i = 0; i < actionSelectors.length; i++) {
      actions = document.querySelector(actionSelectors[i]);
      if (actions) { break; }
    }
    if (!actions) { return null; }

    var notificationNode = null;
    for (var j = 0; j < notificationSelectors.length; j++) {
      notificationNode = actions.querySelector(notificationSelectors[j]);
      if (notificationNode) { break; }
    }

    return { actions: actions, notificationNode: notificationNode };
  }

  if (launcher.parentNode !== document.body) {
    document.body.appendChild(launcher);
  }

  /* ── REFS ── */
  var body       = panel.querySelector('.tbc-body');
  var form       = panel.querySelector('.tbc-form');
  var input      = panel.querySelector('.tbc-input');
  var submit     = panel.querySelector('.tbc-submit');
  var closeBtn   = panel.querySelector('.tbc-close');
  var suggsWrap  = panel.querySelector('#tbcSuggestions');
  var quickBar   = panel.querySelector('#tbcQuickBar');

  /* ── HELPERS ── */
  function setOpen(isOpen) {
    panel.classList.toggle('open', isOpen);
    panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    panel.style.opacity = isOpen ? '1' : '0';
    panel.style.pointerEvents = isOpen ? 'auto' : 'none';
    panel.style.transform = isOpen ? 'translateY(0) scale(1)' : 'translateY(16px) scale(.97)';
    if (isOpen) {
      setTimeout(function () { input.focus(); }, 60);
    }
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 112) + 'px';
  }

  /* ── APPEND MESSAGE ── */
  function appendMessage(role, text, sources) {
    var message = document.createElement('div');
    message.className = 'tbc-msg ' + role;

    if (role === 'bot') {
      var avatar = document.createElement('div');
      avatar.className = 'tbc-avatar';
      avatar.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none">' +
          '<circle cx="12" cy="12" r="3" fill="white"/>' +
          '<path d="M12 3v18M6 9h12" stroke="white" stroke-width="1.8" stroke-linecap="round"/>' +
        '</svg>';
      message.appendChild(avatar);
    }

    var bubble = document.createElement('div');
    bubble.className = 'tbc-bubble';
    bubble.textContent = text;

    if (role === 'bot' && Array.isArray(sources) && sources.length) {
      var sourcesWrap = document.createElement('div');
      sourcesWrap.className = 'tbc-sources';
      sources.slice(0, 3).forEach(function (source) {
        if (!source || !source.url) { return; }
        var link = document.createElement('a');
        link.className = 'tbc-source';
        link.href = source.url;
        link.target = '_blank';
        link.rel = 'noreferrer';
        link.textContent = source.title || source.url;
        sourcesWrap.appendChild(link);
      });
      if (sourcesWrap.childNodes.length) {
        bubble.appendChild(sourcesWrap);
      }
    }

    message.appendChild(bubble);
    body.appendChild(message);
    body.scrollTop = body.scrollHeight;
  }

  /* ── TYPING INDICATOR ── */
  function appendTyping() {
    var message = document.createElement('div');
    message.className = 'tbc-msg bot';
    message.dataset.typing = 'true';
    message.innerHTML =
      '<div class="tbc-avatar">' +
        '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="3" fill="white"/><path d="M12 3v18M6 9h12" stroke="white" stroke-width="1.8" stroke-linecap="round"/></svg>' +
      '</div>' +
      '<div class="tbc-bubble"><span class="tbc-typing"><span></span><span></span><span></span></span></div>';
    body.appendChild(message);
    body.scrollTop = body.scrollHeight;
    return message;
  }

  /* ── SEND MESSAGE (backend inchangé) ── */
  async function sendMessage(text) {
    var clean = (text || '').trim();
    if (!clean) { return; }

    if (suggsWrap) { suggsWrap.remove(); suggsWrap = null; }

    appendMessage('user', clean);
    input.value = '';
    input.style.height = 'auto';
    submit.disabled = true;
    var typing = appendTyping();

    try {
      var response = await fetch('/api/ai/chat/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ message: clean, research_effort: 'lite' })
      });

      var data = await response.json();
      typing.remove();

      if (!response.ok) {
        appendMessage('bot', (data && data.error) ? data.error : 'Une erreur est survenue.');
        return;
      }

      appendMessage(
        'bot',
        (data.data && data.data.content) ? data.data.content : 'Je n\'ai pas reçu de contenu.',
        data.data ? data.data.sources : []
      );
    } catch (error) {
      typing.remove();
      appendMessage('bot', 'Impossible de contacter le service IA pour le moment.');
    } finally {
      submit.disabled = false;
      body.scrollTop = body.scrollHeight;
      input.focus();
    }
  }

  /* ── EVENTS ── */
  launcher.addEventListener('click', function () {
    setOpen(!panel.classList.contains('open'));
  });

  closeBtn.addEventListener('click', function () {
    setOpen(false);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && panel.classList.contains('open')) {
      setOpen(false);
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    sendMessage(input.value);
  });

  input.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      sendMessage(input.value);
    }
  });

  input.addEventListener('input', function () {
    autoResize(input);
  });

  /* Suggestions */
  if (suggsWrap) {
    suggsWrap.querySelectorAll('.tbc-suggestion').forEach(function (btn) {
      btn.addEventListener('click', function () {
        sendMessage(btn.textContent.trim().replace(/^[^\w\s]+\s*/, ''));
      });
    });
  }

  /* Quick pills */
  quickBar.querySelectorAll('.tbc-quick-pill').forEach(function (pill) {
    pill.addEventListener('click', function () {
      input.value = pill.textContent;
      input.focus();
      autoResize(input);
    });
  });

})();



