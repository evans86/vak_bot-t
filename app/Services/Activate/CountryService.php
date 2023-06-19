<?php

namespace App\Services\Activate;

use App\Models\Activate\SmsCountry;
use App\Services\External\SmsActivateApi;
use App\Services\External\VakApi;
use App\Services\MainService;

class CountryService extends MainService
{
    /**
     * Получение, добавление стран и их операторов из API сервиса
     * @return void
     */
    public function getApiCountries()
    {
        //оставить свой API
        $smsVak = new VakApi(config('services.key_activate.key'), BotService::DEFAULT_HOST);

        $countries = $smsVak->getCountryOperatorList();

        $this->formingCountriesArr($countries);
    }

    /**
     * @param $countries
     * @return void
     */
    private function formingCountriesArr($countries)
    {
        foreach ($countries as $key => $country) {

            $org_id = mb_strtolower($key);

            $data = [
                'org_id' => $org_id,
                'name_ru' => null,
                'name_en' => $country[0]['name'],
                'image' => 'https://vak-sms.com' . $country[0]['icon']
            ];

            $country = SmsCountry::updateOrCreate($data);
            $country->save();
        }
    }

    public function getCountries($bot)
    {
        $smsVak = new VakApi($bot->api_key, $bot->resource_link);

        $countries = $smsVak->getCountryOperatorList();

        $result = [];

        foreach ($countries as $key => $country) {

            $org_id = mb_strtolower($key);

            array_push($result, [
                'org_id' => $org_id,
                'name_ru' => null,
                'name_en' => $country[0]['name'],
                'image' => 'https://vak-sms.com' . $country[0]['icon']
            ]);
        }

        return $result;
    }

    /**
     * Список стран по сервису
     *
     * @param $bot
     * @param $service
     * @return array
     */
    public function getPricesService($bot, $service = null)
    {
        $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);

        $countries = $smsActivate->getPrices(null, $service);

        return $this->formingServicesArr($countries, $bot);
    }

    /**
     * Формирование списка стран с ценой для выбранного сервиса
     *
     * @param $countries
     * @param $bot
     * @return array
     */
    private function formingServicesArr($countries, $bot)
    {
        $result = [];
        foreach ($countries as $key => $country) {

            $smsCountry = SmsCountry::query()->where(['org_id' => $key])->first();

            $country = current($country);
            $price = $country["cost"];

            $pricePercent = $price + ($price * ($bot->percent / 100));

            array_push($result, [
                'id' => $smsCountry->org_id,
                'title_ru' => $smsCountry->name_ru,
                'title_eng' => $smsCountry->name_en,
                'image' => $smsCountry->image,
                'count' => $country["count"],
                'cost' => $pricePercent * 100,
            ]);
        }

        return $result;
    }
}
