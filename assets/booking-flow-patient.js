import {
  state,
  flowState,
  esc,
  fileSize,
  isTeleconsultation,
  goStep,
} from "./booking-flow-core.js";
import {
  prefillPaymentHolder,
  updatePaymentSummary,
} from "./booking-flow-payment.js";

const MAX_DOCUMENTS = 5;
const MAX_DOCUMENT_SIZE_BYTES = 5 * 1024 * 1024;

function setDocumentsError(message = "") {
  const error = document.getElementById("errDocumentsLimit");
  if (!error) return;
  error.textContent = message;
  error.classList.toggle("show", Boolean(message));
}

export function hydrateForm(me) {
  state.patient = me || null;
  renderPatientOptions();
  applyProfile();
}

function getSelf() {
  return {
    type: "self",
    relation: "Moi-même",
    tag: "Compte principal",
    prenom: state.patient?.prenom || "",
    nom: state.patient?.nom || "",
    date_naissance: (state.patient?.date_naissance || "").slice(0, 10),
    telephone: state.patient?.telephone || "",
    email: state.patient?.email || "",
  };
}

function getFamily() {
  return state.familyMembers.find((member) => Number(member.id) === Number(state.selectedFamilyMemberId)) || null;
}

function getProfile() {
  if (state.selectedPatientTargetType === "family") {
    const member = getFamily();
    if (member) {
      return {
        type: "family",
        relation: member.relation || "Membre",
        tag: "Famille",
        prenom: member.prenom || "",
        nom: member.nom || "",
        date_naissance: member.date_naissance || "",
        telephone: member.telephone || state.patient?.telephone || "",
        email: member.email || "",
      };
    }
  }

  return getSelf();
}

export function applyProfile() {
  const profile = getProfile();
  document.getElementById("fNom").value = profile.nom;
  document.getElementById("fPrenom").value = profile.prenom;
  document.getElementById("fDob").value = profile.date_naissance;
  document.getElementById("fTel").value = profile.telephone;
  document.getElementById("fEmail").value = profile.email;
  document.getElementById("selectedRelation").value = profile.relation;

  const initials = ((profile.prenom || "?")[0] + (profile.nom || "?")[0]).toUpperCase();
  document.getElementById("psAvatar").textContent = initials;
  document.getElementById("psName").textContent = [profile.prenom, profile.nom].filter(Boolean).join(" ") || "—";
  document.getElementById("psMeta").textContent = profile.relation + (profile.date_naissance ? ` • Né(e) le ${profile.date_naissance}` : "");
  document.getElementById("psTag").textContent = profile.tag;
}

export function renderPatientOptions() {
  const selector = document.getElementById("patientSelector");
  const familyOptions = state.familyMembers
    .map((member) => `<option value="family:${member.id}">${esc(member.prenom || "")} ${esc(member.nom || "")} • ${esc(member.relation || "Famille")}</option>`)
    .join("");

  selector.innerHTML = `<option value="self">Moi-même</option>${familyOptions}`;
  selector.value = state.selectedPatientTargetType === "family" && state.selectedFamilyMemberId
    ? `family:${state.selectedFamilyMemberId}`
    : "self";
}

export function handlePatientChange() {
  const value = document.getElementById("patientSelector").value;
  if (value.startsWith("family:")) {
    state.selectedPatientTargetType = "family";
    state.selectedFamilyMemberId = Number(value.split(":")[1]) || null;
  } else {
    state.selectedPatientTargetType = "self";
    state.selectedFamilyMemberId = null;
  }

  applyProfile();
}

export function toggleChip(element) {
  const label = element.textContent.trim();
  if (element.classList.toggle("active")) {
    flowState.activeMotifs.push(label);
  } else {
    flowState.activeMotifs = flowState.activeMotifs.filter((item) => item !== label);
  }
}

export function setVisit(type) {
  flowState.visitType = type;
  document.getElementById("rNew").classList.toggle("sel", type === "new");
  document.getElementById("rSuivi").classList.toggle("sel", type === "suivi");
}

export function toggleSms() {
  flowState.smsChecked = !flowState.smsChecked;
  document.getElementById("smsToggle").classList.toggle("on", flowState.smsChecked);
}

export function handleFiles() {
  const input = document.getElementById("fDocuments");
  const files = Array.from(input.files || []);
  const list = document.getElementById("fileList");

  if (files.length > MAX_DOCUMENTS) {
    setDocumentsError("5 documents maximum.");
    input.value = "";
    list.innerHTML = '<div class="file-empty">Aucun document ajouté.</div>';
    return;
  }

  const tooLargeFile = files.find((file) => file.size > MAX_DOCUMENT_SIZE_BYTES);
  if (tooLargeFile) {
    setDocumentsError(`"${tooLargeFile.name}" dépasse 5 Mo.`);
    input.value = "";
    list.innerHTML = '<div class="file-empty">Aucun document ajouté.</div>';
    return;
  }

  setDocumentsError("");
  list.innerHTML = files.length
    ? files.map((file) => `
    <div class="file-item">
      <div class="file-item-name">${esc(file.name)}</div>
      <div class="file-item-size">${fileSize(file.size)}</div>
      <div class="file-item-tag">Prêt</div>
    </div>`).join("")
    : '<div class="file-empty">Aucun document ajouté.</div>';
}

export function validateStep2() {
  let valid = true;
  [["fNom", "errNom"], ["fPrenom", "errPrenom"], ["fDob", "errDob"], ["fTel", "errTel"]].forEach(([id, errorId]) => {
    const input = document.getElementById(id);
    const error = document.getElementById(errorId);
    if (!input.value.trim()) {
      input.classList.add("err");
      error.classList.add("show");
      valid = false;
    } else {
      input.classList.remove("err");
      error.classList.remove("show");
    }
  });

  if (!valid) return;

  const nom = document.getElementById("fNom").value.trim();
  const prenom = document.getElementById("fPrenom").value.trim();
  const telephone = document.getElementById("fTel").value.trim();
  const relation = document.getElementById("selectedRelation").value || "Moi-même";
  const motifFree = document.getElementById("fMotif").value.trim();
  const motifText = flowState.activeMotifs.length
    ? flowState.activeMotifs.join(", ") + (motifFree ? `, ${motifFree}` : "")
    : (motifFree || "Non précisé");
  const files = Array.from(document.getElementById("fDocuments").files || []);
  const teleNotes = document.getElementById("fTeleNotes").value.trim();

  document.getElementById("sumPatient").textContent = `${prenom} ${nom.toUpperCase()}`;
  if (document.getElementById("sumRelation")) {
    document.getElementById("sumRelation").textContent = relation;
  }
  document.getElementById("sumTel").textContent = telephone;
  document.getElementById("sumMotif").textContent = motifText;
  document.getElementById("sPatient").textContent = `${prenom} ${nom.toUpperCase()}`;
  document.getElementById("successPatient").textContent = `${prenom} ${nom.toUpperCase()}`;
  document.getElementById("sumTeleNotes").textContent = teleNotes || "Aucune note";
  document.getElementById("sumDocs").textContent = files.length ? `${files.length} document(s)` : "Aucun";

  prefillPaymentHolder();
  updatePaymentSummary();
  goStep(isTeleconsultation() ? 3 : 4);
}
