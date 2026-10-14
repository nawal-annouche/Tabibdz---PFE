## Tools

Ce dossier regroupe les fichiers de support qui ne font pas partie du runtime du site.

- `smoke/` : tests Playwright de non-régression pour les flux critiques.
- `checks/` : scripts ponctuels de vérification ou d'inspection manuelle.

Règle pratique :
- garder ici les scripts de QA, de debug et d'audit
- ne pas laisser de fichiers temporaires ou de scripts jetables à la racine du projet
