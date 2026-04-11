<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\LocalizationController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ManagerController as AdminManagerController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Admin\MemberController as AdminMemberController;
use App\Http\Controllers\Admin\BankController as AdminBankController;
use App\Http\Controllers\Admin\PaymentgatewayController as AdminPaymentgatewayController;
use App\Http\Controllers\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Admin\StateController as AdminStateController;
use App\Http\Controllers\Admin\AreaController as AdminAreaController;
use App\Http\Controllers\Admin\GenreController as AdminGenreController;
use App\Http\Controllers\Admin\ArtistController as AdminArtistController;
use App\Http\Controllers\Admin\SongController as AdminSongController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\GametypeController as AdminGametypeController;
use App\Http\Controllers\Admin\GameController as AdminGameController;
use App\Http\Controllers\Admin\GamebookmarkController as AdminGamebookmarkController;
use App\Http\Controllers\Admin\GamepointController as AdminGamepointController;
use App\Http\Controllers\Admin\GamememberController as AdminGamememberController;
use App\Http\Controllers\Admin\GameplatformController as AdminGameplatformController;
use App\Http\Controllers\Admin\GameplatformaccessController as AdminGameplatformaccessController;
use App\Http\Controllers\Admin\GamelogController as AdminGamelogController;
use App\Http\Controllers\Admin\SliderController as AdminSliderController;
use App\Http\Controllers\Admin\VIPController as AdminVIPController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Admin\CreditController as AdminCreditController;
use App\Http\Controllers\Admin\ShopcreditController as AdminShopcreditController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\AccessController as AdminAccessController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\AgentController as AdminAgentController;
use App\Http\Controllers\Admin\RecruitController as AdminRecruitController;
use App\Http\Controllers\Admin\RecruittutorialController as AdminRecruittutorialController;
use App\Http\Controllers\Admin\AgreementController as AdminAgreementController;
use App\Http\Controllers\Admin\NoticepublicController as AdminNoticepublicController;
use App\Http\Controllers\Admin\ManagercreditController as AdminManagercreditController;
use App\Http\Controllers\Admin\CommissionrankController as AdminCommissionrankController;
use App\Http\Controllers\Admin\PerformanceController as AdminPerformanceController;
use App\Http\Controllers\Admin\IPBlockController as AdminIPBlockController;

Route::get('/home/login', [HomeController::class, 'showLoginForm'])->name('login');
Route::post('/home/login', [HomeController::class, 'login'])->name('login.attempt');
Route::get('/profile', [HomeController::class, 'profile'])->name('profile');
Route::post('/logout', [HomeController::class, 'logout'])->name('logout');
Route::post('/set-theme', [HomeController::class, 'setTheme'])->name('theme.set');

