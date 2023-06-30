<?php

namespace App\Services\External;

use GuzzleHttp\Client;

class VakApi
{
    private $url;

    private $apiKey;

    public function __construct($apiKey, $url)
    {
        $this->apiKey = $apiKey;
        $this->url = $url;
    }

    //баланс пользователя
    public function getBalance()
    {
        $requestParam = [
            'apiKey' => $this->apiKey
        ];

        $client = new Client(['base_uri' => $this->url]);
        $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        $result = json_decode($result, true);
        $this->checkError($result);
        return $result;
    }

    //список стран и операторов
    public function getCountryOperatorList()
    {
        $requestParam = [
            'apiKey' => $this->apiKey
        ];

        $client = new Client(['base_uri' => $this->url]);
        $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        $result = json_decode($result, true);
        $this->checkError($result);
        return $result;
    }

    //количество всех доступных номеров списком
    public function getCountNumberList($country)
    {
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
    }

    //количетсов доступных номеров для сервиса
    public function getCountNumber($service, $country, $price = '')
    {
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
    }

    //получение номера, пока без rent и мультиактивации (после регистрации приложения добавить $softId)
    public function getNumber($service, $country, $softId = 76)
    {
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
    }

    //продление номера, хз пока как юзать
    public function prolongNumber($service, $tel)
    {
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
