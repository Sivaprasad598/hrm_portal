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
const SILENCE_PEAK = 0.01;                 // a segment whose loudest sample stays under 1% has no speech in it

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
        // The window is off-screen: stop Chrome treating it as hidden and throttling its page and audio
        '--disable-backgrounding-occluded-windows',
        '--disable-renderer-backgrounding',
        '--disable-background-timer-throttling',
        // Lets the Meet page record its own tab audio without the "share this tab" prompt
        '--auto-accept-this-tab-capture',
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
    (state.pcs = state.pcs || []).push(pc);
    pc.addEventListener('track', (e) => addTrack(e.track));
    return pc;
  }
  Patched.prototype = Original.prototype;
  Object.setPrototypeOf(Patched, Original);
  window.RTCPeerConnection = Patched;
}

async function startRecorder(page) {
  // One MediaRecorder per segment so each upload is a complete audio file
  return page.evaluate(async (segmentMs) => {
    const state = window.__crmNotes;
    if (!state.ctx) {
      state.ctx = new AudioContext();
      state.dest = state.ctx.createMediaStreamDestination();
    }
    state.ctx.resume();
    state.index = 0;
    state.stopping = false;

    // Record what the Meet tab plays. Meet's incoming WebRTC tracks can arrive as silence while Meet still plays
    // the call, so the tracks collected by captureCallAudio are only the fallback.
    let input = state.dest.stream;
    let source = 'call audio tracks';
    try {
      const tab = await navigator.mediaDevices.getDisplayMedia({
        video: { width: 320, height: 180, frameRate: 1 },
        audio: { suppressLocalAudioPlayback: false, echoCancellation: false, noiseSuppression: false, autoGainControl: false },
        preferCurrentTab: true,
        selfBrowserSurface: 'include',
      });
      if (tab.getAudioTracks().length) {
        state.tab = tab;
        const tabDest = state.ctx.createMediaStreamDestination();
        state.ctx.createMediaStreamSource(new MediaStream(tab.getAudioTracks())).connect(tabDest);
        input = tabDest.stream;
        source = 'tab audio';
      } else {
        tab.getTracks().forEach((t) => t.stop());
      }
    } catch (e) {
      source += ` (tab audio unavailable: ${e.name})`;
    }

    // How loud the call was during each segment, so silent recordings are not sent for transcription
    const analyser = state.ctx.createAnalyser();
    analyser.fftSize = 2048;
    state.ctx.createMediaStreamSource(input).connect(analyser);
    const samples = new Float32Array(analyser.fftSize);
    state.peak = 0;
    clearInterval(state.meter);
    state.meter = setInterval(() => {
      if (state.ctx.state !== 'running') state.ctx.resume();
      analyser.getFloatTimeDomainData(samples);
      for (let i = 0; i < samples.length; i++) state.peak = Math.max(state.peak, Math.abs(samples[i]));
    }, 250);

    const run = () => {
      const chunks = [];
      const recorder = new MediaRecorder(input, { mimeType: 'audio/webm;codecs=opus', audioBitsPerSecond: 32000 });
      state.recorder = recorder;
      recorder.ondataavailable = (e) => { if (e.data && e.data.size) chunks.push(e.data); };
      recorder.onstop = async () => {
        const blob = new Blob(chunks, { type: 'audio/webm' });
        const index = state.index++;
        const buffer = await blob.arrayBuffer();
        let binary = '';
        const bytes = new Uint8Array(buffer);
        for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        const peak = state.peak;
        state.peak = 0;
        await window.__crmSegment(index, btoa(binary), state.stopping, peak, state.keep.length, state.ctx.state);
        if (!state.stopping) run();
      };
      recorder.start(10000);
      state.timer = setTimeout(() => recorder.state === 'recording' && recorder.stop(), segmentMs);
    };
    run();
    return source;
  }, SEGMENT_MS);
}

async function stopRecorder(page) {
  await page.evaluate(() => {
    const state = window.__crmNotes;
    state.stopping = true;
    clearTimeout(state.timer);
    clearInterval(state.meter);
    if (state.recorder && state.recorder.state === 'recording') state.recorder.stop();
    if (state.tab) state.tab.getTracks().forEach((t) => t.stop());
  }).catch(() => {});
}

