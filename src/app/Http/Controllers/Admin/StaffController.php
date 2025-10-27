<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;

class StaffController extends Controller
{
    public function index()
    {
        $staffs = User::select(['id', 'name', 'email'])->orderBy('id')->paginate(20);
        $month  = Carbon::now()->startOfMonth();
        return view('admin.staff.list', compact('staffs', 'month'));
    }
}
