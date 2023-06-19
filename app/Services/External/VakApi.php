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
        return json_decode($result, true);
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
        return json_decode($result, true);
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
        return json_decode($result, true);
    }

    //количетсов доступных номеров для сервиса
    public function getCountNumber($service, $country)
    {
        $requestParam = [
            'apiKey' => $this->apiKey,
            'service' => $service,
            'country' => $country,
        ];

        $client = new Client(['base_uri' => $this->url]);
        $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    //получение номера, пока без rent и мультиактивации (после регистрации приложения добавить $softId)
    public function getNumber($service, $country)
    {
        $requestParam = [
            'apiKey' => $this->apiKey,
            'service' => $service,
            'country' => $country,
        ];

        $client = new Client(['base_uri' => $this->url]);
        $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
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
        return json_decode($result, true);
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
    public function setStatus($status, $idNum)
    {
        $requestParam = [
            'apiKey' => $this->apiKey,
            'status' => $status,//Статус операции.
            'idNum' => $idNum,//ID операции
        ];

        $client = new Client(['base_uri' => $this->url]);
        $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
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
    public function getSmsCode($idNum, $all)
    {
        $requestParam = [
            'apiKey' => $this->apiKey,
            'idNum' => $idNum,//ID операции
            'all' => $all, //Параметр указывает необходимость получить весь список полученных кодов
        ];

        $client = new Client(['base_uri' => $this->url]);
        $response = $client->get(__FUNCTION__ . '?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

//{"error": "apiKeyNotFound"}  # Неверный API ключ.
//{"error": "noService"}  # Данный сервис не поддерживается, свяжитесь с администрацией сайта.
//{"error": "noNumber"}  # Нет номеров, попробуйте позже.
//{"error": "noMoney"}  # Недостаточно средств, пополните баланс.
//{"error": "noCountry"}  # Запрашиваемая страна отсутствует.
//{"error": "noOperator"}  # Оператор не найден для запрашиваемой страны.
//{"error": "badStatus"}  # Не верный статус.
//{"error": "idNumNotFound"}  # Не верный ID операции.
//{"error": "badService"}  # Не верный код сайта, сервиса, соц. сети.
//{"error": "badData"}  # Отправлены неверные данные.

}
