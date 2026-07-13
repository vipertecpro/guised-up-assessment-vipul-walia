<?php

use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\InteractionController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/feed', FeedController::class);
    Route::get('/search', SearchController::class);
    Route::post('/interactions', [InteractionController::class, 'store']);
});
