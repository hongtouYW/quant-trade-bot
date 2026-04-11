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

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\VIPController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\GamepointController;
use App\Http\Controllers\GamememberController;
use App\Http\Controllers\GamebookmarkController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\RecruitController;
use App\Http\Controllers\PaymentgatewayController;
use App\Http\Controllers\PaymentRedirectController;
use App\Http\Controllers\MegaController;
use App\Http\Controllers\JiliController;
use App\Http\Controllers\FpayController;
use App\Http\Controllers\SuperpayController;
use App\Http\Controllers\OnehubxController;
use App\Http\Controllers\AdvantplayController;
use App\Http\Controllers\GameplatformController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ProviderbookmarkController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\XglobalController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\RecruittutorialController;
use App\Http\Controllers\OfficialController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\FirebaseController;
use App\Http\Controllers\GamelogController;
use App\Http\Controllers\NotificationController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

Route::post('/{type}/version', [AuthController::class, 'version']);
Route::post('/{type}/version/list', [AuthController::class, 'versionlist']);
Route::post('/country/list/phone', [CountryController::class, 'phonelist']);
Route::post('/game/point/latest/score', [GamepointController::class, 'latestscore']);
Route::post('/game/log/cronjob', [GameController::class, 'gamelogcronjob']);
Route::post('/provider/list/cronjob', [ProviderController::class, 'providerlistcronjob']);

