<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireLogin;
use Illuminate\View\View;

/**
 * The install guide that goes with the landing page. It also shows on an instance that asks for a
 * login nobody has created yet, since that is all there is to see there.
 */
class LandingInstallController extends Controller
{
    public function __invoke(): View
    {
        $awaitsLogin = RequireLogin::awaitsLogin();

        abort_unless(config('houston.landing') || $awaitsLogin, 404);

        return view('landing.install', ['awaitsLogin' => $awaitsLogin]);
    }
}
