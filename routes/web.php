<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
use App\Http\Controllers\UserController;

Route::post('/users', [UserController::class, 'create']);
Route::post('/login', [UserController::class, 'login']);
Route::get('/', [UserController::class, 'showTransactionsAndBalance']);
Route::get('/deposit', [UserController::class, 'showDeposits']);
Route::post('/deposit', [UserController::class, 'deposit']);
Route::get('/withdrawal', [UserController::class, 'showWithdrawals']);
Route::post('/withdrawal', [UserController::class, 'withdraw']);
