<?php

use App\Http\Controllers\Access\AccessController;
use App\Http\Controllers\Access\DemoController;
use App\Http\Controllers\Conferences\ConferenceController;
use App\Http\Controllers\Imports\ExampleController;
use App\Http\Controllers\Imports\SourceController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/entrar');
Route::get('/modelos/{kind}.csv', [ExampleController::class, 'template'])->whereIn('kind', ['orders', 'payouts']);
Route::get('/exemplos/{kind}.csv', [ExampleController::class, 'example'])->whereIn('kind', ['orders', 'payouts']);
Route::get('/entrar', fn () => Inertia::render('Auth/Login', ['demoEnabled' => config('lastro.demo_enabled')]))->name('login');
Route::get('/csrf', fn (Request $request) => response()->json(['token' => $request->session()->token()]));
Route::post('/login', [AccessController::class, 'login'])->middleware('throttle:access');
Route::post('/logout', [AccessController::class, 'logout']);
Route::get('/demo', fn (Request $request) => redirect()->route($request->user() === null ? 'login' : 'conferences.index'));
Route::post('/demo/start', [DemoController::class, 'start'])->middleware('throttle:access');
Route::post('/demo/reset', [DemoController::class, 'reset'])->middleware(ResolveWorkspace::class);
Route::middleware(ResolveWorkspace::class)->group(function (): void {
    Route::get('/conferencias', [ConferenceController::class, 'index'])->name('conferences.index');
    Route::post('/conferencias', [ConferenceController::class, 'store']);
    Route::get('/conferencias/{id}', [ConferenceController::class, 'show'])->whereUuid('id')->name('conferences.show');
    Route::patch('/conferencias/{id}', [ConferenceController::class, 'update'])->whereUuid('id');
    Route::get('/conferencias/{id}/fontes/{kind}/preview', [SourceController::class, 'show'])->whereUuid('id')->whereIn('kind', ['orders', 'payouts']);
    Route::post('/conferencias/{id}/fontes/{kind}/preview', [SourceController::class, 'preview'])->whereUuid('id')->whereIn('kind', ['orders', 'payouts'])->middleware('throttle:imports');
    Route::post('/conferencias/{id}/fontes/{kind}/confirm', [SourceController::class, 'confirm'])->whereUuid('id')->whereIn('kind', ['orders', 'payouts'])->middleware('throttle:imports');
});
