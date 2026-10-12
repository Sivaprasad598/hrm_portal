// CRM Notes: background service worker.
// Click the toolbar icon in a CRM Google Meet call to start taking notes; it stops when the call ends,
// the tab is closed, or the icon is clicked again. Audio is recorded in offscreen.js and uploaded to the portal.

const MEET_CODE = /^\/([a-z]{3,}-[a-z]{3,}-[a-z]{3,})(?:$|[/?#])/i;

function meetCode(url) {
  try {
    const u = new URL(url);
    if (u.hostname !== 'meet.google.com') return null;
    const m = u.pathname.match(MEET_CODE);
    return m ? m[1].toLowerCase() : null;
  } catch (e) {
    return null;
  }
}

async function config() {
  const { portalUrl = '', key = '' } = await chrome.storage.sync.get(['portalUrl', 'key']);
  return { portalUrl: portalUrl.replace(/\/+$/, ''), key };
}

async function getRecording() {
  const { recording = null } = await chrome.storage.session.get('recording');
  return recording;
}

async function setRecording(recording) {
  if (recording) {
    await chrome.storage.session.set({ recording });
  } else {
    await chrome.storage.session.remove('recording');
  }
}

function toast(tabId, text, kind = 'info') {
  chrome.tabs.sendMessage(tabId, { type: 'crm-notes-toast', text, kind }).catch(() => {});
}

async function lookup(code) {
  const { portalUrl, key } = await config();
  if (!portalUrl || !key) return { error: 'not-configured' };

  try {
    const res = await fetch(`${portalUrl}/api/crm-notes/lookup?code=${encodeURIComponent(code)}`, {
      headers: { Authorization: `Bearer ${key}`, Accept: 'application/json' },
    });
    if (res.status === 401) return { error: 'bad-key' };
    if (!res.ok) return { error: 'portal-' + res.status };
    return await res.json();
  } catch (e) {
    return { error: 'unreachable' };
  }
}

async function ensureOffscreen() {
  const contexts = await chrome.runtime.getContexts({ contextTypes: ['OFFSCREEN_DOCUMENT'] });
  if (contexts.length) return;

  await chrome.offscreen.createDocument({
    url: 'offscreen.html',
    reasons: ['USER_MEDIA'],
    justification: 'Record the Google Meet tab audio and microphone for CRM meeting notes.',
  });
}

async function start(tab) {
  const code = meetCode(tab.url);
  if (!code) {
    return; // not a Meet call tab: nothing to do
  }

  const info = await lookup(code);

  if (info.error === 'not-configured' || info.error === 'bad-key') {
    toast(tab.id, info.error === 'bad-key'
      ? 'CRM Notes: the key is not valid. Create a new key on the portal Meetings tab and save it in the extension options.'
      : 'CRM Notes: set the Portal URL and key in the extension options first.', 'error');
    chrome.runtime.openOptionsPage();
    return;
  }

  if (info.error) {
    toast(tab.id, 'CRM Notes: cannot reach the HRM portal. Check the Portal URL in the extension options.', 'error');
    return;
  }

  if (!info.meeting) {
    toast(tab.id, 'CRM Notes: this Google Meet is not a CRM meeting, so no notes are taken.', 'error');
    return;
  }

  const { portalUrl, key } = await config();

  // Must be called right after the icon click (Chrome only allows tab capture after a user action)
  const streamId = await chrome.tabCapture.getMediaStreamId({ targetTabId: tab.id });

  await ensureOffscreen();
  await setRecording({ tabId: tab.id, code, meetingId: info.meeting.id, title: info.meeting.title });

  chrome.runtime.sendMessage({
    target: 'offscreen',
    type: 'start',
    streamId,
    meetingId: info.meeting.id,
    portalUrl,
    key,
  });

  chrome.action.setBadgeBackgroundColor({ color: '#d93025' });
  chrome.action.setBadgeText({ tabId: tab.id, text: 'REC' });
  toast(tab.id, `CRM Notes is taking notes for "${info.meeting.title}". They will appear in Meet Notes after the call.`);
}

async function stop(reason) {
  const recording = await getRecording();
  if (!recording || recording.stopping) return;

  await setRecording({ ...recording, stopping: true });
  chrome.action.setBadgeText({ tabId: recording.tabId, text: '...' }).catch(() => {});
  toast(recording.tabId, 'CRM Notes: call ended, saving the notes...');
  chrome.runtime.sendMessage({ target: 'offscreen', type: 'stop', reason });
}

chrome.action.onClicked.addListener(async (tab) => {
  const recording = await getRecording();

  if (recording && recording.tabId === tab.id) {
    await stop('icon');
    return;
  }

  if (recording) {
    toast(tab.id, 'CRM Notes is already recording another call. Stop that one first.', 'error');
    return;
  }

  try {
    await start(tab);
  } catch (e) {
    console.error(e);
    toast(tab.id, 'CRM Notes could not start: ' + e.message, 'error');
  }
});

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg.target === 'offscreen') return false;

  // Content script on a Meet page: is this a CRM meeting?
  if (msg.type === 'crm-notes-lookup') {
    lookup(msg.code).then((info) => {
      if (info && info.meeting && sender.tab) {
        chrome.action.setBadgeBackgroundColor({ color: '#5850ec' });
        getRecording().then((r) => {
          if (!r || r.tabId !== sender.tab.id) chrome.action.setBadgeText({ tabId: sender.tab.id, text: 'CRM' });
        });
      }
      sendResponse(info);
    });
    return true;
  }

  // Content script: the Meet call UI is gone (user left the call)
  if (msg.type === 'crm-notes-call-ended' && sender.tab) {
    getRecording().then((r) => {
      if (r && r.tabId === sender.tab.id) stop('left-call');
    });
    return false;
  }

  if (msg.type === 'crm-notes-mic-missing') {
    getRecording().then((r) => {
      if (r) toast(r.tabId, 'CRM Notes: your microphone is not allowed, so only the other people are recorded. Allow it in the extension options.', 'error');
    });
    return false;
  }

  if (msg.type === 'crm-notes-upload-error') {
    getRecording().then((r) => {
      if (r) toast(r.tabId, 'CRM Notes: ' + msg.message, 'error');
    });
    return false;
  }

  // Offscreen document finished (last upload + notes)
  if (msg.type === 'crm-notes-finished') {
    getRecording().then(async (r) => {
      if (r) {
        chrome.action.setBadgeText({ tabId: r.tabId, text: '' }).catch(() => {});
        toast(r.tabId, msg.ok ? 'CRM Notes: meeting notes saved in the CRM (Meet Notes).' : 'CRM Notes: ' + (msg.message || 'notes could not be saved.'), msg.ok ? 'info' : 'error');
      }
      await setRecording(null);
      chrome.offscreen.closeDocument().catch(() => {});
    });
    return false;
  }

  return false;
});

// Tab closed or moved away from the call
chrome.tabs.onRemoved.addListener(async (tabId) => {
  const r = await getRecording();
  if (r && r.tabId === tabId) stop('tab-closed');
});

chrome.tabs.onUpdated.addListener(async (tabId, changeInfo) => {
  if (!changeInfo.url) return;
  const r = await getRecording();
  if (r && r.tabId === tabId && meetCode(changeInfo.url) !== r.code) stop('navigated');
});
