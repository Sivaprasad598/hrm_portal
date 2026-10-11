@echo off
REM Runs Laravel's scheduler once. Windows Task Scheduler calls this every minute;
REM the scheduler itself decides what is actually due (app/Console/Kernel.php): the
REM biometric punch sync every thirty minutes, the device and employee syncs
REM overnight, the queue worker, and the reminder jobs.
REM
REM The Linux server does not use this file; a crontab entry does the same job:
REM   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
cd /d "%~dp0"
echo [%DATE% %TIME%] scheduler tick >> "storage\logs\schedule.log"
"C:\wamp64\bin\php\php8.2.29\php.exe" artisan schedule:run >> "storage\logs\schedule.log" 2>&1
