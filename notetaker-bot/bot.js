// CRM Notetaker bot.
//
// Every 30 seconds it asks the HRM portal which Google Meet meetings are due (GET /api/crm-notes/bot/due),
// joins each one in Chrome as the company Google account (the meeting host, signed in once in ./chrome-profile),
// and stays until everyone else has left. While it is in the call:
//   - Google's Gemini takes notes (they start because the host is present) and the portal fetches them later;
//   - the bot records the call audio and uploads it every 5 minutes to the portal, which transcribes it with
//     OpenAI and writes the meeting notes when the bot leaves (POST .../finish). Notes appear in Meet Notes.
//
// Run: node bot.js   (settings in ./.env: PORTAL_URL, BOT_KEY, BOT_NAME; editable in the portal under
// Settings → Lead Settings → Notetaker Bot, and the bot restarts itself to load them)

const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const env = Object.fromEntries(
  fs.readFileSync(path.join(__dirname, '.env'), 'utf8').split(/\r?\n/)
    .filter((line) => /^\s*[A-Z_]+=/.test(line))
    .map((line) => [line.slice(0, line.indexOf('=')).trim(), line.slice(line.indexOf('=') + 1).trim()])
);

const PORTAL = (env.PORTAL_URL || '').replace(/\/+$/, '');
const KEY = env.BOT_KEY || '';
const BOT_NAME = env.BOT_NAME || 'CRM Notetaker';
const POLL_MS = 30 * 1000;
const SEGMENT_MS = 5 * 60 * 1000;          // upload the audio every 5 minutes
const ALONE_LEAVE_MS = 2 * 60 * 1000;      // leave after being the only one in the call for 2 minutes
const OVERTIME_MS = 30 * 60 * 1000;        // never stay longer than 30 minutes after the scheduled end

if (!PORTAL || !KEY) {
  console.error('Set PORTAL_URL and BOT_KEY in notetaker-bot/.env');
  process.exit(1);
}

const log = (...args) => console.log(new Date().toLocaleTimeString(), ...args);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const active = new Map();   // session -> meeting
const finished = new Set(); // sessions already handled in this run
const nextPart = new Map(); // session -> next audio part number (keeps counting across rejoins)

let contextPromise = null;

function browser() {
  if (!contextPromise) {
    contextPromise = chromium.launchPersistentContext(path.join(__dirname, 'chrome-profile'), {
      channel: 'chrome',
      headless: false,
      ignoreDefaultArgs: ['--enable-automation'],
      args: [
        '--disable-blink-features=AutomationControlled',
        '--autoplay-policy=no-user-gesture-required',
        '--window-position=-2400,0', // keep the window off-screen
        '--window-size=1280,800',
      ],
      viewport: { width: 1280, height: 800 },
    }).then((context) => {
      context.on('close', () => { contextPromise = null; });
      return context;
    }).catch((e) => { contextPromise = null; throw e; });
  }
  return contextPromise;
}

