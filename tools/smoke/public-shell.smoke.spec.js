const { test, expect } = require('@playwright/test');

const smokeEmail = process.env.SMOKE_EMAIL;
const smokePassword = process.env.SMOKE_PASSWORD;

test('homepage guest shell and CTA redirect work', async ({ page }) => {
  await page.goto('/tabibdz-homepage.html', { waitUntil: 'networkidle' });

  await expect(page.locator('.nav-actions a').filter({ hasText: 'Connexion' })).toBeVisible();
  await expect(page.locator('.nav-actions a').filter({ hasText: "S'inscrire" })).toBeVisible();
  await expect(page.locator('.hero-section')).toBeVisible();

  const rdvButtons = page.locator('button.btn-rdv');
  await expect(rdvButtons.first()).toBeVisible();
  await rdvButtons.first().click();
  await expect(page).toHaveURL(/\/connexion\/\?next=%2Ftabibdz-rdv-medecin\.html%3Fmedecin_id%3D/);
});

test('homepage mobile drawer opens with shared actions', async ({ browser }) => {
  const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
  await page.goto('http://127.0.0.1:8000/tabibdz-homepage.html', { waitUntil: 'networkidle' });

  await page.locator('#burger-btn').click();
  await expect(page.locator('#nav-drawer')).toHaveClass(/open/);
  await expect(page.locator('#drawer-nav-actions a').filter({ hasText: 'Connexion' })).toBeVisible();
  await expect(page.locator('#drawer-nav-actions a').filter({ hasText: "S'inscrire" })).toBeVisible();
  await page.close();
});

test('recherche guest shell loads and CTA redirect works', async ({ page }) => {
  await page.goto('/tabibdz-recherche.html', { waitUntil: 'networkidle' });

  await expect(page.locator('.nav-actions a').filter({ hasText: 'Connexion' })).toBeVisible();
  await expect(page.locator('.nav-actions a').filter({ hasText: "S'inscrire" })).toBeVisible();
  await expect(page.locator('#search-query')).toBeVisible();

  const rdvButtons = page.locator('.btn-rdv');
  await expect(rdvButtons.first()).toBeVisible();
  await rdvButtons.first().click();
  await expect(page).toHaveURL(/\/connexion\/\?next=%2Ftabibdz-rdv-medecin\.html%3Fmedecin_id%3D/);
});

test('authenticated public shell hydrates nav and prefills contact form', async ({ page }) => {
  test.skip(!smokeEmail || !smokePassword, 'Smoke credentials are required');

  await page.goto('/connexion/?next=%2Ftabibdz-contact.html', { waitUntil: 'networkidle' });
  await page.locator('#l-email').fill(smokeEmail);
  await page.locator('#l-password').fill(smokePassword);
  await page.locator('#btn-login').click();

  await page.waitForURL(/\/tabibdz-contact\.html/, { timeout: 20000 });
  await page.waitForLoadState('networkidle');

  await expect(page.locator('.nav-actions .nav-user-chip')).toBeVisible();
  await expect(page.locator('.nav-actions .nav-notif-btn')).toBeVisible();
  await expect(page.locator('#f-prenom')).toHaveValue('Smoke');
  await expect(page.locator('#f-nom')).toHaveValue('Tester');
  await expect(page.locator('#f-email')).toHaveValue(smokeEmail);
});
