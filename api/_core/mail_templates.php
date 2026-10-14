<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function emailBrandName(): string
{
    return trim((string) (mailerConfig()['from_name'] ?? 'TabibDZ')) ?: 'TabibDZ';
}

function emailBaseLayout(string $preheader, string $title, string $introHtml, string $contentHtml, string $footerNote = ''): string
{
    $brand = htmlspecialchars(emailBrandName(), ENT_QUOTES, 'UTF-8');
    $safePreheader = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $footer = $footerNote !== '' ? '<p style="margin:0;color:#6b7c72;font-size:13px;line-height:1.6">' . $footerNote . '</p>' : '';

    return '<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . $safeTitle . '</title>
</head>
<body style="margin:0;padding:0;background:#f4faf7;font-family:Arial,Helvetica,sans-serif;color:#152319">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">' . $safePreheader . '</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4faf7">
    <tr>
      <td align="center" style="padding:28px 16px">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border:1px solid #d9ece1;border-radius:18px;overflow:hidden">
          <tr>
            <td style="padding:24px 28px;background:#ffffff;border-bottom:1px solid #e6f2eb">
              <div style="font-size:28px;font-weight:800;letter-spacing:-0.5px;color:#0f2016">' . $brand . '</div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px">
              <h1 style="margin:0 0 14px;font-size:30px;line-height:1.1;color:#102116">' . $safeTitle . '</h1>
              <div style="margin:0 0 22px;font-size:16px;line-height:1.7;color:#4f6257">' . $introHtml . '</div>
              <div style="font-size:15px;line-height:1.7;color:#1f2f25">' . $contentHtml . '</div>
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px 28px">
              <div style="border-top:1px solid #e6f2eb;padding-top:20px">
                ' . $footer . '
                <p style="margin:12px 0 0;color:#6b7c72;font-size:13px;line-height:1.6">Email transactionnel envoye automatiquement par ' . $brand . '.</p>
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

function verificationEmailBundle(string $prenom, string $email, string $token, string $pin): array
{
    $firstName = trim($prenom) !== '' ? trim($prenom) : 'cher patient';
    $verifyUrl = appBaseUrl() . '/verification-email/?email=' . urlencode($email);
    $subject = 'Verification de votre adresse email';
    $text = "Bonjour {$firstName},\n\nBienvenue sur TabibDZ.\n\nPour activer votre compte, ouvrez la page suivante puis saisissez votre code PIN :\n{$verifyUrl}\n\nCode de verification : {$pin}\n\nCe code expire dans 24 heures.\n\nTabibDZ";
    $html = emailBaseLayout(
        'Verification de votre adresse email TabibDZ',
        'Verification de votre email',
        '<p style="margin:0">Bonjour <strong>' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . '</strong>, bienvenue sur TabibDZ.</p>',
        '<p style="margin:0 0 18px">Confirmez votre adresse email pour activer votre compte patient.</p>
         <p style="margin:0 0 24px"><a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:14px 24px;background:#18a465;color:#ffffff;text-decoration:none;border-radius:14px;font-weight:700">Ouvrir la page de verification</a></p>
         <div style="padding:18px;border:1px solid #d9ece1;border-radius:16px;background:#f8fcfa;margin:0 0 20px">
           <div style="font-size:13px;color:#6b7c72;margin-bottom:8px">Code de verification</div>
           <div style="font-size:30px;letter-spacing:6px;font-weight:800;color:#102116">' . htmlspecialchars($pin, ENT_QUOTES, 'UTF-8') . '</div>
         </div>
         <p style="margin:0;color:#4f6257">Le code PIN est obligatoire et expire dans 24 heures.</p>',
        'Si vous n\'etes pas a l\'origine de cette inscription, ignorez simplement cet email.'
    );

    return ['subject' => $subject, 'text' => $text, 'html' => $html];
}

function passwordResetEmailBundle(string $prenom, string $email, string $token, string $pin): array
{
    $firstName = trim($prenom) !== '' ? trim($prenom) : 'cher patient';
    $resetUrl = appBaseUrl() . '/mot-de-passe-oublie/?email=' . urlencode($email);
    $subject = 'Reinitialisation de votre mot de passe';
    $text = "Bonjour {$firstName},\n\nUne demande de reinitialisation de mot de passe a ete recue pour votre compte TabibDZ.\n\nOuvrez la page suivante puis saisissez votre code PIN :\n{$resetUrl}\n\nCode de verification : {$pin}\n\nCe code expire dans 1 heure.\n\nTabibDZ";
    $html = emailBaseLayout(
        'Reinitialisation de mot de passe TabibDZ',
        'Reinitialisez votre mot de passe',
        '<p style="margin:0">Bonjour <strong>' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>',
        '<p style="margin:0 0 18px">Nous avons recu une demande de reinitialisation pour votre compte TabibDZ.</p>
         <p style="margin:0 0 24px"><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:14px 24px;background:#18a465;color:#ffffff;text-decoration:none;border-radius:14px;font-weight:700">Ouvrir la page de reinitialisation</a></p>
         <div style="padding:18px;border:1px solid #d9ece1;border-radius:16px;background:#f8fcfa;margin:0 0 20px">
           <div style="font-size:13px;color:#6b7c72;margin-bottom:8px">Code de verification</div>
           <div style="font-size:30px;letter-spacing:6px;font-weight:800;color:#102116">' . htmlspecialchars($pin, ENT_QUOTES, 'UTF-8') . '</div>
         </div>
         <p style="margin:0;color:#4f6257">Le code PIN est obligatoire et expire dans 1 heure.</p>',
        'Si vous n\'avez pas demande ce changement, ignorez cet email et conservez votre mot de passe actuel.'
    );

    return ['subject' => $subject, 'text' => $text, 'html' => $html];
}

function appointmentEmailBundle(string $recipientName, string $title, string $body): array
{
    $hello = trim($recipientName) !== '' ? trim($recipientName) : 'cher patient';
    $subject = $title;
    $text = "Bonjour {$hello},\n\n{$body}\n\nVous pouvez retrouver les details dans votre espace patient TabibDZ.\n\nTabibDZ";
    $html = emailBaseLayout(
        $title . ' - TabibDZ',
        $title,
        '<p style="margin:0">Bonjour <strong>' . htmlspecialchars($hello, ENT_QUOTES, 'UTF-8') . '</strong>,</p>',
        '<p style="margin:0 0 18px;color:#1f2f25">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</p>
         <p style="margin:0"><a href="' . htmlspecialchars(appBaseUrl() . '/tabibdz-dashboard.html#rdv', ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:14px 24px;background:#18a465;color:#ffffff;text-decoration:none;border-radius:14px;font-weight:700">Ouvrir mon espace patient</a></p>',
        'Conservez cet email pour reference si besoin.'
    );

    return ['subject' => $subject, 'text' => $text, 'html' => $html];
}
