<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;

class InventoryController extends Controller
{
    public function index()
    {
        return view('inventory.index');
    }

    public function movements()
    {
        return view('inventory.movements');
    }

    public function borrowReturn()
    {
        return view('inventory.borrow-return');
    }

    public function disbursement()
    {
        return view('inventory.disbursement');
    }

    public function intradayReturn()
    {
        return view('inventory.intraday-return');
    }

    public function openCloseDay()
    {
        return view('inventory.open-close-day');
    }

    public function booking()
    {
        return view('inventory.booking');
    }

    public function adjustment()
    {
        return view('inventory.adjustment');
    }
}
