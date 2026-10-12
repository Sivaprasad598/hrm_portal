@echo off
rem Starts the CRM Notetaker bot in its own minimised window (keeps running after this window is closed).
rem The bot joins every portal Google Meet at its start time. Log: notetaker-bot\bot.log
cd /d "%~dp0"
start "CRM Notetaker bot" /min cmd /c "node bot.js >> bot.log 2>&1"
