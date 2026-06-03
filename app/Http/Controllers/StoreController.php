<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StoreController extends Controller
{
    /** Hardcoded Nike catalog for the demo. */
    public const PRODUCTS = [
        ['id' => 1, 'name' => 'Nike Air Max 270', 'price' => 1.00, 'img' => '👟'],
        ['id' => 2, 'name' => 'Nike Air Force 1',  'price' => 1.50, 'img' => '👟'],
        ['id' => 3, 'name' => 'Nike Dunk Low',     'price' => 2.00, 'img' => '👟'],
        ['id' => 4, 'name' => 'Nike Pegasus 41',   'price' => 2.50, 'img' => '👟'],
        ['id' => 5, 'name' => 'Nike Dri-FIT Tee',  'price' => 3.00,  'img' => '👕'],
        ['id' => 6, 'name' => 'Nike Club Cap',     'price' => 3.50,  'img' => '🧢'],
    ];

    public function index()
    {
        return Inertia::render('Store/Index', [
            'products'  => self::PRODUCTS,
            'cartCount' => $this->cartCount(),
        ]);
    }

    public function checkout()
    {
        return Inertia::render('Store/Checkout', [
            'cart' => CartItem::where('user_id', Auth::id())->orderBy('id')->get(),
        ]);
    }

    public function orders()
    {
        return Inertia::render('Store/Orders', [
            'orders' => Order::where('user_id', Auth::id())
                ->latest()
                ->get(['out_trade_no', 'total_amount', 'currency', 'method', 'status', 'created_at']),
        ]);
    }

    public function result(string $outTradeNo)
    {
        $order = Order::where('user_id', Auth::id())
            ->where('out_trade_no', $outTradeNo)
            ->firstOrFail();

        return Inertia::render('Store/Result', ['order' => $order]);
    }

    private function cartCount(): int
    {
        return Auth::check()
            ? (int) CartItem::where('user_id', Auth::id())->sum('qty')
            : 0;
    }
}
