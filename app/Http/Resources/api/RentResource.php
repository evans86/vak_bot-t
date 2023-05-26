<?php

namespace App\Http\Resources\api;

use App\Models\Rent\RentOrder;
use Illuminate\Http\Resources\Json\JsonResource;

class RentResource extends JsonResource
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
            'start_time' => (integer)$this->start_time,
            'end_time' => (integer)$this->end_time,
            'status' => (integer)$this->status,
            'codes' => json_decode($this->codes),
            'country' => $this->country->org_id,
            'service' => $this->service,
            'cost' => $this->price_final / 100
        ];
    }

    /**
     * @param RentOrder|null $rent_order
     * @return array
     */
    public static function generateRentArray(RentOrder $rent_order): array
    {
        return [
            'id' => (integer)$rent_order->org_id,
            'phone' => $rent_order->phone,
            'start_time' => (integer)$rent_order->start_time,
            'end_time' => (integer)$rent_order->end_time,
            'status' => (integer)$rent_order->status,
            'codes' => $rent_order->codes,
            'country' => $rent_order->country->org_id,
            'operator' => $rent_order->operator,
            'service' => $rent_order->service,
            'cost' => $rent_order->price_final
        ];
    }
}
