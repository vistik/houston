<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The Houston website at /, on an instance that turns the landing page on. Everyone sees it,
 * logged in or not, and the dashboard stays at its own address. Without it, / goes to the dashboard.
 */
class LandingController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (! config('houston.landing')) {
            return to_route('home');
        }

        return view('landing.home', [
            // The metric lists link to a real report: the first repository, as the dashboard orders them.
            'firstRepository' => Repository::query()->orderBy('owner')->orderBy('name')->first(['owner', 'name']),
        ]);
    }
}
