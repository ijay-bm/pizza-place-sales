<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PizzaTypeController;
use App\Http\Controllers\Api\V1\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/user', [AuthController::class, 'user'])->name('auth.user');

    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{orderId}', [OrderController::class, 'show'])
        ->whereNumber('orderId')
        ->name('orders.show');

    Route::get('pizza-types', [PizzaTypeController::class, 'index'])->name('pizza-types.index');

    Route::prefix('statistics')
        ->name('statistics.')
        ->controller(StatisticsController::class)
        ->group(function (): void {
            Route::get('summary', 'summary')->name('summary');
            Route::get('revenue-by-month', 'revenueByMonth')->name('revenue-by-month');
            Route::get('orders-by-hour', 'ordersByHour')->name('orders-by-hour');
            Route::get('orders-by-weekday', 'ordersByWeekday')->name('orders-by-weekday');
            Route::get('pizza-type-sales', 'pizzaTypeSales')->name('pizza-type-sales');
            Route::get('category-sales', 'categorySales')->name('category-sales');
        });
});
