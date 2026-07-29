param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$Username = "",
    [string]$Password = ""
)

$ErrorActionPreference = "Stop"

$script:PassedTests = 0
$script:FailedTests = 0
$script:SkippedTests = 0

$BaseUrl = $BaseUrl.TrimEnd("/")

function Write-TestSuccess {
    param([string]$Message)

    $script:PassedTests++
    Write-Host ("[PASS] {0}" -f $Message) -ForegroundColor Green
}

function Write-TestFailure {
    param(
        [string]$Message,
        [string]$Details = ""
    )

    $script:FailedTests++

    Write-Host ("[FAIL] {0}" -f $Message) -ForegroundColor Red

    if (-not [string]::IsNullOrWhiteSpace($Details)) {
        Write-Host ("       {0}" -f $Details) -ForegroundColor DarkRed
    }
}

function Write-TestSkipped {
    param([string]$Message)

    $script:SkippedTests++
    Write-Host ("[SKIP] {0}" -f $Message) -ForegroundColor Yellow
}

function Invoke-ApiRequest {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Method,

        [Parameter(Mandatory = $true)]
        [string]$Path,

        [hashtable]$Headers = @{},

        [AllowNull()]
        [object]$Body = $null
    )

    $requestParameters = @{
        Method          = $Method
        Uri             = "$BaseUrl$Path"
        Headers         = $Headers
        UseBasicParsing = $true
        TimeoutSec      = 10
        ErrorAction     = "Stop"
    }

    if ($null -ne $Body) {
        $requestParameters.ContentType = "application/json"
        $requestParameters.Body = $Body | ConvertTo-Json -Compress
    }

    try {
        $webResponse = Invoke-WebRequest @requestParameters

        return [PSCustomObject]@{
            StatusCode = [int]$webResponse.StatusCode
            Content    = $webResponse.Content
            Error      = $null
        }
    }
    catch {
        $exceptionResponse = $_.Exception.Response

        if ($null -eq $exceptionResponse) {
            return [PSCustomObject]@{
                StatusCode = 0
                Content    = ""
                Error      = $_.Exception.Message
            }
        }

        $responseContent = ""

        try {
            $responseStream = $exceptionResponse.GetResponseStream()

            if ($null -ne $responseStream) {
                $reader = New-Object System.IO.StreamReader($responseStream)

                try {
                    $responseContent = $reader.ReadToEnd()
                }
                finally {
                    $reader.Dispose()
                }
            }
        }
        catch {
            $responseContent = ""
        }

        return [PSCustomObject]@{
            StatusCode = [int]$exceptionResponse.StatusCode
            Content    = $responseContent
            Error      = $null
        }
    }
}

