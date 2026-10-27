<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
   
public function store(Request $request)
{
    $validated = $request->validate([
        'conversation_id' => ['required', 'integer'],
        'body' => ['nullable', 'string', 'max:10000'],

        'image' => [
            'nullable',
            'file',
            'max:51200',
            'mimes:jpg,jpeg,png,gif,webp',
        ],

        'attachment' => [
            'nullable',
            'file',
            'max:51200',
            'mimes:pdf,doc,docx,xls,xlsx,txt,zip,rar,ppt,pptx,jpg,jpeg,png,gif,webp',
        ],

        'media' => [
            'nullable',
            'file',
            'max:51200',
            'mimes:jpg,jpeg,png,gif,webp,mp3,wav,ogg,m4a,mp4,webm,pdf,doc,docx,xls,xlsx,txt,zip,rar,ppt,pptx',
        ],

        'voice' => [
            'nullable',
            'file',
            'max:51200',
            'mimes:webm,ogg,wav,mp3,m4a,mp4',
        ],

        'type' => ['nullable', 'string', 'in:text,image,attachment,voice,location'],
        'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        'longitude' => ['nullable', 'numeric', 'between:-180,180'],
    ]);

    $upload = $request->file('voice')
        ?? $request->file('image')
        ?? $request->file('attachment')
        ?? $request->file('media');

    $body = trim($validated['body'] ?? '');

    if ($body === '' && !$upload) {
        throw ValidationException::withMessages([
            'body' => 'Enter a message or attach a file.',
        ]);
    }

    $message = new Message();

    $message->conversation_id = $validated['conversation_id'];
    $message->sender_id = auth()->id();
    $message->body = $body;

    if ($upload) {
        $message->media_path = $upload->store('chat-media', 'public');
        $message->media_type = $upload->getMimeType();
        $message->media_name = $upload->getClientOriginalName();
    }

    $message->save();

    try {
        broadcast(new MessageSent($message->load('user')))->toOthers();
    } catch (\Throwable $e) {
        Log::error('GhostOrchid broadcast failed', [
            'message_id' => $message->id,
            'error' => $e->getMessage(),
        ]);
    }

    $mediaUrl = $message->media_path
        ? Storage::disk('public')->url($message->media_path)
        : null;

    $messageType = 'text';

    if ($message->media_type) {
        if (str_starts_with($message->media_type, 'image/')) {
            $messageType = 'image';
        } elseif (str_starts_with($message->media_type, 'audio/')) {
            $messageType = 'voice';
        } else {
            $messageType = 'attachment';
        }
    }

    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'user_id' => $message->sender_id,
                'sender_id' => $message->sender_id,
                'body' => $message->body,
                'type' => $messageType,
                'media_url' => $mediaUrl,
                'attachment_url' => $mediaUrl,
                'file_url' => $mediaUrl,
                'media_type' => $message->media_type,
                'media_name' => $message->media_name,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ]);
    }

    return redirect()->back();
}

public function downloadAttachment(\App\Models\Message $message)
{
    abort_unless(auth()->check(), 403);

    abort_unless(
        $message->user_id === auth()->id()
        || $message->conversation->users()
            ->where('users.id', auth()->id())
            ->exists(),
        403
    );

    abort_unless($message->media_path, 404);

    $disk = \Illuminate\Support\Facades\Storage::disk('public');

    abort_unless($disk->exists($message->media_path), 404);

    return $disk->download(
        $message->media_path,
        basename($message->file_name ?: $message->media_path)
    );
}
}
