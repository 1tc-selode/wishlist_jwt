# Wishlist REST API – Teljes Dokumentáció (JWT Authentication)

Ez a dokumentáció a Laravel alapú wishlist (kívánságlista) backend API teljes fejlesztési útmutatója. A projekt célja egy olyan REST API létrehozása, amely lehetővé teszi a felhasználók számára termékek hozzáadását a saját kívánságlistájukhoz, termékek kezelését, és egy teljes körű admin felületet biztosít. Az autentikáció JWT (JSON Web Token) alapú.

---

## Projekt Áttekintés

**Base URL-ek:**
- XAMPP: `http://localhost/wishlist/public/api`
- Laravel serve: `http://127.0.0.1:8000/api`
- Aktuális projekt: `http://localhost/wishlist/public/api`

**Technológiák:**
- Laravel 12
- JWT Auth (tymon/jwt-auth) - JSON Web Token autentikáció
- MySQL adatbázis
- PHPUnit tesztelés

**Adatbázis neve:** `wishlists`

---

## Projekt Struktúra

```
wishlist_auth/
├── app/
│   ├── Http/
│   │   ├── Controllers/         # API vezérlők
│   │   │   ├── Controller.php   # Alap vezérlő
│   │   │   └── Api/             # API vezérlők mappája
│   │   │       ├── AuthController.php      # Autentikáció
│   │   │       ├── ProductController.php   # Termékek kezelése
│   │   │       ├── UserController.php      # Felhasználó kezelés
│   │   │       └── WishlistController.php  # Kívánságlista kezelés
│   │   └── Middleware/          # Middleware-ek
│   ├── Models/                  # Adatmodellek
│   │   ├── User.php             # Felhasználó modell
│   │   ├── Product.php          # Termék modell
│   │   └── Wishlist.php         # Kívánságlista modell
│   └── Providers/               # Laravel szolgáltatók
├── database/
│   ├── factories/               # Tesztadat generátorok
│   │   ├── UserFactory.php
│   │   ├── ProductFactory.php
│   │   └── WishlistFactory.php
│   ├── migrations/              # Adatbázis szerkezet
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2025_12_01_081529_create_personal_access_tokens_table.php
│   │   ├── 2025_12_01_081600_create_products_table.php
│   │   └── 2025_12_01_081707_create_wishlists_table.php
│   └── seeders/                 # Tesztadat feltöltés
│       ├── DatabaseSeeder.php
│       ├── UserSeeder.php
│       ├── ProductSeeder.php
│       └── WishlistSeeder.php
├── routes/
│   └── api.php                  # API útvonalak
├── tests/
│   └── Feature/                 # API funkció tesztek
│       ├── AuthApiTest.php
│       ├── ProductApiTest.php
│       ├── UserApiTest.php
│       └── WishlistApiTest.php
├── docs/                        # Dokumentáció
│   ├── API_TEST_ENDPOINTS.md
│   ├── Wishlist_API.postman_collection.json
│   └── wishlist_dokumentacio.md
├── test_api.ps1                 # PowerShell teszt script
├── composer.json                # Composer konfiguráció
├── package.json                 # NPM konfiguráció
├── phpunit.xml                  # PHPUnit teszt konfiguráció
└── vite.config.js               # Vite frontend build
```

---

## Adatbázis Terv

```
+---------------------+       +-----------------+        +------------+
|        users        |       |   wishlists     |        |  products  |
+---------------------+       +-----------------+        +------------+
| id (PK)             |1__    | id (PK)         |     __1| id (PK)    |
| name                |   \__N| user_id (FK)    |    /   | name       |
| email (unique)      |       | product_id (FK) |M__/    | category   |
| password            |       | added_at        |        | price      |
| is_admin (boolean)  |       | created_at      |        | stock      |
| email_verified_at   |       | updated_at      |        | created_at |
| remember_token      |       | deleted_at      |        | updated_at |
| created_at          |       +-----------------+        | deleted_at |
| updated_at          |                                  +------------+
| deleted_at          |
+---------------------+

Megjegyzés: JWT tokenek nem kerülnek tárolásra az adatbázisban,
stateless autentikációt biztosítanak aláírt tokenekkel.
```

### Kapcsolatok:
- **User -> Wishlist:** 1:N (egy felhasználónak több kívánságlistája lehet)
- **Product -> Wishlist:** 1:N (egy terméket több felhasználó is kívánhat)
- **User -> Product:** N:M (many-to-many kapcsolat a wishlists táblán keresztül)
- **Egyedi megszorítás:** user_id + product_id kombinációja egyedi (egy user nem adhatja hozzá kétszer ugyanazt a terméket)
- **Soft Delete:** Minden táblában deleted_at mező biztosítja a logikai törlést

---

## I. Modul - Telepítés és Konfiguráció

### 1. Laravel Projekt Létrehozása

```powershell
# Projekt létrehozása
composer create-project laravel/laravel --prefer-dist wishlist_auth

# Könyvtár váltás
cd wishlist_auth
```

### 2. .env Fájl Konfiguráció

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wishlists
DB_USERNAME=root
DB_PASSWORD=

# JWT konfiguráció
JWT_SECRET=your_secret_key_here
JWT_TTL=60
JWT_REFRESH_TTL=20160
JWT_ALGO=HS256
```

### 3. JWT Auth Telepítése

```powershell
# JWT Auth csomag telepítése
composer require tymon/jwt-auth

# JWT konfigurációs fájl publikálása
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"

# JWT secret kulcs generálása
php artisan jwt:secret
```

**User Model frissítése (app/Models/User.php):**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tymon\JWTAuth\Contracts\JWTSubject;  // JWT interface

class User extends Authenticatable implements JWTSubject  // JWT implementálás
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    // JWT szükséges metódusok
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistedProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlists')
                    ->withTimestamps()
                    ->withPivot('added_at');
    }
}
```

**config/auth.php frissítése:**

```php
'defaults' => [
    'guard' => 'api',  // Alapértelmezett guard megváltoztatása
    'passwords' => 'users',
],

'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'jwt',  // JWT driver
        'provider' => 'users',
    ],
],
```

### 4. Admin Middleware Létrehozása

```powershell
php artisan make:middleware IsAdmin
```

#### app/Http/Middleware/IsAdmin.php

```php
<?php

namespace App\Http\Middleware;

// Importáljuk a szükséges osztályokat
use Closure;                              // Closure típus (middleware következő lépése)
use Illuminate\Http\Request;             // HTTP kérés kezelése
use Symfony\Component\HttpFoundation\Response;  // HTTP válasz konstansok

/**
 * IsAdmin Middleware - Admin jogosultság ellenőrzése
 * Ez a middleware ellenőrzi, hogy a bejelentkezett felhasználó admin-e
 * JWT alapú autentikáció esetén az auth('api')->user() adja vissza a felhasználót
 */
class IsAdmin
{
    /**
     * Bejövő kérés kezelése
     * 
     * @param Request $request - HTTP kérés objektum
     * @param Closure $next - Következő middleware vagy controller
     * @return Response - HTTP válasz
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ellenőrizzük, hogy a felhasználó be van-e jelentkezve ÉS admin-e
        // $request->user() - JWT által autentikált felhasználó
        // is_admin - boolean mező a users táblában
        
        if (!$request->user() || !$request->user()->is_admin) {
            // Ha nincs bejelentkezve VAGY nem admin
            // -> 403 Forbidden válasz JSON üzenettel
            return response()->json([
                'message' => 'This action is unauthorized.'  // Jogosulatlan művelet
            ], 403);  // 403 Forbidden HTTP státusz
        }

        // Ha admin, engedjük tovább a kérést a következő middleware-hez vagy controllerhez
        return $next($request);
    }
}
```

**Middleware regisztrálása (bootstrap/app.php):**

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Admin middleware aliasának regisztrálása
        // Ezután használható: Route::middleware('admin')
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

### 5. Első Teszt

```powershell
# Laravel szerver indítása
php artisan serve

# POSTMAN teszt (publikus végpont)
# GET http://localhost/wishlist/public/api/products
# Headers: Accept: application/json
```

---

## II. Modul - Adatbázis és Modellek

### 1. Modellek és Migrációk Létrehozása

```powershell
# Product modell és migráció létrehozása
php artisan make:model Product -m

# Wishlist modell és migráció létrehozása
php artisan make:model Wishlist -m
```

**Megjegyzés:** A User modell már létezik a Laravel alaptelepítésében.

### 2. Migrációk Konfigurálása

#### users tábla (0001_01_01_000000_create_users_table.php)

```php
<?php

// Importáljuk a szükséges osztályokat
use Illuminate\Database\Migrations\Migration;  // Migráció alap osztály
use Illuminate\Database\Schema\Blueprint;      // Tábla szerkezet definiálásához
use Illuminate\Support\Facades\Schema;         // Adatbázis séma kezeléshez

// Névtelen osztály a migrációhoz
return new class extends Migration
{
    /**
     * Migráció futtatása - táblák létrehozása
     * JWT esetén NEM kell personal_access_tokens tábla,
     * mert a tokenek stateless módon működnek (aláírt, nem tárolt)
     */
    public function up(): void
    {
        // Users tábla létrehozása - felhasználók tárolása
        Schema::create('users', function (Blueprint $table) {
            $table->id();                                      // Primary key (auto increment)
            $table->string('name');                            // Felhasználó neve
            $table->string('email')->unique();                 // Email cím (egyedi)
            $table->timestamp('email_verified_at')->nullable(); // Email megerősítés időpontja
            $table->string('password');                        // Titkosított jelszó
            $table->boolean('is_admin')->default(false);       // Admin jogosultság (alapértelmezett: false)
            $table->rememberToken();                           // "Remember me" token
            $table->timestamps();                              // created_at és updated_at mezők
            $table->softDeletes();                             // deleted_at mező (soft delete támogatás)
        });

        // Jelszó visszaállítási tokenek táblája
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();               // Email cím (primary key)
            $table->string('token');                          // Visszaállítási token
            $table->timestamp('created_at')->nullable();      // Token létrehozás időpontja
        });

        // Session-ök tárolása (bejelentkezett felhasználók munkamenetei)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();                  // Session ID (primary key)
            $table->foreignId('user_id')->nullable()->index(); // Felhasználó ID (nullable, indexelt)
            $table->string('ip_address', 45)->nullable();     // IP cím (IPv4/IPv6)
            $table->text('user_agent')->nullable();           // Böngésző információ
            $table->longText('payload');                      // Session adatok
            $table->integer('last_activity')->index();        // Utolsó aktivitás időbélyeg (indexelt)
        });
    }

    /**
     * Migráció visszavonása - táblák törlése
     */
    public function down(): void
    {
        Schema::dropIfExists('users');                        // Users tábla törlése
        Schema::dropIfExists('password_reset_tokens');        // Password reset tábla törlése
        Schema::dropIfExists('sessions');                     // Sessions tábla törlése
    }
};
```

