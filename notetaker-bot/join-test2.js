// Test: bot joins as the signed-in host (persistent profile) and checks it is in the call.
const { chromium } = require('playwright-core');
const path = require('path');

(async () => {
  const context = await chromium.launchPersistentContext(path.join(__dirname, 'chrome-profile'), {
    channel: 'chrome',
    headless: false,
    ignoreDefaultArgs: ['--enable-automation', '--mute-audio'],
    args: ['--disable-blink-features=AutomationControlled', '--autoplay-policy=no-user-gesture-required', '--window-position=-2400,0', '--window-size=1280,800'],
    viewport: { width: 1280, height: 800 },
  });
  const page = context.pages()[0] || await context.newPage();
  try {
    await page.goto(process.argv[2], { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(8000);
    for (const label of [/continue without/i, /got it/i, /dismiss/i]) {
      const b = page.getByRole('button', { name: label });
      if (await b.count()) await b.first().click().catch(() => {});
    }
    await page.screenshot({ path: 'join2-before.png' });
    const join = page.locator('button:has-text("Join now"), button:has-text("Ask to join"), button:has-text("Switch here")');
    console.log('join buttons:', await join.count(), await join.allInnerTexts());
    if (await join.count()) await join.first().click();
    await page.waitForTimeout(15000);
    await page.screenshot({ path: 'join2-after.png' });
    console.log('IN CALL:', (await page.locator('[aria-label*="Leave call" i]').count()) > 0);
    console.log('PAGE TEXT:', (await page.locator('body').innerText()).replace(/\s+/g, ' ').slice(0, 400));
    const leave = page.locator('[aria-label*="Leave call" i]');
    if (await leave.count()) { await page.waitForTimeout(5000); await leave.first().click(); }
  } catch (e) {
    console.log('ERROR:', e.message);
  } finally {
    await context.close();
  }
})();
