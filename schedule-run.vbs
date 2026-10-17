Rem Runs schedule-run.bat without a console window, so the every-minute
Rem scheduler task does not flash a window on screen. Windows Task Scheduler
Rem calls this through wscript.exe; the 0 hides the window, False returns at once.
Dim folder
folder = CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName)
CreateObject("WScript.Shell").Run """" & folder & "\schedule-run.bat""", 0, False
