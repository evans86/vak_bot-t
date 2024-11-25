<?php

namespace App\Services\Activate;

use App\Dto\BotDto;
use App\Dto\BotFactory;
use App\Helpers\BotLogHelpers;
use App\Models\Activate\SmsCountry;
use App\Models\Bot\SmsBot;
use App\Models\Rent\RentOrder;
use App\Models\User\SmsUser;
use App\Services\External\BottApi;
use App\Services\External\SmsActivateApi;
use App\Services\MainService;
use GuzzleHttp\Pool;
use RuntimeException;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;

class RentService extends MainService
{
    /**
     * формируем список стран
     *
     * @param BotDto $botDto
     * @return array
     */
    public function getRentCountries(BotDto $botDto)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key, $botDto->resource_link);

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
     * @param BotDto $botDto
     * @param $country
     * @return array
     */
    public function getRentService(BotDto $botDto, $country)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key, $botDto->resource_link);

        $resultRequest = $smsActivate->getRentServicesAndCountries($country);
        $services = $resultRequest['services'];

        $result = [];
        foreach ($services as $key => $service) {

            $amountStart = intval(floatval($service['retail_cost']) * 100);
            $amountFinal = $amountStart + ($amountStart * ($botDto->percent / 100));

            array_push($result, [
                'name' => $key,
                'count' => $service['quant']['total'],
                'cost' => $amountFinal,
                'image' => 'https://smsactivate.s3.eu-central-1.amazonaws.com/assets/ico/' . $key . '0.webp',
            ]);
        }

        return $result;
    }

    /**
     * получить цену аренды отдельного сервиса
     *
     * @param BotDto $botDto
     * @param $country
     * @param $service
     * @return mixed
     */
    public function getPriceService(BotDto $botDto, $country, $service)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key, $botDto->resource_link);

        $resultRequest = $smsActivate->getRentServicesAndCountries($country);

        if (!isset($resultRequest['services'][$service]))
            throw new RuntimeException('Сервис не указан или название неверно');

        $service = $resultRequest['services'][$service];
        $service_price = $service['retail_cost'];

        return $service_price;
    }

    /**
     * создание заказа на аренду
     *
     * @param BotDto $botDto
     * @param $service
     * @param $country
     * @param $time
     * @param array|null $userData
     * @param $url
     * @return array
     */
    public function create(BotDto $botDto, $service, $country, $time, array $userData, $url = 'https://activate.bot-t.com/rent/updateSmsRent')
    {
        $smsActivate = new SmsActivateApi($botDto->api_key, $botDto->resource_link);

        $user = SmsUser::query()->where(['telegram_id' => $userData['user']['telegram_id']])->first();
        if (is_null($user)) {
            throw new RuntimeException('not found user');
        }

        $country = SmsCountry::query()->where(['org_id' => $country])->first();
        $orderAmount = $this->getPriceService($botDto, $country->org_id, $service);
        $amountStart = intval(floatval($orderAmount) * 100);
        $amountFinal = $amountStart + ($amountStart * ($botDto->percent / 100));

        //проверка баланса пользователя
        if ($amountFinal > $userData['money']) {
            throw new RuntimeException('Пополните баланс в боте..');
        }

        // Попытаться списать баланс у пользователя
        $result = BottApi::subtractBalance($botDto, $userData, $amountFinal, 'Списание баланса для аренды номера.');

        // Неудача
        if (!$result['result']) {
            throw new RuntimeException('При списании баланса произошла ошибка: ' . $result['message']);
        }

        $resultRequest = $smsActivate->getRentNumber($service, $country->org_id, $time, $url);
        $end_time = strtotime($resultRequest['phone']['endDate']);

        $data = [
            'bot_id' => $botDto->id,
            'user_id' => $user->id,
            'service' => $service,
            'country_id' => $country->id,
            'org_id' => $resultRequest['phone']['id'],
            'phone' => $resultRequest['phone']['number'],
            'codes' => null,
            'status' => RentOrder::STATUS_WAIT_CODE,
            'start_time' => time(),
            'end_time' => $end_time,
            'operator' => null,
            'price_final' => $amountFinal,
            'price_start' => $amountStart,
        ];

        $rent_order = RentOrder::create($data);

        $responseData = [
            'id' => $rent_order->org_id,
            'phone' => $rent_order->phone,
            'start_time' => $rent_order->start_time,
            'end_time' => $rent_order->end_time,
            'status' => $rent_order->status,
            'codes' => null,
            'country' => $country->org_id,
            'service' => $rent_order->service,
            'cost' => $amountFinal
        ];

        return $responseData;
    }

    /**
     * Отмена аренды
     *
     * @param BotDto $botDto
     * @param RentOrder|null $rent_order
     * @param array|null $userData
     * @return mixed
     */
    public function cancel(BotDto $botDto, RentOrder $rent_order, array $userData)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key, $botDto->resource_link);

        // Проверить уже отменёный
        if ($rent_order->status == RentOrder::STATUS_CANCEL)
            throw new RuntimeException('The order has already been canceled');
        // Проверить активированный
        if ($rent_order->status == RentOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');
        if (!is_null($rent_order->codes))
            throw new RuntimeException('The order has not been canceled, the number has been activated');

        $result = $smsActivate->setRentStatus($rent_order->org_id, RentOrder::ACCESS_CANCEL);

        $rent_order->status = RentOrder::STATUS_CANCEL;

        if ($rent_order->save()) {
            // Он же возвращает баланс
            $amountFinal = $rent_order->price_final;
            $result = BottApi::addBalance($botDto, $userData, $amountFinal, 'Возврат баланса, аренда отменена');
        } else {
            throw new RuntimeException('Not save order');
        }

        return $result;
    }

    /**
     * Успешно завершить аренду
     *
     * @param BotDto $botDto
     * @param RentOrder|null $rent_order
     * @param array|null $userData
     * @return false|mixed|string
     */
    public function confirm(BotDto $botDto, RentOrder $rent_order, array $userData)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key, $botDto->resource_link);

        if ($rent_order->status == RentOrder::STATUS_CANCEL)
            throw new RuntimeException('The order has already been canceled');
