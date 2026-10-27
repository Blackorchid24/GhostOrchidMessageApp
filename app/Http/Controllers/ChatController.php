<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Events\MessageDeleted;
class ChatController extends BaseController

{
    
public function index()
{
    $user = auth()->user();

  
$conversations = $user->conversations()
    ->with([
        'latestMessage.user',
        'participants',
    ])
    ->orderByDesc('conversations.last_message_at')
    ->orderByDesc('conversations.id')
    ->get()
    ->unique(function ($conversation) use ($user) {
        if ($conversation->type !== 'private') {
            return 'conversation:' . $conversation->id;
        }

        $other = $conversation->participants->first(
            fn ($participant) =>
                (int) $participant->id !== (int) $user->id
        );

        if (!$other) {
            return 'conversation:' . $conversation->id;
        }

        return 'private:' . $other->id;
    })
    ->values();


    $users = User::query()
        ->where('id', '!=', $user->id)
        ->where('status', 'active')
        ->orderBy('name')
        ->get();

    return view('chat.index', compact(
        'conversations',
        'users'
    ));
}

    public function show(Conversation $conversation)
    {
        $this->authorizeParticipant($conversation);

        $currentUser = auth()->user();

        $conversation->load('participants');

        $isGroup = $conversation->type === 'group';

        $isCreator = (int) $conversation->created_by
            === (int) $currentUser->id;

        $other = null;

        if (!$isGroup) {
            $other = $conversation->participants
                ->first(
                    fn ($participant) =>
                        (int) $participant->id !== (int) $currentUser->id
                );
        }

        $chatTitle = $isGroup
            ? ($conversation->name ?: 'Group Conversation')
            : ($other?->name ?? 'Private Conversation');

        $isOnline = false;

        if (!$isGroup && $other) {
            $lastSeen = $other->last_seen_at;

            $isOnline = $lastSeen !== null
                && $lastSeen->gt(now()->subMinutes(5));
        }

   $messages = Message::query()
    ->where('conversation_id', $conversation->id)
    ->with(['user', 'replyTo.user'])
    ->orderByDesc('id')
    ->limit(300)
    ->get()
    ->reverse()
    ->values();

$messages->each(function (Message $message) {
    $mediaUrl = $message->media_path
        ? Storage::disk('public')->url($message->media_path)
        : null;

    $message->setAttribute('media_url', $mediaUrl);
    $message->setAttribute('attachment_url', $mediaUrl);
});

        $users = User::query()
            ->where('status', 'active')
            ->where('id', '!=', $currentUser->id)
            ->orderBy('name')
            ->limit(100)
            ->get();

        $currentUser->conversations()
            ->updateExistingPivot($conversation->id, [
                'last_read_at' => now(),
            ]);

        return view('chat.show', compact(
            'conversation',
            'messages',
            'users',
            'currentUser',
            'isGroup',
            'isCreator',
            'other',
            'chatTitle',
            'isOnline'
        ));
    }

   

public function startPrivate(Request $request)
{
    $data = $request->validate([
        'user_id' => [
            'required',
            'integer',
            'exists:users,id',
        ],
    ]);

    $currentId = (int) $request->user()->id;
    $otherId = (int) $data['user_id'];

    abort_if(
        $currentId === $otherId,
        422,
        'You cannot start a conversation with yourself.'
    );

    $conversation = DB::transaction(function () use (
        $currentId,
        $otherId
    ) {
        $userIds = [$currentId, $otherId];
        sort($userIds);

        User::query()
            ->whereIn('id', $userIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $existingId = DB::table('conversations as c')
            ->join(
                'conversation_participants as cp1',
                'cp1.conversation_id',
                '=',
                'c.id'
            )
            ->join(
                'conversation_participants as cp2',
                'cp2.conversation_id',
                '=',
                'c.id'
            )
            ->where('c.type', 'private')
            ->where('cp1.user_id', $currentId)
            ->where('cp2.user_id', $otherId)
            ->whereRaw(
                '(SELECT COUNT(*) FROM conversation_participants cp
                  WHERE cp.conversation_id = c.id) = 2'
            )
            ->orderByRaw(
                'CASE WHEN c.last_message_at IS NULL THEN 1 ELSE 0 END'
            )
            ->orderByDesc('c.last_message_at')
            ->orderBy('c.id')
            ->value('c.id');

        if ($existingId) {
            return Conversation::findOrFail($existingId);
        }

        $conversation = Conversation::create([
            'type' => 'private',
            'created_by' => $currentId,
        ]);

        $conversation->participants()->attach([
            $currentId => [
                'role' => 'member',
                'joined_at' => now(),
            ],
            $otherId => [
                'role' => 'member',
                'joined_at' => now(),
            ],
        ]);

        return $conversation;
    }, 3);

    return redirect()->route('chat.show', $conversation);
}

