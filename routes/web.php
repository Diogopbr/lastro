<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/entrar');
Route::get('/entrar', fn () => Inertia::render('Auth/Login'))->name('login');
