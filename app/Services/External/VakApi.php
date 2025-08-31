<?php

namespace App\Services\External;

use App\Helpers\BotLogHelpers;
use GuzzleHttp\Client;
use http\Exception\RuntimeException;

class VakApi
{
    private $url;

    private $apiKey;
    private $proxy = 'http://VtZNR9Hb:nXC9nQ45@45.147.246.121:64614';

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->url = 'https://moresms.net/api/';
    }

    /**
     * Универсальный метод для отправки запросов через прокси
     */
    private function sendProxyRequest($data, $method = 'GET', $endpoint = '', $count = 0)
    {
        if ($count == 5) {
            throw new RuntimeException('Превышен лимит подключений!');
        }

        try {
            $client = new Client(['base_uri' => $this->url]);

            if ($method === 'GET') {
                $response = $client->get($endpoint . '?' . $data, [
                    'proxy' => $this->proxy,
                ]);
            } else {
                $response = $client->post($endpoint . '?' . $data, [
                    'proxy' => $this->proxy,
                ]);
            }

            return $response->getBody()->getContents();

        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'cURL') !== false) {
                return $this->sendProxyRequest($data, $method, $endpoint, $count + 1);
            }
            throw new RuntimeException($e->getMessage());
        }
    }

    //баланс пользователя
    public function getBalance()
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey
            ];

            $result = $this->sendProxyRequest(http_build_query($requestParam), 'GET', __FUNCTION__);

            $result = json_decode($result, true);
            $this->checkError($result, $this->apiKey);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера');
        }
    }

    //список стран и операторов
    public function getCountryList()
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey
            ];

            $result = $this->sendProxyRequest(http_build_query($requestParam), 'GET', __FUNCTION__);

            $result = json_decode($result, true);
            $this->checkError($result, $this->apiKey);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении списка доступных стран.');
        }
    }

//    //метод на API v0!!!!
//    public function getPrices($country)
//    {
////        try {
//            $requestParam = [
//                'api_key' => $this->apiKey,
//                'action' => __FUNCTION__,
//                'country' => $country,
//            ];
//
//            $client = new Client(['base_uri' => 'https://vak-sms.ru/stubs/handler_api.php']);
//            $response = $client->get('?' . http_build_query($requestParam));
//
//            $result = $response->getBody()->getContents();
//
//            $result = json_decode($result, true);
//        $this->checkError($result, $this->apiKey);
//            return $result;
////        } catch (\RuntimeException $r) {
////            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
////            throw new \RuntimeException('Ошибка в получении данных провайдера');
////        }
//    }

    //количество всех доступных номеров списком
    public function getCountNumbersList($country)
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey,
                'country' => $country,
            ];

            $result = $this->sendProxyRequest(http_build_query($requestParam), 'GET', __FUNCTION__);

            $result = json_decode($result, true);
            $this->checkError($result, $this->apiKey);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении списка доступных номеров.');
        }
    }

    //количетсов доступных номеров для сервиса
    public function getCountNumber($service, $country, $price = '')
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey,
                'service' => $service,
                'country' => $country,
                'price' => $price,
            ];

            $result = $this->sendProxyRequest(http_build_query($requestParam), 'GET', __FUNCTION__);

            $result = json_decode($result, true);
            $this->checkError($result, $this->apiKey);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении номера активации.');
        }
    }

    //получение номера, пока без rent и мультиактивации (после регистрации приложения добавить $softId)
    public function getNumber($service, $country, $softId = 76)
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey,
                'service' => $service,
                'country' => $country,
                'softId' => $softId, //номер софта
            ];

            $result = $this->sendProxyRequest(http_build_query($requestParam), 'GET', __FUNCTION__);

            $result = json_decode($result, true);
            $this->checkError($result, $this->apiKey);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Нет доступных номеров, попробуйте позже или воспользуйтесь другой страной.');
        }
    }

//    //продление номера, хз пока как юзать
//    public function prolongNumber($service, $tel)
//    {
//        try {
//            $requestParam = [
//                'apiKey' => $this->apiKey,
//                'service' => $service,
//                'tel' => $tel,//Номер телефона на который ранее был получен код из смс
//            ];
//
//            $client = new Client(['base_uri' => $this->url]);
//            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));
//
//            $result = $response->getBody()->getContents();
//            $result = json_decode($result, true);
//            $this->checkError($result, $this->apiKey);
//            return $result;
//        } catch (\RuntimeException $r) {
//            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
//            throw new \RuntimeException('Ошибка в получении данных провайдера');
//        }
//    }

    /**
     * Изменение статуса
     * status=send - Еще смс
     * {"status": "ready"}
     *
     * status=end - отмена номера
     * {"status": "smsReceived"}  # на данный номер уже получен код подтверждения, отмена невозможна.
     * {"status": "waitSMS"}  # на данные номер уже отправлено смс, отмена невозможна. Ожидайте код.
     * {"status": "update"}  # статус обновлен.
     *
     * status=bad - номер уже использован, забанен
     * {"status": "update"}  # статус успешно обновлен
     * {"status": "waitSMS"}  # статус не может быть обновлен, т.к сервис ожидает повторную смс
     *
     * @param $status
     * @param $idNum
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function setStatus($idNum, $status)
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey,
                'status' => $status,//Статус операции.
                'idNum' => $idNum,//ID операции
            ];

            $result = $this->sendProxyRequest(http_build_query($requestParam), 'GET', __FUNCTION__);

            $result = json_decode($result, true);
            $this->checkError($result, $this->apiKey);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера.');
        }
    }

    /**
     * all = false
     * {"smsCode": null}  # сервис ожидает СМС
     * {"smsCode": "CODE"}  # код получен, в переменной "CODE" содержится код подтверждения активации, type=str
     *
     * all = true
     * {"smsCode": ["CODE1", "CODE2"]}  # Список полученых кодов, type=list(str)
     *
     * @param $status
     * @param $idNum
     * @param bool $all
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSmsCode($idNum, bool $all = true)
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey,
                'idNum' => $idNum,//ID операции
                'all' => $all, //Параметр указывает необходимость получить весь список полученных кодов
            ];

            $result = $this->sendProxyRequest(http_build_query($requestParam), 'GET', __FUNCTION__);

            $result = json_decode($result, true);
            $this->checkError($result, $this->apiKey);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера');
        }
    }

    /**
     * @param $result
     * @return void
     * @throws RequestError
     */
    public function checkError($result, $api_key)
    {
        if (isset($result['error'])) {
            $responsError = new ErrorCodes($result['error'], $api_key);
            $check = $responsError->checkExist($result['error']);
            if ($check) {
                throw new RequestError($result['error'], $api_key);
            }
        }
    }
}
