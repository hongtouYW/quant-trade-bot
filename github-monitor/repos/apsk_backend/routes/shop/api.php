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

use App\Models\Shop;
use App\Http\Controllers\ShopController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

Route::post('/login', [ShopController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/dashboard', [ShopController::class, 'dashboard']);
    Route::post('/alarm', [ShopController::class, 'alarm']);
    Route::post('/member/new', [ShopController::class, 'newmember']);
    Route::post('/member/changepassword', [ShopController::class, 'memberchangepassword']);
    Route::post('/member/random', [ShopController::class, 'randommember']);
    Route::post('/member/search', [ShopController::class, 'searchmember']);
    Route::post('/member/search/list', [ShopController::class, 'searchmemberlist']);
    Route::post('/member/detail/list', [ShopController::class, 'detailmemberlist']);
    Route::post('/member/block', [ShopController::class, 'blockmember']);
    Route::post('/member/block/reason', [ShopController::class, 'blockmemberreason']);
    Route::post('/member/unblock', [ShopController::class, 'unblockmember']);
    Route::post('/member/topup', [ShopController::class, 'topupmember']);
    Route::post('/member/withdraw', [ShopController::class, 'withdrawmember']);
    Route::post('/player/search', [ShopController::class, 'searchplayer']);
    Route::post('/transaction/list', [ShopController::class, 'transactionlist']);
    Route::post('/member/transaction/list', [ShopController::class, 'membertransactionlist']);
    Route::post('/feedback/send', [ShopController::class, 'sendfeedback']);
    Route::post('/edit', [ShopController::class, 'edit']);
    Route::post('/balance', [ShopController::class, 'balance']);
    Route::post('/member/password', [ShopController::class, 'revealmemberpassword']);
    Route::post('/logout', function (Request $request) {
        $authorizedUser = $request->user();
        if (!$authorizedUser) {
            return sendEncryptedJsonResponse(
                [
                    'status' => false,
                    'message' => __('messages.Unauthorized'),
                    'error' => __('messages.Unauthorized'),
                ],
                403
            );
        }
        $request->user()->currentAccessToken()->delete();
        LogLogout( $request, "shop" );
        return sendEncryptedJsonResponse(
            [
                'status' => true,
                'message' => __('messages.logout_success'),
                'error' => "",
            ],
            200
        );
    });
    Route::post('/view', [ShopController::class, 'view']);
    Route::post('/changepassword', [ShopController::class, 'changepassword']);
    Route::post('/withdraw/qr/scan/password', [ShopController::class, 'withdrawqrscanpassword']);
});