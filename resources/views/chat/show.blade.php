@extends('layouts.app')

@php
    $chatTitle =
        $chatTitle ??
        ($isGroup ?? false ? $conversation->name ?? 'Group Conversation' : $other->name ?? 'Private Conversation');
@endphp

@section('title', $chatTitle)
@section('heading', $chatTitle)

@section('content')
    <script>
        window.ghostorchidChat = {
            conversationId: @json($conversation->id),
            currentUserId: @json($currentUser->id),
            csrf: @json(csrf_token()),
            sendUrl: @json(route('chat.messages.store', $conversation)),
            updateUrlTemplate: @json(route('chat.messages.update', ['conversation' => $conversation->id, 'message' => '__MESSAGE_ID__'])),
            deleteUrlTemplate: @json(route('chat.messages.delete', ['conversation' => $conversation->id, 'message' => '__MESSAGE_ID__']))
        };
    </script>

    <div class="chat-room">
        <section class="message-panel">
            <div class="message-panel-head">
                <div class="message-head-person">
                    @if ($isGroup)
                        <div class="mini-group-icon">
                            @if ($conversation->avatar_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($conversation->avatar_path) }}"
                                    alt="Group profile" loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <i class="fa-solid fa-users group-icon-fallback" style="display: none;"></i>
                            @else
                                <i class="fa-solid fa-users group-icon-fallback"></i>
                            @endif
                        </div>
                    @else
                        <img src="{{ $other?->avatar_url ?? ($currentUser->avatar_url ?? asset('images/default-avatar.png')) }}"
                            alt="Profile" loading="lazy">
                    @endif

                    <div>
                        <b>{{ $isGroup ? '' : '💜' }} {{ $chatTitle }}</b>
                        <small>{{ $isGroup ? 'Group conversation 🫂' : 'Direct conversation 💌' }}</small>
                    </div>
                </div>
                @if (isset($conversation) && $conversation && $conversation->type === 'group')
                    <a class="side-link" href="{{ route('chat.group-profile', ['conversation' => $conversation->id]) }}">

                        <span class="side-link-icon">
                            <i class="fa-solid fa-circle-info"></i>
                        </span>
                    </a>
                @endif
            </div>

            <div class="messages-area">
                <div class="orchid-background" aria-hidden="true">
                    <i class="fa-solid fa-spa orchid-one"></i>
                    <i class="fa-solid fa-spa orchid-two"></i>
                    <i class="fa-solid fa-spa orchid-three"></i>
                    <i class="fa-solid fa-spa orchid-four"></i>
                    <i class="fa-solid fa-spa orchid-five"></i>
                    <i class="fa-solid fa-spa orchid-six"></i>

                    <i class="fa-solid fa-ghost ghost-one"></i>
                    <i class="fa-solid fa-ghost ghost-two"></i>
                    <i class="fa-solid fa-ghost ghost-three"></i>
                    <i class="fa-solid fa-ghost ghost-four"></i>
                    <i class="fa-solid fa-ghost ghost-five"></i>
                    <i class="fa-solid fa-ghost ghost-six"></i>
                </div>

                <div class="messages-scroll" id="messagesScroll" data-conversation-id="{{ $conversation->id }}"
                    data-current-user="{{ $currentUser->id }}">
                    <div class="messages-content">
                        @forelse ($messages as $message)

                            <div class="message-row {{ (int) $message->user_id === (int) $currentUser->id ? 'mine' : '' }}"
                                data-message-id="{{ $message->id }}">

                                <img class="message-avatar"
                                    src="{{ $message->user?->avatar_url ?? asset('images/default-avatar.png') }}"
                                    alt="" loading="lazy">

                                <div class="message-content">
                                    <div class="message-meta">
                                        <b>
                                            @if ((int) $message->user_id === (int) $currentUser->id)
                                                😊 You
                                            @else
                                                {{ $message->user?->name ?? 'Unknown user' }}
                                            @endif
                                        </b>
                                        <time>{{ $message->created_at?->format('g:i A') ?? '' }}</time>
                                    </div>

                                    @php
                                        $messageType = $message->type ?? 'text';
                                        $isOwnMessage = (int) $message->user_id === (int) $currentUser->id;
                                        $isTextMessage = $messageType === 'text';

                                        $replyPreview = $isTextMessage
                                            ? (filled($message->body)
                                                ? $message->body
                                                : 'Message')
                                            : (filled($message->file_name)
                                                ? $message->file_name
                                                : match ($messageType) {
                                                    'image' => 'Photo',
                                                    'voice', 'audio' => 'Voice message',
                                                    'attachment' => 'File attachment',
                                                    'location' => 'Shared location',
                                                    default => 'Media message',
                                                });
                                    @endphp

                                    <div class="message-tools">
                                        <button type="button" class="message-menu-trigger" aria-label="Message options"
                                            aria-expanded="false" title="Message options">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>


                                        <div class="message-actions" hidden>
                                            <button type="button" class="reply-message-btn reply"
                                                data-message-id="{{ $message->id }}"
                                                data-user-name="{{ $message->user->name ?? 'Unknown user' }}"
                                                data-message-text="{{ $replyPreview }}" aria-label="Reply to message"
                                                title="Reply to message">
                                                <i class="fa-solid fa-reply" aria-hidden="true"></i>
                                            </button>

                                            @if ((int) $message->user_id === (int) $currentUser->id)
                                                @if (($message->type ?? 'text') === 'text')
                                                    <button type="button" class="message-action-btn"
                                                        data-message-action="edit" aria-label="Edit message"
                                                        title="Edit message">
                                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                                    </button>
                                                @endif

                                                <button type="button" class="message-action-btn delete"
                                                    data-message-action="delete" aria-label="Delete message"
                                                    title="Delete message">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>

                                    </div>

                                    <div
                                        class="message-bubble {{ ($message->type ?? 'text') !== 'text' ? 'media-bubble' : '' }}">
                                        @if ($message->replyTo)
                                            <div class="message-reply-quote">
                                                <span class="message-reply-quote-text">
                                                    @if ($message->replyTo->type === 'image')
                                                        Photo
                                                    @elseif (in_array($message->replyTo->type, ['voice', 'audio']))
                                                        Voice message
                                                    @elseif ($message->replyTo->type === 'attachment')
                                                        {{ $message->replyTo->file_name ?: 'File attachment' }}
                                                    @elseif ($message->replyTo->type === 'location')
                                                        Shared location
                                                    @else
                                                        {{ $message->replyTo->body ?: 'Message' }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                        @php
                                            $messageType = $message->type ?? 'text';
                                            $mediaUrl = $message->media_url ?? ($message->attachment_url ?? null);
                                        @endphp

                                        @if ($messageType === 'image' && $mediaUrl)
                                            <a class="bubble-image-link" href="{{ $mediaUrl }}" target="_blank"
                                                rel="noopener noreferrer">
                                                <img class="bubble-image" src="{{ $mediaUrl }}"
                                                    alt="{{ $message->file_name ?: 'Shared image' }}" loading="lazy">
                                            </a>
                                        @elseif (in_array($messageType, ['voice', 'audio']) && $mediaUrl)
                                            <div class="bubble-voice" data-audio-player>
                                                <audio class="bubble-audio-source" preload="metadata">
                                                    <source src="{{ $mediaUrl }}"
                                                        type="{{ $message->media_type ?: 'audio/webm' }}">
                                                </audio>
                                                <button type="button" class="voice-play-btn"
                                                    aria-label="Play voice message">
                                                    <i class="fa-solid fa-play"></i>
                                                </button>
                                                <div class="voice-player-content">
                                                    <div class="voice-waveform" role="slider" aria-label="Audio progress"
                                                        aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
                                                        tabindex="0">
                                                        @for ($i = 0; $i < 30; $i++)
                                                            <span style="--bar-height: {{ rand(20, 100) }}%"></span>
                                                        @endfor
                                                    </div>
                                                    <div class="voice-player-meta">
                                                        <span class="voice-current-time">0:00</span>
                                                        <span class="voice-duration">0:00</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif ($messageType === 'attachment' && $mediaUrl)
                                            <a class="bubble-attachment"
                                                href="{{ route('messages.attachments.download', $message->id) }}"
                                                aria-label="Download {{ $message->file_name ?: 'attachment' }}">
                                                <span class="attachment-icon">
                                                    @php
                                                        $extension = strtolower(
                                                            pathinfo($message->file_name ?? '', PATHINFO_EXTENSION),
                                                        );
                                                        $icon = match ($extension) {
                                                            'pdf' => 'fa-file-pdf',
                                                            'doc', 'docx' => 'fa-file-word',
                                                            'xls', 'xlsx', 'csv' => 'fa-file-excel',
                                                            'ppt', 'pptx' => 'fa-file-powerpoint',
                                                            'zip', 'rar', '7z' => 'fa-file-zipper',
                                                            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'fa-file-image',
                                                            'mp3', 'wav', 'ogg', 'm4a' => 'fa-file-audio',
                                                            'mp4', 'mov', 'webm', 'avi' => 'fa-file-video',
                                                            'txt' => 'fa-file-lines',
                                                            default => 'fa-file',
                                                        };
                                                    @endphp
                                                    <i class="fa-solid {{ $icon }}"></i>
                                                </span>
                                                <span class="attachment-details">
                                                    <span
                                                        class="attachment-name">{{ $message->file_name ?: 'Download attachment' }}</span>
                                                    <span
                                                        class="attachment-caption">{{ strtoupper($extension ?: 'FILE') }}
                                                        ·
                                                        Download original file</span>
                                                </span>
                                                <span class="attachment-download"><i
                                                        class="fa-solid fa-download"></i></span>
                                            </a>
                                        @elseif ($messageType === 'location')
                                            @if ($message->latitude !== null && $message->longitude !== null)
                                                <a class="bubble-location"
                                                    href="https://www.google.com/maps?q={{ $message->latitude }},{{ $message->longitude }}"
                                                    target="_blank" rel="noopener noreferrer">
                                                    <span class="location-icon">
                                                        <i class="fa-solid fa-location-dot"></i>
                                                    </span>

                                                    <span class="location-details">
                                                        <strong>Shared location</strong>
                                                        <small>View location on Google Maps</small>
                                                    </span>

                                                    <span class="location-open">
                                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                    </span>
                                                </a>
                                            @else
                                                {{ $message->body }}
                                            @endif
                                        @else
                                            {{ $message->body }}
                                        @endif
                                    </div>

                                </div>
                            </div>
                        @empty
                            <div class="empty-messages" id="emptyMessages">
                                <div class="empty-icon"><i class="fa-regular fa-paper-plane"></i></div>
                                <h3>👋 Start the conversation!</h3>
                                <p>💬 Send the first message and make someone's day a little brighter. 💜</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="camera-modal" id="cameraModal" hidden>
                <div class="camera-panel">
                    <div class="camera-header">
                        <strong><i class="fa-solid fa-camera"></i> Take a photo</strong>
                        <button type="button" id="closeCameraButton" aria-label="Close camera">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <video id="cameraVideo" autoplay playsinline></video>
                    <canvas id="cameraCanvas" hidden></canvas>
                    <img id="cameraPreview" alt="Captured photo" hidden>

                    <p id="cameraStatus">Starting camera...</p>

                    <div class="camera-controls">
                        <button type="button" id="capturePhotoButton">
                            <i class="fa-solid fa-circle"></i> Capture
                        </button>
                        <button type="button" id="retakePhotoButton" hidden>
                            <i class="fa-solid fa-rotate-left"></i> Retake
                        </button>
                        <button type="button" id="usePhotoButton" hidden>
                            <i class="fa-solid fa-check"></i> Use photo
                        </button>
                    </div>
                </div>
            </div>

            <form class="message-composer" id="messageForm" method="POST"
                action="{{ route('chat.messages.store', $conversation) }}" enctype="multipart/form-data">
                @csrf

                <div class="composer-box">
                    <div class="composer-tools" role="toolbar" aria-label="Message tools">

                        <button type="button" class="composer-tool" id="cameraButton" title="Take a photo"
                            aria-label="Take a photo">
                            <i class="fa-solid fa-camera"></i>
                        </button>

                        <input type="file" id="cameraInput" accept="image/*" capture="environment" hidden>


                        <button type="button" class="composer-tool" id="imageButton" title="Choose an image"
                            aria-label="Choose an image">
                            <i class="fa-regular fa-image"></i>
                        </button>

                        <button type="button" class="composer-tool" id="attachmentButton" title="Attach a file"
                            aria-label="Attach a file">
                            <i class="fa-solid fa-paperclip"></i>
                        </button>

                        <button type="button" class="composer-tool" id="locationButton" title="Share your location"
                            aria-label="Share your location">
                            <i class="fa-solid fa-location-dot"></i>
                        </button>

                        <button type="button" class="composer-tool" id="voiceButton" title="Record a voice message"
                            aria-label="Record a voice message">
                            <i class="fa-solid fa-microphone"></i>
                        </button>

                        <button type="button" class="composer-tool" id="emojiButton" title="Choose an emoji"
                            aria-label="Choose an emoji" aria-expanded="false" aria-controls="emojiPicker">
                            <i class="fa-regular fa-face-smile"></i>
                        </button>
                        <div class="composer-bottom">
                            <button type="submit" class="send-button" aria-label="Send message" title="Send message">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="type" id="messageType" value="text">
                    <input type="hidden" name="latitude" id="messageLatitude">
                    <input type="hidden" name="longitude" id="messageLongitude">

                    <input type="file" id="imageInput" name="image" accept="image/*" hidden>
                    <input type="file" id="attachmentInput" name="attachment"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar,.ppt,.pptx,.png,.jpg,.jpeg,.webp" hidden>

                    <div id="composerPreview" class="composer-preview" hidden>
                        <img id="composerImagePreview" alt="Selected image preview" hidden>
                        <div class="composer-file-info">
                            <i class="fa-solid fa-file" id="composerFileIcon"></i>
                            <span id="composerFileName"></span>
                        </div>
                        <button type="button" id="removeComposerFile" class="composer-remove-file"
                            aria-label="Remove selected file">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div id="voiceRecorder" class="voice-recorder" hidden>
                        <span class="voice-recording-dot"></span>
                        <span id="voiceStatus">Ready to record</span>
                        <span id="voiceTimer">00:00</span>
                        <button type="button" id="stopVoiceButton" aria-label="Stop recording">
                            <i class="fa-solid fa-stop"></i> Stop
                        </button>
                        <button type="button" id="cancelVoiceButton" aria-label="Cancel recording">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <audio id="voicePreview" class="voice-preview" controls hidden></audio>


                    <div class="reply-preview" id="replyPreview" hidden>
                        <div class="reply-preview-content">
                            <i class="fa-solid fa-reply"></i>

                            <div class="reply-preview-details">
                                <strong id="replyPreviewUser">Replying to</strong>
                                <span id="replyPreviewText"></span>
                            </div>
                        </div>

                        <button type="button" id="cancelReply" aria-label="Cancel reply">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>


                    <input type="hidden" name="reply_to_message_id" id="replyToMessageId">
                    <textarea name="body" id="messageInput" rows="1" maxlength="5000" placeholder="Write a message... 💬">{{ old('body') }}</textarea>


                    <p id="composerStatus" class="composer-status" role="status" aria-live="polite"></p>
                </div>
                <div class="emoji-picker" id="emojiPicker" hidden>
                    <div class="emoji-picker-header">
                        <div class="emoji-picker-title">
                            <i class="fa-regular fa-face-smile"></i>
                            <span>Choose an emoji</span>
                        </div>

                        <button type="button" id="emojiClose" aria-label="Close emoji picker">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <input type="search" id="emojiSearch" class="emoji-search" placeholder="Search emojis..."
                        autocomplete="off" aria-label="Search emojis">

                    <div class="emoji-categories" role="group" aria-label="Emoji categories">
                        <button type="button" class="emoji-category active" data-category="smileys"
                            title="Smileys">😊</button>
                        <button type="button" class="emoji-category" data-category="people" title="People">👋</button>
                        <button type="button" class="emoji-category" data-category="hearts" title="Hearts">💜</button>
                        <button type="button" class="emoji-category" data-category="animals"
                            title="Animals">🐱</button>
                        <button type="button" class="emoji-category" data-category="food" title="Food">🍕</button>
                        <button type="button" class="emoji-category" data-category="nature" title="Nature">🌸</button>
                        <button type="button" class="emoji-category" data-category="activities"
                            title="Activities">⚽</button>
                        <button type="button" class="emoji-category" data-category="objects"
                            title="Objects">💡</button>
                        <button type="button" class="emoji-category" data-category="symbols" title="Symbols">✨</button>
                    </div>

                    <div class="emoji-picker-label" id="emojiCategoryLabel">Smileys &amp; Emotions</div>
                    <div class="emoji-grid" id="emojiGrid" role="group" aria-label="Available emojis"></div>

                    <div class="emoji-picker-footer">
                        <span id="emojiResultCount">Select an emoji to insert it</span>
                    </div>
                </div>
            </form>
        </section>
    </div>
    <script src="{{ asset('js/voice-message.js') }}"></script>
@endsection

@push('styles')
    <style>
        .topbar h1 {
            margin: 5px 0 0;
            color: var(--white, #d8b4fe);
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -.6px;
        }

        .room-details-toggle {
            display: none;
            width: 100%;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            color: #a99bbf;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 12px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: none;
            outline: none;
            transition: border-color .25s ease, color .25s ease;
        }

        .room-details-toggle:hover {
            color: #cba6ff;
            border-color: rgba(190, 140, 255, .45);
        }

        .room-details-toggle:focus,
        .room-details-toggle:active {
            background: transparent;
            box-shadow: none;
            outline: none;
        }

        .room-details-toggle .toggle-chevron,
        .side-link .toggle-chevron {
            transition: transform .2s ease;
        }

        .room-details-toggle[aria-expanded="true"] .toggle-chevron {
            transform: rotate(180deg);
        }

        .room-details-content {
            display: none;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .room-details-content.show,
        #roomDetailsContent.show {
            display: block;
        }

        #roomDetailsContent {
            display: none;
        }

        .room-action-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 12px;
            border-radius: 14px;
            box-sizing: border-box;
        }

        .room-action-buttons>form {
            display: flex;
            flex: 1 1 0;
            min-width: 0;
            margin: 0;
        }

        .room-action-buttons>form>button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-width: 0;
            min-height: 40px;
            padding: 9px 12px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            white-space: nowrap;
            cursor: pointer;
            box-sizing: border-box;
        }

        .room-action-buttons .room-delete-button {
            color: #ff7185;
            background: rgba(255, 65, 90, .08);
            border: 1px solid rgba(255, 90, 110, .25);
        }

        .room-action-buttons .room-delete-button:hover {
            background: rgba(255, 65, 90, .16);
            border-color: rgba(255, 90, 110, .55);
        }

        .room-action-buttons button i {
            flex-shrink: 0;
        }

        .room-conversation-actions.group-profile-danger-actions {
            display: flex;
            flex-direction: row;
            align-items: stretch;
            gap: 12px;
            width: 100%;
        }

        .room-conversation-actions.group-profile-danger-actions form {
            display: flex;
            flex: 1 1 0;
            min-width: 0;
            margin: 0;
        }

        .room-conversation-actions.group-profile-danger-actions button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-width: 0;
            min-height: 44px;
            padding: 12px 14px;
            color: #fff;
            border: none;
            border-radius: 10px;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: background .2s ease, transform .2s ease;
        }

        .room-conversation-actions.group-profile-danger-actions button:hover {
            transform: translateY(-1px);
        }

        .room-conversation-actions.group-profile-danger-actions .room-delete-button {
            background: #7f1d1d;
            color: #fff;
        }

        .room-conversation-actions.group-profile-danger-actions .room-delete-button:hover {
            background: #991b1b;
        }

        .room-conversation-actions.group-profile-danger-actions button i {
            flex-shrink: 0;
        }

        @keyframes roomDetailsReveal {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .emoji-picker[hidden] {
            display: none !important;
        }

        .emoji-picker {
            position: absolute;
            z-index: 9999;
            left: 0;
            bottom: calc(100% + 8px);
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: min(300px, calc(100vw - 32px));
            max-width: calc(100vw - 32px);
            max-height: min(390px, 65vh);
            padding: 11px;
            overflow: hidden;
            color: #eee8fa;
            background: #17131f;
            border: 1px solid rgba(190, 140, 255, .3);
            border-radius: 13px;
            box-shadow: 0 14px 35px rgba(0, 0, 0, .5);
            box-sizing: border-box;
        }

        .emoji-picker-header {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .emoji-picker-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 700;
        }

        .emoji-picker-title i {
            color: #cba6ff;
            font-size: 16px;
        }

        #emojiClose {
            display: grid;
            place-items: center;
            flex: 0 0 27px;
            width: 27px;
            height: 27px;
            color: #bcb1cb;
            background: transparent;
            border: 0;
            border-radius: 7px;
            cursor: pointer;
        }

        #emojiClose:hover {
            color: #fff;
            background: #352743;
        }

        .emoji-search {
            display: block;
            flex: 0 0 34px;
            width: 100%;
            height: 34px;
            padding: 0 10px;
            color: #fff;
            background: #0f0d15;
            border: 1px solid #383044;
            border-radius: 8px;
            outline: none;
            font: inherit;
            font-size: 12px;
            box-sizing: border-box;
        }

        .emoji-search:focus {
            border-color: #a879e8;
        }

        .emoji-categories {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: flex-start;
            gap: 3px;
            overflow-x: auto;
            padding-bottom: 3px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .emoji-categories::-webkit-scrollbar {
            display: none;
        }

        .emoji-category {
            display: grid;
            place-items: center;
            flex: 0 0 28px;
            width: 28px;
            height: 29px;
            padding: 0;
            color: #eee;
            background: transparent;
            border: 0;
            border-radius: 7px;
            font-size: 17px;
            cursor: pointer;
        }

        .emoji-category:hover,
        .emoji-category.active {
            background: #352743;
            box-shadow: inset 0 -2px #bb86fc;
        }

        .emoji-picker-label {
            flex: 0 0 auto;
            margin: 0;
            color: #c3b4d6;
            font-size: 11px;
            font-weight: 700;
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            align-content: start;
            gap: 3px;
            flex: 0 1 auto;
            min-height: 100px;
            max-height: 205px;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .emoji-grid::-webkit-scrollbar {
            width: 0;
            height: 0;
        }

        .emoji-grid.is-scrolling {
            scrollbar-width: thin;
            scrollbar-color: #69517f transparent;
        }

        .emoji-grid.is-scrolling::-webkit-scrollbar {
            width: 4px;
        }

        .emoji-grid.is-scrolling::-webkit-scrollbar-track {
            background: transparent;
        }

        .emoji-grid.is-scrolling::-webkit-scrollbar-thumb {
            background: #69517f;
            border-radius: 10px;
        }

        .emoji-option {
            display: grid;
            place-items: center;
            width: 100%;
            min-width: 0;
            aspect-ratio: 1;
            padding: 0;
            color: inherit;
            background: transparent;
            border: 0;
            border-radius: 6px;
            font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
            font-size: 22px;
            cursor: pointer;
        }

        .emoji-option:hover,
        .emoji-option:focus-visible {
            background: #352743;
            outline: none;
        }

        .emoji-picker-footer {
            flex: 0 0 auto;
            margin: 0;
            padding-top: 7px;
            color: #a99bbf;
            border-top: 1px solid #30283a;
            font-size: 10px;
        }

        .emoji-empty {
            grid-column: 1 / -1;
            padding: 18px 6px;
            color: #a99bbf;
            text-align: center;
            font-size: 12px;
        }

        .voice-recording-dot .voiceTimer {
            color: #a855f7;
        }

        .voice-recorder {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            padding: 15px 18px;
            overflow: hidden;
            color: #a855f7;
            background: radial-gradient(ellipse at top left, rgba(126, 34, 206, .18), transparent 60%), linear-gradient(135deg, #171321, #0d0b14 75%);
            border: 1px solid rgba(168, 85, 247, .25);
            border-radius: 22px;
            box-sizing: border-box;
        }

        .voice-recorder *,
        .voice-recorder *::before,
        .voice-recorder *::after {
            box-sizing: border-box;
        }

        .voice-recorder .voice-mic-btn {
            display: flex;
            flex: 0 0 48px;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            color: #fff;
            background: linear-gradient(145deg, #a855f7, #6d28d9);
            border: 1px solid rgba(216, 180, 254, .35);
            border-radius: 16px;
            box-shadow: 0 0 22px rgba(168, 85, 247, .28);
            font-size: 19px;
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .voice-recorder .voice-mic-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 28px rgba(168, 85, 247, .48);
        }

        .voice-recorder .voice-mic-btn:active {
            transform: scale(.94);
        }

        .voice-recorder .voice-recorder-content {
            flex: 1;
            min-width: 0;
        }

        .voice-recorder .voice-recorder-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .voice-recorder .voice-recorder-title {
            color: #f5efff;
            font-size: 13px;
            font-weight: 650;
            letter-spacing: .2px;
        }

        .voice-recorder .voice-recorder-time {
            color: #c4a5fd;
            font-family: "Courier New", monospace;
            font-size: 12px;
            font-variant-numeric: tabular-nums;
        }

        .voice-recorder .voice-waveform {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3px;
            width: 100%;
            height: 27px;
            overflow: hidden;
        }

        .voice-recorder .voice-waveform span {
            display: block;
            flex: 1;
            min-width: 2px;
            max-width: 5px;
            height: var(--bar-height, 35%);
            background: linear-gradient(to top, #7c3aed, #e9d5ff);
            border-radius: 10px;
            opacity: .8;
        }

        .voice-recorder.is-recording .voice-waveform span {
            animation: orchid-wave .8s ease-in-out infinite alternate;
            animation-delay: var(--delay, 0s);
        }

        .voice-recorder .voice-recorder-status {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 7px;
            color: #a89bbd;
            font-size: 11px;
        }

        .voice-recorder .voice-recorder-status .status-dot {
            width: 7px;
            height: 7px;
            background: #a78bfa;
            border-radius: 50%;
        }

        .voice-recorder.is-recording .voice-recorder-status .status-dot {
            background: #fb7185;
            box-shadow: 0 0 12px rgba(251, 113, 133, .75);
            animation: orchid-pulse 1.2s infinite;
        }

        .voice-recorder .voice-recorder-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .voice-recorder .voice-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            color: #d8b4fe;
            background: rgba(255, 255, 255, .045);
            border: 1px solid rgba(255, 255, 255, .09);
            border-radius: 13px;
            font-size: 15px;
            cursor: pointer;
            transition: background .2s ease, border-color .2s ease;
        }

        .voice-recorder .voice-action-btn:hover {
            background: rgba(168, 85, 247, .18);
            border-color: rgba(168, 85, 247, .4);
        }

        .voice-recorder .voice-action-btn.send {
            color: #fff;
            background: linear-gradient(145deg, #a855f7, #7c3aed);
            border-color: transparent;
        }

        .voice-recorder .voice-action-btn.cancel {
            color: #fda4af;
        }

        @keyframes orchid-wave {
            from {
                transform: scaleY(.45);
                opacity: .55;
            }

            to {
                transform: scaleY(1);
                opacity: 1;
            }
        }

        @keyframes orchid-pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .45;
                transform: scale(.8);
            }
        }

        .message-voice {
            display: flex;
            align-items: center;
            gap: 12px;
            width: min(320px, 100%);
            min-width: 0;
            margin: 5px 0;
            padding: 12px 14px;
            color: #f5efff;
            background: radial-gradient(circle at 10% 0%, rgba(168, 85, 247, .16), transparent 65%), rgba(19, 15, 29, .96);
            border: 1px solid rgba(192, 132, 252, .18);
            border-radius: 18px;
            box-sizing: border-box;
        }

        .message-voice *,
        .message-voice *::before,
        .message-voice *::after,
        .bubble-voice *,
        .bubble-voice *::before,
        .bubble-voice *::after {
            box-sizing: border-box;
        }

        .message-voice .voice-play-btn,
        .bubble-voice .voice-play-btn,
        .voice-play-btn {
            display: flex;
            flex: 0 0 42px;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            padding: 0;
            color: #fff;
            background: linear-gradient(135deg, #1684ff, #6d5dfc);
            border: none;
            border-radius: 50%;
            font-size: 14px;
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .message-voice .voice-play-btn {
            background: linear-gradient(145deg, #c084fc, #7c3aed);
            box-shadow: 0 0 18px rgba(168, 85, 247, .24);
        }

        .message-voice .voice-play-btn:hover,
        .bubble-voice .voice-play-btn:hover,
        .voice-play-btn:hover {
            transform: scale(1.06);
            box-shadow: 0 0 14px rgba(96, 99, 255, .35);
        }

        .message-voice .voice-player-content,
        .bubble-voice .voice-player-content,
        .voice-player-content {
            flex: 1;
            min-width: 0;
        }

        .message-voice .voice-player-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 9px;
        }

        .message-voice .voice-player-label {
            color: #f3e8ff;
            font-size: 12px;
            font-weight: 650;
            letter-spacing: .2px;
        }

        .message-voice .voice-player-time {
            color: #c4b5fd;
            font-family: "Courier New", monospace;
            font-size: 11px;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .message-voice .voice-player-track {
            position: relative;
            width: 100%;
            height: 4px;
            overflow: hidden;
            background: rgba(196, 181, 253, .16);
            border-radius: 99px;
            cursor: pointer;
        }

        .message-voice .voice-player-progress {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #a855f7, #f0abfc);
            border-radius: inherit;
            transition: width .08s linear;
        }

        .message-voice .voice-player-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 8px;
            color: #a99bbd;
            font-size: 10px;
        }

        .message-voice .voice-player-icon {
            color: #c084fc;
            font-size: 11px;
        }

        .message-voice audio,
        .bubble-audio-source {
            display: none;
        }

        .bubble-voice {
            display: flex;
            align-items: center;
            gap: 9px;
            width: min(300px, 100%);
            min-width: 0;
            margin: 4px 0;
            padding: 9px 10px;
            color: #f5efff;
            background: radial-gradient(circle at 12% 0%, rgba(139, 92, 246, .13), transparent 75%), rgba(25, 17, 39, .97);
            border: 1px solid rgba(139, 92, 246, .25);
            border-radius: 20px;
            box-sizing: border-box;
        }

        .bubble-voice .voice-play-btn {
            flex-basis: 39px;
            width: 39px;
            height: 39px;
            background: linear-gradient(145deg, #4385ff, #6652ff);
            box-shadow: 0 3px 12px rgba(67, 101, 255, .2);
        }

        .bubble-voice .voice-player-content {
            flex: 1;
            min-width: 0;
        }

        .bubble-voice .voice-waveform,
        .voice-waveform {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 3px;
            width: 100%;
            height: 28px;
            cursor: pointer;
            outline: none;
        }

        .bubble-voice .voice-waveform {
            gap: 2px;
            height: 25px;
            overflow: hidden;
            touch-action: manipulation;
        }

        .bubble-voice .voice-waveform span,
        .voice-waveform span {
            display: block;
            flex: 1;
            min-width: 2px;
            max-width: 4px;
            min-height: 4px;
            height: var(--bar-height, 50%);
            background: rgba(196, 181, 253, .45);
            border-radius: 5px;
            transition: background .15s ease;
        }

        .bubble-voice .voice-waveform span {
            flex: 1 1 0;
            min-width: 1px;
            max-width: 3px;
            min-height: 0;
            height: var(--bar-height, 50%);
            background: rgba(177, 161, 222, .65);
            border-radius: 3px;
        }

        .bubble-voice .voice-waveform span.played,
        .voice-waveform span.played {
            background: #a78bfa;
        }

        .voice-player-meta,
        .bubble-voice .voice-player-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 4px;
            color: #c4b5fd;
            font-size: 10px;
            font-variant-numeric: tabular-nums;
        }

        .bubble-voice .voice-player-meta {
            margin-top: 2px;
            color: #d0c5e8;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 9px;
            font-weight: 500;
            line-height: 1.4;
        }

        .bubble-voice .voice-current-time,
        .bubble-voice .voice-duration {
            white-space: nowrap;
        }

        .bubble-audio {
            display: block;
            width: 30vh;
            max-width: 100%;
            min-width: 0;
            height: 38px;
            outline: none;
        }

        .message-row {
            position: relative;
            display: flex;
            align-items: flex-end;
            gap: 8px;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .message-row .message-content {
            position: relative;
            flex: 0 1 auto;
            width: fit-content;
            min-width: 0;
            max-width: calc(100% - 44px);
            box-sizing: border-box;
        }

        .message-content {
            position: relative;
            min-width: 0;
            max-width: 100%;
        }

        .message-row .message-bubble:not(.media-bubble) {
            display: block;
            width: fit-content;
            max-width: min(100%, 360px);
            min-width: 0;
            margin: 0 auto;
            padding: 8px 12px;
            color: #f5efff;
            background: #292330;
            border: none;
            border-radius: 18px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 300;
            line-height: 1.5;
            text-align: left;
            overflow-wrap: anywhere;
            word-break: normal;
            hyphens: none;
            box-shadow: none;
            box-sizing: border-box;
        }

        .message-row.mine .message-bubble:not(.media-bubble) {
            margin-left: auto;
            margin-right: 0;
            color: #fff;
            background: #7650c8;
            border-radius: 18px 18px 5px 18px;
        }

        .message-row:not(.mine) .message-bubble:not(.media-bubble) {
            margin-left: 0;
            margin-right: auto;
            color: #f5efff;
            background: #292330;
            border-radius: 18px 18px 18px 5px;
        }

        .message-bubble:not(.media-bubble) a {
            color: inherit;
            overflow-wrap: anywhere;
        }

        .message-bubble.media-bubble {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            margin: 0;
            padding: 6px;
            overflow: hidden;
            box-sizing: border-box;
        }

        .bubble-image-link {
            display: block;
            width: fit-content;
            max-width: 100%;
            overflow: hidden;
            border-radius: 10px;
            line-height: 0;
        }

        .bubble-image {
            display: block;
            width: auto;
            height: auto;
            max-width: 180px;
            max-height: 180px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
        }

        .message-tools {
            position: absolute;
            top: 0;
            right: -34px;
            left: auto;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 30px;
            min-width: 30px;
        }

        .message-row.mine .message-tools {
            right: auto;
            left: -34px;
        }

        .message-menu-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 30px;
            width: 30px;
            min-width: 30px;
            height: 34px;
            padding: 0;
            color: #b9aecb;
            background: transparent;
            border: 0;
            border-radius: 9px;
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            transition: color .18s ease, background .18s ease;
        }

        .message-menu-trigger:hover,
        .message-menu-trigger:focus-visible,
        .message-menu-trigger[aria-expanded="true"],
        .message-tools.is-open .message-menu-trigger {
            color: #e9d5ff;
            background: rgba(167, 139, 250, .17);
            outline: none;
        }

        .message-actions {
            position: absolute;
            top: -5px;
            right: 0;
            left: auto;
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: nowrap;
            gap: 5px;
            width: max-content;
            min-width: 0;
            max-width: min(280px, calc(100vw - 24px));
            min-height: 44px;
            height: auto;
            padding: 5px;
            overflow-x: auto;
            overflow-y: hidden;
            overscroll-behavior-x: contain;
            color: #eee8fa;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .32);
            backdrop-filter: blur(18px) saturate(160%);
            -webkit-backdrop-filter: blur(18px) saturate(160%);
            scrollbar-width: none;
            box-sizing: border-box;
        }

        .message-actions[hidden] {
            display: none !important;
        }

        .message-tools.is-open .message-actions {
            display: flex;
        }

        .message-actions::-webkit-scrollbar {
            display: none;
        }

        .message-row.mine .message-actions {
            right: auto;
            left: -100%;
            top: -100%;

        }

        .message-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            gap: 6px;
            min-width: 38px;
            min-height: 36px;
            height: 36px;
            padding: 0 9px;
            color: rgba(235, 220, 255, .96);
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1;
            white-space: nowrap;
            cursor: pointer;
            touch-action: manipulation;
            box-sizing: border-box;
        }

        .message-action-btn i {
            flex: 0 0 auto;
            width: 15px;
            text-align: center;
            font-size: 13px;
        }

        .message-action-btn:hover,
        .message-action-btn:focus-visible {
            color: #fff;
            background: rgba(120, 70, 180, .3);
            outline: none;
        }

        .message-action-btn.delete {
            color: #ff8585;
        }

        .message-action-btn.delete:hover,
        .message-action-btn.delete:focus-visible {
            color: #ff6478;
            background: rgba(255, 70, 90, .15);
        }

        .reply-message-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            gap: 6px;
            min-width: 38px;
            min-height: 36px;
            height: 36px;
            padding: 0 9px;
            color: rgba(235, 220, 255, .96);
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1;
            white-space: nowrap;
            cursor: pointer;
            touch-action: manipulation;
            box-sizing: border-box;
        }

        .reply-message-btn i {
            flex: 0 0 auto;
            width: 15px;
            text-align: center;
            font-size: 13px;
        }

        .reply-message-btn:hover,
        .reply-message-btn:focus-visible {
            color: #fff;
            background: rgba(120, 70, 180, .3);
            outline: none;
        }

        .reply-message-btn.reply {
            color: #ff8585;
        }

        .media-delete-tools {
            position: relative;
            display: flex;
            justify-content: flex-start;
            margin-bottom: 0;
        }

        .media-message-actions {
            position: absolute;
            top: 0;
            right: 0;
            z-index: 20;
            min-width: 125px;
            padding: 5px;
            background: #19171e;
            border: 1px solid rgba(255, 255, 255, .08);
            border-radius: 8px;
            box-shadow: 0 5px 18px rgba(0, 0, 0, .4);
        }

        .media-message-actions[hidden] {
            display: none !important;
        }

        .media-message-actions .media-delete-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 9px 10px;
            color: #f87171;
            background: transparent;
            border: none;
            border-radius: 5px;
            font-size: 12px;
            text-align: left;
            cursor: pointer;
        }

        .media-message-actions .media-delete-btn:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, .15);
        }

        .reply-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-width: 0;
            margin: 0 0 10px;
            padding: 10px 12px;
            background: transparent;
            border-left: 3px solid #a78bfa;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .reply-preview[hidden] {
            display: none !important;
        }

        .reply-preview-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
            color: #c4b5fd;
        }

        .reply-preview-content>div {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
            min-width: 0;
        }

        .reply-preview-content strong {
            overflow: hidden;
            color: #e9d5ff;
            font-size: 12px;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .reply-preview-content span {
            display: block;
            overflow: hidden;
            max-width: 280px;
            color: #a1a1aa;
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #cancelReply {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 28px;
            height: 28px;
            padding: 0;
            color: #a1a1aa;
            background: transparent;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background .2s ease, color .2s ease;
        }

        #cancelReply:hover {
            color: #f87171;
            background: rgba(239, 68, 68, .15);
        }

        .message-edit-input {
            display: block;
            width: 100%;
            min-height: 80px;
            padding: 10px;
            color: #fff;
            background: rgba(89, 35, 145, .22);
            border: 1px solid rgba(168, 105, 255, .45);
            border-radius: 10px;
            outline: none;
            resize: vertical;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            font: inherit;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .06), 0 4px 18px rgba(111, 44, 190, .12);
            transition: background .25s ease, border-color .25s ease, box-shadow .25s ease;
            box-sizing: border-box;
        }

        .message-edit-input:focus {
            background: rgba(103, 45, 163, .32);
            border-color: rgba(192, 132, 252, .85);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, .14), 0 0 18px rgba(147, 51, 234, .18);
        }

        .message-edit-input::placeholder {
            color: rgba(225, 210, 255, .58);
        }

        .message-edit-controls {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 8px;
            width: 100%;
            margin-top: 10px;
            box-sizing: border-box;
        }

        .message-edit-controls button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 36px;
            padding: 8px 14px;
            margin: 0;
            border-radius: 9px;
            box-sizing: border-box;
            white-space: nowrap;
            line-height: 1;
        }

        .bubble-attachment {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 230px;
            max-width: 100%;
            min-width: 0;
            padding: 8px;
            color: #f5f0ff;
            text-decoration: none;
            background: linear-gradient(135deg, rgba(120, 70, 180, .19), rgba(42, 30, 72, .32));
            border: 1px solid rgba(192, 132, 252, .24);
            border-radius: 12px;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            transition: background .2s ease, border-color .2s ease, transform .2s ease;
            box-sizing: border-box;
        }

        .bubble-attachment:hover {
            background: linear-gradient(135deg, rgba(139, 92, 246, .27), rgba(76, 29, 149, .25));
            border-color: rgba(192, 132, 252, .5);
            transform: translateY(-1px);
        }

        .attachment-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            color: #e9d5ff;
            background: rgba(168, 85, 247, .17);
            border: 1px solid rgba(192, 132, 252, .2);
            border-radius: 10px;
            font-size: 17px;
        }

        .attachment-details {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .attachment-name {
            overflow: hidden;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .attachment-caption {
            overflow: hidden;
            color: #c4b5fd;
            font-size: 9px;
            font-weight: 500;
            letter-spacing: .2px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .attachment-download {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 27px;
            width: 27px;
            height: 27px;
            color: #e9d5ff;
            background: rgba(168, 85, 247, .15);
            border: 1px solid rgba(192, 132, 252, .18);
            border-radius: 8px;
            font-size: 11px;
            transition: color .2s ease, background .2s ease;
        }

        .bubble-attachment:hover .attachment-download {
            color: #fff;
            background: #8b5cf6;
        }

        @media (min-width: 769px) {
            .chat-room {
                display: grid;
                grid-template-columns: minmax(250px, 300px) minmax(0, 1fr);
                align-items: stretch;
                gap: 20px;
            }

            .chat-room>.room-details {
                display: block !important;
                min-width: 0;
            }

            .chat-room>.message-panel {
                min-width: 0;
            }

            .room-details-toggle {
                display: none !important;
            }
        }

        @media (max-width: 768px) {
            .chat-room {
                display: flex;
                flex-direction: column;
                gap: 12px;
                min-width: 0;
                width: 100%;
            }

            .chat-room>.message-panel {
                display: flex;
                flex-direction: column;
                order: 1;
                width: 100%;
                min-width: 0;
            }

            .room-details-toggle {
                display: flex;
                flex-shrink: 0;
            }

            .chat-room>.room-details {
                display: none;
                order: 2;
                width: 100%;
                min-width: 0;
                max-height: 60dvh;
                overflow-y: auto;
                overscroll-behavior: contain;
                scrollbar-width: none;
                -ms-overflow-style: none;
                padding: 16px;
                background: transparent;
                box-sizing: border-box;
            }

            .chat-room>.room-details::-webkit-scrollbar {
                display: none;
            }

            .chat-room>.room-details.is-visible {
                display: block;
                animation: roomDetailsReveal .22s ease;
            }

            .chat-room .message-panel-head,
            .chat-room .message-head-person {
                min-width: 0;
            }

            .chat-room .message-head-person>div {
                min-width: 0;
                overflow-wrap: anywhere;
            }

            .chat-room .messages-scroll {
                width: 100%;
                min-width: 0;
                overflow-x: hidden;
            }

            .chat-room .message-composer {
                width: 100%;
                min-width: 0;
            }

            .chat-room .room-details .full-button {
                width: 100%;
            }

            .message-row {
                position: relative;
                display: flex;
                align-items: flex-end;
                gap: 8px;
                width: 100%;
                min-width: 0;
                box-sizing: border-box;
            }

            .message-row .message-content {
                position: relative;
                flex: 0 1 auto;
                min-width: 0;
                width: fit-content;
                max-width: calc(100% - 44px);
                box-sizing: border-box;
            }

            .message-row .message-bubble:not(.media-bubble) {
                max-width: 100%;
                min-width: 0;
                padding: 8px 12px;
                font-size: 14px;
                overflow-wrap: anywhere;
                box-sizing: border-box;
            }

            .message-row .message-tools {
                position: absolute;
                top: 0;
                right: -36px;
                left: auto;
                z-index: 40;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 30px;
                min-width: 30px;
                max-width: 30px;
                box-sizing: border-box;
            }

            .message-row.mine .message-tools {
                right: auto;
                left: -36px;
            }

            .message-menu-trigger {
                display: flex;
                flex: 0 0 30px;
                align-items: center;
                justify-content: center;
                width: 30px;
                min-width: 30px;
                height: 34px;
                padding: 0;
                box-sizing: border-box;
            }

            .message-actions {
                position: fixed;
                top: auto;
                right: 12px;
                bottom: auto;
                left: auto;
                z-index: 1000;
                display: none;
                align-items: center;
                gap: 4px;
                width: max-content;
                max-width: calc(100vw - 24px);
                padding: 6px;
                box-sizing: border-box;
                overflow-x: auto;
                overflow-y: hidden;
                border-radius: 10px;
            }

            .message-tools.is-open .message-actions {
                display: flex;
            }

            .message-row.mine .message-actions {
                right: 12px;
                left: auto;
            }

            .message-action-btn {
                flex: 0 0 auto;
                min-width: 36px;
                min-height: 36px;
                height: 36px;
                padding: 0 8px;
                font-size: 12px;
                box-sizing: border-box;
            }

            .message-action-btn:active {
                background: rgba(168, 85, 247, .2);
                border-color: rgba(177, 125, 255, .3);
                transform: scale(.96);
            }
        }

        @media (max-width: 600px) {
            .bubble-image {
                max-width: 150px;
                max-height: 150px;
            }

            .bubble-attachment {
                width: 210px;
                max-width: 100%;
                gap: 7px;
                padding: 7px;
                border-radius: 11px;
                box-sizing: border-box;
            }

            .attachment-icon {
                flex-basis: 30px;
                width: 30px;
                height: 30px;
                font-size: 15px;
            }

            .attachment-name {
                font-size: 10px;
                overflow-wrap: anywhere;
            }

            .attachment-caption {
                font-size: 8px;
            }

            .attachment-download {
                flex-basis: 25px;
                width: 25px;
                height: 25px;
                font-size: 10px;
            }

            .bubble-voice {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                gap: 9px;
                padding: 9px 10px;
                box-sizing: border-box;
            }

            .voice-play-btn {
                flex-basis: 38px;
                width: 38px;
                height: 38px;
            }

            .voice-waveform {
                gap: 2px;
                min-width: 0;
            }
        }

        @media (max-width: 480px) {
            .room-action-buttons {
                gap: 6px;
                padding: 8px;
            }

            .room-action-buttons>form>button {
                gap: 5px;
                padding: 8px 6px;
                font-size: 11px;
            }

            .room-conversation-actions.group-profile-danger-actions {
                gap: 8px;
            }

            .room-conversation-actions.group-profile-danger-actions button {
                gap: 6px;
                padding: 10px 8px;
                font-size: 11px;
            }

            .emoji-picker {
                left: 0;
                width: min(290px, calc(100vw - 24px));
                max-width: calc(100vw - 24px);
                max-height: 55vh;
                padding: 10px;
                box-sizing: border-box;
            }

            .emoji-grid {
                grid-template-columns: repeat(7, minmax(0, 1fr));
                max-height: 175px;
            }

            .emoji-option {
                font-size: 21px;
            }

            .voice-recorder {
                gap: 10px;
                padding: 12px;
                border-radius: 18px;
            }

            .voice-recorder .voice-mic-btn {
                flex-basis: 42px;
                width: 42px;
                height: 42px;
                border-radius: 14px;
            }

            .voice-recorder .voice-recorder-actions {
                gap: 5px;
            }

            .voice-recorder .voice-action-btn {
                width: 34px;
                height: 34px;
            }

            .message-voice {
                width: min(280px, 100%);
                max-width: 100%;
                min-width: 0;
                gap: 10px;
                padding: 10px;
                border-radius: 16px;
                box-sizing: border-box;
            }

            .message-voice .voice-play-btn {
                flex-basis: 38px;
                width: 38px;
                height: 38px;
            }

            .bubble-voice {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                gap: 8px;
                padding: 8px 9px;
                border-radius: 17px;
                box-sizing: border-box;
            }

            .bubble-voice .voice-play-btn {
                flex-basis: 36px;
                width: 36px;
                height: 36px;
            }

            .bubble-voice .voice-waveform {
                height: 24px;
            }

            .reply-preview {
                gap: 8px;
                padding: 8px 10px;
            }

            .reply-preview-content {
                gap: 8px;
                min-width: 0;
            }

            .reply-preview-content span {
                max-width: 100%;
                overflow-wrap: anywhere;
            }

            .message-row {
                gap: 6px;
            }

            .message-row .message-content {
                max-width: calc(100% - 40px);
            }

            .message-row .message-tools {
                right: -32px;
                width: 28px;
                min-width: 28px;
                max-width: 28px;
            }

            .message-row.mine .message-tools {
                right: auto;
                left: -32px;
            }

            .message-menu-trigger {
                flex-basis: 28px;
                width: 28px;
                min-width: 28px;
                height: 32px;
            }

            .message-actions {
                right: 8px;
                max-width: calc(100vw - 16px);
                gap: 3px;
                padding: 5px;
            }

            .message-row.mine .message-actions {
                right: 8px;
                left: auto;
            }

            .message-action-btn {
                min-width: 33px;
                min-height: 34px;
                height: 34px;
                padding: 0 7px;
                font-size: 11px;
            }
        }

        @media (max-width: 380px) {
            .message-row {
                gap: 5px;
            }

            .message-row .message-content {
                max-width: calc(100% - 36px);
            }

            .message-row .message-bubble:not(.media-bubble) {
                padding: 8px 10px;
                font-size: 13px;
            }

            .message-row .message-tools {
                right: -30px;
                width: 26px;
                min-width: 26px;
                max-width: 26px;
            }

            .message-row.mine .message-tools {
                right: auto;
                left: -30px;
            }

            .message-menu-trigger {
                flex-basis: 26px;
                width: 26px;
                min-width: 26px;
                height: 31px;
            }

            .message-actions {
                right: 6px;
                max-width: calc(100vw - 12px);
                gap: 2px;
                padding: 4px;
            }

            .message-row.mine .message-actions {
                right: 6px;
                left: auto;
            }

            .message-action-btn {
                min-width: 31px;
                min-height: 32px;
                height: 32px;
                padding: 0 6px;
                font-size: 10px;
            }
        }

        @media (max-width: 320px) {
            .message-row .message-content {
                max-width: calc(100% - 34px);
            }

            .message-row .message-tools {
                right: -28px;
                width: 24px;
                min-width: 24px;
                max-width: 24px;
            }

            .message-row.mine .message-tools {
                right: auto;
                left: -28px;
            }

            .message-menu-trigger {
                flex-basis: 24px;
                width: 24px;
                min-width: 24px;
            }

            .message-actions {
                right: 4px;
                max-width: calc(100vw - 8px);
            }

            .message-row.mine .message-actions {
                right: 4px;
                left: auto;
            }

            .message-action-btn {
                min-width: 29px;
                padding: 0 5px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .room-details-toggle,
            .room-details-toggle .toggle-chevron,
            .room-details.is-visible,
            .voice-recorder *,
            .voice-recorder *::before,
            .voice-recorder *::after {
                animation: none !important;
                transition: none !important;
            }
        }

        @media (max-width: 768px) {

            .message-tools,
            .message-menu-trigger {
                display: none !important;
            }

            .message-row .message-content {
                max-width: 100%;
            }
        }

        @media (max-width: 360px) {

            .message-tools,
            .message-menu-trigger {
                display: none !important;
            }

            .message-row .message-content {
                max-width: 100%;
            }
        }

        .bubble-location {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            max-width: 320px;
            min-width: 0;
            padding: 12px 14px;
            margin: 4px 0;
            color: #f5efff;
            text-decoration: none;
            background: linear-gradient(135deg, rgba(105, 55, 170, 0.22), rgba(36, 24, 57, 0.94));
            border: 1px solid rgba(183, 126, 255, 0.24);
            border-radius: 14px;
            box-sizing: border-box;
            transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
            overflow: hidden;
        }

        .bubble-location:hover {
            background: linear-gradient(135deg, rgba(130, 75, 205, 0.32), rgba(45, 28, 70, 0.98));
            border-color: rgba(195, 145, 255, 0.55);
            transform: translateY(-1px);
        }

        .location-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 42px;
            width: 42px;
            height: 42px;
            color: #fff;
            font-size: 18px;
            background: linear-gradient(145deg, #a855f7, #6d28d9);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
        }

        .location-details {
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .location-details strong {
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .location-details small {
            color: #b9a8d2;
            font-size: 11px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .location-open {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            color: #c9a5ff;
            font-size: 12px;
            background: rgba(168, 85, 247, 0.12);
            border: 1px solid rgba(183, 126, 255, 0.15);
            border-radius: 9px;
            transition: color 0.2s ease, background 0.2s ease;
        }

        .bubble-location:hover .location-open {
            color: #fff;
            background: rgba(168, 85, 247, 0.3);
        }

        @media (max-width: 480px) {
            .bubble-location {
                max-width: 100%;
                gap: 9px;
                padding: 10px;
                border-radius: 12px;
            }

            .location-icon {
                flex-basis: 38px;
                width: 38px;
                height: 38px;
                font-size: 16px;
                border-radius: 10px;
            }

            .location-details strong {
                font-size: 12px;
            }

            .location-details small {
                font-size: 10px;
            }

            .location-open {
                flex-basis: 25px;
                width: 25px;
                height: 25px;
            }
        }

        .camera-modal[hidden],
        .camera-panel [hidden] {
            display: none !important;
        }

        .camera-modal {
            position: fixed;
            inset: 0;
            z-index: 2147483647;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            width: 100vw;
            height: 100vh;
            height: 100dvh;
            max-width: none;
            padding: 0;
            margin: 0;
            overflow: hidden;
            isolation: isolate;
            background: #09060f;
            box-sizing: border-box;
        }

        .camera-panel {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            width: 100%;
            max-width: none;
            height: 100%;
            min-width: 0;
            min-height: 0;
            padding: 0;
            margin: 0;
            color: #f5efff;
            background: #09060f;
            border: 0;
            border-radius: 0;
            box-shadow: none;
            box-sizing: border-box;
            overflow: hidden;
        }

        .camera-header {
            position: relative;
            z-index: 3;
            display: flex;
            flex: 0 0 60px;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 20px;
            margin: 0;
            background: #09060f;
            box-sizing: border-box;
        }

        .camera-header strong {
            font-size: 16px;
            font-weight: 600;
        }

        .camera-header button {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            color: #fff;
            background: rgba(255, 255, 255, 0.08);
            border: 0;
            border-radius: 50%;
            cursor: pointer;
        }

        .camera-header button:hover {
            background: rgba(255, 51, 51, 0.2);
            color: #ff3333;
        }

        #cameraVideo,
        #cameraPreview {
            position: relative;
            z-index: 1;
            display: block;
            flex: 1 1 auto;
            width: 100%;
            height: 0;
            min-width: 0;
            min-height: 0;
            max-height: none;
            aspect-ratio: auto;
            object-fit: contain;
            background: #000;
            border: 0;
            border-radius: 0;
        }

        #cameraStatus {
            position: relative;
            z-index: 2;
            flex: 0 0 auto;
            margin: 0;
            padding: 10px 20px;
            color: #bda9d2;
            background: #09060f;
            font-size: 13px;
            text-align: center;
        }

        .camera-controls {
            position: relative;
            z-index: 3;
            display: flex;
            flex: 0 0 auto;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 16px 20px;
            margin: 0;
            background: #09060f;
            box-sizing: border-box;
        }

        .camera-controls button {
            display: inline-flex;
            flex: 0 1 220px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 0;
            min-height: 48px;
            padding: 12px 18px;
            color: #ff3333;
            background: #1a0a0a;
            border: 1px solid rgba(255, 51, 51, 0.35);
            border-radius: 50px;
            cursor: pointer;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .camera-controls button i {
            color: #ff3333;
        }

        .camera-controls button:hover:not(:disabled) {
            background: rgba(255, 51, 51, 0.15);
            border-color: #ff3333;
        }

        .camera-controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (min-width: 769px) {
            .camera-modal {
                width: 100vw;
                height: 100vh;
                height: 100dvh;
            }

            .camera-panel {
                width: 100%;
                max-width: none;
                height: 100%;
            }

            .camera-header {
                flex-basis: 64px;
                padding: 0 24px;
            }

            .camera-controls {
                gap: 16px;
                padding: 18px 24px;
            }

            .camera-controls button {
                flex-basis: 220px;
                min-height: 52px;
            }
        }

        @media (max-width: 480px) {
            .camera-header {
                flex-basis: 50px;
                padding: 0 12px;
            }

            .camera-header strong {
                font-size: 14px;
            }

            .camera-controls {
                gap: 8px;
                padding: 10px 12px;
                padding-bottom: max(10px, env(safe-area-inset-bottom));
            }

            .camera-controls button {
                flex: 1 1 0;
                min-height: 44px;
                padding: 9px 8px;
                font-size: 12px;
            }

            #cameraStatus {
                padding: 7px 10px;
                font-size: 12px;
            }
        }

        .reply-preview[hidden] {
            display: none !important
        }

        .reply-preview {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            width: 100%;
            min-width: 0;
            margin: 0 0 8px;
            padding: 9px 11px;
            color: #f5efff;
            background: transparent;
            border: 1px solid rgba(192, 132, 252, .13);
            border-left: 3px solid #a855f7;
            border-radius: 8px;
            box-sizing: border-box;
            transition: border-color .2s
        }

        .reply-preview-content {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
            overflow: hidden
        }

        .reply-preview-label {
            color: #d8b4fe;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .reply-preview-text {
            display: block;
            max-width: 100%;
            overflow: hidden;
            color: rgba(255, 255, 255, .78);
            font-size: 12px;
            line-height: 1.4;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        #cancelReplyButton {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            padding: 0;
            color: rgba(255, 255, 255, .7);
            background: transparent;
            border: 1px solid rgba(192, 132, 252, .16);
            border-radius: 50%;
            cursor: pointer;
            transition: color .2s, border-color .2s
        }

        #cancelReplyButton:hover {
            color: #ff5555;
            background: transparent;
            border-color: rgba(248, 113, 113, .5)
        }

        #cancelReplyButton:focus-visible {
            outline: 2px solid #c084fc;
            outline-offset: 2px
        }

        .message-reply-quote {
            display: flex;
            flex-direction: column;
            gap: 2px;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            margin: 0 0 5px;
            padding: 5px 8px;
            background: transparent;
            border: 1px solid rgba(192, 132, 252, .1);
            border-left: 2px solid #a855f7;
            border-radius: 5px;
            box-sizing: border-box;
            overflow: hidden;
            overflow-wrap: anywhere
        }

        .message-reply-quote-sender {
            display: block;
            color: #d8b4fe;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere
        }

        .message-reply-quote-text {
            display: block;
            max-width: 100%;
            color: rgba(255, 255, 255, .82);
            font-size: 11px;
            font-weight: 400;
            line-height: 1.4;
            overflow-wrap: anywhere;
            word-break: break-word
        }


        .message-deleted {
            opacity: 0.65;
        }

        .message-deleted .message-actions,
        .message-deleted .reply-message-btn,
        .message-deleted button {
            display: none !important;
        }

        .message-deleted .message-bubble,
        .message-deleted .message-content,
        .message-deleted .message-body {
            pointer-events: none;
        }

        .deleted-message-bubble {
            background: rgba(255, 255, 255, 0.035) !important;
            border: 1px solid rgba(255, 255, 255, 0.07) !important;
            box-shadow: none !important;
            padding: 5px 10px !important;
            border-radius: 60px !important;
            min-height: 0 !important;
            width: fit-content;
            max-width: 100%;
        }

        .deleted-message-text {
            display: inline-block;
            color: rgba(220, 210, 235, 0.58);
            font-size: 11px;
            font-style: italic;
            font-weight: 400;
            line-height: 1.4;
            letter-spacing: 0.1px;
            overflow-wrap: anywhere;
        }
    </style>
@endpush

@push('scripts')
    <script>
        function createVoicePlayer(audioUrl) {
            const wrapper = document.createElement('div');
            wrapper.className = 'bubble-voice';

            const audio = document.createElement('audio');
            audio.className = 'bubble-audio-source';
            audio.preload = 'metadata';
            audio.src = audioUrl;
            audio.setAttribute('aria-label', 'Voice message audio');

            const playButton = document.createElement('button');
            playButton.type = 'button';
            playButton.className = 'voice-play-btn';
            playButton.setAttribute('aria-label', 'Play voice message');
            playButton.innerHTML = '<i class="fa-solid fa-play"></i>';

            const content = document.createElement('div');
            content.className = 'voice-player-content';

            const waveform = document.createElement('div');
            waveform.className = 'voice-waveform';
            waveform.setAttribute('role', 'slider');
            waveform.setAttribute('aria-label', 'Voice message progress');
            waveform.setAttribute('aria-valuemin', '0');
            waveform.setAttribute('aria-valuemax', '100');
            waveform.setAttribute('aria-valuenow', '0');
            waveform.setAttribute('tabindex', '0');

            const barHeights = [
                8, 14, 20, 12, 25, 17, 10, 22, 28, 15,
                9, 19, 25, 13, 22, 16, 27, 11, 20, 14,
                24, 9, 18, 26, 13, 21, 16, 28, 11, 19
            ];

            barHeights.forEach(height => {
                const bar = document.createElement('span');
                bar.style.setProperty('--bar-height', `${height}px`);
                waveform.appendChild(bar);
            });

            const meta = document.createElement('div');
            meta.className = 'voice-player-meta';

            const currentTime = document.createElement('span');
            currentTime.textContent = '0:00';

            const duration = document.createElement('span');
            duration.textContent = '0:00';

            meta.append(currentTime, duration);
            content.append(waveform, meta);
            wrapper.append(playButton, content, audio);

            const bars = Array.from(waveform.querySelectorAll('span'));

            function formatTime(seconds) {
                if (!Number.isFinite(seconds) || seconds < 0) return '0:00';

                const minutes = Math.floor(seconds / 60);
                const remainder = Math.floor(seconds % 60);

                return `${minutes}:${String(remainder).padStart(2, '0')}`;
            }

            function updateProgress() {
                const total = Number.isFinite(audio.duration) ? audio.duration : 0;
                const current = audio.currentTime || 0;
                const percentage = total > 0 ? current / total : 0;
                const playedBars = Math.floor(percentage * bars.length);

                bars.forEach((bar, index) => {
                    bar.classList.toggle('played', index < playedBars);
                });

                currentTime.textContent = formatTime(current);
                duration.textContent = formatTime(total);

                waveform.setAttribute(
                    'aria-valuenow',
                    String(Math.round(percentage * 100))
                );

                waveform.setAttribute(
                    'aria-valuetext',
                    `${formatTime(current)} of ${formatTime(total)}`
                );
            }

            function updatePlayButton(playing) {
                playButton.innerHTML = playing ?
                    '<i class="fa-solid fa-pause"></i>' :
                    '<i class="fa-solid fa-play"></i>';

                playButton.setAttribute(
                    'aria-label',
                    playing ? 'Pause voice message' : 'Play voice message'
                );

                wrapper.classList.toggle('is-playing', playing);
            }

            playButton.addEventListener('click', async () => {
                if (audio.paused) {
                    document.querySelectorAll('.bubble-audio-source').forEach(other => {
                        if (other !== audio) {
                            other.pause();
                        }
                    });

                    try {
                        await audio.play();
                    } catch (error) {
                        console.error('GhostOrchid audio playback error:', error);
                    }
                } else {
                    audio.pause();
                }
            });

            audio.addEventListener('play', () => updatePlayButton(true));
            audio.addEventListener('pause', () => updatePlayButton(false));
            audio.addEventListener('loadedmetadata', updateProgress);
            audio.addEventListener('durationchange', updateProgress);
            audio.addEventListener('timeupdate', updateProgress);

            audio.addEventListener('ended', () => {
                audio.currentTime = 0;
                updateProgress();
                updatePlayButton(false);
            });

            audio.addEventListener('error', () => {
                playButton.disabled = true;
                playButton.setAttribute('aria-label', 'Audio unavailable');
            });

            function seekToPosition(clientX) {
                if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;

                const bounds = waveform.getBoundingClientRect();
                if (!bounds.width) return;

                const ratio = Math.max(
                    0,
                    Math.min(1, (clientX - bounds.left) / bounds.width)
                );

                audio.currentTime = ratio * audio.duration;
                updateProgress();
            }

            waveform.addEventListener('click', event => {
                seekToPosition(event.clientX);
            });

            waveform.addEventListener('keydown', event => {
                if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;

                if (event.key === 'ArrowRight') {
                    audio.currentTime = Math.min(
                        audio.duration,
                        audio.currentTime + 5
                    );
                } else if (event.key === 'ArrowLeft') {
                    audio.currentTime = Math.max(0, audio.currentTime - 5);
                } else if (event.key === 'Home') {
                    audio.currentTime = 0;
                } else if (event.key === 'End') {
                    audio.currentTime = audio.duration;
                } else {
                    return;
                }

                event.preventDefault();
                updateProgress();
            });

            return wrapper;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const config = window.ghostorchidChat;
            const form = document.getElementById('messageForm');
            const input = document.getElementById('messageInput');
            const list = document.getElementById('messagesScroll');
            const status = document.getElementById('composerStatus');
            const sendButton = form?.querySelector('.send-button');

            const imageButton = document.getElementById('imageButton');
            const attachmentButton = document.getElementById('attachmentButton');
            const locationButton = document.getElementById('locationButton');
            const voiceButton = document.getElementById('voiceButton');

            const imageInput = document.getElementById('imageInput');
            const attachmentInput = document.getElementById('attachmentInput');

            const preview = document.getElementById('composerPreview');
            const imagePreview = document.getElementById('composerImagePreview');
            const fileName = document.getElementById('composerFileName');
            const fileIcon = document.getElementById('composerFileIcon');
            const removePreview = document.getElementById('removeComposerFile');

            const messageTypeInput = document.getElementById('messageType');
            const latitudeInput = document.getElementById('messageLatitude');
            const longitudeInput = document.getElementById('messageLongitude');

            const recorderPanel = document.getElementById('voiceRecorder');
            const voiceStatus = document.getElementById('voiceStatus');
            const voiceTimer = document.getElementById('voiceTimer');
            const stopVoiceButton = document.getElementById('stopVoiceButton');
            const cancelVoiceButton = document.getElementById('cancelVoiceButton');
            const voicePreview = document.getElementById('voicePreview');

            const detailsToggle = document.getElementById('roomDetailsToggle');
            const detailsContent = document.getElementById('roomDetailsContent');

            const replyPreview = document.getElementById('replyPreview');
            const replyPreviewUser = document.getElementById('replyPreviewUser');
            const replyPreviewText = document.getElementById('replyPreviewText');
            const cancelReply = document.getElementById('cancelReply');

            let replyingToMessageId = null;

            function clearReplyPreview() {
                replyingToMessageId = null;

                if (replyPreview) replyPreview.hidden = true;
                if (replyPreviewUser) replyPreviewUser.textContent = 'Replying to';
                if (replyPreviewText) replyPreviewText.textContent = '';
            }

            let selectedImage = null;
            let selectedAttachment = null;
            let selectedVoice = null;
            let selectedLocation = null;

            let recording = false;
            let cancellingRecording = false;
            let mediaRecorder = null;
            let audioChunks = [];
            let audioStream = null;
            let recordingStartedAt = 0;
            let recordingInterval = null;
            let sending = false;

            function showStatus(message, error = false) {
                if (!status) return;

                status.textContent = message;
                status.style.color = error ? '#ff6b6b' : '';
            }

            function setMessageType(type) {
                if (messageTypeInput) messageTypeInput.value = type;
            }

            function clearPreview() {
                selectedImage = null;
                selectedAttachment = null;
                selectedVoice = null;
                selectedLocation = null;

                if (imagePreview) {
                    if (imagePreview.src.startsWith('blob:')) {
                        URL.revokeObjectURL(imagePreview.src);
                    }

                    imagePreview.removeAttribute('src');
                    imagePreview.hidden = true;
                }

                if (fileName) fileName.textContent = '';
                if (fileIcon) fileIcon.className = 'fa-solid fa-file';
                if (preview) preview.hidden = true;

                if (voicePreview) {
                    if (voicePreview.src.startsWith('blob:')) {
                        URL.revokeObjectURL(voicePreview.src);
                    }

                    voicePreview.pause();
                    voicePreview.removeAttribute('src');
                    voicePreview.hidden = true;
                    voicePreview.load();
                }

                if (imageInput) imageInput.value = '';
                if (attachmentInput) attachmentInput.value = '';
                if (latitudeInput) latitudeInput.value = '';
                if (longitudeInput) longitudeInput.value = '';

                setMessageType('text');
            }

            function showFilePreview(file, type) {
                if (!preview) return;

                preview.hidden = false;

                if (imagePreview) {
                    imagePreview.hidden = type !== 'image';

                    if (type === 'image') {
                        if (imagePreview.src.startsWith('blob:')) {
                            URL.revokeObjectURL(imagePreview.src);
                        }

                        imagePreview.src = URL.createObjectURL(file);
                    } else {
                        imagePreview.removeAttribute('src');
                    }
                }

                if (fileName) fileName.textContent = file.name;

                if (fileIcon) {
                    fileIcon.className = type === 'image' ?
                        'fa-solid fa-image' :
                        'fa-solid fa-file-lines';
                }

                setMessageType(type);
                showStatus('');
            }

            function messageActionButtons(isOwnMessage, messageType = 'text') {
                const tools = document.createElement('div');
                tools.className = 'message-tools';

                const trigger = document.createElement('button');
                trigger.type = 'button';
                trigger.className = 'message-menu-trigger';
                trigger.setAttribute('aria-label', 'Message options');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.title = 'Message options';
                trigger.innerHTML =
                    '<i class="fa-solid fa-ellipsis-vertical"></i>';

                const actions = document.createElement('div');
                actions.className = 'message-actions';
                actions.hidden = true;

                const reply = document.createElement('button');
                reply.type = 'button';
                reply.className = 'reply-message-btn reply';
                reply.title = 'Reply to message';
                reply.setAttribute('aria-label', 'Reply to message');
                reply.innerHTML = '<i class="fa-solid fa-reply"></i>';

                actions.appendChild(reply);

                if (isOwnMessage) {
                    if (messageType === 'text') {
                        const edit = document.createElement('button');
                        edit.type = 'button';
                        edit.className = 'message-action-btn';
                        edit.dataset.messageAction = 'edit';
                        edit.title = 'Edit message';
                        edit.setAttribute('aria-label', 'Edit message');
                        edit.innerHTML = '<i class="fa-solid fa-pen"></i>';

                        actions.appendChild(edit);
                    }

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'message-action-btn delete';
                    remove.dataset.messageAction = 'delete';
                    remove.title = 'Delete message';
                    remove.setAttribute('aria-label', 'Delete message');
                    remove.innerHTML = '<i class="fa-solid fa-trash"></i>';

                    actions.appendChild(remove);
                }

                tools.append(trigger, actions);

                return tools;
            }

            function messageEndpoint(template, id) {
                return String(template || '').replace('__MESSAGE_ID__', encodeURIComponent(id));
            }

            async function messageActionRequest(url, method, body = null) {
                const csrf = config?.csrf || document.querySelector('#messageForm [name="_token"]')?.value;
                const headers = {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-Requested-With': 'XMLHttpRequest'
                };

                const options = {
                    method,
                    headers
                };

                if (body !== null) {
                    headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify(body);
                }

                const response = await fetch(url, options);
                let data = {};

                try {
                    data = await response.json();
                } catch (_) {}

                if (!response.ok) {
                    throw new Error(data.message || 'The message action failed. Please try again.');
                }

                return data;
            }

            function setupMessageActions(row) {
                if (!row || row.dataset.messageActionsReady === 'true') return;
                row.dataset.messageActionsReady = 'true';

                const tools = row.querySelector('.message-tools');
                const trigger = row.querySelector('.message-menu-trigger');
                const actionsMenu = row.querySelector('.message-actions');
                const bubbleForPress = row.querySelector('.message-bubble');
                let pressTimer = null;
                let longPressFired = false;

                function closeMenu() {
                    if (tools) tools.classList.remove('is-open');
                    if (actionsMenu) actionsMenu.hidden = true;
                    if (trigger) trigger.setAttribute('aria-expanded', 'false');
                }

                function toggleMenu() {
                    if (!tools || !actionsMenu) return;
                    const opening = !tools.classList.contains('is-open');
                    document.querySelectorAll('.message-tools.is-open').forEach(openTools => {
                        openTools.classList.remove('is-open');
                        const openActions = openTools.querySelector('.message-actions');
                        if (openActions) openActions.hidden = true;
                        openTools.querySelector('.message-menu-trigger')?.setAttribute('aria-expanded',
                            'false');
                    });
                    tools.classList.toggle('is-open', opening);
                    actionsMenu.hidden = !opening;
                    trigger?.setAttribute('aria-expanded', String(opening));
                }

                trigger?.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    toggleMenu();
                });

                if (bubbleForPress && tools) {
                    bubbleForPress.addEventListener('contextmenu', function(event) {
                        if (window.matchMedia('(max-width: 768px)').matches) {
                            event.preventDefault();
                            toggleMenu();
                        }
                    });

                    bubbleForPress.addEventListener('pointerdown', function(event) {
                        if (!window.matchMedia('(max-width: 768px)').matches || event.pointerType ===
                            'mouse') return;
                        longPressFired = false;
                        pressTimer = window.setTimeout(function() {
                            longPressFired = true;
                            toggleMenu();
                        }, 500);
                    });

                    ['pointerup', 'pointerleave', 'pointercancel', 'pointermove'].forEach(type => {
                        bubbleForPress.addEventListener(type, function() {
                            if (pressTimer) window.clearTimeout(pressTimer);
                            pressTimer = null;
                        }, {
                            passive: true
                        });
                    });

                    bubbleForPress.addEventListener('click', function(event) {
                        if (longPressFired) {
                            event.preventDefault();
                            event.stopPropagation();
                            longPressFired = false;
                        }
                    }, true);
                }

                row.addEventListener('click', async function(event) {
                    const button = event.target.closest('[data-message-action]');
                    if (!button || !row.contains(button)) return;

                    const action = button.dataset.messageAction;
                    const bubble = row.querySelector('.message-bubble');
                    const actions = row.querySelector('.message-actions');
                    const id = row.dataset.messageId;

                    if (!bubble || !id || !config) return;
                    closeMenu();

                    if (action === 'delete') {
                        if (!window.confirm('Delete this message? This cannot be undone.')) return;

                        button.disabled = true;

                        try {
                            await messageActionRequest(
                                messageEndpoint(config.deleteUrlTemplate, id),
                                'DELETE'
                            );
                            removeMessageFromChat(id);

                            if (list && !list.querySelector('.message-row')) {
                                const empty = document.createElement('div');
                                empty.className = 'empty-messages';
                                empty.id = 'emptyMessages';
                                empty.innerHTML =
                                    '<div class="empty-icon"><i class="fa-regular fa-paper-plane"></i></div><h3>👋 Start the conversation!</h3><p>💬 Send the first message and make someone\'s day a little brighter. 💜</p>';
                                list.appendChild(empty);
                            }
                        } catch (error) {
                            window.alert(error.message || 'Unable to delete this message.');
                            button.disabled = false;
                        }

                        return;
                    }

                    if (action !== 'edit' || row.querySelector('.message-edit-input')) return;

                    const originalText = bubble.textContent || '';
                    const editor = document.createElement('textarea');
                    editor.className = 'message-edit-input';
                    editor.maxLength = 5000;
                    editor.value = originalText;
                    editor.setAttribute('aria-label', 'Edit message');

                    const controls = document.createElement('div');
                    controls.className = 'message-edit-controls';

                    const cancel = document.createElement('button');
                    cancel.type = 'button';
                    cancel.className = 'message-action-btn';
                    cancel.textContent = 'Cancel';

                    const save = document.createElement('button');
                    save.type = 'button';
                    save.className = 'message-action-btn';
                    save.innerHTML = '<span>Save</span>';

                    bubble.replaceChildren(editor);
                    controls.append(cancel, save);
                    if (actions) actions.hidden = true;
                    bubble.appendChild(controls);
                    editor.focus();
                    editor.setSelectionRange(editor.value.length, editor.value.length);

                    cancel.addEventListener('click', function() {
                        bubble.textContent = originalText;
                        if (actions) actions.hidden = false;
                    });

                    save.addEventListener('click', async function() {
                        const newBody = editor.value.trim();

                        if (!newBody) {
                            window.alert('Message cannot be empty.');
                            editor.focus();
                            return;
                        }

                        save.disabled = true;
                        cancel.disabled = true;

                        try {
                            const result = await messageActionRequest(
                                messageEndpoint(config.updateUrlTemplate, id),
                                'PATCH', {
                                    body: newBody
                                }
                            );

                            bubble.textContent = result.message?.body ?? newBody;
                            if (actions) actions.hidden = false;
                        } catch (error) {
                            window.alert(error.message || 'Unable to edit this message.');
                            save.disabled = false;
                            cancel.disabled = false;
                        }
                    });
                });
            }




            function createReplyQuote(replyTo) {
                if (!replyTo) return null;

                const quote = document.createElement('div');
                quote.className = 'message-reply-quote';

                const text = document.createElement('span');
                text.className = 'message-reply-quote-text';

                const type = (replyTo.type || 'text').toLowerCase();

                switch (type) {
                    case 'image':
                        text.textContent = 'Photo';
                        break;
                    case 'voice':
                    case 'audio':
                        text.textContent = 'Voice message';
                        break;
                    case 'attachment':
                        text.textContent = replyTo.file_name || 'File attachment';
                        break;
                    case 'location':
                        text.textContent = 'Shared location';
                        break;
                    default:
                        text.textContent = replyTo.body || 'Message';
                }

                quote.appendChild(text);
                return quote;
            }



            document.addEventListener('click', function(event) {
                if (!(event.target instanceof Element)) return;

                const button = event.target.closest('.reply-message-btn');
                if (!button) return;

                const id = button.dataset.messageId;

                if (!id || !replyPreview) return;

                replyingToMessageId = id;

                if (replyPreviewUser) {
                    replyPreviewUser.textContent =
                        `Replying to ${button.dataset.userName || 'Unknown user'}`;
                }

                if (replyPreviewText) {
                    replyPreviewText.textContent =
                        button.dataset.messageText || 'Message';
                }

                replyPreview.hidden = false;
                input?.focus();
            });

            cancelReply?.addEventListener('click', function(event) {
                event.preventDefault();
                clearReplyPreview();
            });





            function appendMessage(payload) {
                const message = payload?.message || payload;

                if (!message?.id || !list) return;

                const conversationId = String(config?.conversationId ?? '');

                if (
                    message.conversation_id != null &&
                    String(message.conversation_id) !== conversationId
                ) {
                    return;
                }

                const id = String(message.id);

                if (list.querySelector(`[data-message-id="${CSS.escape(id)}"]`)) {
                    return;
                }

                document.getElementById('emptyMessages')?.remove();

                const mine = Number(message.user_id) === Number(config?.currentUserId);

                const row = document.createElement('div');
                row.className = 'message-row' + (mine ? ' mine' : '');
                row.dataset.messageId = id;

                const avatar = document.createElement('img');
                avatar.className = 'message-avatar';
                avatar.alt = '';
                avatar.loading = 'lazy';
                avatar.src = message.user?.avatar_url || '/images/default-avatar.png';

                avatar.onerror = function() {
                    this.onerror = null;
                    this.src = '/images/default-avatar.png';
                };

                const content = document.createElement('div');
                content.className = 'message-content';

                const meta = document.createElement('div');
                meta.className = 'message-meta';

                const name = document.createElement('b');
                name.textContent = mine ?
                    '😊 You' :
                    (message.user?.name || 'Unknown user');

                const time = document.createElement('time');

                if (message.created_at) {
                    const date = new Date(message.created_at);

                    if (!Number.isNaN(date.getTime())) {
                        time.dateTime = date.toISOString();
                        time.textContent = date.toLocaleTimeString([], {
                            hour: 'numeric',
                            minute: '2-digit'
                        });
                    }
                }

                const bubble = document.createElement('div');
                bubble.className = 'message-bubble';

                const replyQuote = createReplyQuote(message.reply_to);

                if (replyQuote) {
                    bubble.appendChild(replyQuote);
                }

                const type = message.type || 'text';
                const mediaUrl =
                    message.media_url ||
                    message.attachment_url ||
                    message.file_url;

                if (type === 'image' && mediaUrl) {
                    const image = document.createElement('img');
                    image.src = mediaUrl;
                    image.alt = message.file_name || 'Shared image';
                    image.loading = 'lazy';
                    image.style.cssText =
                        'max-width:100%;max-height:360px;border-radius:12px;display:block';

                    image.addEventListener('click', function() {
                        window.open(image.src, '_blank', 'noopener,noreferrer');
                    });

                    bubble.appendChild(image);

                } else if ((type === 'voice' || type === 'audio') && mediaUrl) {
                    bubble.appendChild(createVoicePlayer(mediaUrl));

                } else if (type === 'attachment' && mediaUrl) {
                    const link = document.createElement('a');
                    link.href = mediaUrl;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = '📎 ' + (message.file_name || 'Download attachment');

                    bubble.appendChild(link);

                } else if (type === 'location') {
                    if (message.latitude != null && message.longitude != null) {
                        const link = document.createElement('a');

                        link.href =
                            `https://www.google.com/maps?q=${encodeURIComponent(message.latitude)},${encodeURIComponent(message.longitude)}`;

                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.textContent = '📍 View shared location';

                        bubble.appendChild(link);
                    } else {
                        const text = document.createElement('div');
                        text.textContent = message.body || '📍 Location shared';
                        bubble.appendChild(text);
                    }

                } else {
                    const text = document.createElement('div');
                    text.className = 'message-text';
                    text.textContent = message.body || '';
                    bubble.appendChild(text);
                }
                meta.append(name, time);
                content.append(meta, bubble);

                const tools = messageActionButtons(mine, type);
                content.appendChild(tools);

                const replyButton = tools.querySelector('.reply-message-btn');

                if (replyButton) {
                    replyButton.dataset.messageId = id;
                    replyButton.dataset.userName =
                        message.user?.name || 'Unknown user';

                    replyButton.dataset.messageText =
                        message.body ||
                        message.file_name ||
                        (type === 'image' ? 'Photo' : ['voice', 'audio'].includes(type) ? 'Voice message' :
                            type === 'attachment' ? 'File attachment' :
                            type === 'location' ? 'Shared location' :
                            'Message');
                }

                row.append(avatar, content);
                list.appendChild(row);

                setupMessageActions(row);

                if (typeof setupSwipeToReply === 'function') {
                    setupSwipeToReply(row, mine);
                }

                list.scrollTop = list.scrollHeight;
            }

            function removeMessageFromChat(messageId) {
                if (!list || messageId == null) return;

                const id = String(messageId);

                const row = Array.from(
                    list.querySelectorAll('.message-row[data-message-id]')
                ).find(messageRow => messageRow.dataset.messageId === id);

                if (!row) return;

                row.classList.add('message-deleted');
                row.dataset.deleted = 'true';

                const bubble = row.querySelector(
                    '.message-bubble, .message-content, .message-body'
                );

                if (bubble) {
                    bubble.replaceChildren();
                    bubble.classList.add('deleted-message-bubble');

                    const deletedText = document.createElement('span');
                    deletedText.className = 'deleted-message-text';
                    deletedText.textContent = 'Deleted message';

                    bubble.appendChild(deletedText);
                } else {
                    row.replaceChildren();

                    const deletedText = document.createElement('span');
                    deletedText.className = 'deleted-message-text';
                    deletedText.textContent = 'Deleted message';

                    row.appendChild(deletedText);
                }

                row.querySelectorAll(
                    '.message-actions, .message-reply, .message-reply-preview, .message-media, .voice-player, audio, video, img, button'
                ).forEach(element => element.remove());
            }

            list?.querySelectorAll('.message-row[data-message-id]').forEach(setupMessageActions);

            document.addEventListener('click', function(event) {
                if (event.target.closest('.message-tools')) return;
                document.querySelectorAll('.message-tools.is-open').forEach(tools => {
                    tools.classList.remove('is-open');
                    const actions = tools.querySelector('.message-actions');
                    if (actions) actions.hidden = true;
                    tools.querySelector('.message-menu-trigger')?.setAttribute('aria-expanded',
                        'false');
                });
            });

            if (
                form &&
                input &&
                list &&
                config &&
                sendButton &&
                form.dataset.chatInitialized !== 'true'
            ) {
                form.dataset.chatInitialized = 'true';

                const csrf =
                    config.csrf ||
                    form.querySelector('[name="_token"]')?.value;

                async function sendMessage() {
                    if (sending || recording) return;

                    const body = input.value.trim();

                    if (
                        !body &&
                        !selectedImage &&
                        !selectedAttachment &&
                        !selectedVoice &&
                        !selectedLocation
                    ) {
                        input.focus();
                        return;
                    }

                    if (!csrf) {
                        showStatus('Session token missing. Refresh the page.', true);
                        return;
                    }

                    const type = selectedImage ? 'image' :
                        selectedAttachment ? 'attachment' :
                        selectedVoice ? 'voice' :
                        selectedLocation ? 'location' :
                        'text';

                    const formData = new FormData();

                    formData.append('body', body);
                    formData.append('type', type);


                    if (replyingToMessageId !== null) {
                        formData.append(
                            'reply_to_message_id',
                            String(replyingToMessageId)
                        );
                    }

                    if (selectedImage) {
                        formData.append('image', selectedImage);
                    }

                    if (selectedAttachment) {
                        formData.append('attachment', selectedAttachment);
                    }

                    if (selectedVoice) {
                        formData.append(
                            'voice',
                            selectedVoice,
                            selectedVoice.name || 'voice-message.webm'
                        );
                    }

                    if (selectedLocation) {
                        formData.append('latitude', String(selectedLocation.latitude));
                        formData.append('longitude', String(selectedLocation.longitude));
                    }

                    sending = true;
                    sendButton.disabled = true;
                    showStatus('Sending...');

                    try {
                        const headers = {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        };

                        const socketId = window.Echo?.socketId?.();

                        if (socketId) {
                            headers['X-Socket-ID'] = socketId;
                        }

                        const response = await fetch(
                            config.sendUrl || form.action, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers,
                                body: formData
                            }
                        );

                        const contentType =
                            response.headers.get('content-type') || '';

                        if (!contentType.includes('application/json')) {
                            throw new Error(
                                response.status === 419 ?
                                'Session expired. Refresh the page and try again.' :
                                `Server returned HTTP ${response.status}.`
                            );
                        }

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(
                                data.message || 'Message could not be sent.'
                            );
                        }

                        if (!data.message?.id) {
                            throw new Error(
                                'Server did not return the saved message.'
                            );
                        }

                        appendMessage(data.message);

                        input.value = '';
                        input.style.height = 'auto';

                        clearPreview();
                        clearReplyPreview();

                        showStatus('');
                        input.focus();

                    } catch (error) {
                        console.error('GhostOrchid send error:', error);
                        showStatus(
                            error.message || 'Unable to send message.',
                            true
                        );
                    } finally {
                        sending = false;
                        sendButton.disabled = false;
                    }
                }

                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    sendMessage();
                });

                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        form.requestSubmit();
                    }
                });


                if (window.Echo && !window.ghostorchidChatListenerReady) {
                    window.ghostorchidChatListenerReady = true;

                    window.Echo
                        .private(`conversation.${String(config.conversationId)}`)
                        .listen('.message.sent', appendMessage)
                        .listen('.message.deleted', function(event) {
                            if (
                                String(event.conversation_id) !==
                                String(config.conversationId)
                            ) {
                                return;
                            }

                            removeMessageFromChat(event.id);
                        });
                }


                list.scrollTop = list.scrollHeight;
            }

            imageButton?.addEventListener('click', () => imageInput?.click());
            attachmentButton?.addEventListener('click', () => attachmentInput?.click());

            imageInput?.addEventListener('change', function() {
                const file = this.files?.[0];
                if (!file) return;

                if (!file.type.startsWith('image/')) {
                    showStatus('Please select a valid image.', true);
                    this.value = '';
                    return;
                }

                if (file.size > 10 * 1024 * 1024) {
                    showStatus('Image must be 10 MB or smaller.', true);
                    this.value = '';
                    return;
                }

                selectedImage = file;
                selectedAttachment = null;
                selectedVoice = null;
                selectedLocation = null;

                showFilePreview(file, 'image');
            });

            attachmentInput?.addEventListener('change', function() {
                const file = this.files?.[0];
                if (!file) return;

                if (file.size > 20 * 1024 * 1024) {
                    showStatus('Attachment must be 20 MB or smaller.', true);
                    this.value = '';
                    return;
                }

                selectedAttachment = file;
                selectedImage = null;
                selectedVoice = null;
                selectedLocation = null;

                showFilePreview(file, 'attachment');
            });

            removePreview?.addEventListener('click', clearPreview);

            locationButton?.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    showStatus(
                        'Location sharing is not supported by this browser.',
                        true
                    );
                    return;
                }

                showStatus('Getting your location...');

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        selectedLocation = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude
                        };

                        selectedImage = null;
                        selectedAttachment = null;
                        selectedVoice = null;

                        if (latitudeInput) {
                            latitudeInput.value = selectedLocation.latitude;
                        }

                        if (longitudeInput) {
                            longitudeInput.value = selectedLocation.longitude;
                        }

                        if (preview) preview.hidden = false;
                        if (imagePreview) imagePreview.hidden = true;

                        if (fileName) {
                            fileName.textContent = '📍 Location ready to share';
                        }

                        if (fileIcon) {
                            fileIcon.className = 'fa-solid fa-location-dot';
                        }

                        setMessageType('location');
                        showStatus('Location ready. Press Send to share it.');
                    },
                    function(error) {
                        const messages = {
                            1: 'Location permission was denied.',
                            2: 'Your location is currently unavailable.',
                            3: 'Location request timed out.'
                        };

                        showStatus(
                            messages[error.code] || 'Unable to get your location.',
                            true
                        );
                    }, {
                        enableHighAccuracy: true,
                        timeout: 15000,
                        maximumAge: 0
                    }
                );
            });

            function stopAudioStream() {
                if (audioStream) {
                    audioStream.getTracks().forEach(track => track.stop());
                    audioStream = null;
                }
            }

            function resetRecordingUI() {
                recording = false;

                if (recordingInterval) {
                    clearInterval(recordingInterval);
                    recordingInterval = null;
                }

                if (recorderPanel) recorderPanel.hidden = true;
                if (voiceButton) voiceButton.disabled = false;
                if (stopVoiceButton) stopVoiceButton.disabled = false;
                if (cancelVoiceButton) cancelVoiceButton.disabled = false;

                stopAudioStream();
            }

            voiceButton?.addEventListener('click', async function() {
                if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
                    showStatus(
                        'Voice recording is not supported in this browser.',
                        true
                    );
                    return;
                }

                if (!window.isSecureContext) {
                    showStatus(
                        'Voice recording requires HTTPS or localhost.',
                        true
                    );
                    return;
                }

                try {
                    cancellingRecording = false;

                    audioStream = await navigator.mediaDevices.getUserMedia({
                        audio: true
                    });

                    const options = {};

                    if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                        options.mimeType = 'audio/webm;codecs=opus';
                    } else if (MediaRecorder.isTypeSupported('audio/webm')) {
                        options.mimeType = 'audio/webm';
                    }

                    audioChunks = [];
                    mediaRecorder = new MediaRecorder(audioStream, options);

                    recording = true;
                    recordingStartedAt = Date.now();

                    if (recorderPanel) recorderPanel.hidden = false;
                    if (voiceStatus) voiceStatus.textContent = 'Recording...';
                    if (voiceTimer) voiceTimer.textContent = '00:00';
                    if (voiceButton) voiceButton.disabled = true;

                    mediaRecorder.addEventListener('dataavailable', function(event) {
                        if (event.data?.size) {
                            audioChunks.push(event.data);
                        }
                    });






                    document.querySelectorAll('[data-audio-player]').forEach(player => {
                        if (player.dataset.initialized) return;
                        player.dataset.initialized = 'true';

                        const audio = player.querySelector('.bubble-audio-source');
                        const playBtn = player.querySelector('.voice-play-btn');
                        const waveform = player.querySelector('.voice-waveform');
                        const bars = [...waveform.querySelectorAll('span')];
                        const currentTime = player.querySelector('.voice-current-time');
                        const duration = player.querySelector('.voice-duration');
                        const icon = playBtn.querySelector('i');

                        const formatTime = seconds => {
                            if (!Number.isFinite(seconds)) return '0:00';
                            const mins = Math.floor(seconds / 60);
                            const secs = Math.floor(seconds % 60);
                            return `${mins}:${String(secs).padStart(2, '0')}`;
                        };

                        const updateProgress = () => {
                            const progress = audio.duration ? audio.currentTime / audio
                                .duration : 0;
                            const playedBars = Math.floor(progress * bars.length);

                            bars.forEach((bar, index) => {
                                bar.classList.toggle('played', index < playedBars);
                            });

                            waveform.setAttribute('aria-valuenow', Math.round(progress *
                                100));
                            currentTime.textContent = formatTime(audio.currentTime);
                        };

                        playBtn.addEventListener('click', async () => {
                            if (audio.paused) {
                                document.querySelectorAll('.bubble-audio-source')
                                    .forEach(other => {
                                        if (other !== audio) other.pause();
                                    });

                                try {
                                    await audio.play();
                                } catch (error) {
                                    console.error('Unable to play audio:', error);
                                }
                            } else {
                                audio.pause();
                            }
                        });

                        audio.addEventListener('play', () => {
                            icon.className = 'fa-solid fa-pause';
                            playBtn.setAttribute('aria-label', 'Pause voice message');
                        });

                        audio.addEventListener('pause', () => {
                            icon.className = 'fa-solid fa-play';
                            playBtn.setAttribute('aria-label', 'Play voice message');
                        });

                        audio.addEventListener('loadedmetadata', () => {
                            duration.textContent = formatTime(audio.duration);
                            updateProgress();
                        });

                        audio.addEventListener('timeupdate', updateProgress);

                        audio.addEventListener('ended', () => {
                            updateProgress();
                            icon.className = 'fa-solid fa-play';
                            playBtn.setAttribute('aria-label', 'Play voice message');
                        });

                        waveform.addEventListener('click', event => {
                            if (!Number.isFinite(audio.duration) || !audio.duration)
                                return;

                            const rect = waveform.getBoundingClientRect();
                            const progress = Math.max(0, Math.min(1, (event.clientX -
                                rect.left) / rect.width));
                            audio.currentTime = progress * audio.duration;
                        });

                        waveform.addEventListener('keydown', event => {
                            if (!Number.isFinite(audio.duration) || !audio.duration)
                                return;

                            if (event.key === 'ArrowRight' || event.key ===
                                'ArrowLeft') {
                                event.preventDefault();
                                audio.currentTime = Math.max(
                                    0,
                                    Math.min(audio.duration, audio.currentTime + (
                                        event.key === 'ArrowRight' ? 5 : -5))
                                );
                            }
                        });
                    });

                    mediaRecorder.addEventListener('stop', function() {
                        if (!cancellingRecording && audioChunks.length) {
                            const mimeType =
                                mediaRecorder.mimeType || 'audio/webm';

                            selectedVoice = new File(
                                audioChunks,
                                'voice-message.webm', {
                                    type: mimeType
                                }
                            );

                            selectedImage = null;
                            selectedAttachment = null;
                            selectedLocation = null;

                            if (preview) preview.hidden = false;
                            if (imagePreview) imagePreview.hidden = true;

                            if (fileName) {
                                fileName.textContent = '🎙️ Voice message ready';
                            }

                            if (fileIcon) {
                                fileIcon.className = 'fa-solid fa-microphone';
                            }

                            if (voicePreview) {
                                if (voicePreview.src.startsWith('blob:')) {
                                    URL.revokeObjectURL(voicePreview.src);
                                }

                                voicePreview.src = URL.createObjectURL(selectedVoice);
                                voicePreview.hidden = false;
                                voicePreview.load();
                            }

                            setMessageType('voice');
                            showStatus(
                                'Voice message ready. Press Send to share it.'
                            );
                        }

                        audioChunks = [];
                        resetRecordingUI();
                    });

                    mediaRecorder.start();

                    recordingInterval = setInterval(function() {
                        const seconds = Math.floor(
                            (Date.now() - recordingStartedAt) / 1000
                        );

                        const minutes = String(
                            Math.floor(seconds / 60)
                        ).padStart(2, '0');

                        const remainder = String(seconds % 60).padStart(2, '0');

                        if (voiceTimer) {
                            voiceTimer.textContent = `${minutes}:${remainder}`;
                        }
                    }, 500);

                } catch (error) {
                    console.error('GhostOrchid microphone error:', error);

                    recording = false;
                    stopAudioStream();
                    resetRecordingUI();

                    showStatus(
                        'Microphone access failed. Allow microphone permission and try again.',
                        true
                    );
                }
            });

            stopVoiceButton?.addEventListener('click', function() {
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    if (voiceStatus) {
                        voiceStatus.textContent = 'Preparing voice message...';
                    }

                    if (stopVoiceButton) stopVoiceButton.disabled = true;
                    mediaRecorder.stop();
                }
            });

            cancelVoiceButton?.addEventListener('click', function() {
                cancellingRecording = true;
                audioChunks = [];
                selectedVoice = null;

                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    if (voiceStatus) {
                        voiceStatus.textContent = 'Cancelling recording...';
                    }

                    mediaRecorder.stop();
                } else {
                    resetRecordingUI();
                }

                showStatus('Voice recording cancelled.');
            });

            const emojiButton = document.getElementById('emojiButton');
            const emojiPicker = document.getElementById('emojiPicker');
            const emojiClose = document.getElementById('emojiClose');
            const emojiSearch = document.getElementById('emojiSearch');
            const emojiGrid = document.getElementById('emojiGrid');
            const emojiLabel = document.getElementById('emojiCategoryLabel');
            const emojiResultCount = document.getElementById('emojiResultCount');

            if (
                emojiButton &&
                emojiPicker &&
                emojiClose &&
                emojiSearch &&
                emojiGrid &&
                input
            ) {
                const categories = {
                    smileys: {
                        label: 'Smileys & Emotions',
                        items: [
                            ['😀', 'grinning'],
                            ['😃', 'happy smile'],
                            ['😄', 'smile'],
                            ['😁', 'grin'],
                            ['😆', 'laugh'],
                            ['😅', 'sweat smile'],
                            ['🤣', 'rolling laugh'],
                            ['😂', 'tears of joy'],
                            ['🙂', 'slight smile'],
                            ['🙃', 'upside down'],
                            ['😉', 'wink'],
                            ['😊', 'blush happy'],
                            ['😇', 'angel'],
                            ['🥰', 'love face'],
                            ['😍', 'heart eyes'],
                            ['🤩', 'star eyes'],
                            ['😘', 'kiss'],
                            ['😗', 'kissing'],
                            ['😋', 'yummy'],
                            ['😛', 'tongue'],
                            ['😜', 'crazy wink'],
                            ['🤪', 'zany'],
                            ['😎', 'cool'],
                            ['🤓', 'nerd'],
                            ['🧐', 'monocle'],
                            ['🤔', 'thinking'],
                            ['🤫', 'quiet'],
                            ['🤭', 'giggle'],
                            ['🥳', 'party'],
                            ['😏', 'smirk'],
                            ['😴', 'sleep'],
                            ['🥱', 'yawn'],
                            ['😢', 'sad cry'],
                            ['😭', 'crying'],
                            ['😡', 'angry'],
                            ['🤯', 'mind blown'],
                            ['🥺', 'pleading'],
                            ['😱', 'scream'],
                            ['🤗', 'hug'],
                            ['🤒', 'sick'],
                            ['🤧', 'sneeze'],
                            ['🥶', 'cold'],
                            ['🥵', 'hot'],
                            ['🤮', 'vomit'],
                            ['💀', 'skull'],
                            ['🤡', 'clown'],
                            ['👻', 'ghost']
                        ]
                    },
                    people: {
                        label: 'People & Gestures',
                        items: [
                            ['👋', 'wave hello'],
                            ['👌', 'okay'],
                            ['✌️', 'peace'],
                            ['🤞', 'fingers crossed'],
                            ['🤟', 'love you gesture'],
                            ['🤘', 'rock on'],
                            ['👍', 'thumbs up'],
                            ['👎', 'thumbs down'],
                            ['👏', 'clap'],
                            ['🙌', 'celebrate'],
                            ['🙏', 'thanks'],
                            ['💪', 'strong'],
                            ['🧑‍💻', 'developer'],
                            ['💃', 'dancer']
                        ]
                    },
                    hearts: {
                        label: 'Hearts & Love',
                        items: [
                            ['❤️', 'red heart'],
                            ['🧡', 'orange heart'],
                            ['💛', 'yellow heart'],
                            ['💚', 'green heart'],
                            ['💙', 'blue heart'],
                            ['💜', 'purple heart'],
                            ['🖤', 'black heart'],
                            ['🤍', 'white heart'],
                            ['💔', 'broken heart'],
                            ['💕', 'two hearts'],
                            ['💖', 'sparkling heart'],
                            ['🌹', 'rose']
                        ]
                    },
                    animals: {
                        label: 'Animals & Creatures',
                        items: [
                            ['🐶', 'dog'],
                            ['🐱', 'cat'],
                            ['🐭', 'mouse'],
                            ['🐰', 'rabbit'],
                            ['🦊', 'fox'],
                            ['🐻', 'bear'],
                            ['🐼', 'panda'],
                            ['🦁', 'lion'],
                            ['🐸', 'frog'],
                            ['🐵', 'monkey'],
                            ['🐧', 'penguin'],
                            ['🦉', 'owl'],
                            ['🦋', 'butterfly'],
                            ['🐢', 'turtle'],
                            ['🐬', 'dolphin'],
                            ['🦈', 'shark']
                        ]
                    },
                    food: {
                        label: 'Food & Drink',
                        items: [
                            ['🍎', 'apple'],
                            ['🍊', 'orange'],
                            ['🍌', 'banana'],
                            ['🍉', 'watermelon'],
                            ['🍇', 'grapes'],
                            ['🍓', 'strawberry'],
                            ['🥭', 'mango'],
                            ['🍍', 'pineapple'],
                            ['🥑', 'avocado'],
                            ['🍞', 'bread'],
                            ['🧀', 'cheese'],
                            ['🍔', 'burger'],
                            ['🍟', 'fries'],
                            ['🍕', 'pizza'],
                            ['🍜', 'noodles'],
                            ['🍣', 'sushi'],
                            ['🍰', 'cake'],
                            ['🍪', 'cookie'],
                            ['☕', 'coffee']
                        ]
                    },
                    nature: {
                        label: 'Nature & Weather',
                        items: [
                            ['🌸', 'cherry blossom'],
                            ['🌹', 'rose'],
                            ['🌻', 'sunflower'],
                            ['🌱', 'seedling'],
                            ['🍀', 'clover'],
                            ['🌳', 'tree'],
                            ['🍄', 'mushroom'],
                            ['🌍', 'earth'],
                            ['🌙', 'moon'],
                            ['⭐', 'star'],
                            ['✨', 'sparkles'],
                            ['⚡', 'lightning'],
                            ['🔥', 'fire'],
                            ['🌊', 'wave'],
                            ['☀️', 'sun'],
                            ['🌧️', 'rain'],
                            ['❄️', 'snowflake'],
                            ['🌈', 'rainbow']
                        ]
                    },
                    activities: {
                        label: 'Activities & Sports',
                        items: [
                            ['⚽', 'soccer'],
                            ['🏀', 'basketball'],
                            ['🏈', 'football'],
                            ['🎾', 'tennis'],
                            ['🏐', 'volleyball'],
                            ['🥊', 'boxing'],
                            ['🏆', 'trophy'],
                            ['🎮', 'video game'],
                            ['🎨', 'art'],
                            ['🎤', 'microphone'],
                            ['🎧', 'headphones'],
                            ['🎸', 'guitar'],
                            ['🎬', 'movie'],
                            ['🎉', 'party']
                        ]
                    },
                    objects: {
                        label: 'Objects & Technology',
                        items: [
                            ['📱', 'phone'],
                            ['💻', 'laptop'],
                            ['🖥️', 'desktop'],
                            ['⌨️', 'keyboard'],
                            ['📷', 'camera'],
                            ['🔋', 'battery'],
                            ['💡', 'light bulb'],
                            ['💳', 'credit card'],
                            ['🔧', 'wrench'],
                            ['🔒', 'lock'],
                            ['🔑', 'key'],
                            ['📧', 'email'],
                            ['📦', 'package'],
                            ['📚', 'books'],
                            ['📎', 'paperclip']
                        ]
                    },
                    symbols: {
                        label: 'Symbols & Signs',
                        items: [
                            ['💯', 'hundred'],
                            ['✅', 'check'],
                            ['❌', 'cross'],
                            ['⚠️', 'warning'],
                            ['❗', 'exclamation'],
                            ['❓', 'question'],
                            ['🔔', 'bell'],
                            ['🎵', 'music note'],
                            ['➕', 'plus'],
                            ['♾️', 'infinity'],
                            ['➡️', 'right arrow'],
                            ['🌐', 'globe'],
                            ['🔗', 'link'],
                            ['💬', 'chat bubble'],
                            ['🔴', 'red circle']
                        ]
                    }
                };

                let activeCategory = 'smileys';

                function renderEmojis() {
                    const query = emojiSearch.value.trim().toLowerCase();
                    const source = categories[activeCategory];

                    let filtered = Object.values(categories).flatMap(category =>
                        category.items.filter(([emoji, keywords]) =>
                            !query ||
                            emoji.includes(query) ||
                            keywords.toLowerCase().includes(query)
                        )
                    );

                    if (!query) filtered = source.items;

                    emojiGrid.replaceChildren();

                    if (emojiLabel) {
                        emojiLabel.textContent = query ?
                            'Search results' :
                            source.label;
                    }

                    document.querySelectorAll('.emoji-category').forEach(button => {
                        const active =
                            button.dataset.category === activeCategory && !query;

                        button.classList.toggle('active', active);
                        button.setAttribute('aria-pressed', String(active));
                    });

                    if (!filtered.length) {
                        const empty = document.createElement('div');
                        empty.className = 'emoji-empty';
                        empty.textContent = 'No emojis found.';
                        emojiGrid.appendChild(empty);
                    } else {
                        filtered.forEach(([emoji, keywords]) => {
                            const button = document.createElement('button');

                            button.type = 'button';
                            button.className = 'emoji-option';
                            button.textContent = emoji;
                            button.title = keywords;
                            button.setAttribute('aria-label', keywords);

                            button.addEventListener('click', () => insertEmoji(emoji));

                            emojiGrid.appendChild(button);
                        });
                    }

                    if (emojiResultCount) {
                        emojiResultCount.textContent =
                            `${filtered.length} emojis available`;
                    }
                }

                function insertEmoji(emoji) {
                    const start = input.selectionStart ?? input.value.length;
                    const end = input.selectionEnd ?? start;

                    input.setRangeText(emoji, start, end, 'end');
                    input.focus();
                    input.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                }

                function setPickerOpen(open) {
                    emojiPicker.hidden = !open;
                    emojiButton.setAttribute('aria-expanded', String(open));

                    if (open) {
                        renderEmojis();
                        emojiSearch.focus();
                    }
                }

                emojiButton.addEventListener('click', function() {
                    setPickerOpen(emojiPicker.hidden);
                });

                emojiClose.addEventListener('click', function() {
                    setPickerOpen(false);
                    emojiButton.focus();
                });

                emojiSearch.addEventListener('input', renderEmojis);

                document.querySelectorAll('.emoji-category').forEach(button => {
                    button.addEventListener('click', function() {
                        if (categories[this.dataset.category]) {
                            activeCategory = this.dataset.category;
                            emojiSearch.value = '';
                            renderEmojis();
                        }
                    });
                });

                document.addEventListener('click', function(event) {
                    if (
                        !emojiPicker.hidden &&
                        !emojiPicker.contains(event.target) &&
                        !emojiButton.contains(event.target)
                    ) {
                        setPickerOpen(false);
                    }
                });

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && !emojiPicker.hidden) {
                        setPickerOpen(false);
                        emojiButton.focus();
                    }
                });

                renderEmojis();
            }

            const details = document.getElementById('roomDetailsContent');

            if (detailsToggle && details) {
                const media = window.matchMedia('(max-width: 768px)');
                const label = detailsToggle.querySelector('span');

                function syncDetails() {
                    if (!media.matches) {
                        details.classList.remove('is-visible');
                    }

                    const visible =
                        media.matches && details.classList.contains('is-visible');

                    detailsToggle.setAttribute('aria-expanded', String(visible));

                    if (label) {
                        label.textContent = visible ?
                            'Hide conversation details' :
                            'Conversation details';
                    }
                }

                detailsToggle.addEventListener('click', function() {
                    if (!media.matches) return;

                    details.classList.toggle('is-visible');
                    syncDetails();
                });

                if (media.addEventListener) {
                    media.addEventListener('change', syncDetails);
                } else {
                    media.addListener(syncDetails);
                }

                syncDetails();
            }
        });
    </script>
@endpush
