@echo off
rem Restore AGAPAY backup
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\restore.ps1"
