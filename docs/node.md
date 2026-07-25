# Hearth Node SDK

## Instalare

```bash
npm install node-fetch
```

## Utilizare

```js
const HearthClient = require('./index');
(async () => {
  const client = new HearthClient('https://hearth.scmc.ro');
  const challenge = await client.getChallenge();
  console.log(challenge);
})();
```

## Note
- SDK-ul este minimal, pentru exemple și testare rapidă.
- Pentru producție, implementează gestionare erori, retries, și stocare sigură a token-urilor.
