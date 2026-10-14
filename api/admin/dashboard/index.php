<?php
declare(strict_types=1);

require_once __DIR__ . '/../../_core/helpers.php';

$admin = requireAuth('admin');
assertMethod('GET');

$pdo = db();

$summary = [
    'pending_medecins' => (int) $pdo->query("SELECT COUNT(*) FROM medecin_profiles WHERE COALESCE(approval_status, 'pending') = 'pending' AND submitted_at IS NOT NULL")->fetchColumn(),
    'approved_medecins' => (int) $pdo->query("SELECT COUNT(*) FROM medecin_profiles WHERE COALESCE(approval_status, 'pending') = 'approved'")->fetchColumn(),
    'rejected_medecins' => (int) $pdo->query("SELECT COUNT(*) FROM medecin_profiles WHERE COALESCE(approval_status, 'pending') = 'rejected'")->fetchColumn(),
    'draft_medecins' => (int) $pdo->query("SELECT COUNT(*) FROM medecin_profiles WHERE COALESCE(approval_status, 'pending') = 'pending' AND submitted_at IS NULL")->fetchColumn(),
    'pending_profile_changes' => (int) $pdo->query("SELECT COUNT(*) FROM medecin_profile_change_requests WHERE status = 'pending'")->fetchColumn(),
    'patients_count' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn(),
    'admins_count' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
    'appointments_count' => (int) $pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn(),
];

$pendingStmt = $pdo->query(
    "SELECT u.id,
            u.prenom,
            u.nom,
            u.email,
            u.telephone,
            u.created_at,
            s.nom AS specialite_nom,
            mp.submitted_at,
            mp.review_note,
            'pending' AS workflow_status
     FROM users u
     INNER JOIN medecin_profiles mp ON mp.user_id = u.id
     LEFT JOIN specialites s ON s.id = mp.specialite_id
     WHERE u.role = 'medecin'
       AND COALESCE(mp.approval_status, 'pending') = 'pending'
       AND mp.submitted_at IS NOT NULL
     ORDER BY COALESCE(mp.submitted_at, u.created_at) ASC
     LIMIT 8"
);

$recentActivityStmt = $pdo->query(
    "SELECT u.id,
            u.prenom,
            u.nom,
            u.email,
            COALESCE(mp.approval_status, 'pending') AS approval_status,
            CASE
                WHEN COALESCE(mp.approval_status, 'pending') = 'pending' AND mp.submitted_at IS NULL THEN 'draft'
                ELSE COALESCE(mp.approval_status, 'pending')
            END AS workflow_status,
            mp.submitted_at,
            mp.approved_at,
            mp.review_note,
            s.nom AS specialite_nom
     FROM users u
     INNER JOIN medecin_profiles mp ON mp.user_id = u.id
     LEFT JOIN specialites s ON s.id = mp.specialite_id
     WHERE u.role = 'medecin'
     ORDER BY COALESCE(mp.approved_at, mp.submitted_at, u.created_at) DESC
     LIMIT 12"
);

$pendingChangeStmt = $pdo->query(
    "SELECT r.id,
            r.medecin_id,
            r.title,
            r.description,
            r.submitted_at,
            u.prenom,
            u.nom,
            u.email,
            s.nom AS specialite_nom
     FROM medecin_profile_change_requests r
     INNER JOIN users u ON u.id = r.medecin_id
     LEFT JOIN medecin_profiles mp ON mp.user_id = u.id
     LEFT JOIN specialites s ON s.id = mp.specialite_id
     WHERE r.status = 'pending'
     ORDER BY r.submitted_at ASC
     LIMIT 8"
);

jsonResponse([
    'message' => 'Dashboard admin charge',
    'admin' => [
        'id' => (int) $admin['id'],
        'prenom' => $admin['prenom'] ?? '',
        'nom' => $admin['nom'] ?? '',
        'email' => $admin['email'] ?? '',
    ],
    'summary' => $summary,
    'pending_queue' => $pendingStmt->fetchAll(),
    'pending_profile_changes' => $pendingChangeStmt->fetchAll(),
    'recent_activity' => $recentActivityStmt->fetchAll(),
]);
