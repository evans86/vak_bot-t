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
}