    public function createGroup(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'members' => [
                'nullable',
                'array',
                'max:50',
            ],
            'members.*' => [
                'integer',
                'distinct',
                'exists:users,id',
            ],
        ]);

        $currentId = (int) auth()->id();

        $memberIds = collect($data['members'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $currentId)
            ->unique()
            ->values();

        $conversation = DB::transaction(
            function () use ($data, $memberIds, $currentId) {
                $conversation = Conversation::create([
                    'name' => trim($data['name']),
                    'type' => 'group',
                    'created_by' => $currentId,
                ]);

                $members = [
                    $currentId => [
                        'role' => 'admin',
                        'joined_at' => now(),
                    ],
                ];

                foreach ($memberIds as $memberId) {
                    $members[$memberId] = [
                        'role' => 'member',
                        'joined_at' => now(),
                    ];
                }

                $conversation->participants()->attach($members);

                return $conversation;
            }
        );

        return redirect()->route('chat.show', $conversation);
    }


public function sendMessage(Request $request, Conversation $conversation)
{
    $this->authorizeParticipant($conversation);

    $data = $request->validate([
        'body' => ['nullable', 'string', 'max:5000'],
        'type' => ['required', 'in:text,image,attachment,voice,location'],
        'reply_to_message_id' => [
    'nullable',
    'integer',
    'exists:messages,id',
],

        'image' => [
            'nullable',
            'required_if:type,image',
            'image',
            'mimes:pdf,doc,docx,xls,xlsx,txt,zip,rar,ppt,pptx,png,jpg,jpeg,gif,webp',
            'max:51200',
        ],

        'attachment' => [
            'nullable',
            'required_if:type,attachment',
            'file',
            'mimes:pdf,doc,docx,xls,xlsx,txt,zip,rar,ppt,pptx,png,jpg,jpeg,gif,webp',
            'max:51200',
        ],

        'voice' => [
            'nullable',
            'required_if:type,voice',
            'file',
            'mimes:webm,mp3,wav,ogg,m4a,mp4',
            'max:51200',
        ],

        'latitude' => [
            'nullable',
            'required_if:type,location',
            'numeric',
            'between:-90,90',
        ],

        'longitude' => [
            'nullable',
            'required_if:type,location',
            'numeric',
            'between:-180,180',
        ],
    ]);

    $body = trim($data['body'] ?? '');
    $type = $data['type'];

    if ($type === 'text' && $body === '') {
        return response()->json([
            'message' => 'Message cannot be empty.',
        ], 422);
    }

    if (
        $type === 'location'
        && (!isset($data['latitude']) || !isset($data['longitude']))
    ) {
        return response()->json([
            'message' => 'Location coordinates are required.',
        ], 422);
    }

    $file = match ($type) {
        'image' => $request->file('image'),
        'attachment' => $request->file('attachment'),
        'voice' => $request->file('voice'),
        default => null,
    };

    if (in_array($type, ['image', 'attachment', 'voice'], true) && !$file) {
        return response()->json([
            'message' => 'Please select a file to send.',
        ], 422);
    }


    $replyToMessageId = $data['reply_to_message_id'] ?? null;

if ($replyToMessageId !== null) {
    $replyTarget = Message::query()
        ->where('id', $replyToMessageId)
        ->where('conversation_id', $conversation->id)
        ->first();

    if (!$replyTarget) {
        return response()->json([
            'message' => 'The message you are replying to was not found in this conversation.',
        ], 422);
    }
}

    $mediaPath = null;

    try {
        $fileName = $file?->getClientOriginalName();
        $mediaType = $file?->getMimeType();

        if ($file) {
            $mediaPath = $file->store('chat-media', 'public');

            if (!$mediaPath) {
                throw new \RuntimeException(
                    'Failed to store the uploaded chat file.'
                );
            }
        }

        $message = DB::transaction(function () use (
            $conversation,
            $body,
            $type,
            $mediaPath,
            $fileName,
            $mediaType,
            $data,
            $replyToMessageId
        ) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'reply_to_message_id' => $replyToMessageId,
                'body' => $body,
                'type' => $type,
                'media_path' => $mediaPath,
                'file_name' => $fileName,
                'media_type' => $mediaType,
                'media_name' => $fileName,
                
                'latitude' => $type === 'location'
                    ? $data['latitude']
                    : null,
                'longitude' => $type === 'location'
                    ? $data['longitude']
                    : null,
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);

            auth()->user()->forceFill([
                'last_seen_at' => now(),
            ])->save();

            return $message->load(['user', 'replyTo.user']);
        });

        $mediaUrl = $message->media_path
            ? Storage::disk('public')->url($message->media_path)
            : null;

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                'GhostOrchid chat broadcast failed.',
                [
                    'message_id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'user_id' => $message->user_id,
                    'body' => $message->body,
                    'type' => $message->type,
                    'media_path' => $message->media_path,
                    'media_url' => $mediaUrl,
                    'attachment_url' => $mediaUrl,
                    'file_url' => $mediaUrl,
                    'file_name' => $message->file_name,
                    'media_name' => $message->media_name,
                    'media_type' => $message->media_type,
                    'latitude' => $message->latitude,
                    'longitude' => $message->longitude,
                    'created_at' => $message->created_at
                        ? $message->created_at
                            ->timezone('Asia/Manila')
                            ->toIso8601String()
                        : null,
                    'user' => [
                        'id' => $message->user?->id,
                        'name' => $message->user?->name,
                        'avatar_url' => $message->user?->avatar_url,
                    ],
                    'reply_to_message_id' => $message->reply_to_message_id,
                    'reply_to' => $message->replyTo ? [
                        'id' => $message->replyTo->id,
                        'user_name' => $message->replyTo->user?->name ?? 'Unknown user',
                        'body' => $message->replyTo->body,
                        'type' => $message->replyTo->type,
                        'file_name' => $message->replyTo->file_name,
                    ] : null,
                ],
            ], 201);
    } catch (\Throwable $e) {
        if ($mediaPath) {
            Storage::disk('public')->delete($mediaPath);
        }

        \Illuminate\Support\Facades\Log::error(
            'GhostOrchid message save failed.',
            [
                'conversation_id' => $conversation->id,
                'user_id' => auth()->id(),
                'type' => $type,
                'error' => $e->getMessage(),
            ]
        );

        return response()->json([
            'message' => 'The message could not be saved. Check the Laravel log.',
        ], 500);
    }
}

