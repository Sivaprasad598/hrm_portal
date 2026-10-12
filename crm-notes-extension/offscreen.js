// CRM Notes: offscreen recorder.
// Mixes the Meet tab audio (the other people) with this user's microphone, records ~5-minute segments
// and uploads each one to the portal, which transcribes it right away. On stop: last segment + "finish".

const SEGMENT_MS = 5 * 60 * 1000;
const BITRATE = 32000; // speech quality; ~1.2 MB per 5 minutes

let state = null;

chrome.runtime.onMessage.addListener((msg) => {
  if (msg.target !== 'offscreen') return;
  if (msg.type === 'start') start(msg).catch((e) => finished(false, 'Could not start recording: ' + e.message));
  if (msg.type === 'stop') stop();
});

async function start({ streamId, meetingId, portalUrl, key }) {
  const tabStream = await navigator.mediaDevices.getUserMedia({
    audio: { mandatory: { chromeMediaSource: 'tab', chromeMediaSourceId: streamId } },
    video: false,
  });

  const ctx = new AudioContext();
  const mix = ctx.createMediaStreamDestination();

  // Capturing a tab mutes it, so play the call audio back to the user as well
  const tabSource = ctx.createMediaStreamSource(tabStream);
  tabSource.connect(ctx.destination);
  tabSource.connect(mix);

  // The user's own voice is not in the tab audio; add the microphone (not played back, no echo)
  let micStream = null;
  try {
    micStream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
    ctx.createMediaStreamSource(micStream).connect(mix);
  } catch (e) {
    chrome.runtime.sendMessage({ type: 'crm-notes-mic-missing' });
  }

  state = {
    meetingId, portalUrl, key, ctx, mix, tabStream, micStream,
    index: 0, recorder: null, timer: null, stopping: false,
    uploads: Promise.resolve(),
  };

  nextSegment();
}

function nextSegment() {
  const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm';
  const recorder = new MediaRecorder(state.mix.stream, { mimeType, audioBitsPerSecond: BITRATE });
  const chunks = [];
  const index = state.index++;

  recorder.ondataavailable = (e) => { if (e.data && e.data.size) chunks.push(e.data); };
  recorder.onstop = () => {
    const blob = new Blob(chunks, { type: 'audio/webm' });
    // Each segment is a complete audio file; uploads go one after another, in order
    state.uploads = state.uploads.then(() => upload(index, blob));
    if (!state.stopping) nextSegment();
    else finish();
  };

  recorder.start();
  state.recorder = recorder;
  state.timer = setTimeout(() => recorder.state === 'recording' && recorder.stop(), SEGMENT_MS);
}

async function upload(index, blob, attempt = 1) {
  if (blob.size < 2000) return; // nothing recorded

  const form = new FormData();
  form.append('index', String(index));
  form.append('audio', blob, `segment-${index}.webm`);

  try {
    const res = await fetch(`${state.portalUrl}/api/crm-notes/meetings/${state.meetingId}/segments`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${state.key}`, Accept: 'application/json' },
      body: form,
    });

    if (res.status === 409 || res.status === 401) {
      const body = await res.json().catch(() => ({}));
      chrome.runtime.sendMessage({ type: 'crm-notes-upload-error', message: body.message || 'upload refused.' });
      return;
    }

    if (!res.ok) throw new Error('HTTP ' + res.status);
  } catch (e) {
    if (attempt < 4) {
      await new Promise((r) => setTimeout(r, attempt * 5000));
      return upload(index, blob, attempt + 1);
    }
    chrome.runtime.sendMessage({ type: 'crm-notes-upload-error', message: `part ${index + 1} of the call could not be uploaded (${e.message}).` });
  }
}

function stop() {
  if (!state || state.stopping) return;
  state.stopping = true;
  clearTimeout(state.timer);

  if (state.recorder && state.recorder.state === 'recording') {
    state.recorder.stop(); // onstop uploads the last segment, then finish()
  } else {
    finish();
  }
}

async function finish() {
  await state.uploads;

  let ok = false;
  let message = '';

  try {
    const res = await fetch(`${state.portalUrl}/api/crm-notes/meetings/${state.meetingId}/finish`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${state.key}`, Accept: 'application/json' },
    });
    const body = await res.json().catch(() => ({}));
    ok = res.ok;
    message = body.message || '';
  } catch (e) {
    message = 'could not reach the portal to save the notes (they will be saved automatically within 15 minutes).';
  }

  state.tabStream.getTracks().forEach((t) => t.stop());
  if (state.micStream) state.micStream.getTracks().forEach((t) => t.stop());
  state.ctx.close();

  finished(ok, message);
}

function finished(ok, message) {
  state = null;
  chrome.runtime.sendMessage({ type: 'crm-notes-finished', ok, message });
}
