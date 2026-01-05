# JWT Autentikáció Telepítési Útmutató

Az alkalmazás Laravel Sanctum helyett most **JWT (JSON Web Token)** autentikációt használ a `tymon/jwt-auth` package-dzsel.

## Telepítési Lépések

### 1. Composer Package Telepítése

```bash
composer install
# vagy
composer update
```

Ez automatikusan telepíti a `tymon/jwt-auth` package-t a composer.json alapján.

### 2. JWT Secret Kulcs Generálása

```bash
php artisan jwt:secret
```

Ez a parancs egy egyedi JWT secret kulcsot generál és hozzáadja a `.env` fájlhoz.

### 3. .env Fájl Beállítása

Ellenőrizd, hogy a következő JWT konfigurációk szerepelnek a `.env` fájlodban:

```env
JWT_SECRET=<generált-kulcs>
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256
JWT_BLACKLIST_ENABLED=true
```

### 4. Cache Törlése

```bash
php artisan config:clear
php artisan cache:clear
```

### 5. Migrációk Futtatása

```bash
php artisan migrate
```

### 6. Adatbázis Feltöltése (opcionális)

```bash
php artisan db:seed
```

## Fő Változások Bearer Token → JWT

### 1. User Model
- Eltávolítva: `HasApiTokens` trait
- Hozzáadva: `JWTSubject` interface implementáció
- Új metódusok: `getJWTIdentifier()`, `getJWTCustomClaims()`

### 2. AuthController
- `login()`: JWT token generálás `JWTAuth::attempt()` segítségével
- `logout()`: Token invalidálás `JWTAuth::invalidate()`
- `refresh()`: Új endpoint token frissítéshez
- `expires_in`: Token lejárati idő a válaszban

### 3. Auth Config
- Új `api` guard JWT driver-rel
- Default guard változtatható `api`-ra

### 4. API Routes
- `auth:sanctum` → `auth:api` middleware
- Új `/refresh` endpoint token frissítéshez

### 5. Middleware
- `IsAdmin`: JWT exception handling hozzáadva

### 6. Tesztek
- Sanctum token generálás helyett: `JWTAuth::fromUser($user)`
- Frissített assertion-ök `expires_in` mezővel

## API Használat

### Login
```http
POST /api/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "password"
}
```

**Válasz:**
```json
{
  "message": "Login successful",
  "user": {...},
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### Védett Endpoint Hívás
```http
GET /api/me
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

### Token Frissítés
```http
POST /api/refresh
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Válasz:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### Logout
```http
POST /api/logout
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

## JWT Token Struktúra

A JWT token tartalmazza:
- **sub**: User ID
- **iat**: Token kiadásának időpontja
- **exp**: Token lejárati időpontja
- **jti**: Egyedi token azonosító
- **is_admin**: Custom claim - admin jogosultság (true/false)

## Előnyök JWT vs Sanctum

✅ **Stateless**: Nem igényel adatbázis lekérdezést minden kérésnél
✅ **Token lejárat**: Automatikus 60 perces TTL (konfigurálható)
✅ **Token frissítés**: Lehetőség token-ek frissítésére kijelentkezés nélkül
✅ **Blacklist**: Tokenek invalidálása logout-kor
✅ **Custom claims**: is_admin jogosultság beépítve a tokenbe
✅ **Szélesebb támogatás**: Mobilalkalmazások, microservices

## Tesztelés

```bash
# Összes teszt futtatása
php artisan test

# Csak auth tesztek
php artisan test --filter=AuthApiTest

# Verbose kimenet
php artisan test --verbose
```

## Hibaelhárítás

### "Class 'Tymon\JWTAuth\...' not found"
```bash
composer dump-autoload
php artisan config:clear
```

### "Token could not be parsed from the request"
Ellenőrizd az Authorization header formátumát: `Bearer <token>`

### "The token has been blacklisted"
A token már invalidálva lett (logout után). Új login szükséges.

### "Token Signature could not be verified"
JWT_SECRET megváltozott. Új token generálás szükséges.

## Migrálás Meglévő Projectről

Ha már fut a Sanctum verzió:

1. Futtasd le a composer update-et
2. Generálj JWT secret-et: `php artisan jwt:secret`
3. Töröld a meglévő personal_access_tokens tábla tartalmát (opcionális)
4. Minden kliens újra login szükséges az új JWT tokenekért

## További Információk

- JWT dokumentáció: https://jwt-auth.readthedocs.io/
- Laravel Auth: https://laravel.com/docs/authentication
