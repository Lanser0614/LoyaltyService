<?php

namespace Modules\Integration\Inbox\Services;

use Modules\Integration\Inbox\Models\EventInbox;
use Illuminate\Database\QueryException;

class EventInboxService
{
    public function remember(string $eventId, string $eventType, array $payload): ?EventInbox
    {
        try {
            return EventInbox::query()->create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'payload' => $payload,
                'status' => 'pending',
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    public function markProcessed(EventInbox $event): void
    {
        $event->update([
            'status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markFailed(EventInbox $event, string $message): void
    {
        $event->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
