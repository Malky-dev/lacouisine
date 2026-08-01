param(
    [string]$BaseUrl = "http://localhost:8000",
    [string]$Username = "",
    [string]$Password = ""
)

$smokePath = Join-Path $PSScriptRoot "smoke"
. (Join-Path $smokePath "common.ps1")
. (Join-Path $smokePath "public_api.ps1")
. (Join-Path $smokePath "authentication.ps1")
. (Join-Path $smokePath "category_management.ps1")
. (Join-Path $smokePath "recipe_management.ps1")
. (Join-Path $smokePath "user_management.ps1")

Initialize-SmokeTests $BaseUrl
Write-Host "`nLa Couisine API smoke tests" -ForegroundColor Cyan
Write-Host "Target: $script:BaseUrl`n" -ForegroundColor DarkGray
Invoke-PublicApiSmokeTests
Invoke-AuthenticationSmokeTests $Username $Password
Invoke-CategoryManagementSmokeTests $Username $Password
Invoke-RecipeManagementSmokeTests $Username $Password
Invoke-UserManagementSmokeTests $Username $Password
Complete-SmokeTests
exit 0

<# Legacy monolithic implementation retained temporarily for comparison.

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

$recipesAvailable = Assert-StatusCode `
    -Response $recipesResponse `
    -ExpectedStatus 200 `
    -TestName "Public recipes endpoint is accessible"

if ($recipesAvailable) {
    $recipesData = Convert-JsonResponse `
        -Response $recipesResponse `
        -TestName "Recipes response contains valid JSON"

    if (
        $null -ne $recipesData `
        -and $null -ne $recipesData.data `
        -and $null -ne $recipesData.meta `
        -and $null -ne $recipesData.meta.page `
        -and $null -ne $recipesData.meta.perPage `
        -and $null -ne $recipesData.meta.total `
        -and $null -ne $recipesData.meta.lastPage
    ) {
        Write-TestSuccess "Recipes response follows the V1 contract"
    }
    else {
        Write-TestFailure `
            -Message "Recipes response follows the V1 contract" `
            -Details "Expected data and pagination metadata are missing."
    }

    $nonPublicRecipes = @(
        $recipesData.data | Where-Object { $_.visibility -ne "public" }
    )

    if ($nonPublicRecipes.Count -eq 0) {
        Write-TestSuccess "Public recipes endpoint only exposes public recipes"
    }
    else {
        Write-TestFailure `
            -Message "Public recipes endpoint only exposes public recipes" `
            -Details "At least one non-public recipe was returned."
    }
}

# 2. Public categories endpoints

$categoriesResponse = Invoke-ApiRequest `
    -Method "GET" `
    -Path "/api/v1/categories"

$categoriesAvailable = Assert-StatusCode `
    -Response $categoriesResponse `
    -ExpectedStatus 200 `
    -TestName "Public categories endpoint is accessible"

