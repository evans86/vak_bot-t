<?php

namespace App\Services\Activate;

use App\Helpers\ApiHelpers;
use App\Models\Activate\SmsCountry;
use App\Models\User\SmsUser;
use App\Services\MainService;
use App\Services\External\SmsActivateApi;
use RuntimeException;

class UserService extends MainService
{
    /**
     * Баланс с сервиса
     *
     * @return mixed
     */
    public function balance($bot)
    {
        try {
            $smsActivate = new SmsActivateApi($bot->api_key, $bot->resource_link);
            $balance = $smsActivate->getBalance();
        } catch (\Exception $e) {
            $balance = '';
        }

        return $balance;
    }

    public function getOrCreate(int $telegram_id): SmsUser
    {
        $user = SmsUser::query()->where(['telegram_id' => $telegram_id])->first();
        if (is_null($user)) {
            $user = new SmsUser();
            $user->telegram_id = $telegram_id;
            $user->language = SmsUser::LANGUAGE_RU;
            if(!$user->save())
                throw new RuntimeException('user not created');
        }
        return $user;
    }

    public function updateLanguage(int $telegram_id, string $language): SmsUser
    {
        $user = SmsUser::query()->where(['telegram_id' => $telegram_id])->first();
        if (is_null($user)) {
            throw new RuntimeException('user not found');
        }

        if ($language != SmsUser::LANGUAGE_RU && $language != SmsUser::LANGUAGE_ENG)
            throw new RuntimeException('language not valid');
        $user->language = $language;

        if (!$user->save())
            throw new RuntimeException('user not save language');
        return $user;
    }

    public function updateService(int $telegram_id, string $service): SmsUser
    {
        $user = SmsUser::query()->where(['telegram_id' => $telegram_id])->first();
        if (is_null($user)) {
            throw new RuntimeException('user not found');
        }
        $user->service = $service;

        if (!$user->save())
            throw new RuntimeException('user not save service');
        return $user;
    }

}