#### products tábla (2025_12_01_081600_create_products_table.php)

```php
<?php

// Importáljuk a szükséges osztályokat
use Illuminate\Database\Migrations\Migration;  // Migráció alap osztály
use Illuminate\Database\Schema\Blueprint;      // Tábla szerkezet definiálásához
use Illuminate\Support\Facades\Schema;         // Adatbázis séma kezeléshez

// Névtelen osztály a migrációhoz
return new class extends Migration
{
    /**
     * Migráció futtatása - products tábla létrehozása
     */
    public function up(): void
    {
        // Products tábla létrehozása - termékek tárolása
        Schema::create('products', function (Blueprint $table) {
            $table->id();                          // Primary key (auto increment)
            $table->string('name');                // Termék neve
            $table->string('category');            // Termék kategóriája (pl: Electronics, Audio)
            $table->decimal('price', 10, 2);       // Ár (max 10 számjegy, 2 tizedesjegy)
            $table->integer('stock');              // Raktárkészlet (darabszám)
            $table->timestamps();                  // created_at és updated_at mezők
            $table->softDeletes();                 // deleted_at mező (soft delete támogatás)
        });
    }

    /**
     * Migráció visszavonása - products tábla törlése
     */
    public function down(): void
    {
        Schema::dropIfExists('products');          // Products tábla törlése
    }
};
```

#### wishlists tábla (2025_12_01_081707_create_wishlists_table.php)

```php
<?php

// Importáljuk a szükséges osztályokat
use Illuminate\Database\Migrations\Migration;  // Migráció alap osztály
use Illuminate\Database\Schema\Blueprint;      // Tábla szerkezet definiálásához
use Illuminate\Support\Facades\Schema;         // Adatbázis séma kezeléshez

// Névtelen osztály a migrációhoz
return new class extends Migration
{
    /**
     * Migráció futtatása - wishlists tábla létrehozása
     */
    public function up(): void
    {
        // Wishlists tábla létrehozása - kívánságlista kapcsolatok tárolása
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();                                                    // Primary key (auto increment)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Foreign key a users táblára, cascade delete
            $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // Foreign key a products táblára, cascade delete
            $table->timestamp('added_at')->useCurrent();                    // Hozzáadás időpontja (alapértelmezett: most)
            $table->timestamps();                                            // created_at és updated_at mezők
            $table->softDeletes();                                           // deleted_at mező (soft delete támogatás)
            
            // Unique constraint: egy user csak egyszer kedvelhet egy terméket
            // Ez megakadályozza, hogy ugyanaz a user kétszer adja hozzá ugyanazt a terméket
            $table->unique(['user_id', 'product_id']);
        });
    }

    /**
     * Migráció visszavonása - wishlists tábla törlése
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlists');                                  // Wishlists tábla törlése
    }
};
```

### 3. Modell Fájlok Konfigurálása

#### app/Models/User.php

```php
<?php

namespace App\Models;

// Importáljuk a szükséges osztályokat és trait-eket
use Illuminate\Database\Eloquent\Factories\HasFactory;  // Factory támogatás (tesztadatok generálása)
use Illuminate\Foundation\Auth\User as Authenticatable; // Laravel alap autentikációs osztály
use Illuminate\Notifications\Notifiable;                // Értesítések küldéséhez
use Illuminate\Database\Eloquent\SoftDeletes;          // Soft delete támogatás
use Tymon\JWTAuth\Contracts\JWTSubject;                // JWT interfész

/**
 * User modell - Felhasználók kezelése
 * Ez az osztály felel a felhasználók adatainak tárolásáért és kapcsolatairól
 * JWT implementálás: JWTSubject interfész implementálása
 * Soft delete: Törléskor csak megjelöli (deleted_at), nem törli fizikailag
 */
class User extends Authenticatable implements JWTSubject
{
    // Trait-ek használata (közös funkciók)
    use HasFactory,    // Factory használata tesztadatokhoz
        Notifiable,    // Értesítések küldése
        SoftDeletes;   // Soft delete (logikai törlés)

    /**
     * Tömegesen kitölthető mezők
     * Ezek a mezők értékadása engedélyezett create() és update() műveletek során
     */
    protected $fillable = [
        'name',        // Felhasználó neve
        'email',       // Email cím
        'password',    // Jelszó (titkosítva lesz tárolva)
        'is_admin',    // Admin jogosultság flag
    ];

    /**
     * Rejtett mezők - nem jelennek meg JSON-ben
     * Biztonsági okokból ezeket a mezőket elrejtjük az API válaszokban
     */
    protected $hidden = [
        'password',        // Jelszó sosem kerül visszaküldésre
        'remember_token',  // Remember me token sem
    ];

    /**
     * Mezők tipus konverziója (casting)
     * Meghatározza, hogy egyes mezők milyen típusra legyenek konvertálva
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',  // Időpont objektummá konvertálás
            'password' => 'hashed',             // Automatikus jelszó hash-elés
            'is_admin' => 'boolean',            // Boolean értékké konvertálás
        ];
    }

    /**
     * JWT szükséges metódusok
     * A JWTSubject interfész két metódust követel meg
     */
    
    /**
     * JWT azonosító lekérése
     * Ez lesz a token 'sub' (subject) claim-je
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();  // Primary key (id) visszaadása
    }

    /**
     * Egyéni JWT claim-ek
     * Itt lehet további adatokat hozzáadni a tokenhez
     */
    public function getJWTCustomClaims()
    {
        return [];  // Üres tömb - nincs extra claim
    }

    /**
     * Egy-sok kapcsolat: User -> Wishlist
     * Egy felhasználónak több kívánságlista bejegyzése lehet
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Sok-sok kapcsolat: User <-> Product (wishlists táblán keresztül)
     * A felhasználó kívánt termékei
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function wishlistedProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlists')  // Kapcsoló tábla: wishlists
                    ->withTimestamps()                             // created_at és updated_at mezők használata
                    ->withPivot('added_at');                      // added_at pivot mező hozzáférése
    }
}
```

#### app/Models/Product.php

```php
<?php

namespace App\Models;

// Importáljuk a szükséges osztályokat
use Illuminate\Database\Eloquent\Model;                 // Eloquent ORM alap osztály
use Illuminate\Database\Eloquent\Factories\HasFactory;  // Factory támogatás
use Illuminate\Database\Eloquent\SoftDeletes;          // Soft delete támogatás

/**
 * Product modell - Termékek kezelése
 * Ez az osztály felel a termékek adatainak tárolásáért és kapcsolatairól
 * Soft delete: Törléskor csak megjelöli (deleted_at), nem törli fizikailag
 */
class Product extends Model
{
    use HasFactory,   // Factory használata tesztadatok generálásához
        SoftDeletes;  // Soft delete (logikai törlés)

    /**
     * Tömegesen kitölthető mezők
     * Ezek a mezők értékadása engedélyezett create() és update() műveletek során
     */
    protected $fillable = [
        'name',        // Termék neve
        'category',    // Kategória (pl: Electronics, Audio)
        'price',       // Ár
        'stock',       // Raktárkészlet
    ];

    /**
     * Mezők tipus konverziója (casting)
     * Meghatározza, hogy egyes mezők milyen típusra legyenek konvertálva
     */
    protected $casts = [
        'price' => 'decimal:2',  // Ár: decimal formátum 2 tizedes jeggyel
        'stock' => 'integer',    // Raktárkészlet: egész szám
    ];

    /**
     * Egy-sok kapcsolat: Product -> Wishlist
     * Egy termék több kívánságlista bejegyzésben szerepelhet
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Sok-sok kapcsolat: Product <-> User (wishlists táblán keresztül)
     * Azok a felhasználók, akik a terméket kívánják
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function wishlistedByUsers()
    {
        return $this->belongsToMany(User::class, 'wishlists')  // Kapcsoló tábla: wishlists
                    ->withTimestamps()                          // created_at és updated_at mezők
                    ->withPivot('added_at');                   // added_at pivot mező hozzáférése
    }
}
```

#### app/Models/Wishlist.php

```php
<?php

namespace App\Models;

// Importáljuk a szükséges osztályokat
use Illuminate\Database\Eloquent\Model;                 // Eloquent ORM alap osztály
use Illuminate\Database\Eloquent\Factories\HasFactory;  // Factory támogatás
use Illuminate\Database\Eloquent\SoftDeletes;          // Soft delete támogatás

/**
 * Wishlist modell - Kívánságlista kapcsolatok kezelése
 * Ez a pivot (kapcsoló) modell kezeli a User és Product közöttiMany-to-Many kapcsolatot
 * Soft delete: Törléskor csak megjelöli (deleted_at), nem törli fizikailag
 */
class Wishlist extends Model
{
    use HasFactory,   // Factory használata tesztadatok generálásához
        SoftDeletes;  // Soft delete (logikai törlés)

    /**
     * Tömegesen kitölthető mezők
     * Ezek a mezők értékadása engedélyezett create() és update() műveletek során
     */
    protected $fillable = [
        'user_id',      // Felhasználó azonosító (foreign key)
        'product_id',   // Termék azonosító (foreign key)
        'added_at',     // Hozzáadás időpontja
    ];

    /**
     * Mezők tipus konverziója (casting)
     * Meghatározza, hogy egyes mezők milyen típusra legyenek konvertálva
     */
    protected $casts = [
        'added_at' => 'datetime',  // added_at mező datetime objektummá konvertálása
    ];

    /**
     * Fordított egy-sok kapcsolat: Wishlist -> User
     * Egy wishlist bejegyzés egy felhasználóhoz tartozik
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);  // Hivatkozás a users táblára
    }

    /**
     * Fordított egy-sok kapcsolat: Wishlist -> Product
     * Egy wishlist bejegyzés egy termékhez tartozik
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);  // Hivatkozás a products táblára
    }
}
```

