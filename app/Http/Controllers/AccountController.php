<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $jobs = $request->user()
            ->jobs()
            ->with(['shop', 'service', 'proofs'])
            ->get();

        return view('account', [
            'jobs' => $jobs,
            'counts' => [
                'active' => $jobs->whereIn('status', ['quoted', 'awaiting_proof', 'approved', 'in_production'])->count(),
                'ready' => $jobs->where('status', 'ready')->count(),
                'done' => $jobs->whereIn('status', ['collected', 'cancelled'])->count(),
            ],
            'needsProof' => $jobs->first(fn ($job) => $job->status === 'awaiting_proof'),
        ]);
    }
}
