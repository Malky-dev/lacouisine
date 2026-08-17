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
    try {
        $r=Invoke-WebRequest @p
        [PSCustomObject]@{StatusCode=[int]$r.StatusCode;Content=$r.Content;Error=$null}
    } catch {
        $errorRecord=$_
        $r=$errorRecord.Exception.Response
        if($null-eq$r){return [PSCustomObject]@{StatusCode=0;Content="";Error=$errorRecord.Exception.Message}}

        $c=""
        if($null-ne$errorRecord.ErrorDetails-and-not[string]::IsNullOrWhiteSpace($errorRecord.ErrorDetails.Message)){
            $c=$errorRecord.ErrorDetails.Message
        } else {
            try {
                $contentProperty=$r.PSObject.Properties['Content']
                if($null-ne$contentProperty-and$null-ne$contentProperty.Value-and$null-ne$contentProperty.Value.PSObject.Methods['ReadAsStringAsync']){
                    $c=$contentProperty.Value.ReadAsStringAsync().GetAwaiter().GetResult()
                } elseif($null-ne$r.PSObject.Methods['GetResponseStream']) {
                    $s=$r.GetResponseStream()
                    if($s){
                        $rd=New-Object System.IO.StreamReader($s)
                        try{$c=$rd.ReadToEnd()}finally{$rd.Dispose()}
                    }
                }
            } catch {}
        }

        [PSCustomObject]@{StatusCode=[int]$r.StatusCode;Content=$c;Error=$null}
    }
}
function Invoke-ApiMultipartRequest {
    param([string]$Path,[string]$FilePath,[hashtable]$Headers=@{})
    Add-Type -AssemblyName System.Net.Http
    $client=New-Object System.Net.Http.HttpClient
    $form=New-Object System.Net.Http.MultipartFormDataContent
    try {
        foreach($entry in $Headers.GetEnumerator()){$client.DefaultRequestHeaders.TryAddWithoutValidation($entry.Key,$entry.Value)|Out-Null}
        $bytes=[System.IO.File]::ReadAllBytes($FilePath);$file=New-Object System.Net.Http.ByteArrayContent -ArgumentList (,$bytes);$file.Headers.ContentType=[System.Net.Http.Headers.MediaTypeHeaderValue]::Parse("image/png");$form.Add($file,"thumbnail",[System.IO.Path]::GetFileName($FilePath));$response=$client.PostAsync("$script:BaseUrl$Path",$form).GetAwaiter().GetResult();$content=$response.Content.ReadAsStringAsync().GetAwaiter().GetResult();[PSCustomObject]@{StatusCode=[int]$response.StatusCode;Content=$content;Error=$null}
    } finally {$form.Dispose();$client.Dispose()}
}
function Assert-StatusCode { param([object]$Response,[int]$ExpectedStatus,[string]$TestName) if($Response.StatusCode-eq$ExpectedStatus){Write-TestSuccess $TestName;return $true};Write-TestFailure $TestName "Expected HTTP $ExpectedStatus, received HTTP $($Response.StatusCode). $($Response.Error)";return $false }
function Convert-JsonResponse { param([object]$Response,[string]$TestName) try{$Response.Content|ConvertFrom-Json}catch{Write-TestFailure $TestName "The response does not contain valid JSON.";$null} }
function Assert-ApiError { param([object]$Response,[string]$ExpectedCode,[string]$TestName) $d=Convert-JsonResponse $Response $TestName;if($d.error.code-eq$ExpectedCode-and$d.error.message){Write-TestSuccess $TestName}else{Write-TestFailure $TestName "Expected error.code to equal $ExpectedCode."} }
function Complete-SmokeTests { Write-Host "`nSummary" -ForegroundColor Cyan;Write-Host "Passed : $script:PassedTests" -ForegroundColor Green;Write-Host "Failed : $script:FailedTests" -ForegroundColor Red;Write-Host "Skipped: $script:SkippedTests" -ForegroundColor Yellow;if($script:FailedTests-gt 0){exit 1} }