Route::prefix('{type}')->group(function () {
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/country/list', [CountryController::class, 'list']);
        Route::post('/country/select', [CountryController::class, 'select']);
        Route::post('/state/list', [StateController::class, 'list']);
        Route::post('/state/select', [StateController::class, 'select']);
        Route::post('/area/list', [AreaController::class, 'list']);
        Route::post('/genre/list', [GenreController::class, 'list']);
        Route::post('/artist/list', [ArtistController::class, 'list']);
        Route::post('/song/list', [SongController::class, 'list']);
    });
});
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/{type}/game/list', [GameController::class, 'list']);
    Route::post('/{type}/game/type', [GameController::class, 'type']);
    Route::post('/{type}/game/view', [GameController::class, 'view']);
    Route::post('/{type}/game/bookmark/list', [GamebookmarkController::class, 'list']);
    Route::post('/{type}/game/bookmark/add', [GamebookmarkController::class, 'add']);
    Route::post('/{type}/game/bookmark/delete', [GamebookmarkController::class, 'delete']);

    Route::post('/{type}/feedback/list', [FeedbackController::class, 'list']);
    Route::post('/{type}/feedback/type/list', [FeedbackController::class, 'feedbacktypelist']);

    Route::get('/member/qr/scan/{member_id}', [MemberController::class, 'memberqrscan']); //扫描会员账号
    Route::get('/player/qr/scan/{gamemember_id}', [GamememberController::class, 'playerqrscan']); //扫描玩家账号
    Route::get('/member/withdraw/qr/scan/{payload}', [ShopController::class, 'withdrawqrscan']); //扫描会员提款

    Route::post('/member/player/changepassword', [GamememberController::class, 'changepasswordplayer']);
    Route::post('/{type}/player/password', [GamememberController::class, 'revealplayerpassword']);
    Route::post('/{type}/player/add', [GamememberController::class, 'addplayer']);
    Route::post('/{type}/player/view', [GamememberController::class, 'viewplayer']);
    Route::post('/{type}/player/reload', [GamememberController::class, 'reloadplayer']);
    Route::post('/{type}/player/withdraw', [GamememberController::class, 'withdrawplayer']);
    Route::post('/{type}/player/delete', [GamememberController::class, 'deleteplayer']);
    Route::post('/{type}/player/list', [GamememberController::class, 'playerlist']);
    Route::post('/member/player/login', [GamememberController::class, 'loginplayer']);
    Route::post('/member/player/v2/login', [GamememberController::class, 'loginplayerv2']);
    Route::post('/member/player/v3/login', [GamememberController::class, 'loginplayerv3']);
    Route::post('/member/player/transfer/list', [GamememberController::class, 'transferlist']);
    Route::post('/member/player/transfer/point', [GamememberController::class, 'transferpoint']);
    Route::post('/member/player/transfer/out', [GamememberController::class, 'transferout']);
    Route::post('/member/player/reload/out', [GamememberController::class, 'reloadplayerout']);
    Route::post('/member/player/withdraw/out', [GamememberController::class, 'withdrawplayerout']);
    Route::post('/member/player/list/add/reload/login', [GamememberController::class, 'profile']);
    Route::post('/member/player/list/add/reload/v2/login', [GamememberController::class, 'profilev2']);

    Route::post('/{type}/payment/list', [PaymentgatewayController::class, 'list']);
    Route::post('/{type}/payment/status/deposit', [PaymentgatewayController::class, 'depositstatus']);
    Route::post('/{type}/payment/status/withdraw', [PaymentgatewayController::class, 'withdrawstatus']);

    Route::post('/{type}/game/platform/list', [GameplatformController::class, 'list']);

    Route::post('/{type}/game/provider/view', [ProviderController::class, 'view']);
    Route::post('/{type}/game/provider/list', [ProviderController::class, 'list']);

    Route::post('/{type}/game/provider/bookmark/list', [ProviderbookmarkController::class, 'list']);
    Route::post('/{type}/game/provider/bookmark/add', [ProviderbookmarkController::class, 'add']);
    Route::post('/{type}/game/provider/bookmark/delete', [ProviderbookmarkController::class, 'delete']);

    Route::post('/member/vip/list', [VIPController::class, 'list']);
    Route::post('/member/vip/bonus/first', [VIPController::class, 'firstbonus']);
    Route::post('/member/vip/bonus/daily', [VIPController::class, 'dailybonus']);
    Route::post('/member/vip/bonus/weekly', [VIPController::class, 'weeklybonus']);
    Route::post('/member/vip/bonus/monthly', [VIPController::class, 'monthlybonus']);
    Route::post('/member/vip/bonus/remain/target', [VIPController::class, 'remaintarget']);
    Route::post('/member/vip/bonus/history', [VIPController::class, 'history']); //VIP领取记录
    Route::post('/member/vip/bonus/all', [VIPController::class, 'allbonus']); //VIP一键领取

    Route::post('/member/promotion/list', [PromotionController::class, 'list']);

    Route::post('/member/question/list', [QuestionController::class, 'list']);

    Route::post('/member/referral/qr', [RecruitController::class, 'referralqr']); //扫描玩家邀请码
    Route::post('/member/referral/downline/list', [RecruitController::class, 'downlinereferrallist']); //下线列表邀请码

    Route::post('/member/performance/upline', [PerformanceController::class, 'myupline']); //推广赚钱 直属上线
    Route::post('/member/performance/summary', [PerformanceController::class, 'mydata']); //推广赚钱 我的数据
    Route::post('/member/performance/downline/list', [PerformanceController::class, 'downlinelist']); //推广赚钱 下线信息列表
    Route::post('/member/performance/commission/list', [PerformanceController::class, 'commissionlist']); //推广赚钱 我的佣金
    Route::post('/member/performance/commission/list/total', [PerformanceController::class, 'totalcommissionlist']); //推广赚钱 我的业绩
    Route::post('/member/performance/invite/list', [PerformanceController::class, 'myinvitelist']); //推广赚钱 我的邀请码
    Route::post('/member/performance/invite/new', [PerformanceController::class, 'newinvitecode']); //推广赚钱 成新邀二维码
    Route::post('/member/performance/invite/default/edit', [PerformanceController::class, 'editinvitecodedefault']); //推广赚钱 编辑邀默认二维码
    Route::post('/member/performance/invite/name/edit', [PerformanceController::class, 'editinvitecodename']); //推广赚钱 编辑邀二维码名字
    Route::post('/member/performance/downline/new', [PerformanceController::class, 'registerreferral']); //推广赚钱 直属开户
    Route::post('/member/performance/profile', [PerformanceController::class, 'profile']); //推广赚钱 我的邀请统计
    Route::post('/member/performance/friend/list', [PerformanceController::class, 'friendlist']); //推广赚钱 好友列表
    Route::post('/member/performance/friend/commission', [PerformanceController::class, 'mycommission']); //推广赚钱 我的返利

    Route::post('/{type}/agreement/list', [AgreementController::class, 'list']); //我的协议列表

    Route::post('/{type}/referral/tutorial', [RecruittutorialController::class, 'tutorial']); //推广教程

    Route::post('/member/support/link', [AgentController::class, 'supportlink']); //客服联系

    Route::post('/member/official/link', [OfficialController::class, 'list']); //找到我们

    Route::post('/member/slider/list', [SliderController::class, 'list']); //消息中心 - 首页跑马灯 ***
    Route::post('/member/slider/read', [SliderController::class, 'memberread']); //消息中心 - 读取跑马灯 ***

    Route::post('/{type}/firebase/device', [FirebaseController::class, 'device']); //Firebase Edit Device Key ***

    Route::post('/{type}/notification/list/new', [NotificationController::class, 'list']);
});
//同步游戏记录
Route::get('/game/log/sync', [GamelogController::class, 'syncgamelog']); //同步游戏记录
Route::post('/agreement/view', [AgreementController::class, 'view']); //我的协议
Route::post('/{type}/agent/icon', [AgentController::class, 'agenticon']); //代理图标

// Deposit Status
Route::get('/payment/status/{payload}', [PaymentgatewayController::class, 'paymentstatus']);
//topup redirect route
Route::get('/payment/redirect/{payload}', [PaymentgatewayController::class, 'withdrawredirect']);
Route::get('/payment/redirect', [PaymentRedirectController::class, 'handle']);

Route::post('/mega/callback', [MegaController::class, 'callback']);
Route::post('/jili/callback', [JiliController::class, 'callback']);
Route::post('/fpay/callback', [FpayController::class, 'callback']);
Route::post('/superpay/callback/deposit', [SuperpayController::class, 'callbackdeposit']);
Route::post('/superpay/callback/withdraw', [SuperpayController::class, 'callbackwithdraw']);
Route::post('/superpay/callback/decrypt', [SuperpayController::class, 'callbackdecrypt']);
Route::post('/advantplay/callback', [AdvantplayController::class, 'callback']);
Route::post('/xglobal/callback', [XglobalController::class, 'callback']);
Route::post('/member/bind/phone/random', [MemberController::class, 'bindphonerandommember']);
Route::post('/member/bind/phone/random/otp', [MemberController::class, 'bindphonerandommemberOTP']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/member/commission/add', [RecruitController::class, 'addcommission']); //测试获取佣金
});
