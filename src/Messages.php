<?php

namespace Hearth\LicenseClient;

/**
 * Simple, non-configurable package messages.
 * Keep messages here so applications cannot easily override the blocked text.
 */
class Messages
{
    protected static array $messages = [
        'not_present' => 'Nu există încă o licență salvată pe acest server. Activați aplicația din pagina dedicată, cu cheia primită de la administrator.',
        'invalid' => 'Licența instalată pare a fi invalidă sau coruptă. Vă rugăm să reinstalați licența.',
        'not_active' => 'Licența nu este activă. Activați licența sau contactați suportul.',
        'domain_mismatch' => 'Această licență nu este valabilă pentru acest domeniu.',
        'expired' => 'Licența dumneavoastră a expirat. Vă rugăm să reînnoiți licența pentru a continua.',
    ];

    public static function get(string $key): string
    {
        return static::$messages[$key] ?? static::$messages['invalid'];
    }

    /**
     * Titlu scurt pentru pagina 403 (cod din LicenseState::CODE_*).
     */
    public static function blockHeadline(string $licenseCode): string
    {
        return match ($licenseCode) {
            'missing' => 'Nu aveți licență instalată',
            'invalid' => 'Licența instalată nu este utilizabilă',
            'not_active' => 'Licența nu este activă',
            'expired' => 'Licența a expirat',
            'domain_mismatch' => 'Licența nu se potrivește cu acest site',
            default => 'Acces restricționat',
        };
    }

    /**
     * Indiciu scurt sub titlu (fără URL-uri externe).
     */
    public static function blockHint(string $licenseCode): string
    {
        return match ($licenseCode) {
            'missing' => 'Folosiți „Solicită licența pe domeniu” în pagina de licență sau `php artisan make:license-server` cu cheie aleatoare, apoi activați cu cheia emisă.',
            'invalid' => 'Ștergeți fișierul vechi (dacă există) și introduceți din nou o cheie validă.',
            'not_active' => 'Așteptați activarea sau reintroduceți cheia după ce a fost emisă.',
            'expired' => 'Obțineți o perioadă nouă de valabilitate și actualizați licența.',
            'domain_mismatch' => 'Folosiți licența emisă pentru domeniul configurat în această instalare.',
            default => 'Rezolvați problema de licență pentru a continua.',
        };
    }

    /**
     * Titlu scurt pentru panoul de status pe pagina /licenta (cod LicenseState).
     */
    public static function portalEnforcementTitle(string $licenseCode): string
    {
        return match ($licenseCode) {
            'missing' => 'Licență neinstalată',
            'invalid' => 'Date de licență neutilizabile',
            'not_active' => 'Licență în așteptare sau neactivă',
            'expired' => 'Licență expirată',
            'domain_mismatch' => 'Neconcordanță domeniu',
            'ok' => 'Licență activă',
            default => 'Stare licență',
        };
    }

    /**
     * Descriere pentru utilizatorul business pe pagina /licenta.
     */
    public static function portalEnforcementDescription(string $licenseCode): string
    {
        return match ($licenseCode) {
            'missing' => 'Această instalare nu are încă o licență salvată. Solicitarea pe domeniu: din pagina /licenta folosiți „Solicită licența pe domeniu” sau rulați `php artisan make:license-server` cu o cheie aleatoare; apoi activați cu cheia emisă.',
            'invalid' => 'Fișierul de licență local nu poate fi citit corect. Ștergeți instalarea curentă și solicitați asistență sau o cheie nouă.',
            'not_active' => 'Cheia a fost înregistrată, dar licența nu este încă activă pe serverul de licențiere (ex.: în curs de emitere sau aprobare). Re-verificați periodic sau contactați furnizorul.',
            'expired' => 'Perioada de valabilitate s-a încheiat. Solicitați reînnoirea și actualizați cheia sau datele primite de la furnizor.',
            'domain_mismatch' => 'Licența emisă nu corespunde domeniului acestei instalări. Solicitați o licență pentru domeniul afișat în secțiunea de solicitare (din APP_URL).',
            'ok' => 'Licența este validă; veți fi redirecționat către aplicație.',
            default => 'Consultați detaliile de mai jos sau contactați furnizorul aplicației.',
        };
    }
}
