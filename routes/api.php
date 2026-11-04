<?php

use App\Http\Controllers\Api\AgentController;
use Illuminate\Support\Facades\Route;

/*
 * SỬA 4/11 — API cho phần mềm Chocon Desktop (máy tính của con). Tiền tố /api do bootstrap/app.php gắn.
 *   /api/agent/v1/pair  (không cần token, giới hạn 10 lần/phút để chống dò mã)
 *   các API còn lại cần header Authorization: Bearer <token thiết bị>
 */
Route::prefix('agent/v1')->group(function () {
    Route::post('pair', [AgentController::class, 'pair'])->middleware('throttle:10,1,agent-pair');

    Route::middleware(['agent.device', 'throttle:240,1,agent'])->group(function () {
        Route::get('policy', [AgentController::class, 'policy']);
        Route::post('activity', [AgentController::class, 'activity']);
        Route::post('events', [AgentController::class, 'events']);
        Route::post('heartbeat', [AgentController::class, 'heartbeat']);
        Route::post('extra-time-requests', [AgentController::class, 'extraTime'])->middleware('throttle:10,1,agent-extra');
        Route::get('update', [AgentController::class, 'update']);
    });
});
