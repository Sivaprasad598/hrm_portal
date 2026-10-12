' Starts the CRM Notetaker bot hidden in the background (no window to close by mistake).
' Runs at Windows login from the Startup folder; double-click to start it by hand. Stop it with stop-bot.cmd.
Set fso = CreateObject("Scripting.FileSystemObject")
dir = fso.GetParentFolderName(WScript.ScriptFullName)
Set sh = CreateObject("WScript.Shell")
' Already running? (bot.js writes its process id to bot.pid)
If fso.FileExists(dir & "\bot.pid") Then
  pid = Trim(fso.OpenTextFile(dir & "\bot.pid").ReadAll)
  Set procs = GetObject("winmgmts:").ExecQuery("SELECT ProcessId FROM Win32_Process WHERE Name='node.exe' AND ProcessId=" & pid)
  If procs.Count > 0 Then WScript.Quit
End If
sh.Run "cmd /c """ & dir & "\run-bot.cmd""", 0, False