// Why was a segment silent? Meet's receive statistics say whether any call audio reached the bot at all
async function diagnoseSilence(page, tag) {
  const info = await page.evaluate(async () => {
    const audio = [];
    for (const pc of (window.__crmNotes.pcs || [])) {
      if (pc.connectionState === 'closed') continue;
      const stats = await pc.getStats();
      stats.forEach((r) => {
        if (r.type === 'inbound-rtp' && r.kind === 'audio') {
          audio.push({ kB: Math.round((r.bytesReceived || 0) / 1024), level: r.audioLevel ?? null, energy: r.totalAudioEnergy ?? null });
        }
      });
    }
    const names = [...new Set([...document.querySelectorAll('[data-participant-id]')]
      .map((el) => (el.innerText || '').split('\n')[0].trim()).filter(Boolean))];
    return { audio, names };
  });
  log(tag, 'call audio received by the bot (per stream):', JSON.stringify(info.audio));
  log(tag, 'people in the call as the bot sees them:', JSON.stringify(info.names));
  const shot = path.join(__dirname, 'silent-' + Date.now() + '.png');
  await page.screenshot({ path: shot }).catch(() => {});
  log(tag, 'screenshot:', shot);
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
  await page.exposeFunction('__crmSegment', (pageIndex, base64, last, peak = 0, tracks = 0, ctxState = '') => {
    const audio = Buffer.from(base64, 'base64');
    if (audio.length < 2000) return; // nothing recorded
    const level = `level ${(peak * 100).toFixed(1)}%, ${tracks} audio stream(s), audio ${ctxState}`;
    if (peak < SILENCE_PEAK) {
      log(tag, `segment ${pageIndex + 1} silent, not uploaded (${level})`
        + (tracks ? '' : ' - the bot received no audio from the call'));
      diagnoseSilence(page, tag).catch((e) => log(tag, 'diagnosis failed:', e.message));
      if (last) lastSegmentSent = uploads;
      return;
    }
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
          log(tag, `audio part ${index + 1} uploaded (${Math.round(audio.length / 1024)} KB, ${level})`);
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

    // Never "Switch here": that button means the bot's Google account is already in the call on someone's device,
    // and clicking it would take that person's call away. "Join here too" adds the bot alongside them.
    const join = page.locator('button:has-text("Join now"), button:has-text("Join here too"), button:has-text("Ask to join")');
    if (await page.locator('button:has-text("Switch here")').count()) {
      log(tag, "WARNING: the bot's Google account is already in this call on another device. Sign the bot in to its own account (notetaker-bot\\sign-in.bat); Meet does not send a person's voice to their own second device.");
    }
    await join.first().waitFor({ timeout: 30000 });
    log(tag, 'clicking', JSON.stringify((await join.first().innerText().catch(() => '')).trim()));
    await join.first().click();

    const leave = page.locator('[aria-label*="Leave call" i]');
    await leave.first().waitFor({ timeout: 120000 });
    log(tag, 'in the call');

    log(tag, 'recording', await startRecorder(page));
    // Early check: after a minute in the call, log whether Meet is sending the bot any audio
    setTimeout(() => diagnoseSilence(page, tag).catch(() => {}), 60000);

    const endsAt = new Date(meeting.ends_at).getTime();
    let aloneSince = null;
    let someoneJoined = false; // the bot joins early: wait for people instead of leaving before they arrive

    while (true) {
      await page.waitForTimeout(15000);
      if (!(await leave.count())) { log(tag, 'removed from the call or call ended'); done = true; break; }

      const others = await othersInCall(page);
      if (others > 0 && !someoneJoined) { someoneJoined = true; log(tag, `${others} other person(s) in the call`); }
      aloneSince = others > 0 ? null : (aloneSince || Date.now());

      // Nobody came: stay until the scheduled end (plus the alone time), then leave
      if (!someoneJoined && Date.now() > endsAt + ALONE_LEAVE_MS) { log(tag, 'nobody joined by the end time'); done = true; break; }
      if (someoneJoined && aloneSince && Date.now() - aloneSince > ALONE_LEAVE_MS) { log(tag, 'everyone has left'); done = true; break; }
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
