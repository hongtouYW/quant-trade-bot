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

use App\Models\Countries;
use App\Http\Controllers\CountryController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/list', [CountryController::class, 'list']);
//     Route::post('/add', [CountryController::class, 'add']);
//     Route::post('/edit', [CountryController::class, 'edit']);
//     Route::post('/delete', [CountryController::class, 'delete']);
// });