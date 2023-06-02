<?php

namespace App\Http\Controllers\Activate;

use App\Models\Order\SmsOrder;

class OrderController
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        $orders = SmsOrder::orderBy('id', 'DESC')->Paginate(15);

        $allCount = count(SmsOrder::get());
        $successCount = count(SmsOrder::query()->where('status', SmsOrder::STATUS_FINISH)->get());
        $cancelCount = count(SmsOrder::query()->where('status', SmsOrder::STATUS_CANCEL)->get());

        return view('activate.order.index', compact(
            'orders',
            'allCount',
            'successCount',
            'cancelCount',
        ));
    }
}
