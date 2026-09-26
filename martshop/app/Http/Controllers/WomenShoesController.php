<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class WomenShoesController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect('/c/shoes/women', 301);
    }
}
