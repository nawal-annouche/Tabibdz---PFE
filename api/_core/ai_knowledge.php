<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function aiKnowledgeFaq(): array
{
    return [
        [
            'id' => 'prise-rdv',
            'question' => 'Comment prendre un rendez-vous ?',
            'keywords' => ['rendez-vous', 'rdv', 'prendre', 'reserver', 'reservation'],
            'answer' => "Pour prendre un rendez-vous sur TabibDZ, recherchez d'abord un medecin par specialite ou par ville, ouvrez sa fiche, puis choisissez un creneau disponible. Ensuite, connectez-vous avec votre compte patient pour confirmer la demande.",
        ],
        [
            'id' => 'trouver-medecin',
            'question' => 'Comment trouver un medecin ?',
            'keywords' => ['trouver', 'chercher', 'medecin', 'specialiste', 'cardiologue', 'dermatologue', 'dentiste'],
            'answer' => "Utilisez la page de recherche pour filtrer par specialite, wilaya, commune, disponibilite, tarif et prise en charge CNAS ou CASNOS. Si vous me dites la specialite et la ville, je peux aussi vous guider plus vite.",
        ],
        [
            'id' => 'compte-patient',
            'question' => 'Comment creer un compte patient ?',
            'keywords' => ['compte patient', 'inscription', 'creer un compte', 'register', 'inscrire'],
            'answer' => "Pour creer un compte patient, ouvrez la page d'inscription, saisissez vos informations personnelles, puis validez. Une fois connecte, vous pourrez prendre des rendez-vous, gerer votre profil et suivre vos notifications.",
        ],
        [
            'id' => 'compte-medecin',
            'question' => 'Comment creer un compte medecin ?',
            'keywords' => ['compte medecin', 'espace medecin', 'inscription medecin', 'profil medecin'],
            'answer' => "Le compte medecin permet de completer le profil, publier les disponibilites et gerer les demandes. En general, il faut creer le compte, completer le profil professionnel puis attendre la validation si le workflow d'approbation est actif.",
        ],
        [
            'id' => 'specialites',
            'question' => 'A quoi servent les specialites ?',
            'keywords' => ['specialite', 'specialites', 'difference', 'cardiologue', 'pediatre', 'gynecologue'],
            'answer' => "Les specialites servent a orienter le patient vers le bon type de medecin. Par exemple, un cardiologue s'occupe du coeur, un dermatologue de la peau, un pediatre des enfants et un gynecologue de la sante feminine.",
        ],
        [
            'id' => 'cnas-casnos',
            'question' => 'Que veut dire CNAS ou CASNOS ?',
            'keywords' => ['cnas', 'casnos', 'assurance', 'prise en charge'],
            'answer' => "CNAS et CASNOS indiquent les regimes de prise en charge ou d'affiliation qui peuvent etre mentionnes dans la fiche du medecin. Vous pouvez utiliser les filtres de recherche pour trouver les medecins qui les acceptent.",
        ],
        [
            'id' => 'annuler-rdv',
            'question' => 'Comment annuler ou modifier un rendez-vous ?',
            'keywords' => ['annuler', 'modifier', 'deplacer', 'changer', 'reporter', 'rendez-vous', 'rdv'],
            'answer' => "Depuis votre espace patient, ouvrez la section des rendez-vous puis choisissez l'action disponible sur la reservation concernee. Selon l'etat du rendez-vous, vous pourrez l'annuler, le reprogrammer ou consulter ses details.",
        ],
        [
            'id' => 'messagerie',
            'question' => 'Comment fonctionne la messagerie ?',
            'keywords' => ['message', 'messagerie', 'contacter', 'envoyer', 'discussion'],
            'answer' => "La messagerie sert aux echanges entre patient et medecin apres un rendez-vous termine, selon les regles du projet. Elle permet de suivre une conversation depuis les espaces patient et medecin.",
        ],
        [
            'id' => 'photos-profils',
            'question' => 'Comment changer ma photo ou mon profil ?',
            'keywords' => ['photo', 'profil', 'avatar', 'modifier profil', 'changer photo'],
            'answer' => "Depuis votre espace connecte, vous pouvez mettre a jour certaines informations du profil et, selon le role, envoyer une nouvelle photo. Les changements sont enregistres via les endpoints de profil et de photo du backend.",
        ],
        [
            'id' => 'recherche-par-ville',
            'question' => 'Comment chercher un medecin par ville ou wilaya ?',
            'keywords' => ['ville', 'wilaya', 'commune', 'alger', 'oran', 'chercher par ville'],
            'answer' => "Sur la page de recherche, utilisez les filtres de wilaya et de commune pour limiter les resultats a une zone precise. Vous pouvez ensuite ajouter une specialite ou un tarif maximum pour affiner davantage.",
        ],
        [
            'id' => 'recherche-par-specialite',
            'question' => 'Comment chercher un medecin par specialite ?',
            'keywords' => ['specialite', 'specialiste', 'cardiologue', 'dermatologue', 'pediatre', 'ophtalmo'],
            'answer' => "La recherche par specialite se fait depuis la page principale ou la page recherche. Saisissez le nom de la specialite ou choisissez-la dans les suggestions, puis consultez les medecins proposes.",
        ],
        [
            'id' => 'voir-profil-medecin',
            'question' => 'Comment voir le profil detaille d un medecin ?',
            'keywords' => ['profil medecin', 'fiche medecin', 'details medecin', 'voir le profil'],
            'answer' => "Depuis les resultats de recherche ou une carte medecin, ouvrez la fiche ou le bouton profil. Vous pourrez alors consulter la specialite, la localisation, le tarif, la note, les avis et les prochains creneaux.",
        ],
        [
            'id' => 'voir-disponibilites',
            'question' => 'Comment voir les disponibilites d un medecin ?',
            'keywords' => ['disponibilite', 'creneau', 'horaire', 'disponible', 'prochain rendez-vous'],
            'answer' => "Les disponibilites apparaissent dans la fiche du medecin et dans certains resultats de recherche. Une fois sur la page du medecin, choisissez un creneau disponible pour initier la reservation.",
        ],
        [
            'id' => 'tarifs',
            'question' => 'Comment filtrer par tarif ?',
            'keywords' => ['tarif', 'prix', 'cout', 'pas cher', 'cher', 'budget'],
            'answer' => "La page de recherche permet de definir un tarif maximum pour n'afficher que les medecins dans votre budget. Le tarif de consultation est aussi visible sur la fiche de chaque medecin.",
        ],
        [
            'id' => 'avis-notes',
            'question' => 'A quoi correspondent les notes et avis ?',
            'keywords' => ['avis', 'note', 'evaluation', 'etoiles', 'commentaires'],
            'answer' => "Les notes et avis aident a se faire une idee de l'experience patient. Selon les donnees du profil, la note moyenne et le nombre d'avis sont affiches sur les cartes et les profils medecins.",
        ],
        [
            'id' => 'favoris',
            'question' => 'Comment ajouter un medecin aux favoris ?',
            'keywords' => ['favori', 'favoris', 'sauvegarder medecin', 'ajouter aux favoris'],
            'answer' => "Depuis votre espace patient ou les fiches medecins, vous pouvez enregistrer certains profils en favoris. Cela permet de retrouver plus vite les medecins que vous consultez souvent.",
        ],
        [
            'id' => 'notifications',
            'question' => 'Comment fonctionnent les notifications ?',
            'keywords' => ['notification', 'notifications', 'alerte', 'rappel', 'preferences notification'],
            'answer' => "Les notifications servent a suivre les actions importantes du compte, comme les rendez-vous ou certains changements. Dans l'espace patient, vous pouvez consulter les notifications et regler vos preferences si cette option est disponible.",
        ],
        [
            'id' => 'connexion',
            'question' => 'Comment me connecter ?',
            'keywords' => ['connexion', 'login', 'se connecter', 'entrer', 'compte'],
            'answer' => "Pour vous connecter, ouvrez la page de connexion, saisissez votre email et votre mot de passe, puis validez. Une fois authentifie, vous accedez a votre espace selon votre role patient, medecin ou admin.",
        ],
        [
            'id' => 'deconnexion',
            'question' => 'Comment me deconnecter ?',
            'keywords' => ['deconnexion', 'logout', 'se deconnecter', 'quitter le compte'],
            'answer' => "La deconnexion se fait depuis le menu du compte ou l'espace connecte. Elle supprime la session active et vous renvoie vers une zone publique du site.",
        ],
        [
            'id' => 'mot-de-passe-oublie',
            'question' => 'J ai oublie mon mot de passe, que faire ?',
            'keywords' => ['mot de passe oublie', 'reset password', 'forgot password', 'recuperer mot de passe'],
            'answer' => "Si vous avez oublie votre mot de passe, utilisez la fonction de recuperation sur l'ecran de connexion. Le backend prevoit des endpoints pour la demande de reinitialisation puis la definition d'un nouveau mot de passe.",
        ],
        [
            'id' => 'changer-mot-de-passe',
            'question' => 'Comment changer mon mot de passe ?',
            'keywords' => ['changer mot de passe', 'change password', 'nouveau mot de passe', 'modifier mot de passe'],
            'answer' => "Une fois connecte, vous pouvez utiliser la fonction de changement de mot de passe si elle est exposee dans l'interface. Le backend gere cette action via la route de changement de mot de passe.",
        ],
        [
            'id' => 'verification-email',
            'question' => 'Comment verifier mon email ?',
            'keywords' => ['verifier email', 'verification email', 'email verification', 'confirmer mon email'],
            'answer' => "Le projet peut envoyer une verification d'email apres inscription ou sur demande. Si l'interface le propose, il suffit de lancer l'envoi puis de suivre le lien recu.",
        ],
        [
            'id' => 'rdv-annule',
            'question' => 'Que se passe-t-il si mon rendez-vous est annule ?',
            'keywords' => ['rendez-vous annule', 'rdv annule', 'annulation', 'medecin annule'],
            'answer' => "Si un rendez-vous est annule, son statut est mis a jour dans votre espace. Vous pourrez alors consulter l'information, choisir un autre creneau ou reprendre la recherche selon le cas.",
        ],
        [
            'id' => 'historique-rdv',
            'question' => 'Ou voir mon historique de rendez-vous ?',
            'keywords' => ['historique rendez-vous', 'anciens rendez-vous', 'mes rdv', 'historique'],
            'answer' => "L'espace patient contient une section dediee aux rendez-vous et a leur historique. Vous pouvez y retrouver les reservations passees, les statuts et parfois les actions disponibles.",
        ],
        [
            'id' => 'telecharger-ics',
            'question' => 'Puis-je ajouter un rendez-vous a mon calendrier ?',
            'keywords' => ['calendrier', 'ics', 'agenda', 'google calendar', 'ajouter au calendrier'],
            'answer' => "Oui, le backend prevoit un endpoint ICS pour certains rendez-vous. Cela permet d'ajouter un rendez-vous a un calendrier compatible comme Outlook, Google Calendar ou Apple Calendar.",
        ],
        [
            'id' => 'patient-profil',
            'question' => 'Que puis-je faire dans l espace patient ?',
            'keywords' => ['espace patient', 'dashboard patient', 'compte patient', 'fonction patient'],
            'answer' => "Dans l'espace patient, vous pouvez gerer votre profil, vos rendez-vous, vos favoris, vos notifications et votre messagerie selon les droits disponibles. C'est le tableau de bord principal pour suivre votre activite.",
        ],
        [
            'id' => 'medecin-profil',
            'question' => 'Que puis-je faire dans l espace medecin ?',
            'keywords' => ['espace medecin', 'dashboard medecin', 'compte medecin', 'fonction medecin'],
            'answer' => "Dans l'espace medecin, vous pouvez completer votre profil, definir vos creneaux, consulter vos rendez-vous, suivre votre messagerie et visualiser certaines statistiques selon la configuration du projet.",
        ],
        [
            'id' => 'creneaux-medecin',
            'question' => 'Comment un medecin gere ses creneaux ?',
            'keywords' => ['creneaux medecin', 'gerer les creneaux', 'disponibilites medecin', 'horaires medecin'],
            'answer' => "Le medecin peut ajouter, supprimer ou ajuster ses creneaux depuis son espace. Ces disponibilites servent ensuite a proposer des rendez-vous aux patients dans la recherche et sur la fiche profil.",
        ],
        [
            'id' => 'validation-medecin',
            'question' => 'Pourquoi un profil medecin peut etre en attente ?',
            'keywords' => ['en attente', 'validation medecin', 'approval', 'approbation', 'profil en attente'],
            'answer' => "Le projet inclut un workflow d'approbation des medecins. Tant qu'un profil n'est pas approuve, certaines informations ou actions peuvent rester limitees avant publication.",
        ],
        [
            'id' => 'admin-role',
            'question' => 'A quoi sert l espace admin ?',
            'keywords' => ['admin', 'administrateur', 'espace admin', 'tableau admin'],
            'answer' => "L'espace admin sert a superviser les utilisateurs, les medecins, certaines specialites et parfois des operations de moderation. Il permet aussi de gerer des donnees internes du projet.",
        ],
        [
            'id' => 'specialites-featured',
            'question' => 'Comment afficher les specialites a la une ?',
            'keywords' => ['specialites a la une', 'featured specialites', 'specialites homepage'],
            'answer' => "Le backend expose une route permettant de recuperer les specialites mises en avant. Ces specialites peuvent ensuite etre affichees sur la homepage ou dans des sections marketing du site.",
        ],
        [
            'id' => 'prise-charge-sociale',
            'question' => 'Comment savoir si un medecin accepte CNAS ou CASNOS ?',
            'keywords' => ['accepte cnas', 'accepte casnos', 'prise en charge cnas', 'prise en charge casnos'],
            'answer' => "La fiche medecin et les filtres de recherche indiquent si le medecin accepte CNAS ou CASNOS. Vous pouvez donc filtrer les resultats avant meme d'ouvrir le profil detaille.",
        ],
        [
            'id' => 'langues-medecin',
            'question' => 'Comment savoir quelles langues parle un medecin ?',
            'keywords' => ['langues', 'parle arabe', 'parle francais', 'langue medecin'],
            'answer' => "Lorsque l'information est renseignee dans le profil medecin, les langues apparaissent dans les donnees de recherche ou sur la fiche du praticien. Cela aide les patients a choisir un medecin avec lequel ils sont a l'aise.",
        ],
        [
            'id' => 'secteur-medecin',
            'question' => 'Que veut dire secteur public, prive ou mixte ?',
            'keywords' => ['secteur public', 'secteur prive', 'mixte', 'type de secteur'],
            'answer' => "Le secteur indique le type d'exercice du medecin. Selon les donnees du projet, un medecin peut etre classe dans le public, le prive ou le mixte et ce critere peut servir a filtrer les recherches.",
        ],
        [
            'id' => 'avis-patient',
            'question' => 'Est-ce qu un patient peut laisser un avis ?',
            'keywords' => ['laisser un avis', 'commentaire patient', 'review', 'avis patient'],
            'answer' => "Le projet comporte un module d'avis medecin. Selon les regles d'interface et de validation, un patient peut consulter des avis et, si le parcours le permet, laisser une evaluation apres un rendez-vous.",
        ],
        [
            'id' => 'photos-upload',
            'question' => 'Ou sont stockees les photos de profil ?',
            'keywords' => ['photos', 'uploads', 'photo profil', 'stockage photo'],
            'answer' => "Les photos sont generalement stockees dans le dossier uploads du projet, avec des emplacements distincts pour les patients et les medecins. Le backend gere ensuite leur URL d'affichage.",
        ],
        [
            'id' => 'site-pages',
            'question' => 'Quelles sont les pages principales du site ?',
            'keywords' => ['pages du site', 'homepage', 'recherche', 'dashboard', 'contact', 'comment ca marche'],
            'answer' => "Les pages principales incluent la homepage, la recherche de medecins, le profil medecin, la prise de rendez-vous, l'espace patient, l'espace medecin, la page contact et la page comment ca marche.",
        ],
        [
            'id' => 'bug-auth',
            'question' => 'Pourquoi je n arrive pas a me connecter ?',
            'keywords' => ['je n arrive pas a me connecter', 'connexion impossible', 'authentification impossible', 'login error'],
            'answer' => "Verifiez d'abord votre email et votre mot de passe. Si le probleme persiste, il peut s'agir d'un compte non verifie, desactive, ou d'un souci de session ou de cookie selon l'environnement local.",
        ],
        [
            'id' => 'rdv-conditions',
            'question' => 'Faut-il etre connecte pour prendre un rendez-vous ?',
            'keywords' => ['etre connecte', 'faut-il un compte', 'prendre rendez-vous sans compte', 'connexion avant rdv'],
            'answer' => "En general, il faut etre connecte avec un compte patient pour confirmer un rendez-vous. La recherche publique peut etre visible sans connexion, mais la validation de la reservation demande une session utilisateur.",
        ],
        [
            'id' => 'search-best-practice',
            'question' => 'Comment obtenir de meilleurs resultats de recherche ?',
            'keywords' => ['meilleurs resultats', 'ameliorer recherche', 'astuce recherche', 'resultats recherche'],
            'answer' => "Pour de meilleurs resultats, combinez specialite, wilaya, commune et disponibilite. Vous pouvez aussi filtrer par tarif, secteur, langues ou prise en charge selon les informations disponibles.",
        ],
        [
            'id' => 'support-contact',
            'question' => 'Comment contacter le support ou demander de l aide ?',
            'keywords' => ['support', 'aide', 'contact', 'assistance', 'besoin d aide'],
            'answer' => "Vous pouvez utiliser la page contact du site pour demander de l'aide ou transmettre une question. Selon l'interface, certaines informations d'assistance sont aussi disponibles dans les pages publiques.",
        ],
        [
            'id' => 'who-are-you',
            'question' => 'Qui es-tu ?',
            'keywords' => ['qui es tu', 'tu es qui', 'c est quoi ce chat', 'assistant ia'],
            'answer' => "Je suis l'assistant IA de TabibDZ. Mon role est de guider les utilisateurs sur la recherche de medecins, la prise de rendez-vous, les comptes, les profils et l'utilisation generale de la plateforme.",
        ],
        [
            'id' => 'what-can-you-do',
            'question' => 'Que peux-tu faire ?',
            'keywords' => ['que peux tu faire', 'tes fonctions', 'aide moi', 'ce que tu sais faire'],
            'answer' => "Je peux repondre aux questions frequentes sur TabibDZ, expliquer les pages du site, guider les patients et les medecins, et aider a comprendre la recherche, les rendez-vous, les profils, les favoris, la messagerie et les notifications.",
        ],
        [
            'id' => 'book-for-someone',
            'question' => 'Puis-je prendre rendez-vous pour quelqu un d autre ?',
            'keywords' => ['pour quelqu un d autre', 'prendre rendez-vous pour ma mere', 'pour un proche', 'pour un parent'],
            'answer' => "Cela depend de la logique metier de votre interface et des informations demandees au moment de la reservation. Si le formulaire est lie au profil connecte, il faudra verifier si le projet prevoit une prise de rendez-vous pour un proche.",
        ],
        [
            'id' => 'doctor-location',
            'question' => 'Comment voir ou se trouve le cabinet du medecin ?',
            'keywords' => ['adresse', 'cabinet', 'map', 'localisation', 'ou se trouve'],
            'answer' => "La localisation du medecin est visible via la wilaya, la commune et parfois une URL de carte ou une adresse associee au profil. La fiche medecin est le meilleur endroit pour consulter ces informations.",
        ],
        [
            'id' => 'doctor-availability-today',
            'question' => 'Comment trouver un medecin disponible aujourd hui ?',
            'keywords' => ['aujourd hui', 'disponible aujourd hui', 'today', 'urgent', 'rapide'],
            'answer' => "Utilisez le filtre de disponibilite sur la page de recherche pour afficher les medecins ayant un creneau aujourd'hui. Les resultats peuvent aussi afficher le prochain creneau directement sur la carte du medecin.",
        ],
        [
            'id' => 'difference-patient-doctor-space',
            'question' => 'Quelle est la difference entre espace patient et espace medecin ?',
            'keywords' => ['difference espace patient medecin', 'patient ou medecin', 'quel espace utiliser'],
            'answer' => "L'espace patient sert surtout a chercher des medecins, prendre des rendez-vous et suivre son compte. L'espace medecin sert a gerer le profil professionnel, les disponibilites, les rendez-vous et certains echanges avec les patients.",
        ],
        [
            'id' => 'secure-data',
            'question' => 'Mes donnees sont-elles securisees ?',
            'keywords' => ['securise', 'donnees personnelles', 'confidentialite', 'securite'],
            'answer' => "Le backend du projet prevoit des mecanismes d'authentification, de cookies de session et de controle d'acces. En environnement reel, il faut aussi activer HTTPS, un secret JWT fort et des reglages de production stricts.",
        ],
        [
            'id' => 'admin-users',
            'question' => 'Que peut faire un administrateur avec les utilisateurs ?',
            'keywords' => ['admin utilisateurs', 'gerer les utilisateurs', 'administrateur users'],
            'answer' => "L'administrateur peut consulter et gerer les utilisateurs via les routes admin du backend. Selon les droits exposes dans l'interface, il peut aussi modifier certains statuts ou informations.",
        ],
        [
            'id' => 'admin-medecins',
            'question' => 'Que peut faire un administrateur avec les medecins ?',
            'keywords' => ['admin medecins', 'gerer medecins', 'administrateur medecin', 'validation medecins'],
            'answer' => "L'administrateur peut consulter les medecins, gerer leurs fiches, ajuster certains creneaux et traiter des workflows comme l'approbation des profils si cette logique est active dans le projet.",
        ],
        [
            'id' => 'admin-specialites',
            'question' => 'Que peut faire un administrateur avec les specialites ?',
            'keywords' => ['admin specialites', 'gerer specialites', 'administrateur specialites'],
            'answer' => "Le backend prevoit une gestion admin des specialites. Cela permet d'ajouter, modifier ou organiser les specialites selon les outils exposes par l'interface d'administration.",
        ],
        [
            'id' => 'local-install',
            'question' => 'Comment lancer le projet en local ?',
            'keywords' => ['lancer le projet', 'local', 'installation', 'migrate', 'php -s'],
            'answer' => "Pour lancer le projet en local, il faut configurer PHP, MariaDB, le fichier api/.env.local, lancer le serveur avec php -S 127.0.0.1:8000 puis executer les migrations via /api/database/migrate.php.",
        ],
        [
            'id' => 'api-endpoints',
            'question' => 'Le projet a-t-il une API ?',
            'keywords' => ['api', 'endpoint', 'backend', 'routes api'],
            'answer' => "Oui, le projet expose une API PHP pour l'authentification, les utilisateurs, les medecins, les rendez-vous, les specialites, les notifications, la messagerie et plusieurs fonctions d'administration.",
        ],
    ];
}

