<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class JobController extends Controller
{
    public function show(Job $job): View
    {
        // A job carries the customer's name, phone and payment. The reference is
        // short and guessable, so possession of the URL is not proof of anything.
        Gate::authorize('view', $job);

        $job->load(['shop', 'service', 'lines', 'proofs', 'payment']);

        return view('jobs.show', ['job' => $job]);
    }
}
