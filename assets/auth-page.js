import {
  setFeedback,
  clearFeedback,
  setButtonLoading,
} from "./inline-feedback.js";

const desktopState = {
  currentRole: "patient",
  activeForm: document.body.dataset.initialView || "login",
};

const mobileState = {
  currentRole: "patient",
  activeForm: document.body.dataset.initialView || "login",
};

function setActiveForm(which) {
  desktopState.activeForm = which;
  mobileState.activeForm = which;
  document.body.dataset.authView = which;
}

function nextParam() {
  return new URLSearchParams(window.location.search).get("next") || "";
}

function authPath(which) {
  const path = which === "register" ? "/inscription/" : "/connexion/";
  const next = nextParam();
  return next ? `${path}?next=${encodeURIComponent(next)}` : path;
}

function syncPath(which) {
  const target = authPath(which);
  if (`${window.location.pathname}${window.location.search}` !== target) {
    history.replaceState({}, "", target);
  }
}

function togglePassword(id, button) {
  const input = document.getElementById(id);
  if (!input) return;

  const showing = input.type === "password";
  input.type = showing ? "text" : "password";
  button?.setAttribute("aria-label", showing ? "Masquer le mot de passe" : "Afficher le mot de passe");
  const icon = button?.querySelector("svg");
  if (icon) {
    icon.innerHTML = showing
      ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
      : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  }
}

function paintStrength(ids, classBase, value) {
  let score = 0;
  if (value.length >= 8) score += 1;
  if (value.length >= 10 && /[A-Z]/.test(value)) score += 1;
  if ((/[^a-zA-Z0-9]/.test(value) || (/\d/.test(value) && /[a-z]/i.test(value))) && value.length >= 8) {
    score += 1;
  }

  const classByScore = ["", "w", "m", "s"];
  ids.forEach((id, index) => {
    const element = document.getElementById(id);
    if (!element) return;
    element.className = `${classBase}${index < score ? ` ${classByScore[score]}` : ""}`;
  });
}

function strength(input) {
  paintStrength(["s1", "s2", "s3"], "strength-segment", input.value);
}

function mStrength(input) {
  paintStrength(["ms1", "ms2", "ms3"], "m-seg", input.value);
}

function alertConfig(containerId, textId, baseClass) {
  return {
    container: document.getElementById(containerId),
    textNode: document.getElementById(textId),
    baseClass,
  };
}

function showInlineAlert(containerId, textId, message, type, baseClass) {
  setFeedback({
    ...alertConfig(containerId, textId, baseClass),
    message,
    type,
  });
}

function clearInlineAlerts(definitions) {
  definitions.forEach(({ containerId, textId, baseClass }) => {
    clearFeedback(alertConfig(containerId, textId, baseClass));
  });
}

function setPaneVisibility(oldPane, nextPane, goRight) {
  if (!oldPane || !nextPane) return;
  oldPane.classList.remove("active");
  oldPane.classList.add(goRight ? "hidden-left" : "hidden-right");
  nextPane.classList.remove("hidden-left", "hidden-right");
  requestAnimationFrame(() => requestAnimationFrame(() => nextPane.classList.add("active")));
}

function show(which) {
  const tabLogin = document.getElementById("tab-login");
  const tabRegister = document.getElementById("tab-register");
  const oldPane = document.getElementById(`fs-${desktopState.activeForm}`);
  const nextPane = document.getElementById(`fs-${which}`);
  if (!tabLogin || !tabRegister || !nextPane) {
    syncPath(which);
    return;
  }

  if (desktopState.activeForm === which) {
    setActiveForm(which);
    syncPath(which);
    return;
  }

  const goRight = which === "register";
  tabLogin.classList.toggle("active", which === "login");
  tabRegister.classList.toggle("active", which === "register");
  setPaneVisibility(oldPane, nextPane, goRight);
  setActiveForm(which);
  clearAlerts();
  syncPath(which);
}

function switchAuth(event, which) {
  if (event) event.preventDefault();
  show(which);
  return false;
}

function mShow(which) {
  const tabLogin = document.getElementById("m-tab-login");
  const tabRegister = document.getElementById("m-tab-register");
  const oldPane = document.getElementById(`m-fs-${mobileState.activeForm}`);
  const nextPane = document.getElementById(`m-fs-${which}`);
  if (!tabLogin || !tabRegister || !nextPane) return;

  if (mobileState.activeForm === which) return;

  const goRight = which === "register";
  tabLogin.classList.toggle("active", which === "login");
  tabRegister.classList.toggle("active", which === "register");
  setPaneVisibility(oldPane, nextPane, goRight);
  setActiveForm(which);
  mClearAlerts();
}

