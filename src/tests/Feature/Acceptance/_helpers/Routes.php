<?php
declare(strict_types=1);

namespace Tests\Feature\Acceptance\_helpers;

/**
 * 実装に合わせた URL / 画面ラベルを一元管理するトレイト。
 * ルートやBlade文言に差異があれば、ここだけ直せばテスト全体に反映されます。
 */
trait Routes
{
    // ====== 一般ユーザー 側 ======
    protected string $ROUTE_HOME                 = '/';
    protected string $ROUTE_REGISTER             = '/register';
    protected string $ROUTE_LOGIN                = '/login';

    protected string $ROUTE_STAMP                = '/attendance/stamp';
    protected string $ROUTE_CLOCK_IN             = '/attendance/clock-in';
    protected string $ROUTE_BREAK_IN             = '/attendance/break-in';
    protected string $ROUTE_BREAK_OUT            = '/attendance/break-out';
    protected string $ROUTE_CLOCK_OUT            = '/attendance/clock-out';

    // ユーザー勤怠一覧/詳細
    protected string $ROUTE_USER_ATT_LIST        = '/attendance/list';
    protected string $ROUTE_USER_ATT_DETAIL      = '/attendance/detail';

    // ユーザー修正申請一覧
    protected string $ROUTE_USER_REQ_LIST        = '/stamp_correction_request/list';

    // ====== 管理者 側 ======
    protected string $ROUTE_ADMIN_LOGIN          = '/admin/login';
    protected string $ROUTE_ADMIN_ATT_LIST       = '/admin/attendance/list';
    protected string $ROUTE_ADMIN_ATT_DETAIL     = '/admin/attendance/detail';

    protected string $ROUTE_ADMIN_STAFF_LIST     = '/admin/staff/list';
    protected string $ROUTE_ADMIN_STAFF_ATT_LIST = '/admin/staff/attendance';

    protected string $ROUTE_ADMIN_REQ_LIST       = '/admin/stamp_correction_request/list';
    protected string $ROUTE_ADMIN_REQ_DETAIL     = '/admin/stamp_correction_request/detail';
    protected string $ROUTE_ADMIN_REQ_APPROVE    = '/admin/stamp_correction_request/approve';

    // ====== 画面ラベル ======
    protected string $LBL_STAMP_TITLE            = '勤怠打刻';
    protected string $LBL_ATT_LIST_TITLE         = '勤怠一覧';
    protected string $LBL_ATT_DETAIL_TITLE       = '勤怠詳細';

    protected string $LBL_BTN_CLOCK_IN           = '出勤';
    protected string $LBL_BTN_BREAK_IN           = '休憩入';
    protected string $LBL_BTN_BREAK_OUT          = '休憩戻';
    protected string $LBL_BTN_CLOCK_OUT          = '退勤';
    protected string $LBL_LINK_DETAIL            = '詳細';

    protected string $LBL_STATUS_BEFORE          = '勤務外';
    protected string $LBL_STATUS_WORKING         = '出勤中';
    protected string $LBL_STATUS_BREAK           = '休憩中';
    protected string $LBL_STATUS_AFTER           = '退勤済';

    protected string $LBL_TAB_PENDING            = '承認待ち';
    protected string $LBL_TAB_APPROVED           = '承認済み';

    // ====== バリデーション/エラーメッセージ ======
    protected string $MSG_NAME_REQUIRED          = 'お名前を入力してください';
    protected string $MSG_EMAIL_REQUIRED         = 'メールアドレスを入力してください';
    protected string $MSG_PASSWORD_MIN           = 'パスワードは8文字以上で入力してください';
    protected string $MSG_PASSWORD_CONFIRM       = 'パスワードと一致しません';
    protected string $MSG_PASSWORD_REQUIRED      = 'パスワードを入力してください';
    protected string $MSG_LOGIN_NOT_FOUND        = 'ログイン情報が登録されていません';

    protected string $MSG_INVALID_CLOCK_IN       = '出勤時間が不適切な値です';
    protected string $MSG_INVALID_BREAK_TIME     = '休憩時間が不適切な値です';
    protected string $MSG_INVALID_BREAK_OR_OUT   = '休憩時間もしくは退勤時間が不適切な値です';
    protected string $MSG_NOTE_REQUIRED          = '備考を記入してください';
}
