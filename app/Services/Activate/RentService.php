<?php

namespace App\Services\Activate;

use App\Models\Activate\SmsCountry;
use App\Services\External\SmsActivateApi;
use App\Services\MainService;

class RentService extends MainService
{
    /**
     * формируем список стран
     *
     * @param $bot
     * @return array
     */
    public function getRentCountries($bot)
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $resultRequest = $smsActivate->getRentServicesAndCountries();
        $countries = $resultRequest['countries'];

        $result = [];
        foreach ($countries as $country) {
            $smsCountry = SmsCountry::query()->where(['org_id' => $country])->first();

            array_push($result, [
                'id' => $smsCountry->org_id,
                'title_ru' => $smsCountry->name_ru,
                'title_eng' => $smsCountry->name_en,
                'image' => $smsCountry->image,
            ]);
        }

        return $result;
    }

    /**
     * формируем список сервисов
     *
     * @param $bot
     * @param $country
     * @return array
     */
    public function getRentService($bot, $country)
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $resultRequest = $smsActivate->getRentServicesAndCountries($country);
        $services = $resultRequest['services'];

        $result = [];
        foreach ($services as $key => $service) {
            array_push($result, [
                'name' => $key,
                'count' => $service['quant']['total'],
                'cost' => $service['retail_cost'],
                'image' => 'https://smsactivate.s3.eu-central-1.amazonaws.com/assets/ico/' . $key . '0.webp',
            ]);
        }

        return $result;
    }

    /**
     * создание заказа на аренду
     *
     * @param $bot
     * @param $service
     * @param $country
     * @param $time
     * @param $url
     * @return void
     */
    public function create($bot, $service, $country, $time, $url = '')
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $resultRequest = $smsActivate->getRentNumber($service, $country, $time, $url);

        dd($resultRequest);
    }

    //получение статуса аренды
    public function getStatus($bot)
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $resultRequest = $smsActivate->getRentStatus();
    }

    //изменение статсуса аренды
    public function setStatus($bot)
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $resultRequest = $smsActivate->setRentStatus();
    }

    //получение списка текущих активаций
    public function getRentList($bot)
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $resultRequest = $smsActivate->getRentList();
    }

    //цена продления аренды
    //разобраться для всех ли аренд работает?
    public function priceContinue($bot)
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $resultRequest = $smsActivate->getContinueRentPriceNumber();
    }

    //продление срока аренды
    public function continueRent($bot)
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $resultRequest = $smsActivate->continueRentNumber();
    }
}
