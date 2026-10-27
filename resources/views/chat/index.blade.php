@extends('layouts.app')

@section('title', 'Messages')
@section('heading', 'Your conversations')

@section('content')
    <style>
        .inbox-conversation-title.inbox-message-unread,
        .inbox-message-preview.inbox-message-unread {
            font-weight: 700 !important;
        }

        .inbox-conversation-title:not(.inbox-message-unread),
        .inbox-message-preview:not(.inbox-message-unread) {
            font-weight: 400;
        }
    </style>

    <div class="welcome-strip">
        <div>
            <span class="welcome-kicker">YOUR SPACE, AT YOUR PACE ✨</span>
            <h2>
                Good to see you, {{ explode(' ', auth()->user()->name)[0] }} 👋
                <span>✦</span>
            </h2>
            <p>💬 Find a conversation or start something new.</p>
        </div>

        <div class="welcome-orchid">
            <i class="fa-solid fa-spa"></i>
        </div>
    </div>

    @if ($errors->any())
        <div class="conversation-notice error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="mobile-chat-switcher" role="group" aria-label="Chat sections">
        <button type="button" class="mobile-section-button active" data-section="conversations"
            aria-controls="conversationPanel" aria-expanded="true">
            <i class="fa-solid fa-comments"></i>
            <span>Inbox</span>
            <span class="mobile-section-count">{{ $conversations->count() }}</span>
        </button>

        <button type="button" class="mobile-section-button" data-section="directory" aria-controls="directoryPanel"
            aria-expanded="false">
            <i class="fa-solid fa-user-plus"></i>
            <span>New Chat</span>
        </button>
    </div>

    <div class="chat-home-grid">

        <section class="panel conversation-panel mobile-chat-panel" id="conversationPanel"
            data-mobile-panel="conversations">

            <div class="panel-heading">
                <div>
                    <span class="section-kicker">YOUR INBOX</span>
                    <h3>💬 Recent conversations</h3>
                </div>

                <span class="count-pill" title="Total conversations">
                    {{ $conversations->count() }}
                </span>
            </div>

            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input id="conversationSearch" type="search" placeholder="Search conversations..."
                    aria-label="Search conversations">
            </div>

            <div class="conversation-list" id="conversationList">
                <div id="inbox-conversations">
                    @forelse ($conversations as $conversation)
                        @php
                            $other = $conversation->participants->firstWhere('id', '!=', auth()->id());
                            $isGroup = $conversation->type === 'group';
                            $title = $isGroup
                                ? ($conversation->name ?:
                                'Group conversation')
                                : $other?->name ?? 'Private conversation';
                            $preview = $conversation->latestMessage;
                            $currentParticipant = $conversation->participants->firstWhere('id', auth()->id());
                            $lastReadAt = $currentParticipant?->pivot?->last_read_at;
                            $isUnread =
                                $preview &&
                                (int) $preview->user_id !== (int) auth()->id() &&
                                (!$lastReadAt ||
                                    $preview->created_at->gt(\Illuminate\Support\Carbon::parse($lastReadAt)));
                            $previewText = $preview
                                ? ($preview->user_id === auth()->id()
                                        ? 'You: '
                                        : '💬 ' . ($preview->user?->name ?? 'Someone') . ': ') .
                                    ($preview->body ?? '')
                                : '👋 Say hello and start chatting!';
                            $searchText = strtolower($title . ' ' . ($preview?->body ?? ''));
                        @endphp

                        <div class="conversation-item-wrap">
                            <a class="conversation-item" data-search="{{ $searchText }}"
                                href="{{ route('chat.show', $conversation) }}">
                                <div class="conversation-avatar">
                                    @if ($isGroup)
                                        @if ($conversation->avatar_path)
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($conversation->avatar_path) }}"
                                                alt="Group profile" loading="lazy"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <i class="fa-solid fa-user-group group-avatar-fallback"
                                                style="display:none;"></i>
                                        @else
                                            <i class="fa-solid fa-user-group group-avatar-fallback"></i>
                                        @endif
                                    @else
                                        <img src="{{ $other?->avatar_url ?? (auth()->user()->avatar_url ?? asset('images/default-avatar.png')) }}"
                                            alt="Profile" loading="lazy">
                                        <span
                                            class="status-dot {{ $other && $other->last_seen_at && $other->last_seen_at->gt(now()->subMinutes(2)) ? 'online' : '' }}"></span>
                                    @endif
                                </div>

                                <div class="conversation-copy">
                                    <div class="conversation-topline">
                                        <b
                                            class="inbox-conversation-title {{ $isUnread ? 'inbox-message-unread' : '' }}">{{ $title }}</b>
                                        <small>{{ $preview?->created_at?->format('g:i A') ?? '' }}</small>
                                    </div>

                                    <p class="inbox-message-preview {{ $isUnread ? 'inbox-message-unread' : '' }}"
                                        data-conversation-id="{{ $conversation->id }}"
                                        data-message-id="{{ $preview?->id ?? 0 }}">
                                        {{ $previewText }}
                                    </p>
                                </div>
                            </a>

                            <div class="inbox-actions">
                                <button type="button" class="inbox-menu-toggle" aria-label="Conversation options"
                                    aria-expanded="false" aria-haspopup="true" title="More options">
                                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                </button>

                                <div class="inbox-dropdown">
                                    <form method="POST" action="{{ route('chat.destroy', $conversation) }}"
                                        onsubmit="return true;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inbox-delete-btn">
                                            <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                                            <span>Delete conversation</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fa-regular fa-comments"></i>
                            </div>
                            <h3>📭 Your inbox is waiting!</h3>
                            <p>💜 Start a private conversation with someone or create a group to bring everyone together.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

        </section>

        <section class="panel directory-panel mobile-chat-panel" id="directoryPanel" data-mobile-panel="directory" hidden>

            <div class="panel-heading">
                <div>
                    <span class="section-kicker">MAKE A CONNECTION ✨</span>
                    <h3>💌 Start a conversation</h3>
                </div>

                <span class="panel-icon">
                    <i class="fa-solid fa-user-plus"></i>
                </span>
            </div>

            <p class="panel-description">
                Find someone for a private chat or bring your friends together
                in a group. 💜
            </p>

            <form class="new-chat-form" method="POST" action="{{ route('chat.private') }}">
                @csrf

                <label for="privateUserSearch">💬 Search someone to chat with</label>

                <input type="text" id="privateUserSearch" placeholder="Search by name..." autocomplete="off"
                    aria-controls="privateUserResults" aria-expanded="false" required>

                <input type="hidden" name="user_id" id="privateUser" required>

                <div id="privateUserResults" class="private-user-results" role="listbox" hidden>
                    @foreach ($users as $person)
                        <button type="button" class="private-user-result" role="option"
                            data-user-id="{{ $person->id }}" data-user-name="{{ $person->name }}"
                            aria-label="Select {{ $person->name }}">
                            <img class="private-user-avatar" src="{{ $person->avatar_url }}" alt=""
                                loading="lazy"
                                onerror="this.onerror=null;this.src='{{ asset('images/default-avatar.png') }}';">

                            <span class="private-user-name">{{ $person->name }}</span>
                            <i class="fa-solid fa-chevron-right private-user-arrow" aria-hidden="true"></i>
                        </button>
                    @endforeach
                </div>

                <div id="privateUserSelected" aria-live="polite"></div>

                <button class="primary-button full-button" type="submit">
                    Start private chat
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('privateUserSearch');
                    const userIdInput = document.getElementById('privateUser');
                    const resultsContainer = document.getElementById('privateUserResults');
                    const selectedContainer = document.getElementById('privateUserSelected');
                    const form = searchInput.closest('form');

                    const users = Array.from(
                        resultsContainer.querySelectorAll('.private-user-result')
                    );

                    function filterUsers() {
                        const query = searchInput.value.trim().toLowerCase();
                        let matches = 0;

                        users.forEach(function(user) {
                            const name = user.dataset.userName.toLowerCase();
                            const match = query !== '' && name.includes(query);

                            user.hidden = !match;

                            if (match) {
                                matches++;
                            }
                        });

                        resultsContainer.hidden = query === '' || matches === 0;

                        searchInput.setAttribute(
                            'aria-expanded',
                            String(!resultsContainer.hidden)
                        );

                        if (query && matches === 0) {
                            selectedContainer.textContent = 'No matching names found.';
                        } else if (!userIdInput.value) {
                            selectedContainer.textContent = '';
                        }
                    }

                    searchInput.addEventListener('input', function() {
                        userIdInput.value = '';
                        selectedContainer.textContent = '';
                        filterUsers();
                    });

                    users.forEach(function(user) {
                        user.addEventListener('click', function() {
                            const userName = user.dataset.userName;

                            userIdInput.value = user.dataset.userId;
                            searchInput.value = userName;

                            selectedContainer.textContent = 'Selected: ' + userName;
                            selectedContainer.classList.add('user-selected');

                            resultsContainer.hidden = true;
                            searchInput.setAttribute('aria-expanded', 'false');

                            users.forEach(function(item) {
                                const isSelected = item === user;

                                item.setAttribute('aria-selected', String(isSelected));
                                item.classList.toggle('is-selected', isSelected);
                            });
                        });
                    });

                    form.addEventListener('submit', function(event) {
                        if (!userIdInput.value) {
                            event.preventDefault();

                            selectedContainer.textContent =
                                'Please search for and select a person first.';

                            searchInput.focus();
                        }
                    });

                    document.addEventListener('click', function(event) {
                        if (!form.contains(event.target)) {
                            resultsContainer.hidden = true;
                            searchInput.setAttribute('aria-expanded', 'false');
                        }
                    });
                });
            </script>

            <div class="thin-divider"></div>

            <form class="new-chat-form" method="POST" action="{{ route('chat.group') }}" id="newGroupChatForm">
                @csrf

                <label for="groupName">👥 Group name</label>

                <input type="text" id="groupName" name="name" placeholder="e.g. 🎉 Weekend plans" maxlength="100"
                    required>

                <label for="memberSearch">
                    🫂 Invite members
                    <span class="muted">(optional)</span>
                </label>

                <div class="member-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="memberSearch" placeholder="Search members by name..." autocomplete="off"
                        aria-label="Search members by name">
                </div>

                <p class="member-search-hint" id="memberSearchHint">
                    Search for a name to find members to invite.
                </p>

                <div class="member-picker" id="memberPicker">
                    @forelse ($users as $person)
                        <label class="member-option" data-member-name="{{ strtolower($person->name) }}" hidden>
                            <input type="checkbox" name="members[]" value="{{ $person->id }}"
                                id="member-{{ $person->id }}">

                            <img src="{{ $person->avatar_url ?? asset('images/default-avatar.png') }}" alt=""
                                loading="lazy">

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
                    Create group
                </button>
            </form>

        </section>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('conversationSearch');
            const conversationList = document.getElementById('conversationList');

            if (searchInput && conversationList) {
                function filterConversations() {
                    const query = searchInput.value.trim().toLowerCase();

                    conversationList.querySelectorAll('.conversation-item-wrap').forEach(function(wrapper) {
                        const conversation = wrapper.querySelector('.conversation-item');
                        const searchText = (
                            conversation?.dataset.search ||
                            conversation?.textContent ||
                            ''
                        ).toLowerCase();

                        wrapper.hidden = !searchText.includes(query);
                    });
                }

                searchInput.addEventListener('input', filterConversations);
                window.ghostOrchidFilterInbox = filterConversations;
            }

            const switcher = document.querySelector('.mobile-chat-switcher');

            if (!switcher) {
                return;
            }

            const buttons = Array.from(
                switcher.querySelectorAll('[data-section]')
            );

            const panels = Array.from(
                document.querySelectorAll('[data-mobile-panel]')
            );

            const mobileQuery = window.matchMedia('(max-width: 768px)');
            let activeSection = 'conversations';

            function renderSections() {
                buttons.forEach(function(button) {
                    const active = button.dataset.section === activeSection;

                    button.classList.toggle('active', active);
                    button.setAttribute('aria-expanded', String(active));
                });

                panels.forEach(function(panel) {
                    const active = panel.dataset.mobilePanel === activeSection;

                    panel.hidden = mobileQuery.matches && !active;
                    panel.classList.toggle('is-mobile-active', active);
                });
            }

            buttons.forEach(function(button) {
                button.addEventListener('click', function() {
                    activeSection = button.dataset.section;
                    renderSections();
                });
            });

            if (mobileQuery.addEventListener) {
                mobileQuery.addEventListener('change', renderSections);
            } else {
                mobileQuery.addListener(renderSections);
            }

            renderSections();
        });
    </script>


    <script>
        (function() {
            if (window.ghostOrchidInboxInitialized) return;
            window.ghostOrchidInboxInitialized = true;

            function initializeInbox() {
                const inbox = document.getElementById('inbox-conversations');
                if (!inbox) return;

                let updating = false;
                const subscribedConversationIds = new Set();

                function applyReadState() {
                    inbox.querySelectorAll('.inbox-message-preview').forEach(preview => {
                        const wrap = preview.closest('.conversation-item-wrap');
                        const title = wrap?.querySelector('.inbox-conversation-title');
                        const unread = preview.classList.contains('inbox-message-unread');
                        title?.classList.toggle('inbox-message-unread', unread);
                    });
                }

                function subscribeToConversationChannels() {
                    if (!window.Echo || typeof window.Echo.private !== 'function') return;

                    inbox.querySelectorAll('.inbox-message-preview[data-conversation-id]').forEach(preview => {
                        const conversationId = String(preview.dataset.conversationId || '');
                        if (!conversationId || subscribedConversationIds.has(conversationId)) return;

                        try {
                            subscribedConversationIds.add(conversationId);
                            window.Echo.private(`conversation.${conversationId}`)
                                .listen('.message.sent', function() {
                                    refreshInbox();
                                });
                        } catch (error) {
                            subscribedConversationIds.delete(conversationId);
                            console.error('Inbox realtime subscription failed:', error);
                        }
                    });
                }

                function subscribeToInboxChannel() {
                    if (!window.Echo || typeof window.Echo.private !== 'function') {
                        return;
                    }

                    window.Echo.private('user.{{ auth()->id() }}.inbox')
                        .listen('.message.sent', function(event) {
                            const message = event.message;

                            if (!message) {
                                return;
                            }

                            refreshInbox();
                        });
                }

                async function refreshInbox() {
                    if (updating || document.hidden || !inbox.isConnected) return;

                    if (inbox.querySelector('.inbox-actions.menu-open')) return;

                    updating = true;

                    try {
                        const response = await fetch(window.location.href, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'text/html'
                            },
                            cache: 'no-store'
                        });

                        if (!response.ok) return;

                        const html = await response.text();
                        const doc = new DOMParser().parseFromString(
                            html,
                            'text/html'
                        );

                        const updatedInbox = doc.getElementById(
                            'inbox-conversations'
                        );

                        const menuIsOpen = inbox.querySelector(
                            '.inbox-actions.menu-open'
                        );

                        if (
                            updatedInbox &&
                            inbox.isConnected &&
                            !menuIsOpen
                        ) {
                            inbox.innerHTML = updatedInbox.innerHTML;
                            applyReadState();
                            subscribeToConversationChannels();
                            window.ghostOrchidFilterInbox?.();
                        }
                    } catch (error) {
                        console.error('Inbox refresh failed:', error);
                    } finally {
                        updating = false;
                    }
                }

                applyReadState();
                subscribeToConversationChannels();

                setInterval(refreshInbox, 1000);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeInbox);
            } else {
                initializeInbox();
            }
        })();
    </script>

    <script>
        (function() {
            if (window.ghostOrchidInboxMenuInitialized) return;
            window.ghostOrchidInboxMenuInitialized = true;

            function closeAllMenus(except = null) {
                document.querySelectorAll('.inbox-actions.menu-open').forEach(actions => {
                    if (actions === except) return;

                    actions.classList.remove('menu-open');

                    const toggle = actions.querySelector('.inbox-menu-toggle');
                    const dropdown = actions.querySelector('.inbox-dropdown');

                    toggle?.setAttribute('aria-expanded', 'false');
                    dropdown?.setAttribute('aria-hidden', 'true');
                });
            }

            document.addEventListener('click', function(event) {
                const toggle = event.target.closest('.inbox-menu-toggle');

                if (toggle) {
                    event.preventDefault();
                    event.stopPropagation();

                    const actions = toggle.closest('.inbox-actions');

                    if (!actions) return;

                    const wasOpen = actions.classList.contains('menu-open');

                    closeAllMenus();

                    if (!wasOpen) {
                        actions.classList.add('menu-open');
                        toggle.setAttribute('aria-expanded', 'true');

                        const dropdown = actions.querySelector('.inbox-dropdown');
                        dropdown?.setAttribute('aria-hidden', 'false');
                    }

                    return;
                }

                if (!event.target.closest('.inbox-actions')) {
                    closeAllMenus();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const openMenu = document.querySelector('.inbox-actions.menu-open');

                    closeAllMenus();

                    openMenu?.querySelector('.inbox-menu-toggle')?.focus();
                }
            });
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('memberSearch');
            const memberPicker = document.getElementById('memberPicker');
            const hint = document.getElementById('memberSearchHint');
            const selectedCount = document.getElementById('memberSelectedCount');

            if (!searchInput || !memberPicker) return;

            const members = Array.from(
                memberPicker.querySelectorAll('.member-option')
            );

            function updateMembers() {
                const query = searchInput.value.trim().toLocaleLowerCase();
                let matches = 0;

                members.forEach(function(member) {
                    const name = member.dataset.memberName || '';
                    const isMatch = query.length > 0 && name.includes(query);

                    member.hidden = !isMatch;

                    if (isMatch) matches++;
                });

                if (query.length === 0) {
                    hint.textContent = 'Search for a name to find members to invite.';
                    hint.hidden = false;
                } else if (matches === 0) {
                    hint.textContent = 'No members found matching "' + searchInput.value.trim() + '".';
                    hint.hidden = false;
                } else {
                    hint.textContent = matches + (matches === 1 ?
                        ' member found.' :
                        ' members found.');
                    hint.hidden = false;
                }

                const checkedCount = members.filter(function(member) {
                    return member.querySelector('input[type="checkbox"]').checked;
                }).length;

                selectedCount.textContent = checkedCount + (
                    checkedCount === 1 ? ' member selected' : ' members selected'
                );

                selectedCount.hidden = checkedCount === 0;
            }

            searchInput.addEventListener('input', updateMembers);

            memberPicker.addEventListener('change', function(event) {
                if (event.target.matches('input[type="checkbox"]')) {
                    updateMembers();
                }
            });

            updateMembers();
        });
    </script>
@endsection
