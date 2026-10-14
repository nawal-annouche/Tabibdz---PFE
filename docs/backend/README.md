# TabibDZ Backend Install Guide

## Prerequisites

- PHP 8.2+ with `pdo_mysql`
- MariaDB or MySQL
- A local env file at `api/.env.local`

## Environment setup

1. Copy `api/.env.example` to `api/.env.local`
2. Fill in your database credentials
3. Set a real `JWT_SECRET`
4. In production, set:
   - `APP_ENV=production`
   - `APP_DEBUG=0`
   - `AUTH_COOKIE_SECURE=1`
   - `APP_ALLOWED_ORIGINS=https://your-domain.com`

### Real email notifications via Gmail SMTP

Add these keys to `api/.env.local`:

- `MAIL_ENABLED=1`
- `MAIL_HOST=smtp.gmail.com`
- `MAIL_PORT=465`
- `MAIL_SECURITY=ssl`
- `MAIL_USERNAME=your-address@gmail.com`
- `MAIL_PASSWORD=your-google-app-password`
- `MAIL_FROM_EMAIL=your-address@gmail.com`
- `MAIL_FROM_NAME=TabibDZ`

Then reminder and confirmation emails will be sent when appointment notifications are created.

## Database bootstrap (local only)

These endpoints are intentionally restricted to local/CLI use.

- Run migrations: `/api/database/migrate.php`
- Reseed demo doctors: `/api/database/reseed-medecins.php`
- Set admin password: `/api/database/set-admin-password.php?email=admin@tabibdz.local&password=YourStrongPassword`

## Core endpoints

### Auth
- `POST /api/auth/register/`
- `POST /api/auth/login/`
- `POST /api/auth/logout/`
- `GET /api/auth/me/`
- `POST /api/auth/refresh/`
- `POST /api/auth/forgot-password/`
- `POST /api/auth/reset-password/`
- `POST /api/auth/send-verification/`
- `POST /api/auth/verify-email/`
- `POST /api/auth/change-password/`

### Public data
- `GET /api/wilayas/`
- `GET /api/communes/?wilaya_id=16`
- `GET /api/stats/`
- `GET /api/specialites/?featured=true&limit=8`
- `GET /api/medecins/`
- `GET /api/medecin/?id=...`
- `GET /api/medecin/creneaux/?medecin_id=...`
- `GET /api/medecin/reviews/?medecin_id=...`

### Patient
- `GET|PATCH /api/patient/me/`
- `GET|POST|DELETE /api/patient/favoris/`
- `GET|PATCH /api/patient/preferences-notifications/`
- `POST /api/patient/photo/`
- `GET|POST /api/appointments/`
- `PATCH|DELETE /api/appointments/item.php?id=...`
- `GET /api/appointments/ics.php?id=...`
- `GET /api/notifications/`
- `GET /api/notifications/process-appointments.php` (maintenance locale/cron pour rappels RDV)

### Doctor
- `GET|PATCH /api/medecin/me/`
- `GET /api/medecin/dashboard/`
- `GET|POST|DELETE /api/medecin/creneaux/manage/`

### Admin
- `GET|POST /api/admin/users/`
- `PATCH|DELETE /api/admin/users/item.php?id=...`
- `GET|POST /api/admin/medecins/`
- `PATCH|DELETE /api/admin/medecins/item.php?id=...`
- `POST|DELETE /api/admin/medecins/creneaux.php`
- `GET|POST /api/admin/specialites/`
- Admin UI: `/admin/`

## Pages currently wired

- `tabibdz-homepage.html`
- `tabibdz-recherche.html`
- `tabibdz-profil-medecin.html`
- `tabibdz-rdv-medecin.html`
- `tabibdz-prise-rdv.html`
- `tabibdz-dashboard.html`
- `tabibdz-espace-medecin.html`
- `tabibdz-contact.html`
- `tabibdz-comment-ca-marche.html`

## Production notes

- Do not deploy `api/.env.local`
- Do not expose local maintenance endpoints behind public access
- Clean `api/storage/logs/` before packaging
- Replace all demo content and local credentials before go-live
