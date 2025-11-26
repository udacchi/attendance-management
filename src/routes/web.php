<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\StampCorrectionApprovalController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;

/*
|--------------------------------------------------------------------------
| ゲスト（一般ユーザー）
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => 'OK')->name('home');
Route::middleware('guest:web')->group(function () {
    // Fortifyのregisterを使っているなら不要
});

/*
|--------------------------------------------------------------------------
| Fortify メール認証フロー（要ログイン）
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function (Request $request) {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('attendance.stamp');
        }
        return view('auth.verify-email');
    })->name('verification.notice');

    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')->name('verification.send');

    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect()->route('attendance.stamp');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::get('/open-mailhog', function () {
        $url = config('services.mail_preview_url', 'http://localhost:8025');
        return redirect()->away($url);
    })->name('open.mailhog');
});

/*
|--------------------------------------------------------------------------
| 一般ユーザー（auth + verified）
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web', 'verified'])->group(function () {
    // 打刻
    Route::get('/attendance/stamp',  [AttendanceController::class, 'stamp'])->name('attendance.stamp');
    Route::post('/attendance/punch', [AttendanceController::class, 'punch'])->name('attendance.punch');
    Route::get('/attendance/punch',  fn() => redirect()->route('attendance.stamp'));

    // 勤怠一覧／詳細
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');

    // 詳細（?date=YYYY-MM-DD）
    Route::get('/attendance/detail', [AttendanceController::class, 'detail'])
        ->name('attendance.detail');

    // 修正申請（/attendance/{date}/request）
    Route::post('/attendance/{date}/request', [AttendanceController::class, 'requestCorrection'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendance.request');
});

/*
|--------------------------------------------------------------------------
| 管理者ログイン
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login',  [AdminLoginController::class, 'show'])->name('login')->middleware('guest:admin');
    Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store')->middleware('guest:admin');
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout')->middleware('auth:admin');
});

/*
|--------------------------------------------------------------------------
| 管理者（auth:admin）
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth:admin', 'can:admin'])->group(function () {
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('attendance.list');

    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'detailByUserDate'])
        ->whereNumber('id')->name('attendance.detail');

    Route::get('/staff/list', [AdminStaffController::class, 'index'])->name('staff.list');

    Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'staff'])
        ->whereNumber('id')->name('attendance.staff');

    Route::get('/attendance/staff/{id}/csv', [AdminAttendanceController::class, 'staffCsv'])
        ->whereNumber('id')->name('attendance.staff.csv');

    Route::get('/attendance/{user}/edit', [AdminAttendanceController::class, 'editByUserDate'])
        ->whereNumber('user')->name('attendance.edit');

    Route::post('/attendance/{user}/update', [AdminAttendanceController::class, 'updateByUserDate'])
        ->whereNumber('user')->name('attendance.updateByUserDate');
});

/*
|--------------------------------------------------------------------------
| 申請一覧（ユーザー/管理者 共通パス）
|--------------------------------------------------------------------------
*/
Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index'])
    ->name('stamp_correction_request.list')
    ->middleware('auth.any'); // web/adminどちらでもOKの自作ミドルウェア

/*
|--------------------------------------------------------------------------
| 修正申請 承認（URLは admin なし／権限だけadmin）
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:admin', 'can:admin'])->group(function () {
    Route::get(
        '/stamp_correction_request/approve/{attendance_correct_request_id}',
        [StampCorrectionApprovalController::class, 'show']
    )->whereNumber('attendance_correct_request_id')
        ->name('stamp_correction_request.approve');

    Route::post(
        '/stamp_correction_request/approve/{attendance_correct_request_id}',
        [StampCorrectionApprovalController::class, 'approve']
    )->whereNumber('attendance_correct_request_id')
        ->name('stamp_correction_request.approve.store');
});

/*
|--------------------------------------------------------------------------
| 作業用：強制ログアウト（必要なら）
|--------------------------------------------------------------------------
*/
Route::get('/dev-logout', function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
});
