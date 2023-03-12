<?php

namespace App\Services\Activate;

use App\Helpers\ApiHelpers;
use App\Models\Order\SmsOrder;
use App\Services\External\SmsActivateApi;
use App\Services\MainService;

class OrderService extends MainService
{
    public function createOrder($service, $operator, $country, $user_id)
    {
        try {
            $smsActivate = new SmsActivateApi(config('services.key_activate.key'));

            $serviceResult = $smsActivate->getNumberV2($service, $country);
//            $activeActivation = $smsActivate->getStatus($serviceResult['activationId']);

//            dd($serviceResult);

//            $serviceResult = json_decode($serviceResult);
//            return $serviceResult;

            $dateTime = new \DateTime($serviceResult['activationTime']);
            $dateTime = $dateTime->format('U');
            $dateTime = intval($dateTime);
            $endTime = $dateTime + 1200;

            $id = intval($serviceResult['activationId']);

            $result = [
                'id' => $id,
                'phone' => $serviceResult['phoneNumber'],
                'text' => '',
                'time' => $endTime, //посмотреть время для сервисов?
                'status' => $this->getStatus($id),
            ];

            $data = [
                'org_id' => $id,
                'user_id' => $user_id,
                'phone' => $serviceResult['phoneNumber'],
                'country' => $country,
                'operator' => $serviceResult['activationOperator'],
                'status' => $this->getStatus($id)
            ];

            $order = SmsOrder::create($data);
            $order->save();

            return $result;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getActive()
    {
        $smsActivate = new SmsActivateApi(config('services.key_activate.key'));

        $serviceResult = $smsActivate->getActiveActivations();

        return $serviceResult;
    }

    public function getStatus($id)
    {
        $smsActivate = new SmsActivateApi(config('services.key_activate.key'));

        $serviceResult = $smsActivate->getStatus($id);

        return $serviceResult;
    }
}