function Initialize-SmokeTests {
    param([string]$TargetUrl = "http://localhost:8000")
    $script:BaseUrl = $TargetUrl.TrimEnd("/"); $script:PassedTests = 0; $script:FailedTests = 0; $script:SkippedTests = 0
}
function Write-TestSuccess { param([string]$Message) $script:PassedTests++; Write-Host "[PASS] $Message" -ForegroundColor Green }
function Write-TestSkipped { param([string]$Message) $script:SkippedTests++; Write-Host "[SKIP] $Message" -ForegroundColor Yellow }
function Write-TestFailure { param([string]$Message,[string]$Details="") $script:FailedTests++; Write-Host "[FAIL] $Message" -ForegroundColor Red; if($Details){Write-Host "       $Details" -ForegroundColor DarkRed} }
function Invoke-ApiRequest {
    param([string]$Method,[string]$Path,[hashtable]$Headers=@{},[AllowNull()][object]$Body=$null)
    $p=@{Method=$Method;Uri="$script:BaseUrl$Path";Headers=$Headers;UseBasicParsing=$true;TimeoutSec=10;ErrorAction="Stop"}; if($null-ne$Body){$p.ContentType="application/json";$p.Body=$Body|ConvertTo-Json -Compress}
    try{$r=Invoke-WebRequest @p;[PSCustomObject]@{StatusCode=[int]$r.StatusCode;Content=$r.Content;Error=$null}}catch{$r=$_.Exception.Response;if($null-eq$r){return [PSCustomObject]@{StatusCode=0;Content="";Error=$_.Exception.Message}};$c="";try{$s=$r.GetResponseStream();if($s){$rd=New-Object System.IO.StreamReader($s);try{$c=$rd.ReadToEnd()}finally{$rd.Dispose()}}}catch{};[PSCustomObject]@{StatusCode=[int]$r.StatusCode;Content=$c;Error=$null}}
}
function Assert-StatusCode { param([object]$Response,[int]$ExpectedStatus,[string]$TestName) if($Response.StatusCode-eq$ExpectedStatus){Write-TestSuccess $TestName;return $true};Write-TestFailure $TestName "Expected HTTP $ExpectedStatus, received HTTP $($Response.StatusCode). $($Response.Error)";return $false }
function Convert-JsonResponse { param([object]$Response,[string]$TestName) try{$Response.Content|ConvertFrom-Json}catch{Write-TestFailure $TestName "The response does not contain valid JSON.";$null} }
function Assert-ApiError { param([object]$Response,[string]$ExpectedCode,[string]$TestName) $d=Convert-JsonResponse $Response $TestName;if($d.error.code-eq$ExpectedCode-and$d.error.message){Write-TestSuccess $TestName}else{Write-TestFailure $TestName "Expected error.code to equal $ExpectedCode."} }
function Complete-SmokeTests { Write-Host "`nSummary" -ForegroundColor Cyan;Write-Host "Passed : $script:PassedTests" -ForegroundColor Green;Write-Host "Failed : $script:FailedTests" -ForegroundColor Red;Write-Host "Skipped: $script:SkippedTests" -ForegroundColor Yellow;if($script:FailedTests-gt 0){exit 1} }