function mSwitchAuth(event, which) {
  if (event) event.preventDefault();
  mShow(which);
  return false;
}

function pickRole(card) {
  document.querySelectorAll(".role-card").forEach((item) => item.classList.remove("active"));
  card.classList.add("active");
  desktopState.currentRole = card.dataset.role || "patient";
}

function mPickRole(card) {
  document.querySelectorAll(".m-role-card").forEach((item) => item.classList.remove("active"));
  card.classList.add("active");
  mobileState.currentRole = card.dataset.role || "patient";
}

function clearAlerts() {
  clearInlineAlerts([
    { containerId: "al-login", textId: "al-login-t", baseClass: "alert tdz-feedback tdz-feedback--auth" },
    { containerId: "al-reg", textId: "al-reg-t", baseClass: "alert tdz-feedback tdz-feedback--auth" },
  ]);
}

function mClearAlerts() {
  clearInlineAlerts([
    { containerId: "mal-login", textId: "mal-login-t", baseClass: "m-alert tdz-feedback tdz-feedback--compact" },
    { containerId: "mal-reg", textId: "mal-reg-t", baseClass: "m-alert tdz-feedback tdz-feedback--compact" },
  ]);
}

function setLoading(buttonId, loading) {
  setButtonLoading({ button: document.getElementById(buttonId), loading });
}

function resolvePostLoginRedirect(user) {
  const role = user && user.role ? user.role : "patient";
  const next = nextParam();
  if (role === "medecin") {
    return next && next.startsWith("/tabibdz-espace-medecin") ? next : "/tabibdz-espace-medecin.html";
  }
  return next || "/tabibdz-dashboard.html";
}

function resolveVerificationRedirect(email = "") {
  const query = new URLSearchParams();
  const normalizedEmail = String(email || "").trim();
  const next = nextParam();
  if (normalizedEmail) query.set("email", normalizedEmail);
  if (next) query.set("next", next);
  return `/verification-email/${query.toString() ? `?${query.toString()}` : ""}`;
}

function resolvePostRegisterRedirect(email = "") {
  const query = new URLSearchParams();
  const normalizedEmail = String(email || "").trim();
  const next = nextParam();
  if (normalizedEmail) query.set("email", normalizedEmail);
  if (next) query.set("next", next);
  return `/verification-email/${query.toString() ? `?${query.toString()}` : ""}`;
}

async function postJson(url, payload) {
  const response = await fetch(url, {
    method: "POST",
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(payload),
  });

  const data = await response.json().catch(() => ({}));
  return { response, data };
}

function bindLoginForm({ formId, buttonId, containerId, textId, baseClass, clear }) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clear();
    setLoading(buttonId, true);

    const formData = new FormData(event.currentTarget);
    const payload = {
      email: String(formData.get("email") || "").trim(),
      password: String(formData.get("password") || ""),
    };

    if (!payload.email || !payload.password) {
      showInlineAlert(containerId, textId, "Saisissez votre e-mail et votre mot de passe.", "error", baseClass);
      setLoading(buttonId, false);
      return;
    }

    try {
      const { response, data } = await postJson("/api/auth/login/", payload);
      if (!response.ok && data && data.email_verification_required) {
        showInlineAlert(containerId, textId, "Email non confirmé. Entrez votre code PIN pour activer le compte.", "error", baseClass);
        localStorage.setItem("tabibdz_pending_verification_email", payload.email);
        setTimeout(() => {
          window.location.href = resolveVerificationRedirect(data.email || payload.email);
        }, 900);
        return;
      }

      if (!response.ok) {
        throw new Error(data.error || "Identifiants incorrects ou connexion impossible.");
      }

      showInlineAlert(containerId, textId, "Connexion réussie. Redirection en cours...", "success", baseClass);
      setTimeout(() => {
        window.location.href = resolvePostLoginRedirect(data.user || null);
      }, 700);
    } catch (error) {
      showInlineAlert(containerId, textId, error.message, "error", baseClass);
      setLoading(buttonId, false);
    }
  });
}

