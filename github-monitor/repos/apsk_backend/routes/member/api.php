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

use App\Models\Member;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController;

Route::post('/register', [MemberController::class, 'register']);
Route::post('/login', [MemberController::class, 'login']);
Route::post('/verifyOTP', [MemberController::class, 'verifyOTP']);
Route::post('/resetpassword', [MemberController::class, 'resetpassword']); //重置密码
Route::post('/resetOTP', [MemberController::class, 'resetOTP']); //重置密码OTP
Route::post('/resetnewpassword', [MemberController::class, 'resetnewpassword']); //重置新密码

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/dashboard', [MemberController::class, 'dashboard']);
    Route::post('/alarm', [MemberController::class, 'alarm']);
    Route::post('/topup', [MemberController::class, 'topup']);
    Route::post('/withdraw', [MemberController::class, 'withdraw']);
    Route::post('/player/qr', [MemberController::class, 'playerqr']);
    Route::post('/bank/add', [MemberController::class, 'addbankaccount']);
    Route::post('/bank/list', [MemberController::class, 'listbankaccount']);
    Route::post('/bank/fastpay', [MemberController::class, 'fastpaybankaccount']);
    Route::post('/bank/add', [MemberController::class, 'addbankaccount']);
    Route::post('/bank/delete', [MemberController::class, 'deletebankaccount']);
    Route::post('/notification/list', [NotificationController::class, 'listmember']);
    Route::post('/notification/read', [NotificationController::class, 'memberread']);
    Route::post('/downline/all', [MemberController::class, 'alldownline']);
    Route::post('/transaction/list', [MemberController::class, 'transactionlist']);
    Route::post('/feedback/send', [MemberController::class, 'sendfeedback']);
    Route::post('/bind/phone', [MemberController::class, 'bindphone']);
    Route::post('/bind/phone/otp', [MemberController::class, 'bindphoneOTP']);
    Route::post('/bind/email', [MemberController::class, 'bindemail']);
    Route::post('/bind/email/otp', [MemberController::class, 'bindemailOTP']);
    Route::post('/google/generate/2fa/qr', [MemberController::class, 'generategoogle2FA']);
    Route::post('/bind/google', [MemberController::class, 'bindgoogle']);
    Route::post('/score/view', [MemberController::class, 'score']);

    Route::post('/edit', [MemberController::class, 'edit']);
    Route::post('/avatar/list', [MemberController::class, 'avatarlist']);
    Route::post('/avatar', [MemberController::class, 'avatar']);
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
        LogLogout( $request, "member" );
        return sendEncryptedJsonResponse(
            [
                'status' => true,
                'message' => __('messages.logout_success'),
                'error' => "",
            ],
            200
        );
    });
    Route::post('/view', [MemberController::class, 'view']);
    Route::post('/changepassword', [MemberController::class, 'changepassword']);
    Route::post('/changepassword/send/otp', [MemberController::class, 'changepasswordsendOTP']);
    Route::post('/passwordOTP', [MemberController::class, 'passwordOTP']);
    Route::post('/qr', [MemberController::class, 'memberqr']);
    Route::post('/withdraw/qr', [MemberController::class, 'withdrawqr']);
});