import {
  state,
  isTeleconsultation,
  paymentMethodLabel,
  maskCardNumber,
  digitsOnly,
  money,
  goStep,
} from "./booking-flow-core.js";

export function selectPaymentMethod(method) {
  state.payment.method = method;
  state.payment.completed = false;
  state.payment.maskedCard = "";

  ["edahabia", "baridimob", "cib"].forEach((key) => {
    document.getElementById(`payMethod-${key}`)?.classList.toggle("active", key === method);
  });

  const authLabel = document.getElementById("payAuthLabel");
  if (authLabel) {
    authLabel.textContent = method === "baridimob" ? "Code BaridiMob" : "Mot de passe de validation";
  }

  updatePaymentSummary();
}

export function clearFieldError(inputId, errorId) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(errorId);
  if (input) input.classList.remove("err");
  if (error) error.classList.remove("show");
}

export function showFieldError(inputId, errorId) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(errorId);
  if (input) input.classList.add("err");
  if (error) error.classList.add("show");
}

export function prefillPaymentHolder() {
  const holder = document.getElementById("payHolder");
  if (!holder || holder.value.trim()) return;

  const firstName = document.getElementById("fPrenom")?.value.trim() || "";
  const lastName = document.getElementById("fNom")?.value.trim() || "";
  holder.value = [firstName, lastName].filter(Boolean).join(" ");
}

export function updatePaymentSummary() {
  const teleconsultation = isTeleconsultation();
  const paymentLabel = teleconsultation
    ? `${paymentMethodLabel(state.payment.method)}${state.payment.maskedCard && state.payment.maskedCard !== "—" ? " — " + state.payment.maskedCard : ""}`
    : "Non applicable";

  const payMethodSummary = document.getElementById("payMethodSummary");
  const payDoctorSummary = document.getElementById("payDoctorSummary");
  const payAmount = document.getElementById("payAmount");
  const payStatusBadge = document.getElementById("payStatusBadge");

  if (payMethodSummary) payMethodSummary.textContent = paymentMethodLabel(state.payment.method);
  if (payDoctorSummary) {
    payDoctorSummary.textContent = state.doctor ? `Dr. ${(state.doctor.prenom || "").trim()} ${(state.doctor.nom || "").trim()}`.trim() : "—";
  }
  if (payAmount) payAmount.textContent = money(state.doctor?.tarif || 0);
  if (payStatusBadge) {
    payStatusBadge.textContent = state.payment.completed ? "Paiement confirmé" : "En attente";
    payStatusBadge.classList.toggle("ready", state.payment.completed);
  }

  const sumPaymentRow = document.getElementById("sumPaymentRow");
  const sumPayment = document.getElementById("sumPayment");
  const successPaymentRow = document.getElementById("sPaymentRow");
  const successPayment = document.getElementById("sPayment");

  if (sumPaymentRow) sumPaymentRow.style.display = teleconsultation ? "flex" : "none";
  if (sumPayment) {
    sumPayment.textContent = teleconsultation
      ? `${state.payment.completed ? "Paiement confirmé" : "Paiement simulé en attente"} — ${paymentLabel}`
      : "—";
  }
  if (successPaymentRow) successPaymentRow.style.display = teleconsultation ? "flex" : "none";
  if (successPayment) {
    successPayment.textContent = teleconsultation ? `${state.payment.completed ? "Réglé" : "En attente"} — ${paymentLabel}` : "—";
  }
}

export function validatePaymentStep() {
  let valid = true;

  clearFieldError("payHolder", "errPayHolder");
  clearFieldError("payCardNumber", "errPayCardNumber");
  clearFieldError("payExpiry", "errPayExpiry");
  clearFieldError("payCvv", "errPayCvv");
  clearFieldError("payPassword", "errPayPassword");
  document.getElementById("errPayTerms")?.classList.remove("show");

  const holder = document.getElementById("payHolder").value.trim();
  const cardNumberRaw = document.getElementById("payCardNumber").value.trim();
  const cardDigits = digitsOnly(cardNumberRaw);
  const expiry = document.getElementById("payExpiry").value.trim();
  const cvv = digitsOnly(document.getElementById("payCvv").value.trim());
  const password = document.getElementById("payPassword").value.trim();
  const accepted = document.getElementById("payTerms").checked;

  if (!holder) {
    showFieldError("payHolder", "errPayHolder");
    valid = false;
  }
  if (cardDigits.length !== 16) {
    showFieldError("payCardNumber", "errPayCardNumber");
    valid = false;
  }
  if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(expiry)) {
    showFieldError("payExpiry", "errPayExpiry");
    valid = false;
  }
  if (cvv.length !== 3) {
    showFieldError("payCvv", "errPayCvv");
    valid = false;
  }
  if (password.length < 4) {
    showFieldError("payPassword", "errPayPassword");
    valid = false;
  }
  if (!accepted) {
    document.getElementById("errPayTerms")?.classList.add("show");
    valid = false;
  }
  if (!valid) return;

  state.payment.completed = true;
  state.payment.holder = holder;
  state.payment.maskedCard = maskCardNumber(cardDigits);
  updatePaymentSummary();
  goStep(4);
}

export function bindPaymentInputs() {
  const cardInput = document.getElementById("payCardNumber");
  const expiryInput = document.getElementById("payExpiry");

  if (cardInput) {
    cardInput.addEventListener("input", () => {
      const digits = digitsOnly(cardInput.value).slice(0, 16);
      cardInput.value = digits.replace(/(\d{4})(?=\d)/g, "$1 ").trim();
      state.payment.completed = false;
      state.payment.maskedCard = maskCardNumber(digits);
      clearFieldError("payCardNumber", "errPayCardNumber");
      updatePaymentSummary();
    });
  }

  if (expiryInput) {
    expiryInput.addEventListener("input", () => {
      const digits = digitsOnly(expiryInput.value).slice(0, 4);
      expiryInput.value = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
      clearFieldError("payExpiry", "errPayExpiry");
    });
  }

  ["payHolder", "payCvv", "payPassword"].forEach((id) => {
    const element = document.getElementById(id);
    if (!element) return;

    element.addEventListener("input", () => {
      state.payment.completed = false;

      if (id === "payHolder") clearFieldError("payHolder", "errPayHolder");
      if (id === "payCvv") {
        element.value = digitsOnly(element.value).slice(0, 3);
        clearFieldError("payCvv", "errPayCvv");
      }
      if (id === "payPassword") clearFieldError("payPassword", "errPayPassword");

      updatePaymentSummary();
    });
  });

  const terms = document.getElementById("payTerms");
  if (terms) {
    terms.addEventListener("change", () => {
      document.getElementById("errPayTerms")?.classList.remove("show");
    });
  }
}
