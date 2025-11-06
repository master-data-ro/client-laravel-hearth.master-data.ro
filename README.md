# Hearth License Client

Small Laravel package that lets a client application verify a license key against hearth.master-data.ro and store the verified license locally.

Usage (client application):

1. Add the package to your project's composer (for local testing, add a path repository):

```json
"repositories": [
  {
    "type": "path",
    "url": "../path/to/hearth/master-data/sdk/laravel-license-client"
  }
]
```

then:

```bash
composer require hearth/license-client
```

2. Run the artisan command to verify a license key (this will contact hearth.master-data.ro):

```bash
php artisan make:license-server YOUR-LICENSE-KEY
```

3. On success the package writes `storage/license.json` with the verified license metadata.

Notes:
- The package pins the authority (`https://hearth.master-data.ro`) and includes the authority's public key bundled inside the package for signature verification. The client will only accept responses signed with that embedded key. This prevents a local modification of the authority URL from enabling a forged authority to be accepted.
- This package is intentionally minimal and only used by clients; it does not alter the server.

Encryption and middleware
- The package saves the verified license encrypted by default using the application's `APP_KEY` (AES-256-CBC). The saved file is `storage/license.json` and contains a small JSON wrapper with the encrypted payload.
- By default the package will *enforce* the license check automatically: when the package is registered it pushes `Hearth\\LicenseClient\\Middleware\\EnsureHasValidLicense` into the `web` middleware group and the application will return HTTP 403 for web requests until a valid license is present.

Important: the package only accepts a license as valid when the authority explicitly marks it valid. The client command will not save pending or invalid responses locally. The middleware checks the saved license's `data.valid` flag (the authority's response) and blocks unless it's `true`.

Enforcement (mandatory)
- The package enforces the license check automatically and this behavior is mandatory: the middleware is added to the `web` group on package boot and web requests will receive HTTP 403 until a valid license is present. This cannot be disabled from the environment by design.

If you need to exempt specific internal tooling or health endpoints, perform that logic in your own application before the middleware runs (for example register a higher-priority middleware), but the package itself will not provide an opt-out.

Blocked page and messages are intentionally fixed in the package and cannot be published or customized by the client application. This prevents a client from altering messaging or bypassing enforcement by changing view or config files. If you need a branded version, contact the package maintainer to prepare a custom build.

Usage examples:

1. Verify and save (the saved file will be encrypted using `APP_KEY`):

```bash
php artisan make:license-server YOUR-LICENSE-KEY
```

2. Register middleware in `app/Http/Kernel.php` or apply to routes:

```php
use Hearth\LicenseClient\Middleware\EnsureHasValidLicense;

//'web' => [
//    \App\Http\Middleware\EncryptCookies::class,
//    ...
//    EnsureHasValidLicense::class,
//],
```

3. If you prefer not to use `APP_KEY`, set `APP_LICENSE_PASSPHRASE` in your environment and the package will derive the encryption key from that value instead.

Notes on security:
- `APP_KEY` is a reasonable default for encrypting a local file inside the application, but for extra safety you can provide a dedicated `APP_LICENSE_PASSPHRASE` environment variable and back it up securely.
- If you need key rotation, the package can be extended to store an encrypted key-wrapping key; ask me and I can add rotation support.
# hearth-license-client
