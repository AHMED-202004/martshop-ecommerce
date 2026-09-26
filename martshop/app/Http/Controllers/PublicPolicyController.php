<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceSettings;
use Illuminate\View\View;

class PublicPolicyController extends Controller
{
    public function index(MarketplaceSettings $settings): View
    {
        return view('content.policies', [
            'deliveryText' => $settings->deliveryText(),
        ]);
    }
}
