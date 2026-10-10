<?php

use App\Http\Controllers\Api\SensorController;
use Illuminate\Support\Facades\Route;

Route::post('/sensor', [SensorController::class, 'store']);
Route::get('/sensor/latest', [SensorController::class, 'latest']);