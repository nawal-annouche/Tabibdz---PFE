const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  AlignmentType, HeadingLevel, BorderStyle, WidthType, ShadingType,
  VerticalAlign, PageNumber, Header, Footer, LevelFormat, PageBreak
} = require('docx');
const fs = require('fs');

// ─── helpers ────────────────────────────────────────────────────────────────
const FONT = "Times New Roman";
const SIZE_BODY = 24;   // 12pt
const SIZE_H1   = 32;   // 16pt
const SIZE_H2   = 28;   // 14pt
const SIZE_H3   = 26;   // 13pt

const border = { style: BorderStyle.SINGLE, size: 1, color: "CCCCCC" };
const borders = { top: border, bottom: border, left: border, right: border };
const noBorder = { style: BorderStyle.NONE, size: 0, color: "FFFFFF" };
const noBorders = { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder };

function h1(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_1,
    spacing: { before: 360, after: 180 },
    children: [new TextRun({ text, font: FONT, size: SIZE_H1, bold: true, color: "1F4E79" })],
  });
}
function h2(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_2,
    spacing: { before: 280, after: 140 },
    children: [new TextRun({ text, font: FONT, size: SIZE_H2, bold: true, color: "2E74B5" })],
  });
}
function h3(text) {
  return new Paragraph({
    heading: HeadingLevel.HEADING_3,
    spacing: { before: 200, after: 100 },
    children: [new TextRun({ text, font: FONT, size: SIZE_H3, bold: true, color: "4472C4" })],
  });
}
function p(text, opts = {}) {
  return new Paragraph({
    alignment: AlignmentType.JUSTIFIED,
    spacing: { before: 80, after: 80, line: 360 },
    children: [new TextRun({ text, font: FONT, size: SIZE_BODY, ...opts })],
  });
}
function pBold(text) { return p(text, { bold: true }); }
function empty() { return new Paragraph({ children: [new TextRun("")] }); }
function pageBreak() { return new Paragraph({ children: [new PageBreak()] }); }

function bullet(text, level = 0) {
  return new Paragraph({
    numbering: { reference: "bullets", level },
    spacing: { before: 60, after: 60, line: 300 },
    children: [new TextRun({ text, font: FONT, size: SIZE_BODY })],
  });
}
function numbered(text, level = 0) {
  return new Paragraph({
    numbering: { reference: "numbers", level },
    spacing: { before: 60, after: 60, line: 300 },
    children: [new TextRun({ text, font: FONT, size: SIZE_BODY })],
  });
}

function headerRow(cells, widths) {
  return new TableRow({
    tableHeader: true,
    children: cells.map((text, i) =>
      new TableCell({
        borders,
        width: { size: widths[i], type: WidthType.DXA },
        shading: { fill: "2E74B5", type: ShadingType.CLEAR },
        margins: { top: 80, bottom: 80, left: 120, right: 120 },
        verticalAlign: VerticalAlign.CENTER,
        children: [new Paragraph({
          alignment: AlignmentType.CENTER,
          children: [new TextRun({ text, font: FONT, size: SIZE_BODY, bold: true, color: "FFFFFF" })]
        })]
      })
    )
  });
}
function dataRow(cells, widths, shade = "FFFFFF") {
  return new TableRow({
    children: cells.map((text, i) =>
      new TableCell({
        borders,
        width: { size: widths[i], type: WidthType.DXA },
        shading: { fill: shade, type: ShadingType.CLEAR },
        margins: { top: 80, bottom: 80, left: 120, right: 120 },
        children: [new Paragraph({
          alignment: AlignmentType.LEFT,
          children: [new TextRun({ text, font: FONT, size: SIZE_BODY })]
        })]
      })
    )
  });
}
function makeTable(headerCells, rows, widths) {
  const total = widths.reduce((a, b) => a + b, 0);
  return new Table({
    width: { size: total, type: WidthType.DXA },
    columnWidths: widths,
    rows: [
      headerRow(headerCells, widths),
      ...rows.map((r, idx) => dataRow(r, widths, idx % 2 === 0 ? "F5F8FC" : "FFFFFF"))
    ]
  });
}

