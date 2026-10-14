# Smoke Tests

Tests Playwright légers pour vérifier rapidement les flux critiques du projet.

## Installation

```powershell
cd D:\DS\tools\smoke
npm install
```

## Lancer les suites

```powershell
npm run test:public
npm run test:auth-booking
npm run test:all
```

## Prérequis

- serveur local lancé sur `http://127.0.0.1:8000`
- pour certains tests publics authentifiés :
  - `SMOKE_EMAIL`
  - `SMOKE_PASSWORD`

## Fichiers

- `public-shell.smoke.spec.js` : shell public, nav, drawer, redirections
- `auth-booking.smoke.spec.js` : inscription, reset mot de passe, réservation, conflits API
- `playwright.smoke.config.cjs` : config Playwright locale
