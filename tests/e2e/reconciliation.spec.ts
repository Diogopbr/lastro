import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';

async function startDemo(page: Page) {
  await page.goto('/entrar');
  await page.getByRole('button', { name: 'Iniciar demonstração privada' }).click();
  await page.waitForURL('**/conferencias');
}

async function createConference(page: Page) {
  await page.getByRole('button', { name: 'Nova conferência' }).click();
  await page.getByLabel('Nome da conferência').fill('Conferência de maio — exemplo');
  await page.getByLabel('Data inicial (inclusive)').fill('2026-05-01');
  await page.getByLabel('Data final (inclusive)').fill('2026-05-31');
  await page.getByRole('button', { name: 'Criar conferência', exact: true }).click();
  await page.waitForURL(/\/conferencias\/[a-f0-9-]+$/);
  return new URL(page.url()).pathname;
}

async function upload(page: Page, kind: 'orders' | 'payouts', bytes: Buffer) {
  await page.locator(`a[href$="/fontes/${kind}/preview"]`).first().click();
  await page.waitForURL(`**/fontes/${kind}/preview`);
  await page.locator('input[type=file]').setInputFiles({ name: `${kind}-exemplo.csv`, mimeType: 'text/csv', buffer: bytes });
  await page.getByRole('button', { name: /Gerar prévia/i }).click();
  await page.getByRole('button', { name: /Confirmar importação/i }).click();
  await page.waitForURL(/\/conferencias\/[a-f0-9-]+$/);
}

async function accessible(page: Page) {
  expect((await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => innerWidth));
}

test('keyboard entry and reset dialog keep focus and Escape returns to the trigger', async ({ page }) => {
  await page.goto('/entrar');
  const start = page.getByRole('button', { name: 'Iniciar demonstração privada' });
  for (let step = 0; step < 12 && !(await start.evaluate(el => el === document.activeElement)); step++) {
    await page.keyboard.press('Tab');
  }
  await expect(start).toBeFocused();
  await page.keyboard.press('Enter');
  await page.waitForURL('**/conferencias');
  const reset = page.getByRole('button', { name: 'Reiniciar dados', exact: true });
  for (let step = 0; step < 16 && !(await reset.evaluate(el => el === document.activeElement)); step++) {
    await page.keyboard.press('Tab');
  }
  await expect(reset).toBeFocused();
  await page.keyboard.press('Enter');
  const cancel = page.getByRole('button', { name: 'Cancelar', exact: true });
  const confirm = page.getByRole('button', { name: 'Sim, reiniciar dados', exact: true });
  await expect(cancel).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(confirm).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(cancel).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).toHaveCount(0);
  await expect(reset).toBeFocused();
});

