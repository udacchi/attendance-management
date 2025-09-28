<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CorrectionRequest;

class StampCorrectionRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * 申請一覧（一般ユーザー=自分の申請のみ / 管理者=全件）
     * GET /stamp-correction-requests  name: stamp_correction_request.index
     * view: resources/views/stamp_correction_request/list.blade.php
     */
    public function index(Request $request)
    {
        $isAdmin = (bool)(Auth::user()->is_admin ?? false);

        $requests = CorrectionRequest::query()
            ->when(!$isAdmin, fn($q) => $q->where('user_id', Auth::id()))
            ->latest('id')
            ->paginate(10);

        return view('stamp_correction_request.list', compact('requests', 'isAdmin'));
    }
}
