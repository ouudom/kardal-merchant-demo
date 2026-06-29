<?php

namespace Tests\Unit;

use App\Services\Kardal\KardalClient;
use App\Services\Kardal\KardalPaymentService;
use Tests\TestCase;

class KardalPaymentServiceTest extends TestCase
{
    public function test_create_khqr_uses_gateway_ecommerce_native_pay(): void
    {
        config()->set('kardal.notify_url', 'https://merchant.test/payment/notify');
        config()->set('kardal.redirect_url', 'https://merchant.test/order/result');
        config()->set('kardal.ecommerce.merchant_key', 'merchant-key-123');

        $client = $this->createMock(KardalClient::class);
        $client->expects($this->once())
            ->method('ecommerceGateway')
            ->with([
                'merchantKey' => 'merchant-key-123',
                'outTradeNo'  => 'order-1001',
                'totalAmount' => 12.34,
                'currency'    => 'USD',
                'body'        => 'Nike Store Order',
                'notifyUrl'   => 'https://merchant.test/payment/notify',
                'redirectUrl' => 'https://merchant.test/order/result',
                'service'     => 'nativePay',
            ], 'order-1001')
            ->willReturn([
                'success' => true,
                'data'    => [
                    'orderKey'   => 'checkout-key-1001',
                    'outTradeNo' => 'order-1001',
                    'status'     => 'WAITING',
                    'result'     => [
                        'qrcode'    => '000201010212',
                        'expiresIn' => 1800,
                    ],
                ],
            ]);

        $service = new KardalPaymentService($client);

        $result = $service->createKhqr('order-1001', 12.34, 'USD', 'Nike Store Order');

        $this->assertSame('000201010212', $result['qrcode']);
        $this->assertSame(1800, $result['expires_in']);
        $this->assertSame('checkout-key-1001', $result['order_info']['token']);
        $this->assertSame('order-1001', $result['order_info']['out_trade_no']);
    }

    public function test_create_payment_link_uses_gateway_ecommerce_create_payment_link(): void
    {
        config()->set('kardal.notify_url', 'https://merchant.test/payment/notify');
        config()->set('kardal.ecommerce.merchant_key', 'merchant-key-456');

        $client = $this->createMock(KardalClient::class);
        $client->expects($this->once())
            ->method('ecommerceGateway')
            ->with([
                'merchantKey' => 'merchant-key-456',
                'outTradeNo'  => 'order-2001',
                'totalAmount' => 18.5,
                'currency'    => 'USD',
                'body'        => 'Nike Store Order',
                'notifyUrl'   => 'https://merchant.test/payment/notify',
                'redirectUrl' => 'https://merchant.test/order/order-2001',
                'service'     => 'createPaymentLink',
            ], 'order-2001')
            ->willReturn([
                'success' => true,
                'data'    => [
                    'orderKey' => 'checkout-key-2001',
                    'status'   => 'WAITING',
                    'result'   => [
                        'paymentLink' => 'https://checkout.test?token=checkout-key-2001',
                    ],
                ],
            ]);

        $service = new KardalPaymentService($client);

        $result = $service->createPaymentLink(
            'order-2001',
            18.5,
            'USD',
            'Nike Store Order',
            'https://merchant.test/order/order-2001'
        );

        $this->assertSame('https://checkout.test?token=checkout-key-2001', $result['payment_link']);
        $this->assertSame('checkout-key-2001', $result['order_info']['token']);
        $this->assertSame('WAITING', $result['order_info']['status']);
    }

    public function test_query_order_uses_checkout_status_when_api_key_exists(): void
    {
        config()->set('kardal.api_key', 'merchant-api-key');

        $client = $this->createMock(KardalClient::class);
        $client->expects($this->once())
            ->method('ecommerceCheckoutStatus')
            ->with('checkout-key-3001')
            ->willReturn([
                'success' => true,
                'data'    => [
                    'orderKey' => 'checkout-key-3001',
                    'status'   => 'SUCCESS',
                    'paidAt'   => '2026-06-29T10:20:30',
                ],
            ]);
        $client->expects($this->never())->method('gateway');

        $service = new KardalPaymentService($client);

        $result = $service->queryOrder('order-3001', 'checkout-key-3001');

        $this->assertSame('SUCCESS', $result['status']);
        $this->assertSame('checkout-key-3001', $result['order_key']);
        $this->assertSame('2026-06-29T10:20:30', $result['paid_at']);
    }

    public function test_query_order_falls_back_to_legacy_gateway_without_api_key(): void
    {
        config()->set('kardal.api_key', '');
        config()->set('kardal.seller_code', 'seller-123');

        $client = $this->createMock(KardalClient::class);
        $client->expects($this->never())->method('ecommerceCheckoutStatus');
        $client->expects($this->once())
            ->method('gateway')
            ->with([
                'service'      => 'webpay.acquire.queryOrder',
                'out_trade_no' => 'order-4001',
                'seller_code'  => 'seller-123',
            ])
            ->willReturn(['status' => 'WAITING']);

        $service = new KardalPaymentService($client);

        $result = $service->queryOrder('order-4001', 'checkout-key-4001');

        $this->assertSame('WAITING', $result['status']);
    }
}
