<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Services\Kardal\KardalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /** Order statuses treated as successfully paid. */
    private const PAID = ['PAID', 'SUCCESS', 'COMPLETED'];

    public function __construct(private readonly KardalPaymentService $kardal) {}

    /** Create a KHQR (KESSKHQR) order and return the QR string. */
    public function khqr(Request $request)
    {
        $order = $this->newOrder('khqr');

        $res = $this->kardal->createKhqr(
            $order->out_trade_no,
            (float) $order->total_amount,
            $order->currency,
            $order->body
        );

        $order->update([
            'token'      => $res['order_info']['token'] ?? null,
            'expired_at' => isset($res['expires_in']) ? now()->addSeconds((int) $res['expires_in']) : null,
        ]);

        return response()->json([
            'out_trade_no' => $order->out_trade_no,
            'qrcode'       => $res['qrcode'] ?? null,
            'expires_in'   => $res['expires_in'] ?? null,
        ]);
    }

    /** Create a hosted payment link (createOrder) and return its URL. */
    public function link(Request $request)
    {
        $order = $this->newOrder('link');

        $res = $this->kardal->createPaymentLink(
            $order->out_trade_no,
            (float) $order->total_amount,
            $order->currency,
            $order->body,
            route('store.result', $order->out_trade_no)   // return buyer to the order page
        );

        $order->update(['token' => $res['token'] ?? ($res['order_info']['token'] ?? null)]);

        return response()->json([
            'out_trade_no' => $order->out_trade_no,
            'payment_link' => $res['payment_link'] ?? null,
        ]);
    }

    /* Card payment disabled for this demo — kept for reference.
    public function card(Request $request)
    {
        $data = $request->validate([
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'currency'            => ['required', 'in:USD,KHR'],
            'cart'                => ['array'],
            'card.number'         => ['required', 'string'],
            'card.securityCode'   => ['required', 'string'],
            'card.month'          => ['required', 'string'],
            'card.year'           => ['required', 'string'],
            'card.holder_name'    => ['required', 'string'],
            'customer.first_name' => ['required', 'string'],
            'customer.last_name'  => ['required', 'string'],
            'customer.email'      => ['required', 'email'],
            'customer.phone_number' => ['required', 'string'],
        ]);

        $order = $this->newOrder($data, 'card');

        $res = $this->kardal->createCardPayment(
            $order->out_trade_no,
            (float) $order->total_amount,
            $order->currency,
            $order->body,
            $data['card'],
            $data['customer'] + ['phone_code' => '855'],
            $request->ip()
        );

        $order->update(['token' => $res['order_info']['token'] ?? null]);

        return response()->json([
            'out_trade_no'         => $order->out_trade_no,
            'required_3ds'         => $res['required_3ds'] ?? false,
            'html_confirm_payment' => $res['html_confirm_payment'] ?? null,
        ]);
    }
    */

    /** Poll an order's live status from the gateway (buyer's own order only). */
    public function status(string $outTradeNo)
    {
        $order = Order::where('user_id', Auth::id())
            ->where('out_trade_no', $outTradeNo)
            ->firstOrFail();

        $res = $this->kardal->queryOrder($outTradeNo);

        if (! empty($res['status'])) {
            $order->update(['status' => $res['status']]);
            $this->clearCartIfPaid($order);
        }

        return response()->json([
            'out_trade_no' => $outTradeNo,
            'status'       => $order->status,
        ]);
    }

    /**
     * Server-to-server notify webhook (Kardal -> merchant).
     * In production: verify `sign` before trusting. Logged here for the demo.
     */
    public function notify(Request $request)
    {
        Log::channel('single')->info('Kardal notify', $request->all());

        $outTradeNo = $request->input('out_trade_no');
        $status = $request->input('status');
        if ($outTradeNo && $status) {
            $order = Order::where('out_trade_no', $outTradeNo)->first();
            if ($order) {
                $order->update(['status' => $status]);
                $this->clearCartIfPaid($order);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Build a pending order from the authed buyer's DB cart.
     * Amount + line items come from the server cart, never the client.
     */
    private function newOrder(string $method): Order
    {
        $items = CartItem::where('user_id', Auth::id())->orderBy('id')->get();
        abort_if($items->isEmpty(), 422, 'Your cart is empty.');

        return Order::create([
            'user_id'      => Auth::id(),
            'out_trade_no' => 'nike-' . Str::lower(Str::random(20)),
            'body'         => 'Nike Store Order',
            'total_amount' => $items->sum(fn ($i) => $i->subtotal()),
            'currency'     => 'USD',
            'method'       => $method,
            'status'       => 'WAITING',
            'cart'         => $items->map->only(['product_id', 'name', 'price', 'img', 'qty'])->all(),
        ]);
    }

    /** Empty the buyer's cart once an order reaches a paid status. */
    private function clearCartIfPaid(Order $order): void
    {
        if ($order->user_id && in_array(strtoupper($order->status), self::PAID, true)) {
            CartItem::where('user_id', $order->user_id)->delete();
        }
    }
}
