<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

use App\Models\Bank;
use App\Http\Controllers\BankController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/list', [BankController::class, 'list']);
    // Route::post('/add', [BankController::class, 'add']);
    // Route::post('/edit', [BankController::class, 'edit']);
    // Route::post('/delete', [BankController::class, 'delete']);
});