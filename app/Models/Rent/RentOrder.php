<?php

namespace App\Models\Rent;

use App\Models\Activate\SmsCountry;
use App\Models\User\SmsUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentOrder extends Model
{
    const ACCESS_FINISH = 1; //успешно завершить
    const ACCESS_CANCEL = 2; //отменить
    const STATUS_CANCEL = 9; //Активация/аренда отменена
    const STATUS_FINISH = 10; //Активация/аренда успешно завершена
    const STATUS_WAIT_CODE = 4; //Ожидание первой смс

    use HasFactory;

    protected $guarded = false;
    protected $table = 'rent_order';

    public function user()
    {
        return $this->hasOne(SmsUser::class, 'id', 'user_id');
    }

    public function country()
    {
        return $this->hasOne(SmsCountry::class, 'id', 'country_id');
    }
}
