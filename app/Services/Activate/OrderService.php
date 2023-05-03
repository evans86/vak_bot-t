<?php

namespace App\Services\Activate;

use App\Dto\BotDto;
use App\Dto\BotFactory;
use App\Models\Activate\SmsCountry;
use App\Models\Bot\SmsBot;
use App\Models\Order\SmsOrder;
use App\Models\User\SmsUser;
use App\Services\External\BottApi;
use App\Services\External\SmsActivateApi;
use App\Services\MainService;
use GuzzleHttp\Client;
use RuntimeException;

class OrderService extends MainService
{
    /**
     * @param array $userData Сущность DTO from bott
     * @param BotDto $botDto
     * @param string $country_id
     * @return array
     * @throws \Exception
     */
    public function create(array $userData, BotDto $botDto, string $country_id): array
    {
        // Создать заказ по апи
        $smsActivate = new SmsActivateApi($botDto->api_key);
        $user = SmsUser::query()->where(['telegram_id' => $userData['user']['telegram_id']])->first();
        if (is_null($user)) {
            throw new RuntimeException('not found user');
        }
        if (empty($user->service))
            throw new RuntimeException('Choose service pls');

        $serviceResult = $smsActivate->getNumberV2(
            $user->service,
            $country_id
        );
        $org_id = intval($serviceResult['activationId']);
        // Из него получить цену
        $amountStart = intval(floatval($serviceResult['activationCost']) * 100);
        $amountFinal = $amountStart + $amountStart * $botDto->percent / 100;
        if ($amountFinal > $userData['money']) {
            $serviceResult = $smsActivate->setStatus($org_id, SmsOrder::ACCESS_CANCEL);
            throw new RuntimeException('Пополните баланс в боте');
        }
        // Попытаться списать баланс у пользователя
        $result = BottApi::subtractBalance($botDto, $userData, $amountFinal, 'Списание баланса для номера '
            . $serviceResult['phoneNumber']);

        // Неудача отмена на сервисе
        if (!$result['result']) {
            $serviceResult = $smsActivate->setStatus($org_id, SmsOrder::ACCESS_CANCEL);
            throw new RuntimeException('При списании баланса произошла ошибка: ' . $result['message']);
        }


        // Удача создание заказа в бд
        $country = SmsCountry::query()->where(['org_id' => $country_id])->first();
        $dateTime = new \DateTime($serviceResult['activationTime']);
        $dateTime = $dateTime->format('U');
        $dateTime = intval($dateTime);
        $data = [
            'bot_id' => $botDto->id,
            'user_id' => $user->id,
            'service' => $user->service,
            'country_id' => $country->id,
            'org_id' => $org_id,
            'phone' => $serviceResult['phoneNumber'],
            'codes' => null,
            'status' => SmsOrder::STATUS_WAIT_CODE, //4
            'start_time' => $dateTime,
            'end_time' => $dateTime + 1177,
            'operator' => $serviceResult['activationOperator'],
            'price_final' => $amountFinal,
            'price_start' => $amountStart,
        ];

        $order = SmsOrder::create($data);
        $result = $smsActivate->setStatus($order, SmsOrder::ACCESS_RETRY_GET);
        $result = $this->getStatus($order->org_id, $botDto->api_key);

        $result = [
            'id' => $order->org_id,
            'phone' => $serviceResult['phoneNumber'],
            'time' => $dateTime,
            'status' => $order->status,
            'codes' => null,
            'country' => $country->org_id,
            'operator' => $serviceResult['activationOperator'],
            'service' => $user->service,
            'cost' => $amountFinal
        ];
        return $result;
    }

