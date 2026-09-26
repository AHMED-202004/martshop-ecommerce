<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, AdminDashboardService $dashboard)
    {
        return view('admin.dashboard', $dashboard->data($request->user()));
    }
}
