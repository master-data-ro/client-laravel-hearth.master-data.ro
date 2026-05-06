# hearth.master-data.ro client

[View on GitHub](https://github.com/master-data-ro/hearth-license-client)

## Compatibilitate

- **PHP:** `^8.2` (aliniat cu Laravel 11+).
- **Laravel:** `11.x`, `12.x` și `13.x` (constrângeri explicite pe componentele `illuminate/*` folosite de pachet).
- Auto-descoperire: `extra.laravel.providers` din `composer.json` al pachetului (același mecanism pe 11–13). Dacă `package:discover` nu rulează pe server, vezi secțiunea de troubleshooting de mai jos.

## Instalare / Installation

Adaugă în `composer.json` sau instalează direct din repository:

```bash
composer require hearth/license-client
```

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

3. Dacă tot nu merge, înregistrează provider-ul **manual** în `bootstrap/providers.php` (Laravel 11, 12, 13):  
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

2. La succes, pachetul va salva fișierul `storage/license.json` cu metadatele licenței (criptat).

3. Middleware-ul `EnsureHasValidLicense` este înregistrat automat de provider (global pe kernel). Aplicația răspunde cu HTTP 403 până există o licență validă în `storage/license.json` (excepții: consolă, mod autoritate, rute whitelist).

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

- Middleware-ul de enforcement nu poate fi dezactivat. Anumite rute sensibile (ex: health, JWKS, interfața de licență) sunt permise implicit de pachet.

## Linkuri utile

- [GitHub: master-data-ro/hearth-license-client](https://github.com/master-data-ro/hearth-license-client)

---
© 2025-2026 master-data.ro
