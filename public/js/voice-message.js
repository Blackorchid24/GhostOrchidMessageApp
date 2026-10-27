
(() => {
    function formatTime(seconds) {
        if (!Number.isFinite(seconds) || seconds < 0) {
            return '0:00';
        }

        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);

        return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
    }

    function updateVoicePlayer(player) {
        const audio = player.querySelector('.bubble-audio-source');
        const playBtn = player.querySelector('.voice-play-btn');
        const waveform = player.querySelector('.voice-waveform');
        const currentTime = player.querySelector('.voice-current-time');
        const duration = player.querySelector('.voice-duration');

        if (!audio || !playBtn || !waveform || !currentTime || !duration) {
            return;
        }

        const icon = playBtn.querySelector('i');
        const bars = waveform.querySelectorAll('span');
        const totalDuration = Number.isFinite(audio.duration) ? audio.duration : 0;
        const progress = totalDuration > 0
            ? Math.min(1, audio.currentTime / totalDuration)
            : 0;

        currentTime.textContent = formatTime(audio.currentTime);
        duration.textContent = formatTime(totalDuration);

        waveform.setAttribute('aria-valuenow', String(Math.round(progress * 100)));

        bars.forEach((bar, index) => {
            bar.classList.toggle('played', index < progress * bars.length);
        });

        if (icon) {
            icon.className = audio.paused
                ? 'fa-solid fa-play'
                : 'fa-solid fa-pause';
        }

        playBtn.setAttribute(
            'aria-label',
            audio.paused ? 'Play voice message' : 'Pause voice message'
        );
    }

    function initializeVoicePlayer(player) {
        if (player.dataset.initialized === 'true') {
            updateVoicePlayer(player);
            return;
        }

        const audio = player.querySelector('.bubble-audio-source');
        const playBtn = player.querySelector('.voice-play-btn');
        const waveform = player.querySelector('.voice-waveform');

        if (!audio || !playBtn || !waveform) {
            return;
        }

        player.dataset.initialized = 'true';

        playBtn.addEventListener('click', async () => {
            if (audio.paused) {
                document.querySelectorAll('[data-audio-player] .bubble-audio-source').forEach(otherAudio => {
                    if (otherAudio !== audio) {
                        otherAudio.pause();
                    }
                });

                try {
                    await audio.play();
                } catch (error) {
                    console.error('Unable to play voice message:', error);
                }
            } else {
                audio.pause();
            }

            updateVoicePlayer(player);
        });

        audio.addEventListener('loadedmetadata', () => updateVoicePlayer(player));
        audio.addEventListener('durationchange', () => updateVoicePlayer(player));
        audio.addEventListener('timeupdate', () => updateVoicePlayer(player));
        audio.addEventListener('play', () => updateVoicePlayer(player));
        audio.addEventListener('pause', () => updateVoicePlayer(player));

        audio.addEventListener('ended', () => {
            audio.currentTime = 0;
            updateVoicePlayer(player);
        });

        audio.addEventListener('error', () => {
            console.error('Unable to load voice message audio.');
            updateVoicePlayer(player);
        });

        waveform.addEventListener('click', event => {
            if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
                return;
            }

            const rect = waveform.getBoundingClientRect();
            const progress = Math.max(
                0,
                Math.min(1, (event.clientX - rect.left) / rect.width)
            );

            audio.currentTime = progress * audio.duration;
            updateVoicePlayer(player);
        });

        waveform.addEventListener('keydown', event => {
            if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
                return;
            }

            if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();

                if (event.key === 'Home') {
                    audio.currentTime = 0;
                } else if (event.key === 'End') {
                    audio.currentTime = audio.duration;
                } else {
                    audio.currentTime = Math.max(
                        0,
                        Math.min(
                            audio.duration,
                            audio.currentTime + (event.key === 'ArrowRight' ? 5 : -5)
                        )
                    );
                }

                updateVoicePlayer(player);
            }
        });

        updateVoicePlayer(player);

        if (audio.readyState >= 1) {
            updateVoicePlayer(player);
        }
    }

    function initializeVoicePlayers(root = document) {
        if (root.matches?.('[data-audio-player]')) {
            initializeVoicePlayer(root);
        }

        root.querySelectorAll?.('[data-audio-player]').forEach(initializeVoicePlayer);
    }

    window.initializeVoicePlayers = initializeVoicePlayers;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initializeVoicePlayers());
    } else {
        initializeVoicePlayers();
    }

    const observer = new MutationObserver(mutations => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    initializeVoicePlayers(node);
                }
            });
        }
    });

    function observeVoiceMessages() {
        if (document.body) {
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeVoiceMessages, { once: true });
    } else {
        observeVoiceMessages();
    }
})();
