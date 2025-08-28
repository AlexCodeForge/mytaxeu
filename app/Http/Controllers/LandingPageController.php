<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class LandingPageController extends Controller
{
    /**
     * Display the Spanish marketing landing page.
     */
    public function index(): View
    {
        return view('landing');
    }
}





