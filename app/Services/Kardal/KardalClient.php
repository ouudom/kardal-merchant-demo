<?php

namespace App\Services\Kardal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Low-level Kardal clients: legacy WebPay gateway and new Gateway ecommerce.
 * All secrets stay server-side.
 */
class KardalClient
{
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Fetch (and cache) an OAuth access token via the password grant.
     */
    public function accessToken(): string
    {
        if ($this->config['base_url'] === '') {
            throw new RuntimeException('KARDAL_BASE_URL is not configured.');
        }

        $cached = Cache::get('kardal.access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asJson()
            ->acceptJson()
            ->post($this->config['base_url'] . '/oauth/token', [
                'grant_type'    => $this->config['oauth']['grant_type'],
                'client_id'     => $this->config['oauth']['client_id'],
                'client_secret' => $this->config['oauth']['client_secret'],
                'username'      => $this->config['oauth']['username'],
                'password'      => $this->config['oauth']['password'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Kardal auth failed: ' . $response->body());
        }

        $token = $response->json('access_token') ?? $response->json('token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Kardal auth response missing access_token.');
        }

        // Cache for the token's real lifetime minus a 60s safety buffer, capped
        // by the configured TTL. Avoids serving a token the gateway already
        // expired (→ 401 Unauthenticated).
        $expiresIn = (int) ($response->json('expires_in') ?? 0);
        $ttl = $expiresIn > 0
            ? max(60, min((int) $this->config['token_ttl'], $expiresIn - 60))
            : (int) $this->config['token_ttl'];

        Cache::put('kardal.access_token', $token, $ttl);

        return $token;
    }

    private function forgetToken(): void
    {
        Cache::forget('kardal.access_token');
    }

    /**
     * Fetch (and cache) a Gateway OAuth token via client_credentials.
     */
    public function ecommerceAccessToken(): string
    {
        $clientId = (string) ($this->config['ecommerce']['client_id'] ?? '');
        $cacheKey = 'kardal.ecommerce.access_token.' . sha1($clientId);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $ecommerce = $this->ecommerceConfig();
        $response = Http::asForm()
            ->acceptJson()
            ->withBasicAuth($ecommerce['client_id'], $ecommerce['client_secret'])
            ->post($ecommerce['base_url'] . '/gateway/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope'      => $ecommerce['scope'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Kardal ecommerce auth failed: ' . $response->body());
        }

        $token = $this->tokenFromResponse($response->json());
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Kardal ecommerce auth response missing access_token.');
        }

        $expiresIn = (int) ($response->json('data.item.expires_in') ?? $response->json('expires_in') ?? 0);
        $ttl = $expiresIn > 0
            ? max(60, min((int) $ecommerce['token_ttl'], $expiresIn - 60))
            : (int) $ecommerce['token_ttl'];

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    private function forgetEcommerceToken(): void
    {
        $clientId = (string) ($this->config['ecommerce']['client_id'] ?? '');
        Cache::forget('kardal.ecommerce.access_token.' . sha1($clientId));
    }

    /**
     * Sign a flat payload: sort by key asc, build k=v&..., append &key={api_key},
     * then MD5 or HMAC-SHA256. Mirrors the gateway's verification.
     */
    public function sign(array $params): string
    {
        $params = array_filter(
            $params,
            fn ($v, $k) => $k !== 'sign' && $v !== '' && $v !== null && ! is_array($v) && ! is_object($v),
            ARRAY_FILTER_USE_BOTH
        );
        ksort($params);

        $pairs = [];
        foreach ($params as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        $base = implode('&', $pairs) . '&key=' . $this->config['api_key'];

        return ($this->config['sign_type'] === 'HMAC-SHA256')
            ? hash_hmac('sha256', $base, $this->config['api_key'])
            : md5($base);
    }

    /**
     * Sign + POST a gateway request. Returns the decoded `data` payload.
     *
     * @param  array  $payload  Flat payload (service, seller_code, etc.) WITHOUT sign.
     */
    public function gateway(array $payload): array
    {
        $payload['sign_type'] = $payload['sign_type'] ?? $this->config['sign_type'];
        $payload['sign'] = $this->sign($payload);

        $post = fn () => Http::withToken($this->accessToken())
            ->asJson()
            ->acceptJson()
            ->post($this->config['base_url'] . '/api/mch/v2/gateway', $payload);

        $response = $post();

        // Stale/expired token → drop it and retry once with a fresh token.
        if ($response->status() === 401) {
            $this->forgetToken();
            $response = $post();
        }

        if ($response->failed()) {
            throw new RuntimeException('Kardal gateway error (' . $response->status() . '): ' . $response->body());
        }

        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? false) !== true) {
            throw new RuntimeException('Kardal gateway returned failure: ' . $response->body());
        }

        return $body['data'] ?? [];
    }

    /**
     * Sign raw JSON and POST the new ecommerce gateway endpoint.
     */
    public function ecommerceGateway(array $payload, string $idempotencyKey): array
    {
        $ecommerce = $this->ecommerceConfig();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! is_string($body)) {
            throw new RuntimeException('Failed to encode ecommerce payload.');
        }

        $signature = hash_hmac('sha256', $body, $ecommerce['signature_secret']);

        $post = fn () => Http::withToken($this->ecommerceAccessToken())
            ->acceptJson()
            ->withHeaders([
                'request-signature' => $signature,
                'Idempotency-Key'   => $idempotencyKey,
            ])
            ->withBody($body, 'application/json')
            ->post($ecommerce['base_url'] . '/api/ecommerce/v1/gateway');

        $response = $post();

        if ($response->status() === 401) {
            $this->forgetEcommerceToken();
            $response = $post();
        }

        if ($response->failed()) {
            throw new RuntimeException('Kardal ecommerce gateway error (' . $response->status() . '): ' . $response->body());
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new RuntimeException('Kardal ecommerce gateway returned invalid JSON: ' . $response->body());
        }

        return $body;
    }

    /**
     * RSA-encrypt a JSON object with the merchant public key, hex-encoded.
     * Used for the card + customer blobs in directPay.
     */
    public function encrypt(array $data): string
    {
        $pem = $this->publicKey();
        $ok = openssl_public_encrypt(
            json_encode($data, JSON_UNESCAPED_SLASHES),
            $encrypted,
            $pem
        );
        if (! $ok) {
            throw new RuntimeException('Card/customer encryption failed: ' . (openssl_error_string() ?: 'unknown'));
        }

        return bin2hex($encrypted);
    }

    private function publicKey(): string
    {
        $inline = $this->config['public_key'];
        if (is_string($inline) && trim($inline) !== '') {
            return str_replace(['\\n', '\\r'], ["\n", "\r"], trim($inline));
        }

        $path = $this->config['public_key_path'];
        if (! is_string($path) || ! is_readable($path)) {
            throw new RuntimeException('Merchant public key not found. Set KARDAL_PUBLIC_KEY or place the file at ' . $path);
        }

        return (string) file_get_contents($path);
    }

    private function ecommerceConfig(): array
    {
        $ecommerce = $this->config['ecommerce'] ?? [];
        foreach (['base_url', 'client_id', 'client_secret', 'scope', 'signature_secret', 'core_merchant_key'] as $key) {
            if (($ecommerce[$key] ?? '') === '') {
                throw new RuntimeException('KARDAL ecommerce config missing: ' . $key);
            }
        }

        return $ecommerce;
    }

    private function tokenFromResponse(?array $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        return $body['data']['item']['access_token']
            ?? $body['access_token']
            ?? $body['token']
            ?? null;
    }
}
