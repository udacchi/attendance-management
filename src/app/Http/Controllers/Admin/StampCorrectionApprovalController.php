<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CorrectionRequest;

class StampCorrectionApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'can:admin']);
    }

    /**
     * 修正申請 承認一覧
     * GET /admin/stamp-corrections  name: admin.corrections.index
     */
    public function index(Request $request)
    {
        $status = $request->query('status'); // pending/approved/rejected

        $requests = CorrectionRequest::query()
            ->when($status, fn($q) => $q->where('status', $status))
            ->with('user:id,name')
            ->latest('id')
            ->paginate(20);

        return view('admin.stamp_corrections.index', compact('requests', 'status'));
    }

    /**
     * 修正申請 詳細
     * GET /admin/stamp-corrections/{correctionRequest}  name: admin.corrections.show
     */
    public function show(CorrectionRequest $correctionRequest)
    {
        return view('admin.stamp_corrections.show', [
            'request' => $correctionRequest,
        ]);
    }
}
