const { test, expect, request: playwrightRequest } = require('@playwright/test');

const SMALL_PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aYuoAAAAASUVORK5CYII=',
  'base64'
);

function uniqueEmail(prefix) {
  return `${prefix}.${Date.now()}.${Math.floor(Math.random() * 10000)}@example.com`;
}

async function registerPatient(request, { email, password, prenom = 'Smoke', nom = 'Tester', telephone = '+213550000000' }) {
  const response = await request.post('/api/auth/register/', {
    data: {
      role: 'patient',
      prenom,
      nom,
      telephone,
      email,
      password,
    },
  });
  expect(response.ok()).toBeTruthy();
  const data = await response.json();
  expect(data.debug_verification_pin).toBeTruthy();
  return { data, email, password, prenom, nom, telephone };
}

async function verifyPatient(request, email, pin) {
  const response = await request.post('/api/auth/verify-email/', {
    data: { email, pin },
  });
  expect(response.ok()).toBeTruthy();
  return response.json();
}

async function createVerifiedPatient(request, options) {
  const registration = await registerPatient(request, options);
  await verifyPatient(request, registration.email, registration.data.debug_verification_pin);
  return registration;
}

async function loginViaApi(request, email, password) {
  const response = await request.post('/api/auth/login/', {
    data: { email, password },
  });
  expect(response.ok()).toBeTruthy();
  return response.json();
}

async function loginViaUi(page, email, password) {
  await page.locator('#l-email').fill(email);
  await page.locator('#l-password').fill(password);
  await page.locator('#btn-login').click();
}

async function getAvailableSlots(request, doctorId, minimumCount = 1) {
  const response = await request.get(`/api/medecin/creneaux/?medecin_id=${doctorId}`);
  expect(response.ok()).toBeTruthy();
  const data = await response.json();
  expect(Array.isArray(data)).toBeTruthy();
  expect(data.length).toBeGreaterThanOrEqual(minimumCount);
  return data;
}

async function createAppointment(request, payload) {
  const response = await request.post('/api/appointments/', { data: payload });
  return {
    response,
    data: await response.json(),
  };
}

