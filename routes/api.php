<?php

use App\Http\Controllers\AlumniController;
use App\Http\Controllers\AlumniBlastController;
use App\Http\Controllers\AlumniSearchController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CareerAdvisorController;
use App\Http\Controllers\CtaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardInsightsController;
use App\Http\Controllers\TracerAccreditationController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\ResponseExportController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WilayahController;
use App\Http\Controllers\SurveyTokenController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['message' => 'API OK']);
});

// Auth Sanctum (SPA)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// --- PUBLIC SURVEY ACCESS (Open or Secure) ---
Route::post('/surveys/validate-token', [SurveyTokenController::class, 'validateToken'])->middleware('throttle:public');
Route::post('/responses/submit-via-token', [ResponseController::class, 'submitViaToken'])->middleware('throttle:submit');
Route::post('/responses/submit', [ResponseController::class, 'submit'])->middleware('throttle:submit'); // Public Access by NIM

// --- PUBLIC UTILITIES ---
Route::get('/wilayah/provinces', [WilayahController::class, 'provinces']);
Route::get('/wilayah/regencies/{province}', [WilayahController::class, 'regencies']);

// --- PUBLIC CONTENT (HOME, PROFIL, JOBS, NEWS) ---
Route::get('/homepage', [HomepageController::class, 'show']);
Route::get('/cta', [CtaController::class, 'index']);
Route::get('/jobs', [JobController::class, 'index']);
Route::get('/jobs/{id}', [JobController::class, 'show']);
Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show']);

// --- PUBLIC QUESTIONNAIRE & LOOKUP ---
Route::get('/questionnaires/active', [QuestionnaireController::class, 'active']);
Route::get('/questionnaires/{questionnaire}', [QuestionnaireController::class, 'show']);
Route::get('/questionnaires/{questionnaire}/questions', [QuestionController::class, 'index']);
Route::get('/alumni/lookup/{nim}', [AlumniController::class, 'lookupByNim'])->middleware('throttle:public');
Route::get('/alumni/search', [AlumniSearchController::class, 'search'])->middleware('throttle:public');

