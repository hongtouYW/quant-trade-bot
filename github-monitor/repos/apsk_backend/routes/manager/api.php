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

use App\Models\Manager;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LogController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

Route::post('/login', [ManagerController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/dashboard', [ManagerController::class, 'dashboard']);
    Route::post('/view', [ManagerController::class, 'view']);
    Route::post('/search', [ManagerController::class, 'search']);
    Route::post('/member/search/phone', [ManagerController::class, 'searchphone']);
    Route::post('/shop/new', [ManagerController::class, 'newshop']);
    Route::post('/shop/view', [ManagerController::class, 'viewshop']);
    Route::post('/shop/password', [ManagerController::class, 'revealshoppassword']);
    Route::post('/shop/changepassword', [ManagerController::class, 'shopchangepassword']);
    Route::post('/shop/pin', [ManagerController::class, 'shoppin']);
    Route::post('/shop/unpin', [ManagerController::class, 'shopunpin']);
    Route::post('/shop/list', [ManagerController::class, 'shoplist']);
    Route::post('/shop/detail/list', [ManagerController::class, 'detailshoplist']);
    Route::post('/shop/amount', [ManagerController::class, 'shopamount']);
    Route::post('/shop/clear', [ManagerController::class, 'clearaccount']);
    Route::post('/shop/permission', [ManagerController::class, 'permissionshop']);
    Route::post('/shop/open', [ManagerController::class, 'openshop']);
    Route::post('/shop/close', [ManagerController::class, 'closeshop']);
    Route::post('/shop/close/reason', [ManagerController::class, 'closeshopreason']);
    Route::post('/shop/transaction/list', [ManagerController::class, 'shoptransactionlist']);
    Route::post('/member/view', [ManagerController::class, 'viewmember']);
    Route::post('/member/password', [ManagerController::class, 'revealmemberpassword']);
    Route::post('/member/list', [ManagerController::class, 'memberlist']);
    Route::post('/member/changepassword', [ManagerController::class, 'memberchangepassword']);
    Route::post('/member/block', [ManagerController::class, 'blockmember']);
    Route::post('/member/block/reason', [ManagerController::class, 'blockmemberreason']);
    Route::post('/member/unblock', [ManagerController::class, 'unblockmember']);
    Route::post('/member/delete', [ManagerController::class, 'deletemember']);
    Route::post('/member/delete/reason', [ManagerController::class, 'deletememberreason']);
    Route::post('/member/edit/phone', [ManagerController::class, 'editmemberphone']);
    Route::post('/player/password/reset', [ManagerController::class, 'resetplayerpassword']);
    Route::post('/notification/list', [NotificationController::class, 'listmanager']);
    Route::post('/notification/read', [NotificationController::class, 'managerread']);
    Route::post('/log/search/filter', [LogController::class, 'searchfiltermanager']);
    Route::post('/log/list', [LogController::class, 'listmanager']);
    Route::post('/point/list', [ManagerController::class, 'pointlist']);
    Route::post('/player/log/list', [ManagerController::class, 'gameloglist']);
    Route::post('/permission/list', [ManagerController::class, 'permissionlist']);
    Route::post('/permission/search', [ManagerController::class, 'permissionsearch']);
    Route::post('/permission/add', [ManagerController::class, 'permissionadd']);
    Route::post('/permission/delete', [ManagerController::class, 'permissiondelete']);

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
        LogLogout( $request, "manager" );
        return sendEncryptedJsonResponse(
            [
                'status' => true,
                'message' => __('messages.logout_success'),
                'error' => "",
            ],
            200
        );
    });
    Route::post('/view', [ManagerController::class, 'view']);
    Route::post('/changepassword', [ManagerController::class, 'changepassword']);
});