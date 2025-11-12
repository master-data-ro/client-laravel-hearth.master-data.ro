# Hearth PHP SDK

## Utilizare rapidă

```php
require 'HearthClient.php';

// Serverul emite un JWT (RS256) când adminul aprobă licența.
// Clientul primește JWT-ul și îl folosește ca Bearer token pentru API-uri protejate.

$client = new \Hearth\SDK\HearthClient('https://master-data.ro');
$client->setToken('eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...');

// Exemplu request protejat
$res = $client->request('GET', '/api/protected-example');
print_r($res);

// Pentru verificare locală a tokenului, folosește cheia publică (storage/public.pem)
// și o librărie JWT cu suport RS256 sau openssl_verify manual.
```

## Recomandări
- SDK-ul este minimal, pentru exemple și testare rapidă.
- Pentru producție: gestionează erorile, stochează token-urile în siguranță, implementează refresh/revoke.
