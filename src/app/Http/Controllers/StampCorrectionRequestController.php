<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;   // ★ 追加
use App\Models\CorrectionRequest;

class StampCorrectionRequestController extends Controller  // ★ クラス名修正
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * 申請一覧（一般ユーザー=自分の申請のみ / 管理者=全件）
     */
    public function index(Request $request)
    {
        $isAdmin = Gate::allows('admin');  // ★ ここで判定（isAdmin() を呼ばない）

        $requests = CorrectionRequest::query()
            ->when(!$isAdmin, fn($q) => $q->where('requested_by', Auth::id())) // ★ requested_by を使用
            ->latest('id')
            ->paginate(10);

        return view('stamp_correction_request.list', compact('requests', 'isAdmin'));
    }
}
