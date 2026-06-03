<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'product_id', 'name', 'price', 'img', 'qty'];

    protected $casts = [
        'price' => 'decimal:2',
        'qty'   => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subtotal(): float
    {
        return (float) $this->price * $this->qty;
    }
}