    /**
     * @param array $userData
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return mixed
     */
    public function cancel(array $userData, BotDto $botDto, SmsOrder $order)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key);
        // Проверить уже отменёный
        if ($order->status == SmsOrder::STATUS_CANCEL)
            throw new RuntimeException('The order has already been canceled');
        if ($order->status == SmsOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');
        // Можно отменить только статус 4 и кодов нет
        if (!is_null($order->codes))
            throw new RuntimeException('The order has not been canceled, the number has been activated');

        // Обновить статус setStatus()
        $result = $smsActivate->setStatus($order->org_id, SmsOrder::ACCESS_CANCEL);
        // Проверить статус getStatus()
        $result = $this->getStatus($order->org_id, $botDto->api_key);
        if ($result != SmsOrder::STATUS_CANCEL)
            //надо писать лог
            throw new RuntimeException('При проверке статуса произошла ошибка, вернулся статус: ' . $result);

        $order->status = SmsOrder::STATUS_CANCEL;
        if($order->save()) {
            // Он же возвращает баланс
            $amountFinal = $order->price_final;
            $result = BottApi::addBalance($botDto, $userData, $amountFinal, 'Возврат баланса, активация отменена');
        } else {
            throw new RuntimeException('Not save order');
        }
        return $result;
    }

    /**
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return int
     */
    public function confirm(BotDto $botDto, SmsOrder $order)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key);

        if ($order->status == SmsOrder::STATUS_CANCEL)
            throw new RuntimeException('The order has already been canceled');
        if (is_null($order->codes))
            throw new RuntimeException('Попытка установить несуществующий статус');
        if ($order->status == SmsOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');

        $result = $smsActivate->setStatus($order->org_id, SmsOrder::ACCESS_ACTIVATION);

        $result = $this->getStatus($order->org_id, $botDto->api_key);

        if ($result != SmsOrder::STATUS_FINISH)
            //надо писать лог
            throw new RuntimeException('При проверке статуса произошла ошибка, вернулся статус: ' . $result);

        $resultSet = $order->status = SmsOrder::STATUS_FINISH;

        $order->save();

        return $resultSet;
    }

    /**
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return int
     */
    public function second(BotDto $botDto, SmsOrder $order)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key);

        if ($order->status == SmsOrder::STATUS_CANCEL)
            throw new RuntimeException('The order has already been canceled');
        if (is_null($order->codes))
            throw new RuntimeException('Попытка установить несуществующий статус');
        if ($order->status == SmsOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');

        $result = $smsActivate->setStatus($order->org_id, SmsOrder::ACCESS_READY);

        $result = $this->getStatus($order->org_id, $botDto->api_key);

        if ($result != SmsOrder::STATUS_WAIT_RETRY)
            throw new RuntimeException('При проверке статуса произошла ошибка, вернулся статус: ' . $result);

        $resultSet = $order->status = SmsOrder::STATUS_WAIT_RETRY; //проверить что приходит с сервиса и поменять на STATUS_WAIT_RETRY

        $order->save();
        return $resultSet;
    }

    /**
     * @param array $userData
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return void
     */
    public function order(array $userData, BotDto $botDto, SmsOrder $order): void
    {
        switch ($order->status) {
            case SmsOrder::STATUS_CANCEL:
            case SmsOrder::STATUS_FINISH:
                break;
            case SmsOrder::STATUS_WAIT_CODE:
            case SmsOrder::STATUS_WAIT_RETRY:
                $resultStatus = $this->getStatus($order->org_id, $botDto->api_key);
                switch ($resultStatus) {
                    case SmsOrder::STATUS_OK:
                    case SmsOrder::STATUS_FINISH:
                    case SmsOrder::STATUS_CANCEL:
                        break;
                    case SmsOrder::STATUS_WAIT_CODE:
                    case SmsOrder::STATUS_WAIT_RETRY:
                        $smsActivate = new SmsActivateApi($botDto->api_key);
                        $activateActiveOrders = $smsActivate->getActiveActivations();
                        if (key_exists('activeActivations', $activateActiveOrders)) {
                            $activateActiveOrders = $activateActiveOrders['activeActivations'];

                            $results = [];
                            foreach ($activateActiveOrders as $activateActiveOrder) {
                                $order_id = $activateActiveOrder['activationId'];
                                if ($order_id == $order->org_id) {
                                    $results = $activateActiveOrder;
                                }
                            }

                            // if (key_exists('smsCode', $results)) {
                            //     if (is_null($order->codes)) {
                            //         BottApi::createOrder($botDto, $userData, $order->price_final,
                            //             'Заказ активации для номера ' . $order->phone . ' с смс: ' . $results['smsCode']);
                            //     }
                            //     $order->codes = $results['smsCode'];
                            // }
                        }

                        $order->status = $resultStatus;
                        $order->save();
                        break;
                }
        }
    }

    /**
     * @return void
     */
    public function cronUpdateStatus(): void
    {
        $statuses = [SmsOrder::STATUS_WAIT_CODE, SmsOrder::STATUS_WAIT_RETRY];

        $orders = SmsOrder::query()->where(['status' => $statuses])
            ->where('end_time', '>=' , time())->get();

        foreach ($orders as $key => $order) {
            $bot = SmsBot::query()->where(['id' => $order->bot_id])->first();

            $botDto = BotFactory::fromEntity($bot);
            $result = BottApi::get(
                $order->user->telegram_id,
                $botDto->public_key,
                $botDto->private_key
            );

            if (is_null($order->codes)) {
                $this->cancel(
                    $result['data'],
                    $botDto,
                    $order
                );
            } else {
                $this->confirm(
                    $botDto,
                    $order
                );
            }

        }
    }

    /**
     * Установка статуса заказа на сервисе
     *
     * @param $order
     * @param $status
     * @param string $api_key
     * @return mixed
     */
    public function setStatus($order, $status, string $api_key)
    {
        $smsActivate = new SmsActivateApi($api_key);

        $serviceResult = $smsActivate->setStatus($order->org_id, $status);

        $data = [
            'status' => $status
        ];

        $order->update($data);
        $order->save();

        return $serviceResult;
    }

    /**
     * Статус заказа с сервиса
     *
     * @param $id
     * @param string $api_key
     * @return mixed
     */
    public function getStatus($id, string $api_key)
    {
        $smsActivate = new SmsActivateApi($api_key);

        $serviceResult = $smsActivate->getStatus($id);
        return $serviceResult;
    }

    /**
     * Создание заказа bot-t
     * @param $order
     * @param $bot
     * @param $user_key
     * @param $uri
     * @return \Psr\Http\Message\StreamInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createBotOrder($order, $bot, $uri, $user_key)
    {
        $link = 'https://api.bot-t.com/v1/module/shop/';
        $public_key = $bot->public_key; //062d7c679ca22cf88b01b13c0b24b057
        $private_key = $bot->private_key; //d75bee5e605d87bf6ebd432a2b25eb0e
        $user_id = $order->user->telegram_id; //1028741753
        $secret_key = $user_key; //'2997ec12c0c4e2df3e316d943e3da6e72997ec123e3d4d9429971695e4d5e4d5';
        $amount = $order->price_final; //1050
        $count = 1;
        $category_id = $bot->category_id;
        $product = 'СМС Активация';

        $requestParam = [
            'public_key' => $public_key,
            'private_key' => $private_key,
            'user_id' => $user_id,
            'secret_key' => $secret_key,
            'amount' => $amount,
            'count' => $count,
            'category_id' => $category_id,
            'product' => $product,
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', $uri, [
            'form_params' => $requestParam,
        ]);

        return $response->getBody();
    }

    /**
     * Списание баланса на Bot-t
     *
     * @param $order
     * @param $bot
     * @param $uri
     * @param $user_key
     * @return \Psr\Http\Message\StreamInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function changeBalance($order, $bot, $uri, $user_key)
    {
        $link = 'https://api.bot-t.com/v1/module/user/';
        $public_key = $bot->public_key; //062d7c679ca22cf88b01b13c0b24b057
        $private_key = $bot->private_key; //d75bee5e605d87bf6ebd432a2b25eb0e
        $user_id = $order->user->telegram_id; //1028741753
        $secret_key = $user_key; //'2997ec12c0c4e2df3e316d943e3da6e72997ec123e3d4d9429971695e4d5e4d5';
        $amount = $order->price_final; //1050
        $comment = 'Списание за активацию СМС';

        $requestParam = [
            'public_key' => $public_key,
            'private_key' => $private_key,
            'user_id' => $user_id,
            'secret_key' => $secret_key,
            'amount' => $amount,
            'comment' => $comment,
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', $uri, [
            'form_params' => $requestParam,
        ]);

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }
}
