<?php

namespace App\Dto;

class BotDto
{
    public int    $id;
    public string $public_key;
    public string $private_key;
    public int    $bot_id;
    public string $api_key;
    public int    $category_id;
    public int    $percent;
    public int    $version;
    public string $resource_link;

    public function getArray(): array
    {
        return [
            'public_key' => $this->public_key,
            'private_key' => $this->private_key,
            'bot_id' => $this->bot_id,
            'api_key' => $this->api_key,
            'category_id' => $this->category_id,
            'percent' => $this->percent,
            'version' => $this->version,
            'resource_link' => $this->resource_link,
         ];
    }




}