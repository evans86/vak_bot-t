<?php

namespace App\Services\Activate;

use App\Dto\BotDto;
use App\Dto\BotFactory;
use App\Helpers\BotLogHelpers;
use App\Models\Activate\SmsCountry;
use App\Models\Bot\SmsBot;
use App\Models\Order\SmsOrder;
use App\Models\User\SmsUser;
use App\Services\External\BottApi;
use App\Services\External\VakApi;
use App\Services\MainService;
use DB;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Log;
use RuntimeException;

class OrderService extends MainService
{
    /**
     * @param BotDto $botDto
     * @param string $country_id
     * @param string $services
     * @param array $userData
     * @return array
     * @throws GuzzleException
     */
    public function createMulti(BotDto $botDto, string $country_id, string $services, array $userData)
    {
        $smsVak = new VakApi($botDto->api_key, $botDto->resource_link);
        $user = SmsUser::query()->where(['telegram_id' => $userData['user']['telegram_id']])->first();
//        $user = SmsUser::query()->where(['id' => 1])->first();
        if (is_null($user)) {
            throw new RuntimeException('not found user');
        }

        $convert_services = explode(',', $services);
        $first_price = $smsVak->getCountNumber($convert_services[0], $country_id);
        $second_price = $smsVak->getCountNumber($convert_services[1], $country_id);

        $all_price_services = $first_price['price'] + $second_price['price'];
        $all_price = $all_price_services + ($all_price_services / 2);

        $amountStart = (int)ceil(floatval($all_price) * 100);
        $amountFinal = $amountStart + $amountStart * $botDto->percent / 100;

        if ($amountFinal > $userData['money']) {
            throw new RuntimeException('Пополните баланс в боте');
        }

        //Попытаться списать баланс у пользователя
        $result = BottApi::subtractBalance($botDto, $userData, $amountFinal, 'Списание баланса для активации номера');

        if (!$result['result']) {
            throw new RuntimeException('При списании баланса произошла ошибка: ' . $result['message']);
        }

        //Создание мультисервиса
        $serviceResults = $smsVak->getNumber(
            $services,
            $country_id
        );

        // Удача создание заказа в бд
        $country = SmsCountry::query()->where(['org_id' => $country_id])->first();
        $dateTime = intval(time());

        $response = [];

        foreach ($serviceResults as $key => $serviceResult) {

            $service_price = $smsVak->getCountNumber($serviceResult['service'], $country_id);
            $final_service_price = $service_price['price'] + (($all_price_services / 2) / 2);

            //формирование цены для каждого заказа
            $amountStart = (int)ceil(floatval($final_service_price) * 100);
            $amountFinal = $amountStart + $amountStart * $botDto->percent / 100;

            $data = [
                'bot_id' => $botDto->id,
                'user_id' => $user->id,
                'service' => $serviceResult['service'],
                'country_id' => $country->id,
                'org_id' => $serviceResult['idNum'],
                'phone' => $serviceResult['tel'],
                'codes' => null,
                'status' => SmsOrder::STATUS_WAIT_CODE,
                'start_time' => $dateTime,
                'end_time' => $dateTime + 1177,
                'operator' => null,
                'price_final' => $amountFinal,
                'price_start' => $amountStart,
            ];

            $order = SmsOrder::create($data);
            Log::info('Vak: Произошло создание заказа (списание баланса) ' . $order->id);

            array_push($response, [
                'id' => $order->org_id,
                'phone' => $order->phone,
                'time' => $order->start_time,
                'status' => $order->status,
                'codes' => null,
                'country' => $country->org_id,
                'service' => $order->service,
                'cost' => $amountFinal
            ]);

        }

        return $response;
    }