public function updateGroupAvatar(Request $request, \App\Models\Conversation $conversation)
{
    $user = $request->user();

    abort_unless($conversation->type === 'group', 404);
    abort_unless((int) $conversation->created_by === (int) $user->id, 403);
    abort_unless(
        $conversation->participants()->where('users.id', $user->id)->exists(),
        403
    );

    $request->validate([
        'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
    ]);

    $path = $request->file('avatar')->store('group-avatars', 'public');

    if (! $path) {
        return back()->withErrors([
            'avatar' => 'The group profile picture could not be uploaded.',
        ]);
    }

    $oldPath = $conversation->avatar_path;

    $conversation->avatar_path = $path;
    $conversation->save();

    if ($oldPath) {
        Storage::disk('public')->delete($oldPath);
    }

    return back();
}
public function groupProfile(\App\Models\Conversation $conversation)
{
    $currentUser = auth()->user();

    abort_unless($currentUser, 401);

    abort_unless(
        $conversation->type === 'group',
        404
    );

    abort_unless(
        $conversation->participants()
            ->where('users.id', $currentUser->id)
            ->exists(),
        403
    );

    $conversation->load('participants');

    $users = \App\Models\User::query()
        ->orderBy('name')
        ->get();

    return view('profile.groupProfile', [
        'conversation' => $conversation,
        'currentUser' => $currentUser,
        'users' => $users,
    ]);
}

public function leaveGroup(Request $request, \App\Models\Conversation $conversation)
{
    $user = $request->user();

    abort_unless($conversation->type === 'group', 404);

    $isParticipant = $conversation->participants()
        ->where('users.id', $user->id)
        ->exists();

    abort_unless($isParticipant, 403);

    $remainingMembers = $conversation->participants()
        ->where('users.id', '!=', $user->id)
        ->get();

    if ((int) $conversation->created_by === (int) $user->id && $remainingMembers->isNotEmpty()) {
        $newCreator = $remainingMembers->first();

        $conversation->created_by = $newCreator->id;
        $conversation->save();
    }

    $conversation->participants()->detach($user->id);

    if ($remainingMembers->isEmpty()) {
        if ($conversation->avatar_path) {
            Storage::disk('public')->delete($conversation->avatar_path);
        }

        $conversation->messages()->delete();
        $conversation->delete();
    }

    return redirect()
        ->route('chat.index')
        ->with('success', 'You have left the group chat.');
}


public function updateMessage(
    Request $request,
    Conversation $conversation,
    Message $message
) {
    $this->authorizeParticipant($conversation);

    abort_unless(
        (int) $message->conversation_id === (int) $conversation->id,
        404
    );

    abort_unless(
        (int) $message->user_id === (int) $request->user()->id,
        403,
        'You can only edit your own messages.'
    );

    abort_unless(
        ($message->type ?? 'text') === 'text',
        422,
        'Only text messages can be edited.'
    );

    $data = $request->validate([
        'body' => ['required', 'string', 'max:5000'],
    ]);

    $body = trim($data['body']);

    abort_if($body === '', 422, 'Message cannot be empty.');

    $message->update(['body' => $body]);

    return response()->json([
        'message' => [
            'id' => $message->id,
            'body' => $message->body,
            'updated_at' => $message->updated_at?->toISOString(),
        ],
    ]);
}


