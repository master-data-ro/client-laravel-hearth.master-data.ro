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
            'missing' => 'Activați aplicația introducând cheia de licență în pagina dedicată.',
            'invalid' => 'Ștergeți fișierul vechi (dacă există) și introduceți din nou o cheie validă.',
            'not_active' => 'Așteptați activarea sau reintroduceți cheia după ce a fost emisă.',
            'expired' => 'Obțineți o perioadă nouă de valabilitate și actualizați licența.',
            'domain_mismatch' => 'Folosiți licența emisă pentru domeniul configurat în această instalare.',
            default => 'Rezolvați problema de licență pentru a continua.',
        };
    }
}
