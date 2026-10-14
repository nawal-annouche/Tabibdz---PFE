import {
  state,
  flowState,
  DAYS,
  param,
  dateKey,
  fmtDate,
  fmtShort,
  getWeekDates,
  api,
  setDoctorUI,
  setDateUI,
  refreshStepUI,
  clearConfirmError,
  showConfirmError,
  stripInjectedChrome,
  goStep,
} from "./booking-flow-core.js";
import {
  hydrateForm,
  renderPatientOptions,
  applyProfile,
} from "./booking-flow-patient.js";
import {
  bindPaymentInputs,
  selectPaymentMethod,
} from "./booking-flow-payment.js";

function renderSlotCell(slots) {
  if (!Array.isArray(slots) || !slots.length) {
    return '<div class="slot busy">—</div>';
  }

  return slots.map((slot) => {
    const selected = Number(state.selectedCreneauId) === Number(slot.id);
    const label = new Intl.DateTimeFormat("fr-FR", { hour: "2-digit", minute: "2-digit" }).format(new Date(slot.start_at));
    return `<div class="slot ${selected ? "selected" : "free"}" onclick="pickSlot(${slot.id}, '${slot.start_at}')">${label}</div>`;
  }).join("");
}

export function renderAvailability() {
  const dates = getWeekDates(state.weekOffset);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const firstLabel = new Intl.DateTimeFormat("fr-FR", { day: "numeric", month: "short" }).format(dates[0]);
  const lastLabel = new Intl.DateTimeFormat("fr-FR", { day: "numeric", month: "short", year: "numeric" }).format(dates[5]);
  document.getElementById("weekLabel").textContent = `${firstLabel} — ${lastLabel}`;

  document.getElementById("dayHeaders").innerHTML = dates.map((date) => {
    const isToday = date.toDateString() === today.toDateString();
    return `<div class="day-hd"><div class="d-name">${DAYS[date.getDay()]}</div><div class="d-num ${isToday ? "today" : ""}">${date.getDate()}</div></div>`;
  }).join("");

  let morningHtml = "";
  let afternoonHtml = "";

  dates.forEach((date) => {
    const key = dateKey(date);
    const daySlots = state.slotsByDay.get(key) || { morning: [], afternoon: [] };
    morningHtml += `<div class="slot-stack">${renderSlotCell(daySlots.morning)}</div>`;
    afternoonHtml += `<div class="slot-stack">${renderSlotCell(daySlots.afternoon)}</div>`;
  });

  document.getElementById("morningSlots").innerHTML = morningHtml;
  document.getElementById("afternoonSlots").innerHTML = afternoonHtml;
}

export function pickSlot(id, dateTime) {
  state.selectedCreneauId = Number(id);
  state.selectedDateTime = dateTime;
  setDateUI(dateTime);
  renderAvailability();
}

export function prevWeek() {
  state.weekOffset -= 1;
  syncWeekSelection();
  renderAvailability();
}

export function nextWeek() {
  state.weekOffset += 1;
  syncWeekSelection();
  renderAvailability();
}

export function syncWeekSelection() {
  const dates = getWeekDates(state.weekOffset);
  const validDateKeys = new Set(dates.map((date) => dateKey(date)));
  const current = state.selectedDateTime ? new Date(state.selectedDateTime) : null;

  if (current && !Number.isNaN(current.valueOf()) && validDateKeys.has(dateKey(current))) {
    return;
  }

  for (const date of dates) {
    const daySlots = state.slotsByDay.get(dateKey(date)) || { morning: [], afternoon: [] };
    const firstSlot = daySlots.morning[0] || daySlots.afternoon[0];
    if (firstSlot) {
      state.selectedCreneauId = Number(firstSlot.id);
      state.selectedDateTime = firstSlot.start_at;
      setDateUI(firstSlot.start_at);
      return;
    }
  }
}