### 4. Migráció Futtatása

```powershell
php artisan migrate
```

---

## III. Modul - Seeding (Tesztadatok)

### 1. Factory-k és Seederek Létrehozása

```powershell
# Factory-k létrehozása
php artisan make:factory ProductFactory
php artisan make:factory WishlistFactory

# Seederek létrehozása
php artisan make:seeder UserSeeder
php artisan make:seeder ProductSeeder
php artisan make:seeder WishlistSeeder
```

### 2. Seederek Konfigurálása

#### database/seeders/UserSeeder.php

```php
<?php

namespace Database\Seeders;

// Importáljuk a szükséges osztályokat
use Illuminate\Database\Seeder;           // Seeder alap osztály
use App\Models\User;                      // User modell
use Illuminate\Support\Facades\Hash;      // Jelszó hash-eléshez

/**
 * UserSeeder - Felhasználók adatbázisba töltése
 * Ez a seeder teszt felhasználókat hoz létre az adatbázisban
 */
class UserSeeder extends Seeder
{
    /**
     * Seeder futtatása
     * Létrehoz egy admin és néhány normál felhasználót
     */
    public function run(): void
    {
        // Admin felhasználó létrehozása
        User::create([
            'name' => 'Admin User',                   // Név
            'email' => 'admin@example.com',           // Email (egyedi)
            'password' => Hash::make('password'),     // Jelszó: "password" (hash-elve)
            'is_admin' => true,                       // Admin jogosultság: IGEN
        ]);

        // Első normál felhasználó létrehozása
        User::create([
            'name' => 'John Doe',                     // Név
            'email' => 'john@example.com',            // Email (egyedi)
            'password' => Hash::make('password'),     // Jelszó: "password" (hash-elve)
            'is_admin' => false,                      // Admin jogosultság: NEM
        ]);

        // Második normál felhasználó létrehozása
        User::create([
            'name' => 'Jane Smith',                   // Név
            'email' => 'jane@example.com',            // Email (egyedi)
            'password' => Hash::make('password'),     // Jelszó: "password" (hash-elve)
            'is_admin' => false,                      // Admin jogosultság: NEM
        ]);

        // További 7 véletlen felhasználó generálása factory-val
        // A factory véletlen adatokat generál (faker library használatával)
        User::factory(7)->create();
    }
}
```

#### database/seeders/ProductSeeder.php

```php
<?php

namespace Database\Seeders;

// Importáljuk a szükséges osztályokat
use Illuminate\Database\Seeder;  // Seeder alap osztály
use App\Models\Product;          // Product modell

/**
 * ProductSeeder - Termékek adatbázisba töltése
 * Ez a seeder teszt termékeket hoz létre az adatbázisban
 */
class ProductSeeder extends Seeder
{
    /**
     * Seeder futtatása
     * Létrehoz kézi és generált termékeket
     */
    public function run(): void
    {
        // Konkrét termékek definiálása tömbben
        $products = [
            [
                'name' => 'Laptop Dell XPS 15',       // Termék neve
                'category' => 'Electronics',          // Kategória
                'price' => 1299.99,                   // Ár (USD)
                'stock' => 15,                        // Raktárkészlet (db)
            ],
            [
                'name' => 'iPhone 15 Pro',
                'category' => 'Electronics',
                'price' => 999.99,
                'stock' => 25,
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'category' => 'Electronics',
                'price' => 899.99,
                'stock' => 30,
            ],
            [
                'name' => 'Sony WH-1000XM5',          // Fejhallgató
                'category' => 'Audio',
                'price' => 349.99,
                'stock' => 50,
            ],
            [
                'name' => 'iPad Pro 12.9',
                'category' => 'Electronics',
                'price' => 1099.99,
                'stock' => 20,
            ],
            [
                'name' => 'MacBook Pro 16',
                'category' => 'Electronics',
                'price' => 2499.99,
                'stock' => 10,
            ],
            [
                'name' => 'AirPods Pro',
                'category' => 'Audio',
                'price' => 249.99,
                'stock' => 100,
            ],
            [
                'name' => 'Samsung 4K Monitor',
                'category' => 'Electronics',
                'price' => 499.99,
                'stock' => 35,
            ],
        ];

        // Végigiterálunk a termékek tömbjén és mindegyiket létrehozzuk
        foreach ($products as $product) {
            Product::create($product);  // Termék mentése az adatbázisba
        }

        // További 20 véletlen termék generálása factory-val
        // A factory véletlen adatokat generál (faker library használatával)
        Product::factory(20)->create();
    }
}
```

#### database/seeders/WishlistSeeder.php

```php
<?php

namespace Database\Seeders;

// Importáljuk a szükséges osztályokat
use Illuminate\Database\Seeder;  // Seeder alap osztály
use App\Models\User;             // User modell
use App\Models\Product;          // Product modell
use App\Models\Wishlist;         // Wishlist modell

/**
 * WishlistSeeder - Kívánságlista kapcsolatok létrehozása
 * Ez a seeder véletlenszerű wishlist bejegyzéseket hoz létre
 */
class WishlistSeeder extends Seeder
{
    /**
     * Seeder futtatása
     * Létrehoz kapcsolatokat felhasználók és termékek között
     */
    public function run(): void
    {
        // Összes felhasználó és termék lekérése
        $users = User::all();           // Összes user az adatbázisból
        $products = Product::all();     // Összes product az adatbázisból

        // Végigmegyünk minden felhasználón
        foreach ($users as $user) {
            // Véletlenszerű számú termék (1-5 közötti) hozzáadása a kívánságlistához
            $randomProducts = $products->random(rand(1, 5));
            
            // Minden kiválasztott termékhez létrehozunk egy wishlist bejegyzést
            foreach ($randomProducts as $product) {
                Wishlist::create([
                    'user_id' => $user->id,           // Felhasználó ID
                    'product_id' => $product->id,     // Termék ID
                    'added_at' => now(),              // Aktuális időpont
                ]);
            }
        }
    }
}
```

#### database/seeders/DatabaseSeeder.php

```php
<?php

namespace Database\Seeders;

// Importáljuk a Seeder alap osztályt
use Illuminate\Database\Seeder;

/**
 * DatabaseSeeder - Fő seeder osztály
 * Ez az osztály hívja meg az összes többi seedert a megfelelő sorrendben
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seeder futtatása
     * Az összes seeder meghívása helyes sorrendben
     */
    public function run(): void
    {
        // Seederek meghívása sorrendben
        // FONTOS: A sorrend számít a foreign key kapcsolatok miatt!
        $this->call([
            UserSeeder::class,       // 1. Először a felhasználók (nincs függősége)
            ProductSeeder::class,    // 2. Majd a termékek (nincs függősége)
            WishlistSeeder::class,   // 3. Végül a wishlists (függ userstől és productstól)
        ]);
    }
}
```

### 3. Seeding Futtatása

```powershell
php artisan db:seed
```

---

## IV. Modul - Controller-ek és API Végpontok

### 1. Controller-ek Létrehozása

```powershell
# API controller-ek létrehozása
php artisan make:controller Api/AuthController
php artisan make:controller Api/ProductController
php artisan make:controller Api/UserController
php artisan make:controller Api/WishlistController
```

### 2. AuthController Implementálása

#### app/Http/Controllers/Api/AuthController.php

