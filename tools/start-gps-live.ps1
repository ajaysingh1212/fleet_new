$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath 'D:\fleet_new'
$Host.UI.RawUI.WindowTitle = 'GPS LIVE - Port 9000 - IMEI 864540081540460'
$gpsLogPath = 'D:\fleet_new\storage\logs\gps-live-diagnostic.log'
Write-Host 'GPS LIVE DIAGNOSTIC - IMEI 864540081540460' -ForegroundColor Cyan
Write-Host 'Live TCP reception and documented GT06 login/heartbeat ACKs. No database persistence.' -ForegroundColor Yellow
Write-Host 'If the Python test listener is running, press Ctrl+C in its terminal.' -ForegroundColor Yellow
$gpsWaitLogged = $false
while (@(netstat -ano -p tcp | Select-String '^\s*TCP\s+\S+:9000\s+\S+\s+LISTENING\s+\d+\s*$').Count -gt 0) {
    if (-not $gpsWaitLogged) {
        $gpsWaitMessage = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] WAITING: Port 9000 is occupied. Stop the Python listener with Ctrl+C."
        Write-Host $gpsWaitMessage -ForegroundColor Yellow
        Add-Content -LiteralPath $gpsLogPath -Value $gpsWaitMessage
        $gpsWaitLogged = $true
    }
    Start-Sleep -Seconds 2
}
Write-Host 'Port 9000 is free. Starting Laravel diagnostic listener...' -ForegroundColor Green
& 'C:\xampp\php\php.exe' artisan gps:listen --host=0.0.0.0 --port=9000 --diagnostic --diagnostic-imei=864540081540460 2>&1 |
    ForEach-Object { Write-Output $_; Add-Content -LiteralPath $gpsLogPath -Value $_ -Encoding UTF8 }
Write-Host "Listener exited with code $LASTEXITCODE. Log: $gpsLogPath" -ForegroundColor Yellow
