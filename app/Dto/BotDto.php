<?php

namespace App\Dto;

class BotDto
{
    public int $id;
    public string $public_key;
    public string $private_key;
    public int $bot_id;
    public string $api_key;
    public int $category_id;
    public int $percent;
    public int $version;
    public int $color;
    public ?bool $is_saved;
    public ?string $black;
    public string $resource_link;

    public function getArray(): array
    {
        return [
            'id' => $this->id,
            'public_key' => $this->public_key,
            'private_key' => $this->private_key,
            'bot_id' => $this->bot_id,
            'api_key' => $this->api_key,
            'category_id' => $this->category_id,
            'percent' => $this->percent,
            'version' => $this->version,
            'color' => $this->color,
           // 'is_saved' => $this->is_saved,
            'black' => $this->black,
//            'resource_link' => $this->resource_link,
        ];
    }

    public function getSettings(): array
    {
        return [
            'color' => $this->color,
            'is_saved' => $this->is_saved,
        ];
    }
}