```php
<?php

namespace App\Http\Controllers\Api;

// Importáljuk a szükséges osztályokat
use App\Http\Controllers\Controller;         // Alap controller osztály
use App\Models\User;                         // User modell
use Illuminate\Http\Request;                 // HTTP kérés kezelése
use Illuminate\Support\Facades\Hash;         // Jelszó hash-elés
use Illuminate\Support\Facades\Validator;    // Validáció
use Tymon\JWTAuth\Facades\JWTAuth;          // JWT Auth facade
use Tymon\JWTAuth\Exceptions\JWTException;   // JWT kivételek

/**
 * AuthController - Felhasználói autentikáció kezelése JWT-vel
 * Kezeli a regisztrációt, bejelentkezést, kijelentkezést, token frissítést
 */
class AuthController extends Controller
{
    /**
     * Új felhasználó regisztrálása
     * POST /api/register
     * 
     * @param Request $request - HTTP kérés objektum (tartalmazza a POST adatokat)
     * @return \Illuminate\Http\JsonResponse - JSON válasz
     */
    public function register(Request $request)
    {
        // Validációs szabályok definiálása
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',                    // Név: kötelező, string, max 255 karakter
            'email' => 'required|string|email|max:255|unique:users', // Email: kötelező, email formátum, egyedi
            'password' => 'required|string|min:8|confirmed',        // Jelszó: kötelező, min 8 karakter, megerősítés kell
            'is_admin' => 'sometimes|boolean',                      // Admin flag: opcionális, boolean
        ]);

        // Ha a validáció sikertelen, visszaadjuk a hibákat
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',      // Hibaüzenet
                'errors' => $validator->errors()      // Validációs hibák részletei
            ], 422);  // 422 Unprocessable Entity HTTP státusz
        }

        // Új felhasználó létrehozása az adatbázisban
        $user = User::create([
            'name' => $request->name,                              // Név
            'email' => $request->email,                            // Email
            'password' => Hash::make($request->password),          // Jelszó hash-elése (biztonság)
            'is_admin' => $request->input('is_admin', false),     // Admin flag (alapértelmezett: false)
        ]);

        // Sikeres regisztráció visszajelzése
        return response()->json([
            'message' => 'User registered successfully. Please login.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
            ],
        ], 201);  // 201 Created HTTP státusz
    }

    /**
     * Felhasználó bejelentkeztetése és JWT token létrehozása
     * POST /api/login
     * 
     * @param Request $request - HTTP kérés objektum (email és password)
     * @return \Illuminate\Http\JsonResponse - JSON válasz JWT tokennel
     */
    public function login(Request $request)
    {
        // Bejelentkezési adatok validációja
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',       // Email: kötelező, email formátum
            'password' => 'required',          // Jelszó: kötelező
        ]);

        // Ha a validáció sikertelen
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Credentials (email + password) lekérése
        $credentials = $request->only('email', 'password');

        try {
            // JWT token létrehozása az attempt metódussal
            // Az attempt ellenőrzi a credentials-t és JWT tokent generál
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'message' => 'Invalid credentials'  // Hibás email vagy jelszó
                ], 401);  // 401 Unauthorized HTTP státusz
            }
        } catch (JWTException $e) {
            // JWT kivétel kezelése (pl. token generálási hiba)
            return response()->json([
                'message' => 'Could not create token'
            ], 500);  // 500 Internal Server Error
        }

        // Bejelentkezett felhasználó lekérése (explicit api guard használata)
        $user = auth('api')->user();

        // Sikeres bejelentkezés visszajelzése JWT tokennel
        return response()->json([
            'message' => 'Login successful',
            'user' => $user,                           // Felhasználó adatai
            'access_token' => $token,                  // JWT access token
            'token_type' => 'Bearer',                  // Token típusa (Bearer a standard)
            'expires_in' => config('jwt.ttl') * 60     // Lejárati idő másodpercben (3600 sec)
        ]);
    }

    /**
     * Felhasználó kijelentkeztetése (JWT token invalidálása)
     * POST /api/logout
     * Middleware: auth:api (JWT autentikáció)
     * 
     * @param Request $request - HTTP kérés objektum
     * @return \Illuminate\Http\JsonResponse - JSON válasz
     */
    public function logout(Request $request)
    {
        try {
            // JWT token invalidálása (blacklist-re kerül)
            // A getToken() lekéri a request-ből a tokent
            // Az invalidate() a blacklist-re teszi
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return response()->json([
                'message' => 'Logged out successfully'
            ]);
        } catch (JWTException $e) {
            // JWT kivétel kezelése
            return response()->json([
                'message' => 'Failed to logout, please try again'
            ], 500);
        }
    }

    /**
     * Bejelentkezett felhasználó adatainak lekérése
     * GET /api/me
     * Middleware: auth:api
     * 
     * @param Request $request - HTTP kérés objektum
     * @return \Illuminate\Http\JsonResponse - Felhasználó adatai JSON-ben
     */
    public function me(Request $request)
    {
        // Az autentikált felhasználó adatainak visszaadása
        // auth('api')->user() explicit módon az api guard-ot használja
        return response()->json(auth('api')->user());
    }

    /**
     * JWT token frissítése
     * POST /api/refresh
     * Middleware: auth:api
     * 
     * @return \Illuminate\Http\JsonResponse - Új JWT token
     */
    public function refresh()
    {
        try {
            // Új JWT token generálása a régi alapján
            // A refresh() metódus automatikusan lekéri a régi tokent
            // és új tokent generál, a régit pedig blacklist-re teszi
            $token = JWTAuth::refresh(JWTAuth::getToken());
            
            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60  // JWT config-ból (60 perc = 3600 sec)
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Could not refresh token'
            ], 500);
        }
    }
}
```

### 3. ProductController Implementálása

#### app/Http/Controllers/Api/ProductController.php

```php
<?php

namespace App\Http\Controllers\Api;

// Importáljuk a szükséges osztályokat
use App\Http\Controllers\Controller;         // Alap controller osztály
use App\Models\Product;                      // Product modell
use Illuminate\Http\Request;                 // HTTP kérés kezelése
use Illuminate\Support\Facades\Validator;    // Validáció

/**
 * ProductController - Termékek kezelése
 * CRUD (Create, Read, Update, Delete) műveletek termékekkel
 */
class ProductController extends Controller
{
    /**
     * Összes termék listázása (publikus végpont)
     * GET /api/products
     * Nem kell autentikáció - bárki megtekintheti a termékeket
     * 
     * @param Request $request - HTTP kérés objektum
     * @return \Illuminate\Http\JsonResponse - Termékek JSON listája
     */
    public function index(Request $request)
    {
        // Összes termék lekérése az adatbázisból
        $products = Product::all();
        
        // Termékek visszaadása JSON formátumban
        return response()->json($products);
    }

    /**
     * Új termék létrehozása (csak admin)
     * POST /api/products
     * Middleware: auth:api, admin
     * 
     * @param Request $request - HTTP kérés objektum (tartalmazza a termék adatait)
     * @return \Illuminate\Http\JsonResponse - Létrehozott termék adatai
     */
    public function store(Request $request)
    {
        // Bemeneti adatok validálása
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',        // Név: kötelező, string, max 255 karakter
            'category' => 'required|string|max:255',    // Kategória: kötelező, string, max 255 karakter
            'price' => 'required|numeric|min:0',        // Ár: kötelező, szám, minimum 0
            'stock' => 'required|integer|min:0',        // Készlet: kötelező, egész szám, minimum 0
        ]);

        // Ha a validáció sikertelen, hibákat visszaadunk
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);  // 422 Unprocessable Entity
        }

        // Új termék létrehozása az adatbázisban
        $product = Product::create($request->all());

        // Sikeres létrehozás visszajelzése
        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);  // 201 Created HTTP státusz
    }

    /**
     * Adott termék megtekintése (publikus végpont)
     * GET /api/products/{id}
     * 
     * @param int $id - Termék azonosító
     * @return \Illuminate\Http\JsonResponse - Termék adatai vagy hibaüzenet
     */
    public function show($id)
    {
        // Termék keresése ID alapján
        $product = Product::find($id);

        // Ha nem található a termék, 404 hiba
        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);  // 404 Not Found HTTP státusz
        }

        // Termék adatainak visszaadása
        return response()->json($product);
    }

    /**
     * Termék módosítása (csak admin)
     * PUT /api/products/{id}
     * Middleware: auth:api, admin
     * 
     * @param Request $request - HTTP kérés objektum (módosított adatok)
     * @param int $id - Termék azonosító
     * @return \Illuminate\Http\JsonResponse - Frissített termék vagy hibaüzenet
     */
    public function update(Request $request, $id)
    {
        // Termék keresése ID alapján
        $product = Product::find($id);

        // Ha nem található a termék, 404 hiba
        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // Bemeneti adatok validálása
        // 'sometimes' = csak akkor kötelező, ha be van küldve az érték
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
        ]);

        // Ha a validáció sikertelen
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Termék frissítése a beküldött adatokkal
        $product->update($request->all());

        // Sikeres frissítés visszajelzése
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Termék törlése (csak admin)
     * DELETE /api/products/{id}
     * Middleware: auth:api, admin
     * 
     * @param int $id - Termék azonosító
     * @return \Illuminate\Http\JsonResponse - Sikeres törlés üzenet vagy hiba
     */
    public function destroy($id)
    {
        // Termék keresése ID alapján
        $product = Product::find($id);

        // Ha nem található a termék, 404 hiba
        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // Termék SOFT DELETE - csak megjelöli töröltnek (deleted_at mező)
        // Fizikailag NEM törlődik az adatbázisból!
        // A SoftDeletes trait miatt deleted_at = current timestamp lesz
        // FONTOS: A cascade delete miatt a kapcsolódó wishlist bejegyzések is törlődnek
        $product->delete();

        // Sikeres törlés visszajelzése
        return response()->json([
            'message' => 'Product soft deleted successfully'  // Soft delete siker
        ]);
    }
}
```

### 4. WishlistController Implementálása

#### app/Http/Controllers/Api/WishlistController.php