    /**
     * Создание заказа
     *
     * @param array $userData Сущность DTO from bott
     * @param BotDto $botDto
     * @param string $country_id
     * @param string $service
     * @return array
     * @throws GuzzleException
     */
    public
    function create(array $userData, BotDto $botDto, string $country_id, string $service): array
    {
        // Создать заказ по апи
        $smsVak = new VakApi($botDto->api_key, $botDto->resource_link);
        $user = SmsUser::query()->where(['telegram_id' => $userData['user']['telegram_id']])->first();
//        $user = SmsUser::query()->where(['id' => 1])->first();
        if (is_null($user)) {
            throw new RuntimeException('not found user');
        }

        $service_price = $smsVak->getCountNumber($service, $country_id);

//        $amountStart = intval(floatval($service_price['price']) * 100);
        $amountStart = (int)ceil(floatval($service_price['price']) * 100);
        $amountFinal = $amountStart + $amountStart * $botDto->percent / 100;

        if ($amountFinal > $userData['money']) {
            throw new RuntimeException('Пополните баланс в боте');
        }

        $serviceResult = $smsVak->getNumber(
            $service,
            $country_id
        );

        $org_id = $serviceResult['idNum'];

        // Удача создание заказа в бд
        $country = SmsCountry::query()->where(['org_id' => $country_id])->first();
        $dateTime = time();
        $data = [
            'bot_id' => $botDto->id,
            'user_id' => $user->id,
            'service' => $service,
            'country_id' => $country->id,
            'org_id' => $org_id,
            'phone' => $serviceResult['tel'],
            'codes' => null,
            'status' => SmsOrder::STATUS_WAIT_CODE, //4
            'start_time' => $dateTime,
            'end_time' => $dateTime + 1177,
            'operator' => null,
            'price_final' => $amountFinal,
            'price_start' => $amountStart,
        ];

        $order = SmsOrder::create($data);

        //Попытаться списать баланс у пользователя
        $result = BottApi::subtractBalance($botDto, $userData, $amountFinal, 'Списание баланса для активации номера ' . $serviceResult['tel']);

        if (!$result['result']) {
            $this->cancel($userData, $botDto, $order, true);
            throw new RuntimeException('При списании баланса произошла ошибка: ' . $result['message']);
        }

        Log::info('Vak: Произошло создание заказа (списание баланса) ' . $order->id);

        $result = [
            'id' => $order->org_id,
            'phone' => $serviceResult['tel'],
            'time' => $dateTime,
            'status' => $order->status,
            'codes' => null,
            'country' => $country->org_id,
            'operator' => null,
            'service' => $service,
            'cost' => $amountFinal
        ];
        return $result;
    }

