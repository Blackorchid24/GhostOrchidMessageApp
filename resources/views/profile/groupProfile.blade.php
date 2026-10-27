@extends('layouts.app')

@section('content')
    <section class="group-profile-page">

        <aside class="room-details group-profile-card" id="roomDetailsContent">

            <a href="{{ route('chat.show', $conversation) }}" class="back-link">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Group Chat
            </a>

            @if ($errors->any())
                <div class="conversation-notice error" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="room-profile">


                <form method="POST" action="{{ route('chat.group-avatar', $conversation) }}" enctype="multipart/form-data"
                    class="group-avatar-upload">
                    @csrf

                    <label
                        @if ((int) $conversation->created_by === (int) $currentUser->id) for="groupAvatarInput"
            role="button"
            tabindex="0"
            aria-label="Change group photo" @endif
                        class="room-avatar group-avatar group-avatar-clickable {{ (int) $conversation->created_by === (int) $currentUser->id ? 'can-edit' : '' }}"
                        title="{{ (int) $conversation->created_by === (int) $currentUser->id ? 'Change group photo' : 'Group photo' }}">
                        @if ($conversation->avatar_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($conversation->avatar_path) }}"
                                alt="{{ $conversation->name ?? 'Group' }} profile" class="group-avatar-image">
                        @else
                            <i class="fa-solid fa-users group-avatar-placeholder"></i>
                        @endif

                        @if ((int) $conversation->created_by === (int) $currentUser->id)
                            <span class="group-avatar-edit">
                                <i class="fa-solid fa-camera"></i>
                            </span>
                        @endif
                    </label>

                    @if ((int) $conversation->created_by === (int) $currentUser->id)
                        <input type="file" id="groupAvatarInput" name="avatar" accept="image/jpeg,image/png,image/webp"
                            hidden>
                    @endif
                </form>


                <h3>
                    <i class="fa-solid fa-users"></i>
                    {{ $conversation->name ?? 'Group Chat' }}
                </h3>

                <p>
                    {{ $conversation->participants->count() }} members
                </p>

                <span class="online-label">
                    <span></span>
                    GROUP SPACE
                </span>
            </div>

            @if ((int) $conversation->created_by === (int) $currentUser->id)
                <div class="room-conversation-actions">

                    <details class="conversation-edit room-edit">
                        <summary class="secondary-button">
                            <i class="fa-solid fa-pen"></i>
                            <span>Edit group name</span>
                        </summary>

                        <form method="POST" action="{{ route('chat.update', $conversation) }}"
                            class="conversation-edit-form">
                            @csrf
                            @method('PATCH')

                            <div class="roomGroupName">
                                <input type="text" id="room-group-name" name="name" placeholder="Edit group name"
                                    maxlength="100" required>
                            </div>

                            <button type="submit" class="primary-button full-button">
                                <i class="fa-solid fa-check"></i>
                                Save changes
                            </button>
                        </form>
                    </details>

                </div>
            @endif

            <div class="room-members">

                <div class="members-title">
                    <span>GROUP MEMBERS</span>
                    <span>{{ $conversation->participants->count() }}</span>
                </div>

                @foreach ($conversation->participants as $member)
                    <div class="room-member">

                        <img src="{{ $member->avatar_url ?? asset('images/default-avatar.png') }}" alt=""
                            loading="lazy">

                        <span>
                            {{ $member->name }}

                            @if ((int) $member->id === (int) $currentUser->id)
                                <small>(You)</small>
                            @endif
                        </span>

                        @if ((int) $conversation->created_by === (int) $currentUser->id && (int) $member->id !== (int) $currentUser->id)
                            <form method="POST"
                                action="{{ route('chat.members.remove', [
                                    'conversation' => $conversation->id,
                                    'user' => $member->id,
                                ]) }}"
                                onsubmit="return true;">
                                @csrf
                                @method('DELETE')

                                <button class="tiny-icon" type="submit" title="Remove member"
                                    aria-label="Remove {{ $member->name }} from group">
                                    <i class="fa-solid fa-user-minus"></i>
                                </button>
                            </form>
                        @endif

                    </div>
                @endforeach

                @if ((int) $conversation->created_by === (int) $currentUser->id)
                    <form class="new-chat-form" method="POST" action="{{ route('chat.group') }}" id="newGroupChatForm">
                        @csrf

                        <label for="memberSearch">
                            Invite members
                            <span class="muted">(optional)</span>
                        </label>
                        <p class="member-search-hint" id="memberSearchHint">
                            Search for a name to find members to invite.
                        </p>
                        <div class="member-search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="memberSearch" class="memberSearch"
                                placeholder="Search members by name..." autocomplete="off"
                                aria-label="Search members by name">
                        </div>

                        <div class="member-picker" id="memberPicker">
                            @forelse ($users as $person)
                                <label class="member-option" data-member-name="{{ strtolower($person->name) }}" hidden>
                                    <input type="checkbox" name="members[]" value="{{ $person->id }}"
                                        id="member-{{ $person->id }}">

                                    <img src="{{ $person->avatar_url ?? asset('images/default-avatar.png') }}"
                                        alt="" loading="lazy">

                                    <span>{{ $person->name }}</span>

                                    <i class="fa-solid fa-check member-selected-icon"></i>
                                </label>
                            @empty
                                <p class="muted">No other members are available to invite yet.</p>
                            @endforelse
                        </div>

                        <p class="member-selected-count" id="memberSelectedCount" hidden>
                            0 members selected
                        </p>

                        <button class="secondary-button full-button" type="submit">
                            <i class="fa-solid fa-users"></i>
                            Add member
                        </button>
                    </form>
                @endif

            </div>

            <div class="room-conversation-actions group-profile-danger-actions">

                <form method="POST" action="{{ route('chat.destroy', $conversation) }}" onsubmit="return true;">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="conversation-delete-button room-delete-button">
                        <i class="fa-solid fa-trash"></i>
                        <span>Remove Conversation</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('chat.leave', $conversation) }}" onsubmit="return true;">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="conversation-delete-button room-delete-button">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Leave Group</span>
                    </button>
                </form>

            </div>

            <div class="room-private-note">
                <i class="fa-solid fa-shield-halved"></i>

                <span>
                    <b>A little space to connect</b>
                    <small>Keep conversations respectful, kind, and considerate.</small>
                </span>
            </div>

        </aside>
    </section>
