<?php

namespace App\Helpers;

class OrdersHelper
{
    public static function statusList(): array
    {
        return [
            'waitCode' => 'Ожидание смс',
            'secondWaitCode' => 'Ожидание уточнения',
            'cancel' => 'Активация отменена',
            'finish' => 'Активация успешно активирована',
        ];
    }

    public static function statusLabel($status): string
    {
        switch ($status) {
            case 'waitCode':
                $class = 'badge bg-info';
                break;
            case 'secondWaitCode':
                $class = 'badge bg-warning';
                break;
            case 'finish':
                $class = 'badge bg-success';
                break;
            case 'cancel':
                $class = 'badge bg-danger';
                break;
            default:
                $class = 'badge bg-default';
        }


        return '<span class="' . $class . '">' . \Arr::get(self::statusList(), $status) . '</span>';
    }
}