export async function hydrateSlots() {
  const from = new Date();
  const slots = await api(`/api/medecin/creneaux/?medecin_id=${state.doctorId}&from=${encodeURIComponent(from.toISOString())}`);
  const map = new Map();

  for (const slot of slots) {
    const start = new Date(slot.start_at);
    if (Number.isNaN(start.valueOf())) continue;

    const key = dateKey(start);
    if (!map.has(key)) map.set(key, { morning: [], afternoon: [] });
    const group = map.get(key);
    (start.getHours() < 12 ? group.morning : group.afternoon).push(slot);
  }

  state.slotsByDay = map;
}

function slotsPageUrl() {
  const params = new URLSearchParams();
  if (state.doctorId) params.set("medecin_id", String(state.doctorId));
  if (state.selectedCreneauId) params.set("creneau_id", String(state.selectedCreneauId));
  if (state.selectedDateTime) params.set("date_heure", state.selectedDateTime);
  if (state.consultationMode) params.set("consultation_mode", state.consultationMode);
  const queryString = params.toString();
  return `/tabibdz-rdv-medecin.html${queryString ? `?${queryString}` : ""}`;
}

export function goBackToSlots() {
  window.location.href = slotsPageUrl();
}

export async function confirmRDV() {
  clearConfirmError();

  if (state.consultationMode === "teleconsultation" && !state.payment.completed) {
    showConfirmError("Veuillez valider le paiement de la téléconsultation avant de confirmer le rendez-vous.");
    goStep(3);
    return;
  }

  const button = document.querySelector(".btn-confirm");
  button.disabled = true;
  button.textContent = "Envoi en cours…";

  const nom = document.getElementById("fNom").value.trim();
  const prenom = document.getElementById("fPrenom").value.trim();
  const dob = document.getElementById("fDob").value.trim();
  const telephone = document.getElementById("fTel").value.trim();
  const email = document.getElementById("fEmail").value.trim();
  const relation = document.getElementById("selectedRelation").value || "Moi-même";
  const motifFree = document.getElementById("fMotif").value.trim();
  const motifText = flowState.activeMotifs.length
    ? flowState.activeMotifs.join(", ") + (motifFree ? `, ${motifFree}` : "")
    : (motifFree || "Consultation");
  const teleNotes = document.getElementById("fTeleNotes").value.trim();
  const files = Array.from(document.getElementById("fDocuments").files || []);

  const payload = {
    medecin_id: state.doctorId,
    creneau_id: state.selectedCreneauId,
    date_heure: state.selectedDateTime,
    motif: motifText,
    type_visite: flowState.visitType,
    consultation_mode: state.consultationMode,
    patient_target_type: state.selectedPatientTargetType,
    family_member_id: state.selectedPatientTargetType === "family" ? state.selectedFamilyMemberId : null,
    teleconsultation_notes: state.consultationMode === "teleconsultation" ? teleNotes : null,
    patient_profile: {
      prenom,
      nom,
      date_naissance: dob,
      telephone,
      email,
      relation,
    },
  };

  try {
    let response;
    if (state.consultationMode === "teleconsultation" && files.length) {
      const formData = new FormData();
      formData.append("payload", JSON.stringify(payload));
      files.forEach((file) => formData.append("documents[]", file));
      response = await fetch("/api/appointments/", {
        method: "POST",
        credentials: "include",
        headers: { Accept: "application/json" },
        body: formData,
      });
    } else {
      response = await fetch("/api/appointments/", {
        method: "POST",
        credentials: "include",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });
    }

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const backendMessage = (data.error === "Reservation impossible" && data.details)
        ? data.details
        : (data.error || data.message || `HTTP ${response.status}`);
      throw new Error(backendMessage);
    }

    flowState.createdAppointmentId = data.appointment_id || null;
    const code = data.confirmation_code || (`TBZ-${new Date().getFullYear()}-0000`);
    document.getElementById("confNum").textContent = `#${code}`;

    document.getElementById("appShell").style.display = "none";
    document.getElementById("successPage").classList.add("show");
    window.scrollTo({ top: 0, behavior: "smooth" });
  } catch (error) {
    button.disabled = false;
    button.innerHTML = '<svg viewBox="0 0 16 16" fill="none"><path d="M3 8.5L6.5 12L13 5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Confirmer le rendez-vous';
    showConfirmError(error.message || "Une erreur est survenue pendant la réservation.");
  }
}

