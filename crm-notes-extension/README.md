# CRM Notes (Chrome extension)

Takes notes in CRM Google Meet calls without Gemini: the extension records the call audio from the lead owner's
browser, the HRM portal transcribes it with OpenAI and writes meeting notes, and the notes appear in
**Customers → Meetings → Meet Notes**.

## Install (each lead owner, once)

1. Copy this `crm-notes-extension` folder to the lead owner's computer.
2. In Chrome open `chrome://extensions`, turn on **Developer mode** (top right), click **Load unpacked**, choose the folder.
3. Click the puzzle icon in the toolbar and **pin** CRM Notes.
4. In the portal: Customers → Meetings → **CRM Notes extension** → **Create my key**.
5. Right-click the CRM Notes icon → **Options**: paste the Portal URL and key → **Save and test**,
   then **Allow microphone**.

## Use (each meeting)

1. Open the CRM meeting's Google Meet link in Chrome (on a computer) and join. A banner says it is a CRM meeting.
2. Click the **CRM Notes** icon once. The icon shows **REC**.
3. Have the meeting. When you leave the call (or close the tab, or click the icon again) the notes are saved.
4. A minute or two later the notes are in **Meet Notes** for that meeting.

Only meetings created in the portal are recorded. Tell customers the call is recorded for notes.

## How it works

- Audio: the Meet tab (the other people) mixed with the lead owner's microphone, uploaded every 5 minutes.
- The portal transcribes each part (OpenAI `OPENAI_TRANSCRIBE_MODEL`), deletes the audio and keeps only the text.
- When the call ends, the transcript is turned into notes (OpenAI `OPENAI_NOTES_MODEL`):
  Summary, Discussion Points, Customer Requirements, Decisions, Action Items, Next Follow-up.
- If the browser closes mid-call, the portal's scheduler saves the notes 15 minutes after the last upload.
