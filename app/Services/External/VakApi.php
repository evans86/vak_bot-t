<?php

namespace App\Services\External;

use App\Helpers\BotLogHelpers;
use GuzzleHttp\Client;
use http\Exception\RuntimeException;

class VakApi
{
    private $url;

    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->url = 'https://vak-sms.ru/api/';
    }

    //баланс пользователя
    public function getBalance()
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey
            ];

            $client = new Client(['base_uri' => $this->url]);
            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->checkError($result);
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

            $client = new Client(['base_uri' => $this->url]);
            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->checkError($result);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера');
        }
    }

    //метод на API v0!!!!
    public function getPrices($country)
    {
//        try {
            $requestParam = [
                'api_key' => $this->apiKey,
                'action' => __FUNCTION__,
                'country' => $country,
            ];

            $client = new Client(['base_uri' => 'https://vak-sms.ru/stubs/handler_api.php']);
            $response = $client->get('?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();

            $result = json_decode($result, true);
            $this->checkError($result);
            return $result;
//        } catch (\RuntimeException $r) {
//            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
//            throw new \RuntimeException('Ошибка в получении данных провайдера');
//        }
    }

    //количество всех доступных номеров списком
    public function getCountNumbersList($country)
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey,
                'country' => $country,
            ];

            $client = new Client(['base_uri' => $this->url]);
            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->checkError($result);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера');
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

            $client = new Client(['base_uri' => $this->url]);
            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->checkError($result);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера');
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

            $client = new Client(['base_uri' => $this->url]);
            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->checkError($result);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера');
        }
    }

    //продление номера, хз пока как юзать
    public function prolongNumber($service, $tel)
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey,
                'service' => $service,
                'tel' => $tel,//Номер телефона на который ранее был получен код из смс
            ];

            $client = new Client(['base_uri' => $this->url]);
            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->checkError($result);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера');
        }
    }

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

            $client = new Client(['base_uri' => $this->url]);
            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->checkError($result);
            return $result;
        } catch (\RuntimeException $r) {
            BotLogHelpers::notifyBotLog('(🟢E ' . __FUNCTION__ . ' Vak): ' . $r->getMessage());
            throw new \RuntimeException('Ошибка в получении данных провайдера');
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
     * @param $all
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getSmsCode($idNum, $all = '')
    {
        try {
            $requestParam = [
                'apiKey' => $this->apiKey,
                'idNum' => $idNum,//ID операции
                'all' => $all, //Параметр указывает необходимость получить весь список полученных кодов
            ];

            $client = new Client(['base_uri' => $this->url]);
            $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

            $result = $response->getBody()->getContents();
            $result = json_decode($result, true);
            $this->checkError($result);
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
    public function checkError($result)
    {
        if (isset($result['error'])) {
            $responsError = new ErrorCodes($result['error']);
            $check = $responsError->checkExist($result['error']);
            if ($check) {
                throw new RequestError($result['error']);
            }
        }
    }
}
