@echo off
rem Stop AGAPAY
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\stop.ps1"
