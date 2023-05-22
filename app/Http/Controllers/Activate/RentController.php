<?php

namespace App\Http\Controllers\Activate;

use App\Http\Controllers\Controller;
use App\Models\Rent\RentOrder;

class RentController extends Controller
{
    public function index()
    {
        $rent_orders = RentOrder::orderBy('id', 'DESC')->Paginate(15);

        return view('activate.rent.index', compact(
            'rent_orders',
        ));
    }
}
