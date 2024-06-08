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

        $countries = $smsVak->getCountryList();

        $this->formingCountriesArr($countries);
    }

    /**
     * @param $countries
     * @return void
     */
    private function formingCountriesArr($countries)
    {
        foreach ($countries as $key => $country) {

//            $org_id = mb_strtolower($key);

            $data = [
                'org_id' => $country['countryCode'],
                'name_ru' => null,
                'name_en' => $country['countryName'],
                'image' => 'https://vak-sms.ru/static/country/' . $country['countryCode'] . '.png'
            ];

            $country = SmsCountry::updateOrCreate($data);
            $country->save();
        }
    }

    public function getCountries($bot)
    {
        $smsVak = new VakApi($bot->api_key);

//        $countries = \Cache::get('countries');
//        if ($countries === null) {
            $countries = $smsVak->getCountryList();
//            \Cache::put('countries', $countries, 900);
//        }
        $result = [];

        foreach ($countries as $key => $country) {
            array_push($result, [
                'org_id' => $country['countryCode'],
                'name_ru' => null,
                'name_en' => $country['countryName'],
                'image' => 'https://vak-sms.ru/static/country/' . $country['countryCode'] . '.png'
            ]);
        }

        return $result;
    }
}
