<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'update_id',
    'update_type',
    'message_text',
    'telegram_user_id',
    'chat_id',
    'callback_query_id',
    'callback_token_hash',
    'telegram_message_id',
    'link_token_hash',
    'processed_at',
])]
#[Hidden(['link_token_hash', 'callback_token_hash', 'message_text'])]
class TelegramUpdate extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