function Assert-StatusCode {
    param(
        [Parameter(Mandatory = $true)]
        [object]$Response,

        [Parameter(Mandatory = $true)]
        [int]$ExpectedStatus,

        [Parameter(Mandatory = $true)]
        [string]$TestName
    )

    if ($Response.StatusCode -eq $ExpectedStatus) {
        Write-TestSuccess $TestName
        return $true
    }

    $details = "Expected HTTP {0}, received HTTP {1}." -f `
        $ExpectedStatus,
        $Response.StatusCode

    if (-not [string]::IsNullOrWhiteSpace($Response.Error)) {
        $details = "$details $($Response.Error)"
    }

    Write-TestFailure -Message $TestName -Details $details

    return $false
}

function Convert-JsonResponse {
    param(
        [Parameter(Mandatory = $true)]
        [object]$Response,

        [Parameter(Mandatory = $true)]
        [string]$TestName
    )

    try {
        return $Response.Content | ConvertFrom-Json
    }
    catch {
        Write-TestFailure `
            -Message $TestName `
            -Details "The response does not contain valid JSON."

        return $null
    }
}

Write-Host ""
Write-Host "La Couisine API smoke tests" -ForegroundColor Cyan
Write-Host ("Target: {0}" -f $BaseUrl) -ForegroundColor DarkGray
Write-Host ""

# 1. Public recipes endpoint

$recipesResponse = Invoke-ApiRequest `
    -Method "GET" `
    -Path "/api/v1/recipes"

Assert-StatusCode `
    -Response $recipesResponse `
    -ExpectedStatus 200 `
    -TestName "Public recipes endpoint is accessible" | Out-Null

# 2. Invalid login

$invalidLoginResponse = Invoke-ApiRequest `
    -Method "POST" `
    -Path "/api/v1/auth/login" `
    -Body @{
        username = "__invalid_smoke_test_user__"
        password = "__invalid_smoke_test_password__"
    }

Assert-StatusCode `
    -Response $invalidLoginResponse `
    -ExpectedStatus 401 `
    -TestName "Invalid credentials are rejected" | Out-Null

# 3. Anonymous access to /me

$anonymousMeResponse = Invoke-ApiRequest `
    -Method "GET" `
    -Path "/api/v1/me"

Assert-StatusCode `
    -Response $anonymousMeResponse `
    -ExpectedStatus 401 `
    -TestName "Anonymous access to /me is rejected" | Out-Null

# 4. Check optional credentials

$hasUsername = -not [string]::IsNullOrWhiteSpace($Username)
$hasPassword = -not [string]::IsNullOrWhiteSpace($Password)

if ($hasUsername -xor $hasPassword) {
    Write-TestFailure `
        -Message "Authenticated tests configuration" `
        -Details "Username and Password must be provided together."
}
elseif (-not $hasUsername -and -not $hasPassword) {
    Write-TestSkipped "Authenticated login test"
    Write-TestSkipped "Authenticated /me test"
    Write-TestSkipped "Altered token test"
}
else {
    # 5. Valid login

    $loginResponse = Invoke-ApiRequest `
        -Method "POST" `
        -Path "/api/v1/auth/login" `
        -Body @{
            username = $Username
            password = $Password
        }

    $loginSucceeded = Assert-StatusCode `
        -Response $loginResponse `
        -ExpectedStatus 200 `
        -TestName "Valid credentials return HTTP 200"

    if ($loginSucceeded) {
        $loginData = Convert-JsonResponse `
            -Response $loginResponse `
            -TestName "Login response contains valid JSON"

        $token = $null

        if ($null -ne $loginData) {
            $token = $loginData.token
        }

        if ([string]::IsNullOrWhiteSpace($token)) {
            Write-TestFailure `
                -Message "Login response contains a JWT" `
                -Details "The token property is missing or empty."
        }
        else {
            Write-TestSuccess "Login response contains a JWT"

            # 6. Authenticated access to /me

            $authenticatedHeaders = @{
                Authorization = "Bearer $token"
            }

            $authenticatedMeResponse = Invoke-ApiRequest `
                -Method "GET" `
                -Path "/api/v1/me" `
                -Headers $authenticatedHeaders

            $meSucceeded = Assert-StatusCode `
                -Response $authenticatedMeResponse `
                -ExpectedStatus 200 `
                -TestName "Authenticated access to /me succeeds"

            if ($meSucceeded) {
                $meData = Convert-JsonResponse `
                    -Response $authenticatedMeResponse `
                    -TestName "/me response contains valid JSON"

                if (
                    $null -ne $meData `
                    -and $null -ne $meData.data `
                    -and -not [string]::IsNullOrWhiteSpace($meData.data.username)
                ) {
                    Write-TestSuccess "/me response contains user data"
                }
                else {
                    Write-TestFailure `
                        -Message "/me response contains user data" `
                        -Details "The data.username property is missing."
                }
            }

            # 7. Altered token

            $lastCharacter = $token.Substring($token.Length - 1)
            $replacementCharacter = if ($lastCharacter -eq "A") { "B" } else { "A" }

            $alteredToken = $token.Substring(0, $token.Length - 1) +
                $replacementCharacter

            $alteredTokenResponse = Invoke-ApiRequest `
                -Method "GET" `
                -Path "/api/v1/me" `
                -Headers @{
                    Authorization = "Bearer $alteredToken"
                }

            Assert-StatusCode `
                -Response $alteredTokenResponse `
                -ExpectedStatus 401 `
                -TestName "Altered JWT is rejected" | Out-Null
        }
    }
    else {
        Write-TestSkipped "JWT presence test"
        Write-TestSkipped "Authenticated /me test"
        Write-TestSkipped "Altered token test"
    }
}

Write-Host ""
Write-Host "Summary" -ForegroundColor Cyan
Write-Host ("Passed : {0}" -f $script:PassedTests) -ForegroundColor Green
Write-Host ("Failed : {0}" -f $script:FailedTests) -ForegroundColor Red
Write-Host ("Skipped: {0}" -f $script:SkippedTests) -ForegroundColor Yellow
Write-Host ""

if ($script:FailedTests -gt 0) {
    exit 1
}

exit 0