// Route::middleware(['auth',  'admin.iframe.auth'])->group(function () {
// Route::middleware(['admin.iframe.auth'])->group(function () {
Route::middleware('auth')->group(function () {
    // Sync Progress
    Route::get('/sync-progress', function () {
        return response()->json(
            Cache::get('sync_progress', [
                'processed' => 0,
                'total' => 0,
                'percent' => 0
            ])
        );
    });
    // // Iframe
    // Route::get('/dashboard', function () {
    //     return view('iframe');
    // })->name('iframe');
    // // Dashboard
    // Route::get('admin/dashboard', function () {
    //     return view('dashboard');
    // })->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'iframe'])->name('iframe');
    Route::get('admin/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Setup
    Route::get('/setup', [HomeController::class, 'setup'])->name('setup');
    Route::put('/setup/{id}', [HomeController::class, 'setupupdate'])->name('setup.update');

    // Agent Management
    Route::resource('admin/agent', AdminAgentController::class)->names([
        'index'   => 'admin.agent.index',
        'create'  => 'admin.agent.create',
        'store'   => 'admin.agent.store',
        'show'    => 'admin.agent.show',
        'edit'    => 'admin.agent.edit',
        'update'  => 'admin.agent.update',
        'destroy' => 'admin.agent.destroy',
    ]);
    Route::post('admin/agent/{id}/clear', [AdminAgentController::class, 'clear'])
        ->name('admin.agent.clear');
    Route::get('/agent/states/{country_code}', [AdminAgentController::class, 'filterstate'])
        ->name('admin.agent.filterstate');
    Route::post('/agent/download/link/{agent_id}', [AdminAgentController::class, 'downloadlink'])
        ->name('admin.agent.downloadlink');
    // Admin Management
    Route::resource('admin/user', AdminUserController::class)->names([
        'index'   => 'admin.user.index',
        'create'  => 'admin.user.create',
        'store'   => 'admin.user.store',
        'show'    => 'admin.user.show',
        'edit'    => 'admin.user.edit',
        'update'  => 'admin.user.update',
        'destroy' => 'admin.user.destroy',
    ]);
    Route::get('/user/areas/{state_code}', [AdminUserController::class, 'filterarea'])
        ->name('admin.user.filterarea');
    // Manager Management
    Route::resource('admin/manager', AdminManagerController::class)->names([
        'index'   => 'admin.manager.index',
        'create'  => 'admin.manager.create',
        'store'   => 'admin.manager.store',
        'edit'    => 'admin.manager.edit',
        'update'  => 'admin.manager.update',
        'destroy' => 'admin.manager.destroy',
    ]);
    Route::get('admin/manager/{manager}', [AdminManagerController::class, 'show'])
        ->name('admin.manager.show');
    Route::post('admin/manager/{id}/clear', [AdminManagerController::class, 'clear'])
        ->name('admin.manager.clear');
    Route::get('/manager/areas/{state_code}', [AdminManagerController::class, 'filterarea'])
        ->name('admin.manager.filterarea');
    // Shop Management
    Route::resource('admin/shop', AdminShopController::class)->names([
        'index'   => 'admin.shop.index',
        'create'  => 'admin.shop.create',
        'store'   => 'admin.shop.store',
        'edit'    => 'admin.shop.edit',
        'update'  => 'admin.shop.update',
    ]);
    Route::get('admin/shop/{shop}', [AdminShopController::class, 'show'])
        ->name('admin.shop.show');
    Route::post('/shop/delete/{shop_id}', [AdminShopController::class, 'delete'])
        ->name('admin.shop.delete');
    Route::get('/shop/areas/{state_code}', [AdminShopController::class, 'filterarea'])
        ->name('admin.shop.filterarea');
    Route::post('/shop/password/{shop_id}', [AdminShopController::class, 'revealpassword'])
        ->name('admin.shop.revealpassword');
    Route::post('/shop/download/link/{shop_id}', [AdminShopController::class, 'downloadlink'])
        ->name('admin.shop.downloadlink');
    Route::post('/shop/manager/permission/list/{manager_id}', [AdminShopController::class, 'permissionlist'])
        ->name('admin.shop.permissionlist');
    // Member Management
    Route::resource('admin/member', AdminMemberController::class)->names([
        'index'   => 'admin.member.index',
        'create'  => 'admin.member.create',
        'store'   => 'admin.member.store',
        'edit'    => 'admin.member.edit',
        'update'  => 'admin.member.update',
        'destroy' => 'admin.member.destroy',
    ]);
    Route::get('admin/member/{member}/show',
        [AdminMemberController::class, 'show']
    )->name('admin.member.show');
    Route::get('/member/areas/{state_code}', [AdminMemberController::class, 'filterarea'])
        ->name('admin.member.filterarea');
    Route::post('/member/password/{member_id}', [AdminMemberController::class, 'revealpassword'])
        ->name('admin.member.revealpassword');
    Route::post('/member/game/{member_id}', [AdminMemberController::class, 'syncgamemember'])
        ->name('admin.member.syncgamemember');
    Route::post('/member/game/log/{member_id}', [AdminMemberController::class, 'syncgamelog'])
        ->name('admin.member.syncgamelog');
    Route::post('/member/game/log/add/{member_id}', [AdminMemberController::class, 'insertgamelog'])
        ->name('admin.member.insertgamelog');
    Route::post('/member/game/log/turnover/{member_id}', [AdminMemberController::class, 'turnover'])
        ->name('admin.member.turnover');
    // Bank Management
    Route::resource('admin/bank', AdminBankController::class)->names([
        'index'   => 'admin.bank.index',
        'create'  => 'admin.bank.create',
        'store'   => 'admin.bank.store',
        'show'    => 'admin.bank.show',
        'edit'    => 'admin.bank.edit',
        'update'  => 'admin.bank.update',
        'destroy' => 'admin.bank.destroy',
    ]);
    // Paymentgateway Management
    Route::resource('admin/paymentgateway', AdminPaymentgatewayController::class)->names([
        'index'   => 'admin.paymentgateway.index',
        'create'  => 'admin.paymentgateway.create',
        'store'   => 'admin.paymentgateway.store',
        'show'    => 'admin.paymentgateway.show',
        'edit'    => 'admin.paymentgateway.edit',
        'update'  => 'admin.paymentgateway.update',
        'destroy' => 'admin.paymentgateway.destroy',
    ]);
    // Country Management
    Route::resource('admin/country', AdminCountryController::class)->names([
        'index'   => 'admin.country.index',
        'create'  => 'admin.country.create',
        'store'   => 'admin.country.store',
        'show'    => 'admin.country.show',
        'edit'    => 'admin.country.edit',
        'update'  => 'admin.country.update',
        'destroy' => 'admin.country.destroy',
    ]);
    // State Management
    Route::resource('admin/state', AdminStateController::class)->names([
        'index'   => 'admin.state.index',
        'create'  => 'admin.state.create',
        'store'   => 'admin.state.store',
        'show'    => 'admin.state.show',
        'edit'    => 'admin.state.edit',
        'update'  => 'admin.state.update',
        'destroy' => 'admin.state.destroy',
    ]);
    // Area Management
    Route::resource('admin/area', AdminAreaController::class)->names([
        'index'   => 'admin.area.index',
        'create'  => 'admin.area.create',
        'store'   => 'admin.area.store',
        'show'    => 'admin.area.show',
        'edit'    => 'admin.area.edit',
        'update'  => 'admin.area.update',
        'destroy' => 'admin.area.destroy',
    ]);
    // Genre Management
    Route::resource('admin/genre', AdminGenreController::class)->names([
        'index'   => 'admin.genre.index',
        'create'  => 'admin.genre.create',
        'store'   => 'admin.genre.store',
        'show'    => 'admin.genre.show',
        'edit'    => 'admin.genre.edit',
        'update'  => 'admin.genre.update',
        'destroy' => 'admin.genre.destroy',
    ]);
    // Artist Management
    Route::resource('admin/artist', AdminArtistController::class)->names([
        'index'   => 'admin.artist.index',
        'create'  => 'admin.artist.create',
        'store'   => 'admin.artist.store',
        'show'    => 'admin.artist.show',
        'edit'    => 'admin.artist.edit',
        'update'  => 'admin.artist.update',
        'destroy' => 'admin.artist.destroy',
    ]);
    // Song Management
    Route::resource('admin/song', AdminSongController::class)->names([
        'index'   => 'admin.song.index',
        'create'  => 'admin.song.create',
        'store'   => 'admin.song.store',
        'show'    => 'admin.song.show',
        'edit'    => 'admin.song.edit',
        'update'  => 'admin.song.update',
        'destroy' => 'admin.song.destroy',
    ]);
    // Application Management
    Route::resource('admin/application', AdminApplicationController::class)->names([
        'index'   => 'admin.application.index',
        'create'  => 'admin.application.create',
        'store'   => 'admin.application.store',
        'show'    => 'admin.application.show',
        'edit'    => 'admin.application.edit',
        'update'  => 'admin.application.update',
        'destroy' => 'admin.application.destroy',
    ]);
    // Gametype Management
    // Route::resource('admin/gametype', AdminGametypeController::class)->names([
    //     'index'   => 'admin.gametype.index',
    //     'create'  => 'admin.gametype.create',
    //     'store'   => 'admin.gametype.store',
    //     'show'    => 'admin.gametype.show',
    //     'edit'    => 'admin.gametype.edit',
    //     'update'  => 'admin.gametype.update',
    //     'destroy' => 'admin.gametype.destroy',
    // ]);
    // Game Management
    Route::resource('admin/game', AdminGameController::class)->names([
        'index'   => 'admin.game.index',
        'create'  => 'admin.game.create',
        'edit'    => 'admin.game.edit',
        'update'  => 'admin.game.update',
        'destroy' => 'admin.game.destroy',
    ]);

    // Gamebookmark Management
    Route::resource('admin/gamebookmark', AdminGamebookmarkController::class)->names([
        'index'   => 'admin.gamebookmark.index',
        'create'  => 'admin.gamebookmark.create',
        'store'   => 'admin.gamebookmark.store',
        'show'    => 'admin.gamebookmark.show',
        'edit'    => 'admin.gamebookmark.edit',
        'update'  => 'admin.gamebookmark.update',
        'destroy' => 'admin.gamebookmark.destroy',
    ]);
    // Gamepoint Management
    Route::resource('admin/gamepoint', AdminGamepointController::class)->names([
        'index'   => 'admin.gamepoint.index',
        'create'  => 'admin.gamepoint.create',
        'store'   => 'admin.gamepoint.store',
        'show'    => 'admin.gamepoint.show',
        'edit'    => 'admin.gamepoint.edit',
        'update'  => 'admin.gamepoint.update',
        'destroy' => 'admin.gamepoint.destroy',
    ]);
    // Gamemember Management
    Route::resource('admin/gamemember', AdminGamememberController::class)->names([
        'index'   => 'admin.gamemember.index',
        'create'  => 'admin.gamemember.create',
        'store'   => 'admin.gamemember.store',
        'show'    => 'admin.gamemember.show',
        'edit'    => 'admin.gamemember.edit',
        'update'  => 'admin.gamemember.update',
        'destroy' => 'admin.gamemember.destroy',
    ]);
    Route::post('/gamemember/log/{gamemember_id}', [AdminGamememberController::class, 'syncgamelog'])
        ->name('admin.gamemember.syncgamelog');
    // Gameplatform Management
    Route::resource('admin/gameplatform', AdminGameplatformController::class)->names([
        'index'   => 'admin.gameplatform.index',
        'create'  => 'admin.gameplatform.create',
        'store'   => 'admin.gameplatform.store',
        'show'    => 'admin.gameplatform.show',
        'edit'    => 'admin.gameplatform.edit',
        'update'  => 'admin.gameplatform.update',
        'destroy' => 'admin.gameplatform.destroy',
    ]);
    // Gameplatformaccess Management
    Route::resource('admin/gameplatformaccess', AdminGameplatformaccessController::class)->names([
        'edit'    => 'admin.gameplatformaccess.edit',
    ]);
    Route::put('/admin/gameplatformaccess/{agent_id}', [AdminGameplatformaccessController::class, 'update'])
        ->name('admin.gameplatformaccess.update');
    // Gamelog Management
    Route::resource('admin/gamelog', AdminGamelogController::class)->names([
        'index'   => 'admin.gamelog.index',
    ]);
    // Slider Management
    Route::resource('admin/slider', AdminSliderController::class)->names([
        'index'   => 'admin.slider.index',
        'create'  => 'admin.slider.create',
        'store'   => 'admin.slider.store',
        'show'    => 'admin.slider.show',
        'edit'    => 'admin.slider.edit',
        'update'  => 'admin.slider.update',
        'destroy' => 'admin.slider.destroy',
    ]);
    // VIP Management
    Route::resource('admin/vip', AdminVIPController::class)->names([
        'index'   => 'admin.vip.index',
        'create'  => 'admin.vip.create',
        'store'   => 'admin.vip.store',
        'show'    => 'admin.vip.show',
        'edit'    => 'admin.vip.edit',
        'update'  => 'admin.vip.update',
        'destroy' => 'admin.vip.destroy',
    ]);
    // Notification Management
    Route::resource('admin/notification', AdminNotificationController::class)->names([
        'index'   => 'admin.notification.index',
        'create'  => 'admin.notification.create',
        'store'   => 'admin.notification.store',
        'show'    => 'admin.notification.show',
        'edit'    => 'admin.notification.edit',
        'update'  => 'admin.notification.update',
        'destroy' => 'admin.notification.destroy',
    ]);
    // Question Management
    Route::resource('admin/question', AdminQuestionController::class)->names([
        'index'   => 'admin.question.index',
        'create'  => 'admin.question.create',
        'store'   => 'admin.question.store',
        'show'    => 'admin.question.show',
        'edit'    => 'admin.question.edit',
        'update'  => 'admin.question.update',
        'destroy' => 'admin.question.destroy',
    ]);
    // Credit Management
    Route::resource('admin/credit', AdminCreditController::class)->names([
        'index'   => 'admin.credit.index',
        'edit'    => 'admin.credit.edit',
        'update'  => 'admin.credit.update',
        'destroy' => 'admin.credit.destroy',
    ]);
    // Managercredit Management
    Route::resource('admin/managercredit', AdminManagercreditController::class)->names([
        'index'   => 'admin.managercredit.index',
    ]);
    // Shopcredit Management
    Route::resource('admin/shopcredit', AdminShopcreditController::class)->names([
        'index'   => 'admin.shopcredit.index',
    ]);
    // Role Management
    Route::resource('admin/role', AdminRoleController::class)->names([
        'index'   => 'admin.role.index',
        'create'  => 'admin.role.create',
        'store'   => 'admin.role.store',
        'show'    => 'admin.role.show',
        'edit'    => 'admin.role.edit',
        'update'  => 'admin.role.update',
        'destroy' => 'admin.role.destroy',
    ]);
    // Access Management
    Route::resource('admin/access', AdminAccessController::class)->names([
        'edit'    => 'admin.access.edit',
        'update'  => 'admin.access.update',
    ]);
    // Feedback Management
    Route::resource('admin/feedback', AdminFeedbackController::class)->names([
        'index'   => 'admin.feedback.index',
        'edit'    => 'admin.feedback.edit',
        'update'  => 'admin.feedback.update',
        'destroy' => 'admin.feedback.destroy',
    ]);
    // Provider Management
    Route::resource('admin/provider', AdminProviderController::class)->names([
        'index'   => 'admin.provider.index',
        'edit'    => 'admin.provider.edit',
        'update'  => 'admin.provider.update',
        'destroy' => 'admin.provider.destroy',
    ]);
    // Promotion Management
    Route::resource('admin/promotion', AdminPromotionController::class)->names([
        'index'   => 'admin.promotion.index',
        'create'  => 'admin.promotion.create',
        'store'   => 'admin.promotion.store',
        'show'    => 'admin.promotion.show',
        'edit'    => 'admin.promotion.edit',
        'update'  => 'admin.promotion.update',
        'destroy' => 'admin.promotion.destroy',
    ]);
    // Recruit Management
    Route::resource('admin/recruit', AdminRecruitController::class)->names([
        'index'   => 'admin.recruit.index',
    ]);
    // Recruittutorial Management
    Route::resource('admin/recruittutorial', AdminRecruittutorialController::class)->names([
        'index'   => 'admin.recruittutorial.index',
        'create'  => 'admin.recruittutorial.create',
        'store'   => 'admin.recruittutorial.store',
        'show'    => 'admin.recruittutorial.show',
        'edit'    => 'admin.recruittutorial.edit',
        'update'  => 'admin.recruittutorial.update',
        'destroy' => 'admin.recruittutorial.destroy',
    ]);
    // Agreement Management
    Route::resource('admin/agreement', AdminAgreementController::class)->names([
        'index'   => 'admin.agreement.index',
        'create'  => 'admin.agreement.create',
        'store'   => 'admin.agreement.store',
        'show'    => 'admin.agreement.show',
        'edit'    => 'admin.agreement.edit',
        'update'  => 'admin.agreement.update',
        'destroy' => 'admin.agreement.destroy',
    ]);
    // Noticepublic Management
    Route::resource('admin/noticepublic', AdminNoticepublicController::class)->names([
        'index'   => 'admin.noticepublic.index',
        'create'  => 'admin.noticepublic.create',
        'store'   => 'admin.noticepublic.store',
        'show'    => 'admin.noticepublic.show',
        'edit'    => 'admin.noticepublic.edit',
        'update'  => 'admin.noticepublic.update',
        'destroy' => 'admin.noticepublic.destroy',
    ]);
    // Commissionrank Management
    Route::resource('admin/commissionrank', AdminCommissionrankController::class)->names([
        'index'   => 'admin.commissionrank.index',
        'create'  => 'admin.commissionrank.create',
        'store'   => 'admin.commissionrank.store',
        'show'    => 'admin.commissionrank.show',
        'edit'    => 'admin.commissionrank.edit',
        'update'  => 'admin.commissionrank.update',
        'destroy' => 'admin.commissionrank.destroy',
    ]);
    // Performance Management
    Route::resource('admin/performance', AdminPerformanceController::class)->names([
        'index'   => 'admin.performance.index',
    ]);
    // IPBlock Management
    Route::resource('admin/ipblock', AdminIPBlockController::class)->names([
        'index'   => 'admin.ipblock.index',
        'create'  => 'admin.ipblock.create',
        'store'   => 'admin.ipblock.store',
        'show'    => 'admin.ipblock.show',
        'edit'    => 'admin.ipblock.edit',
        'update'  => 'admin.ipblock.update',
        'destroy' => 'admin.ipblock.destroy',
    ]);

});

Route::get('/', function () {
    // Check if the user is authenticated
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});
// Language switcher route
Route::get('/lang/{locale}', [LocalizationController::class, 'setLang'])->name('lang.set');

// =========================================================
// !!! PUT YOUR DEBUG ROUTE HERE !!!
// =========================================================
Route::get('/debug-auth', function () {
    // You should see true, true, and a User object here!
    // dd(Auth::check(), Auth::guard('sanctum')->check(), Auth::user());
});

Route::get('/test-locale', function () {
    return 'This is a test page for locale middleware.';
})->middleware('web'); // <--- EXPLICITLY apply the 'web' middleware group

Route::get('/check-middleware-class', function () {
    // Check if the class exists
    $classExists = class_exists(SetLocale::class);
    // Check if it's an instance of the expected middleware
    $isMiddleware = is_subclass_of(SetLocale::class, \Illuminate\Http\Middleware\TrustProxies::class) || // Not quite right, but for testing
        is_subclass_of(SetLocale::class, \Illuminate\Foundation\Http\Kernel::class) || // Not quite right
        is_subclass_of(SetLocale::class, \Illuminate\Http\Middleware\HandleCors::class); // Not quite right
    // The correct check for middleware would be if it implements Middleware interface or extends a base middleware class
    // For simplicity, just check class existence here.

    // dd('Class exists: ' . ($classExists ? 'Yes' : 'No'));
});
// Add this temporary route if you haven't already
Route::get('/test-translation', function () {
    // You should see the translation for 'dashboard' in the current locale
    // dd(__('messages.dashboard'), App::getLocale());
})->middleware('web'); // Ensure this route uses the 'web' middleware group
// =========================================================
