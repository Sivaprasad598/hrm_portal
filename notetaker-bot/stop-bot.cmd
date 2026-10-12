@echo off
rem Stops the CRM Notetaker bot (it starts again at the next Windows login, or with start-bot.vbs).
cd /d "%~dp0"
echo stop> stop.flag
if exist bot.pid (for /f %%p in (bot.pid) do taskkill /PID %%p /T /F >nul 2>&1)
del bot.pid 2>nul
echo CRM Notetaker bot stopped.
timeout /t 3 >nul
