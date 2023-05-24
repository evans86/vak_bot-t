<?php

namespace App\Services\External;

class ErrorCodes extends RequestError
{
    public function checkExist($errorCode)
    {
        return array_key_exists($errorCode, $this->errorCodes);
    }
}