function aiFindFaqMatch(string $message): ?array
{
    $normalized = lowerUtf8($message);
    if ($normalized === '') {
        return null;
    }

    $best = null;
    $bestScore = 0;

    foreach (aiKnowledgeFaq() as $item) {
        $score = 0;
        foreach ($item['keywords'] as $keyword) {
            if (str_contains($normalized, lowerUtf8((string) $keyword))) {
                $score++;
            }
        }

        if (str_contains($normalized, lowerUtf8((string) $item['question']))) {
            $score += 2;
        }

        if ($score > $bestScore) {
            $best = $item;
            $bestScore = $score;
        }
    }

    if ($bestScore < 2) {
        return null;
    }

    $best['score'] = $bestScore;
    return $best;
}

function aiBuildProjectKnowledgeContext(): string
{
    $lines = [
        'Contexte projet TabibDZ:',
        '- Plateforme de recherche de medecins et prise de rendez-vous.',
        '- Espaces principaux: homepage, recherche, profil medecin, prise de rendez-vous, dashboard patient, espace medecin.',
        '- Fonctions courantes: authentification, gestion du profil, rendez-vous, favoris, notifications, messagerie, creneaux medecin.',
    ];

    try {
        $specialites = db()->query('SELECT nom FROM specialites ORDER BY nom LIMIT 12')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($specialites) {
            $lines[] = '- Exemples de specialites disponibles: ' . implode(', ', array_map(static fn($value): string => (string) $value, $specialites)) . '.';
        }
    } catch (Throwable $e) {
        // Le contexte statique suffit si la BD n'est pas disponible.
    }

    try {
        $wilayas = db()->query('SELECT nom FROM wilayas ORDER BY id LIMIT 10')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($wilayas) {
            $lines[] = '- Exemples de wilayas disponibles: ' . implode(', ', array_map(static fn($value): string => (string) $value, $wilayas)) . '.';
        }
    } catch (Throwable $e) {
        // Ignore.
    }

    $faqLines = [];
    foreach (aiKnowledgeFaq() as $faq) {
        $faqLines[] = '- Q: ' . $faq['question'] . ' | R: ' . $faq['answer'];
    }
    $lines[] = 'FAQ prioritaire:';
    array_push($lines, ...$faqLines);

    return implode("\n", $lines);
}

