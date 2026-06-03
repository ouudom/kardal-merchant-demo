<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'out_trade_no', 'body', 'total_amount', 'currency',
        'method', 'status', 'token', 'cart', 'expired_at',
    ];

    protected $casts = [
        'cart'         => 'array',
        'total_amount' => 'decimal:2',
        'expired_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
