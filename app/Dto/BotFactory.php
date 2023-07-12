<?php

namespace App\Dto;

use App\Models\Bot\SmsBot;

class BotFactory
{
    public static function fromEntity(SmsBot $bot): BotDto
    {
        $dto = new BotDto();
        $dto->id = $bot->id;
        $dto->public_key = $bot->public_key;
        $dto->private_key = $bot->private_key;
        $dto->bot_id = $bot->bot_id;
        $dto->api_key = $bot->api_key;
        $dto->category_id = $bot->category_id;
        $dto->percent = $bot->percent;
        $dto->version = $bot->version;
        $dto->color = $bot->color;
        $dto->black = $bot->black;
        $dto->resource_link = $bot->resource_link;
        return $dto;
    }
}