function bindRegisterForm({ formId, buttonId, containerId, textId, baseClass, clear, getRole }) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clear();

    const formData = new FormData(event.currentTarget);
    const payload = {
      prenom: String(formData.get("prenom") || "").trim(),
      nom: String(formData.get("nom") || "").trim(),
      telephone: String(formData.get("telephone") || "").trim(),
      email: String(formData.get("email") || "").trim(),
      password: String(formData.get("password") || ""),
      role: getRole(),
    };

    if (!payload.prenom || !payload.nom || !payload.telephone || !payload.email || !payload.password) {
      showInlineAlert(containerId, textId, "Complétez tous les champs requis avant de continuer.", "error", baseClass);
      return;
    }
    if (payload.password.length < 8) {
      showInlineAlert(containerId, textId, "Le mot de passe doit contenir au moins 8 caractères.", "error", baseClass);
      return;
    }

    setLoading(buttonId, true);

    try {
      const { response, data } = await postJson("/api/auth/register/", payload);
      if (!response.ok) {
        throw new Error(data.error || "Erreur lors de la création du compte. Cet email est peut-être déjà utilisé.");
      }

      showInlineAlert(containerId, textId, "Compte créé. Vérifiez votre email avec le code PIN...", "success", baseClass);
      setTimeout(() => {
        window.location.href = resolvePostRegisterRedirect(payload.email);
      }, 900);
    } catch (error) {
      showInlineAlert(containerId, textId, error.message, "error", baseClass);
      setLoading(buttonId, false);
    }
  });
}

function syncInitialDesktopView() {
  const loginTab = document.getElementById("tab-login");
  const registerTab = document.getElementById("tab-register");
  const loginPane = document.getElementById("fs-login");
  const registerPane = document.getElementById("fs-register");
  if (!loginTab || !registerTab || !loginPane || !registerPane) return;

  const isRegister = desktopState.activeForm === "register";
  loginTab.classList.toggle("active", !isRegister);
  registerTab.classList.toggle("active", isRegister);
  loginPane.className = `form-pane ${isRegister ? "hidden-left" : "active"}`;
  registerPane.className = `form-pane ${isRegister ? "active" : "hidden-right"}`;
}

function syncInitialMobileView() {
  const loginTab = document.getElementById("m-tab-login");
  const registerTab = document.getElementById("m-tab-register");
  const loginPane = document.getElementById("m-fs-login");
  const registerPane = document.getElementById("m-fs-register");
  if (!loginTab || !registerTab || !loginPane || !registerPane) return;

  const isRegister = mobileState.activeForm === "register";
  loginTab.classList.toggle("active", !isRegister);
  registerTab.classList.toggle("active", isRegister);
  loginPane.className = `m-pane ${isRegister ? "hidden-left" : "active"}`;
  registerPane.className = `m-pane ${isRegister ? "active" : "hidden-right"}`;
}

function initAuthPage() {
  setActiveForm(desktopState.activeForm);
  syncInitialDesktopView();
  syncInitialMobileView();
  syncPath(desktopState.activeForm);

  bindLoginForm({
    formId: "f-login",
    buttonId: "btn-login",
    containerId: "al-login",
    textId: "al-login-t",
    baseClass: "alert tdz-feedback tdz-feedback--auth",
    clear: clearAlerts,
  });
  bindRegisterForm({
    formId: "f-register",
    buttonId: "btn-reg",
    containerId: "al-reg",
    textId: "al-reg-t",
    baseClass: "alert tdz-feedback tdz-feedback--auth",
    clear: clearAlerts,
    getRole: () => desktopState.currentRole,
  });
  bindLoginForm({
    formId: "mf-login",
    buttonId: "mbtn-login",
    containerId: "mal-login",
    textId: "mal-login-t",
    baseClass: "m-alert tdz-feedback tdz-feedback--compact",
    clear: mClearAlerts,
  });
  bindRegisterForm({
    formId: "mf-register",
    buttonId: "mbtn-reg",
    containerId: "mal-reg",
    textId: "mal-reg-t",
    baseClass: "m-alert tdz-feedback tdz-feedback--compact",
    clear: mClearAlerts,
    getRole: () => mobileState.currentRole,
  });
}

Object.assign(window, {
  show,
  switchAuth,
  pickRole,
  togglePassword,
  strength,
  mShow,
  mSwitchAuth,
  mPickRole,
  mTogglePass: togglePassword,
  mStrength,
});

document.addEventListener("DOMContentLoaded", initAuthPage);