```php
<?php

namespace App\Http\Controllers\Api;

// Importáljuk a szükséges osztályokat
use App\Http\Controllers\Controller;         // Alap controller osztály
use App\Models\Wishlist;                     // Wishlist modell
use Illuminate\Http\Request;                 // HTTP kérés kezelése
use Illuminate\Support\Facades\Validator;    // Validáció

/**
 * WishlistController - Kívánságlista kezelése
 * CRUD műveletek a felhasználók kívánságlistájával
 */
class WishlistController extends Controller
{
    /**
     * Felhasználó saját kívánságlistájának lekérése
     * GET /api/wishlists
     * Middleware: auth:api
     * 
     * @param Request $request - HTTP kérés objektum (tartalmazza az autentikált usert)
     * @return \Illuminate\Http\JsonResponse - Wishlist bejegyzések termék adatokkal
     */
    public function index(Request $request)
    {
        // Bejelentkezett felhasználó lekérése
        $user = $request->user();
        
        // Felhasználó wishlist bejegyzéseinek lekérése termék adatokkal
        $wishlists = Wishlist::where('user_id', $user->id)  // Csak a saját wishlistek
            ->with('product')                                 // Eager loading: termék adatok betöltése
            ->get();                                          // Lekérés

        // Wishlistek visszaadása JSON formátumban
        return response()->json($wishlists);
    }

    /**
     * Összes wishlist lekérése (csak admin)
     * GET /api/admin/wishlists
     * Middleware: auth:api, admin
     * 
     * @return \Illuminate\Http\JsonResponse - Összes wishlist user és termék adatokkal
     */
    public function indexAll()
    {
        // Összes wishlist bejegyzés lekérése user és termék adatokkal
        $wishlists = Wishlist::with(['user', 'product'])->get();  // Eager loading mindkét kapcsolathoz
        
        return response()->json($wishlists);
    }

    /**
     * Termék hozzáadása a kívánságlistához
     * POST /api/wishlists
     * Middleware: auth:api
     * 
     * @param Request $request - HTTP kérés objektum (product_id)
     * @return \Illuminate\Http\JsonResponse - Létrehozott wishlist bejegyzés vagy hiba
     */
    public function store(Request $request)
    {
        // Bemeneti adat validálása
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',  // Kötelező, léteznie kell a products táblában
        ]);

        // Ha a validáció sikertelen
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ellenőrizzük, hogy már benne van-e a termék a wishlistben
        // Ez megakadályozza a duplikációt (bár az adatbázis unique constraint is véd)
        $existingWishlist = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        // Ha már létezik, 409 Conflict hibát adunk
        if ($existingWishlist) {
            return response()->json([
                'message' => 'Product is already in your wishlist'
            ], 409);  // 409 Conflict HTTP státusz
        }

        // Új wishlist bejegyzés létrehozása
        $wishlist = Wishlist::create([
            'user_id' => $request->user()->id,      // Bejelentkezett felhasználó ID-ja
            'product_id' => $request->product_id,   // Kiválasztott termék ID-ja
            'added_at' => now(),                    // Aktuális időpont
        ]);

        // Termék adatok betöltése a válaszhoz
        $wishlist->load('product');  // Eager loading: product kapcsolat betöltése

        // Sikeres létrehozás visszajelzése
        return response()->json([
            'message' => 'Product added to wishlist successfully',
            'wishlist' => $wishlist
        ], 201);  // 201 Created HTTP státusz
    }

    /**
     * Adott wishlist bejegyzés megtekintése
     * GET /api/wishlists/{id}
     * Middleware: auth:api
     * 
     * @param Request $request - HTTP kérés objektum
     * @param int $id - Wishlist bejegyzés azonosító
     * @return \Illuminate\Http\JsonResponse - Wishlist bejegyzés vagy hiba
     */
    public function show(Request $request, $id)
    {
        // Wishlist bejegyzés keresése ID alapján
        // FONTOS: Csak a saját wishlist bejegyzéseket lehet megtekinteni
        $wishlist = Wishlist::where('id', $id)
            ->where('user_id', $request->user()->id)  // Biztonsági ellenőrzés: csak saját
            ->with('product')                          // Termék adatok betöltése
            ->first();

        // Ha nem található vagy nem a felhasználóé
        if (!$wishlist) {
            return response()->json([
                'message' => 'Wishlist item not found'
            ], 404);  // 404 Not Found HTTP státusz
        }

        // Wishlist bejegyzés visszaadása
        return response()->json($wishlist);
    }

    /**
     * Termék eltávolítása a kívánságlistából
     * DELETE /api/wishlists/{id}
     * Middleware: auth:api
     * 
     * @param Request $request - HTTP kérés objektum
     * @param int $id - Wishlist bejegyzés azonosító
     * @return \Illuminate\Http\JsonResponse - Sikeres törlés vagy hiba
     */
    public function destroy(Request $request, $id)
    {
        // Wishlist bejegyzés keresése ID alapján
        // FONTOS: Csak a saját wishlist bejegyzéseket lehet törölni
        $wishlist = Wishlist::where('id', $id)
            ->where('user_id', $request->user()->id)  // Biztonsági ellenőrzés: csak saját
            ->first();

        // Ha nem található vagy nem a felhasználóé
        if (!$wishlist) {
            return response()->json([
                'message' => 'Wishlist item not found'
            ], 404);
        }

        // Wishlist bejegyzés SOFT DELETE - csak megjelöli töröltnek
        // A SoftDeletes trait miatt deleted_at = current timestamp lesz
        // Fizikailag NEM törlődik az adatbázisból, később visszaállítható
        $wishlist->delete();

        // Sikeres törlés visszajelzése
        return response()->json([
            'message' => 'Product removed from wishlist successfully (soft deleted)'
        ]);
    }

    /**
     * Adott felhasználó kívánságlistájának lekérése (csak admin)
     * GET /api/admin/users/{userId}/wishlists
     * Middleware: auth:api, admin
     * 
     * @param int $userId - Felhasználó azonosító
     * @return \Illuminate\Http\JsonResponse - Felhasználó wishlistjei
     */
    public function getUserWishlist($userId)
    {
        // Adott felhasználó összes wishlist bejegyzésének lekérése
        $wishlists = Wishlist::where('user_id', $userId)
            ->with(['user', 'product'])  // User és termék adatok betöltése
            ->get();

        return response()->json($wishlists);
    }
}
```

### 5. API Routes Konfiguráció

#### routes/api.php

```php
<?php

// Importáljuk a szükséges osztályokat
use Illuminate\Http\Request;                          // HTTP kérés kezelése
use Illuminate\Support\Facades\Route;                 // Route facade (útvonal kezelés)
use App\Http\Controllers\Api\AuthController;          // Autentikáció controller
use App\Http\Controllers\Api\ProductController;       // Termék controller
use App\Http\Controllers\Api\WishlistController;      // Wishlist controller
use App\Http\Controllers\Api\UserController;          // User controller (admin)

/**
 * API Routes - REST API végpontok definiálása
 * Prefix: /api (automatikusan hozzáadódik minden útvonalon)
 * Példa: /api/login, /api/products, stb.
 */

// ============================================================================
// PUBLIKUS ÚTVONALAK - Autentikáció nélkül elérhetők
// ============================================================================

// Regisztráció - új felhasználó létrehozása
Route::post('/register', [AuthController::class, 'register']);

// Bejelentkezés - API token generálása
Route::post('/login', [AuthController::class, 'login']);

// Termékek publikus lekérése - bárki megtekintheti
Route::get('/products', [ProductController::class, 'index']);       // Összes termék listázása
Route::get('/products/{id}', [ProductController::class, 'show']);   // Adott termék megtekintése

// ============================================================================
// VÉDETT ÚTVONALAK - auth:api middleware (JWT token szükséges)
// ============================================================================

Route::middleware('auth:api')->group(function () {
    
    // ------------------------------------------------------------------------
    // Autentikáció útvonalak (bejelentkezett felhasználóknak)
    // ------------------------------------------------------------------------
    
    // Kijelentkezés - JWT token invalidálása
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Saját profil lekérése
    Route::get('/me', [AuthController::class, 'me']);
    
    // JWT token frissítése
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // ------------------------------------------------------------------------
    // Wishlist útvonalak (bejelentkezett felhasználóknak)
    // ------------------------------------------------------------------------
    
    // Saját kívánságlista lekérése
    Route::get('/wishlists', [WishlistController::class, 'index']);
    
    // Termék hozzáadása a kívánságlistához
    Route::post('/wishlists', [WishlistController::class, 'store']);
    
    // Adott wishlist bejegyzés megtekintése
    Route::get('/wishlists/{id}', [WishlistController::class, 'show']);
    
    // Termék eltávolítása a kívánságlistából
    Route::delete('/wishlists/{id}', [WishlistController::class, 'destroy']);

    // ========================================================================
    // ADMIN ÚTVONALAK - admin middleware (admin jogosultság szükséges)
    // ========================================================================
    
    Route::middleware('admin')->group(function () {
        
        // --------------------------------------------------------------------
        // Termék kezelés (csak admin)
        // --------------------------------------------------------------------
        
        // Új termék létrehozása
        Route::post('/products', [ProductController::class, 'store']);
        
        // Termék módosítása
        Route::put('/products/{id}', [ProductController::class, 'update']);
        
        // Termék törlése
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // --------------------------------------------------------------------
        // Felhasználó kezelés (csak admin)
        // --------------------------------------------------------------------
        
        // Összes felhasználó listázása
        Route::get('/users', [UserController::class, 'index']);
        
        // Adott felhasználó megtekintése
        Route::get('/users/{id}', [UserController::class, 'show']);
        
        // Felhasználó módosítása
        Route::put('/users/{id}', [UserController::class, 'update']);
        
        // Felhasználó törlése
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // --------------------------------------------------------------------
        // Wishlist admin útvonalak (csak admin)
        // --------------------------------------------------------------------
        
        // Összes wishlist lekérése (admin nézet)
        Route::get('/admin/wishlists', [WishlistController::class, 'indexAll']);
        
        // Adott felhasználó kívánságlistájának lekérése
        Route::get('/admin/users/{userId}/wishlists', [WishlistController::class, 'getUserWishlist']);
    });
});

/**
 * Middleware magyarázat:
 * 
 * - auth:api: JWT Auth autentikáció
 *   Ellenőrzi a Bearer tokent a request headerben
 *   Ha érvénytelen/hiányzik -> 401 Unauthorized
 *   A JWT guard használja a JWTAuth facade-ot
 * 
 * - admin: Egyedi middleware (app/Http/Middleware/IsAdmin.php)
 *   Ellenőrzi, hogy a user->is_admin == true
 *   Ha nem admin -> 403 Forbidden
 * 
 * Route csoportok:
 * - Route::middleware()->group(): Middleware alkalmazása több útvonalra
 * - Nested groups: Belül is lehet újabb group (pl. admin middleware)
 * 
 * JWT token használata:
 * - Stateless: Token nem tárolódik az adatbázisban
 * - Aláírás: HS256 algoritmussal aláírt (config/jwt.php)
 * - Lejárat: JWT_TTL alapján (alapértelmezett: 60 perc)
 * - Refresh: JWT_REFRESH_TTL alapján (alapértelmezett: 2 hét)
 */
```

### 6. UserController Implementálása (Admin funkciók)

#### app/Http/Controllers/Api/UserController.php

