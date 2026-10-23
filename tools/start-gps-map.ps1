$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
$Host.UI.RawUI.WindowTitle = 'GPS Live Map - 865167041931701'
Write-Host 'Live terminal and map: 865167041931701. Database storage ON; file logging OFF.' -ForegroundColor Green
& 'C:\xampp\php\php.exe' artisan gps:listen --host=0.0.0.0 --port=9000 --diagnostic --persist --diagnostic-imei=865167041931701 --diagnostic-protocol=pt06 --format=pretty
