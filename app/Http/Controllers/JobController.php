<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Contracts\View\View;

class JobController extends Controller
{
    public function show(Job $job): View
    {
        $job->load(['shop', 'service', 'lines', 'proofs', 'payment']);

        return view('jobs.show', ['job' => $job]);
    }
}
