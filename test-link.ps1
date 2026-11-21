# Usage (PowerShell, Windows 10+ has curl.exe built in):
#   powershell -ExecutionPolicy Bypass -File .\test-link.ps1
#   powershell -ExecutionPolicy Bypass -File .\test-link.ps1 -WaitExpiry
#   powershell -ExecutionPolicy Bypass -File .\test-link.ps1 -Base "http://localhost:8080/stream-lab"
param(
  [string]$Base = "http://localhost/stream-lab",
  [switch]$WaitExpiry
)

$jar = Join-Path $env:TEMP "streamlab-cookies.txt"

function Show([string]$label, $actual, $expected) {
  $mark = if ("$actual" -eq "$expected") { "OK " } else { "?? " }
  Write-Host ("{0} {1,-58} HTTP {2} (expected {3})" -f $mark, $label, $actual, $expected)
}

function Status([string[]]$curlArgs) {
  & curl.exe -s -o NUL -w "%{http_code}" @curlArgs
}

function Login([string]$user, [string]$pass) {
  Remove-Item $jar -ErrorAction SilentlyContinue
  & curl.exe -s -o NUL -c $jar -d "username=$user" -d "password=$pass" "$Base/login.php" | Out-Null
}

Write-Host "Target: $Base"
Write-Host ""

Remove-Item $jar -ErrorAction SilentlyContinue
Show "link.php without login" (Status @("$Base/link.php?f=video.mp4")) 401

Login "free" "free123"
Show "link.php as NON-subscriber (free)" (Status @("-b", $jar, "$Base/link.php?f=video.mp4")) 403

Login "sub" "sub123"
$resp = & curl.exe -s -b $jar "$Base/link.php?f=video.mp4"
Show "link.php as subscriber (sub)" (Status @("-b", $jar, "$Base/link.php?f=video.mp4")) 200

try { $url = ($resp | ConvertFrom-Json).url } catch { $url = $null }
if (-not $url) { Write-Host "Could not extract signed URL. Response was: $resp"; exit 1 }
Write-Host "Signed URL: $url"
Write-Host ""

Show "download with the link, NO cookies (like a download tool)" (Status @($url)) 200
Show "Range request, first 100 bytes (video seeking)" (Status @("-r", "0-99", $url)) 206
Show "tampered signature" (Status @("$($url)0")) 403
Show "media.php with no token" (Status @("$Base/media.php?f=video.mp4")) 403
Show "direct access to /private/video.mp4 (Apache .htaccess)" (Status @("$Base/private/video.mp4")) 403

if ($WaitExpiry) {
  Write-Host "Waiting 65s for the link to expire..."
  Start-Sleep -Seconds 65
  Show "same link after expiry" (Status @($url)) 403
}
