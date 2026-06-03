<?php

namespace App\Services\Kardal;

/**
 * High-level Kardal operations used by the Nike-store demo.
 *
 * Payloads are FLAT (seller_code, out_trade_no, ... at top level) to match the
 * signature computed by KardalClient::sign() and the working web-demo reference.
 */
class KardalPaymentService
{
    public function __construct(private readonly KardalClient $client) {}

    /**
     * KHQR (KESSKHQR) — generate a dynamic QR via nativePay.
     * Returns the gateway `data` (qrcode, expires_in, order_info, ...).
     */
    public function createKhqr(string $outTradeNo, float $amount, string $currency, string $body): array
    {
        return $this->client->gateway($this->base($outTradeNo, $amount, $currency, $body) + [
            'service'      => 'webpay.acquire.nativePay',
            'service_code' => config('kardal.khqr_service_code', 'KHQR'),
        ]);
    }

    /**
     * Hosted payment link (createOrder) — returns a Kardal-hosted page URL the
     * buyer is redirected to. Buyer picks any enabled method there.
     * Returns the gateway `data` (payment_link, token, out_trade_no, ...).
     */
    public function createPaymentLink(string $outTradeNo, float $amount, string $currency, string $body, ?string $redirectUrl = null): array
    {
        $payload = $this->base($outTradeNo, $amount, $currency, $body) + [
            'service'    => 'webpay.acquire.createOrder',
            'login_type' => 'ANONYMOUS',
            'expires_in' => 12000,
        ];

        if ($redirectUrl) {
            $payload['redirect_url'] = $redirectUrl;   // bring buyer back to the order page
        }

        return $this->client->gateway($payload);
    }

    /** List the payment methods (BICs) actually enabled for this merchant. */
    public function listPaymentMethods(): array
    {
        return $this->client->gateway(['service' => 'webpay.acquire.getpaymentmethods']);
    }

    /**
     * Card (VISA_MASTER) — directPay with RSA-encrypted card + customer blobs.
     * Returns `data` including required_3ds + html_confirm_payment when 3DS applies.
     */
    public function createCardPayment(
        string $outTradeNo,
        float $amount,
        string $currency,
        string $body,
        array $card,
        array $customer,
        ?string $ipAddress = null
    ): array {
        $payload = $this->base($outTradeNo, $amount, $currency, $body) + [
            'service'    => 'webpay.acquire.directPay',
            'card'       => $this->client->encrypt([
                'number'       => $card['number'],
                'securityCode' => $card['securityCode'],
                'expiry'       => ['month' => $card['month'], 'year' => $card['year']],
            ]),
            'customer'   => $this->client->encrypt($customer),
            'ip_address' => $ipAddress,
        ];

        // service_code optional; gateway defaults to VISA_MASTER. Send only when overridden.
        $serviceCode = config('kardal.card_service_code');
        if (! empty($serviceCode)) {
            $payload['service_code'] = $serviceCode;
        }

        return $this->client->gateway($payload);
    }

    /**
     * Query an order's current status by out_trade_no.
     */
    public function queryOrder(string $outTradeNo): array
    {
        return $this->client->gateway([
            'service'      => 'webpay.acquire.queryOrder',
            'out_trade_no' => $outTradeNo,
            'seller_code'  => config('kardal.seller_code'),
        ]);
    }

    private function base(string $outTradeNo, float $amount, string $currency, string $body): array
    {
        return [
            'seller_code'  => config('kardal.seller_code'),
            'out_trade_no' => $outTradeNo,
            'body'         => $body,
            'total_amount' => $amount,
            'currency'     => $currency,
            'notify_url'   => config('kardal.notify_url'),
            'redirect_url' => config('kardal.redirect_url'),
        ];
    }
}
