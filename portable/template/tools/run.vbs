' AGAPAY for Windows: runs one of the AGAPAY tools without a black console window.
'   wscript run.vbs open.ps1 [arguments]   (used by the AGAPAY shortcuts, Startup and the scheduled tasks)
Set shell = CreateObject("WScript.Shell")
folder = CreateObject("Scripting.FileSystemObject").GetParentFolderName(WScript.ScriptFullName)
command = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File """ & folder & "\" & WScript.Arguments(0) & """"
For i = 1 To WScript.Arguments.Count - 1
    command = command & " " & WScript.Arguments(i)
Next
WScript.Quit shell.Run(command, 0, True)
