<?php

namespace App\Http\Controllers\Trader;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TraderInventoryController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isTrader(), 403, 'Access denied. Trader role required.');

        return view('trader.inventory-dashboard');
    }
}
