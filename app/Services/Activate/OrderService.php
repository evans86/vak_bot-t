<?php

namespace App\Services\Activate;

use App\Models\Activate\SmsCountry;
use App\Models\Order\SmsOrder;
use App\Services\External\SmsActivateApi;
use App\Services\MainService;
use GuzzleHttp\Client;

class OrderService extends MainService
{
    /**
     * Создание заказа а сервисе
     *
     * @param $service
     * @param $country_id
     * @param $user_id
     * @param $bot
     * @param $user_secret_key
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createOrder($service, $country_id, $user_id, $bot, $user_secret_key)
    {
        try {
            $smsActivate = new SmsActivateApi($bot->api_key);

            $serviceResult = $smsActivate->getNumberV2(
                $service,
                $country_id,
                0,
                null,
                config('services.key_activate.ref')
            );

            $dateTime = new \DateTime($serviceResult['activationTime']);
            $dateTime = $dateTime->format('U');
            $dateTime = intval($dateTime);

            $id = intval($serviceResult['activationId']);

            $countries = $smsActivate->getTopCountriesByService($service);
            foreach ($countries as $key => $country) {
                if ($country['country'] == $country_id) {
                    $price = $country["retail_price"];
                    $pricePercent = $price + ($price * ($bot->percent / 100));
                    break;
                }
            }

            $country = SmsCountry::query()->where(['org_id' => $country_id])->first();

            $data = [
                'bot_id' => $bot->id,
                'user_id' => $user_id,

                'service_id' => null,
                'service' => $service,
                'country_id' => $country->id,

                'org_id' => $id,
                'phone' => $serviceResult['phoneNumber'],
                'codes' => null,
                'status' => $this->getStatus($id, $bot), //4
                'start_time' => $dateTime,
                'end_time' => $dateTime + 1177,
                'operator' => $serviceResult['activationOperator'],
                'price_final' => $pricePercent * 100,
                'price_start' => $price * 100,
            ];

            $order = SmsOrder::create($data);

            //списание баланса

            $change_balance = $this->changeBalance($order, $bot, 'subtract-balance', $user_secret_key);

            if ($change_balance['result'] == false) {
                $this->setStatus($order, 8, $bot);
                throw new \Exception($change_balance['message']);
            }

//            $this->createBotOrder($order, $bot, 'order-create', $user_secret_key);

            $order->save();

            $result = [
                'id' => $id,
                'phone' => $serviceResult['phoneNumber'],
                'time' => $dateTime,
                'status' => $this->getStatus($id, $bot), //4
                'codes' => null,
                'country' => $country->org_id,
                'operator' => $serviceResult['activationOperator'],
                'service' => $service,
                'cost' => $pricePercent
            ];

            return $result;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Установка статуса заказа на сервисе
     *
     * @param $order
     * @param $status
     * @param $bot
     * @return mixed
     */
    public function setStatus($order, $status, $bot)
    {
        $smsActivate = new SmsActivateApi($bot->api_key);

        $serviceResult = $smsActivate->setStatus($order->org_id, $status);

        $data = [
            'status' => $status
        ];

        $order->update($data);
        $order->save();

        return $serviceResult;
    }

