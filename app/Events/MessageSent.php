<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing([
            'user',
            'replyTo.user',
        ]);
    }

    public function broadcastOn(): array
    {
        $conversation = Conversation::query()
            ->with('participants')
            ->find($this->message->conversation_id);

        $channels = [
            new PrivateChannel(
                'conversation.' . $this->message->conversation_id
            ),
        ];

        if ($conversation) {
            foreach ($conversation->participants as $participant) {
                $channels[] = new PrivateChannel(
                    'user.' . $participant->id . '.inbox'
                );
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing([
            'user',
            'replyTo.user',
        ]);

        $mediaUrl = $message->media_path
            ? Storage::disk('public')->url($message->media_path)
            : null;

        $replyTo = $message->replyTo;

        return [
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'user_id' => $message->user_id,
                'body' => $message->body,
                'type' => $message->type,
                'reply_to_message_id' => $message->reply_to_message_id,

                'reply_to' => $replyTo ? [
                    'id' => $replyTo->id,
                    'user_name' => $replyTo->user?->name ?? 'Unknown user',
                    'body' => $replyTo->body,
                    'type' => $replyTo->type,
                    'file_name' => $replyTo->file_name,
                ] : null,

                'created_at' => $message->created_at
                    ? $message->created_at
                        ->timezone('Asia/Manila')
                        ->toIso8601String()
                    : null,

                'media_url' => $mediaUrl,
                'attachment_url' => $mediaUrl,
                'file_url' => $mediaUrl,
                'file_name' => $message->file_name,
                'media_name' => $message->media_name,
                'media_type' => $message->media_type,
                'latitude' => $message->latitude,
                'longitude' => $message->longitude,

                'user' => [
                    'id' => $message->user?->id,
                    'name' => $message->user?->name,
                    'avatar_url' => $message->user?->avatar_url,
                ],
            ],
        ];
    }
}
