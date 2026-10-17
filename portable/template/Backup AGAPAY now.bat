@echo off
rem Backup AGAPAY now
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\backup.ps1"