test('inscription redirects to verification page, verifies PIN, then allows login', async ({ page }) => {
  const email = uniqueEmail('register');
  const password = 'SmokeTest123!';

  await page.goto('/inscription/', { waitUntil: 'networkidle' });

  await page.locator('#r-prenom').fill('Smoke');
  await page.locator('#r-nom').fill('Tester');
  await page.locator('#r-telephone').fill('+213550000000');
  await page.locator('#r-email').fill(email);
  await page.locator('#r-password').fill(password);

  const registerResponsePromise = page.waitForResponse((response) =>
    response.url().includes('/api/auth/register/') && response.request().method() === 'POST'
  );

  await page.locator('#btn-reg').click();
  const registerResponse = await registerResponsePromise;
  const registerData = await registerResponse.json();

  expect(registerResponse.ok()).toBeTruthy();
  expect(registerData.debug_verification_pin).toBeTruthy();

  await page.waitForURL(/\/verification-email\/\?email=/, { timeout: 10000 });
  await expect(page.locator('#email')).toHaveValue(email);

  await page.locator('#pin').fill(registerData.debug_verification_pin);
  await page.locator('#verify-btn').click();

  await page.waitForURL(/\/connexion\//, { timeout: 10000 });
  await loginViaUi(page, email, password);
  await page.waitForURL(/\/tabibdz-dashboard\.html/, { timeout: 15000 });
});

test('mot de passe oublie resets password and allows a fresh login', async ({ page, request }) => {
  const email = uniqueEmail('reset');
  const initialPassword = 'SmokeTest123!';
  const nextPassword = 'SmokeReset456!';

  await createVerifiedPatient(request, { email, password: initialPassword, prenom: 'Reset', nom: 'Flow' });

  await page.goto('/mot-de-passe-oublie/', { waitUntil: 'networkidle' });
  await page.locator('#request-email').fill(email);

  const requestResponsePromise = page.waitForResponse((response) =>
    response.url().includes('/api/auth/forgot-password/') && response.request().method() === 'POST'
  );

  await page.locator('#request-btn').click();
  const requestResponse = await requestResponsePromise;
  const requestData = await requestResponse.json();

  expect(requestResponse.ok()).toBeTruthy();
  expect(requestData.debug_reset_pin).toBeTruthy();

  await expect(page.locator('#reset-step')).toHaveClass(/active/);
  await page.locator('#reset-pin').fill(requestData.debug_reset_pin);
  await page.locator('#new-password').fill(nextPassword);
  await page.locator('#confirm-password').fill(nextPassword);
  await page.locator('#reset-btn').click();

  await page.waitForURL(/\/connexion\//, { timeout: 10000 });
  await loginViaUi(page, email, nextPassword);
  await page.waitForURL(/\/tabibdz-dashboard\.html/, { timeout: 15000 });
});

test('prise-rdv teleconsultation preloads patient info, validates documents, and confirms booking', async ({ page, request }) => {
  const email = uniqueEmail('booking');
  const password = 'SmokeBook123!';

  await createVerifiedPatient(request, {
    email,
    password,
    prenom: 'Booking',
    nom: 'Flow',
    telephone: '+213550111222',
  });

  await page.goto('/connexion/?next=%2Ftabibdz-prise-rdv.html%3Fmedecin_id%3D2%26consultation_mode%3Dteleconsultation', {
    waitUntil: 'networkidle',
  });
  await loginViaUi(page, email, password);

  await page.waitForURL(/\/tabibdz-prise-rdv\.html\?medecin_id=2&consultation_mode=teleconsultation/, { timeout: 20000 });
  await expect(page.locator('#step2.active')).toBeVisible();

  await expect(page.locator('#fPrenom')).toHaveValue('Booking');
  await expect(page.locator('#fNom')).toHaveValue('Flow');
  await expect(page.locator('#fTel')).toHaveValue('+213550111222');
  await expect(page.locator('#fEmail')).toHaveValue(email);

  await page.locator('#fDob').fill('1990-01-01');

  await page.locator('#fDocuments').setInputFiles({
    name: 'too-large.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.alloc(5 * 1024 * 1024 + 8, 1),
  });
  await expect(page.locator('#errDocumentsLimit')).toHaveClass(/show/);
  await expect(page.locator('#errDocumentsLimit')).toContainText('5 Mo');

  await page.locator('#fDocuments').setInputFiles({
    name: 'bilan.png',
    mimeType: 'image/png',
    buffer: SMALL_PNG,
  });
  await expect(page.locator('#errDocumentsLimit')).not.toHaveClass(/show/);

  await page.locator('#step2 .btn-next').click();
  await expect(page.locator('#step3.active')).toBeVisible();

  await page.locator('#payHolder').fill('Booking Flow');
  await page.locator('#payCardNumber').fill('4242424242424242');
  await page.locator('#payExpiry').fill('12/30');
  await page.locator('#payCvv').fill('123');
  await page.locator('#payPassword').fill('4321');
  await page.locator('#payTerms').check();
  await page.locator('#step3 .btn-next').click();

  await expect(page.locator('#step4.active')).toBeVisible();

  const confirmResponsePromise = page.waitForResponse((response) =>
    response.url().includes('/api/appointments/') && response.request().method() === 'POST'
  );

  await page.locator('.btn-confirm').click();
  const confirmResponse = await confirmResponsePromise;
  expect(confirmResponse.ok()).toBeTruthy();

  await expect(page.locator('#successPage')).toHaveClass(/show/);
  await expect(page.locator('#confNum')).not.toHaveText('#TBZ-2026-0000');
});

test('api blocks double booking on the same slot for another patient', async ({ request }) => {
  const firstPatient = await createVerifiedPatient(request, {
    email: uniqueEmail('double-a'),
    password: 'DoubleBook123!',
    prenom: 'Double',
    nom: 'First',
  });

  await loginViaApi(request, firstPatient.email, firstPatient.password);

  const [slot] = await getAvailableSlots(request, 2, 1);
  const firstBooking = await createAppointment(request, {
    medecin_id: 2,
    creneau_id: slot.id,
    consultation_mode: 'cabinet',
    type_visite: 'new',
    motif: 'Smoke double booking first pass',
  });

  expect(firstBooking.response.status()).toBe(201);

  const secondContext = await playwrightRequest.newContext({ baseURL: 'http://127.0.0.1:8000' });
  try {
    const secondPatient = await createVerifiedPatient(secondContext, {
      email: uniqueEmail('double-b'),
      password: 'DoubleBook456!',
      prenom: 'Double',
      nom: 'Second',
    });

    await loginViaApi(secondContext, secondPatient.email, secondPatient.password);

    const secondBooking = await createAppointment(secondContext, {
      medecin_id: 2,
      creneau_id: slot.id,
      consultation_mode: 'cabinet',
      type_visite: 'new',
      motif: 'Smoke double booking second pass',
    });

    expect(secondBooking.response.status()).toBe(409);
    expect(secondBooking.data.error).toMatch(/creneau/i);
  } finally {
    await secondContext.dispose();
  }
});

test('api blocks rescheduling to an already reserved slot', async ({ request }) => {
  const patient = await createVerifiedPatient(request, {
    email: uniqueEmail('conflict'),
    password: 'ConflictBook123!',
    prenom: 'Conflict',
    nom: 'Patient',
  });

  await loginViaApi(request, patient.email, patient.password);

  const slots = await getAvailableSlots(request, 3, 2);
  const firstSlot = slots[0];
  const secondSlot = slots[1];

  const firstBooking = await createAppointment(request, {
    medecin_id: 3,
    creneau_id: firstSlot.id,
    consultation_mode: 'cabinet',
    type_visite: 'new',
    motif: 'Smoke reschedule source',
  });
  expect(firstBooking.response.status()).toBe(201);

  const secondBooking = await createAppointment(request, {
    medecin_id: 3,
    creneau_id: secondSlot.id,
    consultation_mode: 'cabinet',
    type_visite: 'suivi',
    motif: 'Smoke reschedule target conflict',
  });
  expect(secondBooking.response.status()).toBe(201);

  const patchResponse = await request.patch(`/api/appointments/item.php?id=${firstBooking.data.appointment_id}`, {
    data: {
      creneau_id: secondSlot.id,
    },
  });
  expect(patchResponse.status()).toBe(409);

  const patchData = await patchResponse.json();
  expect(patchData.error).toMatch(/creneau|reserve|disponible/i);
});