// ─── document ───────────────────────────────────────────────────────────────
const doc = new Document({
  numbering: {
    config: [
      {
        reference: "bullets",
        levels: [{
          level: 0, format: LevelFormat.BULLET, text: "•",
          alignment: AlignmentType.LEFT,
          style: { paragraph: { indent: { left: 720, hanging: 360 } } }
        }]
      },
      {
        reference: "numbers",
        levels: [{
          level: 0, format: LevelFormat.DECIMAL, text: "%1.",
          alignment: AlignmentType.LEFT,
          style: { paragraph: { indent: { left: 720, hanging: 360 } } }
        }]
      }
    ]
  },
  styles: {
    default: { document: { run: { font: FONT, size: SIZE_BODY } } },
    paragraphStyles: [
      {
        id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: SIZE_H1, bold: true, font: FONT, color: "1F4E79" },
        paragraph: { spacing: { before: 360, after: 180 }, outlineLevel: 0 }
      },
      {
        id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: SIZE_H2, bold: true, font: FONT, color: "2E74B5" },
        paragraph: { spacing: { before: 280, after: 140 }, outlineLevel: 1 }
      },
      {
        id: "Heading3", name: "Heading 3", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: SIZE_H3, bold: true, font: FONT, color: "4472C4" },
        paragraph: { spacing: { before: 200, after: 100 }, outlineLevel: 2 }
      }
    ]
  },
  sections: [{
    properties: {
      page: {
        size: { width: 11906, height: 16838 },
        margin: { top: 1440, right: 1134, bottom: 1440, left: 1701 }
      }
    },
    headers: {
      default: new Header({
        children: [new Paragraph({
          alignment: AlignmentType.RIGHT,
          border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: "2E74B5", space: 4 } },
          children: [new TextRun({ text: "Chapitre 3 : Réalisation", font: FONT, size: 20, color: "666666", italics: true })]
        })]
      })
    },
    footers: {
      default: new Footer({
        children: [new Paragraph({
          alignment: AlignmentType.CENTER,
          border: { top: { style: BorderStyle.SINGLE, size: 4, color: "2E74B5", space: 4 } },
          children: [
            new TextRun({ text: "TabibDZ  —  Page ", font: FONT, size: 18, color: "888888" }),
            new TextRun({ children: [PageNumber.CURRENT], font: FONT, size: 18, color: "888888" }),
          ]
        })]
      })
    },
    children: [

      // ── TITRE DU CHAPITRE ──────────────────────────────────────────────────
      new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { before: 480, after: 120 },
        children: [new TextRun({ text: "CHAPITRE 3", font: FONT, size: 40, bold: true, color: "1F4E79", allCaps: true })]
      }),
      new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { before: 0, after: 480 },
        border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: "2E74B5", space: 4 } },
        children: [new TextRun({ text: "Réalisation", font: FONT, size: 36, bold: true, color: "2E74B5" })]
      }),

      // ── INTRODUCTION ──────────────────────────────────────────────────────
      h1("Introduction"),
      p("Ce chapitre présente la phase de réalisation du projet TabibDZ, une plateforme web algérienne de santé numérique permettant aux patients de rechercher des médecins, de prendre des rendez-vous en ligne et d’effectuer des téléconsultations. Nous y décrivons l’environnement technique utilisé, l’architecture mise en place, la structure de la base de données, les différentes interfaces réalisées ainsi que les fonctionnalités implémentées. Nous abordons également les aspects de sécurité, les tests effectués, et les difficultés rencontrées lors du développement."),
      empty(),

      // ══════════════════════════════════════════════════════════════════════
      // 1. ENVIRONNEMENT DE DÉVELOPPEMENT
      // ══════════════════════════════════════════════════════════════════════
      h1("1. Environnement de développement"),

      // 1.1
      h2("1.1 Système d’exploitation et matériel"),
      p("Le projet a été développé sur un environnement Windows 11 Pro (64 bits). La machine utilisée dispose d’un processeur modern, de 16 Go de RAM et d’un disque SSD, ce qui garantit des temps de compilation et de rechargement rapides lors du développement local."),
      empty(),

      // 1.2
      h2("1.2 Outils de développement"),
      p("Les outils suivants ont été utilisés tout au long du cycle de développement :"),
      empty(),
      makeTable(
        ["Outil", "Version / Remarque", "Rôle"],
        [
          ["XAMPP", "PHP 8.x intégré", "Serveur PHP local (CLI)"],
          ["PHP", "8.1+", "Langage côté serveur"],
          ["MariaDB", "10.x", "Système de gestion de base de données"],
          ["Node.js", "v24", "Utilisé pour des scripts et outils de génération"],
          ["Visual Studio Code", "Dernière version", "Éditeur de code principal"],
          ["PowerShell", "Windows natif", "Scripts et automatisation"],
          ["Git", "—", "Contrôle de version"],
          ["Navigateur (Chrome)", "Dernière version", "Tests et débogage front-end"],
        ],
        [3200, 3200, 2626]
      ),
      empty(),

      // 1.3
      h2("1.3 Outils de conception"),
      p("Pour la conception graphique et la modélisation, les outils suivants ont été employés :"),
      bullet("Figma : maquettage des interfaces et conception de l’expérience utilisateur (UX/UI)."),
      bullet("draw.io / Lucidchart : réalisation des diagrammes UML (cas d’utilisation, classes, séquences)."),
      bullet("phpMyAdmin / DBeaver : administration visuelle de la base de données MariaDB."),
      bullet("Postman : test et documentation des endpoints de l’API REST."),
      empty(),

      // ══════════════════════════════════════════════════════════════════════
      // 2. ARCHITECTURE ET BASE DE DONNÉES
      // ══════════════════════════════════════════════════════════════════════
      pageBreak(),
      h1("2. Architecture et base de données"),

      // 2.1
      h2("2.1 Architecture globale"),
      p("TabibDZ adopte une architecture client-serveur de type SPA (Single-Page Application) côté client couplée à une API REST côté serveur. Cette séparation claire entre la couche présentation et la couche métier facilite la maintenabilité et l’évolutivité de l’application."),
      empty(),
      makeTable(
        ["Couche", "Technologie", "Description"],
        [
          ["Présentation (Front-end)", "HTML5, CSS3, JavaScript vanilla", "Interfaces utilisateur servies statiquement"],
          ["Logique métier (Back-end)", "PHP 8.1 (API REST sans framework)", "Traitement des requêtes HTTP, JWT, logique applicative"],
          ["Persistance des données", "MariaDB 10.x", "Base de données relationnelle, encodage utf8mb4"],
          ["Communication temps réel", "WebRTC / polling HTTP", "Téléconsultation et messagerie"],
          ["Notifications & emails", "PHP mail / SMTP", "Rappels de rendez-vous, confirmations"],
          ["Stockage fichiers", "Système de fichiers local", "Documents médicaux, ordonnances, photos"],
        ],
        [2500, 2800, 3726]
      ),
      empty(),
      p("L’API REST étant implémentée sans framework tiers, chaque module correspond à un dossier indépendant sous /api/, ce qui assure une organisation modulaire et lisible du code."),
      empty(),

      // 2.2
      h2("2.2 Organisation du projet"),
      p("Le projet est structuré selon le découpage suivant :"),
      empty(),
      makeTable(
        ["Répertoire", "Contenu"],
        [
          ["/api/", "API REST PHP : auth, rendez-vous, médecins, messages, notifications, téléconsultation, IA…"],
          ["/api/_core/", "Noyau applicatif : config, base de données (PDO), JWT, mailer, helpers"],
          ["/api/database/", "Scripts SQL : schéma, migrations, seeds de données de test"],
          ["/assets/", "Feuilles de style CSS globales et scripts JavaScript partagés"],
          ["/admin/", "Interface d’administration (dashboard, gestion utilisateurs)"],
          ["/dashboard/, /profil/, /rdv/…", "Pages front-end par module fonctionnel"],
          ["/teleconsultation/, /teleconsultation-room/", "Module de consultation vidéo en ligne"],
          ["/uploads/", "Stockage des fichiers envoyés par les utilisateurs"],
          ["/docs/", "Documentation technique du projet"],
        ],
        [3200, 5826]
      ),
      empty(),

      // 2.3
      h2("2.3 Structure de la base de données"),
      p("La base de données tabibdz est créée sous MariaDB avec l’encodage utf8mb4 (support complet de l’Unicode et des émoji). Elle comprend quinze tables principales couvrant l’ensemble des besoins métier de la plateforme."),
      empty(),
      p("Le schéma relationnel s’articule autour de la table centrale users (patients et médecins) à partir de laquelle rayonnent toutes les autres entités. Les clés étrangères avec ON DELETE CASCADE assurent l’intégrité référentielle automatiquement."),
      empty(),

      // 2.4
      h2("2.4 Description des tables principales"),
      empty(),
      makeTable(
        ["Table", "Description", "Colonnes clés"],
        [
          ["users", "Utilisateurs (patients et médecins)", "id, role, prenom, nom, email, password_hash, wilaya_id, groupe_sanguin"],
          ["medecin_profiles", "Profil professionnel du médecin", "user_id, specialite_id, tarif, cnas, casnos, note, avis_count, adresse"],
          ["specialites", "Catégories médicales (cardiologie, dermatologie…)", "id, nom, slug, icon_name, featured"],
          ["wilayas / communes", "Découpage géographique algérien (58 wilayas)", "id, nom, wilaya_id"],
          ["creneaux", "Créneaux horaires disponibles des médecins", "id, medecin_id, start_at, end_at, is_available"],
          ["appointments", "Rendez-vous pris par les patients", "id, patient_id, medecin_id, creneau_id, consultation_mode, statut, confirmation_code"],
          ["patient_family_members", "Membres de la famille liés à un patient", "id, patient_id, prenom, nom, relation, date_naissance"],
          ["appointment_documents", "Documents joints à un rendez-vous", "id, appointment_id, original_name, file_url, document_type, uploaded_by_role"],
          ["patient_favoris", "Médecins favoris d’un patient", "patient_id, medecin_id"],
          ["message_threads", "Fils de discussion patient-médecin", "id, patient_id, medecin_id, last_message_at"],
          ["messages", "Messages individuels d’un fil", "id, thread_id, sender_user_id, body, read_at_patient, read_at_medecin"],
          ["message_attachments", "Pièces jointes des messages", "id, message_id, kind (image/file/audio), file_url"],
          ["notifications", "Notifications en temps réel pour les patients", "id, patient_id, title, body, is_read"],
          ["notification_preferences", "Paramètres de notifications de chaque patient", "patient_id, sms_24h, email_24h, email_2h, sms_annulation"],
          ["teleconsultation_sessions", "Sessions de téléconsultation (salles vidéo)", "id, appointment_id, room_token, statut"],
        ],
        [2400, 3200, 3426]
      ),
      empty(),

      // ══════════════════════════════════════════════════════════════════════
      // 3. PRÉSENTATION DES INTERFACES
      // ══════════════════════════════════════════════════════════════════════
      pageBreak(),
      h1("3. Présentation des interfaces"),

      // 3.1
      h2("3.1 Interface publique"),
      p("La page d’accueil (tabibdz-homepage.html) constitue le point d’entrée de la plateforme pour tout visiteur non connecté. Elle présente :"),
      bullet("Une barre de navigation avec les liens vers les sections principales (Accueil, Comment ça marche, À propos, Contact)."),
      bullet("Un moteur de recherche permettant de trouver un médecin par spécialité, nom ou localisation."),
      bullet("Les spécialités médicales mises en avant (cardiologie, dermatologie, pédiatrie, ophtalmologie, etc.)."),
      bullet("Une sélection de médecins réputés avec leur note et leur tarif de consultation."),
      bullet("Les appels à l’action (inscription, connexion, prise de rendez-vous)."),
      empty(),
      p("La page « Comment ça marche » (tabibdz-comment-ca-marche.html) explique le parcours utilisateur en trois étapes illustrées : recherche du médecin, prise de rendez-vous, et consultation (présentielle ou en téléconsultation)."),
      empty(),

      // 3.2
      h2("3.2 Interface d’authentification"),
      p("Le module d’authentification comprend les écrans suivants :"),
      bullet("Connexion : formulaire email / mot de passe avec gestion des erreurs et option « Se souvenir de moi ». Le jeton JWT est stocké dans un cookie HttpOnly sécurisé."),
      bullet("Inscription : création de compte pour patients et médecins, avec validation des données côté serveur (unicité de l’email, solidité du mot de passe)."),
      bullet("Mot de passe oublié : envoi d’un lien de réinitialisation par email avec token à usage unique."),
      bullet("Vérification d’email : confirmation de l’adresse électronique par un code envoyé à l’inscription."),
      empty(),

      // 3.3
      h2("3.3 Espace patient"),
      p("Après connexion, le patient accède à son tableau de bord personnel (tabibdz-dashboard.html) qui centralise :"),
      bullet("La liste de ses prochains rendez-vous (avec médecin, date, heure, mode de consultation)."),
      bullet("L’historique complet des consultations passées (terminées ou annulées)."),
      bullet("L’accès rapide à la messagerie avec ses médecins."),
      bullet("Le centre de notifications (rappels, confirmations, nouveaux messages)."),
      bullet("La gestion de son profil : informations personnelles, groupe sanguin, allergies, numéro d’assurance CNAS/CASNOS."),
      bullet("La gestion des membres de sa famille : ajout de proches pour lesquels il peut prendre des rendez-vous."),
      bullet("Ses médecins favoris."),
      empty(),

      // 3.4
      h2("3.4 Espace médecin"),
      p("L’espace médecin (tabibdz-espace-medecin.html) offre une interface dédiée comprenant :"),
      bullet("La vue d’agenda hebdomadaire avec les créneaux disponibles et les rendez-vous planifiés."),
      bullet("La gestion des rendez-vous : consultation des détails, changement de statut (confirmé, terminé, annulé)."),
      bullet("La messagerie avec les patients : envoi et réception de messages et de pièces jointes."),
      bullet("La gestion des créneaux horaires : ajout, modification et suppression de plages de disponibilité."),
      bullet("Les statistiques personnelles : nombre de consultations, taux d’occupation, note moyenne."),
      bullet("La génération d’ordonnances au format Word et PDF."),
      empty(),

      // 3.5
      h2("3.5 Interface d’administration"),
      p("Le panneau d’administration (/admin/) est réservé aux administrateurs de la plateforme. Il permet de :"),
      bullet("Superviser l’ensemble des utilisateurs (patients et médecins) et leurs informations."),
      bullet("Gérer les spécialités médicales disponibles sur la plateforme."),
      bullet("Consulter les statistiques globales (nombre d’inscriptions, de rendez-vous, de téléconsultations)."),
      bullet("Modérer les profils et, si nécessaire, intervenir sur les données."),
      empty(),

      // 3.6
      h2("3.6 Salle de téléconsultation"),
      p("La salle de téléconsultation (teleconsultation.html / teleconsultation-room.html) constitue l’une des fonctionnalités les plus avancées du projet. Elle comprend :"),
      bullet("Un système de jonction sécurisé à la salle via un token lié au rendez-vous."),
      bullet("La vidéoconférence en temps réel basée sur WebRTC."),
      bullet("Un chat intégré permettant l’échange de messages texte et de fichiers pendant la consultation."),
      bullet("Le partage de documents médicaux (ordonnances, résultats d’analyses) directement dans la salle."),
      bullet("Des contrôles audio/vidéo (muet, caméra, partage d’écran)."),
      empty(),

      // ══════════════════════════════════════════════════════════════════════
      // 4. FONCTIONNALITÉS IMPLÉMENTÉES
      // ══════════════════════════════════════════════════════════════════════
      pageBreak(),
      h1("4. Fonctionnalités implémentées"),

      // 4.1
      h2("4.1 Gestion des utilisateurs"),
      p("La gestion des utilisateurs constitue le socle de la plateforme. Elle couvre :"),
      bullet("L’inscription différenciée selon le rôle (patient ou médecin), avec validation des données d’entrée."),
      bullet("L’authentification par JWT (JSON Web Token) généré côté serveur et stocké en cookie HttpOnly."),
      bullet("La vérification de l’adresse email par code à usage unique."),
      bullet("La réinitialisation du mot de passe par email (lien sécurisé à durée limitée)."),
      bullet("La modification du profil utilisateur avec téléchargement d’une photo de profil."),
      bullet("La gestion des membres de la famille du patient (ajout, modification, suppression)."),
      empty(),

      // 4.2
      h2("4.2 Prise de rendez-vous"),
      p("Le module de prise de rendez-vous (tabibdz-prise-rdv.html) guide le patient à travers un processus en plusieurs étapes :"),
      numbered("Recherche d’un médecin par spécialité, nom ou wilaya."),
      numbered("Consultation du profil complet du médecin (tarif, adresse, avis, disponibilités)."),
      numbered("Sélection d’un créneau horaire disponible dans le calendrier du médecin."),
      numbered("Choix du mode de consultation : présentielle au cabinet ou téléconsultation."),
      numbered("Indication du patient cible : soi-même ou un membre de la famille."),
      numbered("Saisie du motif de la consultation et des notes éventuelles."),
      numbered("Confirmation du rendez-vous avec génération automatique d’un code de confirmation unique (format TBZ-AAAA-XXXX)."),
      empty(),
      p("Lors de la confirmation, le système marque automatiquement le créneau comme non disponible (is_available = 0) et envoie un email de confirmation au patient."),
      empty(),

      // 4.3
      h2("4.3 Téléconsultation"),
      p("La fonctionnalité de téléconsultation permet au patient et au médecin de se connecter à une salle de consultation vidéo en ligne. Le flux est le suivant :"),
      bullet("Le rendez-vous est créé avec le mode consultation_mode = ‘teleconsultation’."),
      bullet("Un token de salle unique est généré et associé au rendez-vous dans la table teleconsultation_sessions."),
      bullet("Chaque participant (patient et médecin) accède à la salle via l’endpoint /api/teleconsultation/join.php."),
      bullet("La communication vidéo est établie en pair-à-pair grâce à WebRTC, garantissant une faible latence."),
      bullet("Un chat latéral permet l’échange de messages et de fichiers pendant la séance."),
      empty(),

      // 4.4
      h2("4.4 Messagerie"),
      p("Le système de messagerie interne permet à un patient et à son médecin d’échanger des messages en dehors des séances de téléconsultation. Ses caractéristiques :"),
      bullet("Création automatique d’un fil de discussion (thread) unique par paire patient-médecin."),
      bullet("Envoi de messages texte, d’images, de fichiers et de messages audio."),
      bullet("Indicateurs de lecture (read_at_patient, read_at_medecin) pour savoir si le message a été lu."),
      bullet("Compteur de messages non lus affiché dans la navigation."),
      bullet("Stockage sécurisé des pièces jointes dans le dossier /uploads/."),
      empty(),

      // 4.5
      h2("4.5 Notifications et emails"),
      p("La plateforme intègre un double système de notification :"),
      pBold("Notifications in-app :"),
      bullet("Générées automatiquement lors des événements clés (confirmation de RDV, rappels, annulations)."),
      bullet("Stockées dans la table notifications et affichées dans le centre de notifications du tableau de bord."),
      bullet("Paramétrables par le patient (table notification_preferences) : choix des délais (24h, 2h avant)."),
      empty(),
      pBold("Notifications par email :"),
      bullet("Envoi via SMTP configurable dans le fichier .env (hote, port, sécurité SSL/TLS)."),
      bullet("Templates HTML multilingues codés dans /api/_core/mail_templates.php."),
      bullet("Journalisation dans /api/storage/logs/mailer.log pour le suivi et le débogage."),
      bullet("Emails envoyés : confirmation d’inscription, vérification d’email, réinitialisation de mot de passe, confirmation/rappel/annulation de rendez-vous."),
      empty(),

      // ══════════════════════════════════════════════════════════════════════
      // 5. SÉCURITÉ ET TESTS
      // ══════════════════════════════════════════════════════════════════════
      pageBreak(),
      h1("5. Sécurité et tests"),

      // 5.1
      h2("5.1 Mesures de sécurité"),
      p("Plusieurs mesures de sécurité ont été mises en place à différents niveaux de l’application :"),
      empty(),
      makeTable(
        ["Mesure", "Implémentation"],
        [
          ["Authentification JWT", "Tokens HS256 signés avec un secret, expiration configurable (défaut 24h), stockés en cookie HttpOnly pour éviter le vol par XSS."],
          ["Hachage des mots de passe", "Utilisation de password_hash() avec l’algorithme bcrypt (cost factor 10)."],
          ["Protection CSRF", "Cookies SameSite=Lax et vérification de l’origine des requêtes."],
          ["Validation des entrées", "Validation et assainissement de toutes les entrées utilisateur avant traitement ou stockage."],
          ["Requêtes préparées (PDO)", "Toutes les interactions avec la base de données utilisent des paramètres liés (prepared statements) pour prévenir les injections SQL."],
          ["CORS configurable", "Les origines autorisées sont listées explicitement dans .env (APP_ALLOWED_ORIGINS)."],
          ["Accès aux routes protégées", "Vérification du JWT et du rôle (patient / médecin / admin) avant tout accès à une ressource sensible."],
          ["Tokens à usage unique", "Les liens de réinitialisation de mot de passe et de vérification d’email sont basés sur des tokens aléatoires expirables."],
        ],
        [3000, 6026]
      ),
      empty(),

      // 5.2
      h2("5.2 Tests et validation"),
      p("La validation de l’application a été réalisée selon plusieurs approches complémentaires :"),
      pBold("Tests fonctionnels manuels :"),
      bullet("Vérification de chaque parcours utilisateur (inscription, connexion, prise de RDV, téléconsultation, messagerie) sur navigateur réel."),
      bullet("Tests des cas limites : créneau déjà réservé, email déjà utilisé, token expiré."),
      empty(),
      pBold("Tests de l’API :"),
      bullet("Utilisation de Postman pour tester chaque endpoint REST : codes de réponse HTTP, structure JSON, gestion des erreurs."),
      bullet("Vérification des règles d’autorisation : accès refusé sans JWT valide, accès croisés entre rôles."),
      empty(),
      pBold("Tests de compatibilité :"),
      bullet("Validation du rendu sur les principaux navigateurs (Chrome, Firefox, Edge)."),
      bullet("Vérification de la réactivité (responsive design) sur différentes résolutions d’écran."),
      empty(),
      pBold("Données de démonstration :"),
      bullet("Un jeu de données de test complet (tabibdz-seed-import-mariadb.sql) a été préparé avec des patients, des médecins, des rendez-vous et des notifications fictifs pour valider le comportement de l’application dans un contexte réaliste."),
      empty(),

      // ══════════════════════════════════════════════════════════════════════
      // 6. DIFFICULTÉS ET SOLUTIONS
      // ══════════════════════════════════════════════════════════════════════
      pageBreak(),
      h1("6. Difficultés rencontrées et solutions apportées"),
      empty(),
      makeTable(
        ["Difficulté", "Solution apportée"],
        [
          [
            "Gestion des créneaux horaires concurrents",
            "Mise en place d’une contrainte d’unicité (medecin_id, start_at) en base de données. Le serveur vérifie l’atomicité de la réservation avant de confirmer le rendez-vous."
          ],
          [
            "Implémentation de JWT sans bibliothèque externe",
            "Développement d’un module JWT léger en PHP pur (HS256) incluant la génération, la vérification de la signature HMAC et la gestion de l’expiration."
          ],
          [
            "Téléconsultation : traversal de NAT et pare-feu",
            "Utilisation de serveurs STUN/TURN publics (Google STUN) dans la configuration WebRTC pour assurer la connectivité entre pairs dans différents réseaux."
          ],
          [
            "Gestion des fichiers envoyés par les utilisateurs",
            "Validation stricte du type MIME et de la taille côté serveur. Renommage des fichiers avec des noms aléatoires (bin2hex) pour éviter les conflits et les attaques par traversal de chemin."
          ],
          [
            "Emails non reçus (SPAM ou non délivrés)",
            "Ajout d’en-têtes MIME complets (Message-ID, In-Reply-To) et mise en place d’un système de journalisation dans mailer.log pour détecter les échecs d’envoi."
          ],
          [
            "Prise de rendez-vous pour un membre de la famille",
            "Introduction d’une colonne patient_target_type et d’une clé étrangère family_member_id dans la table appointments, permettant de dissocier l’identité du payeur de celle du bénéficiaire."
          ],
          [
            "Intégration géographique algérienne (58 wilayas)",
            "Création de tables dédiées (wilayas, communes) pré-remplies avec les données officielles et déploiement dans le script de schéma SQL pour éviter les erreurs de saisie."
          ],
        ],
        [3200, 5826]
      ),
      empty(),

      // ══════════════════════════════════════════════════════════════════════
      // CONCLUSION
      // ══════════════════════════════════════════════════════════════════════
      pageBreak(),
      h1("Conclusion"),
      p("La phase de réalisation a permis de concrétiser l’ensemble des fonctionnalités conçues lors de la phase d’analyse et de conception. La plateforme TabibDZ constitue désormais une application web complète, couvrant la recherche de médecins, la prise de rendez-vous en ligne, la téléconsultation vidéo, la messagerie sécurisée et un système de notifications multi-canal."),
      empty(),
      p("Le choix d’une architecture sans framework tiers pour le back-end PHP a démontré qu’il est possible de réaliser une application robuste et sécurisée en maîtrisant les fondamentaux du développement web. Les difficultés rencontrées, notamment autour de la gestion des créneaux concurrents, de l’authentification JWT et de la téléconsultation WebRTC, ont été surmountées grâce à une analyse rigoureuse et à des solutions adaptées au contexte du projet."),
      empty(),
      p("Ce travail ouvre des perspectives d’amélioration futures telles que l’intégration d’un module de paiement en ligne, le déploiement sur une infrastructure cloud, l’ajout d’une application mobile, ainsi que l’enrichissement du module d’intelligence artificielle pour l’aide au triage médical."),
    ]
  }]
});

Packer.toBuffer(doc).then(buffer => {
  fs.writeFileSync("D:\\DS\\docs\\Chapitre3-Realisation.docx", buffer);
  console.log("OK: D:\\DS\\docs\\Chapitre3-Realisation.docx");
});
