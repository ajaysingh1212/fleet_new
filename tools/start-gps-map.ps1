$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
& (Join-Path $PSScriptRoot 'start-gps-realtime.ps1')
