<?php

use App\Http\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::post('/usage', [UsageController::class, 'store'])->middleware('throttle:usage');
