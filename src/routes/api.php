<?php

use App\Http\Controllers\TicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');
// use App\Http\Controllers\TicketController;
Route::get('/ticket-status', [TicketController::class, 'getStatus']);
Route::post('/buy-no-lock', [TicketController::class, 'orderWithoutLock']);
Route::post('/buy-with-lock', [TicketController::class, 'orderWithRedisLock']);