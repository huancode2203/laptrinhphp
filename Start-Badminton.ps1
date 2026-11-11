param([switch] $Watch)
$ErrorActionPreference = 'Stop'
$projectPath = $PSScriptRoot
$runtimePath = Join-Path $env:ProgramData 'BadmintonHub'
$apachePath = 'C:\laragon\bin\apache\httpd-2.4.68-260617-Win64-VS18\bin\httpd.exe'
$mysqlPath = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe'
$phpPath = 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
$apacheConfig = Join-Path $runtimePath 'apache.conf'
if ([Security.Principal.WindowsIdentity]::GetCurrent().User.Value -ne 'S-1-5-18') {
    $userRuntime = Join-Path $env:LOCALAPPDATA 'BadmintonHub'
    New-Item -ItemType Directory -Force -Path $userRuntime | Out-Null
    $configText = [IO.File]::ReadAllText($apacheConfig).Replace($runtimePath.Replace('\','/'), $userRuntime.Replace('\','/'))
    $runtimePath = $userRuntime
    $apacheConfig = Join-Path $runtimePath 'apache-user.conf'
    [IO.File]::WriteAllText($apacheConfig, $configText)
}
$mutex = $null
if ($Watch) {
    $created = $false
    $mutex = New-Object Threading.Mutex($true, 'Local\BadmintonHubRunner', [ref] $created)
    if (!$created) { $mutex.Dispose(); exit }
}
function Test-LocalPort([int] $Port) {
    $client = New-Object Net.Sockets.TcpClient
    try { $client.Connect('127.0.0.1', $Port); return $true } catch { return $false } finally { $client.Dispose() }
}
do {
try {
if (!(Test-LocalPort 3306)) {
    Start-Process -FilePath $mysqlPath -ArgumentList '--defaults-file=C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini','--bind-address=127.0.0.1' -WindowStyle Hidden
    for ($attempt = 0; $attempt -lt 10 -and !(Test-LocalPort 3306); $attempt++) { Start-Sleep -Seconds 1 }
}
if (!(Test-LocalPort 8091)) {
    Start-Process -FilePath $apachePath -ArgumentList '-f',('"' + $apacheConfig + '"') -WindowStyle Hidden
}
Set-Location -LiteralPath $projectPath
& $phpPath artisan schedule:run --no-interaction *>> (Join-Path $runtimePath 'scheduler.log')
} catch {
    Add-Content -LiteralPath (Join-Path $runtimePath 'scheduler.log') -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + ' Scheduler chua hoan tat; se thu lai o phut tiep theo.')
    if (!$Watch) { exit 1 }
}
if ($Watch) { Start-Sleep -Seconds ([Math]::Max(1, 60 - (Get-Date).Second)) }
} while ($Watch)