// --- PROTECTED ROUTES ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', [UserProfileController::class, 'show']);
    Route::put('/user', [UserProfileController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Route::get('/alumni/search', [AlumniSearchController::class, 'search']); // Moved to Public
    // Route::get('/alumni/lookup/{nim}', [AlumniController::class, 'lookupByNim']); // Moved to Public

    // Questionnaire Resources
    // Route::get('/questionnaires/active', [QuestionnaireController::class, 'active']); // Moved to Public
    Route::apiResource('questionnaires', QuestionnaireController::class)->except(['show']);
    Route::apiResource('questionnaires.questions', QuestionController::class)->shallow()->except(['index']);
    Route::get('/questionnaires/{questionnaire}/responses', [ResponseController::class, 'byQuestionnaire']);
    Route::get('/questionnaires/{questionnaire}/responses/summary', [ResponseController::class, 'summaryByQuestionnaire']);
    Route::delete('/questionnaires/{questionnaire}', [QuestionnaireController::class, 'destroy']);

    Route::post('/exports/responses', [ResponseExportController::class, 'store'])->middleware('throttle:export');
    Route::get('/exports/{export}', [ResponseExportController::class, 'show'])->middleware('throttle:export');
    Route::get('/exports/{export}/download', [ResponseExportController::class, 'download'])->middleware('throttle:export');

    // Responses (Authenticated Submission - Optional if using public endpoint, but kept for legacy auth checks if needed)
    // Route::post('/responses/submit', [ResponseController::class, 'submitAuthenticated']); // Moved to public
    Route::delete('/responses/{id}', [ResponseController::class, 'destroy']);
    Route::get('/responses/attempt/{attempt_id}', [ResponseController::class, 'getAttemptDetail']);
    Route::get('/responses/{alumni_id}', [ResponseController::class, 'getAttempts']);

    // Dashboard tracer (Protected)
    Route::get('/dashboard', [DashboardController::class, 'sample']);
    Route::get('/dashboard/summary', [DashboardController::class, 'adminSummary']);
    Route::get('/dashboard/tracer/accreditation-summary', [TracerAccreditationController::class, 'summary']);
    Route::get('/dashboard/tracer', [DashboardController::class, 'tracerSummaryQuery']);
    Route::get('/dashboard/tracer/insights', [DashboardInsightsController::class, 'tracerInsights']);
    Route::get('/dashboard/tracer/{questionnaire_id}', [DashboardController::class, 'tracerSummary'])
        ->whereNumber('questionnaire_id');

    // Jobs (Admin Management)
    Route::post('/jobs', [JobController::class, 'store']);
    Route::put('/jobs/{id}', [JobController::class, 'update']);
    Route::delete('/jobs/{id}', [JobController::class, 'destroy']);
    Route::post('/jobs/{id}/publish', [JobController::class, 'publish']);
    Route::post('/jobs/{id}/close', [JobController::class, 'close']);

    // News (Admin Management)
    Route::post('/news', [NewsController::class, 'store']);
    Route::put('/news/{id}', [NewsController::class, 'update']);
    Route::delete('/news/{id}', [NewsController::class, 'destroy']);

    // Homepage Management
    Route::put('/homepage', [HomepageController::class, 'update']);
    Route::put('/cta', [CtaController::class, 'update']);

    // Question bank
    Route::get('/question-bank', [QuestionBankController::class, 'index']);
    Route::post('/question-bank', [QuestionBankController::class, 'store']);
    Route::get('/question-bank/{id}', [QuestionBankController::class, 'show']);
    Route::put('/question-bank/{id}', [QuestionBankController::class, 'update']);
    Route::delete('/question-bank/{id}', [QuestionBankController::class, 'destroy']);

    // Admin Tools
    Route::post('/admin/generate-survey-link', [SurveyTokenController::class, 'generate']);

    // Admin Role Specific
    Route::middleware(['role:super_admin,admin_universitas,admin_fakultas,admin_prodi'])
        ->group(function () {
            Route::get('/admin/alumni', [AlumniController::class, 'index']);
            Route::get('/admin/alumni/{alumni}', [AlumniController::class, 'show']);
            Route::put('/admin/alumni/{alumni}', [AlumniController::class, 'update']);
            Route::delete('/admin/alumni/{alumni}', [AlumniController::class, 'destroy']);
            Route::post('/admin/alumni/blast-email', [AlumniBlastController::class, 'send']);
            Route::get('/admin/email-templates/{key}', [EmailTemplateController::class, 'show']);
            Route::put('/admin/email-templates/{key}', [EmailTemplateController::class, 'update']);

            // Bulk Import
            Route::post('/admin/alumni/import', [AlumniController::class, 'import']);
            Route::post('/admin/alumni/import-preview', [AlumniController::class, 'importPreview']);
            Route::get('/admin/alumni/import-progress/{importId}', [AlumniController::class, 'importProgress']);
            Route::get('/admin/alumni/import-report/{importId}', [AlumniController::class, 'downloadImportReport']);
            Route::post('/alumni/import', [AlumniController::class, 'import']);
            Route::post('/import-alumni', [AlumniController::class, 'import']);

            // Bulk Submission (Admin Only)
            Route::post('/submissions/bulk', [ResponseController::class, 'submitBulk']);
        });

    Route::middleware(['role:super_admin,admin_universitas'])
        ->get('/admin/kuisioner', [QuestionnaireController::class, 'index']);

    Route::prefix('v1/career-advisor')->group(function () {
        Route::get('/options', [CareerAdvisorController::class, 'options']);
        Route::post('/sessions', [CareerAdvisorController::class, 'createSession']);
        Route::patch('/sessions/{session}/profile', [CareerAdvisorController::class, 'updateProfile']);
        Route::post('/sessions/{session}/generate', [CareerAdvisorController::class, 'generate'])->middleware('throttle:submit');
        Route::get('/sessions/{session}/result', [CareerAdvisorController::class, 'result']);
        Route::post('/sessions/{session}/action', [CareerAdvisorController::class, 'saveAction']);
        Route::post('/sessions/{session}/feedback', [CareerAdvisorController::class, 'saveFeedback']);
    });

    // Admin Users
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users', [AdminUserController::class, 'store']);
    Route::put('/admin/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{id}', [AdminUserController::class, 'destroy']);
    Route::post('/admin/users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
});
