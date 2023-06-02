<?php

namespace App\Http\Controllers\Activate;

use App\Http\Controllers\Controller;
use App\Models\Rent\RentOrder;

class RentController extends Controller
{
    public function index()
    {
        $rent_orders = RentOrder::orderBy('id', 'DESC')->Paginate(15);

        $allCount = count(RentOrder::get());
        $successCount = count(RentOrder::query()->where('status', RentOrder::STATUS_FINISH)->get());
        $cancelCount = count(RentOrder::query()->where('status', RentOrder::STATUS_CANCEL)->get());

        return view('activate.rent.index', compact(
            'rent_orders',
            'allCount',
            'successCount',
            'cancelCount',
        ));
    }
}
