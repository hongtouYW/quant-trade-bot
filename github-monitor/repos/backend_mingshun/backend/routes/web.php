<?php

use App\Http\Controllers\AuthorController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\CutServerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FtpController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PhotoProjectRuleController;
use App\Http\Controllers\PostChooseController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectRulesController;
use App\Http\Controllers\ProjectServersController;
use App\Http\Controllers\ProjectTypesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SubtitleLanguageController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TypeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoChooseController;
use App\Http\Controllers\VideoController;

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
// 1:超级管理员，2:分类主管，3:上传手，4:审核手，5:项目运营，6:项目主管
Route::middleware('auth')->group(function () {
    Route::group(['middleware' => 'role:1,999'], function () {
        Route::get('/chart/cutRealServerStatus', [ChartController::class, 'cutRealServerStatus'])->name('chart.cutRealServerStatus');
        Route::get('/chart/cutRealServerStatusSolo', [ChartController::class, 'cutRealServerStatusSolo'])->name('chart.cutRealServerStatusSolo');
    });

    Route::group(['middleware' => 'role:1,2,3,6'], function () {
        Route::resource('photos', PhotoController::class);
        Route::resource('pphotorules', PhotoProjectRuleController::class);
        Route::put('/pphotorules/changeStatus/{id}', [PhotoProjectRuleController::class, 'changeStatus'])->name('pphotorules.changeStatus');
    });

    Route::group(['middleware' => 'role:1'], function () {
        Route::get('/chart/dailyVideoType', [ChartController::class, 'dailyVideoType'])->name('chart.dailyVideoType');
        Route::get('/chart/dailyVideoUploader', [ChartController::class, 'dailyVideoUploader'])->name('chart.dailyVideoUploader');
        Route::get('/chart/dailyVideoReviewer', [ChartController::class, 'dailyVideoReviewer'])->name('chart.dailyVideoReviewer');
        Route::get('/chart/dailyVideoChoose', [ChartController::class, 'dailyVideoChoose'])->name('chart.dailyVideoChoose');
        Route::get('/chart/dailyCut', [ChartController::class, 'dailyCut'])->name('chart.dailyCut');
        Route::get('/chart/dailyAiGenerate', [ChartController::class, 'dailyAiGenerate'])->name('chart.dailyAiGenerate');

        Route::get('/chart/dailyPhoto', [ChartController::class, 'dailyPhoto'])->name('chart.dailyPhoto');
        Route::get('/chart/videoChooseStatistic', [ChartController::class, 'videoChooseStatistic'])->name('chart.videoChooseStatistic');
        Route::resource('users', UserController::class)->except([
            'show', 'update', 'edit'
        ]);
        Route::put('/users/changeStatus/{id}', [UserController::class, 'changeStatus'])->name('users.changeStatus');
        Route::resource('servers', ServerController::class);
        Route::resource('cutServer', CutServerController::class);
        Route::resource('configs', ConfigController::class);
        Route::put('/servers/changeStatus/{id}', [ServerController::class, 'changeStatus'])->name('servers.changeStatus');
        Route::resource('ftps', FtpController::class)->except([
            'index', 'update', 'edit'
        ]);
        Route::get('/ftpReset', [FtpController::class, 'ftpReset'])->name('ftp.ftpReset');
        Route::resource('projects', ProjectController::class);
        Route::resource('logs', LogController::class)->only([
            'index'
        ]);
        Route::get('/videosChoose/show/{id}', [VideoChooseController::class, 'show'])->name('videoChoose.show');

        Route::get('/temp/videosChoose/cut', [VideoChooseController::class, 'cut'])->name('videoChoose.cut');

        Route::post('/videosChoose/changeStatusModal', [VideoChooseController::class, 'changeStatusModal'])->name('videoChoose.changeStatusModal');
    
        Route::get('/temp/temporary', [HomeController::class, 'temp']);

        Route::get('/export', [HomeController::class, 'exportExcel']);
    });

    Route::group(['middleware' => 'role:1,2,3,4,7'], function () {
        Route::resource('videos', VideoController::class)->only([
            'edit', 'update'
        ]);
        Route::resource('posts', PostController::class)->only([
            'edit', 'update'
        ]);
    });

    Route::group(['middleware' => 'role:1,7'], function () {
        Route::post('/videos/{id}/generateCover', [VideoController::class, 'generateCover'])->name('videos.generate');
    });

    Route::group(['middleware' => 'role:1,3'], function () {
        Route::resource('ftps', FtpController::class)->only([
            'index', 'update', 'edit'
        ]);
        Route::resource('videos', VideoController::class)->except([
            'index', 'show', 'edit', 'update'
        ]);
        Route::resource('posts', PostController::class)->except([
            'index', 'show', 'edit', 'update'
        ]);
    });

    Route::group(['middleware' => 'role:1,2,3,4,7'], function () {
        Route::get('/calendar', [HomeController::class, 'calendar'])->name('calendar');
        Route::post('/getMonthData', [HomeController::class, 'getMonthData'])->name('getMonthData');
        Route::post('/getMonthDataClick', [HomeController::class, 'getMonthDataClick'])->name('getMonthDataClick');
    });

    Route::group(['middleware' => 'role:1,2,4,7'], function () {
        Route::get('/videosSuccess', [VideoController::class, 'successedIndex'])->name('videosSuccess.index');
        Route::get('/videosRereview', [VideoController::class, 'reReviewIndex'])->name('videosRereview.index');

        Route::get('/dailyQuest', [VideoController::class, 'dailyQuest'])->name('videos.dailyQuest');
        Route::get('/extraQuest', [VideoController::class, 'extraQuest'])->name('videos.extraQuest');
    });

    Route::group(['middleware' => 'role:1,2,3'], function () {
        Route::resource('types', TypeController::class)->only([
            'index'
        ]);
    });

    Route::group(['middleware' => 'role:1,2'], function () {
        Route::resource('types', TypeController::class)->except([
            'index'
        ]);
        Route::put('/tags/changeStatus/{id}', [TagController::class, 'changeStatus'])->name('tags.changeStatus');
        Route::post('/tags/import', [TagController::class, 'import'])->name('tags.import');
        Route::get('/tags/import/example', [TagController::class, 'importExample'])->name('tags.importExample');
        Route::get('/export/tags', [TagController::class, 'export'])->name('tags.export');
        Route::resource('tags', TagController::class);
        Route::put('/types/changeStatus/{id}', [TypeController::class, 'changeStatus'])->name('types.changeStatus');
        Route::post('/types/import', [TypeController::class, 'import'])->name('types.import');
        Route::get('/types/import/example', [TypeController::class, 'importExample'])->name('types.importExample');
        Route::resource('authors', AuthorController::class);
        Route::put('/authors/changeStatus/{id}', [AuthorController::class, 'changeStatus'])->name('authors.changeStatus');
        Route::post('/authors/import', [AuthorController::class, 'import'])->name('authors.import');
        Route::get('/authors/import/example', [AuthorController::class, 'importExample'])->name('authors.importExample');
        Route::get('/postsFailed', [PostController::class, 'failedIndex'])->name('postsFailed.index');
        Route::resource('subtitleLanguages', SubtitleLanguageController::class);
        Route::put('/subtitleLanguages/changeStatus/{id}', [SubtitleLanguageController::class, 'changeStatus'])->name('subtitleLanguages.changeStatus');
    });

    Route::group(['middleware' => 'role:1,2,3,5,6'], function () {
        Route::get('/videosChooseHistory', [VideoChooseController::class, 'historyIndex'])->name('videoChooseHistory.index');
        Route::resource('prules', ProjectRulesController::class)->only([
            'index'
        ]);
        Route::resource('ptypes', ProjectTypesController::class)->only([
            'index'
        ]);
    });

    Route::group(['middleware' => 'role:1,2,5,6'], function () {
        Route::get('/videosChoose/{id}', [VideoChooseController::class, 'edit'])->name('videoChoose.edit');
        Route::put('/videosChoose/{id}', [VideoChooseController::class, 'update'])->name('videoChoose.update');
        Route::get('/videosChoose', [VideoChooseController::class, 'index'])->name('videoChoose.index');
        Route::put('/videosChoose/changeStatus/{id}', [VideoChooseController::class, 'changeStatus'])->name('videoChoose.changeStatus');
        Route::post('/videosChoose/cutStatus', [VideoChooseController::class, 'cutStatus'])->name('videoChoose.cutStatus');
        Route::get('/videosChooseCallback/{id}', [VideoChooseController::class, 'callback'])->name('videoChoose.callback');

        Route::get('/postsChoose', [PostChooseController::class, 'index'])->name('postsChoose.index');
        Route::put('/postsChoose/changeStatus/{id}', [PostChooseController::class, 'changeStatus'])->name('postsChoose.changeStatus');
        Route::post('/postsChoose/cutStatus', [PostChooseController::class, 'cutStatus'])->name('postsChoose.cutStatus');
        Route::get('/postsChooseHistory', [PostChooseController::class, 'historyIndex'])->name('postsChooseHistory.index');

        Route::resource('prules', ProjectRulesController::class)->except([
            'index'
        ]);
        Route::put('/prules/changeStatus/{id}', [ProjectRulesController::class, 'changeStatus'])->name('prules.changeStatus');
        Route::resource('ptypes', ProjectTypesController::class)->except([
            'index'
        ]);
        Route::put('/ptypes/changeStatus/{id}', [ProjectTypesController::class, 'changeStatus'])->name('ptypes.changeStatus');
        Route::get('/pservers', [ProjectServersController::class, 'form'])->name('pservers.form');
        Route::post('/pservers', [ProjectServersController::class, 'store'])->name('pservers.store');

        Route::post('/videosChoose/massChangeStatus', [VideoChooseController::class, 'massChangeStatus'])->name('videoChoose.massChangeStatus');
    });

    Route::group(['middleware' => 'role:1,2,3,4,5,6,7'], function () {
        Route::resource('videos', VideoController::class)->only([
            'index', 'show'
        ]);
        Route::resource('posts', PostController::class)->only([
            'index', 'show'
        ]);
        Route::resource('users', UserController::class)->only([
            'update', 'edit', 'show'
        ]);
        Route::put('/videos/changeStatus/{id}', [VideoController::class, 'changeStatus'])->name('videos.changeStatus');
        Route::post('/videos/changeStatusModal', [VideoController::class, 'changeStatusModal'])->name('videos.changeStatusModal');
        Route::post('/videos/massChangeStatus', [VideoController::class, 'massChangeStatus'])->name('videos.massChangeStatus');
    
        Route::put('/posts/changeStatus/{id}', [PostController::class, 'changeStatus'])->name('posts.changeStatus');
        Route::post('/posts/changeStatusModal', [PostController::class, 'changeStatusModal'])->name('posts.changeStatusModal');
        Route::post('/posts/massChangeStatus', [PostController::class, 'massChangeStatus'])->name('posts.massChangeStatus');
    
        Route::post('/tempUpload', [HomeController::class, 'tempUpload'])->name('tempUpload');
        Route::post('/tempUploadVideo', [HomeController::class, 'tempUploadVideo'])->name('tempUploadVideo');
    
        Route::get('/projectsSelect', [ProjectController::class, 'select'])->name('projects.select');
        Route::get('/serversSelect', [ServerController::class, 'select'])->name('servers.select');
        Route::get('/rolesSelect', [RoleController::class, 'select'])->name('roles.select');
        Route::get('/tagsSelect', [TagController::class, 'select'])->name('tags.select');
        Route::get('/typesSelect', [TypeController::class, 'select'])->name('types.select');
        Route::get('/authorSelect', [AuthorController::class, 'select'])->name('authors.select');
        Route::get('/userSelect', [UserController::class, 'select'])->name('users.select');
        Route::get('/sourceSelect', [VideoController::class, 'sourceSelect'])->name('source.select');
        Route::get('/websiteSelect', [VideoController::class, 'websiteSelect'])->name('website.select');
        Route::get('/projectSelect', [ProjectTypesController::class, 'select'])->name('projectType.select');
        Route::get('/showSelect', [ProjectTypesController::class, 'showSelect'])->name('projectType.showSelect');
    
        Route::get('/userSelectCoverer', [UserController::class, 'selectCoverer'])->name('users.coverer.select');
        Route::get('/userSelectReviewer', [UserController::class, 'selectReviewer'])->name('users.reviewer.select');
        Route::get('/userSelectUploader', [UserController::class, 'selectUploader'])->name('users.uploader.select');

        Route::get('/userProjecter', [UserController::class, 'selectUserProject'])->name('users.project.select');
    });
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::get('/login', [LoginController::class, 'loginPage'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::fallback(function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});
