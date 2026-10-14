
    const state = { doctors: [], selectedDoctorId: null, selectedProfileChangeId: null, selectedWorkspaceType: 'doctor', dashboard: null };

    async function api(url, options = {}) {
      const response = await fetch(url, {
        credentials: 'include',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.error || 'Erreur API');
      return data;
    }

    function formatDate(value) {
      if (!value) return 'Non renseigné';
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return value;
      return date.toLocaleString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
    function approvalLabel(status) {
      if (status === 'draft') return 'Brouillon';
      if (status === 'approved') return 'Approuvé';
      if (status === 'rejected') return 'Refusé';
      return 'En attente';
    }
    function escapeHtml(value) {
      return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
    }
    function getStatusClass(status) {
      if (status === 'draft') return 'neutral';
      if (status === 'approved') return 'approved';
      if (status === 'rejected') return 'rejected';
      return 'pending';
    }
    function changeStatusLabel(status) {
      if (status === 'approved') return 'Changement approuvé';
      if (status === 'rejected') return 'Changement refusé';
      if (status === 'cancelled') return 'Demande annulée';
      return 'Changement en attente';
    }
    function changePayloadLabels() {
      return {
        prenom: 'Prénom',
        nom: 'Nom',
        email: 'Email',
        telephone: 'Téléphone',
        wilaya_id: 'Wilaya',
        commune: 'Commune',
        photo_url: 'Photo URL',
        specialite_id: 'Spécialité',
        tarif: 'Tarif',
        cnas: 'CNAS',
        casnos: 'CASNOS',
        adresse: 'Adresse',
        langues: 'Langues',
        secteur: 'Secteur',
        experience_years: 'Expérience',
        agrement_no: 'Agrément',
        bio: 'Bio',
        formations_text: 'Formations',
        hopitaux_text: 'Hôpitaux / Cliniques',
        map_url: 'Lien Google Maps',
        mutuelle_private: 'Mutuelle privée'
      };
    }
    function formatChangePayload(payload) {
      const labels = changePayloadLabels();
      const entries = Object.entries(payload || {});
      if (!entries.length) return 'Aucun changement détecté.';
      return entries.map(([key, value]) => {
        const label = labels[key] || key;
        const text = Array.isArray(value) ? value.join(', ') : String(value ?? '—');
        return `${label} : ${text}`;
      }).join('\n');
    }
    function showFlash(message, type = 'success') {
      const flash = document.getElementById('reviewFlash');
      const icon = type === 'success'
        ? `<svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
        : `<svg class="flash-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
      flash.innerHTML = icon + `<span>${escapeHtml(message)}</span>`;
      flash.className = `flash show ${type}`;
    }
    function hasValue(value) { return value !== null && value !== undefined && String(value).trim() !== ''; }

    function calculateChecklist(doctor) {
      const checks = [
        { title: 'Identité du compte', description: 'Nom, prénom et email disponibles.', ok: hasValue(doctor.prenom) && hasValue(doctor.nom) && hasValue(doctor.email) },
        { title: 'Téléphone et contact', description: 'Numéro de contact pour vérification complémentaire.', ok: hasValue(doctor.telephone) },
        { title: 'Spécialité médicale', description: 'Spécialité clairement rattachée au profil.', ok: hasValue(doctor.specialite_nom) },
        { title: "Numéro d'agrément", description: 'Référence professionnelle à vérifier avant publication.', ok: hasValue(doctor.agrement_no) },
        { title: 'Adresse professionnelle', description: 'Cabinet, clinique ou lieu d\'exercice visible.', ok: hasValue(doctor.adresse) },
        { title: 'Présentation du profil', description: 'Bio et informations de confiance pour le patient.', ok: hasValue(doctor.bio) },
        { title: 'Parcours professionnel', description: 'Formations ou établissements renseignés.', ok: hasValue(doctor.formations_text) || hasValue(doctor.hopitaux_text) },
        { title: 'Compte prêt à publier', description: 'Email vérifié et compte actif.', ok: Boolean(doctor.email_verified_at) && Number(doctor.is_active) === 1 },
      ];
      const okCount = checks.filter(item => item.ok).length;
      return { checks, score: Math.round((okCount / checks.length) * 100) };
    }

    function fillMetrics(summary) {
      document.getElementById('metricPending').textContent = summary.pending_medecins || 0;
      document.getElementById('metricApproved').textContent = summary.approved_medecins || 0;
      document.getElementById('metricRejected').textContent = summary.rejected_medecins || 0;
      document.getElementById('metricPatients').textContent = summary.patients_count || 0;
      document.getElementById('metricAdmins').textContent = summary.admins_count || 0;
      document.getElementById('metricAppointments').textContent = summary.appointments_count || 0;
      document.getElementById('heroPending').textContent = summary.pending_medecins || 0;
      document.getElementById('heroApproved').textContent = summary.approved_medecins || 0;
      document.getElementById('heroRejected').textContent = summary.rejected_medecins || 0;
    }

    function renderPendingQueue(rows) {
      const root = document.getElementById('pendingQueue');
      const count = Array.isArray(rows) ? rows.length : 0;
      document.getElementById('queueCounter').textContent = `${count} dossier${count > 1 ? 's' : ''}`;
      if (!count) {
        root.innerHTML = `<div class="empty"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>Aucun dossier médecin en attente.</div>`;
        return;
      }
      root.innerHTML = rows.map(doctor => {
        const submitted = formatDate(doctor.submitted_at || doctor.created_at);
        const note = doctor.review_note ? '<span class="tag neutral">Note existante</span>' : '';
        return `<article class="queue-item">
          <div>
            <div class="doctor-name">Dr ${escapeHtml(doctor.prenom || '')} ${escapeHtml(doctor.nom || '')}</div>
            <div class="doctor-meta">${escapeHtml(doctor.specialite_nom || 'Spécialité non définie')} · ${escapeHtml(doctor.email || '')}</div>
            <div class="tag-row"><span class="tag pending">En attente</span><span class="tag neutral">Soumis ${submitted}</span>${note}</div>
          </div>
          <button class="btn btn-primary btn-sm" type="button" onclick="openDoctor(${Number(doctor.id)})">Vérifier</button>
        </article>`;
        }).join('');
      }
      function renderPendingProfileChanges(rows) {
        const root = document.getElementById('pendingProfileChangeQueue');
        const count = Array.isArray(rows) ? rows.length : 0;
        document.getElementById('changeQueueCounter').textContent = `${count} demande${count > 1 ? 's' : ''}`;
        if (!count) {
          root.innerHTML = `<div class="empty"><div class="empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>Aucune demande de changement en attente.</div>`;
          return;
        }
        root.innerHTML = rows.map((item) => {
          const submitted = formatDate(item.submitted_at);
          return `<article class="queue-item">
            <div>
              <div class="doctor-name">${escapeHtml(item.title || 'Demande de changement')}</div>
              <div class="doctor-meta">Dr ${escapeHtml(item.prenom || '')} ${escapeHtml(item.nom || '')} · ${escapeHtml(item.specialite_nom || 'Spécialité non définie')}</div>
              <div class="tag-row"><span class="tag pending">Changement</span><span class="tag neutral">Soumis ${submitted}</span></div>
            </div>
            <button class="btn btn-primary btn-sm" type="button" onclick="openProfileChange(${Number(item.id)})">Ouvrir</button>
          </article>`;
        }).join('');
      }

    function renderRecentActivity(rows) {
      const root = document.getElementById('recentActivity');
      if (!rows || !rows.length) {
        root.innerHTML = `<div class="empty">Pas encore d'activité récente à afficher.</div>`;
        return;
      }
      root.innerHTML = rows.map(doctor => `
        <article class="activity-item">
          <div class="tag-row" style="margin-bottom:8px">
            <span class="tag ${getStatusClass(doctor.workflow_status || doctor.approval_status)}">${approvalLabel(doctor.workflow_status || doctor.approval_status)}</span>
            <span class="tag neutral">${formatDate(doctor.approved_at || doctor.submitted_at || doctor.created_at)}</span>
          </div>
          <div class="doctor-name">Dr ${escapeHtml(doctor.prenom || '')} ${escapeHtml(doctor.nom || '')}</div>
          <div class="doctor-meta">${escapeHtml(doctor.specialite_nom || 'Spécialité non définie')} · ${escapeHtml(doctor.email || '')}</div>
          <div class="activity-note">${escapeHtml(doctor.review_note || 'Aucune note admin pour ce dossier.')}</div>
        </article>`).join('');
    }

    function renderDoctorsTable() {
      const body = document.getElementById('doctorsTableBody');
      if (!state.doctors.length) {
        body.innerHTML = `<tr><td colspan="5"><div class="empty">Aucun médecin ne correspond au filtre actuel.</div></td></tr>`;
        return;
      }
      body.innerHTML = state.doctors.map(doctor => {
        const { score } = calculateChecklist(doctor);
        const isActive = state.selectedDoctorId === Number(doctor.id);
        return `<tr class="row-click ${isActive ? 'is-active' : ''}" onclick="openDoctor(${Number(doctor.id)})">
          <td><div class="table-dr-name">Dr ${escapeHtml(doctor.prenom || '')} ${escapeHtml(doctor.nom || '')}</div><div class="table-dr-email">${escapeHtml(doctor.email || '')}</div></td>
          <td>${escapeHtml(doctor.specialite_nom || 'Non définie')}</td>
          <td><span class="score-pill" style="font-size:.75rem;padding:5px 10px;">${score}%</span></td>
          <td><span class="tag ${getStatusClass(doctor.workflow_status || doctor.approval_status)}">${approvalLabel(doctor.workflow_status || doctor.approval_status)}</span></td>
          <td style="color:var(--muted);font-size:.82rem">${formatDate(doctor.submitted_at || doctor.created_at)}</td>
        </tr>`;
      }).join('');
    }

    function renderChecklist(doctor) {
      const root = document.getElementById('verificationChecklist');
      const { checks, score } = calculateChecklist(doctor);
      document.getElementById('detailScoreText').textContent = `${score}%`;
      document.getElementById('detailScoreBar').style.width = `${score}%`;
      root.innerHTML = checks.map(item => {
        const cls = item.ok ? 'ok' : (score >= 60 ? 'warn' : 'bad');
        const label = item.ok ? '✓ OK' : 'À revoir';
        return `<div class="check-item">
          <div>
            <div class="check-item-title">${escapeHtml(item.title)}</div>
            <div class="check-item-desc">${escapeHtml(item.description)}</div>
          </div>
          <span class="check-badge ${cls}">${label}</span>
        </div>`;
      }).join('');
    }

    function renderDoctorDetail(doctor) {
      state.selectedWorkspaceType = 'doctor';
      state.selectedProfileChangeId = null;
      state.selectedDoctorId = Number(doctor.id);
      const workflowStatus = doctor.workflow_status || doctor.approval_status;
      document.getElementById('doctorDetailEmpty').style.display = 'none';
      document.getElementById('doctorDetail').style.display = 'block';
      document.getElementById('profileChangeBlock').style.display = 'none';
      document.getElementById('detailStatus').className = `tag ${getStatusClass(workflowStatus)}`;
      document.getElementById('detailStatus').textContent = approvalLabel(workflowStatus);
      document.getElementById('detailSubmittedAtTop').textContent = workflowStatus === 'draft'
        ? `Brouillon créé ${formatDate(doctor.created_at)}`
        : `Soumis ${formatDate(doctor.submitted_at || doctor.created_at)}`;
      document.getElementById('detailName').textContent = `Dr ${doctor.prenom || ''} ${doctor.nom || ''}`.trim();
      document.getElementById('detailSubtitle').textContent = `${doctor.specialite_nom || 'Spécialité non définie'} · créé le ${formatDate(doctor.created_at)}`;
      document.getElementById('detailVisibility').textContent = workflowStatus === 'approved' ? 'Profil publiable' : workflowStatus === 'rejected' ? 'Correction demandée' : workflowStatus === 'draft' ? 'Brouillon non envoyé' : 'Publication en attente';
      document.getElementById('detailEmail').textContent = doctor.email || 'Non renseigné';
      document.getElementById('detailPhone').textContent = doctor.telephone || 'Non renseigné';
      document.getElementById('detailSpecialite').textContent = doctor.specialite_nom || 'Non définie';
      document.getElementById('detailLocation').textContent = [doctor.wilaya_id ? `Wilaya #${doctor.wilaya_id}` : null, doctor.commune || null].filter(Boolean).join(' / ') || 'Non renseigné';
      document.getElementById('detailAgrement').textContent = doctor.agrement_no || 'Non renseigné';
      document.getElementById('detailExperience').textContent = doctor.experience_years ? `${doctor.experience_years} an(s)` : 'Non renseigné';
      document.getElementById('detailTarif').textContent = doctor.tarif ? `${doctor.tarif} DZD` : 'Non renseigné';
      document.getElementById('detailAccountState').textContent = Number(doctor.is_active) === 1 ? (doctor.email_verified_at ? 'Actif · email vérifié' : 'Actif · email non vérifié') : 'Compte inactif';
      document.getElementById('detailAdresse').textContent = doctor.adresse || 'Aucune adresse renseignée';
      document.getElementById('detailBio').textContent = doctor.bio || 'Aucune bio fournie';
      document.getElementById('detailFormations').textContent = doctor.formations_text || 'Aucune formation fournie';
      document.getElementById('detailHopitaux').textContent = doctor.hopitaux_text || 'Aucun établissement renseigné';
      document.getElementById('reviewNote').value = doctor.review_note || '';
      document.getElementById('reviewFlash').className = 'flash';
      document.getElementById('saveNoteBtn').textContent = 'Enregistrer la note';
      document.getElementById('approveDoctorBtn').textContent = 'Approuver';
      document.getElementById('rejectDoctorBtn').textContent = 'Refuser';
      document.getElementById('approveDoctorBtn').disabled = workflowStatus === 'draft';
      document.getElementById('rejectDoctorBtn').disabled = workflowStatus === 'draft';
      document.getElementById('saveNoteBtn').disabled = workflowStatus === 'draft';
      renderChecklist(doctor);
      renderDoctorsTable();
    }
    function renderProfileChangeDetail(request) {
      state.selectedWorkspaceType = 'profile_change';
      state.selectedProfileChangeId = Number(request.id);
      state.selectedDoctorId = Number(request.medecin_id);
      document.getElementById('doctorDetailEmpty').style.display = 'none';
      document.getElementById('doctorDetail').style.display = 'block';
      document.getElementById('profileChangeBlock').style.display = 'block';
      document.getElementById('detailStatus').className = `tag ${getStatusClass(request.status)}`;
      document.getElementById('detailStatus').textContent = changeStatusLabel(request.status);
      document.getElementById('detailSubmittedAtTop').textContent = `Soumis ${formatDate(request.submitted_at)}`;
      document.getElementById('detailName').textContent = `Demande profil · Dr ${request.prenom || ''} ${request.nom || ''}`.trim();
      document.getElementById('detailSubtitle').textContent = `${request.specialite_nom || 'Spécialité non définie'} · ${request.email || 'Email non renseigné'}`;
      document.getElementById('detailVisibility').textContent = request.status === 'pending' ? 'Validation admin requise' : changeStatusLabel(request.status);
      document.getElementById('detailEmail').textContent = request.email || 'Non renseigné';
      document.getElementById('detailPhone').textContent = request.telephone || 'Non renseigné';
      document.getElementById('detailSpecialite').textContent = request.specialite_nom || 'Non définie';
      document.getElementById('detailLocation').textContent = [request.wilaya_id ? `Wilaya #${request.wilaya_id}` : null, request.commune || null].filter(Boolean).join(' / ') || 'Non renseigné';
      document.getElementById('detailAgrement').textContent = 'Voir changements demandés';
      document.getElementById('detailExperience').textContent = '—';
      document.getElementById('detailTarif').textContent = '—';
      document.getElementById('detailAccountState').textContent = request.status === 'pending' ? 'Demande en attente' : changeStatusLabel(request.status);
      document.getElementById('detailAdresse').textContent = 'Le profil actuel reste inchangé tant que la demande n’est pas approuvée.';
      document.getElementById('detailBio').textContent = request.description || 'Aucune description fournie.';
      document.getElementById('detailFormations').textContent = 'Titre: ' + (request.title || 'Sans titre');
      document.getElementById('detailHopitaux').textContent = 'Modification soumise à validation admin.';
      document.getElementById('detailChangeTitle').textContent = request.title || 'Sans titre';
      document.getElementById('detailChangeDescription').textContent = request.description || 'Aucune description fournie.';
      document.getElementById('detailChangePayload').textContent = formatChangePayload(request.payload || {});
      document.getElementById('reviewNote').value = request.admin_note || '';
      document.getElementById('reviewFlash').className = 'flash';
      document.getElementById('approveDoctorBtn').disabled = request.status !== 'pending';
      document.getElementById('rejectDoctorBtn').disabled = request.status !== 'pending';
      document.getElementById('saveNoteBtn').disabled = request.status !== 'pending';
      document.getElementById('approveDoctorBtn').textContent = 'Approuver le changement';
      document.getElementById('rejectDoctorBtn').textContent = 'Refuser le changement';
      document.getElementById('saveNoteBtn').textContent = 'Enregistrer la note';
      document.getElementById('verificationChecklist').innerHTML = `<div class="check-item"><div><div class="check-item-title">Changements demandés</div><div class="check-item-desc">Vérifie le titre, la description et le contenu avant d’approuver la mise à jour du profil public.</div></div><div class="check-item-state warn">À relire</div></div>`;
      document.getElementById('detailScoreText').textContent = '—';
      document.getElementById('detailScoreBar').style.width = '0%';
      renderDoctorsTable();
    }

    async function loadDashboard() {
      const payload = await api('/api/admin/dashboard/');
      state.dashboard = payload;
      fillMetrics(payload.summary || {});
      renderPendingQueue(payload.pending_queue || []);
      renderPendingProfileChanges(payload.pending_profile_changes || []);
      renderRecentActivity(payload.recent_activity || []);
      const admin = payload.admin || {};
      const fullName = `${admin.prenom || ''} ${admin.nom || ''}`.trim() || 'Administrateur';
      document.getElementById('adminName').textContent = fullName;
      document.getElementById('adminEmail').textContent = admin.email || 'admin@tabibdz.local';
      const initials = ((admin.prenom || 'A')[0] || 'A').toUpperCase() + ((admin.nom || 'D')[0] || 'D').toUpperCase();
      document.getElementById('adminAvatar').textContent = initials;
    }

    async function loadDoctors() {
      const status = document.getElementById('doctorStatus').value;
      const search = document.getElementById('doctorSearch').value.trim();
      const params = new URLSearchParams();
      if (status) params.set('status', status);
      if (search) params.set('search', search);
      const payload = await api(`/api/admin/medecins/${params.toString() ? '?' + params.toString() : ''}`);
      state.doctors = Array.isArray(payload.data) ? payload.data : [];
      renderDoctorsTable();
      if (state.selectedDoctorId) {
        const current = state.doctors.find(d => Number(d.id) === Number(state.selectedDoctorId));
        if (current) renderDoctorDetail(current);
      }
    }

    async function openDoctor(id) {
      const payload = await api(`/api/admin/medecins/item.php?id=${Number(id)}`);
      renderDoctorDetail(payload.data);
    }
    async function openProfileChange(id) {
      const payload = await api(`/api/admin/profile-changes/item.php?id=${Number(id)}`);
      renderProfileChangeDetail(payload.data);
    }

    async function updateDoctorStatus(status) {
      if (!state.selectedDoctorId) return;
      const reviewNote = document.getElementById('reviewNote').value.trim();
      const payload = { review_note: reviewNote };
      if (status) payload.approval_status = status;
      await api(`/api/admin/medecins/item.php?id=${state.selectedDoctorId}`, { method: 'PATCH', body: JSON.stringify(payload) });
      showFlash(
        status === 'approved' ? 'Dossier approuvé et note admin enregistrée.' :
        status === 'rejected' ? 'Dossier refusé. La note de vérification a été enregistrée.' :
        'Note admin enregistrée.', 'success'
      );
      await Promise.all([loadDashboard(), loadDoctors()]);
      await openDoctor(state.selectedDoctorId);
    }
    async function updateProfileChangeStatus(action) {
      if (!state.selectedProfileChangeId) return;
      const adminNote = document.getElementById('reviewNote').value.trim();
      await api(`/api/admin/profile-changes/item.php?id=${state.selectedProfileChangeId}`, {
        method: 'PATCH',
        body: JSON.stringify({ action, admin_note: adminNote })
      });
      showFlash(
        action === 'approve' ? 'Demande de changement approuvée et profil mis à jour.' :
        action === 'reject' ? 'Demande de changement refusée.' :
        'Note admin enregistrée.',
        'success'
      );
      await Promise.all([loadDashboard(), loadDoctors()]);
      await openProfileChange(state.selectedProfileChangeId);
    }

    document.getElementById('refreshDashboardBtn').addEventListener('click', async () => {
      try { await Promise.all([loadDashboard(), loadDoctors()]); } catch (e) { showFlash(e.message, 'error'); }
    });
    document.getElementById('focusPendingBtn').addEventListener('click', async () => {
      document.getElementById('doctorStatus').value = 'pending';
      await loadDoctors();
      document.querySelector('.queue-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    document.getElementById('searchDoctorsBtn').addEventListener('click', loadDoctors);
    document.getElementById('doctorSearch').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); loadDoctors(); } });
    document.getElementById('saveNoteBtn').addEventListener('click', () => {
      if (state.selectedWorkspaceType === 'profile_change') return updateProfileChangeStatus('note').catch(e => showFlash(e.message, 'error'));
      return updateDoctorStatus('').catch(e => showFlash(e.message, 'error'));
    });
    document.getElementById('approveDoctorBtn').addEventListener('click', () => {
      if (state.selectedWorkspaceType === 'profile_change') return updateProfileChangeStatus('approve').catch(e => showFlash(e.message, 'error'));
      return updateDoctorStatus('approved').catch(e => showFlash(e.message, 'error'));
    });
    document.getElementById('rejectDoctorBtn').addEventListener('click', () => {
      if (state.selectedWorkspaceType === 'profile_change') return updateProfileChangeStatus('reject').catch(e => showFlash(e.message, 'error'));
      return updateDoctorStatus('rejected').catch(e => showFlash(e.message, 'error'));
    });
    document.getElementById('logoutBtn').addEventListener('click', async () => {
      try { await api('/api/auth/logout/', { method: 'POST' }); } catch (e) {}
      window.location.replace('/admin/login.html');
    });

    async function ensureAdminSession() {
      try {
        const payload = await api('/api/auth/me/');
        if (!payload.user || payload.user.role !== 'admin') { window.location.replace('/admin/login.html'); return false; }
        return true;
      } catch (e) { window.location.replace('/admin/login.html'); return false; }
    }

    (async function bootstrap() {
      const ok = await ensureAdminSession();
      if (!ok) return;
      try { await Promise.all([loadDashboard(), loadDoctors()]); }
      catch (e) { document.getElementById('doctorDetailEmpty').querySelector('.empty').textContent = 'Accès admin indisponible.'; }
    })();
  