// Feasibility test: can an automated Chrome join a Google Meet as a guest?
const { chromium } = require('playwright-core');
const fs = require('fs');
const os = require('os');
const path = require('path');

(async () => {
  const url = process.argv[2];
  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'crm-notetaker-'));
  const context = await chromium.launchPersistentContext(profile, {
    channel: 'chrome',
    headless: false,
    ignoreDefaultArgs: ['--enable-automation', '--mute-audio'],
    args: ['--disable-blink-features=AutomationControlled', '--autoplay-policy=no-user-gesture-required', '--window-position=-2400,0', '--window-size=1280,800'],
    viewport: { width: 1280, height: 800 },
  });
  const page = context.pages()[0] || await context.newPage();
  const shot = (n) => page.screenshot({ path: `join-test-${n}.png` });
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(8000);
    await shot(1);
    // Dismiss "use microphone/camera?" dialogs
    for (const label of [/continue without/i, /got it/i, /dismiss/i]) {
      const b = page.getByRole('button', { name: label });
      if (await b.count()) await b.first().click().catch(() => {});
    }
    const name = page.locator('input[type="text"]');
    if (await name.count()) await name.first().fill('CRM Notetaker');
    await page.waitForTimeout(1000);
    const join = page.getByRole('button', { name: /join now|ask to join|join/i });
    console.log('join buttons:', await join.count());
    if (await join.count()) await join.first().click();
    await page.waitForTimeout(15000);
    await shot(2);
    const leave = await page.locator('[aria-label*="Leave call" i]').count();
    console.log('IN CALL:', leave > 0);
    console.log('PAGE TEXT:', (await page.locator('body').innerText()).replace(/\s+/g, ' ').slice(0, 500));
  } catch (e) {
    console.log('ERROR:', e.message);
    await shot('err').catch(() => {});
  } finally {
    await context.close();
    fs.rmSync(profile, { recursive: true, force: true });
  }
})();
