<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Bot\SmsBot;
use App\Services\Activate\RentService;
use Illuminate\Http\Request;

class RentController extends Controller
{
    /**
     * @var RentService
     */
    private RentService $rentService;

    public function __construct()
    {
        $this->rentService = new RentService();
    }

    /**
     * формирование страны доступные для аренды
     *
     * @param Request $request
     * @return array|string
     */
    public function getRentCountries(Request $request)
    {
        if (is_null($request->public_key))
            return ApiHelpers::error('Not found params: public_key');
        $bot = SmsBot::query()->where('public_key', $request->public_key)->first();
        if (empty($bot))
            return ApiHelpers::error('Not found module.');

        $countries = $this->rentService->getRentCountries($bot);

        return ApiHelpers::success($countries);
    }

    /**
     * формирование сервисов доступных для аренды
     *
     * @param Request $request
     * @return array|string
     */
    public function getRentServices(Request $request)
    {
        if (is_null($request->public_key))
            return ApiHelpers::error('Not found params: public_key');
        if (is_null($request->country))
            return ApiHelpers::error('Not found params: country');
        $bot = SmsBot::query()->where('public_key', $request->public_key)->first();
        if (empty($bot))
            return ApiHelpers::error('Not found module.');

        $services = $this->rentService->getRentService($bot, $request->country);

        return ApiHelpers::success($services);
    }

    /**
     * создание заказа на аренду
     *
     * @param Request $request
     * @return string|void
     */
    public function createRentOrder(Request $request)
    {
        if (is_null($request->public_key))
            return ApiHelpers::error('Not found params: public_key');
        $bot = SmsBot::query()->where('public_key', $request->public_key)->first();
        if (empty($bot))
            return ApiHelpers::error('Not found module.');

        $rentOrder = $this->rentService->create($bot);
    }

}
