<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LevelController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WeatherController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/membership/apply', [ApplicationController::class, 'create'])->name('membership.create');
Route::post('/membership/apply', [ApplicationController::class, 'store'])->middleware('throttle:5,1')->name('membership.store');
Route::get('/weather', [WeatherController::class, 'show'])->middleware('throttle:30,1')->name('weather.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::middleware(['auth', EnsureAdmin::class])->group(function (): void {
    Route::get('/admin', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::resource('admin/members', MemberController::class);
    Route::post('/admin/members/{member}/reactivate', [MemberController::class, 'reactivate'])->name('members.reactivate');
    Route::post('/admin/members/{member}/renew', [TransactionController::class, 'renew'])->name('members.renew');
    Route::post('/admin/members/{member}/reapply', [TransactionController::class, 'reapply'])->name('members.reapply');
    Route::resource('admin/categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('admin/levels', LevelController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/admin/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/admin/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::get('/admin/transactions/{transaction}/receipt', [TransactionController::class, 'receipt'])->name('transactions.receipt');
    Route::post('/admin/transactions/{transaction}/approve', [TransactionController::class, 'approve'])->name('transactions.approve');
    Route::post('/admin/transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');
    Route::post('/admin/transactions/{transaction}/payments', [PaymentController::class, 'store'])->name('payments.store');
});
