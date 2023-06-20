<?php

namespace App\Services\External;

class RequestError extends \Exception
{
    private $responseCode;

    public function __construct($errorCode)
    {
        $this->responseCode = $errorCode;
        $message = "{$this->errorCodes[$errorCode]}";
        parent::__construct($message);
    }

    protected $errorCodes = array(
        'apiKeyNotFound' => 'Неверный API ключ',
        'noService' => 'Данный сервис не поддерживается, свяжитесь с администрацией сайта.',
        'noNumber' => 'Нет номеров, попробуйте позже.',
        'noMoney' => 'Недостаточно средств, пополните баланс.',
        'noCountry' => 'Запрашиваемая страна отсутствует',
        'noOperator' => 'Оператор не найден для запрашиваемой страны',
        'badStatus' => 'Не верный статус',
        'idNumNotFound' => 'Не верный ID операции',
        'badService' => 'Не верный код сайта, сервиса, соц. сети.',
        'badData' => 'Отправлены неверные данные.'
    );

    public function getResponseCode()
    {
        return $this->errorCodes[$this->responseCode];
    }
}