```php
<?php

namespace App\Http\Controllers\Api;

// Importáljuk a szükséges osztályokat
use App\Http\Controllers\Controller;         // Alap controller osztály
use App\Models\User;                         // User modell
use Illuminate\Http\Request;                 // HTTP kérés kezelése
use Illuminate\Support\Facades\Hash;         // Jelszó hash-elés
use Illuminate\Support\Facades\Validator;    // Validáció

/**
 * UserController - Felhasználók kezelése (Admin funkciók)
 * CRUD műveletek felhasználókkal (csak admin jogosultsággal)
 */
class UserController extends Controller
{
    /**
     * Összes felhasználó listázása (csak admin)
     * GET /api/users
     * Middleware: auth:api, admin
     * 
     * @return \Illuminate\Http\JsonResponse - Felhasználók listája
     */
    public function index()
    {
        // Összes felhasználó lekérése az adatbázisból
        $users = User::all();
        
        return response()->json($users);
    }

    /**
     * Adott felhasználó megtekintése (csak admin)
     * GET /api/users/{id}
     * Middleware: auth:api, admin
     * 
     * @param int $id - Felhasználó azonosító
     * @return \Illuminate\Http\JsonResponse - Felhasználó adatai vagy hiba
     */
    public function show($id)
    {
        // Felhasználó keresése ID alapján
        $user = User::find($id);

        // Ha nem található
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);  // 404 Not Found
        }

        // Felhasználó adatainak visszaadása
        return response()->json($user);
    }

    /**
     * Felhasználó módosítása (csak admin)
     * PUT /api/users/{id}
     * Middleware: auth:api, admin
     * 
     * @param Request $request - HTTP kérés objektum (módosított adatok)
     * @param int $id - Felhasználó azonosító
     * @return \Illuminate\Http\JsonResponse - Frissített felhasználó vagy hiba
     */
    public function update(Request $request, $id)
    {
        // Felhasználó keresése ID alapján
        $user = User::find($id);

        // Ha nem található
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Bemeneti adatok validálása
        // 'sometimes' = csak akkor kötelező, ha be van küldve
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',                      // Név
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$id,  // Email (egyedi, kivéve saját)
            'password' => 'sometimes|required|string|min:8',                    // Jelszó (minimum 8 karakter)
            'is_admin' => 'sometimes|boolean',                                  // Admin flag
        ]);

        // Ha a validáció sikertelen
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Frissítendő adatok előkészítése
        $updateData = $request->only(['name', 'email', 'is_admin']);

        // Ha új jelszót adtak meg, hash-eljük
        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        // Felhasználó frissítése
        $user->update($updateData);

        // Sikeres frissítés visszajelzése
        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Felhasználó törlése (csak admin)
     * DELETE /api/users/{id}
     * Middleware: auth:api, admin
     * 
     * @param int $id - Felhasználó azonosító
     * @return \Illuminate\Http\JsonResponse - Sikeres törlés vagy hiba
     */
    public function destroy($id)
    {
        // Felhasználó keresése ID alapján
        $user = User::find($id);

        // Ha nem található
        if (!$user) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        }

        // Felhasználó SOFT DELETE - csak megjelöli töröltnek (deleted_at)
        // Fizikailag NEM törlődik! Később visszaállítható.
        // FONTOS: A cascade delete miatt a kapcsolódó wishlist bejegyzések is soft delete-elve lesznek
        // Előny: adatvesztés megakadályozása, audit trail, visszavonás lehetősége
        $user->delete();

        // Sikeres törlés visszajelzése
        return response()->json([
            'message' => 'User soft deleted successfully'
        ]);
    }
}
```

---

## V. API Végpontok Részletes Dokumentációja

### Általános Információk

**Base URL:** `http://localhost/wishlist/public/api`

**Content-Type:** `application/json`  
**Accept:** `application/json`

**JWT Autentikáció (védett végpontokhoz):**
```
Authorization: Bearer {jwt_token}
```

**JWT Token jellemzők:**
- **Típus:** JSON Web Token (stateless)
- **Lejárati idő:** 60 perc (JWT_TTL)
- **Refresh idő:** 2 hét (JWT_REFRESH_TTL)
- **Algoritmus:** HS256
- **Tárolás:** Nem kerül adatbázisba, csak aláírt token
- **Blacklist:** Kijelentkezéskor blacklist-re kerül

**Hibakódok:**
- `400 Bad Request` - Hibás formátumú kérés
- `401 Unauthorized` - Érvénytelen/lejárt JWT token vagy hitelesítés szükséges
- `403 Forbidden` - Nincs jogosultság (admin jogok szükségesek)
- `404 Not Found` - Erőforrás nem található
- `409 Conflict` - Ütköző művelet (pl. termék már a kívánságlistában)
- `422 Unprocessable Entity` - Validációs hiba
- `500 Internal Server Error` - Szerver hiba (pl. JWT token generálási hiba)

### Publikus végpontok (JWT token nélkül)

#### POST /api/register
Új felhasználó regisztrálása.

**URL:** `http://localhost/wishlist/public/api/register`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Kérés törzse:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "is_admin": false
}
```

**Válasz:** 201 Created
```json
{
  "message": "User registered successfully. Please login.",
  "user": {
    "id": 13,
    "name": "John Doe",
    "email": "john@example.com",
    "is_admin": false
  }
}
```

**Hiba:** 422 Unprocessable Entity
```json
{
  "message": "Validation error",
  "errors": {
    "email": [
      "The email has already been taken."
    ],
    "password": [
      "The password confirmation does not match."
    ]
  }
}
```

---

#### POST /api/login
Bejelentkezés és JWT token megszerzése.

**URL:** `http://localhost/wishlist/public/api/login`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Kérés törzse:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Válasz:** 200 OK
```json
{
  "message": "Login successful",
  "user": {
    "id": 13,
    "name": "John Doe",
    "email": "john@example.com",
    "is_admin": false,
    "email_verified_at": null,
    "created_at": "2026-01-05T10:30:00.000000Z",
    "updated_at": "2026-01-05T10:30:00.000000Z"
  },
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0OjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzM2MDY2NDAwLCJleHAiOjE3MzYwNzAwMDAsIm5iZiI6MTczNjA2NjQwMCwianRpIjoiMTIzNDU2Nzg5MCIsInN1YiI6IjEzIiwicHJ2IjoiMjNiZDVjODk0OWY2MDBhZGIzOWU3MDFjNDAwODcyZGI3YTU5NzZmNyJ9.signature_here",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

**Megjegyzés:** Az `access_token` egy JWT token, amely tartalmazza:
- **iss** (issuer): Token kiállító URL
- **iat** (issued at): Kiállítás időpontja
- **exp** (expiration): Lejárati időpont (iat + JWT_TTL)
- **nbf** (not before): Mettől érvényes
- **jti** (JWT ID): Egyedi token azonosító
- **sub** (subject): Felhasználó ID
- **prv** (provider): Hash a user provider-ről

**Hiba:** 401 Unauthorized
```json
{
  "message": "Invalid credentials"
}
```

**Hiba:** 500 Internal Server Error
```json
{
  "message": "Could not create token"
}
```

---

#### GET /api/products
Összes termék lekérése (publikus, JWT token nélkül).

**URL:** `http://localhost/wishlist/public/api/products`

**Headers:**
```
Accept: application/json
```

**Válasz:** 200 OK
```json
[
  {
    "id": 1,
    "name": "Laptop Dell XPS 15",
    "category": "Electronics",
    "price": "1299.99",
    "stock": 15,
    "created_at": "2025-12-06T10:00:00.000000Z",
    "updated_at": "2025-12-06T10:00:00.000000Z"
  },
  {
    "id": 2,
    "name": "iPhone 15 Pro",
    "category": "Electronics",
    "price": "999.99",
    "stock": 25,
    "created_at": "2025-12-06T10:00:00.000000Z",
    "updated_at": "2025-12-06T10:00:00.000000Z"
  }
]
```

#### GET /api/products/{id}
Adott termék részleteinek lekérése (publikus).

**URL:** `http://localhost/wishlist/public/api/products/{id}`

**Headers:**
```
Accept: application/json
```

**Példa URL:** `http://localhost/wishlist/public/api/products/1`

**Válasz:** 200 OK
```json
{
  "id": 1,
  "name": "Laptop Dell XPS 15",
  "category": "Electronics",
  "price": "1299.99",
  "stock": 15,
  "created_at": "2026-01-05T10:00:00.000000Z",
  "updated_at": "2026-01-05T10:00:00.000000Z"
}
```

**Hiba:** 404 Not Found
```json
{
  "message": "Product not found"
}
```

### Védett végpontok (JWT autentikáció szükséges)

#### POST /api/logout
JWT token invalidálása (blacklist-re helyezés).

**URL:** `http://localhost/wishlist/public/api/logout`

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
Accept: application/json
```

**Válasz:** 200 OK
```json
{
  "message": "Logged out successfully"
}
```

**Hiba:** 500 Internal Server Error
```json
{
  "message": "Failed to logout, please try again"
}
```

---

#### GET /api/me
Aktuális bejelentkezett felhasználó adatainak lekérése.

**URL:** `http://localhost/wishlist/public/api/me`

**Headers:**
```
Authorization: Bearer {jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
{
  "id": 13,
  "name": "John Doe",
  "email": "john@example.com",
  "is_admin": false,
  "email_verified_at": null,
  "created_at": "2026-01-05T10:30:00.000000Z",
  "updated_at": "2026-01-05T10:30:00.000000Z"
}
```

**Hiba:** 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

---

#### POST /api/refresh
JWT token frissítése (új token generálás).

**URL:** `http://localhost/wishlist/public/api/refresh`

**Headers:**
```
Authorization: Bearer {jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

**Hiba:** 500 Internal Server Error
```json
{
  "message": "Could not refresh token"
}
```

---

### Kívánságlista végpontok (JWT autentikáció szükséges)

#### GET /api/wishlists
Saját kívánságlista lekérése.

**URL:** `http://localhost/wishlist/public/api/wishlists`

**Headers:**
```
Authorization: Bearer {jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
[
  {
    "id": 1,
    "user_id": 13,
    "product_id": 1,
    "added_at": "2026-01-05T10:30:00.000000Z",
    "created_at": "2026-01-05T10:30:00.000000Z",
    "updated_at": "2026-01-05T10:30:00.000000Z",
    "product": {
      "id": 1,
      "name": "Laptop Dell XPS 15",
      "category": "Electronics",
      "price": "1299.99",
      "stock": 15,
      "created_at": "2026-01-05T10:00:00.000000Z",
      "updated_at": "2026-01-05T10:00:00.000000Z"
    }
  }
]
```

---

#### POST /api/wishlists
Termék hozzáadása a kívánságlistához.

