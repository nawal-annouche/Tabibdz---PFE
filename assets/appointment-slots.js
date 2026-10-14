import {
  setFeedback,
  clearFeedback,
} from "./inline-feedback.js";

const state = {
  doctorId: null,
  doctor: null,
  selectedCreneauId: null,
  selectedDateTime: null,
  weekOffset: 0,
  slotsByDay: new Map(),
  consultationMode: "cabinet",
  mobActiveDayIdx: 0,
};

const FR_DAYS = ["Dim", "Lun", "Mar", "Mer", "Jeu", "Ven", "Sam"];
const errorBox = document.getElementById("agendaErrBox");
const errorText = document.getElementById("agendaErrText");

function getParam(key) {
  return new URLSearchParams(window.location.search).get(key);
}

function dateKey(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function getWeekDates(offset) {
  const start = new Date();
  start.setHours(0, 0, 0, 0);
  start.setDate(start.getDate() + offset * 6);
  return Array.from({ length: 6 }, (_, index) => {
    const date = new Date(start);
    date.setDate(start.getDate() + index);
    return date;
  });
}

function formatLong(value) {
  return new Intl.DateTimeFormat("fr-FR", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date(value));
}

function formatTime(value) {
  return new Intl.DateTimeFormat("fr-FR", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}

function formatAddr(doctor) {
  return [doctor.adresse, doctor.commune, doctor.wilaya].filter(Boolean).join(", ") || "Adresse non renseignée";
}

function showPageError(message) {
  setFeedback({
    container: errorBox,
    textNode: errorText,
    message,
    type: "error",
    baseClass: "agenda-inline-error tdz-feedback",
    scroll: true,
  });
}

function clearPageError() {
  clearFeedback({
    container: errorBox,
    textNode: errorText,
    baseClass: "agenda-inline-error tdz-feedback",
  });
}

async function api(url, options = {}) {
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

function setSlotUI(value) {
  const dateEl = document.getElementById("sbSlotDate");
  const timeEl = document.getElementById("sbSlotTime");
  const mobDate = document.getElementById("mobSlotDate");
  const mobTime = document.getElementById("mobSlotTime");
  const mobBtn = document.getElementById("mobBtnContinue");
  const agendaBtn = document.getElementById("agendaBtnContinue");
  const mobBtn2 = document.getElementById("mobBtnContinue2");

  if (!value) {
    dateEl.innerHTML = '<span class="sb-slot-empty">Aucun créneau choisi</span>';
    timeEl.innerHTML = "";
    if (agendaBtn) agendaBtn.disabled = true;
    if (mobDate) {
      mobDate.textContent = "Aucun créneau choisi";
      mobDate.classList.add("empty");
    }
    if (mobTime) {
      mobTime.style.display = "none";
      mobTime.textContent = "";
    }
    if (mobBtn) mobBtn.disabled = true;
    if (mobBtn2) mobBtn2.disabled = true;
    return;
  }

  dateEl.textContent = formatLong(value);
  timeEl.innerHTML = `<span class="sb-slot-time">⏱ ${formatTime(value)}</span>`;
  if (agendaBtn) agendaBtn.disabled = false;

  const date = new Date(value);
  const dayLabel = new Intl.DateTimeFormat("fr-FR", {
    weekday: "short",
    day: "numeric",
    month: "short",
  }).format(date);

  if (mobDate) {
    mobDate.textContent = dayLabel.charAt(0).toUpperCase() + dayLabel.slice(1);
    mobDate.classList.remove("empty");
  }
  if (mobTime) {
    mobTime.textContent = `⏱ ${formatTime(value)}`;
    mobTime.style.display = "block";
  }
  if (mobBtn) mobBtn.disabled = false;
  if (mobBtn2) mobBtn2.disabled = false;
}

function setDoctorUI(doctor) {
  const fullName = `Dr. ${doctor.prenom || ""} ${doctor.nom || ""}`.trim();
  document.getElementById("sbDocName").textContent = fullName;
  document.getElementById("sbDocSpec").textContent = doctor.specialite || "Spécialité";
  document.getElementById("sbDocAddr").textContent = formatAddr(doctor);
  document.getElementById("sbBadgeCnas").style.display = Number(doctor.cnas || 0) ? "inline-block" : "none";
  document.getElementById("sbBadgeCasnos").style.display = Number(doctor.casnos || 0) ? "inline-block" : "none";

  const backButton = document.getElementById("agendaBtnBack");
  if (backButton) {
    backButton.onclick = () => {
      window.location.href = `/tabibdz-profil-medecin.html?medecin_id=${state.doctorId}`;
    };
  }

  document.title = `TabibDZ — Rendez-vous — ${fullName}`;
}

function selectMode(mode) {
  clearPageError();
  state.consultationMode = mode;
  const isTele = mode === "teleconsultation";
  document.getElementById("modeCabinet").classList.toggle("active", !isTele);
  document.getElementById("modeTele").classList.toggle("active", isTele);
  document.getElementById("modeHintText").textContent = isTele
    ? "En téléconsultation, vous pourrez joindre vos analyses, ordonnances ou certificats à l'étape suivante."
    : "La consultation au cabinet suit exactement le parcours habituel, sans changement.";
}

function renderCell(slots) {
  if (!slots || !slots.length) {
    return '<div class="slot busy">—</div>';
  }

  return slots.map((slot) => {
    const selected = Number(state.selectedCreneauId) === Number(slot.id);
    return `<div class="slot ${selected ? "selected" : "free"}" onclick="selectSlot(${Number(slot.id)}, '${slot.start_at}')">${formatTime(slot.start_at)}</div>`;
  }).join("");
}

function isMobile() {
  return window.innerWidth <= 600;
}

function renderCalendar() {
  if (isMobile()) {
    renderMobileCal();
    return;
  }

  const dates = getWeekDates(state.weekOffset);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const formatDay = (date) => new Intl.DateTimeFormat("fr-FR", { day: "numeric", month: "short" }).format(date);
  document.getElementById("calWeekLabel").textContent = `${formatDay(dates[0])} — ${formatDay(dates[5])} ${dates[5].getFullYear()}`;

  const daysRow = document.getElementById("calDaysRow");
  daysRow.innerHTML = '<div class="cal-days-spacer"></div>';
  daysRow.innerHTML += dates.map((date) => `
    <div class="cal-day-header">
      <div class="cal-day-name">${FR_DAYS[date.getDay()]}</div>
      <div class="cal-day-num${date.toDateString() === today.toDateString() ? " today" : ""}">${date.getDate()}</div>
    </div>
  `).join("");

  const morningRow = document.getElementById("calMorningRow");
  morningRow.innerHTML = `<div class="cal-period-label">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
    Matin
  </div>`;
  dates.forEach((date) => {
    const day = state.slotsByDay.get(dateKey(date)) || { morning: [], afternoon: [] };
    morningRow.innerHTML += `<div class="cal-cell">${renderCell(day.morning)}</div>`;
  });

  const afternoonRow = document.getElementById("calAfternoonRow");
  afternoonRow.innerHTML = `<div class="cal-period-label">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M17.7 7.7A7.1 7.1 0 0 0 7 12a7 7 0 1 0 10.7-5.9"/></svg>
    Après-midi
  </div>`;
  dates.forEach((date) => {
    const day = state.slotsByDay.get(dateKey(date)) || { morning: [], afternoon: [] };
    afternoonRow.innerHTML += `<div class="cal-cell">${renderCell(day.afternoon)}</div>`;
  });
}

function renderMobileDayTabs() {
  const dates = getWeekDates(state.weekOffset);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const tabsEl = document.getElementById("mobDayTabs");
  if (!tabsEl) return;

  const formatDay = (date) => new Intl.DateTimeFormat("fr-FR", { day: "numeric", month: "short" }).format(date);
  const weekLabel = document.getElementById("mobWeekLabel");
  if (weekLabel) {
    weekLabel.textContent = `${formatDay(dates[0])} — ${formatDay(dates[5])}`;
  }

  tabsEl.innerHTML = dates.map((date, index) => {
    const day = state.slotsByDay.get(dateKey(date)) || { morning: [], afternoon: [] };
    const hasSlots = day.morning.length > 0 || day.afternoon.length > 0;
    const isToday = date.toDateString() === today.toDateString();
    const isActive = index === state.mobActiveDayIdx;
    return `<div class="mob-day-tab${hasSlots ? " has-slots" : ""}${isToday ? " today" : ""}${isActive ? " active" : ""}" onclick="mobSelectDay(${index})" ontouchstart="">
      <div class="mob-day-name">${FR_DAYS[date.getDay()]}</div>
      <div class="mob-day-num">${date.getDate()}</div>
      <div class="mob-day-dot"></div>
    </div>`;
  }).join("");

  const tabs = tabsEl.querySelectorAll(".mob-day-tab");
  tabs[state.mobActiveDayIdx]?.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
}

function renderMobileSlots() {
  const dates = getWeekDates(state.weekOffset);
  const date = dates[state.mobActiveDayIdx];
  if (!date) return;

  const day = state.slotsByDay.get(dateKey(date)) || { morning: [], afternoon: [] };
  const buildSlots = (slots, containerId) => {
    const element = document.getElementById(containerId);
    if (!element) return;
    if (!slots.length) {
      element.innerHTML = '<div class="mob-slots-empty">Aucun créneau</div>';
      return;
    }

    element.innerHTML = slots.map((slot) => {
      const selected = Number(state.selectedCreneauId) === Number(slot.id);
      return `<button class="mob-slot-btn ${selected ? "selected" : "free"}" onclick="selectSlot(${Number(slot.id)}, '${slot.start_at}')" ontouchstart="">${formatTime(slot.start_at)}</button>`;
    }).join("");
  };

  buildSlots(day.morning, "mobMorningSlots");
  buildSlots(day.afternoon, "mobAfternoonSlots");
}

function renderMobileCal() {
  const dates = getWeekDates(state.weekOffset);
  if (state.selectedDateTime) {
    const selected = new Date(state.selectedDateTime);
    const index = dates.findIndex((date) => dateKey(date) === dateKey(selected));
    if (index >= 0) state.mobActiveDayIdx = index;
  } else {
    for (let index = 0; index < dates.length; index += 1) {
      const day = state.slotsByDay.get(dateKey(dates[index])) || { morning: [], afternoon: [] };
      if (day.morning.length || day.afternoon.length) {
        state.mobActiveDayIdx = index;
        break;
      }
    }
  }

  renderMobileDayTabs();
  renderMobileSlots();
}

function selectSlot(id, datetime) {
  state.selectedCreneauId = Number(id);
  state.selectedDateTime = datetime;
  clearPageError();
  setSlotUI(datetime);
  if (isMobile()) {
    renderMobileSlots();
    renderMobileDayTabs();
  } else {
    renderCalendar();
  }
  if (navigator.vibrate) navigator.vibrate(18);
}

function syncWeek() {
  const dates = getWeekDates(state.weekOffset);
  const allowed = new Set(dates.map((date) => dateKey(date)));
  const current = state.selectedDateTime ? new Date(state.selectedDateTime) : null;
  const visible = current && !Number.isNaN(current.valueOf()) && allowed.has(dateKey(current));
  if (visible) return;

  for (const date of dates) {
    const day = state.slotsByDay.get(dateKey(date)) || { morning: [], afternoon: [] };
    const first = day.morning[0] || day.afternoon[0];
    if (first) {
      state.selectedCreneauId = Number(first.id);
      state.selectedDateTime = first.start_at;
      setSlotUI(first.start_at);
      return;
    }
  }
}

function prevWeek() {
  state.weekOffset -= 1;
  syncWeek();
  renderCalendar();
  if (isMobile()) renderMobileCal();
}

function nextWeek() {
  state.weekOffset += 1;
  syncWeek();
  renderCalendar();
  if (isMobile()) renderMobileCal();
}

function mobSelectDay(index) {
  state.mobActiveDayIdx = index;
  renderMobileDayTabs();
  renderMobileSlots();
  if (navigator.vibrate) navigator.vibrate(10);
}

function continueToInfo() {
  if (!state.selectedDateTime) {
    showPageError("Veuillez choisir un créneau avant de continuer.");
    return;
  }

  const params = new URLSearchParams();
  params.set("medecin_id", String(state.doctorId));
  if (state.selectedCreneauId) params.set("creneau_id", String(state.selectedCreneauId));
  params.set("date_heure", state.selectedDateTime);
  params.set("consultation_mode", state.consultationMode);
  window.location.href = `/tabibdz-prise-rdv.html?${params.toString()}`;
}

async function init() {
  clearPageError();
  const agendaBtn = document.getElementById("agendaBtnContinue");
  if (agendaBtn) agendaBtn.disabled = true;

  let doctorId = Number(getParam("medecin_id") || getParam("id"));
  if (!Number.isInteger(doctorId) || doctorId <= 0) {
    const list = await api("/api/medecins/?limit=1");
    const rows = Array.isArray(list?.data) ? list.data : (Array.isArray(list) ? list : []);
    if (rows.length) doctorId = Number(rows[0].id);
  }
  if (!doctorId) {
    throw new Error("Médecin introuvable.");
  }
  state.doctorId = doctorId;

  if (getParam("consultation_mode") === "teleconsultation") {
    selectMode("teleconsultation");
  }

  state.doctor = await api(`/api/medecin/?id=${doctorId}`);
  setDoctorUI(state.doctor);

  const from = new Date();
  const slots = await api(`/api/medecin/creneaux/?medecin_id=${doctorId}&from=${encodeURIComponent(from.toISOString())}`);
  const map = new Map();
  for (const slot of slots) {
    const start = new Date(slot.start_at);
    if (Number.isNaN(start.valueOf())) continue;
    const key = dateKey(start);
    if (!map.has(key)) map.set(key, { morning: [], afternoon: [] });
    (start.getHours() < 12 ? map.get(key).morning : map.get(key).afternoon).push(slot);
  }
  state.slotsByDay = map;

  const slotId = Number(getParam("creneau_id"));
  const explicitDate = getParam("date_heure") || getParam("start_at") || getParam("slot");
  const allSlots = [...map.values()].flatMap((group) => [...group.morning, ...group.afternoon]);
  if (Number.isInteger(slotId) && slotId > 0) {
    const matched = allSlots.find((slot) => Number(slot.id) === slotId);
    if (matched) {
      state.selectedCreneauId = slotId;
      state.selectedDateTime = matched.start_at;
    }
  }
  if (!state.selectedDateTime && explicitDate) {
    const parsed = new Date(explicitDate);
    if (!Number.isNaN(parsed.valueOf())) {
      state.selectedDateTime = parsed.toISOString();
    }
  }
  if (!state.selectedDateTime && allSlots.length) {
    state.selectedCreneauId = Number(allSlots[0].id);
    state.selectedDateTime = allSlots[0].start_at;
  }

  setSlotUI(state.selectedDateTime);
  renderCalendar();
  if (isMobile()) renderMobileCal();
}

function initBurgerMenu() {
  const button = document.getElementById("burger-btn");
  const drawer = document.getElementById("nav-drawer");
  if (!button || !drawer) return;

  function toggleMenu(open) {
    button.classList.toggle("open", open);
    drawer.classList.toggle("open", open);
    button.setAttribute("aria-expanded", open);
    drawer.setAttribute("aria-hidden", !open);
    document.body.style.overflow = open ? "hidden" : "";
  }

  button.addEventListener("click", () => {
    toggleMenu(!button.classList.contains("open"));
  });
  document.addEventListener("click", (event) => {
    if (!button.contains(event.target) && !drawer.contains(event.target)) {
      toggleMenu(false);
    }
  });
  drawer.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => toggleMenu(false));
  });
  window.addEventListener("resize", () => {
    if (window.innerWidth > 900) toggleMenu(false);
  });
}

Object.assign(window, {
  selectMode,
  prevWeek,
  nextWeek,
  selectSlot,
  mobSelectDay,
  continueToInfo,
});

window.addEventListener("resize", () => {
  if (isMobile()) renderMobileCal();
});

document.addEventListener("DOMContentLoaded", () => {
  initBurgerMenu();
  init().catch((error) => {
    console.error(error);
    showPageError(`Impossible de charger les disponibilités pour le moment. ${error.message || ""}`.trim());
  });
});
