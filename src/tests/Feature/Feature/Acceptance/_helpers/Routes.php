<?php

declare(strict_types=1);

namespace Tests\Feature\Acceptance\_helpers;

/**
 * 実装に合わせた URL / 文言 を一元管理。
 * もし画面のラベルやルートが違っていたら “ここだけ” 書き換えてください。
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
    // 一覧: GET /attendance/list?from=YYYY-MM-DD&to=YYYY-MM-DD もしくは ?month=YYYY-MM
    protected string $ROUTE_USER_ATT_LIST        = '/attendance/list';
    // 詳細: GET /attendance/detail?date=YYYY-MM-DD
    // 保存: POST /attendance/detail
    protected string $ROUTE_USER_ATT_DETAIL      = '/attendance/detail';

    // ユーザーの修正申請一覧（承認待ち/承認済みタブ）
    protected string $ROUTE_USER_REQ_LIST        = '/stamp_correction_request/list';

    // ====== 管理者 側 ======
    protected string $ROUTE_ADMIN_LOGIN          = '/admin/login';
    // 日別勤怠一覧: GET /admin/attendance/list?date=YYYY-MM-DD
    protected string $ROUTE_ADMIN_ATT_LIST       = '/admin/attendance/list';
    // 勤怠詳細: GET /admin/attendance/detail?id={userId}&date=YYYY-MM-DD
    // 保存: POST /admin/attendance/detail
    protected string $ROUTE_ADMIN_ATT_DETAIL     = '/admin/attendance/detail';

    // スタッフ一覧/ユーザーの月次勤怠
    protected string $ROUTE_ADMIN_STAFF_LIST     = '/admin/staff/list';
    protected string $ROUTE_ADMIN_STAFF_ATT_LIST = '/admin/staff/attendance'; // 例: ?id={userId}&month=YYYY-MM

    // 修正申請（管理者）
    // 一覧: GET /admin/stamp_correction_request/list?status=pending|approved
    // 詳細: GET /admin/stamp_correction_request/detail?id={requestId}
    // 承認: POST /admin/stamp_correction_request/approve
    protected string $ROUTE_ADMIN_REQ_LIST       = '/admin/stamp_correction_request/list';
    protected string $ROUTE_ADMIN_REQ_DETAIL     = '/admin/stamp_correction_request/detail';
    protected string $ROUTE_ADMIN_REQ_APPROVE    = '/admin/stamp_correction_request/approve';

    // ====== 画面の日本語ラベル（Bladeの表示と一致させる） ======
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

    // バリデーション文言（lang/ja/messages.php 等に合わせてください）
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