**URL:** `http://localhost/wishlist/public/api/wishlists`

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
Accept: application/json
```

**Kérés törzse:**
```json
{
  "product_id": 1
}
```

**Válasz:** 201 Created
```json
{
  "message": "Product added to wishlist",
  "wishlist": {
    "user_id": 13,
    "product_id": 1,
    "added_at": "2026-01-05T10:30:00.000000Z",
    "updated_at": "2026-01-05T10:30:00.000000Z",
    "created_at": "2026-01-05T10:30:00.000000Z",
    "id": 1,
    "product": {
      "id": 1,
      "name": "Laptop Dell XPS 15",
      "category": "Electronics",
      "price": "1299.99",
      "stock": 15,
      "created_at": "2026-01-05T10:00:00.000000Z",
      "updated_at": "2026-01-05T10:00:00.000000Z"
    }
  }
}
```

**Hiba:** 409 Conflict
```json
{
  "message": "Product already in wishlist"
}
```

---

#### GET /api/wishlists/{id}
Adott kívánságlista elem lekérése.

**URL:** `http://localhost/wishlist/public/api/wishlists/{id}`

**Headers:**
```
Authorization: Bearer {jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
{
  "id": 1,
  "user_id": 13,
  "product_id": 1,
  "added_at": "2026-01-05T10:30:00.000000Z",
  "created_at": "2026-01-05T10:30:00.000000Z",
  "updated_at": "2026-01-05T10:30:00.000000Z",
  "product": {
    "id": 1,
    "name": "Laptop Dell XPS 15",
    "category": "Electronics",
    "price": "1299.99",
    "stock": 15,
    "created_at": "2026-01-05T10:00:00.000000Z",
    "updated_at": "2026-01-05T10:00:00.000000Z"
  }
}
```

---

#### DELETE /api/wishlists/{id}
Termék eltávolítása a kívánságlistából (soft delete).

**URL:** `http://localhost/wishlist/public/api/wishlists/{id}`

**Headers:**
```
Authorization: Bearer {jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
{
  "message": "Product removed from wishlist (soft delete)"
}
```

**Hiba:** 404 Not Found
```json
{
  "message": "Wishlist item not found"
}
```

---

### Admin végpontok (JWT autentikáció + admin jogosultság szükséges)

#### POST /api/products
Új termék létrehozása (csak admin).

**URL:** `http://localhost/wishlist/public/api/products`

**Headers:**
```
Authorization: Bearer {admin_jwt_token}
Content-Type: application/json
Accept: application/json
```

**Kérés törzse:**
```json
{
  "name": "New Product",
  "category": "Electronics",
  "price": 299.99,
  "stock": 50
}
```

**Válasz:** 201 Created
```json
{
  "message": "Product created successfully",
  "product": {
    "name": "New Product",
    "category": "Electronics",
    "price": 299.99,
    "stock": 50,
    "updated_at": "2026-01-05T10:30:00.000000Z",
    "created_at": "2026-01-05T10:30:00.000000Z",
    "id": 25
  }
}
```

**Hiba:** 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

---

#### PUT /api/products/{id}
Termék módosítása (csak admin).

**URL:** `http://localhost/wishlist/public/api/products/{id}`

**Headers:**
```
Authorization: Bearer {admin_jwt_token}
Content-Type: application/json
Accept: application/json
```

**Kérés törzse:**
```json
{
  "name": "Updated Product Name",
  "price": 399.99,
  "stock": 25
}
```

**Válasz:** 200 OK
```json
{
  "message": "Product updated successfully",
  "product": {
    "id": 1,
    "name": "Updated Product Name",
    "category": "Electronics",
    "price": "399.99",
    "stock": 25,
    "created_at": "2025-12-06T10:00:00.000000Z",
    "updated_at": "2025-12-06T10:35:00.000000Z"
  }
}
```

#### DELETE /api/products/{id}
Termék törlése (csak admin, soft delete).

**URL:** `http://localhost/wishlist/public/api/products/{id}`

**Headers:**
```
Authorization: Bearer {admin_jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
{
  "message": "Product deleted successfully (soft delete)"
}
```

**Hiba:** 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

---

#### GET /api/users
Összes felhasználó listázása (csak admin).

**URL:** `http://localhost/wishlist/public/api/users`

**Headers:**
```
Authorization: Bearer {admin_jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
[
  {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "is_admin": true,
    "email_verified_at": null,
    "created_at": "2026-01-05T09:00:00.000000Z",
    "updated_at": "2026-01-05T09:00:00.000000Z"
  },
  {
    "id": 2,
    "name": "John Doe",
    "email": "john@example.com",
    "is_admin": false,
    "email_verified_at": null,
    "created_at": "2026-01-05T10:30:00.000000Z",
    "updated_at": "2026-01-05T10:30:00.000000Z"
  }
]
```

**Hiba:** 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

---

#### GET /api/admin/wishlists
Összes kívánságlista lekérése (csak admin).

**URL:** `http://localhost/wishlist/public/api/admin/wishlists`

**Headers:**
```
Authorization: Bearer {admin_jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
[
  {
    "id": 1,
    "user_id": 2,
    "product_id": 1,
    "added_at": "2026-01-05T10:30:00.000000Z",
    "created_at": "2026-01-05T10:30:00.000000Z",
    "updated_at": "2026-01-05T10:30:00.000000Z",
    "user": {
      "id": 2,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "product": {
      "id": 1,
      "name": "Laptop Dell XPS 15",
      "category": "Electronics",
      "price": "1299.99",
      "stock": 15
    }
  }
]
```

**Hiba:** 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

---

#### GET /api/admin/users/{userId}/wishlists
Adott felhasználó kívánságlistája (csak admin).

**URL:** `http://localhost/wishlist/public/api/admin/users/{userId}/wishlists`

**Headers:**
```
Authorization: Bearer {admin_jwt_token}
Accept: application/json
```

**Válasz:** 200 OK
```json
[
  {
    "id": 1,
    "user_id": 2,
    "product_id": 1,
    "added_at": "2026-01-05T10:30:00.000000Z",
    "created_at": "2026-01-05T10:30:00.000000Z",
    "updated_at": "2026-01-05T10:30:00.000000Z",
    "user": {
      "id": 2,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "product": {
      "id": 1,
      "name": "Laptop Dell XPS 15",
      "category": "Electronics",
      "price": "1299.99",
      "stock": 15
    }
  }
]
```

**Hiba:** 403 Forbidden
```json
{
  "message": "This action is unauthorized."
}
```

---

## VI. Tesztelés

### 1. Teszt Fájlok Létrehozása

```powershell
# Feature tesztek létrehozása
php artisan make:test AuthApiTest
php artisan make:test ProductApiTest
php artisan make:test UserApiTest
php artisan make:test WishlistApiTest
```

### 2. AuthApiTest - Autentikációs Tesztek

**Fájl:** `tests/Feature/AuthApiTest.php`

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Wishlist;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Teszt User',
            'email' => 'teszt@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message', 
                     'user' => ['id', 'name', 'email', 'is_admin']
                 ]);
    }

    /** @test */
    public function user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'teszt@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $response = $this->postJson('/api/login', [
            'email' => 'teszt@example.com',
            'password' => 'password',
        ]);
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message', 
                     'user', 
                     'access_token', 
                     'token_type', 
                     'expires_in'
                 ]);
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/logout');
        
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Logged out successfully']);
    }

    /** @test */
    public function user_can_get_own_profile()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/me');
        
        $response->assertStatus(200)
                 ->assertJson([
                     'id' => $user->id,
                     'name' => $user->name,
                     'email' => $user->email
                 ]);
    }

    /** @test */
    public function user_can_refresh_token()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/refresh');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'access_token', 
                     'token_type', 
                     'expires_in'
                 ]);
    }
}
```

**Tesztelt végpontok:**
- `POST /api/register` - Regisztráció
- `POST /api/login` - Bejelentkezés (JWT token generálás)
- `POST /api/logout` - Kijelentkezés (JWT token invalidálás)
- `GET /api/me` - Saját profil lekérése
- `POST /api/refresh` - JWT token frissítés

---

### 3. ProductApiTest - Termék Tesztek

**Fájl:** `tests/Feature/ProductApiTest.php`

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function anyone_can_list_products()
    {
        Product::factory()->count(3)->create();
        
        $response = $this->getJson('/api/products');
        
        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    /** @test */
    public function anyone_can_view_a_product()
    {
        $product = Product::factory()->create();
        
        $response = $this->getJson('/api/products/' . $product->id);
        
        $response->assertStatus(200)
                 ->assertJson(['id' => $product->id]);
    }

    /** @test */
    public function admin_can_create_product()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        
        $data = [
            'name' => 'Teszt Termék',
            'category' => 'Electronics',
            'price' => 123.45,
            'stock' => 10,
        ];
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/products', $data);
        
        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'product'])
                 ->assertJson(['product' => ['name' => 'Teszt Termék']]);
    }

    /** @test */
    public function admin_can_update_product()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $product = Product::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->putJson('/api/products/' . $product->id, [
                             'stock' => 99
                         ]);
        
        $response->assertStatus(200)
                 ->assertJson(['product' => ['stock' => 99]]);
    }

    /** @test */
    public function admin_can_delete_product()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $product = Product::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->deleteJson('/api/products/' . $product->id);
        
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Product deleted successfully (soft delete)']);
    }

    /** @test */
    public function non_admin_cannot_create_product()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = JWTAuth::fromUser($user);
        
        $data = [
            'name' => 'Teszt Termék',
            'category' => 'Electronics',
            'price' => 123.45,
            'stock' => 10,
        ];
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/products', $data);
        
        $response->assertStatus(403);
    }
}
```

**Tesztelt végpontok:**
- `GET /api/products` - Termékek listázása (nyilvános)
- `GET /api/products/{id}` - Termék megtekintése (nyilvános)
- `POST /api/products` - Termék létrehozása (csak admin)
- `PUT /api/products/{id}` - Termék módosítása (csak admin)
- `DELETE /api/products/{id}` - Termék törlése (csak admin)

---

