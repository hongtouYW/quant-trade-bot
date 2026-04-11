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

use App\Models\Gametype;
use App\Http\Controllers\GametypeController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/list', [GametypeController::class, 'list']);
    Route::post('/add', [GametypeController::class, 'add']);
    Route::post('/edit', [GametypeController::class, 'edit']);
    Route::post('/delete', [GametypeController::class, 'delete']);
});