@echo off
rem CRM Notetaker bot: runs bot.js and restarts it if it ever stops (unless stop-bot.cmd was used). Log: bot.log
cd /d "%~dp0"
del stop.flag 2>nul
:loop
node bot.js >> bot.log 2>&1
if exist stop.flag goto end
timeout /t 15 /nobreak >nul
if exist stop.flag goto end
goto loop
:end
del stop.flag 2>nul
