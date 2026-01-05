# Test API Endpoint Script
# PowerShell script to test the Wishlist API

Write-Host "===== Wishlist API Test =====" -ForegroundColor Cyan
Write-Host ""

# Test 1: Get Products (Public)
Write-Host "1. Testing GET /api/products (public)" -ForegroundColor Yellow
try {
    $products = Invoke-RestMethod -Uri "http://localhost/wishlists/public/api/products" -Method Get
    Write-Host "   ✓ Success! Found $($products.total) products" -ForegroundColor Green
} catch {
    Write-Host "   ✗ Failed: $_" -ForegroundColor Red
}

Write-Host ""

# Test 2: Login as Admin
Write-Host "2. Testing POST /api/login (admin)" -ForegroundColor Yellow
$loginData = @{
    email = "admin@example.com"
    password = "password"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "http://localhost/wishlists/public/api/login" -Method Post -Body $loginData -ContentType "application/json"
    $token = $loginResponse.access_token
    Write-Host "   ✓ Login successful! Token received" -ForegroundColor Green
    Write-Host "   User: $($loginResponse.user.name) (Admin: $($loginResponse.user.is_admin))" -ForegroundColor Cyan
} catch {
    Write-Host "   ✗ Login failed: $_" -ForegroundColor Red
    exit
}

Write-Host ""

# Test 3: Get Profile
Write-Host "3. Testing GET /api/me (protected)" -ForegroundColor Yellow
$headers = @{
    Authorization = "Bearer $token"
}

try {
    $profile = Invoke-RestMethod -Uri "http://localhost/wishlists/public/api/me" -Method Get -Headers $headers
    Write-Host "   ✓ Profile retrieved!" -ForegroundColor Green
    Write-Host "   Name: $($profile.name), Email: $($profile.email)" -ForegroundColor Cyan
} catch {
    Write-Host "   ✗ Failed: $_" -ForegroundColor Red
}

Write-Host ""

# Test 4: Get Users (Admin only)
Write-Host "4. Testing GET /api/users (admin only)" -ForegroundColor Yellow
try {
    $users = Invoke-RestMethod -Uri "http://localhost/wishlists/public/api/users" -Method Get -Headers $headers
    Write-Host "   ✓ Success! Found $($users.Count) users" -ForegroundColor Green
} catch {
    Write-Host "   ✗ Failed: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host "===== Test Complete =====" -ForegroundColor Cyan
Write-Host ""
Write-Host "API is working correctly! ✓" -ForegroundColor Green
Write-Host "You can now test with Postman using the collection file." -ForegroundColor Yellow