export function addToGoogleCal() {
  const start = new Date(state.selectedDateTime);
  const end = new Date(start.getTime() + 3600000);
  const format = (date) => date.toISOString().replace(/[-:]/g, "").replace(".000", "");
  const doctor = document.getElementById("sDoctor")?.textContent || "Dr.";
  const speciality = document.getElementById("sumSpecialite")?.textContent || "Consultation";
  const address = document.getElementById("sAddress")?.textContent || "";
  const details = state.consultationMode === "teleconsultation"
    ? "Téléconsultation — TabibDZ"
    : "Consultation en cabinet — TabibDZ";

  window.open(
    `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(`RDV ${doctor} — ${speciality}`)}&dates=${format(start)}/${format(end)}&details=${encodeURIComponent(details)}&location=${encodeURIComponent(address)}`,
    "_blank"
  );
}

export function addToAppleCal() {
  if (flowState.createdAppointmentId) {
    window.open(`/api/appointments/ics.php?id=${flowState.createdAppointmentId}`, "_blank");
  }
}

async function requireSession() {
  try {
    const me = await api("/api/patient/me/");
    hydrateForm(me);
    return me;
  } catch (_) {
    window.location.href = `/connexion/?next=${encodeURIComponent(window.location.pathname + window.location.search)}`;
    throw new Error("auth");
  }
}

async function loadFamily() {
  try {
    const members = await api("/api/patient/family/");
    state.familyMembers = Array.isArray(members) ? members : [];
  } catch (_) {
    state.familyMembers = [];
  }

  renderPatientOptions();
  applyProfile();
}

async function resolveDoctor() {
  state.consultationMode = param("consultation_mode") === "teleconsultation" ? "teleconsultation" : "cabinet";

  const urlDoctorId = Number(param("medecin_id") || param("id"));
  if (Number.isInteger(urlDoctorId) && urlDoctorId > 0) {
    state.doctorId = urlDoctorId;
  }

  const urlCreneauId = Number(param("creneau_id"));
  if (Number.isInteger(urlCreneauId) && urlCreneauId > 0) {
    state.selectedCreneauId = urlCreneauId;
  }

  const explicitDate = param("date_heure") || param("start_at") || param("slot");
  if (explicitDate) {
    const date = new Date(explicitDate);
    if (!Number.isNaN(date.valueOf())) {
      state.selectedDateTime = date.toISOString();
    }
  }

  if (!state.doctorId) {
    const doctors = await api("/api/medecins/?limit=1");
    if (Array.isArray(doctors) && doctors.length) {
      state.doctorId = Number(doctors[0].id);
    }
  }

  if (!state.doctorId) {
    throw new Error("Médecin introuvable");
  }

  state.doctor = await api(`/api/medecin/?id=${state.doctorId}`);
  await hydrateSlots();

  const allSlots = [...state.slotsByDay.values()].flatMap((group) => [...group.morning, ...group.afternoon]);
  if (state.selectedCreneauId) {
    const picked = allSlots.find((slot) => Number(slot.id) === Number(state.selectedCreneauId));
    if (picked) state.selectedDateTime = picked.start_at;
  }
  if (!state.selectedDateTime && allSlots.length) {
    state.selectedDateTime = allSlots[0].start_at;
    state.selectedCreneauId = Number(allSlots[0].id) || null;
  }
  if (!state.selectedDateTime) {
    const fallback = new Date();
    fallback.setDate(fallback.getDate() + 1);
    fallback.setHours(9, 0, 0, 0);
    state.selectedDateTime = fallback.toISOString();
  }

  setDoctorUI(state.doctor);
  setDateUI(state.selectedDateTime);

  const navBackButton = document.getElementById("navBackBtn");
  if (navBackButton) navBackButton.href = slotsPageUrl();

  renderAvailability();
}

export async function initBookingFlow() {
  try {
    stripInjectedChrome();
    bindPaymentInputs();
    selectPaymentMethod(state.payment.method);
    refreshStepUI(1);
    await requireSession();
    await loadFamily();
    await resolveDoctor();
    goStep(2);
  } catch (error) {
    if (error.message !== "auth") {
      console.error(error);
    }
  }
}
