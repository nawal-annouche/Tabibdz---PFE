UPDATE medecin_profiles mp
INNER JOIN users u ON u.id = mp.user_id
SET mp.langues = CONCAT(
  CASE
    WHEN mp.langues IS NULL OR mp.langues = '' THEN 'Arabe,Français'
    ELSE mp.langues
  END,
  ',Tamazight'
)
WHERE u.role = 'medecin'
  AND (
    u.wilaya_id IN (6, 15, 34) -- Bejaia, Tizi Ouzou, Bordj Bou Arreridj
    OR LOWER(COALESCE(u.commune, '')) LIKE '%bejaia%'
  )
  AND LOWER(COALESCE(mp.langues, '')) NOT LIKE '%tamazight%';
