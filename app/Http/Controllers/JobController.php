<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class JobController extends Controller
{
    public function show(Job $job): View
    {
        // A job page carries the customer's name, notes, priced lines and payment
        // state, and the reference is a short guessable string. Anything the
        // viewer is not entitled to see is reported as missing, not forbidden —
        // a 403 would confirm the job exists.
        abort_unless(Gate::allows('view', $job), 404);

        $job->load(['shop', 'service', 'lines', 'proofs', 'payment']);

        return view('jobs.show', ['job' => $job]);
    }
}
