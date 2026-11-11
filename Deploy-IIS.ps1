#Requires -RunAsAdministrator
param([string] $IisRoot = 'C:\inetpub\lover-projects')
$ErrorActionPreference = 'Stop'
$projectPath = $PSScriptRoot
$runtimePath = Join-Path $env:ProgramData 'BadmintonHub'
$apacheRoot = 'C:/laragon/bin/apache/httpd-2.4.68-260617-Win64-VS18'
$configPath = Join-Path $IisRoot 'web.config'
if (!(Test-Path -LiteralPath $configPath)) { throw 'Khong tim thay web.config IIS. Khong thay doi site khac.' }
New-Item -ItemType Directory -Force -Path $runtimePath | Out-Null
$publicPath = (Join-Path $projectPath 'public').Replace('\','/')
$runtimeApachePath = $runtimePath.Replace('\','/')
$apacheConfig = @"
ServerRoot "$apacheRoot"
Listen 127.0.0.1:8091
ServerName localhost:8091
PidFile "$runtimeApachePath/apache.pid"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule log_config_module modules/mod_log_config.so
LoadModule env_module modules/mod_env.so
LoadModule headers_module modules/mod_headers.so
LoadModule rewrite_module modules/mod_rewrite.so
Include "C:/laragon/etc/apache2/mod_php.conf"
DocumentRoot "$publicPath"
<Directory "$publicPath">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
TypesConfig conf/mime.types
ErrorLog "$runtimeApachePath/apache-error.log"
LogFormat "%h %t %m %U %>s" hub
CustomLog "$runtimeApachePath/apache-access.log" hub
ServerTokens Prod
ServerSignature Off
Timeout 60
KeepAlive On
"@
[IO.File]::WriteAllText((Join-Path $runtimePath 'apache.conf'), $apacheConfig, (New-Object Text.UTF8Encoding($false)))
$validation = Start-Process -FilePath "$apacheRoot/bin/httpd.exe" -ArgumentList '-t','-f',('"' + (Join-Path $runtimePath 'apache.conf') + '"') -WindowStyle Hidden -Wait -PassThru -RedirectStandardError (Join-Path $runtimePath 'apache-validation.log')
if ($validation.ExitCode -ne 0) { throw 'Cau hinh Apache chua hop le; IIS chua duoc thay doi. Xem apache-validation.log.' }
$appcmdPath = Join-Path $env:windir 'System32\inetsrv\appcmd.exe'
[xml] $allowed = (& $appcmdPath list config /section:system.webServer/rewrite/allowedServerVariables /xml)
foreach ($name in @('HTTP_X_FORWARDED_PREFIX','HTTP_X_FORWARDED_HOST','HTTP_X_FORWARDED_PROTO','HTTP_X_FORWARDED_PORT')) {
    if (!$allowed.SelectSingleNode("//add[@name='$name']")) {
        & $appcmdPath set config /section:system.webServer/rewrite/allowedServerVariables "/+[name='$name']" /commit:apphost | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Khong them duoc bien proxy $name" }
    }
}
Copy-Item -LiteralPath $configPath -Destination (Join-Path $runtimePath ('iis-before-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.config'))
[xml] $config = Get-Content -LiteralPath $configPath -Raw
$rules = $config.configuration.'system.webServer'.rewrite.rules
foreach ($name in @('Badminton canonical','Badminton subpath','Badminton local preview')) {
    $old = $rules.SelectSingleNode("rule[@name='$name']")
    if ($old) { $rules.RemoveChild($old) | Out-Null }
}
$fragment = $config.CreateDocumentFragment()
$fragment.InnerXml = @'
<rule name="Badminton canonical" stopProcessing="true"><match url="^badminton$"/><action type="Redirect" url="/badminton/" redirectType="Permanent"/></rule>
<rule name="Badminton subpath" stopProcessing="true"><match url="^badminton/(.*)$"/><serverVariables><set name="HTTP_X_FORWARDED_PREFIX" value="/badminton"/><set name="HTTP_X_FORWARDED_HOST" value="pdh2203.id.vn"/><set name="HTTP_X_FORWARDED_PROTO" value="https"/><set name="HTTP_X_FORWARDED_PORT" value="443"/></serverVariables><action type="Rewrite" url="http://127.0.0.1:8091/{R:1}" appendQueryString="true"/></rule>
<rule name="Badminton local preview" stopProcessing="true"><match url="(.*)"/><conditions><add input="{HTTP_HOST}" pattern="^(localhost|127\.0\.0\.1):8088$"/></conditions><action type="Rewrite" url="http://127.0.0.1:8091/{R:1}" appendQueryString="true"/></rule>
'@
$rules.AppendChild($fragment) | Out-Null
$config.Save($configPath)
$launchPath = Join-Path $projectPath 'Start-Badminton.ps1'
$powershellPath = Join-Path $env:windir 'System32\WindowsPowerShell\v1.0\powershell.exe'
$action = New-ScheduledTaskAction -Execute $powershellPath -Argument ('-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + $launchPath + '"')
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$startup = New-ScheduledTaskTrigger -AtStartup
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 3) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName 'Badminton Hub - Web and Scheduler' -Action $action -Trigger @($trigger,$startup) -Settings $settings -User 'SYSTEM' -RunLevel Highest -Force | Out-Null
Start-ScheduledTask -TaskName 'Badminton Hub - Web and Scheduler'
Write-Host 'Da cau hinh /badminton/ va localhost:8088. Quy tac /lover/ duoc giu nguyen.'
