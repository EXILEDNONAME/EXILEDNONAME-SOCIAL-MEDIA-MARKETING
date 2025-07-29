<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () { return view('pages.frontend.index'); });

require __DIR__.'/auth.php';
require __DIR__.'/backend/dashboard.php';
require __DIR__.'/backend/administrative/application.php';
require __DIR__.'/backend/administrative/management.php';
require __DIR__.'/backend/administrative/session.php';
require __DIR__.'/backend/application/datatable.php';

// ORDERS
Route::group([
  'as' => 'dashboard.main.orders.',
  'prefix' => 'dashboard/orders',
  'namespace' => 'App\Http\Controllers\Backend\__Main',
  'middleware' => ['auth', 'web']
], function () {
  Route::get('/', 'OrderController@index')->name('index');
  Route::get('/{id}', 'OrderController@show')->name('show');
  Route::post('/', 'OrderController@store')->name('store');
});

// PRODUCTS
Route::group([
  'as' => 'dashboard.main.products.',
  'prefix' => 'dashboard/products',
  'namespace' => 'App\Http\Controllers\Backend\__Main',
  'middleware' => ['auth', 'web']
], function () {
  Route::get('/', 'ProductController@index')->name('index');
});


// TRANSACTIONS
Route::group([
  'as' => 'dashboard.main.transactions.',
  'prefix' => 'dashboard/transactions',
  'namespace' => 'App\Http\Controllers\Backend\__Main',
  'middleware' => ['auth', 'web']
], function () {
  Route::get('/all', 'TransactionController@all')->name('all');
  Route::get('/', 'TransactionController@index')->name('index');
});

// WALLETS
Route::group([
  'as' => 'dashboard.main.wallets.',
  'prefix' => 'dashboard/wallets',
  'namespace' => 'App\Http\Controllers\Backend\__Main',
  'middleware' => ['auth', 'web']
], function () {
  Route::get('/', 'WalletController@index')->name('index');
  Route::post('/checkout', 'WalletController@checkout')->name('checkout');

});

Route::get('/wallets', [WalletTransactionController::class, 'index']);
Route::post('/checkout', [WalletTransactionController::class, 'checkout']);
Route::post('/midtrans-callback', [WalletTransactionController::class, 'callback']);
