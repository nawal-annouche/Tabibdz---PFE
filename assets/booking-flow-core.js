export const state = {
  doctorId: null,
  selectedCreneauId: null,
  selectedDateTime: null,
  doctor: null,
  weekOffset: 0,
  slotsByDay: new Map(),
  patient: null,
  familyMembers: [],
  consultationMode: "cabinet",
  selectedPatientTargetType: "self",
  selectedFamilyMemberId: null,
  payment: {
    method: "edahabia",
    completed: false,
    holder: "",
    maskedCard: "",
  },
};

export const flowState = {
  currentStep: 1,
  visitType: "new",
  activeMotifs: [],
  smsChecked: true,
  createdAppointmentId: null,
};

const DAYS = ["Dim", "Lun", "Mar", "Mer", "Jeu", "Ven", "Sam"];
const DEFAULT_MOTIF_OPTIONS = [
  "Consultation générale",
  "Bilan / contrôle",
  "Suivi traitement",
  "Deuxième avis",
  "Symptômes récents",
  "Renouvellement ordonnance",
];
const SPECIALITY_MOTIF_OPTIONS = {
  cardiologie: ["Contrôle annuel", "Douleurs thoraciques", "Palpitations", "Essoufflement", "Hypertension", "Suivi traitement", "ECG / Bilan"],
  dermatologie: ["Acné", "Eczéma", "Chute de cheveux", "Taches / grains de beauté", "Éruption cutanée", "Mycose", "Suivi traitement peau"],
  ophtalmologie: ["Baisse de vision", "Douleur oculaire", "Larmoiement", "Rougeur des yeux", "Contrôle lunettes", "Suivi cataracte", "Fond d’œil / bilan"],
  pediatrie: ["Fièvre", "Toux / rhume", "Vaccination", "Contrôle croissance", "Douleurs abdominales", "Allergie", "Suivi pédiatrique"],
  gynecologie: ["Suivi grossesse", "Douleurs pelviennes", "Troubles du cycle", "Contrôle gynécologique", "Échographie", "Infection / pertes", "Contraception"],
  dentisterie: ["Douleur dentaire", "Détartrage", "Carie", "Contrôle dentaire", "Saignement gencives", "Urgence dentaire", "Suivi traitement dentaire"],
  orthopedie: ["Douleur articulaire", "Douleur du dos", "Traumatisme / entorse", "Suivi fracture", "Genou / épaule", "Rééducation", "Bilan orthopédique"],
};

let paymentSummaryUpdater = () => {};

export function setUpdatePaymentSummary(callback) {
  paymentSummaryUpdater = typeof callback === "function" ? callback : () => {};
}

export function param(name) {
  return new URLSearchParams(window.location.search).get(name);
}

