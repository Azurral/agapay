@echo off
rem Copy AGAPAY backups to USB
powershell -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%~dp0tools\copy-to-usb.ps1"
