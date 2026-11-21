<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class JobController extends Controller
{
    public function show(Job $job): View
    {
        // A job carries the customer's name, phone and payment, and the reference
        // is `TL-` plus five digits — guessable. 404 rather than 403 so the
        // response does not confirm that the job exists at all.
        abort_unless(Gate::allows('view', $job), 404);

        $job->load(['shop', 'service', 'lines', 'proofs', 'payment']);

        return view('jobs.show', ['job' => $job]);
    }

    /** The receipt a customer can print or keep — the same view the shop sees. */
    public function receipt(Job $job): View
    {
        abort_unless(Gate::allows('view', $job), 404);

        $job->load(['shop', 'service', 'lines', 'payment', 'user']);

        return view('jobs.receipt', ['job' => $job]);
    }
}
