<?php

namespace App\Http\Resources\api;

use App\Models\Order\SmsOrder;
use App\Models\Rent\RentOrder;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => (integer)$this->org_id,
            'phone' => $this->phone,
            'time' => (integer)$this->start_time,
            'status' => (integer)$this->status,
            'codes' => json_decode($this->codes),
            'country' => $this->country->org_id,
            'service' => $this->service,
            'cost' => $this->price_final / 100
        ];
    }

    /**
     * @param SmsOrder $order
     * @return array
     */
    public static function generateOrderArray(SmsOrder $order): array
    {
        return [
            'id' => (integer)$order->org_id,
            'phone' => $order->phone,
            'time' => $order->start_time,
            'status' => (integer)$order->status,
            'codes' => $order->codes,
            'country' => $order->country->org_id,
            'operator' => $order->operator,
            'service' => $order->service,
            'cost' => $order->price_final
        ];
    }

    /**
     * @param RentOrder $rent_order
     * @return array
     */
    public static function generateRentArray(RentOrder $rent_order): array
    {
        return [
            'id' => (integer)$rent_order->org_id,
            'phone' => $rent_order->phone,
            'time' => $rent_order->start_time,
            'status' => (integer)$rent_order->status,
            'codes' => $rent_order->codes,
            'country' => $rent_order->country->org_id,
            'operator' => $rent_order->operator,
            'service' => $rent_order->service,
            'cost' => $rent_order->price_final
        ];
    }
}
