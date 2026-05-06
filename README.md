# hearth.master-data.ro client

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

1. Verifică versia instalată: `composer show hearth/license-client` (sau în `composer.lock` câmpul `version`).
2. Dacă vezi **`0.1.0`**, actualizează constrângerea în **`composer.json`** la minim **`^0.1.1`**, apoi `composer update hearth/license-client`.
3. Rulează **`php artisan package:discover`** și **`php artisan optimize:clear`**. Dacă providerul nu e descoperit, adaugă-l manual în `bootstrap/providers.php` (vezi mai jos).

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

După actualizare, diagnosticul:

```bash
php artisan license-client:status
```

### 404 pe `/licenta` sau pagina UI nu se încarcă

1. **Cache rute:** dacă rulezi `php artisan route:cache`, rutele pachetului trebuie incluse în acel cache. După `composer update` / prima instalare a pachetului, un cache vechi poate să nu conțină rutele — rulează **`php artisan route:clear`** (sau regenerează `route:cache` după ce pachetul e instalat).
2. **Subdirector:** dacă aplicația e servită sub un path (ex. `https://exemplu.ro/myapp`), setează **`APP_URL`** (și `config('app.url')`) la URL-ul complet **cu** acel path. Rutele UI se înregistrează atunci la `…/myapp/licenta` (nu la rădăcină domeniului). Verifică cu `php artisan license-client:status` câmpul „Cale UI activare licență”.
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

## Utilizare / Usage

1. Rulează comanda artisan pentru a valida o cheie de licență (va contacta autoritatea):

```bash
php artisan make:license-server LICENTA-TA
```

   Dacă site-ul nu se blochează fără licență, rulează diagnosticul:

```bash
php artisan license-client:status
```

2. La succes, pachetul va salva fișierul `storage/license.json` cu metadatele licenței (criptat).

3. Interfață web **Bootstrap 5** (autonomă, fără `layouts.app`): **`/licenta`** (sau `{prefix-din-APP_URL}/licenta` în subdirector) — status, **solicitare licență** (e-mail precompletat dacă setezi opțional `LICENSE_REQUEST_EMAIL` în `.env`) și activare cheie (accesibilă **doar când licența nu e validă**; cu licență activă se redirecționează la `/`). Nu există link public către portalul autorității; endpoint-ul rămâne doar în cod (`Package`).

4. Middleware-ul `EnsureHasValidLicense` este înregistrat automat de provider (global pe kernel). Aplicația răspunde cu HTTP 403 până există o licență validă în `storage/license.json` (excepții: consolă, mod autoritate, rute whitelist).

## Cum funcționează (Principiul "ping-pong")

1. **Clientul** (aplicația ta) trimite cheia de licență și domeniul către autoritate (hearth.master-data.ro) folosind comanda:
   ```bash
   php artisan make:license-server YOUR-LICENSE-KEY
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
2. **Autoritatea** (hearth.master-data.ro): Răspunde cu status (valid/pending/invalid) + semnătură
3. **Clientul**: Verifică semnătura, salvează local fișierul de licență criptat
4. **Middleware**: Verifică la fiecare request dacă licența este validă
5. **Aplicația Laravel**: Permite acces doar dacă licența este validă

Flux simplificat:

Client → Autoritate → Client → Middleware → Aplicație

- Cerere licență   →   Răspuns semnat   →   Salvare locală   →   Enforcement   →   Acces

## Detalii suplimentare

- **Verificare periodică:** Poți re-verifica licența oricând (din UI sau CLI) pentru a actualiza statusul fără a reinstala.
- **Pending/În aprobare:** Dacă autoritatea răspunde cu pending, aplicația va afișa statusul "În aprobare" și va bloca funcționalitatea până la aprobare.
- **Securitate:** Orice modificare manuală a fișierului de licență va fi detectată și va bloca accesul.
- **Push automat:** Autoritatea poate trimite licențe noi/actualizate direct către endpoint-ul clientului.
- **Fără configurare la client:** Pachetul este blocat; nu există `config/license-client.php` și nu se pot schimba endpoint-uri sau comportamente. Singurul lucru setabil este cheia de licență, introdusă din UI sau CLI.
- **Debug:** Mesajele de la autoritate sunt afișate clar în UI pentru transparență.

## Securitate / Security

- Licența este salvată local, criptată în mod implicit; nu sunt necesare parole sau variabile suplimentare.
- Cheia publică a autorității este preluată automat de la: `https://hearth.master-data.ro/keys/pem`.

## Notă enforcement

- Middleware-ul de enforcement nu poate fi dezactivat. Fără licență validă, cererile **HTML** sunt **redirecționate** către pagina de activare (ruta `license-client.licenta.index`, calea: `Package::licenseActivationBasePath()`); cererile **`Accept: application/json`** primesc **403** JSON (`message`, `license_code`). Cu licență activă, `/licenta` redirecționează la `/`. Alte rute permise implicit: health, JWKS, push-license etc. (vezi `Package::whitelist()`).

## Linkuri utile

- [GitHub: master-data-ro/hearth-license-client](https://github.com/master-data-ro/hearth-license-client)

---
© 2025-2026 master-data.ro
