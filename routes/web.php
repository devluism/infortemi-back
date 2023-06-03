<?php

use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

use App\Http\Controllers\UserController;
use App\Services\PagueloFacilService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

Route::get('/clear-cache', function () {
    Artisan::call('optimize:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:cache');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    return 'DONE'; //Return anything
});

Route::get('/', function () {
    return view('welcome');
});
Route::get('/mail', function () {
    return view('mails/contactUs');
});

Route::post('paguelo-response', [ReportsController::class, 'pagueloFacilResponse']);

Route::get('/paguelo', function(){
    $pagueloService = new PagueloFacilService();
    $result = $pagueloService->makeTransaction();
    header("Location: $result");
    die();
});

// Users Administration Routes
Route::prefix('users')->group(function () {
    Route::get('/',[UserController::class, 'index'])->name('users.profile');
    // Route::post('/update',[UserController::class, 'update'])->name('users.update');
    // Route::post('/photo/update',[UserController::class, 'updatePhoto'])->name('users.photo.update');
    // Route::get('/photo/delete',[UserController::class, 'deletePhoto'])->name('users.photo.delete');
});