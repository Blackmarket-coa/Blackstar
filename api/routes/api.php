<?php

use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\FleetController;
use App\Http\Controllers\Api\NodeController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\ShipmentPaymentReferenceController;
use App\Http\Controllers\Api\ShipmentBoardListingController;
use App\Http\Controllers\Api\Webhooks\FreeBlackMarketWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/freeblackmarket', [FreeBlackMarketWebhookController::class, 'handle']);
Route::post('webhooks/freeblackmarket/retry', [FreeBlackMarketWebhookController::class, 'retry']);

Route::middleware('auth')->group(function () {
    Route::apiResource('nodes', NodeController::class);
    Route::apiResource('fleets', FleetController::class);
    Route::apiResource('vehicles', VehicleController::class);
    Route::apiResource('drivers', DriverController::class);

    Route::post('shipment-board-listings', [ShipmentBoardListingController::class, 'store']);
    Route::get('shipment-board-listings/eligible', [ShipmentBoardListingController::class, 'eligibleListings']);
    Route::post('shipment-board-listings/{shipmentBoardListing}/claim', [ShipmentBoardListingController::class, 'claim']);
    Route::post('shipment-board-listings/{shipmentBoardListing}/bids', [ShipmentBoardListingController::class, 'submitBid']);
    Route::post('shipment-board-listings/{shipmentBoardListing}/status', [ShipmentBoardListingController::class, 'updateStatus']);

    Route::get('shipment-payment-references', [ShipmentPaymentReferenceController::class, 'index']);
    Route::get('shipment-payment-references/{shipmentPaymentReference}', [ShipmentPaymentReferenceController::class, 'show']);
    Route::post('shipment-payment-references', [ShipmentPaymentReferenceController::class, 'store']);
    Route::patch('shipment-payment-references/{shipmentPaymentReference}', [ShipmentPaymentReferenceController::class, 'update']);
});
