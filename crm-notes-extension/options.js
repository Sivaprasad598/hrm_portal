const $ = (id) => document.getElementById(id);

function show(id, text, ok) {
  $(id).textContent = text;
  $(id).className = 'status ' + (ok ? 'ok' : 'err');
}

chrome.storage.sync.get(['portalUrl', 'key'], ({ portalUrl = '', key = '' }) => {
  $('portalUrl').value = portalUrl;
  $('key').value = key;
});

$('save').addEventListener('click', async () => {
  const portalUrl = $('portalUrl').value.trim().replace(/\/+$/, '');
  const key = $('key').value.trim();

  if (!/^https?:\/\//i.test(portalUrl) || !key) {
    show('saveStatus', 'Enter the Portal URL (starting with http:// or https://) and your key.', false);
    return;
  }

  // Portals other than localhost need permission for that site
  const origin = new URL(portalUrl).origin + '/*';
  if (!/^http:\/\/(localhost|127\.0\.0\.1)\//.test(origin)) {
    const granted = await chrome.permissions.request({ origins: [origin] });
    if (!granted) {
      show('saveStatus', 'Permission for the portal site was not given.', false);
      return;
    }
  }

  await chrome.storage.sync.set({ portalUrl, key });

  try {
    const res = await fetch(`${portalUrl}/api/crm-notes/lookup?code=test-test-test`, {
      headers: { Authorization: `Bearer ${key}`, Accept: 'application/json' },
    });
    if (res.ok) show('saveStatus', 'Saved. Connected to the portal.', true);
    else if (res.status === 401) show('saveStatus', 'Saved, but the key is not valid. Create a new key on the Meetings tab.', false);
    else show('saveStatus', `Saved, but the portal answered with an error (${res.status}). Check the Portal URL.`, false);
  } catch (e) {
    show('saveStatus', 'Saved, but the portal could not be reached. Check the Portal URL.', false);
  }
});

$('mic').addEventListener('click', async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    stream.getTracks().forEach((t) => t.stop());
    show('micStatus', 'Microphone allowed.', true);
  } catch (e) {
    show('micStatus', 'Microphone was not allowed (' + e.name + '). Click the camera/lock icon in the address bar to allow it, or plug in a microphone.', false);
  }
});
