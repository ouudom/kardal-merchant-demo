<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

// Public storefront — browse the catalog without an account.
Route::get('/', [StoreController::class, 'index'])->name('store.index');

// Buyer area — cart, checkout, payment, order history (login required).
Route::middleware('auth')->group(function () {
    // Breeze's post-login target; the storefront has no dashboard, send buyers home.
    Route::get('/dashboard', fn () => redirect()->route('store.index'))->name('dashboard');

    Route::get('/checkout', [StoreController::class, 'checkout'])->name('store.checkout');
    Route::get('/orders', [StoreController::class, 'orders'])->name('store.orders');
    Route::get('/order/{outTradeNo}', [StoreController::class, 'result'])->name('store.result');

    // Server-side cart
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{item}', [CartController::class, 'destroy'])->name('cart.destroy');

    // Kardal payment endpoints (called from the SPA via axios)
    Route::post('/payment/khqr', [PaymentController::class, 'khqr'])->name('payment.khqr');
    Route::post('/payment/link', [PaymentController::class, 'link'])->name('payment.link');
    // Card payment disabled for this demo.
    // Route::post('/payment/card', [PaymentController::class, 'card'])->name('payment.card');
    Route::get('/payment/{outTradeNo}/status', [PaymentController::class, 'status'])->name('payment.status');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Server-to-server callback from Kardal (CSRF-exempt — see bootstrap/app.php). Public.
Route::post('/payment/notify', [PaymentController::class, 'notify'])->name('payment.notify');

require __DIR__.'/auth.php';
