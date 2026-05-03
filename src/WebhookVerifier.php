<?php

declare(strict_types=1);

namespace Liqaa;

/**
 * Verify HMAC-SHA256 signed webhook deliveries from LIQAA.
 *
 * Headers we send:
 *   X-LIQAA-Signature: t=<unix_ts>,v1=<hex_hmac>
 *
 * The HMAC is computed over the string "{$timestamp}.{$rawBody}" using your
 * webhook signing_secret (returned once when you create the subscription).
 */
final class WebhookVerifier
{
    public function __construct(
        private readonly string $signingSecret,
        private readonly int $replayWindowSeconds = 300,
    ) {}

    /**
     * Returns true iff the signature is valid AND not a replay (older than window).
     */
    public function verify(string $rawBody, string $signatureHeader): bool
    {
        $parts = $this->parseHeader($signatureHeader);
        if ($parts === null) {
            return false;
        }
        [$timestamp, $received] = $parts;

        // Replay protection.
        if (abs(time() - $timestamp) > $this->replayWindowSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->signingSecret);

        return hash_equals($expected, $received);
    }

    /**
     * @return array{0: int, 1: string}|null  [timestamp, received_signature]
     */
    private function parseHeader(string $header): ?array
    {
        if ($header === '') {
            return null;
        }

        $parts = [];
        foreach (explode(',', $header) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || ! str_contains($segment, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $segment, 2);
            $parts[trim($k)] = trim($v);
        }

        if (! isset($parts['t'], $parts['v1'])) {
            return null;
        }
        if (! ctype_digit($parts['t'])) {
            return null;
        }

        return [(int) $parts['t'], $parts['v1']];
    }
}
