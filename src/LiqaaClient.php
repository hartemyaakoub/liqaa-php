<?php

declare(strict_types=1);

namespace Liqaa;

use RuntimeException;

/**
 * Server-side PHP client for the LIQAA Public API.
 *
 * Authenticates with sk_live_… (server-only). Never expose secretKey to the browser.
 *
 * @see https://liqaa.io/docs
 */
final class LiqaaClient
{
    private const BASE_URL = 'https://liqaa.io/api/public/v1';

    public function __construct(
        private readonly string $publicKey,
        private readonly string $secretKey,
        private readonly string $baseUrl = self::BASE_URL,
        private readonly int $timeout = 8,
    ) {
        if (! str_starts_with($publicKey, 'pk_')) {
            throw new RuntimeException('publicKey must start with pk_live_ or pk_test_');
        }
        if (! str_starts_with($secretKey, 'sk_')) {
            throw new RuntimeException('secretKey must start with sk_live_ or sk_test_');
        }
    }

    /**
     * Sign a user identity with sk_ and exchange for a 1-hour browser-safe JWT.
     *
     * @param  array{email: string, name?: string}  $identity
     * @return array{sdk_token: string, expires_at: string}
     */
    public function exchangeSdkToken(array $identity): array
    {
        $identity['ts'] = time();
        $identityB64 = base64_encode((string) json_encode($identity, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $identityB64, $this->secretKey);

        return $this->post('/sdk-token', [
            'public_key'      => $this->publicKey,
            'identity_base64' => $identityB64,
            'signature'       => $signature,
        ]);
    }

    /**
     * Create or reuse a persistent room for a (caller, callee, conversation_id) tuple.
     *
     * @return array{ok: bool, room_name: string, join_url: string, ...}
     */
    public function createConversation(
        string $callerEmail,
        ?string $callerName = null,
        ?string $calleeEmail = null,
        ?string $calleeName = null,
        ?string $externalConversationId = null,
        ?string $title = null,
    ): array {
        return $this->post('/conversations', array_filter([
            'caller_email'             => $callerEmail,
            'caller_name'              => $callerName,
            'callee_email'             => $calleeEmail,
            'callee_name'              => $calleeName,
            'external_conversation_id' => $externalConversationId,
            'title'                    => $title,
        ], fn ($v) => $v !== null));
    }

    public function getConversation(string $id): array
    {
        return $this->get("/conversations/{$id}");
    }

    public function endConversation(string $id): void
    {
        $this->delete("/conversations/{$id}");
    }

    /**
     * Subscribe to events. The signing_secret in the response is shown only once — store it.
     *
     * @param  array<string>  $events
     */
    public function createWebhook(string $url, array $events, ?string $description = null): array
    {
        return $this->post('/webhooks', array_filter([
            'url'         => $url,
            'events'      => $events,
            'description' => $description,
        ], fn ($v) => $v !== null));
    }

    /** @return array<int, array<string, mixed>> */
    public function listWebhooks(): array
    {
        return $this->get('/webhooks');
    }

    public function deleteWebhook(int $id): void
    {
        $this->delete("/webhooks/{$id}");
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        return $this->request('POST', $path, $body);
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    private function delete(string $path): void
    {
        $this->request('DELETE', $path);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->baseUrl.$path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer '.$this->secretKey,
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("LIQAA request failed: {$error}");
        }
        if ($status >= 400) {
            throw new RuntimeException("LIQAA API error {$status}: ".substr((string) $raw, 0, 300));
        }
        if ($raw === '' || $raw === '0') {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = (array) json_decode((string) $raw, true);

        return $decoded;
    }
}
