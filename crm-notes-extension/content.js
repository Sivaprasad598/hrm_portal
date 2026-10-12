// CRM Notes: runs on meet.google.com.
// Tells the user when a call is a CRM meeting, shows CRM Notes messages, and reports when the call ends.

(function () {
  const match = location.pathname.match(/^\/([a-z]{3,}-[a-z]{3,}-[a-z]{3,})(?:$|[/?#])/i);
  if (!match) return;

  const code = match[1].toLowerCase();

  function toast(text, kind) {
    let box = document.getElementById('crm-notes-toast');
    if (!box) {
      box = document.createElement('div');
      box.id = 'crm-notes-toast';
      box.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:2147483647;max-width:520px;'
        + 'padding:10px 14px;border-radius:8px;font:14px/1.4 Arial,sans-serif;box-shadow:0 4px 16px rgba(0,0,0,.3);cursor:pointer;';
      box.title = 'Click to close';
      box.addEventListener('click', () => box.remove());
      document.body.appendChild(box);
    }
    box.style.background = kind === 'error' ? '#fce8e6' : '#e8eaff';
    box.style.color = kind === 'error' ? '#a50e0e' : '#1e1b6b';
    box.textContent = text;
    clearTimeout(box._timer);
    box._timer = setTimeout(() => box.remove(), 9000);
  }

  chrome.runtime.onMessage.addListener((msg) => {
    if (msg.type === 'crm-notes-toast') toast(msg.text, msg.kind);
  });

  chrome.runtime.sendMessage({ type: 'crm-notes-lookup', code }, (info) => {
    if (chrome.runtime.lastError || !info) return;
    if (info.meeting) {
      toast(`CRM meeting: ${info.meeting.title}. After joining, click the CRM Notes icon in the Chrome toolbar to take notes.`);
    } else if (info.error === 'not-configured') {
      toast('CRM Notes: set the Portal URL and key in the extension options to take notes in CRM meetings.', 'error');
    }
  });

  // The call ends when Meet's "Leave call" button disappears (user left / was removed)
  let inCall = false;
  let goneChecks = 0;

  setInterval(() => {
    const leaveButton = document.querySelector('[aria-label^="Leave call" i], [aria-label*="leave call" i]');

    if (leaveButton) {
      inCall = true;
      goneChecks = 0;
    } else if (inCall && ++goneChecks >= 2) {
      inCall = false;
      chrome.runtime.sendMessage({ type: 'crm-notes-call-ended' });
    }
  }, 3000);
})();
