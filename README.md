# hearth.scmc.ro client

[View on GitHub](https://github.com/master-data-ro/hearth-license-client)

## Compatibilitate

- **PHP:** `^8.2` (aliniat cu Laravel 11+).
- **Laravel:** `11.x`, `12.x` și `13.x` (constrângeri explicite pe componentele `illuminate/*` folosite de pachet).
- Auto-descoperire: `extra.laravel.providers` din `composer.json` al pachetului (același mecanism pe 11–13). Dacă `package:discover` nu rulează pe server, vezi secțiunea de troubleshooting de mai jos.

## Instalare / Installation

**Versiune minimă recomandată: `^0.1.1`.** Dacă în `composer.json` ai **`^0.1.0`**, Composer poate instala exact **`0.1.0`**, unde **nu există** comanda `license-client:status` (și alte îmbunătățiri). Folosește:

```bash
composer require hearth/license-client:^0.1.1
```

sau, dacă pachetul e deja listat, schimbă constrângerea la `^0.1.1` (sau `^0.1.4`) și rulează:

```bash
composer update hearth/license-client
php artisan package:discover --ansi
```

### Eroare: `There are no commands defined in the "license-client" namespace`

În **`hearth/license-client` v0.1.0** nu există namespace-ul Artisan `license-client:` — este normal. Comenzile din pachet sunt doar cele legate de **`make:license-server`** (`MakeLicenseServerCommand`). Folosește tabelul din secțiunea **Comenzi Artisan: make:license-server** mai jos.

Dacă vrei și **`php artisan license-client:status`** (diagnostic), actualizează la **≥ 0.1.1** (vezi [Instalare](#instalare--installation)).

1. Verifică versia instalată: `composer show hearth/license-client` (sau în `composer.lock` câmpul `version`).
2. Dacă rămâi pe **`0.1.0`**, nu rula `license-client:*`; folosește doar `make:license-server`. Pentru comenzi `license-client:*`, schimbă constrângerea la **`^0.1.1`** și `composer update hearth/license-client`.
3. După update: **`php artisan package:discover`** și **`php artisan optimize:clear`**. Dacă providerul nu e descoperit, adaugă-l manual în `bootstrap/providers.php` (vezi mai jos).

### Dacă după `composer require` site-ul merge fără licență

Laravel încarcă provider-ul pachetului doar după **`php artisan package:discover`**. La tine Composer raportează scripturi care nu există, de exemplu:

`You made a reference to a non-existent script @/usr/local/bin/php artisan package:discover`

În acest caz descoperirea pachetelor **nu s-a rulat** — provider-ul nu e înregistrat și middleware-ul nu se activează.

**Pași:**

1. În `composer.json` al aplicației (nu al pachetului), la `scripts` → `post-autoload-dump`, înlocuiește `@/usr/local/bin/php` cu **`@php`** (sau calea reală către binarul PHP de pe server, ex. `/usr/bin/php`).
2. Din directorul proiectului, rulează manual:

```bash
php artisan package:discover --ansi
php artisan optimize:clear
```

Dacă `package:discover` a rulat dar încă nu vezi efect, **șterge cache-ul vechi de manifest** și redescoperă:

```bash
rm -f bootstrap/cache/packages.php
php artisan package:discover --ansi
php artisan optimize:clear
```

După actualizare la **≥ 0.1.1**, poți rula diagnosticul:

```bash
php artisan license-client:status
```

### 404 pe `/licenta` sau pagina UI nu se încarcă

1. **Cache rute:** dacă rulezi `php artisan route:cache`, rutele pachetului trebuie incluse în acel cache. După `composer update` / prima instalare a pachetului, un cache vechi poate să nu conțină rutele — rulează **`php artisan route:clear`** (sau regenerează `route:cache` după ce pachetul e instalat).
2. **Subdirector:** dacă aplicația e servită sub un path (ex. `https://exemplu.ro/myapp`), setează **`APP_URL`** (și `config('app.url')`) la URL-ul complet **cu** acel path. Rutele UI se înregistrează atunci la `…/myapp/licenta` (nu la rădăcină domeniului). Cu pachet **≥ 0.1.1**, comanda `php artisan license-client:status` afișează și „Cale UI activare licență”.
3. **Provider manual:** dacă tot nu merge, înregistrează provider-ul în `bootstrap/providers.php` (Laravel 11, 12, 13):  
   `Hearth\LicenseClient\LicenseServiceProvider::class,`

Pentru testare locală, poți adăuga un repository de tip `path`:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/master-data-ro/hearth-license-client.git"
  }
]
```

## Comenzi Artisan: make:license-server

Această comandă există **din v0.1.0** și rămâne comanda principală de verificare / salvare din CLI:

| Acțiune | Comandă |
|--------|---------|
| **Prima solicitare pe domeniu** (cheie provizorie aleatoare; trimite `APP_URL` → host către Hearth) | `php artisan make:license-server CHEIE-ALEATOARE` |
| Verificare / salvare cu **cheia emisă** de autoritate | `php artisan make:license-server CHEIA-DEFINITIVA` |
| Afișează licența salvată (decrypt, JSON în consolă) | `php artisan make:license-server --show` |

Opțional: `--passphrase=` pentru derivarea cheii de criptare (implicit se folosește `APP_KEY` / fluxul din `Encryption`).

## Comenzi `license-client:*` (doar ≥ 0.1.1)

- **`php artisan license-client:status`** — diagnostic (manifest, provider, cache rute, cale UI `/licenta`).
- **`php artisan license-client:probe`** — verificare **one-shot** fără browser: reachability **JWKS**, **PEM**, **POST /api/verify** (corp de probă), versiunea din `composer.json` a pachetului, existența `storage/license.json`, rezumat `LicenseState`, calea efectivă `Package::licenseActivationBasePath()` (inclusiv prefix din `APP_URL`). Pentru **CI / deploy**, folosește **`--json`** (ieșire JSON stabilă pe stdout).
- **`php artisan license-client:sync`** — re-verifică **licența salvată** la autoritate și rescrie `storage/license.json` (fără cheie în argument; dacă nu există fișier, nu face nimic). Folosit și de **scheduler**.
- Alte îmbunătățiri depind de versiune; vezi changelog / tag-uri pe GitHub.

## Scheduler (cron la 5 minute)

Pachetul înregistrează automat în Laravel **`license-client:sync --quiet-sync`** la **`everyFiveMinutes()`**, cu **`withoutOverlapping(5)`** (minute), ca licența dezactivată la autoritate să se reflecte la client fără acțiune manuală.

Pe server trebuie rulat **scheduler-ul Laravel** (o dată pe minut), de exemplu:

```bash
* * * * * cd /calea/proiectului && php artisan schedule:run >> /dev/null 2>&1
```

Pe **instalare autoritate** (același host ca `Package::authorityUrl()` sau există `storage/keys/private.pem`), comanda **nu** apelează autoritatea (iese imediat).

Manual: `php artisan license-client:sync` (mesaje în consolă) sau `php artisan license-client:sync --quiet-sync`.

## Utilizare / Usage

1. **Prima solicitare:** pe server, din rădăcina proiectului, rulează `php artisan make:license-server` cu o **cheie aleatoare** (orice șir unic); autoritatea primește **domeniul** din `APP_URL`. **După emitere**, aceeași comandă cu **cheia primită** salvează licența definitivă.

```bash
php artisan make:license-server cheie-random
# … după ce primiți cheia de la Hearth / furnizor:
php artisan make:license-server CHEIA-EMISA
```

   Dacă site-ul nu se blochează fără licență și ai **≥ 0.1.1**, rulează **`php artisan license-client:status`**. Pe **0.1.0** verifică `package:discover`, `bootstrap/providers.php` și existența `storage/license.json` manual sau cu `make:license-server --show`.

2. La succes, pachetul salvează `storage/license.json` (criptat).

3. Interfață web **Bootstrap 5** (autonomă, fără `layouts.app`): ruta **`/licenta`** (sau cu prefix din `APP_URL`) — în **versiunile recente**; pe **0.1.0** folosiți CLI-ul. **Solicitare pe domeniu:** buton care trimite `POST /licenta/solicita` (cheie provizorie generată pe server dacă nu există încă una salvată; altfel reîntreabă autoritatea pentru **stadiu**). Alternativ: `make:license-server` + cheie aleatoare din SSH. **Activare** cu cheia emisă: formular sau din nou comanda. Nu există link public către portalul autorității; endpoint-urile rămân în cod (`Package`).

4. Middleware-ul `EnsureHasValidLicense` este înregistrat automat de provider (global pe kernel). Fără licență validă, cererile **HTML** sunt redirecționate spre pagina de activare; răspunsurile **JSON** pot primi **403** cu `license_code` (excepții: consolă, mod autoritate, rute whitelist — vezi [Notă enforcement](#notă-enforcement)).

## Cum funcționează (Principiul "ping-pong")

1. **Clientul** trimite către autoritate (hearth.scmc.ro) **domeniul** (din `APP_URL`) și o **cheie** — la prima solicitare cheia poate fi **aleatoare**; după emitere se folosește cheia primită:
   ```bash
   php artisan make:license-server CHEIE-ALEATOARE-SAU-EMISA
   ```
2. **Autoritatea** verifică cheia și domeniul:
   - Dacă licența este validă, răspunde cu un payload semnat și criptat, ce conține metadatele licenței.
   - Dacă licența nu există sau necesită aprobare, răspunde cu un mesaj de pending/în așteptare.
   - Dacă licența este invalidă, răspunde cu eroare și motiv.
3. **Clientul** verifică semnătura autorității (folosind cheia publică) și salvează local payload-ul, criptat automat (fără setări suplimentare).
4. **Middleware-ul** pachetului blochează accesul la aplicație până când există o licență validă și verificată local.
5. Poți re-verifica oricând licența locală cu autoritatea (din UI sau CLI) pentru a actualiza statusul.

Acest flux asigură că doar licențele validate de autoritate pot debloca aplicația, iar orice modificare locală este detectată și blocată.

## Flux

1. **Clientul** (comandă/Interfață): Trimite cheie + domeniu către autoritate
2. **Autoritatea** (hearth.scmc.ro): Răspunde cu status (valid/pending/invalid) + semnătură
3. **Clientul**: Verifică semnătura, salvează local fișierul de licență criptat
4. **Middleware**: Verifică la fiecare request dacă licența este validă
5. **Aplicația Laravel**: Permite acces doar dacă licența este validă

Flux simplificat:

Client → Autoritate → Client → Middleware → Aplicație

- **Prima cerere (domeniu):** `make:license-server` + cheie provizorie (SSH) → răspuns semnat (pending/valid) → salvare locală dacă se returnează payload
- **Activare finală:** aceeași comandă sau UI cu cheia emisă → enforcement → acces

## Detalii suplimentare

- **Verificare periodică:** Poți re-verifica licența oricând (din UI sau CLI) pentru a actualiza statusul fără a reinstala.
- **Pending/În aprobare:** Dacă autoritatea răspunde cu pending, aplicația va afișa statusul "În aprobare" și va bloca funcționalitatea până la aprobare.
- **Securitate:** Orice modificare manuală a fișierului de licență va fi detectată și va bloca accesul.
- **Push automat:** Autoritatea poate trimite licențe noi/actualizate direct către endpoint-ul clientului.
- **Fără configurare la client:** Pachetul este blocat; nu există `config/license-client.php` și nu se pot schimba endpoint-uri sau comportamente. Singurul lucru setabil este cheia de licență, introdusă din UI sau CLI.
- **Debug:** Mesajele de la autoritate sunt afișate clar în UI pentru transparență.

## Securitate / Security

- Licența este salvată local, criptată în mod implicit; nu sunt necesare parole sau variabile suplimentare.
- Cheia publică a autorității este preluată automat de la: `https://hearth.scmc.ro/keys/pem`.

## Notă enforcement

- Middleware-ul de enforcement nu poate fi dezactivat. Fără licență validă, cererile **HTML** sunt **redirecționate** către pagina de activare (ruta `license-client.licenta.index`, calea: `Package::licenseActivationBasePath()`); cererile care **așteaptă JSON** (`Accept: application/json` / `expectsJson()`, tipic API-uri, **Inertia**, unele SPA) primesc **403** cu corp și headere stabile (vezi mai jos). Cu licență activă, `/licenta` redirecționează la `/`. Alte rute permise implicit: health, JWKS, push-license etc. (vezi `Package::whitelist()`).

### Contract stabil: HTTP 403 JSON (blocare licență)

Pentru frontend-uri **SPA / Inertia / API**, tratați uniform răspunsul **403**:

**Corp JSON** (câmpurile `message` și `license_code` sunt mereu prezente):

```json
{
  "message": "…",
  "license_code": "missing"
}
```

- **`license_code`**: cod intern al stării (ex. `missing`, `invalid`, `not_active`, `expired`, `domain_mismatch`) — același cod ca în `LicenseState::resolve()['code']`.
- **`retry_after`**: opțional; număr întreg de secunde (ex. rate limiting viitor). Dacă lipsește din corp, nu presupuneți retry.

**Headere HTTP** (comune cu răspunsurile de tip „health” unde se expune starea):

| Header | Rol |
|--------|-----|
| **`X-License-Code`** | Același cod ca `license_code` din corp (comod pentru interceptor fără parsare JSON). |
| **`X-License-Ok`** | `0` la blocare. |

Constantele din cod: `Hearth\LicenseClient\Package::HEADER_LICENSE_CODE`, `Package::HEADER_LICENSE_OK`. Helper corp: `Package::licenseForbiddenJsonBody()`.

Dacă în viitor apare **`retry_after`** în corp, un client poate folosi și header-ul standard **`Retry-After`** când este trimis de server (alinieri viitoare); până atunci, bazați-vă pe corpul JSON de mai sus.

## Testare (pachet)

În monorepo-ul pachetului, după `composer install`:

```bash
composer test
```

Se folosește **Orchestra Testbench** și PHPUnit. În **`phpunit.xml`** este setat **`HEARTH_SKIP_JWKS_BOOT=1`** ca pachetul să nu depindă de JWKS live la boot în timpul testelor (util și în CI pentru suite rapide și deterministe), iar **`APP_KEY`** în format **`base64:…`** (32 octeți) pentru ca cifrarea Laravel din timpul request-urilor de test să fie validă. Pentru integrare reală contra autorității, rulați **`license-client:probe`** în mediul țintă.

## Linkuri utile

- [GitHub: master-data-ro/hearth-license-client](https://github.com/master-data-ro/hearth-license-client)

---
© 2025-2026 master-data.ro