    /**
     * Отмена заказа со статусом 9
     *
     * @param array $userData
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return mixed
     * @throws GuzzleException
     */
    public
    function cancel(array $userData, BotDto $botDto, SmsOrder $order, bool $error = false)
    {
        // Проверить уже отменёный
        if ($order->status == SmsOrder::STATUS_CANCEL)
            throw new RuntimeException('The order has already been canceled ' . $botDto->bot_id);
        if ($order->status == SmsOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');
        // Можно отменить только статус 4 и кодов нет
        if (!is_null($order->codes))
            throw new RuntimeException('The order has not been canceled, the number has been activated');

        $smsVak = new VakApi($botDto->api_key, $botDto->resource_link);

        // Обновить статус setStatus()
        try {
            $result = $smsVak->setStatus($order->org_id, SmsOrder::ACCESS_END);

            if ($result['status'] == SmsOrder::STATUS_RECEIVED)
                throw new RuntimeException('На данный номер уже получен код подтверждения, отмена невозможна.');
            if ($result['status'] == SmsOrder::STATUS_WAIT_SMS)
                throw new RuntimeException('На данные номер уже отправлено смс, отмена невозможна. Ожидайте код.');

        } catch (Exception $e) {
            if ($e->getMessage() != 'Не верный ID операции')
                throw new RuntimeException('Ошибка сервера');
        }

        $order->status = SmsOrder::STATUS_CANCEL;
        if ($order->save()) {
            if ($error) {
                Log::info('Vak: Произошла отмена заказа (без возврата (ошибка списания баланса)) ' . $order->id);
                BotLogHelpers::notifyBotLog('(🟢SUB ' . __FUNCTION__ . ' Vak): ' . 'Произошла отмена заказа (без возврата (ошибка списания баланса)) ' . $order->id);
            }else{
                // Он же возвращает баланс
                $amountFinal = $order->price_final;
                BotLogHelpers::notifyBotLog('(🟢SUB ' . __FUNCTION__ . ' Vak): ' . 'Вернул баланс order_id = ' . $order->id);
                $result = BottApi::addBalance($botDto, $userData, $amountFinal, 'Возврат баланса, активация отменена order_id = ' . $order->id);
                Log::info('Vak: Произошла отмена заказа (возврат баланса) ' . $order->id);
            }
        } else {
            throw new RuntimeException('Not save order');
        }
        return $result;
    }

    /**
     * Успешное завершение заказа со статусом 10
     *
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return int
     */
    public
    function confirm(BotDto $botDto, SmsOrder $order)
    {
        if ($order->status == SmsOrder::STATUS_CANCEL)
            throw new RuntimeException('The order has already been canceled');
        if (is_null($order->codes))
            throw new RuntimeException('Попытка установить несуществующий статус');
        if ($order->status == SmsOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');

        $order->status = SmsOrder::STATUS_FINISH;

        $order->save();
        Log::info('Vak: Произошло успешное завершение заказа ' . $order->id);

        return SmsOrder::STATUS_FINISH;
    }

    /**
     * Повторное получение СМС
     *
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return int
     * @throws GuzzleException
     */
    public
    function second(BotDto $botDto, SmsOrder $order)
    {
        $smsVak = new VakApi($botDto->api_key, $botDto->resource_link);

        if ($order->status == SmsOrder::STATUS_CANCEL)
            throw new RuntimeException('The order has already been canceled');
        if (is_null($order->codes))
            throw new RuntimeException('Попытка установить несуществующий статус');
        if ($order->status == SmsOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');

        $result = $smsVak->setStatus($order->org_id, SmsOrder::ACCESS_SEND);

        if ($result['status'] != SmsOrder::STATUS_READY)
            throw new RuntimeException('При проверке статуса произошла ошибка, вернулся статус: ' . $result['status']);

        $resultSet = $order->status = SmsOrder::STATUS_WAIT_RETRY;

        $order->save();
        return $resultSet;
    }

    /**
     * Получение активного заказа и обновление кодов
     *
     * @param array $userData
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return void
     * @throws GuzzleException
     */
    public
    function order(array $userData, BotDto $botDto, SmsOrder $order): void
    {
        switch ($order->status) {
            case SmsOrder::STATUS_CANCEL:
            case SmsOrder::STATUS_FINISH:
                break;
            case SmsOrder::STATUS_WAIT_CODE:
            case SmsOrder::STATUS_WAIT_RETRY:
                $smsVak = new VakApi($botDto->api_key, $botDto->resource_link);
                $result = $smsVak->getSmsCode($order->org_id);
                $sms = $result['smsCode'];
                if (is_null($sms))
                    break;
                if (is_null($order->codes)) {
                    BottApi::createOrder($botDto, $userData, $order->price_final,
                        'Заказ активации номера: ' . $order->phone);
                }
                $order->codes = $sms;
                $order->save();

                break;
        }
    }

    /**
     * Крон обновление статусов
     *
     * @return void
     */
    public
    function cronUpdateStatus(): void
    {
        try {
            $statuses = [SmsOrder::STATUS_WAIT_CODE, SmsOrder::STATUS_WAIT_RETRY];

            //используем более строгую блокировку
            $orders = SmsOrder::query()
                ->whereIn('status', $statuses)
                ->where('end_time', '<=', time())
                ->where('status', '!=', SmsOrder::STATUS_CANCEL)
                ->lockForUpdate() // Блокировка для чтения
                ->get();

            echo "START count:" . count($orders) . PHP_EOL;

            $start_text = "VAK Start count: " . count($orders) . PHP_EOL;
            $this->notifyTelegram($start_text);

            $processedOrders = []; // Трекер обработанных заказов

            foreach ($orders as $key => $order) {
                // Проверяем, не обрабатывали ли уже этот заказ
                if (in_array($order->id, $processedOrders)) {
                    echo "SKIP already processed: " . $order->id . PHP_EOL;
                    continue;
                }

                echo "Processing: " . $order->id . PHP_EOL;

                $bot = SmsBot::query()->where(['id' => $order->bot_id])->first();
                $botDto = BotFactory::fromEntity($bot);

                $result = BottApi::get(
                    $order->user->telegram_id,
                    $botDto->public_key,
                    $botDto->private_key
                );

                echo $order->id . PHP_EOL;

                DB::transaction(function () use ($order, $botDto, $result, &$processedOrders) {

                    // Двойная проверка статуса внутри транзакции
                    $freshOrder = SmsOrder::query()
                        ->where('id', $order->id)
                        ->lockForUpdate()
                        ->first();

                    if ($freshOrder->status == SmsOrder::STATUS_CANCEL) {
                        echo "Already canceled: " . $order->id . PHP_EOL;
                        return;
                    }

                    if (is_null($order->codes)) {
                        echo 'cancel_start' . PHP_EOL;
                        $this->cancelCron(
                            $result['data'],
                            $botDto,
                            $order
                        );
                        echo 'cancel_finish' . PHP_EOL;
                    } else {
                        echo 'confirm_start' . PHP_EOL;
                        $this->confirm(
                            $botDto,
                            $order
                        );
                        echo 'confirm_finish' . PHP_EOL;
                    }

                    $processedOrders[] = $freshOrder->id;
                });

                echo "FINISH" . $order->id . PHP_EOL;

            }

            $finish_text = "VAK finish count: " . count($orders) . PHP_EOL;
            $this->notifyTelegram($finish_text);

        } catch (Exception $e) {
            $this->notifyTelegram('🔴' . $e->getMessage());
        }
    }

    /**
     * Отмена заказа со статусом 9 костыль
     *
     * @param array $userData
     * @param BotDto $botDto
     * @param SmsOrder $order
     * @return mixed
     * @throws GuzzleException
     */
    public
    function cancelCron(array $userData, BotDto $botDto, SmsOrder $order)
    {
        // Дополнительная проверка с блокировкой
        $freshOrder = SmsOrder::query()
            ->where('id', $order->id)
            ->lockForUpdate()
            ->first();

        if ($freshOrder->status == SmsOrder::STATUS_CANCEL) {
            BotLogHelpers::notifyBotLog('(🟡SKIP ' . __FUNCTION__ . ' Vak): Order already canceled: ' . $order->id);
            throw new RuntimeException('The order has not been canceled, status 9');
        }

        if ($freshOrder->status == SmsOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');

        if (!is_null($freshOrder->codes))
            throw new RuntimeException('The order has not been canceled, the number has been activated');

        // Обновить статус setStatus()
//        try {
//            $result = $smsVak->setStatus($order->org_id, SmsOrder::ACCESS_END);
//
//            if ($result['status'] == SmsOrder::STATUS_RECEIVED)
//                throw new RuntimeException('На данный номер уже получен код подтверждения, отмена невозможна.');
//            if ($result['status'] == SmsOrder::STATUS_WAIT_SMS)
//                throw new RuntimeException('На данные номер уже отправлено смс, отмена невозможна. Ожидайте код.');
//
//        } catch (\Exception $e) {
//            if ($e->getMessage() != 'Не верный ID операции')
//                throw new RuntimeException('Ошибка сервера');
//        }

        $order->status = SmsOrder::STATUS_CANCEL;
        if ($order->save()) {
            // Он же возвращает баланс
            $amountFinal = $order->price_final;
            $result = BottApi::addBalance($botDto, $userData, $amountFinal, 'Возврат баланса, активация отменена order_id = ' . $order->id);
            BotLogHelpers::notifyBotLog('(🟢SUB ' . __FUNCTION__ . ' Vak): ' . 'Вернул баланс (КРОН) order_id = ' . $order->id);
            Log::info('Vak: Произошла отмена заказа (возврат баланса (крон)) ' . $order->id);
        } else {
            throw new RuntimeException('Not save order');
        }
        return $result;
    }

//    /**
//     * @param $text
//     * @return void
//     * @throws GuzzleException
//     */
//    public function notifyTelegram($text)
//    {
//        $client = new Client();
//
//        $ids = [
//            6715142449,
////            778591134
//        ];
//
//        //CronLogBot#1
//        try {
//            foreach ($ids as $id) {
//                $client->post('https://api.telegram.org/bot6393333114:AAHaxf8M8lRdGXqq6OYwly6rFQy9HwPeHaY/sendMessage', [
//
//                    RequestOptions::JSON => [
//                        'chat_id' => $id,
//                        'text' => $text,
//                    ]
//                ]);
//            }
//            //CronLogBot#2
//        } catch (Exception $e) {
//            foreach ($ids as $id) {
//                $client->post('https://api.telegram.org/bot6934899828:AAGg_f4k1LG_gcZNsNF2LHgdm7tym-1sYVg/sendMessage', [
//
//                    RequestOptions::JSON => [
//                        'chat_id' => $id,
//                        'text' => $text,
//                    ]
//                ]);
//            }
//        }
//    }

    public function notifyTelegram($text)
    {
        $client = new Client([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Принудительно IPv4
            ],
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);

        $ids = [6715142449]; // Список chat_id
        $bots = [
            '6393333114:AAHaxf8M8lRdGXqq6OYwly6rFQy9HwPeHaY', // Основной бот
            '6934899828:AAGg_f4k1LG_gcZNsNF2LHgdm7tym-1sYVg'  // Резервный бот
        ];

        // Если текст пустой, заменяем его на заглушку (или оставляем пустым)
        $message = ($text === '') ? '[Empty message]' : $text;

        $lastError = null;

        foreach ($bots as $botToken) {
            try {
                foreach ($ids as $id) {
                    $client->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        RequestOptions::JSON => [
                            'chat_id' => $id,
                            'text' => $message,
                        ],
                    ]);
                }
                return true; // Успешно отправлено
            } catch (\Exception $e) {
                $lastError = $e;
                continue; // Пробуем следующего бота
            }
        }

        // Если все боты не сработали, логируем ошибку (или просто игнорируем)
        error_log("Telegram send failed: " . $lastError->getMessage());
        return false;
    }
}