async function api(method, url, body) {
  const res = await fetch(PORTAL + url, {
    method,
    headers: { Authorization: `Bearer ${KEY}`, Accept: 'application/json' },
    body,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`${method} ${url} → ${res.status} ${data.message || ''}`);
  return data;
}

// Runs in the Meet page before Meet's own scripts: collect every incoming audio track into one stream.
function captureCallAudio() {
  const Original = window.RTCPeerConnection;
  const seen = new Set();
  const state = (window.__crmNotes = { ctx: null, dest: null, keep: [] });

  function mix() {
    if (!state.ctx) {
      state.ctx = new AudioContext();
      state.dest = state.ctx.createMediaStreamDestination();
    }
    return state;
  }

  function addTrack(track) {
    if (track.kind !== 'audio' || seen.has(track.id)) return;
    seen.add(track.id);
    const stream = new MediaStream([track]);
    // Chrome only delivers remote WebRTC audio to Web Audio if the stream is also attached to a media element
    const el = new Audio();
    el.srcObject = stream;
    el.muted = true;
    el.play().catch(() => {});
    state.keep.push(el);
    const m = mix();
    m.ctx.createMediaStreamSource(stream).connect(m.dest);
  }

  function Patched(...args) {
    const pc = new Original(...args);
    pc.addEventListener('track', (e) => addTrack(e.track));
    return pc;
  }
  Patched.prototype = Original.prototype;
  Object.setPrototypeOf(Patched, Original);
  window.RTCPeerConnection = Patched;
}

async function startRecorder(page) {
  // One MediaRecorder per segment so each upload is a complete audio file
  await page.evaluate((segmentMs) => {
    const state = window.__crmNotes;
    if (!state.ctx) {
      state.ctx = new AudioContext();
      state.dest = state.ctx.createMediaStreamDestination();
    }
    state.ctx.resume();
    state.index = 0;
    state.stopping = false;

    const run = () => {
      const chunks = [];
      const recorder = new MediaRecorder(state.dest.stream, { mimeType: 'audio/webm;codecs=opus', audioBitsPerSecond: 32000 });
      state.recorder = recorder;
      recorder.ondataavailable = (e) => { if (e.data && e.data.size) chunks.push(e.data); };
      recorder.onstop = async () => {
        const blob = new Blob(chunks, { type: 'audio/webm' });
        const index = state.index++;
        const buffer = await blob.arrayBuffer();
        let binary = '';
        const bytes = new Uint8Array(buffer);
        for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        await window.__crmSegment(index, btoa(binary), state.stopping);
        if (!state.stopping) run();
      };
      recorder.start(10000);
      state.timer = setTimeout(() => recorder.state === 'recording' && recorder.stop(), segmentMs);
    };
    run();
  }, SEGMENT_MS);
}

async function stopRecorder(page) {
  await page.evaluate(() => {
    const state = window.__crmNotes;
    state.stopping = true;
    clearTimeout(state.timer);
    if (state.recorder && state.recorder.state === 'recording') state.recorder.stop();
  }).catch(() => {});
}

async function othersInCall(page) {
  return page.evaluate(() => {
    const ids = new Set([...document.querySelectorAll('[data-participant-id]')].map((el) => el.getAttribute('data-participant-id')));
    return Math.max(0, ids.size - 1); // minus the bot itself
  }).catch(() => 0);
}

// Returns true when the meeting is over for the bot (everyone left / time up), false if it should rejoin
async function runMeeting(meeting) {
  let done = false;
  const tag = `#${meeting.id} ${meeting.title}`;
  const context = await browser();
  const page = await context.newPage();
  let uploads = Promise.resolve();
  let segments = 0;
  let lastSegmentSent = null;

  await page.addInitScript(captureCallAudio);
  await page.exposeFunction('__crmSegment', (pageIndex, base64, last) => {
    const audio = Buffer.from(base64, 'base64');
    if (audio.length < 2000) return; // nothing recorded
    const index = nextPart.get(meeting.session) || 0;
    nextPart.set(meeting.session, index + 1);
    segments++;
    uploads = uploads.then(async () => {
      for (let attempt = 1; attempt <= 3; attempt++) {
        try {
          const form = new FormData();
          form.append('index', String(index));
          form.append('audio', new Blob([audio], { type: 'audio/webm' }), `segment-${index}.webm`);
          await api('POST', `/api/crm-notes/meetings/${meeting.id}/segments`, form);
          log(tag, `audio part ${index + 1} uploaded (${Math.round(audio.length / 1024)} KB)`);
          return;
        } catch (e) {
          log(tag, `upload of part ${index + 1} failed (try ${attempt}):`, e.message);
          await sleep(5000 * attempt);
        }
      }
    });
    if (last) lastSegmentSent = uploads;
  });

  try {
    log(tag, 'joining', meeting.link);
    await page.goto(meeting.link, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(6000);

    for (const label of ['Continue without microphone and camera', 'Got it', 'Dismiss']) {
      const b = page.locator(`button:has-text("${label}")`);
      if (await b.count()) await b.first().click().catch(() => {});
    }
    const name = page.locator('input[type="text"][aria-label*="name" i]');
    if (await name.count()) await name.first().fill(BOT_NAME);

    const join = page.locator('button:has-text("Join now"), button:has-text("Switch here"), button:has-text("Ask to join")');
    await join.first().waitFor({ timeout: 30000 });
    await join.first().click();

    const leave = page.locator('[aria-label*="Leave call" i]');
    await leave.first().waitFor({ timeout: 120000 });
    log(tag, 'in the call');

    await startRecorder(page);

    const endsAt = new Date(meeting.ends_at).getTime();
    let aloneSince = null;

    while (true) {
      await page.waitForTimeout(15000);
      if (!(await leave.count())) { log(tag, 'removed from the call or call ended'); done = true; break; }

      const others = await othersInCall(page);
      aloneSince = others > 0 ? null : (aloneSince || Date.now());

      if (aloneSince && Date.now() - aloneSince > ALONE_LEAVE_MS) { log(tag, 'everyone has left'); done = true; break; }
      if (Date.now() > endsAt + OVERTIME_MS) { log(tag, '30 minutes past the end time'); done = true; break; }
    }
  } catch (e) {
    log(tag, 'error:', e.message);
  } finally {
    await stopRecorder(page);
    // Wait for the last audio part to reach the page callback, then for all uploads
    for (let i = 0; i < 20 && !lastSegmentSent; i++) await sleep(500);
    await uploads.catch(() => {});

    const leave = page.locator('[aria-label*="Leave call" i]');
    if (await leave.count().catch(() => 0)) await leave.first().click().catch(() => {});
    await page.close().catch(() => {});

    // Closed after the scheduled end: the portal stops listing the meeting, so write the notes now
    if (!done && Date.now() >= new Date(meeting.ends_at).getTime()) done = true;

    // Browser closed or an error mid-meeting: the next check rejoins; notes are written when the meeting is really over
    if (!done) {
      log(tag, 'will rejoin on the next check');
    } else if (nextPart.get(meeting.session)) {
      try {
        const r = await api('POST', `/api/crm-notes/meetings/${meeting.id}/finish`);
        log(tag, r.message || 'notes saved');
      } catch (e) {
        log(tag, 'notes failed:', e.message);
      }
    } else {
      log(tag, 'no audio was recorded');
    }
  }
  return done;
}

// Settings changed in the portal (Settings → Lead Settings → Notetaker Bot): exit once no meeting is in
// progress, so run-bot.cmd starts the bot again with the new .env
let restartPending = false;

function restartIfIdle() {
  if (restartPending && active.size === 0) {
    log('restarting with the new settings');
    process.exit(0);
  }
}

fs.watchFile(path.join(__dirname, '.env'), { interval: 5000 }, (curr, prev) => {
  if (curr.mtimeMs === prev.mtimeMs) return;
  restartPending = true;
  log(active.size ? 'settings changed: restarting after the meeting in progress' : 'settings changed');
  restartIfIdle();
});

async function poll() {
  restartIfIdle();
  try {
    const { meetings } = await api('GET', '/api/crm-notes/bot/due');
    for (const meeting of meetings) {
      if (active.has(meeting.session) || finished.has(meeting.session)) continue;
      active.set(meeting.session, meeting);
      runMeeting(meeting).then((done) => {
        if (done) finished.add(meeting.session);
      }).catch((e) => log('meeting', meeting.id, 'failed:', e.message)).finally(() => {
        active.delete(meeting.session);
        restartIfIdle();
      });
    }
  } catch (e) {
    log('portal check failed:', e.message);
  }
}

// Process id for start-bot.vbs (already running?) and stop-bot.cmd
fs.writeFileSync(path.join(__dirname, 'bot.pid'), String(process.pid));
process.on('exit', () => { try { fs.unlinkSync(path.join(__dirname, 'bot.pid')); } catch (e) {} });

log(`CRM Notetaker bot started. Portal: ${PORTAL}. Checking for meetings every ${POLL_MS / 1000}s.`);
poll();
setInterval(poll, POLL_MS);