//        if (is_null($rent_order->codes))
//            throw new RuntimeException('Попытка установить несуществующий статус');
        if ($rent_order->status == RentOrder::STATUS_FINISH)
            throw new RuntimeException('The order has not been canceled, the number has been activated, Status 10');

        $result = $smsActivate->setRentStatus($rent_order->org_id, RentOrder::ACCESS_FINISH);

        $rent_order->status = RentOrder::STATUS_FINISH;

        if ($rent_order->save()) {
            BottApi::createOrder($botDto, $userData, $rent_order->price_final,
                'Заказ активации для номера ' . $rent_order->phone);
        } else {
            throw new RuntimeException('Not save order');
        }

        return RentOrder::STATUS_FINISH;
    }

    /**
     * цена продления аренды
     *
     * @param BotDto $botDto
     * @param RentOrder|null $rent_order
     * @param $time
     * @return float|int
     */
    public function priceContinue(BotDto $botDto, RentOrder $rent_order, $time)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key, $botDto->resource_link);

        $resultRequest = $smsActivate->getContinueRentPriceNumber($rent_order->org_id, $time);
        $requestAmount = $resultRequest['price'];

        $amountStart = intval(floatval($requestAmount) * 100);
        $amountFinal = $amountStart + ($amountStart * ($botDto->percent / 100));

        return $amountFinal;
    }

    /**
     * продление срока аренды
     *
     * @param BotDto $botDto
     * @param RentOrder|null $rent_order
     * @param $time
     * @param array|null $userData
     * @return void
     */
    public function continueRent(BotDto $botDto, RentOrder $rent_order, $time, array $userData)
    {
        $smsActivate = new SmsActivateApi($botDto->api_key, $botDto->resource_link);

        $user = SmsUser::query()->where(['telegram_id' => $userData['user']['telegram_id']])->first();
        if (is_null($user)) {
            throw new RuntimeException('not found user');
        }

        $amountFinal = $this->priceContinue($botDto, $rent_order, $time);

        //проверка баланса пользователя
        if ($amountFinal > $userData['money']) {
            throw new RuntimeException('Пополните баланс в боте..');
        }

        // Попытаться списать баланс у пользователя
        $result = BottApi::subtractBalance($botDto, $userData, $amountFinal, 'Списание баланса для продления аренды номера.');

        // Неудача отмена - заказа
        if (!$result['result']) {
            throw new RuntimeException('При списании баланса произошла ошибка: ' . $result['message']);
        }

        $resultRequest = $smsActivate->continueRentNumber($rent_order->org_id, $time);

        $end_time = strtotime($resultRequest['phone']['endDate']);
        $rent_order->end_time = $end_time;

        $rent_order->save();
    }

    /**
     * обновление кода через вебхук
     *
     * @param array $hook_rent
     * @return void
     */
    public function updateSms(array $hook_rent)
    {
        $rent_org_id = $hook_rent['rentId'];
        $codes = $hook_rent['sms']['text'];
        $codes_date = strtotime($hook_rent['sms']['date']);
        $codes_id = $hook_rent['sms']['smsId'];

        $rentOrder = RentOrder::query()->where(['org_id' => $rent_org_id])->first();

//        $str_code = 'Ваш код подтверждения: 107-981. Наберите его в поле ввода.';
        $codes = explode(' ', $codes);
        $codes = $codes[3];
        $update_codes = $rentOrder->codes . ' ' . $codes;

        $rentOrder->codes = $update_codes;
        $rentOrder->codes_id = $codes_id;
        $rentOrder->codes_date = $codes_date;

        $rentOrder->save();
    }

    // Асинхронный запрос для тестов
    public function cronGuzzle()
    {
        $url = 'http://vpn/closeOrder';

        $params = [
            'user_id' => '1',
            'order_id' => '1731874944091838',
            'public_key' => '062d7c679ca22cf88b01b13c0b24b057',
        ];

        $requests = 10;
        $promises = [];
        $client = new \GuzzleHttp\Client([
            'verify' => false,
            'http_errors' => false // чтобы видеть тело ответа даже при ошибках
        ]);

        for ($i = 0; $i < $requests; $i++) {
            $promises[] = $client->getAsync($url, [ // используем GET запрос
                'query' => $params // параметры будут добавлены к URL
            ]);
        }

        $results = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

        $successful_cancellations = 0;
        $errors = [];

        foreach ($results as $result) {
            if ($result['state'] === 'fulfilled') {
                $response = json_decode($result['value']->getBody(), true);
                echo "Response: " . json_encode($response) . "\n";
                if (isset($response['success']) && $response['success']) {
                    $successful_cancellations++;
                } else {
                    $errors[] = $response['message'] ?? 'Unknown error';
                }
            } else {
                $errors[] = $result['reason']->getMessage();
                echo "Request Error: " . $result['reason']->getMessage() . "\n";
            }
        }

        echo "\nSummary:\n";
        echo "Total requests: $requests\n";
        echo "Successful cancellations: $successful_cancellations\n";
        echo "\nErrors encountered:\n";
        foreach (array_count_values($errors) as $error => $count) {
            echo "$error: $count times\n";
        }


//        $url = 'http://vak/closeOrder?user_id=1&order_id=1731874944091838&public_key=062d7c679ca22cf88b01b13c0b24b057'; // URL вашего endpoint'а
//
//        $requests = 10; // Количество параллельных запросов
//        $promises = [];
//        $client = new \GuzzleHttp\Client();
//
//        for ($i = 0; $i < $requests; $i++) {
//            $promises[] = $client->postAsync($url, [
//            ]);
//        }
//
//        // Запускаем все запросы параллельно
//        $results = \GuzzleHttp\Promise\Utils::settle($promises)->wait();
//
//        $successful_cancellations = 0;
//        $errors = [];
//
//        foreach ($results as $result) {
//            if ($result['state'] === 'fulfilled') {
//                $response = json_decode($result['value']->getBody(), true);
//                if (isset($response['success']) && $response['success']) {
//                    $successful_cancellations++;
//                } else {
//                    // Сохраняем текст ошибки
//                    $errors[] = $response['message'] ?? 'Unknown error';
//                }
//            } else {
//                // Сохраняем ошибку запроса
//                $errors[] = $result['reason']->getMessage();
//            }
//        }
//
//        echo "Total requests: $requests\n";
//        echo "Successful cancellations: $successful_cancellations\n";
//        echo "\nErrors encountered:\n";
//        foreach (array_count_values($errors) as $error => $count) {
//            echo "$error: $count times\n";
//        }

//        $client = new Client();
//        $requests = [
//            'req1' => $client->getAsync('http://vak/closeOrder?user_id=1&order_id=1731874944091838&public_key=062d7c679ca22cf88b01b13c0b24b057'),
//            'req2' => $client->getAsync('http://vak/closeOrder?user_id=1&order_id=1731874944091838&public_key=062d7c679ca22cf88b01b13c0b24b057'),
//            'req3' => $client->getAsync('http://vak/closeOrder?user_id=1&order_id=1731874944091838&public_key=062d7c679ca22cf88b01b13c0b24b057')
//        ];
//
//        $pool = new Pool($client, $requests);
//        $promise = $pool->promise();
//        $promise->wait();

// Ждем завершения всех запросов
//        $results = Promise::settle($promises)->wait();

// Обрабатываем результаты каждого запроса
//        foreach ($results as $url => $result) {
//            if ($result['state'] === 'fulfilled') {
//                // Обработка успешного ответа
//                echo 'Response from ' . $url . ': ' . $result['value']->getBody() . PHP_EOL;
//            } else {
//                // Обработка ошибки
//                echo 'Error making request to ' . $url . PHP_EOL;
//            }
//        }


//        $client = new \GuzzleHttp\Client([
//            'base_uri' => 'http://vak',
//        ]);
//
//        $urls = [
//            '/closeOrder?user_id=1&order_id=1731874944091838&public_key=062d7c679ca22cf88b01b13c0b24b057',
//            '/closeOrder?user_id=1&order_id=1731874944091838&public_key=062d7c679ca22cf88b01b13c0b24b057',
//            '/closeOrder?user_id=1&order_id=1731874944091838&public_key=062d7c679ca22cf88b01b13c0b24b057',
//            '/closeOrder?user_id=1&order_id=1731874944091838&public_key=062d7c679ca22cf88b01b13c0b24b057',
//        ];
//
//        $promises = [];
//
//        foreach ($urls as $urlIndex => $url) {
//            $request = new \GuzzleHttp\Psr7\Request('GET', $url, []);
//
//            echo date('d.m.Y H:i:s') . ' запрос ' . $url . PHP_EOL;
//
//            $promises[$urlIndex] = $client->sendAsync($request, [
//                'timeout' => 10,
//                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use ($url) {
//                    // Тут можно получить статистику запроса
//                    $stat = $stats->getHandlerStats();
////                    dd($stat);
//                    echo date('d.m.Y H:i:s') . ' получена статистика ' . $url . PHP_EOL;
//                }
//            ]);
//
//            $promises[$urlIndex]->then(
//                function (\Psr\Http\Message\ResponseInterface $res) use ($url) {
//                    // Тут обработка ответа
//                    echo date('d.m.Y H:i:s') . ' запрос выполнен ' . $url . PHP_EOL;
//                },
//                function (\GuzzleHttp\Exception\RequestException $e) {
//                    // Тут обработка ошибки
//                }
//            );
//        }
//
//        // Ждать ответов
//        $results = \GuzzleHttp\Promise\Utils::settle($promises)->wait(true);
//
//        // Обработка результатов по всем запросам
//        if (sizeof($results) > 0) {
//            foreach ($results as $urlIndex => $result) {
//                // Обработка ответа по запросу $urls[$urlIndex]
//
//                if ($result['state'] != 'fulfilled' || !isset($result['value'])) {
//                    // Если запрос выполнился с ошибкой
//                    continue;
//                }
//
//                /** @var \GuzzleHttp\Psr7\Response $response */
//                $response = $result['value'];
//
//                // Получение заголовков
//                // $response->getHeaderLine('Content-Length');
//
//                // Обработка тела ответа
//                $body = $response->getBody();
//                echo date('d.m.Y H:i:s') . ' обработка запроса в цикле' . $urls[$urlIndex] . PHP_EOL;
//            }
//        }
    }

    /**
     * крон обновления статуса
     *
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public
    function cronUpdateRentStatus(): void
    {
        $statuses = [RentOrder::STATUS_WAIT_CODE];

        $rent_orders = RentOrder::query()->whereIn('status', $statuses)
            ->where('end_time', '<=', time())->get();

        echo "START count:" . count($rent_orders) . PHP_EOL;

        foreach ($rent_orders as $key => $rent_order) {
            echo $rent_order->id . PHP_EOL;

            $bot = SmsBot::query()->where(['id' => $rent_order->bot_id])->first();

            $botDto = BotFactory::fromEntity($bot);
            $result = BottApi::get(
                $rent_order->user->telegram_id,
                $botDto->public_key,
                $botDto->private_key
            );

            echo 'confirm_start' . PHP_EOL;
            $this->confirm(
                $botDto,
                $rent_order,
                $result['data']
            );
            echo 'confirm_finish' . PHP_EOL;

            echo "FINISH" . $rent_order->id . PHP_EOL;
        }
    }
}