public function deleteMessage(
    Request $request,
    Conversation $conversation,
    Message $message
) {
    $this->authorizeParticipant($conversation);

    abort_unless(
        (int) $message->conversation_id === (int) $conversation->id,
        404
    );

    abort_unless(
        (int) $message->user_id === (int) $request->user()->id,
        403,
        'You can only delete your own messages.'
    );

    $messageId = (int) $message->id;
    $conversationId = (int) $message->conversation_id;
    $mediaPath = $message->media_path;

    DB::transaction(function () use ($message) {
        $message->delete();
    });

    if ($mediaPath) {
        try {
            $deleted = Storage::disk('public')->delete($mediaPath);

            if (!$deleted) {
                \Illuminate\Support\Facades\Log::warning(
                    'Chat media file could not be deleted.',
                    [
                        'message_id' => $messageId,
                        'media_path' => $mediaPath,
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error(
                'Failed to delete chat media.',
                [
                    'message_id' => $messageId,
                    'media_path' => $mediaPath,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    event(new MessageDeleted($conversationId, $messageId));

    return response()->json([
        'success' => true,
        'id' => $messageId,
    ]);
}


    public function addMember(
        Request $request,
        Conversation $conversation
    ) {
        $this->authorizeParticipant($conversation);

        abort_unless(
            $conversation->type === 'group',
            422,
            'Members can only be added to groups.'
        );

        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ]);

        $currentId = (int) auth()->id();

        $this->authorizeGroupManager($conversation, $currentId);

        $userId = (int) $data['user_id'];

        $alreadyMember = $conversation->participants()
            ->where('users.id', $userId)
            ->exists();

        abort_if(
            $userId === $currentId,
            422,
            'You are already a member of this group.'
        );

        if (!$alreadyMember) {
            $conversation->participants()->attach($userId, [
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        return back();
    }

    public function removeMember(
        Conversation $conversation,
        User $user
    ) {
        $this->authorizeParticipant($conversation);

        abort_unless(
            $conversation->type === 'group',
            422,
            'Members can only be removed from groups.'
        );

        $currentId = (int) auth()->id();

        $this->authorizeGroupManager($conversation, $currentId);

        abort_if(
            (int) $user->id === (int) $conversation->created_by,
            422,
            'The group creator cannot be removed.'
        );

        $isMember = $conversation->participants()
            ->where('users.id', $user->id)
            ->exists();

        abort_unless(
            $isMember,
            404,
            'This user is not a member of the group.'
        );

        $conversation->participants()->detach($user->id);

        return back();
    }

    public function updateConversation(
        Request $request,
        Conversation $conversation
    ) {
        $this->authorizeParticipant($conversation);

        $currentId = (int) $request->user()->id;

        abort_unless(
            $conversation->type === 'group'
                && (int) $conversation->created_by === $currentId,
            403,
            'Only the group creator can edit this conversation.'
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:1',
                'max:100',
            ],
        ]);

        $name = trim($validated['name']);

        abort_if(
            $name === '',
            422,
            'The group name cannot be empty.'
        );

        $conversation->update([
            'name' => $name,
        ]);

        return redirect()
            ->route('chat.show', $conversation)
            ->with(
                'success',
                'Conversation name updated successfully.'
            );
    }

    public function destroyConversation(
        Request $request,
        Conversation $conversation
    ) {
        $user = $request->user();

        $this->authorizeParticipant($conversation);

        DB::transaction(function () use ($conversation, $user) {
            $conversation->participants()->detach($user->id);

            if (!$conversation->participants()->exists()) {
                $conversation->messages()->delete();
                $conversation->delete();
            }
        });

        return redirect()
            ->route('chat.index')
            ->with(
                'success',
            );
    }

    private function authorizeParticipant(
        Conversation $conversation
    ): void {
        abort_unless(
            $conversation->participants()
                ->where('users.id', auth()->id())
                ->exists(),
            403,
            'You are not a member of this conversation.'
        );
    }

    private function authorizeGroupManager(
        Conversation $conversation,
        int $userId
    ): void {
        $isAdmin = $conversation->participants()
            ->where('users.id', $userId)
            ->wherePivot('role', 'admin')
            ->exists();

        abort_unless(
            $isAdmin
                || (int) $conversation->created_by === $userId,
            403,
            'Only group administrators can manage members.'
        );
    }
}
