<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\AdvisorController;
use App\Http\Controllers\ShortlistController;
use App\Http\Controllers\BrokerController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| HanapBahay AI – API v1 Routes
|--------------------------------------------------------------------------
| Versioned under /api/v1 (no Accept-Version header required).
| All write endpoints need 'auth:sanctum' guard.
| Rate limits: anonymous 60/min, authenticated 300/min, AI 20/min.
*/

Route::prefix('v1')->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('logout',          [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('me',               [AuthController::class, 'me'])->middleware('auth:sanctum');
        Route::post('refresh',         [AuthController::class, 'refresh'])->middleware('auth:sanctum');
        Route::post('otp/request',     [AuthController::class, 'requestOtp']);
        Route::post('otp/verify',      [AuthController::class, 'verifyOtp']);

        // OAuth
        Route::get('google',           [AuthController::class, 'redirectToGoogle']);
        Route::get('google/callback',  [AuthController::class, 'handleGoogleCallback']);
        Route::get('apple',            [AuthController::class, 'redirectToApple']);
        Route::post('apple/callback',  [AuthController::class, 'handleAppleCallback']);
    });

    // ── Profile / Onboarding ─────────────────────────────────────────────
    Route::prefix('profile')->middleware('auth:sanctum')->group(function () {
        Route::get('/',                [ProfileController::class, 'show']);
        Route::put('/',                [ProfileController::class, 'update']);
        Route::post('onboarding',      [ProfileController::class, 'completeOnboarding']);
        Route::get('archetype',        [ProfileController::class, 'archetype']);
        Route::delete('/',             [ProfileController::class, 'destroy']);
    });

    // ── Listings ─────────────────────────────────────────────────────────
    Route::prefix('listings')->group(function () {
        Route::get('/',                [ListingController::class, 'index']);
        Route::get('{listing:slug}',   [ListingController::class, 'show']);
        Route::get('{listing:slug}/score', [ListingController::class, 'score'])->middleware('auth:sanctum');
        Route::get('{listing:slug}/similar',  [ListingController::class, 'similar']);

        // Broker-only mutations
        Route::middleware(['auth:sanctum', 'role:broker|admin'])->group(function () {
            Route::post('/',           [ListingController::class, 'store']);
            Route::put('{listing}',    [ListingController::class, 'update']);
            Route::delete('{listing}', [ListingController::class, 'destroy']);
        });
    });

    // ── Financial Simulator ──────────────────────────────────────────────
    Route::prefix('financial')->group(function () {
        Route::post('simulate',        [FinancialController::class, 'simulate'])->middleware('throttle:60,1');
        Route::post('pagibig',         [FinancialController::class, 'pagibig'])->middleware('throttle:60,1');
        Route::post('bank',            [FinancialController::class, 'bank'])->middleware('throttle:60,1');
        Route::post('dti',             [FinancialController::class, 'dti'])->middleware('throttle:60,1');
        Route::post('hidden-costs',    [FinancialController::class, 'hiddenCosts'])->middleware('throttle:60,1');
        Route::post('stress-test',     [FinancialController::class, 'stressTest'])->middleware('throttle:60,1');
    });

    // ── AI Advisor (SSE streaming) ───────────────────────────────────────
    Route::prefix('advisor')->middleware(['auth:sanctum', 'throttle:20,1'])->group(function () {
        Route::get('consultations',    [AdvisorController::class, 'index']);
        Route::post('consultations',   [AdvisorController::class, 'create']);
        Route::get('consultations/{id}',   [AdvisorController::class, 'show']);
        Route::delete('consultations/{id}',[AdvisorController::class, 'destroy']);
        Route::post('consultations/{id}/messages', [AdvisorController::class, 'sendMessage']); // SSE
        Route::get('recommendations',  [AdvisorController::class, 'recommendations']);
    });

    // ── Shortlists ───────────────────────────────────────────────────────
    Route::prefix('shortlists')->middleware('auth:sanctum')->group(function () {
        Route::get('/',                [ShortlistController::class, 'index']);
        Route::post('/',               [ShortlistController::class, 'store']);
        Route::put('{shortlist}',      [ShortlistController::class, 'update']);
        Route::delete('{shortlist}',   [ShortlistController::class, 'destroy']);
        Route::post('{shortlist}/listings/{listing}', [ShortlistController::class, 'addListing']);
        Route::delete('{shortlist}/listings/{listing}', [ShortlistController::class, 'removeListing']);
    });

    // ── Brokers ──────────────────────────────────────────────────────────
    Route::prefix('brokers')->group(function () {
        Route::get('/',                [BrokerController::class, 'index']);
        Route::get('{broker}',         [BrokerController::class, 'show']);
        Route::post('apply',           [BrokerController::class, 'apply'])->middleware('auth:sanctum');
        Route::post('{broker}/verify', [BrokerController::class, 'verifyKyc'])->middleware(['auth:sanctum', 'role:admin']);
    });

    // ── Admin ────────────────────────────────────────────────────────────
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('listings/pending', [AdminController::class, 'pendingListings']);
        Route::post('listings/{listing}/approve', [AdminController::class, 'approveListing']);
        Route::post('listings/{listing}/reject',  [AdminController::class, 'rejectListing']);
        Route::get('users',            [AdminController::class, 'users']);
        Route::get('metrics',          [AdminController::class, 'metrics']);
        Route::post('ingestion/trigger', [AdminController::class, 'triggerIngestion']);
    });

    // ── Health ───────────────────────────────────────────────────────────
    Route::get('health', fn () => response()->json(['status' => 'ok', 'service' => 'hanapbahay-api']));
});
