<?php

namespace App\Http\Controllers\Activate;

use App\Http\Controllers\Controller;

class RentController extends Controller
{
    public function index()
    {
        return view('activate.rent.index');
    }
}
