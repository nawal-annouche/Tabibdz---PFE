(function () {
  const PUBLIC_PATHS = new Set([
    "/tabibdz-homepage.html",
    "/tabibdz-recherche.html",
    "/tabibdz-a-propos.html",
    "/tabibdz-contact.html",
    "/tabibdz-comment-ca-marche.html",
  ]);

  const NAV_ITEMS = [
    { href: "/tabibdz-homepage.html", label: "Accueil" },
    { href: "/tabibdz-recherche.html", label: "Médecins" },
    { href: "/tabibdz-a-propos.html", label: "À propos" },
    { href: "/tabibdz-contact.html", label: "Contact" },
    { href: "/tabibdz-comment-ca-marche.html", label: "Comment ça marche" },
  ];

  const FOOTER_LINKS = [
    { href: "/tabibdz-a-propos.html", label: "À propos" },
    { href: "/tabibdz-contact.html", label: "Contact" },
    { href: "/tabibdz-comment-ca-marche.html", label: "Comment ça marche" },
    { href: "/tabibdz-comment-ca-marche.html", label: "Mentions légales" },
    { href: "/tabibdz-comment-ca-marche.html", label: "Confidentialité" },
  ];

  const path = window.location.pathname;
  if (!PUBLIC_PATHS.has(path)) return;

  let readyResolved = false;
  let unreadNotifications = 0;
  let resolveReady;

  const readyPromise = new Promise((resolve) => {
    resolveReady = resolve;
  });

  const chromeState = {
    authUser: null,
    unreadNotifications: 0,
    getAuthUser() {
      return this.authUser;
    },
    getUnreadNotifications() {
      return this.unreadNotifications;
    },
    whenReady() {
      return readyPromise;
    },
  };

  window.TabibPublicChrome = chromeState;

  function safeNext(currentPath) {
    return encodeURIComponent(currentPath || "/tabibdz-homepage.html");
  }

  function buildNavLinks(asList) {
    if (asList) {
      return NAV_ITEMS.map((item) => {
        const active = item.href === path ? " class=\"active\"" : "";
        return `<li><a href="${item.href}"${active}>${item.label}</a></li>`;
      }).join("");
    }
    return NAV_ITEMS.map((item) => {
      const active = item.href === path ? " class=\"active\"" : "";
      return `<a href="${item.href}"${active}>${item.label}</a>`;
    }).join("");
  }

  async function apiGet(url) {
    const res = await fetch(url, {
      headers: { Accept: "application/json" },
      credentials: "include",
    });
    const data = await res.json().catch(() => null);
    if (!res.ok) throw new Error((data && (data.error || data.message)) || `HTTP ${res.status}`);
    return data;
  }

  function ensureNotificationBadgeStyles() {
    if (document.getElementById("shared-nav-notif-badge-styles")) return;
    const style = document.createElement("style");
    style.id = "shared-nav-notif-badge-styles";
    style.textContent = `
      .nav-notif-btn { position: relative; }
      .nav-notif-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        display: none;
        align-items: center;
        justify-content: center;
        background: #15A870;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        line-height: 1;
        border: 2px solid #fff;
      }
      .nav-notif-badge.show { display: inline-flex; }
    `;
    document.head.appendChild(style);
  }

  function notifBadgeMarkup() {
    const count = Math.max(0, Number(unreadNotifications || 0));
    const label = count > 99 ? "99+" : String(count);
    return `<span class="nav-notif-badge${count > 0 ? " show" : ""}">${count > 0 ? label : ""}</span>`;
  }

  function syncDrawerActions() {
    const desktopActions = document.querySelector(".nav-actions, .nav-right");
    const drawerActions = document.getElementById("drawer-nav-actions");
    if (desktopActions && drawerActions) {
      drawerActions.innerHTML = desktopActions.innerHTML;
    }
  }

  function publishState() {
    chromeState.unreadNotifications = unreadNotifications;
    window.TabibPublicChrome = chromeState;
    syncDrawerActions();
    document.dispatchEvent(new CustomEvent("tabibdz:public-auth-ready", {
      detail: {
        user: chromeState.authUser,
        unreadNotifications: chromeState.unreadNotifications,
      },
    }));
    if (!readyResolved) {
      readyResolved = true;
      resolveReady(chromeState);
    }
  }

  function renderFooter() {
    const footer = document.querySelector("footer");
    if (!footer) return;
    const mode = footer.dataset.sharedFooter || "";
    const hasManualContent = footer.children.length > 0 || footer.textContent.trim() !== "";
    if (mode === "preserve" || (hasManualContent && mode !== "replace")) {
      return;
    }
    footer.classList.add("site-footer");
    footer.innerHTML = `
      <a href="/tabibdz-homepage.html" class="site-footer-logo">Tabib<span>DZ</span></a>
      <div class="site-footer-links">
        ${FOOTER_LINKS.map((item) => `<a href="${item.href}">${item.label}</a>`).join("")}
      </div>
      <div class="site-footer-copy">© 2025 TabibDZ. Tous droits réservés.</div>
    `;
  }

  function renderStaticNav() {
    const navLinks = document.querySelector(".nav-links");
    if (navLinks) {
      navLinks.innerHTML = buildNavLinks(navLinks.tagName === "UL");
    }

    const logo = document.querySelector(".logo");
    if (logo && logo.tagName !== "A") {
      logo.innerHTML = `<a href="/tabibdz-homepage.html" style="color:inherit;text-decoration:none;">${logo.innerHTML}</a>`;
    }

    const navActions = document.querySelector(".nav-actions, .nav-right");
    if (navActions) {
      const next = safeNext(window.location.pathname + window.location.search);
      navActions.innerHTML = `
        <a href="/connexion/?next=${next}" class="${navActions.classList.contains("nav-right") ? "btn-ghost" : "btn btn-outline"}">Connexion</a>
        <a href="/inscription/?next=${next}" class="${navActions.classList.contains("nav-right") ? "btn-primary" : "btn btn-primary"}">S'inscrire</a>
      `;
    }
    syncDrawerActions();
  }

  function roleDashboardBase(role) {
    return role === "medecin" ? "/tabibdz-espace-medecin.html" : "/tabibdz-dashboard.html";
  }

  function roleNotificationsUrl(role) {
    return "/tabibdz-centre-notifications.html";
  }

  async function hydrateAuthState() {
    const navActions = document.querySelector(".nav-actions, .nav-right");
    if (!navActions) {
      publishState();
      return;
    }

    try {
      ensureNotificationBadgeStyles();
      const payload = await apiGet("/api/auth/me/");
      const user = payload && payload.user ? payload.user : null;
      chromeState.authUser = user;
      if (!user) {
        publishState();
        return;
      }
      if (user.role === "patient") {
        try {
          const notifications = await apiGet("/api/notifications/?unread=true");
          unreadNotifications = Number(notifications.unread_count || 0);
        } catch (_) {
          unreadNotifications = 0;
        }
      } else {
        unreadNotifications = 0;
      }

      const prenom = String(user.prenom || "");
      const nom = String(user.nom || "");
      const initials = `${(prenom[0] || "").toUpperCase()}${(nom[0] || "").toUpperCase()}` || "U";
      const shortName = user.role === "medecin" ? "Espace médecin" : `${prenom} ${(nom[0] || "").toUpperCase()}.`.trim();

      const notifClass = navActions.classList.contains("nav-right") ? "btn-ghost nav-notif-btn" : "btn nav-notif-btn";
      const chipClass = navActions.classList.contains("nav-right") ? "btn-ghost nav-user-chip" : "btn nav-user-chip";

      navActions.innerHTML = `
        <a href="${roleNotificationsUrl(user.role)}" class="${notifClass}" aria-label="Notifications">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 17H9M18 8A6 6 0 1 0 6 8c0 7-3 8-3 8h18s-3-1-3-8M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
          ${notifBadgeMarkup()}
        </a>
        <a href="${roleDashboardBase(user.role)}" class="${chipClass}">
          <span class="nav-user-avatar">${initials}</span>
          <span>${shortName}</span>
          <span class="nav-user-caret">v</span>
        </a>
      `;
    } catch (_) {
      // keep guest state
      chromeState.authUser = null;
    }
    publishState();
  }

  renderStaticNav();
  renderFooter();
  hydrateAuthState();
})();

