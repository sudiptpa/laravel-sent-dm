<?php

declare(strict_types=1);

namespace Sujip\SentDm\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies Sent.dm webhook signatures.
 *
 * Algorithm (verified against docs.sent.dm/start/webhooks/signature-verification):
 *   1. Strip "whsec_" prefix from the signing secret, base64-decode the rest.
 *   2. Build signing string: "{webhookId}.{timestamp}.{rawBody}".
 *   3. HMAC-SHA256 with the decoded key, base64-encode, prepend "v1,".
 *   4. Timing-safe compare against the x-webhook-signature header.
 *   5. Reject if the timestamp is outside the 5-minute replay window.
 */
class VerifySignature
{
    private const TOLERANCE = 300;

    public const VALID = 0;

    public const INVALID_SIGNATURE = 1;

    public const STALE_TIMESTAMP = 2;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('sent.webhook.secret');
        $secret = is_string($configured) ? $configured : '';

        if ($secret === '') {
            return response()->json(['message' => 'Webhook secret is not configured.'], 500);
        }

        $signature = $this->header($request, 'x-webhook-signature');
        $webhookId = $this->header($request, 'x-webhook-id');
        $timestamp = $this->header($request, 'x-webhook-timestamp');

        if ($signature === '' || $webhookId === '' || $timestamp === '') {
            return response()->json(['message' => 'Missing signature headers.'], 401);
        }

        $key = $this->decodeSecret($secret);

        if ($key === null) {
            return response()->json(['message' => 'Webhook secret is malformed.'], 500);
        }

        $result = $this->verify($key, $webhookId, $timestamp, $request->getContent(), $signature);

        if ($result === self::INVALID_SIGNATURE) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if ($result === self::STALE_TIMESTAMP) {
            return response()->json(['message' => 'Signature timestamp is outside the tolerance window.'], 401);
        }

        return $next($request);
    }

    /**
     * $key must already be decoded (see decodeSecret()), not the raw "whsec_..." value.
     */
    public function verify(string $key, string $webhookId, string $timestamp, string $rawBody, string $signature): int
    {
        $signingString = $webhookId.'.'.$timestamp.'.'.$rawBody;
        $expected = 'v1,'.base64_encode(hash_hmac('sha256', $signingString, $key, true));

        if (! hash_equals($expected, $signature)) {
            return self::INVALID_SIGNATURE;
        }

        if (! $this->timestampIsFresh($timestamp)) {
            return self::STALE_TIMESTAMP;
        }

        return self::VALID;
    }

    private function header(Request $request, string $key): string
    {
        $value = $request->header($key);

        return is_string($value) ? $value : '';
    }

    public function decodeSecret(string $secret): ?string
    {
        if (! str_starts_with($secret, 'whsec_')) {
            return $secret;
        }

        $decoded = base64_decode(substr($secret, 6), true);

        return $decoded !== false ? $decoded : null;
    }

    private function timestampIsFresh(string $timestamp): bool
    {
        if (! is_numeric($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= self::TOLERANCE;
    }
}
