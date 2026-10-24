param([int]$Port = 9000, [switch]$Restart)

$ErrorActionPreference = 'Stop'
$gpsWorkspace = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $gpsWorkspace
$gpsPhp = 'C:\xampp\php\php.exe'
$gpsStatePath = Join-Path $gpsWorkspace 'storage\framework\gps-realtime-processes.json'
$gpsServices = @{}
if (Test-Path -LiteralPath $gpsStatePath) {
    $gpsSaved = Get-Content -LiteralPath $gpsStatePath -Raw | ConvertFrom-Json
    foreach ($gpsProperty in $gpsSaved.PSObject.Properties) { $gpsServices[$gpsProperty.Name] = $gpsProperty.Value }
}

function Start-GpsService([string]$Name, [string[]]$Arguments, [int]$ListenPort = 0) {
    $gpsExisting = $gpsServices[$Name]
    if ($gpsExisting) {
        $gpsRunning = Get-Process -Id $gpsExisting.Id -ErrorAction SilentlyContinue
        if ($gpsRunning -and $gpsRunning.ProcessName -eq 'php' -and $gpsRunning.StartTime.ToUniversalTime().Ticks.ToString() -eq $gpsExisting.StartTicks) {
            if (-not $Restart) {
                Write-Host "$Name is already running (PID $($gpsRunning.Id))."
                return
            }
            # Only stop the exact process recorded by this workspace, checking
            # start time as well as PID so a reused PID is never terminated.
            Stop-Process -Id $gpsRunning.Id
            $gpsRunning.WaitForExit(10000) | Out-Null
            if (-not $gpsRunning.HasExited) { throw "$Name did not stop." }
        }
    }
    if ($ListenPort -and @(netstat -ano -p tcp | Select-String "^\s*TCP\s+\S+:$ListenPort\s+\S+\s+LISTENING\s+\d+\s*$").Count) {
        Write-Host "Port $ListenPort already has a listener. Keeping it; restart that process after code changes."
        return
    }
    foreach ($gpsSuffix in @('.log', '-error.log')) {
        $gpsLog = Join-Path $gpsWorkspace "storage\logs\gps-$Name$gpsSuffix"
        if (Test-Path -LiteralPath $gpsLog) {
            Copy-Item -LiteralPath $gpsLog -Destination "$gpsLog.$(Get-Date -Format 'yyyyMMdd-HHmmss-fff').bak"
        }
    }
    $gpsProcess = Start-Process -FilePath $gpsPhp -ArgumentList $Arguments -WorkingDirectory $gpsWorkspace -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $gpsWorkspace "storage\logs\gps-$Name.log") `
        -RedirectStandardError (Join-Path $gpsWorkspace "storage\logs\gps-$Name-error.log") -PassThru
    $gpsServices[$Name] = @{Id = $gpsProcess.Id; StartTicks = $gpsProcess.StartTime.ToUniversalTime().Ticks.ToString()}
    Write-Host "Started $Name (PID $($gpsProcess.Id))."
}

# Dedicated local database queue: no network broadcasts inside the GPS read loop.
# Production uses Redis ingestion and supervised workers; see GPS-REALTIME.md.
Start-GpsService 'reverb' @('artisan','reverb:start','--host=127.0.0.1','--port=8080') 8080
Start-GpsService 'live-worker' @('artisan','queue:work','database','--queue=gps-live','--sleep=0.1','--tries=5','--backoff=3','--timeout=30')
Start-GpsService 'persistence-worker' @('artisan','queue:work','database','--queue=gps-persist','--sleep=1','--tries=5','--backoff=3','--timeout=30')
Start-GpsService 'broadcast-worker' @('artisan','queue:work','database','--queue=gps-broadcasts','--sleep=1','--tries=5','--backoff=3','--timeout=30')
Start-GpsService 'receiver' @('artisan','gps:listen','--host=0.0.0.0',"--port=$Port",'--diagnostic','--persist','--registered','--diagnostic-protocol=pt06','--format=json') $Port
$gpsServices | ConvertTo-Json | Set-Content -LiteralPath $gpsStatePath -Encoding UTF8
Write-Host 'Realtime fleet services started. Logs: storage\logs\gps-{service}.log. Run this script again after a PC restart.'
