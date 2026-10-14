
  const state = {
      authUser: null,
      doctor: null,
      currentProfileChangeRequest: null,
      pendingProfilePayload: null,
      dashboard: null,
    dashboardRange: 'today',
    currentTab: 'profile',
    monthSlots: [],
    visibleMonth: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
    selectedDate: null,
    isApproved: false,
    currentMessagePeerId: null,
  };

  const fallbackWilayas = [
    { id: 1, nom: 'Adrar' }, { id: 2, nom: 'Chlef' }, { id: 3, nom: 'Laghouat' }, { id: 4, nom: 'Oum El Bouaghi' },
    { id: 5, nom: 'Batna' }, { id: 6, nom: 'Bejaia' }, { id: 7, nom: 'Biskra' }, { id: 8, nom: 'Bechar' },
    { id: 9, nom: 'Blida' }, { id: 10, nom: 'Bouira' }, { id: 11, nom: 'Tamanrasset' }, { id: 12, nom: 'Tebessa' },
    { id: 13, nom: 'Tlemcen' }, { id: 14, nom: 'Tiaret' }, { id: 15, nom: 'Tizi Ouzou' }, { id: 16, nom: 'Alger' },
    { id: 17, nom: 'Djelfa' }, { id: 18, nom: 'Jijel' }, { id: 19, nom: 'Setif' }, { id: 20, nom: 'Saida' },
    { id: 21, nom: 'Skikda' }, { id: 22, nom: 'Sidi Bel Abbes' }, { id: 23, nom: 'Annaba' }, { id: 24, nom: 'Guelma' },
    { id: 25, nom: 'Constantine' }, { id: 26, nom: 'Medea' }, { id: 27, nom: 'Mostaganem' }, { id: 28, nom: 'Msila' },
    { id: 29, nom: 'Mascara' }, { id: 30, nom: 'Ouargla' }, { id: 31, nom: 'Oran' }, { id: 32, nom: 'El Bayadh' },
    { id: 33, nom: 'Illizi' }, { id: 34, nom: 'Bordj Bou Arreridj' }, { id: 35, nom: 'Boumerdes' }, { id: 36, nom: 'El Tarf' },
    { id: 37, nom: 'Tindouf' }, { id: 38, nom: 'Tissemsilt' }, { id: 39, nom: 'El Oued' }, { id: 40, nom: 'Khenchela' },
    { id: 41, nom: 'Souk Ahras' }, { id: 42, nom: 'Tipaza' }, { id: 43, nom: 'Mila' }, { id: 44, nom: 'Ain Defla' },
    { id: 45, nom: 'Naama' }, { id: 46, nom: 'Ain Temouchent' }, { id: 47, nom: 'Ghardaia' }, { id: 48, nom: 'Relizane' },
    { id: 49, nom: 'Timimoun' }, { id: 50, nom: 'Bordj Badji Mokhtar' }, { id: 51, nom: 'Ouled Djellal' }, { id: 52, nom: 'Beni Abbes' },
    { id: 53, nom: 'In Salah' }, { id: 54, nom: 'In Guezzam' }, { id: 55, nom: 'Touggourt' }, { id: 56, nom: 'Djanet' },
    { id: 57, nom: 'El M Ghair' }, { id: 58, nom: 'El Meniaa' }, { id: 59, nom: 'Aflou' }, { id: 60, nom: 'El Abiodh Sidi Cheikh' },
    { id: 61, nom: 'El Kantara' }, { id: 62, nom: 'Bir El Ater' }, { id: 63, nom: 'El Aricha' }, { id: 64, nom: 'Ksar Chellala' },
    { id: 65, nom: 'Ain Oussara' }, { id: 66, nom: 'Messaad' }, { id: 67, nom: 'Ksar El Boukhari' }, { id: 68, nom: 'Boussaada' },
    { id: 69, nom: 'Barika' }
  ];

  const fallbackSpecialites = [
    { id: 1, nom: 'Cardiologie' },
    { id: 7, nom: 'Dentisterie' },
    { id: 2, nom: 'Dermatologie' },
    { id: 6, nom: 'Gynecologie' },
    { id: 5, nom: 'Medecine generale' },
    { id: 3, nom: 'Ophtalmologie' },
    { id: 8, nom: 'Orthopedie' },
    { id: 4, nom: 'Pediatrie' }
  ];

  function $(id) { return document.getElementById(id); }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  }

  function showToast(message) {
      const el = $('toast');
      el.textContent = message;
      el.classList.add('active');
     clearTimeout(showToast.timer);
     showToast.timer = setTimeout(() => el.classList.remove('active'), 2600);
  }

  function setProfileFeedback(message = '') {
    const el = $('profileFeedback');
    if (!el) return;
    if (!message) {
      el.textContent = '';
      el.classList.remove('active');
      return;
    }
    el.textContent = message;
    el.classList.add('active');
  }
  function toggleProfileChangeBanner() {
    const banner = $('profileChangeBanner');
    const request = state.currentProfileChangeRequest;
    if (!banner) return;
    if (!request || request.status !== 'pending') {
      banner.style.display = 'none';
      return;
    }
    $('profileChangeBannerTitle').textContent = 'Demande de changement envoyee';
    $('profileChangeBannerText').textContent = (request.title || 'Mise a jour du profil') + ' · en attente de verification admin.';
    banner.style.display = 'flex';
  }
  function openProfileChangeModal(payload) {
    state.pendingProfilePayload = payload;
    setProfileFeedback('');
    $('profileChangeTitle').value = '';
    $('profileChangeDescription').value = '';
    $('profileChangeModal').classList.add('active');
    $('profileChangeTitle').focus();
  }
  function closeProfileChangeModal() {
    $('profileChangeModal').classList.remove('active');
  }

  function setButtonBusy(buttonIds, isBusy, busyLabel) {
    buttonIds.forEach((id) => {
      const button = $(id);
      if (!button) return;
      if (!button.dataset.defaultLabel) {
        button.dataset.defaultLabel = button.textContent;
      }
      button.disabled = isBusy;
      button.textContent = isBusy ? busyLabel : button.dataset.defaultLabel;
    });
  }

  function revealField(fieldId) {
    const field = $(fieldId);
    if (!field) return;
    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    try { field.focus({ preventScroll: true }); } catch (_) {}
  }

  async function apiRequest(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'include',
      headers: { 'Accept': 'application/json', ...(options.headers || {}) },
      ...options,
    });

    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(data.error || data.message || ('HTTP ' + response.status));
      error.status = response.status;
      error.payload = data;
      throw error;
    }
    return data;
  }

  function redirectToDoctorLogin() {
    const next = window.location.pathname + window.location.search;
    window.location.href = '/connexion/?next=' + encodeURIComponent(next);
  }

  async function verifyDoctorSession(options = {}) {
    try {
      const data = await apiRequest('/api/auth/me/');
      const role = data?.user?.role || '';
      if (role !== 'medecin') {
        if (!options.silent) showToast('La session medecin a change. Reconnexion necessaire.');
        setTimeout(() => redirectToDoctorLogin(), options.silent ? 0 : 500);
        return false;
      }
      return true;
    } catch (_) {
      if (!options.silent) showToast('Session expiree. Reconnexion necessaire.');
      setTimeout(() => redirectToDoctorLogin(), options.silent ? 0 : 500);
      return false;
    }
  }

  function initials(prenom, nom) {
    return (((prenom || '').trim()[0] || '') + ((nom || '').trim()[0] || '')).toUpperCase() || 'DR';
  }

  function formatDateHuman(iso) {
    return new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso));
  }

  function formatShortDate(iso) {
    return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' }).format(new Date(iso));
  }

  function formatTime(iso) {
    return new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
  }

  function formatMonthTitle(date) {
    return new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(date);
  }

  function formatMessageTime(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(date);
  }

  function toDateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
  }

  function statusMeta(status) {
    if (status === 'draft') {
      return {
        pillClass: 'status-pending',
        label: 'Brouillon',
        title: 'Votre dossier n est pas encore envoye',
        description: 'Enregistrez librement votre formulaire puis envoyez-le vous-meme quand il est pret.',
      };
    }
    if (status === 'approved') {
      return {
        pillClass: 'status-approved',
        label: 'Profil approuve',
        title: 'Votre espace medecin est actif',
        description: 'Vous avez acces au dashboard, au profil et a la gestion complete de vos disponibilites.',
      };
    }
    if (status === 'rejected') {
      return {
        pillClass: 'status-rejected',
        label: 'Profil a corriger',
        title: 'Votre dossier demande une correction',
        description: 'Mettez votre profil a jour puis faites valider a nouveau votre dossier medecin.',
      };
    }
    return {
      pillClass: 'status-pending',
      label: 'Validation en attente',
      title: 'Votre dossier est en attente',
      description: 'Le profil reste modifiable. Le dashboard complet s active des que la validation est accordee.',
    };
  }

  function applyDoctorContext() {
    if (!state.doctor) return;

    const workflowStatus = state.doctor.workflow_status || (state.doctor.approval_status === 'pending' && !state.doctor.submitted_at ? 'draft' : (state.doctor.approval_status || 'pending'));
    const status = statusMeta(workflowStatus);
    state.isApproved = workflowStatus === 'approved';

    $('doctorAvatar').textContent = initials(state.doctor.prenom, state.doctor.nom);
    $('doctorChipName').textContent = 'Dr. ' + state.doctor.prenom + ' ' + state.doctor.nom;
    $('headerContext').textContent = (state.doctor.specialite_nom || state.doctor.specialite || 'Medecin') + ' - ' + (state.doctor.commune || 'Profil en cours');
    $('statusPill').className = 'status-pill ' + status.pillClass;
    $('statusPill').textContent = status.label;
    $('sidebarTitle').textContent = status.title;
    $('sidebarDescription').textContent = status.description;
    $('sidebarNote').textContent = state.isApproved
      ? 'Votre tableau de bord affiche vos vraies stats, vos rendez-vous et vos disponibilites depuis la base.'
      : 'Pendant la validation, vous pouvez mettre a jour votre profil. Les statistiques et le planning seront debloques apres approbation.';

    if (state.doctor.id && state.isApproved) {
      $('publicProfileLink').href = '/tabibdz-profil-medecin.html?medecin_id=' + encodeURIComponent(state.doctor.id) + '&preview=doctor&return_url=' + encodeURIComponent('/tabibdz-espace-medecin.html');
      $('publicProfileLink').style.pointerEvents = 'auto';
      $('publicProfileLink').style.opacity = '1';
    } else {
      $('publicProfileLink').removeAttribute('href');
      $('publicProfileLink').style.pointerEvents = 'none';
      $('publicProfileLink').style.opacity = '.45';
    }

    const banner = $('approvalBanner');
    if (state.isApproved) {
      banner.className = 'banner';
      banner.classList.remove('active');
    } else {
      banner.className = 'banner active ' + (workflowStatus === 'rejected' ? 'rejected' : (workflowStatus === 'draft' ? 'draft' : 'pending'));
      $('approvalBannerTitle').textContent =
        workflowStatus === 'rejected' ? 'Profil a corriger avant validation'
        : workflowStatus === 'draft' ? 'Dossier en brouillon'
          : 'Dossier envoye a l equipe admin';
      $('approvalBannerText').textContent = state.doctor.review_note
          ? state.doctor.review_note
        : workflowStatus === 'draft'
            ? 'Completez le formulaire, enregistrez vos modifications, puis cliquez sur Envoyer pour validation quand le dossier est pret.'
            : 'Le dossier a deja ete envoye. Vous pouvez encore modifier les champs et cliquer sur Enregistrer le profil. Si vous voulez retirer la demande en cours, cliquez sur Annuler l envoi.';
    }

    const canSubmit = workflowStatus === 'draft' || workflowStatus === 'rejected';
    $('submitProfileBtn').style.display = canSubmit ? 'inline-flex' : 'none';
    $('submitProfileBtnInline').style.display = canSubmit ? 'inline-flex' : 'none';
    $('revertDraftBtn').style.display = workflowStatus === 'pending' ? 'inline-flex' : 'none';
    $('saveProfileBtn').textContent = canSubmit ? 'Enregistrer le profil' : 'Envoyer une demande de changement';

    $('tabDashboardBtn').disabled = !state.isApproved;
    $('tabMessagesBtn').disabled = !state.isApproved;
    $('tabAvailabilityBtn').disabled = !state.isApproved;
    $('dashboardStatusPill').className = 'status-pill ' + status.pillClass;
    $('dashboardStatusPill').textContent = status.label;
  }

  function setTab(tab) {
    if ((tab === 'dashboard' || tab === 'availability' || tab === 'messages') && !state.isApproved) {
      showToast('Cette section sera disponible apres validation du profil medecin.');
      tab = 'profile';
    }

    state.currentTab = tab;
    document.querySelectorAll('.menu button').forEach((button) => {
      button.classList.toggle('active', button.dataset.tab === tab);
    });
    document.querySelectorAll('.section').forEach((section) => {
      section.classList.toggle('active', section.id === 'section-' + tab);
    });
    if (tab === 'messages' && state.isApproved) {
      loadDoctorMessages(state.currentMessagePeerId).catch((error) => showToast(error.message));
    }
  }

  async function loadAuthUser() {
    try {
      const data = await apiRequest('/api/auth/me/');
      state.authUser = data.user || null;
      if (!state.authUser || state.authUser.role !== 'medecin') {
        redirectToDoctorLogin();
        return false;
      }
      return true;
    } catch (_) {
      redirectToDoctorLogin();
      return false;
    }
  }

  function renderSelectOptions(selectId, placeholder, rows) {
    const select = $(selectId);
    if (!select) return;
    const list = Array.isArray(rows) ? rows : [];
    select.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>' + list.map((item) => (
      '<option value="' + item.id + '">' + escapeHtml(item.nom) + '</option>'
    )).join('');
  }

  async function loadSelectOptions() {
    renderSelectOptions('medWilaya', 'Selectionner une wilaya', fallbackWilayas);
    renderSelectOptions('medSpecialite', 'Choisir une specialite', fallbackSpecialites);

    const [wilayasResult, specialitesResult] = await Promise.allSettled([
      apiRequest('/api/wilayas/'),
      apiRequest('/api/specialites/?limit=100'),
    ]);

    const wilayas = wilayasResult.status === 'fulfilled' && Array.isArray(wilayasResult.value) && wilayasResult.value.length
      ? wilayasResult.value
      : fallbackWilayas;
    const specialites = specialitesResult.status === 'fulfilled' && Array.isArray(specialitesResult.value) && specialitesResult.value.length
      ? specialitesResult.value
      : fallbackSpecialites;

    renderSelectOptions('medWilaya', 'Selectionner une wilaya', wilayas);
    renderSelectOptions('medSpecialite', 'Choisir une specialite', specialites);
  }

  function fillProfileForm(doctor) {
    $('medPrenom').value = doctor.prenom || '';
    $('medNom').value = doctor.nom || '';
    $('medEmail').value = doctor.email || '';
    $('medTelephone').value = doctor.telephone || '';
    $('medPhotoUrl').value = doctor.photo_url || '';
    $('medWilaya').value = doctor.wilaya_id ? String(doctor.wilaya_id) : '';
    $('medCommune').value = doctor.commune || '';
    $('medAdresse').value = doctor.adresse || '';
    $('medMapUrl').value = doctor.map_url || '';
    $('medSpecialite').value = doctor.specialite_id ? String(doctor.specialite_id) : '';
    $('medTarif').value = doctor.tarif || '';
    $('medExperience').value = doctor.experience_years || '';
    $('medAgrement').value = doctor.agrement_no || '';
    $('medSecteur').value = doctor.secteur || 'prive';
    $('medLangues').value = Array.isArray(doctor.langues) ? doctor.langues.join(', ') : (doctor.langues || '');
    $('medCnas').value = Number(doctor.cnas || 0) ? '1' : '0';
    $('medCasnos').value = Number(doctor.casnos || 0) ? '1' : '0';
    $('medMutuellePrivate').value = Number(doctor.mutuelle_private || 0) ? '1' : '0';
    $('medBio').value = doctor.bio || '';
    $('medFormations').value = doctor.formations_text || '';
    $('medHopitaux').value = doctor.hopitaux_text || '';
  }

  function collectProfilePayload() {
    return {
      prenom: $('medPrenom').value.trim(),
      nom: $('medNom').value.trim(),
      email: $('medEmail').value.trim(),
      telephone: $('medTelephone').value.trim(),
      photo_url: $('medPhotoUrl').value.trim(),
      wilaya_id: $('medWilaya').value || null,
      commune: $('medCommune').value.trim(),
      adresse: $('medAdresse').value.trim(),
      map_url: $('medMapUrl').value.trim(),
      specialite_id: $('medSpecialite').value || null,
      tarif: $('medTarif').value || 0,
      experience_years: $('medExperience').value || null,
      agrement_no: $('medAgrement').value.trim(),
      secteur: $('medSecteur').value || 'prive',
      langues: $('medLangues').value.trim(),
      cnas: Number($('medCnas').value || 0),
      casnos: Number($('medCasnos').value || 0),
      mutuelle_private: Number($('medMutuellePrivate').value || 0),
      bio: $('medBio').value.trim(),
      formations_text: $('medFormations').value.trim(),
      hopitaux_text: $('medHopitaux').value.trim(),
    };
  }

  function validateProfilePayload(payload, options = {}) {
      const isSubmit = !!options.forSubmit;
      const checks = [
        ['prenom', 'Renseignez le prenom.', 'medPrenom'],
        ['nom', 'Renseignez le nom.', 'medNom'],
        ['email', 'Renseignez l email.', 'medEmail'],
        ['specialite_id', 'Choisissez une specialite.', 'medSpecialite'],
      ];

      if (isSubmit) {
        checks.push(
          ['telephone', 'Renseignez le telephone avant l envoi.', 'medTelephone'],
          ['adresse', 'Renseignez l adresse du cabinet avant l envoi.', 'medAdresse'],
          ['agrement_no', 'Renseignez le numero d agrement avant l envoi.', 'medAgrement'],
          ['bio', 'Ajoutez une bio medicale avant l envoi.', 'medBio']
        );
      }

      for (const [key, message, fieldId] of checks) {
        const value = payload[key];
        if (value === null || value === undefined || String(value).trim() === '') {
          const error = new Error(message);
          error.fieldId = fieldId;
          throw error;
        }
      }
    }

  async function loadDoctorProfile() {
      const doctor = await apiRequest('/api/medecin/me/');
      state.doctor = doctor;
      fillProfileForm(doctor);
      applyDoctorContext();
      return doctor;
    }
  async function loadProfileChangeRequests() {
    try {
      const data = await apiRequest('/api/medecin/profile-change/');
      state.currentProfileChangeRequest = data.current_request || null;
      toggleProfileChangeBanner();
    } catch (_) {
      state.currentProfileChangeRequest = null;
      toggleProfileChangeBanner();
    }
  }

  async function saveProfile() {
    const payload = collectProfilePayload();
    setProfileFeedback('');
    validateProfilePayload(payload);
    const workflowStatus = state.doctor?.workflow_status || state.doctor?.approval_status || 'draft';

    if ((workflowStatus === 'approved' || workflowStatus === 'pending') && state.currentProfileChangeRequest?.status === 'pending') {
      throw new Error('Une demande de changement est deja en attente. Annulez-la avant d en envoyer une nouvelle.');
    }

    if (workflowStatus === 'approved' || workflowStatus === 'pending') {
      openProfileChangeModal(payload);
      return;
    }

    const data = await apiRequest('/api/medecin/me/', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    state.doctor = data.doctor;
    fillProfileForm(data.doctor);
    applyDoctorContext();
    showToast('Profil medecin enregistre.');
  }

  async function submitProfileForReview() {
      const payload = collectProfilePayload();
      setProfileFeedback('');
      validateProfilePayload(payload, { forSubmit: true });
      payload.submit_for_review = true;
      setButtonBusy(['submitProfileBtn', 'submitProfileBtnInline'], true, 'Envoi en cours...');
      try {
        const data = await apiRequest('/api/medecin/me/', {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        state.doctor = data.doctor;
        fillProfileForm(data.doctor);
        applyDoctorContext();
        setProfileFeedback('Dossier envoye pour validation.');
        showToast('Dossier envoye pour validation.');
      } finally {
        setButtonBusy(['submitProfileBtn', 'submitProfileBtnInline'], false, 'Envoi en cours...');
      }
    }

  async function revertProfileToDraft() {
      setProfileFeedback('');
      const data = await apiRequest('/api/medecin/me/', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ revert_to_draft: true }),
    });
    state.doctor = data.doctor;
    fillProfileForm(data.doctor);
    applyDoctorContext();
      showToast('Le dossier est repasse en brouillon.');
    }
  async function submitProfileChangeRequest() {
    const title = $('profileChangeTitle').value.trim();
    const description = $('profileChangeDescription').value.trim();
    const payload = state.pendingProfilePayload;
    if (!payload) {
      throw new Error('Aucune modification a envoyer.');
    }
    if (!title) {
      throw new Error('Renseignez le titre de la demande.');
    }
    setButtonBusy(['confirmProfileChangeBtn'], true, 'Envoi en cours...');
    try {
      const data = await apiRequest('/api/medecin/profile-change/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, description, payload }),
      });
      state.currentProfileChangeRequest = data.request || null;
      toggleProfileChangeBanner();
      closeProfileChangeModal();
      setProfileFeedback('Demande de changement envoyee a l admin.');
      showToast('Demande de changement envoyee.');
    } finally {
      setButtonBusy(['confirmProfileChangeBtn'], false, 'Envoi en cours...');
    }
  }
  async function cancelProfileChangeRequest() {
    const data = await apiRequest('/api/medecin/profile-change/', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'cancel' }),
    });
    state.currentProfileChangeRequest = null;
    toggleProfileChangeBanner();
    setProfileFeedback(data.message || 'Demande de changement annulee.');
    showToast(data.message || 'Demande de changement annulee.');
  }

  async function uploadDoctorPhoto(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (file.size > 2 * 1024 * 1024) {
      showToast('Image trop volumineuse (max 2 Mo).');
      input.value = '';
      return;
    }

    try {
      const formData = new FormData();
      formData.append('photo', file);
      const response = await fetch('/api/medecin/photo/', {
        method: 'POST',
        credentials: 'include',
        body: formData,
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.error || 'Upload impossible');

      $('medPhotoUrl').value = data.photo_url || '';
      if (state.doctor) {
        state.doctor.photo_url = data.photo_url || '';
      }
      showToast('Photo medecin mise a jour.');
    } catch (error) {
      showToast(error.message);
    } finally {
      input.value = '';
    }
  }

  function renderAgenda(items) {
    const el = $('agendaList');
    if (!items.length) {
      el.innerHTML = '<div class="empty">Aucun rendez-vous sur cette periode.</div>';
      return;
    }
    el.innerHTML = items.map((item) => (
      '<div class="item">' +
        '<div class="item-time">' + escapeHtml(formatTime(item.date_heure)) + '</div>' +
        '<div class="item-main">' +
          '<strong>' + escapeHtml(item.patient.prenom + ' ' + item.patient.nom) + '</strong>' +
          '<span>' + escapeHtml(item.motif || 'Consultation') + ' - ' + escapeHtml(item.type_visite === 'suivi' ? 'Suivi' : 'Nouvelle visite') + '</span>' +
          '<span>' + escapeHtml(item.patient.commune || 'Commune non renseignee') + '</span>' +
        '</div>' +
        '<span class="status-tag status-' + escapeHtml(item.statut) + '">' + escapeHtml(item.statut) + '</span>' +
      '</div>'
    )).join('');
  }

  function renderUpcoming(items) {
    const el = $('upcomingList');
    if (!items.length) {
      el.innerHTML = '<div class="empty">Aucun rendez-vous a venir.</div>';
      $('miniNextAppointment').textContent = 'Aucun RDV planifie';
      $('miniNextAppointmentSub').textContent = 'Des qu une consultation sera prise, elle apparaitra ici.';
      return;
    }
    const next = items[0];
    $('miniNextAppointment').textContent = formatShortDate(next.date_heure) + ' - ' + formatTime(next.date_heure);
    $('miniNextAppointmentSub').textContent = next.patient.prenom + ' ' + next.patient.nom + ' - ' + (next.motif || 'Consultation');
    el.innerHTML = items.map((item) => (
      '<div class="mini-card">' +
        '<strong>' + escapeHtml(formatDateHuman(item.date_heure)) + ' a ' + escapeHtml(formatTime(item.date_heure)) + '</strong>' +
        '<p>' + escapeHtml(item.patient.prenom + ' ' + item.patient.nom) + ' - ' + escapeHtml(item.motif || 'Consultation') + '</p>' +
      '</div>'
    )).join('');
  }

  function renderRecentPatients(items) {
    const el = $('recentPatientsList');
    if (!items.length) {
      el.innerHTML = '<div class="empty">Aucun patient recent.</div>';
      return;
    }
    el.innerHTML = items.map((patient) => (
      '<div class="mini-card patient-line">' +
        '<strong>' + escapeHtml(patient.prenom + ' ' + patient.nom) + '</strong>' +
        '<span>' + escapeHtml((patient.age ? (patient.age + ' ans') : 'Age non renseigne') + ' - ' + (patient.commune || 'Commune non renseignee')) + '</span>' +
        '<span>Dernier passage: ' + escapeHtml(formatShortDate(patient.last_appointment_at)) + ' a ' + escapeHtml(formatTime(patient.last_appointment_at)) + '</span>' +
      '</div>'
    )).join('');
  }

  function renderChart(chart) {
    const el = $('chartBars');
    if (!chart.length) {
      el.innerHTML = '<div class="empty" style="grid-column:1/-1;">Aucune activite sur les 7 derniers jours.</div>';
      return;
    }
    const max = Math.max(1, ...chart.map((item) => Number(item.count || 0)));
    el.innerHTML = chart.map((item) => {
      const height = Math.max(18, Math.round((Number(item.count || 0) / max) * 150));
      return '<div class="bar-wrap"><div class="bar-count">' + escapeHtml(String(item.count || 0)) + '</div><div class="bar" style="height:' + height + 'px"></div><div class="bar-label">' + escapeHtml(item.label) + '</div></div>';
    }).join('');
  }

  function renderWeekAvailability(slotsByDay) {
    const el = $('weekAvailability');
    const keys = Object.keys(slotsByDay || {});
    if (!keys.length) {
      el.innerHTML = '<div class="empty" style="grid-column:1/-1;">Aucun creneau programme sur les 7 prochains jours.</div>';
      return;
    }
    el.innerHTML = keys.map((day) => {
      const slots = slotsByDay[day] || [];
      const freeCount = slots.filter((slot) => slot.is_available && !slot.is_booked).length;
      const bookedCount = slots.filter((slot) => slot.is_booked).length;
      return '<div class="day-card"><strong>' + escapeHtml(new Intl.DateTimeFormat('fr-FR', { weekday: 'short', day: 'numeric' }).format(new Date(day + 'T00:00:00'))) + '</strong><div class="day-counts">Libre: ' + freeCount + '<br>Reserve: ' + bookedCount + '</div></div>';
    }).join('');
  }

  function doctorMessageAvatar(peer) {
    if (peer?.photo_url) {
      return '<img src="' + escapeHtml(peer.photo_url) + '" alt="' + escapeHtml((peer.prenom || '') + ' ' + (peer.nom || '')) + '">';
    }
    return escapeHtml(initials(peer?.prenom, peer?.nom));
  }

  function showDoctorMessagesContacts() {
    $('doctorMessagesContactsPanel').classList.remove('hidden');
    $('doctorMessagesThreadPanel').classList.add('hidden');
  }

  function showDoctorMessagesThread() {
    $('doctorMessagesContactsPanel').classList.add('hidden');
    $('doctorMessagesThreadPanel').classList.remove('hidden');
  }

  function renderDoctorMessagesList(conversations) {
    const el = $('doctorMessagesList');
    if (!el) return;
    if (!conversations.length) {
      el.innerHTML = '<div class="messages-empty"><div class="messages-empty-inner"><div class="messages-empty-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div>Aucun patient eligible a la messagerie pour le moment.</div></div></div>';
      return;
    }

    el.innerHTML = conversations.map((item) => {
      const peer = item.peer || {};
      const active = Number(peer.id) === Number(state.currentMessagePeerId);
      return '<button type="button" class="message-thread-link ' + (active ? 'active' : '') + '" data-peer-id="' + peer.id + '">' +
        '<div class="message-thread-top">' +
          '<div class="message-thread-id">' +
            '<div class="message-thread-avatar">' + doctorMessageAvatar(peer) + '</div>' +
            '<div style="min-width:0;">' +
              '<div class="message-thread-name">' + escapeHtml((peer.prenom || '') + ' ' + (peer.nom || '')) + '</div>' +
              '<div class="message-thread-sub">' + escapeHtml([peer.commune, peer.wilaya].filter(Boolean).join(', ') || 'Patient') + '</div>' +
            '</div>' +
          '</div>' +
          '<div class="message-thread-time">' + escapeHtml(formatMessageTime(item.last_message?.created_at || item.last_appointment_at)) + '</div>' +
        '</div>' +
        '<div class="message-thread-meta">' +
          '<div class="message-thread-preview">' + escapeHtml(item.last_message?.body || "Aucun message envoye pour l'instant.") + '</div>' +
          (item.unread_count ? '<span class="message-thread-badge">' + item.unread_count + '</span>' : '') +
        '</div>' +
      '</button>';
    }).join('');

    el.querySelectorAll('[data-peer-id]').forEach((button) => {
      button.addEventListener('click', () => {
        showDoctorMessagesThread();
        loadDoctorMessages(button.dataset.peerId).catch((error) => showToast(error.message));
      });
    });
  }

  function renderDoctorMessagesThread(payload) {
    const selected = payload?.selected || null;
    const peer = selected?.peer || null;
    const avatar = $('doctorMessagesPeerAvatar');
    const name = $('doctorMessagesPeerName');
    const meta = $('doctorMessagesPeerMeta');
    const body = $('doctorMessagesBody');
    const input = $('doctorMessagesInput');
    const sendBtn = $('doctorMessagesSendBtn');
    const note = $('doctorMessagesNote');

    if (!peer) {
      showDoctorMessagesContacts();
      avatar.innerHTML = '?';
      name.textContent = 'Messagerie medecin';
      meta.textContent = payload?.eligibility_rule || 'Selectionnez un patient a gauche.';
      body.innerHTML = '<div class="messages-empty"><div class="messages-empty-inner"><div class="messages-empty-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div>Selectionnez un patient pour afficher la conversation.</div></div></div>';
      input.disabled = true;
      sendBtn.disabled = true;
      note.textContent = payload?.eligibility_rule || 'La messagerie est reservee aux patients deja vus.';
      return;
    }

    avatar.innerHTML = doctorMessageAvatar(peer);
    name.textContent = (peer.prenom || '') + ' ' + (peer.nom || '');
    meta.textContent = [peer.commune, peer.wilaya, peer.last_appointment_at ? ('Dernier RDV ' + formatShortDate(peer.last_appointment_at)) : ''].filter(Boolean).join(' - ');
    input.disabled = false;
    sendBtn.disabled = false;
    note.textContent = 'Conversation securisee patient-medecin.';

    const messages = selected.messages || [];
    if (!messages.length) {
      body.innerHTML = '<div class="messages-empty"><div class="messages-empty-inner"><div class="messages-empty-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div>Aucun message pour le moment. Vous pouvez demarrer la conversation.</div></div></div>';
      return;
    }

    body.innerHTML = messages.map((message) => (
      '<div class="msg-row ' + (message.is_mine ? 'mine' : '') + '">' +
        '<div class="msg-bubble">' +
          '<p>' + escapeHtml(message.body || '') + '</p>' +
          '<time>' + escapeHtml(formatMessageTime(message.created_at)) + '</time>' +
        '</div>' +
      '</div>'
    )).join('');
    body.scrollTop = body.scrollHeight;
  }

  async function loadDoctorMessages(peerId = null) {
    const url = peerId
      ? '/api/messages/?context=medecin&peer_id=' + encodeURIComponent(peerId)
      : '/api/messages/?context=medecin';
    const data = await apiRequest(url).catch((error) => {
      if (/Session incompatible/i.test(error.message) || error.status === 403) {
        redirectToDoctorLogin();
        return null;
      }
      throw error;
    });
    if (!data) return;
    state.currentMessagePeerId = peerId && data.selected?.peer?.id ? Number(data.selected.peer.id) : null;
    renderDoctorMessagesList(data.conversations || []);
    if (peerId && data.selected?.peer) {
      renderDoctorMessagesThread(data);
      showDoctorMessagesThread();
    } else {
      renderDoctorMessagesThread({ eligibility_rule: data.eligibility_rule });
      showDoctorMessagesContacts();
    }
  }

  async function sendDoctorMessage(event) {
    event.preventDefault();
    if (!state.currentMessagePeerId) {
      showToast('Selectionnez un patient.');
      return;
    }

    const input = $('doctorMessagesInput');
    const sendBtn = $('doctorMessagesSendBtn');
    const body = (input.value || '').trim();
    if (!body) {
      showToast("Ecrivez un message avant l'envoi.");
      return;
    }

    sendBtn.disabled = true;
    try {
      await apiRequest('/api/messages/', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ context: 'medecin', peer_id: state.currentMessagePeerId, body }),
      });
      input.value = '';
      await loadDoctorMessages(state.currentMessagePeerId);
      showToast('Message envoye.');
    } finally {
      sendBtn.disabled = false;
    }
  }

  async function loadDashboard() {
    if (!state.isApproved) return;
    const data = await apiRequest('/api/medecin/dashboard/?range=' + encodeURIComponent(state.dashboardRange));
    state.dashboard = data;
    $('dashboardHeroTitle').textContent = 'Dr. ' + data.doctor.prenom + ' ' + data.doctor.nom;
    $('dashboardHeroText').textContent = 'Suivi en temps reel de votre activite, de vos consultations et de vos disponibilites.';
    $('heroMetaSpecialite').textContent = data.doctor.specialite || 'Medecin';
    $('heroMetaLocation').textContent = [data.doctor.commune, data.doctor.wilaya].filter(Boolean).join(', ') || 'Localisation a completer';
    $('heroMetaApproval').textContent = data.doctor.approved_at ? ('Approuve le ' + formatShortDate(data.doctor.approved_at)) : 'Profil approuve';
    $('dashboardDateText').textContent = formatDateHuman(new Date().toISOString());
    $('miniApprovalInfo').textContent = 'Profil approuve';
    $('miniApprovalInfoSub').textContent = data.doctor.review_note || 'Le compte medecin est actif et connecte aux donnees patients / rendez-vous.';
    $('statTodayValue').textContent = String(data.stats.today_total || 0);
    $('statTodaySub').textContent = (data.stats.today_confirmed || 0) + ' confirme, ' + (data.stats.today_cancelled || 0) + ' annule';
    $('statMonthValue').textContent = String(data.stats.month_total || 0);
    $('statMonthSub').textContent = (data.stats.month_cancelled || 0) + ' annulation(s) ce mois';
    $('statFillValue').textContent = String(data.stats.fill_rate || 0) + '%';
    $('statFillSub').textContent = (data.stats.week_slots || 0) + ' creneaux ouverts cette semaine';
    $('statRatingValue').textContent = Number(data.stats.note || 0).toFixed(1);
    $('statRatingSub').textContent = (data.stats.avis_count || 0) + ' avis patients';
    renderAgenda(data.appointments || []);
    renderUpcoming(data.upcoming_appointments || []);
    renderRecentPatients(data.recent_patients || []);
    renderChart(data.chart || []);
    renderWeekAvailability(data.slots_by_day || {});
  }

  function monthRange(date) {
    const start = new Date(date.getFullYear(), date.getMonth(), 1);
    const end = new Date(date.getFullYear(), date.getMonth() + 1, 1);
    return { start, end };
  }

  function slotsByDay(slots) {
    return slots.reduce((acc, slot) => {
      const day = String(slot.start_at).slice(0, 10);
      (acc[day] ||= []).push(slot);
      return acc;
    }, {});
  }

  async function loadMonthSlots() {
    if (!state.isApproved) return;
    const range = monthRange(state.visibleMonth);
    const data = await apiRequest('/api/medecin/creneaux/manage/?from=' + encodeURIComponent(range.start.toISOString()) + '&to=' + encodeURIComponent(range.end.toISOString()));
    state.monthSlots = Array.isArray(data) ? data : [];
    const grouped = slotsByDay(state.monthSlots);
    if (!state.selectedDate) {
      const todayKey = toDateKey(new Date());
      state.selectedDate = grouped[todayKey] ? todayKey : toDateKey(range.start);
    }
    renderCalendar();
    renderSelectedDay();
  }

  function renderCalendar() {
    const grid = $('calendarGrid');
    const current = new Date(state.visibleMonth.getFullYear(), state.visibleMonth.getMonth(), 1);
    $('calendarTitle').textContent = 'Calendrier - ' + formatMonthTitle(current);

    const grouped = slotsByDay(state.monthSlots);
    const firstDayIndex = (current.getDay() + 6) % 7;
    const startCell = new Date(current);
    startCell.setDate(current.getDate() - firstDayIndex);
    const cells = [];

    ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'].forEach((label) => {
      cells.push('<div class="weekday">' + label + '</div>');
    });

    for (let i = 0; i < 42; i += 1) {
      const cellDate = new Date(startCell);
      cellDate.setDate(startCell.getDate() + i);
      const key = toDateKey(cellDate);
      const sameMonth = cellDate.getMonth() === current.getMonth();
      const daySlots = grouped[key] || [];
      const freeCount = daySlots.filter((slot) => slot.is_available && !slot.is_booked).length;
      const bookedCount = daySlots.filter((slot) => slot.is_booked).length;
      const dotClass = bookedCount > 0 ? 'has-booked' : (freeCount > 0 ? 'has-free' : '');
      const classes = ['calendar-day'];
      if (!sameMonth) classes.push('other');
      if (state.selectedDate === key) classes.push('selected');

      cells.push(
        '<button type="button" class="' + classes.join(' ') + '" data-date="' + key + '">' +
          '<div class="calendar-day-top"><strong>' + cellDate.getDate() + '</strong><span class="day-dot ' + dotClass + '"></span></div>' +
          '<div class="calendar-meta"><span>' + freeCount + ' libre(s)</span><span>' + bookedCount + ' reserve(s)</span></div>' +
        '</button>'
      );
    }

    grid.innerHTML = cells.join('');
    grid.querySelectorAll('.calendar-day').forEach((button) => {
      button.addEventListener('click', () => {
        state.selectedDate = button.dataset.date;
        renderCalendar();
        renderSelectedDay();
      });
    });
  }

  function renderSelectedDay() {
    const title = $('selectedDayTitle');
    const subtitle = $('selectedDaySubtitle');
    const list = $('selectedDaySlots');

    if (!state.selectedDate) {
      title.textContent = 'Jour selectionne';
      subtitle.textContent = 'Choisissez une date dans le calendrier.';
      list.innerHTML = '<div class="empty">Aucun jour selectionne.</div>';
      return;
    }

    title.textContent = formatDateHuman(state.selectedDate + 'T00:00:00');
    subtitle.textContent = 'Generez des creneaux sur cette date puis gerez les places libres ou reservees.';

    const slots = state.monthSlots
      .filter((slot) => String(slot.start_at).slice(0, 10) === state.selectedDate)
      .sort((a, b) => String(a.start_at).localeCompare(String(b.start_at)));

    if (!slots.length) {
      list.innerHTML = '<div class="empty">Aucun creneau sur cette journee. Choisissez un horaire puis cliquez sur "Generer les creneaux".</div>';
      return;
    }

    const now = Date.now();
    list.innerHTML = slots.map((slot) => {
      const isPast = new Date(slot.end_at).getTime() < now;
      const rowClass = slot.is_booked ? 'booked' : (isPast ? 'past' : 'free');
      const stateLabel = slot.is_booked ? 'Reserve' : (isPast ? 'Passe' : 'Libre');
      const action = slot.is_booked || isPast
        ? '<span class="status-tag status-' + (slot.is_booked ? 'termine' : 'annule') + '">' + stateLabel + '</span>'
        : '<button type="button" class="btn btn-ghost btn-sm" data-slot-id="' + slot.id + '">Supprimer</button>';

      return '<div class="slot-row ' + rowClass + '"><div><strong>' + escapeHtml(formatTime(slot.start_at)) + ' - ' + escapeHtml(formatTime(slot.end_at)) + '</strong><small>' + stateLabel + '</small></div>' + action + '</div>';
    }).join('');

    list.querySelectorAll('[data-slot-id]').forEach((button) => {
      button.addEventListener('click', async () => {
        try {
          await apiRequest('/api/medecin/creneaux/manage/?id=' + encodeURIComponent(button.dataset.slotId), { method: 'DELETE' });
          await Promise.all([loadMonthSlots(), loadDashboard()]);
          showToast('Creneau supprime.');
        } catch (error) {
          showToast(error.message);
        }
      });
    });
  }

  function buildSlotsForDay() {
    if (!state.selectedDate) throw new Error('Choisissez d abord un jour dans le calendrier.');
    const start = $('slotStartTime').value;
    const end = $('slotEndTime').value;
    const duration = Number($('slotDuration').value || 30);
    if (!start || !end) throw new Error('Renseignez les heures de debut et de fin.');

    let cursor = new Date(state.selectedDate + 'T' + start + ':00');
    const finish = new Date(state.selectedDate + 'T' + end + ':00');
    if (!(cursor < finish)) throw new Error('L heure de fin doit etre apres l heure de debut.');

    const slots = [];
    while (cursor < finish) {
      const next = new Date(cursor.getTime() + duration * 60000);
      if (next <= finish) {
        slots.push({ start_at: cursor.toISOString(), end_at: next.toISOString() });
      }
      cursor = next;
    }
    if (!slots.length) throw new Error('Aucun creneau genere avec cette configuration.');
    return slots;
  }

  async function generateSlotsForDay() {
    const slots = buildSlotsForDay();
    const result = await apiRequest('/api/medecin/creneaux/manage/', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ slots }),
    });
    await Promise.all([loadMonthSlots(), loadDashboard()]);
    showToast((result.created_count || 0) + ' creneau(x) cree(s).');
  }

  async function logoutDoctor() {
    try {
      await apiRequest('/api/auth/logout/', { method: 'POST' });
    } catch (_) {}
    window.location.href = '/tabibdz-homepage.html';
  }

  window.__doctorSaveProfile = () => saveProfile().then(() => {
    if (state.isApproved) {
      return Promise.allSettled([loadDashboard(), loadMonthSlots()]);
    }
    return Promise.resolve();
  }).catch((error) => {
    setProfileFeedback(error.message);
    if (error.fieldId) revealField(error.fieldId);
    showToast(error.message);
  });

  window.__doctorSubmitProfile = () => submitProfileForReview().catch((error) => {
    setProfileFeedback(error.message);
    if (error.fieldId) revealField(error.fieldId);
    showToast(error.message);
  });

  window.__doctorRevertDraft = () => revertProfileToDraft().catch((error) => showToast(error.message));
  window.__doctorReloadProfile = () => loadDoctorProfile().then(() => showToast('Profil recharge.')).catch((error) => showToast(error.message));
  window.__doctorCancelProfileChange = () => cancelProfileChangeRequest().catch((error) => {
    setProfileFeedback(error.message);
    showToast(error.message);
  });

  function bindEvents() {
    document.querySelectorAll('.menu button[data-tab]').forEach((button) => {
      button.addEventListener('click', async () => {
        setTab(button.dataset.tab);
        if (button.dataset.tab === 'dashboard' && state.isApproved) {
          try { await loadDashboard(); } catch (error) { showToast(error.message); }
        }
        if (button.dataset.tab === 'availability' && state.isApproved) {
          try { await loadMonthSlots(); } catch (error) { showToast(error.message); }
        }
      });
    });

    document.querySelectorAll('#rangeSwitcher button').forEach((button) => {
      button.addEventListener('click', async () => {
        state.dashboardRange = button.dataset.range;
        document.querySelectorAll('#rangeSwitcher button').forEach((item) => item.classList.toggle('active', item === button));
        try { await loadDashboard(); } catch (error) { showToast(error.message); }
      });
    });

    $('saveProfileBtn').addEventListener('click', window.__doctorSaveProfile);
    $('submitProfileBtn').addEventListener('click', window.__doctorSubmitProfile);
    $('submitProfileBtnInline').addEventListener('click', window.__doctorSubmitProfile);
    $('revertDraftBtn').addEventListener('click', window.__doctorRevertDraft);
    $('reloadProfileBtn').addEventListener('click', window.__doctorReloadProfile);
    $('cancelProfileChangeBtn').addEventListener('click', window.__doctorCancelProfileChange);
    $('closeProfileChangeModalBtn').addEventListener('click', closeProfileChangeModal);
    $('cancelProfileChangeModalBtn').addEventListener('click', closeProfileChangeModal);
    $('confirmProfileChangeBtn').addEventListener('click', () => submitProfileChangeRequest().catch((error) => {
      setProfileFeedback(error.message);
      showToast(error.message);
    }));
    $('generateDaySlotsBtn').addEventListener('click', () => generateSlotsForDay().catch((error) => showToast(error.message)));
    $('prevMonthBtn').addEventListener('click', () => {
      state.visibleMonth = new Date(state.visibleMonth.getFullYear(), state.visibleMonth.getMonth() - 1, 1);
      loadMonthSlots().catch((error) => showToast(error.message));
    });
    $('nextMonthBtn').addEventListener('click', () => {
      state.visibleMonth = new Date(state.visibleMonth.getFullYear(), state.visibleMonth.getMonth() + 1, 1);
      loadMonthSlots().catch((error) => showToast(error.message));
    });
    $('medPhotoFile').addEventListener('change', function () {
      uploadDoctorPhoto(this);
    });
    $('doctorMessagesForm').addEventListener('submit', (event) => {
      sendDoctorMessage(event).catch((error) => showToast(error.message));
    });
    $('doctorMessagesBackBtn').addEventListener('click', showDoctorMessagesContacts);
    $('logoutBtn').addEventListener('click', logoutDoctor);
  }

  async function bootstrap() {
    bindEvents();
    const verified = await verifyDoctorSession({ silent: true });
    if (!verified) return;
    const ok = await loadAuthUser();
    if (!ok) return;
    await loadSelectOptions();
    await loadDoctorProfile();
    await loadProfileChangeRequests();
    if (state.isApproved) {
      setTab('dashboard');
      await Promise.allSettled([loadDashboard(), loadMonthSlots(), loadDoctorMessages()]);
    } else {
      setTab('profile');
    }
  }

  window.addEventListener('focus', () => {
    verifyDoctorSession({ silent: false });
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      verifyDoctorSession({ silent: true });
    }
  });

  document.addEventListener('DOMContentLoaded', bootstrap);
