<?php

// routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\StampCorrectionApprovalController;

// 一般ユーザー
Route::middleware(['auth', 'verified'])->group(function () {
Route::get('/attendance/stamp', [AttendanceController::class, 'stamp'])->name('attendance.stamp');
Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');

// ← モデルに合わせて {attendanceDay} に変更（Route Model Binding）
Route::get('/attendance/{attendanceDay}', [AttendanceController::class, 'show'])->name('attendance.show');

Route::get('/stamp-correction-requests', [StampCorrectionRequestController::class, 'index'])
->name('stamp_correction_request.index');
});

// 管理者
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'can:admin'])->group(function () {
Route::get('/attendance', [AdminAttendanceController::class, 'index'])->name('attendance.index');

// ← バインディング名を {attendanceDay} に統一
Route::get('/attendance/{attendanceDay}', [AdminAttendanceController::class, 'show'])->name('attendance.show');

Route::get('/staff/{user}/attendance', [AdminAttendanceController::class, 'staffAttendance'])
->name('staff.attendance.index');

Route::get('/stamp-corrections', [StampCorrectionApprovalController::class, 'index'])->name('corrections.index');

// ← {correctionRequest} に合わせる
Route::get('/stamp-corrections/{correctionRequest}', [StampCorrectionApprovalController::class, 'show'])
->name('corrections.show');
});