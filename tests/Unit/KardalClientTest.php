<?php

namespace Tests\Unit;

use App\Services\Kardal\KardalClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KardalClientTest extends TestCase
{
    public function test_ecommerce_access_token_accepts_gateway_camel_case_envelope(): void
    {
        Cache::flush();
        Http::fake([
            'http://gateway.test/gateway/oauth2/token' => Http::response([
                'success' => true,
                'data'    => [
                    'item' => [
                        'accessToken' => 'gateway-token-123',
                        'expiresIn'   => 1800,
                    ],
                ],
            ]),
        ]);

        $client = new KardalClient($this->config());

        $this->assertSame('gateway-token-123', $client->ecommerceAccessToken());

        Http::assertSent(function ($request) {
            return $request->url() === 'http://gateway.test/gateway/oauth2/token'
                && $request['grant_type'] === 'client_credentials'
                && $request['scope'] === 'merchant.ecommerce.payment:create';
        });
    }

    public function test_ecommerce_access_token_still_accepts_snake_case_envelope(): void
    {
        Cache::flush();
        Http::fake([
            'http://gateway.test/gateway/oauth2/token' => Http::response([
                'success' => true,
                'data'    => [
                    'item' => [
                        'access_token' => 'gateway-token-snake',
                        'expires_in'   => 1800,
                    ],
                ],
            ]),
        ]);

        $client = new KardalClient($this->config());

        $this->assertSame('gateway-token-snake', $client->ecommerceAccessToken());
    }

    public function test_ecommerce_gateway_signs_with_api_key(): void
    {
        Cache::flush();
        Http::fake([
            'http://gateway.test/gateway/oauth2/token' => Http::response([
                'success' => true,
                'data'    => [
                    'item' => [
                        'accessToken' => 'gateway-token-123',
                        'expiresIn'   => 1800,
                    ],
                ],
            ]),
            'http://gateway.test/api/gateway/v1/ecommerce' => Http::response([
                'success' => true,
            ]),
        ]);

        $client = new KardalClient($this->config());
        $payload = [
            'merchantKey' => 'merchant-key',
            'outTradeNo' => 'ORDER-1',
            'service' => 'nativePay',
        ];

        $client->ecommerceGateway($payload, 'idem-1');

        Http::assertSent(function ($request) use ($payload) {
            if ($request->url() !== 'http://gateway.test/api/gateway/v1/ecommerce') {
                return false;
            }

            $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
            return $request->hasHeader('request-signature', hash_hmac('sha256', $body, 'merchant-api-key'));
        });
    }

    public function test_ecommerce_checkout_status_signs_order_key_with_api_key(): void
    {
        Http::fake([
            'http://gateway.test/api/gateway/v1/ecommerce/checkout/checkout-key-1/status' => Http::response([
                'success' => true,
            ]),
        ]);

        $client = new KardalClient($this->config());

        $client->ecommerceCheckoutStatus('checkout-key-1');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://gateway.test/api/gateway/v1/ecommerce/checkout/checkout-key-1/status'
                && $request->hasHeader('request-signature', hash_hmac('sha256', 'checkout-key-1', 'merchant-api-key'));
        });
    }

    private function config(): array
    {
        return [
            'base_url' => '',
            'oauth' => [
                'grant_type' => 'password',
                'client_id' => null,
                'client_secret' => null,
                'username' => null,
                'password' => null,
            ],
            'api_key' => 'merchant-api-key',
            'sign_type' => 'MD5',
            'public_key_path' => '',
            'public_key' => '',
            'token_ttl' => 1500,
            'ecommerce' => [
                'base_url' => 'http://gateway.test',
                'client_id' => 'merchant-client',
                'client_secret' => 'merchant-secret',
                'scope' => 'merchant.ecommerce.payment:create',
                'merchant_key' => 'merchant-key',
                'token_ttl' => 3000,
            ],
        ];
    }
}
