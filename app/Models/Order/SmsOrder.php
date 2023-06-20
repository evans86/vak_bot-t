<?php

namespace App\Models\Order;

use App\Models\Activate\SmsCountry;
use App\Models\User\SmsUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsOrder extends Model
{
//    const ACCESS_RETRY_GET = 1; //Готовность номера подтверждена
//    const ACCESS_READY = 3; //Ожидание нового смс
//    const STATUS_WAIT_CODE = 4; //Ожидание первой смс
//    const STATUS_WAIT_RETRY = 5; //Ожидание уточнения кода
//    const ACCESS_ACTIVATION = 6; //Сервис успешно активирован
//    const STATUS_OK = 7; //Статус ОК
//    const ACCESS_CANCEL = 8; //Отмена активации
//    const STATUS_CANCEL = 9; //Активация/аренда отменена
//    const STATUS_FINISH = 10; //Активация/аренда успешно завершена

    const ACCESS_SEND = 'send'; //Еще смс
    const ACCESS_END = 'end'; //Отмена номера
    const STATUS_WAIT_CODE = 'waitCode'; //Ожидание первой смс
    const STATUS_WAIT_RETRY = 'secondWaitCode'; //Ожидание уточнения кода
    const STATUS_CANCEL = 'cancel'; //Активация/аренда отменена
    const STATUS_FINISH = 'finish'; //Активация/аренда успешно завершена
    const STATUS_READY = 'ready'; //номер готов при отправке ACCESS_SEND
    const STATUS_RECEIVED = 'smsReceived'; //на данный номер уже получен код подтверждения, отмена невозможна. при отправке ACCESS_END
    const STATUS_WAIT_SMS = 'waitSMS'; //на данные номер уже отправлено смс, отмена невозможна. Ожидайте код. при отправке ACCESS_END
    const STATUS_UPDATE = 'update'; //статус обновлен.



    use HasFactory;

    protected $guarded = false;
    protected $table = 'order';

    public function user()
    {
        return $this->hasOne(SmsUser::class, 'id', 'user_id');
    }

    public function country()
    {
        return $this->hasOne(SmsCountry::class, 'id', 'country_id');
    }
}
