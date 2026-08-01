function Invoke-PublicApiSmokeTests {
    $anonymousUsers = Invoke-ApiRequest "GET" "/api/v1/users"
    if (Assert-StatusCode $anonymousUsers 401 "Anonymous user listing is rejected") {
        Assert-ApiError $anonymousUsers "UNAUTHORIZED" "User management requires authentication"
    }

    $anonymousRecipe = Invoke-ApiRequest "POST" "/api/v1/recipes" -Body @{ title = "Unauthorized recipe"; content = "Unauthorized content"; categoryId = 1 }
    if (Assert-StatusCode $anonymousRecipe 401 "Anonymous recipe creation is rejected") {
        Assert-ApiError $anonymousRecipe "UNAUTHORIZED" "Recipe management requires authentication"
    }

    $anonymousCreate = Invoke-ApiRequest "POST" "/api/v1/categories" -Body @{ name = "Unauthorized smoke category" }
    if (Assert-StatusCode $anonymousCreate 401 "Anonymous category creation is rejected") {
        Assert-ApiError $anonymousCreate "UNAUTHORIZED" "Category management requires authentication"
    }

    $r=Invoke-ApiRequest "GET" "/api/v1/recipes";if(Assert-StatusCode $r 200 "Public recipes endpoint is accessible"){$d=Convert-JsonResponse $r "Recipes response contains valid JSON";if($null-ne$d.data-and$null-ne$d.meta.page-and$null-ne$d.meta.total){Write-TestSuccess "Recipes response follows the V1 contract"}else{Write-TestFailure "Recipes response follows the V1 contract"};if(@($d.data|Where-Object{$_.visibility-ne"public"}).Count-eq 0){Write-TestSuccess "Public recipes endpoint only exposes public recipes"}else{Write-TestFailure "Public recipes endpoint only exposes public recipes"}}
    $r=Invoke-ApiRequest "GET" "/api/v1/categories";if(Assert-StatusCode $r 200 "Public categories endpoint is accessible"){$d=Convert-JsonResponse $r "Categories response contains valid JSON";if($null-ne$d.data-and$null-ne$d.meta.page-and$null-ne$d.meta.total){Write-TestSuccess "Categories response follows the V1 contract"}else{Write-TestFailure "Categories response follows the V1 contract"};if($d.data.Count-gt 0){$slug=$d.data[0].slug;$detail=Invoke-ApiRequest "GET" "/api/v1/categories/$slug";if(Assert-StatusCode $detail 200 "Public category detail is accessible"){$item=Convert-JsonResponse $detail "Category detail contains valid JSON";if($item.data.slug-eq$slug-and$null-ne$item.data.recipeCount){Write-TestSuccess "Category detail follows the V1 contract"}else{Write-TestFailure "Category detail follows the V1 contract"}}}else{Write-TestSkipped "Public category detail test"}}
    foreach($case in @(@{P="/api/v1/recipes/2147483647";N="Missing recipe"},@{P="/api/v1/__unknown_smoke_test_route__";N="Unknown API route"},@{P="/api/v1/categories/missing-smoke-test-category";N="Missing category"})){$r=Invoke-ApiRequest "GET" $case.P;if(Assert-StatusCode $r 404 "$($case.N) returns HTTP 404"){Assert-ApiError $r "RESOURCE_NOT_FOUND" "$($case.N) follows the error contract"}}
}