    /**
     * @param $order
     * @param $bot
     * @param $user_secret_key
     * @return mixed|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getActive($order, $bot, $user_secret_key)
    {
        $smsActivate = new SmsActivateApi($bot->api_key);

        $serviceResults = $smsActivate->getActiveActivations();

        //Если мы до этого подтвердили успешное получение смс, то для нашего заказа нужен статус ACCESS_ACTIVATION
        if ($order->status == SmsOrder::ACCESS_ACTIVATION) {
            $order->status = SmsOrder::ACCESS_ACTIVATION;
            $order->save();
            //Если отменили заказ, то нужен статус ACCESS_CANCEL
        } elseif ($order->status == SmsOrder::ACCESS_CANCEL) {
            $order->status = SmsOrder::ACCESS_CANCEL;
            $order->save();
            //Если мы никаких действий с заказом не производили, то надо пробежаться по кейсам и посмтореть что с
            //заказом на Sms-activate
        } else {
            //Получаем статус заказа по его ID в Sms-activate
            //Объясню почему тут присутстствуют такие статусы:
            //Когда проверял через запрос, когда-то в ответ приходил, когда-то другой, но по своей сути они сводятся
            // к небольшому количеству конечных результатов и установка статусов для заказа который находится
            // у НАС в БД, по сути является флагом для фронта
            switch ($this->getStatus($order->org_id, $bot)) {
                //Вернулся статус STATUS_WAIT_RETRY, значит устанавливаем доступ ACCESS_READY
                case SmsOrder::STATUS_WAIT_RETRY:
                    $order->status = SmsOrder::ACCESS_READY;
                    $order->save();
                    break;
                    //Бывает что при запросе по API может вернуть статус ОК, он по сути промежуточный, но конкретно
                //в этот раз мы сделали запрос и получили статсус ОК, что бы не было ошибки и конфликта с фронтом,
                //его тоже включил
                case SmsOrder::STATUS_OK:
                    //Вернулся статус STATUS_WAIT_CODE, значит устанавливаем доступ STATUS_WAIT_CODE
                case SmsOrder::STATUS_WAIT_CODE:
                    $order->status = SmsOrder::STATUS_WAIT_CODE;
                    $order->save();
                    break;
            }
        }

        //Условие если мы вызвали метод, а время заказа закончилось (думаю часто кто-то будет забывать закрыть заказ
        //так что вызов этого метода продолжается всё время существоания заказа и еще несколько запросов после его окончания
        if (time() >= $order->end_time) {
            //Здесь мы уже пробежимся по статусам которые у нас остались записанными после окночания заказа
            switch ($order->status) {
                //Записаны статусы ACCESS_READY, STATUS_WAIT_CODE значит нужно решить с каким окончательным статусом
                //закрыть заказ (их два: отменён и сумма вернулась на баланс и успешно получил СМС и зарезервированная сумма
                //не возвращается
                case SmsOrder::ACCESS_READY:
                case SmsOrder::STATUS_WAIT_CODE:
                    //проверяем наличие полученных кодов (это единственный флаг который можно отработать от Sms-activate)
                    if (is_null($order->codes)) {
                        //Если ничего не было - закрываем заказ отменой ACCESS_CANCEL и возвращаем баланс
                        $order->status = SmsOrder::ACCESS_CANCEL;
                        $order->save();
                        $this->changeBalance($order, $bot, 'add-balance', $user_secret_key);
                    } else {
                        //коды были полусены - успешно завершаем активацию и ничего не возвращаем
                        $order->status = SmsOrder::ACCESS_ACTIVATION;
                        $order->save();
                    }
                    break;
                    //И последнее, если метод был вызван, а заказ уже закрыт, то пропуск
                case SmsOrder::ACCESS_ACTIVATION:
                case SmsOrder::ACCESS_CANCEL:
                    break;
            }
        }

        if (key_exists('activeActivations', $serviceResults)) {
            $serviceResults = $serviceResults['activeActivations'];

            $results = [];
            foreach ($serviceResults as $serviceResult) {
                $order_id = $serviceResult['activationId'];
                if ($order_id == $order->org_id)
                    $results = $serviceResult;
            }

            if (key_exists('smsCode', $results))
                $result = $results['smsCode'];
            else
                $result = null;
        } else {
            $result = null;
        }

        $data = [
            'codes' => $result,
            'status' => $order->status
        ];

        $order->update($data);
        $order->save();

        return $result;
    }

    /**
     * Статус заказа с сервиса
     *
     * @param $id
     * @param $bot
     * @return mixed
     */
    public function getStatus($id, $bot)
    {
        $smsActivate = new SmsActivateApi($bot->api_key);

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
