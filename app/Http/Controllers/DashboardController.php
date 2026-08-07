<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummaryService;
use Illuminate\Http\Request;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSummaryService $summaries): Response
    {
        return inertia('dashboard', [
            'summary' => $summaries->forUser($request->user()),
        ]);
    }
}