function aiEmergencyKeywords(): array
{
    return [
        'douleur thoracique intense',
        'douleur poitrine intense',
        'mal a la poitrine intense',
        'essoufflement severe',
        'difficulte a respirer',
        'detresse respiratoire',
        'perte de connaissance',
        'evanouissement',
        'convulsion',
        'paralysie brutale',
        'faiblesse d un cote',
        'faiblesse d’un cote',
        'visage tombe',
        'trouble brutal de la parole',
        'saignement important',
        'cracher du sang',
        'sang dans les vomissements',
        'douleur abdominale intense',
        'idee suicidaire',
        'suicidaire',
    ];
}

function aiSymptomSpecialtyRules(): array
{
    return [
        [
            'specialty' => 'Cardiologue',
            'keywords' => ['douleur poitrine', 'palpitations', 'coeur', 'coeur qui bat vite', 'oppression thoracique', 'tension elevee', 'hypertension', 'essoufflement effort'],
            'response' => "Les symptomes que vous decrivez peuvent relever d'un cardiologue, surtout en cas de douleur thoracique, palpitations, hypertension ou essoufflement a l'effort. Si la douleur thoracique est intense, dure, ou s'accompagne d'essoufflement important, de malaise ou d'une irradiation vers le bras ou la machoire, il faut chercher une aide medicale urgente.",
        ],
        [
            'specialty' => 'Dermatologue',
            'keywords' => ['boutons', 'acne', 'eczema', 'psoriasis', 'demangeaison', 'demangeaisons', 'plaque rouge', 'rougeur peau', 'grain de beaute', 'tache sur la peau', 'peau', 'ongle', 'cheveux qui tombent'],
            'response' => "Ces symptomes orientent plutot vers un dermatologue, qui s'occupe de la peau, des ongles et des cheveux. C'est souvent la bonne specialite pour les boutons persistants, demangeaisons, eczemas, plaques, chutes de cheveux ou lesions cutanees.",
        ],
        [
            'specialty' => 'Pneumologue',
            'keywords' => ['toux chronique', 'respiration', 'essoufflement', 'asthme', 'sifflement respiratoire', 'douleur en respirant', 'bronches', 'poumons'],
            'response' => "Cela peut relever d'un pneumologue, surtout en cas de toux persistante, essoufflement, sifflements ou symptomes lies aux poumons et aux bronches. Si vous avez une vraie difficulte a respirer ou une aggravation brutale, il faut consulter en urgence.",
        ],
        [
            'specialty' => 'ORL',
            'keywords' => ['mal oreille', 'otite', 'sinusite', 'nez bouche', 'nez', 'gorge', 'mal de gorge', 'vertige', 'bourdonnement', 'perte audition', 'saignement nez'],
            'response' => "Un ORL peut etre la specialite adaptee si vos symptomes touchent l'oreille, le nez, la gorge, les sinus, l'audition ou certains vertiges. C'est la bonne orientation pour les sinusites, douleurs d'oreille, troubles de voix ou problemes de gorge persistants.",
        ],
        [
            'specialty' => 'Ophtalmologue',
            'keywords' => ['vision floue', 'oeil rouge', 'oeil', 'yeux', 'baisse vision', 'douleur oeil', 'mouches volantes', 'larmoiement'],
            'response' => "Ces symptomes font plutot penser a une evaluation par un ophtalmologue. Une douleur oculaire, une baisse brutale de la vision ou un oeil rouge important doivent etre evalues rapidement.",
        ],
        [
            'specialty' => 'Neurologue',
            'keywords' => ['migraine', 'maux de tete frequents', 'maux de tete', 'mal a la tete', 'mal de tete', 'cephalee', 'fourmillement', 'engourdissement', 'crise', 'tremblement', 'vertige neurologique', 'faiblesse musculaire', 'memoire'],
            'response' => "Cela peut orienter vers un neurologue, notamment pour les migraines frequentes, les maux de tete repetes, fourmillements, tremblements, crises, troubles neurologiques ou symptomes touchant le cerveau, la moelle epiniere ou les nerfs. Si le mal de tete est brutal, tres intense, ou accompagne de faiblesse d'un cote, trouble de la parole, fievre importante, raideur de nuque ou perte de connaissance, il faut une prise en charge urgente.",
        ],
        [
            'specialty' => 'Gastro-enterologue',
            'keywords' => ['douleur ventre', 'douleur abdominale', 'brulure estomac', 'reflux', 'nausee', 'vomissements', 'constipation', 'diarrhee', 'sang dans les selles', 'foie'],
            'response' => "Ces symptomes peuvent relever d'un gastro-enterologue, surtout pour les douleurs digestives, reflux, troubles du transit ou problemes hepatiques et intestinaux. Si la douleur abdominale est intense ou s'il y a du sang, il faut une evaluation rapide.",
        ],
        [
            'specialty' => 'Gynecologue',
            'keywords' => ['regles', 'douleur pelvienne', 'cycle', 'pertes vaginales', 'grossesse', 'gyneco', 'ovaires', 'uterus', 'saignement entre les regles'],
            'response' => "Pour des symptomes gynecologiques comme douleurs pelviennes, troubles des regles, saignements anormaux ou questions liees a la grossesse et a la sante reproductive, un gynecologue est souvent la specialite adaptee.",
        ],
        [
            'specialty' => 'Urologue',
            'keywords' => ['uriner', 'brulure urinaire', 'sang dans les urines', 'prostate', 'testicule', 'douleur lombaire urinaire', 'infection urinaire homme'],
            'response' => "Cela peut relever d'un urologue, surtout en cas de problemes urinaires, de prostate, de testicules ou de sang dans les urines. Une douleur importante, une retention d'urine ou de la fievre associee doivent faire consulter rapidement.",
        ],
        [
            'specialty' => 'Orthopediste',
            'keywords' => ['genou', 'epaule', 'fracture', 'entorse', 'douleur os', 'douleur articulation', 'dos apres chute', 'poignet', 'cheville'],
            'response' => "Pour une douleur osseuse, articulaire ou un traumatisme, l'orientation peut aller vers un orthopediste. En cas de deformation, impossibilite de bouger, traumatisme important ou douleur intense apres chute, une evaluation urgente est souhaitable.",
        ],
        [
            'specialty' => 'Rhumatologue',
            'keywords' => ['douleur articulaire chronique', 'arthrite', 'articulations gonflees', 'raideur matinale', 'douleurs articulaires'],
            'response' => "Des douleurs articulaires chroniques, une raideur matinale ou des articulations gonflees peuvent relever d'un rhumatologue, notamment si le probleme semble inflammatoire ou durable.",
        ],
        [
            'specialty' => 'Endocrinologue',
            'keywords' => ['thyroide', 'diabete', 'sucre', 'prise de poids importante', 'perte de poids inexpliquée', 'perte de poids inexpliquee', 'hormones'],
            'response' => "Si vous parlez de diabete, thyroide, hormones, variations de poids ou troubles metaboliques, un endocrinologue peut etre la specialite adaptee.",
        ],
        [
            'specialty' => 'Pediatre',
            'keywords' => ['enfant', 'bebe', 'nourrisson', 'mon fils', 'ma fille', 'petit enfant'],
            'response' => "Comme il s'agit d'un enfant, le pediatre est souvent le premier specialiste ou referent adapte. Selon les symptomes, il pourra ensuite reorienter vers une autre specialite si necessaire.",
        ],
        [
            'specialty' => 'Psychiatre ou psychologue',
            'keywords' => ['anxiete', 'angoisse', 'depression', 'stress intense', 'insomnie severe', 'attaque de panique', 'moral tres bas'],
            'response' => "Pour des symptomes psychiques comme l'anxiete importante, les attaques de panique, la depression ou des idees noires, il faut envisager un professionnel de sante mentale. En cas d'idees suicidaires ou de danger immediat, il faut demander une aide urgente sans attendre.",
        ],
        [
            'specialty' => 'Medecin generaliste',
            'keywords' => ['fievre', 'fatigue', 'douleurs diffuses', 'je ne sais pas', 'plusieurs symptomes', 'symptomes generaux'],
            'response' => "Quand les symptomes sont generaux, multiples ou difficiles a classer, le medecin generaliste est souvent le meilleur premier point d'entree. Il peut faire une premiere evaluation puis orienter vers la specialite adaptee.",
        ],
    ];
}

