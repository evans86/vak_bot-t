<?php

namespace App\Http\Controllers\Activate;

use App\Models\Order\SmsOrder;
use Carbon\Carbon;

class OrderController
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        $orders = SmsOrder::orderBy('id', 'DESC')->take(100)->Paginate(15);

        $allCount = count(SmsOrder::get());
        $successCount = count(SmsOrder::query()->where('status', SmsOrder::STATUS_FINISH)->get());
        $cancelCount = count(SmsOrder::query()->where('status', SmsOrder::STATUS_CANCEL)->get());

        $todayOrders = count(SmsOrder::whereDate('created_at', Carbon::today())->get());
        $todaySuccess = count(SmsOrder::query()->whereDate('created_at', Carbon::today())->
            where('status', SmsOrder::STATUS_FINISH)->get());
        $todayCancel = count(SmsOrder::query()->whereDate('created_at', Carbon::today())->
            where('status', SmsOrder::STATUS_CANCEL)->get());

        return view('activate.order.index', compact(
            'orders',
            'allCount',
            'successCount',
            'cancelCount',
            'todayOrders',
            'todaySuccess',
            'todayCancel',
        ));
    }
}
