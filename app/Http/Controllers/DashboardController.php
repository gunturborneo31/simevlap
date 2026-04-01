<?php

namespace App\Http\Controllers;

use App\Models\Opd;
use App\Models\Program;
use App\Models\Realisasi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $stats = [
            'total_opd' => Opd::where('is_active', true)->count(),
            'total_program' => Program::count(),
            'total_realisasi' => Realisasi::count(),
        ];
        return Inertia::render('Dashboard', ['stats' => $stats, 'user' => $user->load('opd')]);
    }
}
