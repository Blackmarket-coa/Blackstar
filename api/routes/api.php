<?php

use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\FleetController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::apiResource('nodes', NodeController::class);
    Route::apiResource('fleets', FleetController::class);
    Route::apiResource('vehicles', VehicleController::class);
    Route::apiResource('drivers', DriverController::class);
});