function aiFindSymptomSpecialtyMatch(string $message): ?array
{
    $normalized = lowerUtf8($message);
    if ($normalized === '') {
        return null;
    }

    $emergencyDetected = false;
    foreach (aiEmergencyKeywords() as $keyword) {
        if (str_contains($normalized, lowerUtf8($keyword))) {
            $emergencyDetected = true;
            break;
        }
    }

    $best = null;
    $bestScore = 0;
    foreach (aiSymptomSpecialtyRules() as $rule) {
        $score = 0;
        foreach ($rule['keywords'] as $keyword) {
            if (str_contains($normalized, lowerUtf8((string) $keyword))) {
                $score++;
            }
        }

        if ($score > $bestScore) {
            $best = $rule;
            $bestScore = $score;
        }
    }

    if ($bestScore < 1 && !$emergencyDetected) {
        return null;
    }

    if ($emergencyDetected && $best === null) {
        $best = [
            'specialty' => 'Urgence / medecin generaliste',
            'response' => "Certains mots dans votre message peuvent correspondre a une situation potentiellement urgente. Je ne peux pas poser de diagnostic, mais il vaut mieux demander une evaluation medicale rapide ou contacter les urgences selon la gravite des symptomes.",
        ];
    }

    $content = "Orientation informative seulement, pas un diagnostic.\n\n";
    $content .= "Specialite suggeree: " . $best['specialty'] . ".\n";
    $content .= $best['response'];
    $content .= "\n\nSi vous voulez, decrivez vos symptomes avec: zone du corps, duree, intensite, age du patient et signes associes. Je pourrai vous orienter de facon plus precise.";

    if ($emergencyDetected) {
        $content .= "\n\nAttention: certains termes de votre message peuvent correspondre a une urgence. Si la personne va mal, appelez les secours ou consultez rapidement un professionnel de sante.";
    }

    return [
        'specialty' => $best['specialty'],
        'content' => $content,
        'emergency' => $emergencyDetected,
    ];
}
