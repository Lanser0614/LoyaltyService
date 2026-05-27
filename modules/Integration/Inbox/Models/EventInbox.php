<?php

namespace Modules\Integration\Inbox\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventInbox extends Model
{
    use HasFactory;

    protected $table = 'event_inbox';

    protected $fillable = [
        'event_id',
        'event_type',
        'payload',
        'status',
        'processed_at',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}
