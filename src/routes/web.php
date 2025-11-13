<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\StampCorrectionApprovalController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Auth\RegisteredUserController;

/*
|--------------------------------------------------------------------------
| ゲスト（一般ユーザー）
|--------------------------------------------------------------------------
*/

// 動作チェック用トップ（必要なければ削除可）
Route::get('/', fn() => 'OK')->name('home');

// ★ 一般ユーザーの会員登録（表示＆送信）
Route::middleware('guest:web')->group(function () {
    // GET /register（リンク先）※ルート名は register に統一
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    // POST /register（登録実行）
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

/*
|--------------------------------------------------------------------------
| Fortify メール認証フロー（要ログイン）
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    // 認証案内（登録直後の画面）
    Route::get('/email/verify', function (Request $request) {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('attendance.stamp');
        }
        return view('auth.verify-email');
    })->name('verification.notice');

    // 認証メール再送
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')->name('verification.send');

    // メール内リンク検証 → 勤怠打刻へ
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect()->route('attendance.stamp');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
});

/*
|--------------------------------------------------------------------------
| 一般ユーザー（auth + verified）
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:web', 'verified'])->group(function () {
    // 打刻画面
    Route::get('/attendance/stamp', [AttendanceController::class, 'stamp'])->name('attendance.stamp');

    // 打刻実行（POST 集約）
    Route::post('/attendance/punch', [AttendanceController::class, 'punch'])->name('attendance.punch');

    // 保険：誤って GET で叩かれても画面に戻す
    Route::get('/attendance/punch', function () {
        return redirect()->route('attendance.stamp');
    });

    // 勤怠一覧
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');

    // 勤怠詳細（YYYY-MM-DD）
    Route::get('/attendance/{date}', [AttendanceController::class, 'detail'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendance.detail');

    Route::post('/attendance/{date}/request', [StampCorrectionRequestController::class, 'store'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('attendance.request');

});

/*
|--------------------------------------------------------------------------
| 管理者ログイン（一般ユーザーとは分離）
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    // 管理者ログイン画面（GET）
    Route::get('/login', [AdminLoginController::class, 'show'])->name('login')->middleware('guest:admin');

    // 管理者ログイン送信（POST）
    Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store')->middleware('guest:admin');

    // ★ ログアウト（POST）
    Route::post('logout', [AdminLoginController::class, 'destroy'])->name('logout')->middleware('auth:admin');
});

/*
|--------------------------------------------------------------------------
| 管理者（auth:admin）
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth:admin', 'can:admin'])->group(function () {
    // 勤怠一覧
    Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('attendance.list');

    // 勤怠詳細
    Route::get('/attendance/{id}', [AdminAttendanceController::class, 'detailByUserDate'])->whereNumber('id')
        ->name('attendance.detail');

    // スタッフ一覧
    Route::get('/staff/list', [AdminStaffController::class, 'index'])->name('staff.list');

    // スタッフ別勤怠一覧
    Route::get('/attendance/staff/{id}', [AdminAttendanceController::class, 'staff'])
        ->whereNumber('id')->name('attendance.staff');

    // ★ CSV出力
    Route::get('/attendance/staff/{id}/csv', [AdminAttendanceController::class, 'staffCsv'])
        ->whereNumber('id')->name('attendance.staff.csv');

    // 修正編集（管理者）
    Route::get('/attendance/{user}/edit', [AdminAttendanceController::class, 'editByUserDate'])
        ->name('attendance.edit');

    // ユーザーに反映
    Route::put('/attendance/{user}', [AdminAttendanceController::class, 'updateByUserDate'])
        ->name('attendance.update');

});

/*
|--------------------------------------------------------------------------
| 申請一覧
|--------------------------------------------------------------------------
*/
Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index'])
    ->name('stamp_correction_request.list')
    ->middleware('auth.any');

// 承認画面／承認実行をまとめて保護
Route::prefix('admin')->middleware(['auth:admin', 'can:admin'])->group(function () {
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}',
        [StampCorrectionApprovalController::class, 'show']
    )->whereNumber('attendance_correct_request_id')
     ->name('stamp_correction_request.approve');

    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}',
        [StampCorrectionApprovalController::class, 'approve']
    )->whereNumber('attendance_correct_request_id')
     ->name('stamp_correction_request.approve.store');
});

/*
|--------------------------------------------------------------------------
| 作業用：強制ログアウト（確認後は削除OK）
|--------------------------------------------------------------------------
*/
Route::get('/dev-logout', function () {
    Auth::guard('web')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
});



