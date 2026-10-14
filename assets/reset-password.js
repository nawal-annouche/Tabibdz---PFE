import {
  setFeedback,
  setButtonLoading,
} from "./inline-feedback.js";

const params = new URLSearchParams(window.location.search);
const storageKey = "tabibdz_reset_email";

const statusBox = document.getElementById("status");
const statusText = document.getElementById("status-text");
const requestStep = document.getElementById("request-step");
const resetStep = document.getElementById("reset-step");
const requestEmail = document.getElementById("request-email");
const resetEmail = document.getElementById("reset-email");
const resetPin = document.getElementById("reset-pin");
const requestBtn = document.getElementById("request-btn");
const resetBtn = document.getElementById("reset-btn");
const resendBtn = document.getElementById("resend-btn");

function showStatus(message, type = "info") {
  setFeedback({
    container: statusBox,
    textNode: statusText,
    message,
    type,
    baseClass: "status tdz-feedback tdz-feedback--status",
    scroll: true,
  });
}

function setLoading(button, loading, idleLabel, loadingLabel) {
  setButtonLoading({
    button,
    loading,
    idleLabel,
    loadingLabel,
  });
}

async function api(path, payload) {
  const response = await fetch(path, {
    method: "POST",
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify(payload),
  });

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || data.message || `HTTP ${response.status}`);
  }

  return data;
}

function openResetStep() {
  requestStep.classList.remove("active");
  resetStep.classList.add("active");
  showStatus("Le code PIN est obligatoire avant tout changement de mot de passe.", "info");
}

function initResetPage() {
  document.getElementById("request-form")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const email = String(requestEmail.value || "").trim();
    if (!email) {
      showStatus("Saisissez une adresse email valide.", "error");
      return;
    }

    setLoading(requestBtn, true, "Envoyer le code PIN", "Envoi...");
    try {
      const result = await api("/api/auth/forgot-password/", { email });
      localStorage.setItem(storageKey, email);
      resetEmail.value = email;
      openResetStep();
      showStatus(result.message || "Si cet email existe, un code PIN a été envoyé.", "success");
    } catch (error) {
      showStatus(error.message || "Impossible d'envoyer le code PIN.", "error");
    } finally {
      setLoading(requestBtn, false, "Envoyer le code PIN", "Envoi...");
    }
  });

  document.getElementById("reset-form")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const email = String(resetEmail.value || "").trim();
    const pin = String(resetPin.value || "").replace(/\D+/g, "").slice(0, 6);
    const payload = {
      email,
      pin,
      new_password: String(document.getElementById("new-password").value || ""),
      confirm_password: String(document.getElementById("confirm-password").value || ""),
    };

    if (!email) {
      showStatus("Saisissez votre email.", "error");
      return;
    }
    if (pin.length !== 6) {
      showStatus("Entrez le code PIN correct de 6 chiffres.", "error");
      return;
    }

    setLoading(resetBtn, true, "Enregistrer le nouveau mot de passe", "Validation...");
    try {
      const result = await api("/api/auth/reset-password/", payload);
      localStorage.removeItem(storageKey);
      showStatus(result.message || "Mot de passe réinitialisé avec succès.", "success");
      setTimeout(() => {
        window.location.href = "/connexion/";
      }, 1400);
    } catch (error) {
      showStatus(error.message || "Réinitialisation impossible.", "error");
    } finally {
      setLoading(resetBtn, false, "Enregistrer le nouveau mot de passe", "Validation...");
    }
  });

  resendBtn?.addEventListener("click", async () => {
    const email = String(resetEmail.value || "").trim();
    if (!email) {
      showStatus("Ajoutez votre email pour renvoyer le code PIN.", "error");
      return;
    }

    setLoading(resendBtn, true, "Renvoyer le code", "Envoi...");
    try {
      const result = await api("/api/auth/forgot-password/", { email });
      showStatus(result.message || "Un nouveau code PIN a été envoyé.", "success");
    } catch (error) {
      showStatus(error.message || "Impossible de renvoyer le code.", "error");
    } finally {
      setLoading(resendBtn, false, "Renvoyer le code", "Envoi...");
    }
  });

  resetPin?.addEventListener("input", () => {
    resetPin.value = resetPin.value.replace(/\D+/g, "").slice(0, 6);
  });

  const initialEmail = params.get("email") || localStorage.getItem(storageKey) || "";
  if (initialEmail) {
    requestEmail.value = initialEmail;
    resetEmail.value = initialEmail;
    if (params.get("email")) {
      openResetStep();
      showStatus("Email détecté. Entrez maintenant le code PIN reçu pour changer le mot de passe.", "info");
    }
  }
}

document.addEventListener("DOMContentLoaded", initResetPage);
