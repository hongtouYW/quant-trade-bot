<?php

use App\Http\Controllers\PhotoApiController;
use App\Http\Controllers\PostApiController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::middleware('apiLog')->group(function () {
    Route::middleware('apiKey')->group(function () {
        Route::post('/video', [VideoApiController::class, 'video'])->name('video');
        Route::post('/post', [PostApiController::class, 'post'])->name('post');
        Route::post('/postSendCallback', [PostApiController::class, 'postSendCallback'])->name('postSendCallback');
        Route::post('/videoCallback', [VideoApiController::class, 'videoCallback'])->name('videoCallback');
        Route::post('/videoServerInfo', [VideoApiController::class, 'videoServerInfo'])->name('videoServerInfo');
        Route::post('/videoSyncCallback', [VideoApiController::class, 'videoSyncCallback'])->name('videoSyncCallback');
        Route::post('/updateMsgCallback', [VideoApiController::class, 'updateMsgCallback'])->name('updateMsgCallback');
        Route::post('/videoChoose/recut', [VideoApiController::class, 'recut'])->name('recut');
        Route::post('/videoChoose/resetCut', [VideoApiController::class, 'resetCut'])->name('resetCut');
        Route::post('/video/down', [VideoApiController::class, 'down'])->name('down');

        Route::post('/videoChoose/getRule', [VideoApiController::class, 'getVideoChooseRule'])->name('getVideoChooseRule');

        Route::post('/ftp', [VideoApiController::class, 'ftp'])->name('ftp');

        Route::post('/photo', [PhotoApiController::class, 'photo'])->name('photo');
        Route::post('/videoImport', [VideoApiController::class, 'videoImport'])->name('videoImport');
        Route::post('/videoImport2', [VideoApiController::class, 'videoImport2'])->name('videoImport2');
        Route::post('/videoCustomImport', [VideoApiController::class, 'videoCustomImport'])->name('videoCustomImport');
        Route::post('/videoCustomImportDirect', [VideoApiController::class, 'videoCustomImportDirect'])->name('videoCustomImportDirect');

        Route::post('/videoStatistics', [VideoApiController::class, 'videoStatistics'])->name('videoStatistics');

        Route::post('/videoReviewInfo', [VideoApiController::class, 'videoReviewInfo'])->name('videoReviewInfo');
        Route::post('/videoChooseReviewInfo', [VideoApiController::class, 'videoChooseReviewInfo'])->name('videoChooseReviewInfo');

        Route::post('/video/getSubtitle', [VideoApiController::class, 'getSubtitle'])->name('getSubtitle');

        Route::post('/testCallback', [VideoApiController::class, 'testCallback'])->name('testCallback');
    });
});

Route::fallback(function () {
    return 'Invalid';
});

