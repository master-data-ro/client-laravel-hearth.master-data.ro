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
| Verifică cheia la Hearth și salvează `storage/license.json` | `php artisan make:license-server CHEIA-TA` |
| Afișează licența salvată (decrypt, JSON în consolă) | `php artisan make:license-server --show` |

Opțional: `--passphrase=` pentru derivarea cheii de criptare (implicit se folosește `APP_KEY` / fluxul din `Encryption`).

## Comenzi `license-client:*` (doar ≥ 0.1.1)

- **`php artisan license-client:status`** — diagnostic (manifest, provider, cache rute, cale UI `/licenta`).
- Alte îmbunătățiri (UI `/licenta`, redirect enforcement etc.) depind de versiune; vezi changelog / tag-uri pe GitHub.

## Utilizare / Usage

1. Validează o cheie și salvează local (contactează autoritatea):

```bash
php artisan make:license-server LICENTA-TA
```

   Dacă site-ul nu se blochează fără licență și ai **≥ 0.1.1**, rulează **`php artisan license-client:status`**. Pe **0.1.0** verifică `package:discover`, `bootstrap/providers.php` și existența `storage/license.json` manual sau cu `make:license-server --show`.

2. La succes, pachetul salvează `storage/license.json` (criptat).

3. Interfață web **Bootstrap 5** (autonomă, fără `layouts.app`): ruta **`/licenta`** (sau cu prefix din `APP_URL` în subdirector) — disponibilă în **versiunile recente** ale pachetului; pe **0.1.0** folosiți în principal CLI-ul `make:license-server`. Pagina include status, **solicitare licență** (e-mail precompletat dacă setezi `LICENSE_REQUEST_EMAIL` în `.env`) și activare cheie (**doar** când licența nu e validă; cu licență activă se redirecționează la `/`). Nu există link public către portalul autorității; endpoint-urile rămân în cod (`Package`).

4. Middleware-ul `EnsureHasValidLicense` este înregistrat automat de provider (global pe kernel). Fără licență validă, cererile **HTML** sunt redirecționate spre pagina de activare; răspunsurile **JSON** pot primi **403** cu `license_code` (excepții: consolă, mod autoritate, rute whitelist — vezi [Notă enforcement](#notă-enforcement)).

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