@endsection


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const avatarInput = document.getElementById('groupAvatarInput');
            const avatarLabel = document.querySelector(
                '.group-avatar-clickable[for="groupAvatarInput"]'
            );

            if (!avatarInput) {
                return;
            }

            if (avatarLabel) {
                avatarLabel.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        avatarInput.click();
                    }
                });
            }

            avatarInput.addEventListener('change', function() {
                const file = this.files?.[0];

                if (!file) {
                    return;
                }

                const allowedTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                if (!allowedTypes.includes(file.type)) {
                    alert('Please choose a JPG, PNG, or WEBP image.');
                    this.value = '';
                    return;
                }

                const maxSize = 5 * 1024 * 1024;

                if (file.size > maxSize) {
                    alert('The group photo must be 5 MB or smaller.');
                    this.value = '';
                    return;
                }

                const form = avatarInput.closest('form');

                if (!form) {
                    alert('Unable to find the group photo upload form.');
                    this.value = '';
                    return;
                }

                if (avatarLabel) {
                    avatarLabel.classList.add('is-uploading');
                    avatarLabel.setAttribute('aria-busy', 'true');
                }

                const submitForm = function() {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                };

                submitForm();
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('newGroupChatForm');
            const search = document.getElementById('memberSearch');
            const picker = document.getElementById('memberPicker');
            const hint = document.getElementById('memberSearchHint');
            const count = document.getElementById('memberSelectedCount');

            if (!form || !search || !picker || !hint || !count) return;

            const options = Array.from(
                picker.querySelectorAll('.member-option')
            );

            function filterMembers() {
                const query = search.value.trim().toLowerCase();
                let matches = 0;

                options.forEach(function(option) {
                    const name = option.dataset.memberName || '';
                    const match = query !== '' && name.includes(query);

                    option.hidden = !match;

                    if (match) matches++;
                });

                if (query === '') {
                    hint.textContent = 'Search for a name to find members to invite.';
                } else if (matches === 0) {
                    hint.textContent = 'No members found matching "' + search.value.trim() + '".';
                } else {
                    hint.textContent = matches + (matches === 1 ?
                        ' member found.' :
                        ' members found.');
                }
            }

            function updateSelectedCount() {
                const selectedCount = options.filter(function(option) {
                    return option.querySelector('input[type="checkbox"]').checked;
                }).length;

                count.hidden = selectedCount === 0;
                count.textContent = selectedCount + (
                    selectedCount === 1 ? ' member selected' : ' members selected'
                );
            }

            search.addEventListener('input', filterMembers);

            options.forEach(function(option) {
                const checkbox = option.querySelector('input[type="checkbox"]');

                checkbox.addEventListener('change', updateSelectedCount);
            });

            form.addEventListener('reset', function() {
                setTimeout(function() {
                    search.value = '';
                    filterMembers();
                    updateSelectedCount();
                }, 0);
            });

            filterMembers();
            updateSelectedCount();
        });
    </script>
@endpush