if ($categoriesAvailable) {
    $categoriesData = Convert-JsonResponse `
        -Response $categoriesResponse `
        -TestName "Categories response contains valid JSON"

    if (
        $null -ne $categoriesData `
        -and $null -ne $categoriesData.data `
        -and $null -ne $categoriesData.meta `
        -and $null -ne $categoriesData.meta.page `
        -and $null -ne $categoriesData.meta.perPage `
        -and $null -ne $categoriesData.meta.total `
        -and $null -ne $categoriesData.meta.lastPage
    ) {
        Write-TestSuccess "Categories response follows the V1 contract"
    }
    else {
        Write-TestFailure `
            -Message "Categories response follows the V1 contract" `
            -Details "Expected data and pagination metadata are missing."
    }

    if ($null -ne $categoriesData -and $categoriesData.data.Count -gt 0) {
        $categorySlug = $categoriesData.data[0].slug
        $categoryResponse = Invoke-ApiRequest `
            -Method "GET" `
            -Path "/api/v1/categories/$categorySlug"

        $categoryAvailable = Assert-StatusCode `
            -Response $categoryResponse `
            -ExpectedStatus 200 `
            -TestName "Public category detail is accessible"

        if ($categoryAvailable) {
            $categoryData = Convert-JsonResponse `
                -Response $categoryResponse `
                -TestName "Category detail contains valid JSON"

            if (
                $null -ne $categoryData.data `
                -and $categoryData.data.slug -eq $categorySlug `
                -and $null -ne $categoryData.data.recipeCount
            ) {
                Write-TestSuccess "Category detail follows the V1 contract"
            }
            else {
                Write-TestFailure `
                    -Message "Category detail follows the V1 contract" `
                    -Details "Expected category data is missing."
            }
        }
    }
    else {
        Write-TestSkipped "Public category detail test"
    }
}

function Assert-ApiError {
    param(
        [Parameter(Mandatory = $true)]
        [object]$Response,

        [Parameter(Mandatory = $true)]
        [string]$ExpectedCode,

        [Parameter(Mandatory = $true)]
        [string]$TestName
    )

    try {
        $errorData = $Response.Content | ConvertFrom-Json
    }
    catch {
        Write-TestFailure `
            -Message $TestName `
            -Details "The error response does not contain valid JSON."

        return
    }

    if (
        $null -ne $errorData.error `
        -and $errorData.error.code -eq $ExpectedCode `
        -and -not [string]::IsNullOrWhiteSpace($errorData.error.message)
    ) {
        Write-TestSuccess $TestName
        return
    }

    Write-TestFailure `
        -Message $TestName `
        -Details ("Expected error.code to equal {0}." -f $ExpectedCode)
}

# 3. Invalid login

$invalidLoginResponse = Invoke-ApiRequest `
    -Method "POST" `
    -Path "/api/v1/auth/login" `
    -Body @{
        username = "__invalid_smoke_test_user__"
        password = "__invalid_smoke_test_password__"
    }

$invalidLoginRejected = Assert-StatusCode `
    -Response $invalidLoginResponse `
    -ExpectedStatus 401 `
    -TestName "Invalid credentials are rejected"

if ($invalidLoginRejected) {
    Assert-ApiError `
        -Response $invalidLoginResponse `
        -ExpectedCode "INVALID_CREDENTIALS" `
        -TestName "Invalid login follows the error contract"
}

# 4. Anonymous access to /me

$anonymousMeResponse = Invoke-ApiRequest `
    -Method "GET" `
    -Path "/api/v1/me"

$anonymousMeRejected = Assert-StatusCode `
    -Response $anonymousMeResponse `
    -ExpectedStatus 401 `
    -TestName "Anonymous access to /me is rejected"

if ($anonymousMeRejected) {
    Assert-ApiError `
        -Response $anonymousMeResponse `
        -ExpectedCode "UNAUTHORIZED" `
        -TestName "Anonymous access follows the error contract"
}

# 5. API exception contract

$missingRecipeResponse = Invoke-ApiRequest `
    -Method "GET" `
    -Path "/api/v1/recipes/2147483647"

$missingRecipeRejected = Assert-StatusCode `
    -Response $missingRecipeResponse `
    -ExpectedStatus 404 `
    -TestName "Missing recipe returns HTTP 404"

if ($missingRecipeRejected) {
    Assert-ApiError `
        -Response $missingRecipeResponse `
        -ExpectedCode "RESOURCE_NOT_FOUND" `
        -TestName "Missing recipe follows the error contract"
}

$unknownRouteResponse = Invoke-ApiRequest `
    -Method "GET" `
    -Path "/api/v1/__unknown_smoke_test_route__"

$unknownRouteRejected = Assert-StatusCode `
    -Response $unknownRouteResponse `
    -ExpectedStatus 404 `
    -TestName "Unknown API route returns HTTP 404"

if ($unknownRouteRejected) {
    Assert-ApiError `
        -Response $unknownRouteResponse `
        -ExpectedCode "RESOURCE_NOT_FOUND" `
        -TestName "Unknown API route follows the error contract"
}

$missingCategoryResponse = Invoke-ApiRequest `
    -Method "GET" `
    -Path "/api/v1/categories/missing-smoke-test-category"

$missingCategoryRejected = Assert-StatusCode `
    -Response $missingCategoryResponse `
    -ExpectedStatus 404 `
    -TestName "Missing category returns HTTP 404"

if ($missingCategoryRejected) {
    Assert-ApiError `
        -Response $missingCategoryResponse `
        -ExpectedCode "RESOURCE_NOT_FOUND" `
        -TestName "Missing category follows the error contract"
}

# 6. Check optional credentials

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
    # 7. Valid login

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

            # 8. Authenticated access to /me

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

            # 9. Altered token

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

            $alteredTokenRejected = Assert-StatusCode `
                -Response $alteredTokenResponse `
                -ExpectedStatus 401 `
                -TestName "Altered JWT is rejected"

            if ($alteredTokenRejected) {
                Assert-ApiError `
                    -Response $alteredTokenResponse `
                    -ExpectedCode "INVALID_TOKEN" `
                    -TestName "Altered JWT follows the error contract"
            }
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
#>
