<?php

use Illuminate\Support\Facades\Route;

// Fortify のコントローラ（ビュー表示用の GET で使用）
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Laravel\Fortify\Http\Controllers\EmailVerificationPromptController;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StampCorrectionApprovalController as AdminStampCorrectionApprovalController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;

/*
|--------------------------------------------------------------------------
| ゲスト（一般ユーザー）: ログイン／会員登録／メール認証誘導
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    // ログイン画面（GET）  name: login
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');

    // 会員登録画面（GET）  name: register
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
});

// メール認証の誘導画面（GET） name: verification.notice
Route::middleware('auth')->get('/email/verify', [EmailVerificationPromptController::class, '__invoke'])
    ->name('verification.notice');

/*
|--------------------------------------------------------------------------
| 一般ユーザー（認証＆メール認証済）
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    // 打刻画面
    Route::get('/attendance/stamp', [AttendanceController::class, 'stamp'])->name('attendance.stamp');

    // 勤怠一覧（画面名に合わせて .list）
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.list');

    // 勤怠詳細
    Route::get('/attendance/{attendanceDay}', [AttendanceController::class, 'show'])->name('attendance.show');

    // 申請一覧（ユーザー／管理者共通ビュー）
    Route::get('/stamp-correction-requests', [StampCorrectionRequestController::class, 'index'])
        ->name('stamp_correction_request.list');
});

/*
|--------------------------------------------------------------------------
| 管理者ログイン（一般ログインと分離）
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware('guest')->group(function () {
    // 管理者ログイン画面（GET）
    Route::get('/login', [AdminLoginController::class, 'show'])->name('login');
    // 管理者ログイン送信（POST）
    Route::post('/login', [AdminLoginController::class, 'store']);
});
// 管理者ログアウト（POST）
Route::post('/admin/logout', [AdminLoginController::class, 'destroy'])
    ->middleware('auth') // 必要なら admin ガードに変更
    ->name('admin.logout');

/*
|--------------------------------------------------------------------------
| 管理者（認証・権限）
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'can:admin'])->group(function () {
    // 全社員の勤怠一覧（.list に統一）
    Route::get('/attendance', [AdminAttendanceController::class, 'index'])->name('attendance.list');

    // 勤怠詳細
    Route::get('/attendance/{attendanceDay}', [AdminAttendanceController::class, 'show'])->name('attendance.show');

    // スタッフ別勤怠一覧
    Route::get('/staff/{user}/attendance', [AdminAttendanceController::class, 'staffAttendance'])
        ->name('staff.attendance.list');

    // 修正申請 承認一覧
    Route::get('/stamp-corrections', [AdminStampCorrectionApprovalController::class, 'index'])
        ->name('corrections.list');

    // 修正申請 詳細
    Route::get('/stamp-corrections/{correctionRequest}', [AdminStampCorrectionApprovalController::class, 'show'])
        ->name('corrections.show');
});
