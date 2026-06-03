<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /** Add a catalog product to the buyer's cart (increment qty if present). */
    public function store(Request $request)
    {
        $data = $request->validate(['product_id' => ['required', 'integer']]);

        $product = collect(StoreController::PRODUCTS)->firstWhere('id', $data['product_id']);
        abort_if(! $product, 404, 'Unknown product.');

        $item = CartItem::firstOrNew([
            'user_id'    => Auth::id(),
            'product_id' => $product['id'],
        ]);
        $item->fill([
            'name'  => $product['name'],   // snapshot — no products table
            'price' => $product['price'],
            'img'   => $product['img'],
        ]);
        $item->qty = ($item->qty ?? 0) + 1;
        $item->save();

        return back();
    }

    /** Set a line's quantity (qty <= 0 removes it). */
    public function update(Request $request, CartItem $item)
    {
        $this->authorizeOwner($item);
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:99']]);

        if ($data['qty'] === 0) {
            $item->delete();
        } else {
            $item->update(['qty' => $data['qty']]);
        }

        return back();
    }

    public function destroy(CartItem $item)
    {
        $this->authorizeOwner($item);
        $item->delete();

        return back();
    }

    private function authorizeOwner(CartItem $item): void
    {
        abort_if($item->user_id !== Auth::id(), 403);
    }
}