test('imports real CSVs, reviews and reopens a divergence, exports every result and reloads', async ({ page }) => {
  await startDemo(page);
  await createConference(page);
  for (const kind of ['orders', 'payouts'] as const) {
    const response = await page.request.get(`/exemplos/${kind}.csv`);
    expect(response.ok()).toBeTruthy();
    await upload(page, kind, await response.body());
  }
  await page.getByRole('button', { name: 'Executar conciliação', exact: true }).click();
  await page.waitForURL(/\/execucoes\/[a-f0-9-]+$/);
  const runPath = new URL(page.url()).pathname;
  const row = page.getByRole('row').filter({ hasText: '0002' });
  await row.getByRole('button', { name: 'Examinar', exact: true }).click();
  await page.getByLabel('Nota da análise').fill('Diferença conferida com a origem.');
  await page.getByRole('button', { name: 'Salvar revisão', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Reabrir apontamento', exact: true })).toBeVisible();
  await expect(page.locator('.divergence-strip')).toContainText('1 de 3 divergências revisadas');
  await page.reload();
  await page.getByRole('row').filter({ hasText: '0002' }).getByRole('button', { name: 'Examinar', exact: true }).click();
  await expect(page.getByText('Diferença conferida com a origem.', { exact: true })).toBeVisible();
  const downloadPromise = page.waitForEvent('download');
  await page.locator(`a[href="${runPath}/exportar"]`).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toMatch(/^lastro-LT-\d+-[a-f0-9-]+\.csv$/);
  const csv = await fs.readFile((await download.path())!, 'utf8');
  expect(csv.trim().split(/\r?\n/)).toHaveLength(5);
  expect(csv).toContain('0002,amountMismatch,200.00,190.00,5.00,185.00,-10.00,reviewed');
  await accessible(page);
  await page.setViewportSize({ width: 390, height: 844 });
  await accessible(page);
  await page.getByLabel('Nota da análise').fill('Reaberto para nova conferência.');
  await page.getByRole('button', { name: 'Reabrir apontamento', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Salvar revisão', exact: true })).toBeVisible();
  await expect(page.locator('.divergence-strip')).toContainText('0 de 3 divergências revisadas');
});

test('invalid preview keeps the source absent and allows a corrected file on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await startDemo(page);
  const conferencePath = await createConference(page);
  await page.locator('a[href$="/fontes/orders/preview"]').first().click();
  await page.locator('input[type=file]').setInputFiles({ name: 'invalido.csv', mimeType: 'text/csv', buffer: Buffer.from('order_id,ordered_at,gross_amount,currency\n0001,2026-05-01,1e2,BRL\n') });
  await page.getByRole('button', { name: /Gerar prévia/i }).click();
  await expect(page.getByText(/Informe dinheiro com ponto/)).toBeVisible();
  const absent = await page.request.get(conferencePath, { headers: { Accept: 'application/json' } });
  expect((await absent.json()).sources.orders).toBeNull();
  const confirm = page.getByRole('button', { name: /Confirmar importação/i });
  if (await confirm.count()) await expect(confirm).toBeDisabled();
  await accessible(page);
  const valid = await page.request.get('/exemplos/orders.csv');
  await page.locator('input[type=file]').setInputFiles({ name: 'corrigido.csv', mimeType: 'text/csv', buffer: await valid.body() });
  await page.getByRole('button', { name: /Gerar prévia/i }).click();
  await page.getByRole('button', { name: /Confirmar importação/i }).click();
  await page.waitForURL(`**${conferencePath}`);
  const persisted = await page.request.get(conferencePath, { headers: { Accept: 'application/json' } });
  expect((await persisted.json()).sources.orders.rowCount).toBe(3);
});

test('two visitors are isolated and reset invalidates a page in another tab', async ({ page, browser }) => {
  await startDemo(page);
  const listing = await page.request.get('/conferencias', { headers: { Accept: 'application/json' } });
  const first = (await listing.json()).conferences.find((conference: { run: unknown }) => conference.run);
  const other = await browser.newContext({ baseURL: new URL(page.url()).origin });
  try {
    const otherPage = await other.newPage();
    await startDemo(otherPage);
    const foreign = await otherPage.request.get(`/execucoes/${first.run.id}`, { headers: { Accept: 'application/json' } });
    expect(foreign.status()).toBe(404);
    const stale = await page.context().newPage();
    await stale.goto('/conferencias');
    await stale.getByRole('button', { name: 'Nova conferência' }).click();
    await stale.getByLabel('Nome da conferência').fill('Rascunho da aba anterior');
    await stale.getByLabel('Data inicial (inclusive)').fill('2026-05-01');
    await stale.getByLabel('Data final (inclusive)').fill('2026-05-31');
    const token = await page.request.get('/csrf');
    const reset = await page.request.post('/demo/reset', { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': (await token.json()).token } });
    expect(reset.ok()).toBeTruthy();
    await stale.getByRole('button', { name: 'Criar conferência', exact: true }).click();
    await expect(stale.getByText('Esta página não corresponde ao ambiente atual. Atualize antes de salvar.')).toBeVisible();
    await expect(stale.getByLabel('Nome da conferência')).toHaveValue('Rascunho da aba anterior');
    const replacementListing = await page.request.get('/conferencias', { headers: { Accept: 'application/json' } });
    expect((await replacementListing.json()).conferences).toHaveLength(3);
    const revoked = await stale.request.get(`/execucoes/${first.run.id}/exportar`, { headers: { Accept: 'application/json' } });
    expect(revoked.status()).toBe(404);
    const otherListing = await otherPage.request.get('/conferencias', { headers: { Accept: 'application/json' } });
    expect((await otherListing.json()).conferences).toHaveLength(3);
  } finally {
    await other.close();
  }
});
