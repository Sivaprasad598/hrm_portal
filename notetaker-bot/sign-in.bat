@echo off
rem Opens the CRM Notetaker bot's own Chrome profile. Sign in once as the company Google account, then close the window.
start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --user-data-dir="%~dp0chrome-profile" --no-first-run --new-window "https://accounts.google.com/ServiceLogin?continue=https://meet.google.com/"
