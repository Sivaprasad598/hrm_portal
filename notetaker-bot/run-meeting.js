// CRM Notetaker: join one Google Meet as the signed-in company account (the meeting host) and stay
// until the given time, so Gemini takes the notes. Usage: node run-meeting.js <meet link> <join HH:MM> <leave HH:MM>
const { chromium } = require('playwright-core');
const path = require('path');

const [link, joinAt, leaveAt] = process.argv.slice(2);
const at = (hhmm) => { const [h, m] = hhmm.split(':').map(Number); const d = new Date(); d.setHours(h, m, 0, 0); return d; };
const log = (...a) => console.log(new Date().toLocaleTimeString(), ...a);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const wait = at(joinAt) - Date.now();
  if (wait > 0) { log(`waiting until ${joinAt} to join`); await sleep(wait); }

  const context = await chromium.launchPersistentContext(path.join(__dirname, process.env.BOT_PROFILE || 'chrome-profile'), {
    channel: 'chrome',
    headless: false,
    ignoreDefaultArgs: ['--enable-automation', '--mute-audio'],
    args: ['--disable-blink-features=AutomationControlled', '--autoplay-policy=no-user-gesture-required', '--window-position=-2400,0', '--window-size=1280,800'],
    viewport: { width: 1280, height: 800 },
  });
  const page = context.pages()[0] || await context.newPage();

  try {
    await page.goto(link, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(8000);
    for (const label of [/continue without/i, /got it/i, /dismiss/i]) {
      const b = page.getByRole('button', { name: label });
      if (await b.count()) await b.first().click().catch(() => {});
    }
    const join = page.locator('button:has-text("Join now"), button:has-text("Ask to join"), button:has-text("Switch here")');
    await join.first().click({ timeout: 30000 });
    await page.waitForTimeout(15000);

    const inCall = (await page.locator('[aria-label*="Leave call" i]').count()) > 0;
    const text = await page.locator('body').innerText();
    log('in call:', inCall, '| Gemini taking notes:', /taking notes/i.test(text));

    while (Date.now() < at(leaveAt)) {
      await sleep(30000);
      if (!(await page.locator('[aria-label*="Leave call" i]').count())) { log('call ended or bot was removed'); break; }
    }

    const leave = page.locator('[aria-label*="Leave call" i]');
    if (await leave.count()) { await leave.first().click(); log('left the call'); }
    await page.waitForTimeout(3000);
  } catch (e) {
    log('ERROR:', e.message);
  } finally {
    await context.close();
    log('done');
  }
})();
