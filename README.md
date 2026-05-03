<div align="center">

# LIQAA PHP SDK

**Server-side PHP client for the LIQAA Public API.**

[![packagist](https://img.shields.io/packagist/v/liqaa/liqaa.svg?style=flat-square&color=1d4ed8)](https://packagist.org/packages/liqaa/liqaa)
[![php](https://img.shields.io/badge/php-%3E%3D8.1-777bb4?style=flat-square)](https://php.net)
[![license](https://img.shields.io/badge/license-MIT-475569.svg?style=flat-square)](./LICENSE)
[![docs](https://img.shields.io/badge/docs-liqaa.io-1d4ed8.svg?style=flat-square)](https://liqaa.io/docs)

[Website](https://liqaa.io) · [Docs](https://liqaa.io/docs) · [JS SDK](https://github.com/hartemyaakoub/liqaa-js)

</div>

---

## Install

```bash
composer require liqaa/liqaa
```

Requires PHP 8.1+ and the `curl`, `json`, `hash` extensions (all enabled by default in modern PHP).

## Quick start

### 1) Exchange identity for an SDK token

Sign your authenticated user's identity with `sk_live_…` and get back a 1-hour browser-safe JWT.

```php
use Liqaa\LiqaaClient;

$client = new LiqaaClient(
    publicKey: $_ENV['LIQAA_PK'],
    secretKey: $_ENV['LIQAA_SK'],
);

$token = $client->exchangeSdkToken([
    'email' => $user->email,
    'name'  => $user->name,
]);

// Pass $token['sdk_token'] to your frontend.
```

### 2) Create a persistent room (server-to-server)

```php
$conv = $client->createConversation(
    callerEmail: 'agent@yoursite.com',
    callerName:  'Support Agent',
    calleeEmail: 'customer@example.com',
    calleeName:  'Customer X',
    externalConversationId: 'ticket-42',
);

// → $conv['join_url'] e.g. https://liqaa.io/meeting/room-abc123
```

### 3) Verify a webhook signature

```php
use Liqaa\WebhookVerifier;

$verifier = new WebhookVerifier($_ENV['LIQAA_WEBHOOK_SECRET']);
$payload  = file_get_contents('php://input');
$header   = $_SERVER['HTTP_X_LIQAA_SIGNATURE'] ?? '';

if (! $verifier->verify($payload, $header)) {
    http_response_code(401);
    exit;
}
$event = json_decode($payload, true);
// $event['event'] === 'call.started' / 'call.ended' / etc.
```

## Laravel integration

For Laravel apps, [`examples/laravel`](./examples/laravel) ships:

- `LiqaaTokenController` — REST endpoint that exchanges identity for an SDK token
- `WebhookController` — verifies signatures and dispatches Laravel events
- `config/services.php` snippet
- `routes/api.php` snippet

## API surface

| Method                                       | Description                                     |
| -------------------------------------------- | ----------------------------------------------- |
| `LiqaaClient::exchangeSdkToken($identity)`   | Identity → 1-hour JWT for the browser           |
| `LiqaaClient::createConversation(...)`       | Create or reuse a persistent room               |
| `LiqaaClient::getConversation($id)`          | Fetch room state                                |
| `LiqaaClient::endConversation($id)`          | End an active call                              |
| `LiqaaClient::createWebhook($url, $events)`  | Subscribe to events                             |
| `LiqaaClient::listWebhooks()`                | List your subscriptions                         |
| `LiqaaClient::deleteWebhook($id)`            | Cancel a subscription                           |
| `WebhookVerifier::verify($body, $header)`    | Constant-time HMAC verification + replay window |

Full reference at [**liqaa.io/docs**](https://liqaa.io/docs).

## License

[MIT](./LICENSE) © TKAWEN — LIQAA Cloud.