### 4. WishlistApiTest - Kívánságlista Tesztek

**Fájl:** `tests/Feature/WishlistApiTest.php`

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Wishlist;
use Tymon\JWTAuth\Facades\JWTAuth;

class WishlistApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_list_own_wishlist()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $product = Product::factory()->create();
        
        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'added_at' => now(),
        ]);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/wishlists');
        
        $response->assertStatus(200)
                 ->assertJsonFragment(['product_id' => $product->id]);
    }

    /** @test */
    public function user_can_add_product_to_wishlist()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $product = Product::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/wishlists', [
                             'product_id' => $product->id
                         ]);
        
        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'wishlist'])
                 ->assertJson(['wishlist' => ['product_id' => $product->id]]);
    }

    /** @test */
    public function user_can_remove_product_from_wishlist()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $product = Product::factory()->create();
        
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'added_at' => now(),
        ]);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->deleteJson('/api/wishlists/' . $wishlist->id);
        
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Product removed from wishlist (soft delete)']);
    }

    /** @test */
    public function user_cannot_add_duplicate_product_to_wishlist()
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $product = Product::factory()->create();
        
        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'added_at' => now(),
        ]);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->postJson('/api/wishlists', [
                             'product_id' => $product->id
                         ]);
        
        $response->assertStatus(409)
                 ->assertJson(['message' => 'Product already in wishlist']);
    }

    /** @test */
    public function admin_can_list_all_wishlists()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'added_at' => now(),
        ]);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/admin/wishlists');
        
        $response->assertStatus(200)
                 ->assertJsonFragment(['user_id' => $user->id]);
    }

    /** @test */
    public function admin_can_get_user_specific_wishlist()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $user = User::factory()->create();
        $product = Product::factory()->create();
        
        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'added_at' => now(),
        ]);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/admin/users/' . $user->id . '/wishlists');
        
        $response->assertStatus(200)
                 ->assertJsonFragment(['product_id' => $product->id]);
    }
}
```

**Tesztelt végpontok:**
- `GET /api/wishlists` - Saját kívánságlista (autentikált felhasználó)
- `POST /api/wishlists` - Termék hozzáadása kívánságlistához
- `DELETE /api/wishlists/{id}` - Termék eltávolítása kívánságlistából
- `GET /api/admin/wishlists` - Összes kívánságlista (csak admin)
- `GET /api/admin/users/{userId}/wishlists` - Adott felhasználó kívánságlistája (csak admin)

---

### 5. UserApiTest - Felhasználó Tesztek

**Fájl:** `tests/Feature/UserApiTest.php`

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_list_all_users()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $user = User::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/users');
        
        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $user->id]);
    }

    /** @test */
    public function admin_can_view_specific_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $user = User::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/users/' . $user->id);
        
        $response->assertStatus(200)
                 ->assertJson(['id' => $user->id]);
    }

    /** @test */
    public function admin_can_update_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $user = User::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->putJson('/api/users/' . $user->id, [
                             'name' => 'Updated Name'
                         ]);
        
        $response->assertStatus(200)
                 ->assertJson(['user' => ['name' => 'Updated Name']]);
    }

    /** @test */
    public function admin_can_delete_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = JWTAuth::fromUser($admin);
        $user = User::factory()->create();
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->deleteJson('/api/users/' . $user->id);
        
        $response->assertStatus(200)
                 ->assertJson(['message' => 'User deleted successfully (soft delete)']);
    }

    /** @test */
    public function non_admin_cannot_manage_users()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = JWTAuth::fromUser($user);
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                         ->getJson('/api/users');
        
        $response->assertStatus(403);
    }
}
```

**Tesztelt végpontok:**
- `GET /api/users` - Összes felhasználó listázása (csak admin)
- `GET /api/users/{id}` - Felhasználó megtekintése (csak admin)
- `PUT /api/users/{id}` - Felhasználó módosítása (csak admin)
- `DELETE /api/users/{id}` - Felhasználó törlése (csak admin)

---

### 6. Tesztek Futtatása

```powershell
# Összes teszt futtatása
php artisan test

# Specifikus teszt fájl futtatása
php artisan test tests/Feature/AuthApiTest.php
php artisan test tests/Feature/ProductApiTest.php
php artisan test tests/Feature/WishlistApiTest.php
php artisan test tests/Feature/UserApiTest.php

# Részletes kimenet
php artisan test --verbose

# Coverage report generálása (ha xdebug telepítve van)
php artisan test --coverage
```

**Várható kimenet:**
```
PASS  Tests\Feature\AuthApiTest
✓ user can register
✓ user can login
✓ user can logout
✓ user can get own profile
✓ user can refresh token

PASS  Tests\Feature\ProductApiTest
✓ anyone can list products
✓ anyone can view a product
✓ admin can create product
✓ admin can update product
✓ admin can delete product
✓ non admin cannot create product

PASS  Tests\Feature\WishlistApiTest
✓ user can list own wishlist
✓ user can add product to wishlist
✓ user can remove product from wishlist
✓ user cannot add duplicate product to wishlist
✓ admin can list all wishlists
✓ admin can get user specific wishlist

PASS  Tests\Feature\UserApiTest
✓ admin can list all users
✓ admin can view specific user
✓ admin can update user
✓ admin can delete user
✓ non admin cannot manage users

Tests:    26 passed
Duration: 2.45s
```
---

## VII. Végpontok Összefoglalója

| HTTP Metódus | Útvonal | Jogosultság | Státuszkódok | Rövid Leírás |
|--------------|---------|-------------|--------------|--------------|
| POST | /register | Nyilvános | 201 Created, 422 Unprocessable Entity | Új felhasználó regisztrációja |
| POST | /login | Nyilvános | 200 OK, 401 Unauthorized | Bejelentkezés |
| GET | /products | Nyilvános | 200 OK | Összes termék listázása |
| GET | /products/{id} | Nyilvános | 200 OK, 404 Not Found | Adott termék lekérése |
| POST | /logout | Hitelesített | 200 OK | Kijelentkezés |
| GET | /me | Hitelesített | 200 OK | Saját profil lekérése |
| GET | /wishlists | Hitelesített | 200 OK | Saját kívánságlista lekérése |
| POST | /wishlists | Hitelesített | 201 Created, 409 Conflict, 422 Unprocessable Entity | Termék hozzáadása kívánságlistához |
| GET | /wishlists/{id} | Hitelesített | 200 OK, 404 Not Found | Kívánságlista elem lekérése |
| DELETE | /wishlists/{id} | Hitelesített | 200 OK, 404 Not Found | Termék eltávolítása kívánságlistából |
| POST | /products | Admin | 201 Created, 422 Unprocessable Entity | Új termék létrehozása |
| PUT | /products/{id} | Admin | 200 OK, 404 Not Found, 422 Unprocessable Entity | Termék módosítása |
| DELETE | /products/{id} | Admin | 200 OK, 404 Not Found | Termék törlése |
| GET | /users | Admin | 200 OK, 403 Forbidden | Összes felhasználó listázása |
| GET | /users/{id} | Admin | 200 OK, 403 Forbidden, 404 Not Found | Felhasználó lekérése |
| PUT | /users/{id} | Admin | 200 OK, 403 Forbidden, 404 Not Found | Felhasználó módosítása |
| DELETE | /users/{id} | Admin | 200 OK, 403 Forbidden, 404 Not Found | Felhasználó törlése |
| GET | /admin/wishlists | Admin | 200 OK, 403 Forbidden | Összes kívánságlista (admin nézet) |
| GET | /admin/users/{userId}/wishlists | Admin | 200 OK, 403 Forbidden | Felhasználó kívánságlistája (admin) |

---
## VIII. Útmutató

### Rendszerkövetelmények
- PHP 8.2+
- MySQL 8.0+ vagy MariaDB 10.4+
- Composer 2.x
- XAMPP vagy Laravel Valet/Herd

### Lépések (meglévő projekt)

1. **Projekt klónozása**
```powershell
git clone [repository_url] wishlist_auth
cd wishlist_auth
```

2. **Függőségek telepítése**
```powershell
composer install
npm install # ha van frontend
```

3. **Környezeti konfiguráció**
```powershell
cp .env.example .env
php artisan key:generate
```

4. **Adatbázis konfiguráció**
```powershell
# phpMyAdmin-ban hozd létre: wishlists adatbázist
# .env fájl módosítása
```

5. **Migráció és seeding**
```powershell
php artisan migrate
php artisan db:seed
```

6. **Szerver indítása**
```powershell
php artisan serve
# vagy XAMPP használata
```

7. **Tesztelés**
```powershell
php artisan test
```

### Új projekt létrehozása (nulláról)

```powershell
# Laravel projekt létrehozása
composer create-project laravel/laravel wishlist_auth

cd wishlist_auth

# JWT Auth telepítése
composer require tymon/jwt-auth

# JWT config fájl publikálása
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"

# JWT secret key generálása
php artisan jwt:secret

# Admin middleware létrehozása
php artisan make:middleware IsAdmin

# Modellek és controller-ek létrehozása
php artisan make:model Product -m
php artisan make:model Wishlist -m
php artisan make:controller Api/AuthController
php artisan make:controller Api/ProductController
php artisan make:controller Api/UserController  
php artisan make:controller Api/WishlistController

# Factory-k és seederek
php artisan make:factory ProductFactory
php artisan make:factory WishlistFactory
php artisan make:seeder UserSeeder
php artisan make:seeder ProductSeeder
php artisan make:seeder WishlistSeeder

# Tesztek
php artisan make:test AuthApiTest
php artisan make:test ProductApiTest
php artisan make:test UserApiTest
php artisan make:test WishlistApiTest
```

### JWT Auth Beállítása

1. **User model módosítása** (`app/Models/User.php`):
```php
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    // JWT methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
```

2. **Auth config frissítése** (`config/auth.php`):
```php
'defaults' => [
    'guard' => 'api',
    'passwords' => 'users',
],

'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

3. **Routes védése** (`routes/api.php`):
```php
Route::middleware('auth:api')->group(function () {
    // Protected routes
});
```