export function esc(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

export function normalizeText(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

export function money(value) {
  return `${new Intl.NumberFormat("fr-FR").format(Number(value || 0))} DA`;
}

export function fmtLong(dateTime) {
  return new Intl.DateTimeFormat("fr-FR", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  }).format(new Date(dateTime));
}

export function fmtShort(dateTime) {
  return new Intl.DateTimeFormat("fr-FR", {
    weekday: "short",
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(dateTime)).replace(",", " -");
}

export function fmtTime(dateTime) {
  return new Intl.DateTimeFormat("fr-FR", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(dateTime));
}

export function fmtDate(dateTime) {
  const label = fmtLong(dateTime);
  return label.charAt(0).toUpperCase() + label.slice(1);
}

export function dateKey(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

export function fileSize(size) {
  if (!size) return "0 Ko";
  if (size >= 1048576) return `${(size / 1048576).toFixed(1)} Mo`;
  return `${Math.max(1, Math.round(size / 1024))} Ko`;
}

export function digitsOnly(value) {
  return String(value || "").replace(/\D+/g, "");
}

export function isTeleconsultation() {
  return state.consultationMode === "teleconsultation";
}

export function getFlowStepIds() {
  return isTeleconsultation() ? [1, 2, 3, 4] : [1, 2, 4];
}

export function paymentMethodLabel(method) {
  return ({ edahabia: "Edahabia", baridimob: "BaridiMob", cib: "CIB" })[method] || "Edahabia";
}

export function maskCardNumber(value) {
  const digits = digitsOnly(value);
  if (!digits) return "—";
  const last4 = digits.slice(-4).padStart(4, "•");
  return `•••• •••• •••• ${last4}`;
}

export function stripInjectedChrome() {
  const shell = document.querySelector(".shell");
  if (!shell) return;

  Array.from(document.body.children).forEach((node) => {
    if (node === shell || node.id === "successPage" || node.id === "mobileApptBar" || node.tagName === "SCRIPT") {
      return;
    }

    const isGlobalChrome =
      node.matches?.(".navbar, header, footer, .site-footer, .site-header, .public-navbar") ||
      node.querySelector?.(".nav-links, .nav-actions");

    if (isGlobalChrome) {
      node.remove();
    }
  });
}

export function getWeekDates(offset) {
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  now.setDate(now.getDate() + offset * 6);

  return Array.from({ length: 6 }, (_, index) => {
    const date = new Date(now);
    date.setDate(now.getDate() + index);
    return date;
  });
}

export async function api(url, options = {}) {
  const response = await fetch(url, {
    credentials: "include",
    headers: { Accept: "application/json", ...(options.headers || {}) },
    ...options,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || data.message || `HTTP ${response.status}`);
  }
  return data;
}

function getMotifOptionsForDoctor(doctor) {
  const slug = normalizeText(doctor?.specialite_slug || "");
  const name = normalizeText(doctor?.specialite || "");
  const candidates = [slug, name];

  for (const key of Object.keys(SPECIALITY_MOTIF_OPTIONS)) {
    const normalizedKey = normalizeText(key);
    if (candidates.some((value) => value && (value === normalizedKey || value.includes(normalizedKey)))) {
      return SPECIALITY_MOTIF_OPTIONS[key];
    }
  }

  return DEFAULT_MOTIF_OPTIONS;
}

export function renderMotifChips() {
  const wrap = document.getElementById("motifChips");
  if (!wrap) return;

  const motifs = getMotifOptionsForDoctor(state.doctor);
  flowState.activeMotifs = flowState.activeMotifs.filter((item) => motifs.includes(item));
  wrap.innerHTML = motifs
    .map((label) => `<div class="chip${flowState.activeMotifs.includes(label) ? " active" : ""}" onclick="toggleChip(this)">${esc(label)}</div>`)
    .join("");
}

export function refreshStepUI(activeStep = flowState.currentStep) {
  const flow = getFlowStepIds();
  const total = flow.length;
  const activeIndex = Math.max(0, flow.indexOf(activeStep));

  [1, 2, 3, 4].forEach((stepId) => {
    const desktop = document.getElementById(`si${stepId}`);
    const mobile = document.getElementById(`msp${stepId}`);
    const visible = flow.includes(stepId);
    const position = flow.indexOf(stepId);

    if (desktop) {
      desktop.style.display = visible ? "flex" : "none";
      desktop.classList.remove("s-active", "s-done");
      const stepNumber = desktop.querySelector(".snum span");
      if (stepNumber && visible) stepNumber.textContent = String(position + 1);
      if (visible && position < activeIndex) desktop.classList.add("s-done");
      if (visible && position === activeIndex) desktop.classList.add("s-active");
    }

    if (mobile) {
      mobile.style.display = visible ? "block" : "none";
      mobile.classList.remove("active", "done");
      if (visible && position < activeIndex) mobile.classList.add("done");
      if (visible && position === activeIndex) mobile.classList.add("active");
    }
  });

  const label = document.getElementById("mspLabel");
  if (label) label.textContent = `Étape ${activeIndex + 1} / ${total}`;

  const step1Eyebrow = document.getElementById("step1Eyebrow");
  const step2Eyebrow = document.getElementById("step2Eyebrow");
  const step3Eyebrow = document.getElementById("step3Eyebrow");
  const step4Eyebrow = document.getElementById("step4Eyebrow");
  if (step1Eyebrow) step1Eyebrow.textContent = `Étape 1 sur ${total}`;
  if (step2Eyebrow) step2Eyebrow.textContent = `Étape 2 sur ${total}`;
  if (step3Eyebrow) step3Eyebrow.textContent = `Étape 3 sur ${total}`;
  if (step4Eyebrow) step4Eyebrow.textContent = `Étape ${flow.indexOf(4) + 1} sur ${total}`;
}

export function clearConfirmError() {
  const box = document.getElementById("confirmErrBox");
  if (!box) return;
  box.textContent = "";
  box.classList.remove("show");
}

export function goStep(stepNumber) {
  clearConfirmError();
  document.querySelectorAll(".step-view").forEach((panel) => panel.classList.remove("active"));
  document.getElementById(`step${stepNumber}`)?.classList.add("active");
  flowState.currentStep = stepNumber;
  refreshStepUI(stepNumber);
  document.querySelector(".panel-r")?.scrollTo({ top: 0, behavior: "smooth" });
  window.scrollTo({ top: 0, behavior: "smooth" });
}

export function showConfirmError(message) {
  const box = document.getElementById("confirmErrBox");
  if (!box) {
    console.error(message);
    return;
  }

  box.textContent = message;
  box.classList.add("show");
  box.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

export function setDoctorUI(doctor) {
  const name = `Dr. ${doctor.prenom || ""} ${doctor.nom || ""}`.trim();
  const speciality = doctor.specialite || "Spécialité";

  document.getElementById("lDocName").innerHTML = `Dr. <em>${doctor.prenom || ""} ${doctor.nom || ""}</em>`;
  document.getElementById("lDocSpec").textContent = speciality;
  document.getElementById("lTarif").textContent = money(doctor.tarif || 0);
  document.getElementById("sumDoctor").textContent = name;
  document.getElementById("sumSpecialite").textContent = speciality;
  document.getElementById("sumTarif").textContent = money(doctor.tarif || 0);
  document.getElementById("sDoctor").textContent = name;
  document.getElementById("successDoctor").textContent = name;
  document.getElementById("sDoctorSpec").textContent = `${speciality}${doctor.commune ? " — " + doctor.commune : ""}`;
  document.title = `TabibDZ — ${name}`;

  const mobileDoctor = document.getElementById("mobBarDoctor");
  if (mobileDoctor) mobileDoctor.textContent = name;

  renderMotifChips();
  updateModeUI();
}

export function setDateUI(dateTime) {
  const longLabel = fmtDate(dateTime);
  const timeLabel = fmtTime(dateTime);
  const shortLabel = fmtShort(dateTime);

  document.getElementById("lDate").textContent = longLabel;
  document.getElementById("lTime").textContent = timeLabel;
  document.getElementById("sumDateTime").textContent = shortLabel;
  document.getElementById("sDateTime").textContent = shortLabel;

  const mobileDetail = document.getElementById("mobBarDetail");
  if (mobileDetail) mobileDetail.textContent = shortLabel;
}

export function updateModeUI() {
  const teleconsultation = state.consultationMode === "teleconsultation";

  document.getElementById("lMode").textContent = teleconsultation ? "Téléconsultation" : "Cabinet";
  document.getElementById("sumMode").textContent = teleconsultation ? "Téléconsultation" : "Cabinet";
  document.getElementById("sMode").textContent = teleconsultation ? "Téléconsultation" : "Consultation en cabinet";
  document.getElementById("teleBlock").classList.toggle("show", teleconsultation);

  const mobileMode = document.getElementById("mobBarMode");
  if (mobileMode) mobileMode.textContent = teleconsultation ? "Téléconsultation" : "Cabinet";

  document.getElementById("sumTeleRow").style.display = teleconsultation ? "flex" : "none";
  document.getElementById("sumDocsRow").style.display = teleconsultation ? "flex" : "none";

  if (state.doctor) {
    const address = [state.doctor.adresse, state.doctor.commune, state.doctor.wilaya].filter(Boolean).join(", ") || "Adresse non renseignée";
    const label = teleconsultation ? "Accès" : "Adresse";
    const value = teleconsultation ? "Téléconsultation sécurisée TabibDZ" : address;

    document.getElementById("lAccessLbl").textContent = label;
    document.getElementById("lAddress").textContent = teleconsultation ? "Lien partagé après confirmation" : address;
    document.getElementById("sumAddrLbl").textContent = label;
    document.getElementById("sumAddress").textContent = value;
    document.getElementById("sAddrLbl").textContent = label;
    document.getElementById("sAddress").textContent = teleconsultation ? "Le lien vous sera communiqué après confirmation." : address;
  }

  if (!teleconsultation) state.payment.completed = false;
  paymentSummaryUpdater();
  refreshStepUI(flowState.currentStep);
}

export { DAYS };
