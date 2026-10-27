import './bootstrap';
import '../css/app.css';

document.addEventListener('DOMContentLoaded', () => {
    const menu = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const scrim = document.getElementById('sidebarScrim');

    const closeMenu = () => {
        sidebar ?.classList.remove('open');
        scrim ?.classList.remove('show');
    };

    menu ?.addEventListener('click', () => {
        sidebar ?.classList.toggle('open');
        scrim ?.classList.toggle('show');
    });

    scrim ?.addEventListener('click', closeMenu);

    document.querySelectorAll('[data-password]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.password);

            if (!input) return;

            input.type = input.type === 'password' ? 'text' : 'password';

            button.innerHTML = input.type === 'password' ?
                '<i class="fa-regular fa-eye"></i>' :
                '<i class="fa-regular fa-eye-slash"></i>';
        });
    });

    const search = document.getElementById('conversationSearch');

    search ?.addEventListener('input', () => {
        const term = search.value.toLowerCase().trim();

        document.querySelectorAll('#conversationList .conversation-item').forEach(item => {
            const searchableText = (item.dataset.search || '').toLowerCase();
            item.style.display = searchableText.includes(term) ? '' : 'none';
        });
    });

    const messagesBox = document.getElementById('messagesScroll');

    if (messagesBox) {
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const imageInput = document.getElementById('imageInput');
    const attachmentInput = document.getElementById('attachmentInput');
    const imageButton = document.getElementById('imageButton');
    const attachmentButton = document.getElementById('attachmentButton');
    const locationButton = document.getElementById('locationButton');
    const voiceButton = document.getElementById('voiceButton');

    const preview = document.getElementById('composerPreview');
    const imagePreview = document.getElementById('composerImagePreview');
    const fileName = document.getElementById('composerFileName');
    const fileIcon = document.getElementById('composerFileIcon');
    const removeFileButton = document.getElementById('removeComposerFile');

    const textarea = document.getElementById('messageInput');
    const status = document.getElementById('composerStatus');

    const recorderPanel = document.getElementById('voiceRecorder');
    const voiceStatus = document.getElementById('voiceStatus');
    const voiceTimer = document.getElementById('voiceTimer');
    const stopVoiceButton = document.getElementById('stopVoiceButton');
    const cancelVoiceButton = document.getElementById('cancelVoiceButton');
    const voicePreview = document.getElementById('voicePreview');

    const messageForm = document.getElementById('messageForm');

    if (
        !imageInput ||
        !attachmentInput ||
        !textarea ||
        !imageButton ||
        !attachmentButton ||
        !locationButton ||
        !voiceButton ||
        !preview ||
        !imagePreview ||
        !fileName ||
        !fileIcon ||
        !removeFileButton ||
        !status ||
        !recorderPanel ||
        !voiceStatus ||
        !voiceTimer ||
        !stopVoiceButton ||
        !cancelVoiceButton ||
        !voicePreview
    ) {
        return;
    }

    let selectedFile = null;
    let mediaRecorder = null;
    let audioChunks = [];
    let stream = null;
    let recordingStartedAt = 0;
    let timerInterval = null;
    let voiceUrl = null;

    function setStatus(message) {
        status.textContent = message;
    }

    function showFile(file, isImage) {
        selectedFile = file;

        preview.hidden = false;
        imagePreview.hidden = true;
        imagePreview.removeAttribute('src');

        fileName.textContent = file.name;

        fileIcon.className = isImage ?
            'fa-regular fa-image' :
            'fa-solid fa-file-lines';

        if (isImage) {
            const reader = new FileReader();

            reader.onload = event => {
                imagePreview.src = event.target.result;
                imagePreview.hidden = false;
            };

            reader.readAsDataURL(file);
        }

        setStatus(
            'File selected. Media upload must be enabled on the server before it can be sent.'
        );
    }

    imageButton.addEventListener('click', () => {
        imageInput.click();
    });

    attachmentButton.addEventListener('click', () => {
        attachmentInput.click();
    });

    imageInput.addEventListener('change', function () {
        const file = this.files[0];

        if (!file) return;

        if (!file.type.startsWith('image/')) {
            setStatus('Please select a valid image.');
            this.value = '';
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            setStatus('The image must be 10 MB or smaller.');
            this.value = '';
            return;
        }

        attachmentInput.value = '';
        showFile(file, true);
    });

    attachmentInput.addEventListener('change', function () {
        const file = this.files[0];

        if (!file) return;

        if (file.size > 20 * 1024 * 1024) {
            setStatus('The attachment must be 20 MB or smaller.');
            this.value = '';
            return;
        }

        imageInput.value = '';
        showFile(file, false);
    });

    removeFileButton.addEventListener('click', () => {
        selectedFile = null;

        imageInput.value = '';
        attachmentInput.value = '';

        preview.hidden = true;
        imagePreview.hidden = true;
        imagePreview.removeAttribute('src');

        fileName.textContent = '';

        setStatus('Selected file removed.');
    });

    locationButton.addEventListener('click', () => {
        if (!navigator.geolocation) {
            setStatus('Location sharing is not supported by this browser.');
            return;
        }

        setStatus('Requesting your location permission...');

        navigator.geolocation.getCurrentPosition(
            position => {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;

                const mapUrl =
                    'https://www.google.com/maps?q=' +
                    latitude +
                    ',' +
                    longitude;

                const locationMessage = '📍 My location: ' + mapUrl;
                const existingText = textarea.value.trim();

                textarea.value = existingText ?
                    existingText + '\n' + locationMessage :
                    locationMessage;

                textarea.dispatchEvent(
                    new Event('input', {
                        bubbles: true
                    })
                );

                textarea.focus();

                setStatus(
                    'Location link added to your message. Press Send to share it.'
                );
            },
            error => {
                const messages = {
                    1: 'Location permission was denied.',
                    2: 'Your location is currently unavailable.',
                    3: 'Location request timed out.'
                };

                setStatus(
                    messages[error.code] ||
                    'Unable to get your location.'
                );
            }, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    });

    function stopTracks() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
    }

    function clearRecordingTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    function formatDuration(milliseconds) {
        const totalSeconds = Math.floor(milliseconds / 1000);
        const minutes = String(
            Math.floor(totalSeconds / 60)
        ).padStart(2, '0');

        const seconds = String(
            totalSeconds % 60
        ).padStart(2, '0');

        return minutes + ':' + seconds;
    }

    function resetRecorder() {
        clearRecordingTimer();
        stopTracks();

        mediaRecorder = null;
        audioChunks = [];

        recorderPanel.hidden = true;
        voiceButton.disabled = false;

        voiceButton.innerHTML =
            '<i class="fa-solid fa-microphone"></i><span>Voice</span>';
    }

    voiceButton.addEventListener('click', async () => {
        if (
            !navigator.mediaDevices ?.getUserMedia ||
            !window.MediaRecorder
        ) {
            setStatus('Voice recording is not supported in this browser.');
            return;
        }

        try {
            if (voiceUrl) {
                URL.revokeObjectURL(voiceUrl);
                voiceUrl = null;
            }

            voicePreview.pause();
            voicePreview.removeAttribute('src');
            voicePreview.load();
            voicePreview.hidden = true;

            stream = await navigator.mediaDevices.getUserMedia({
                audio: true
            });

            const options = MediaRecorder.isTypeSupported(
                'audio/webm;codecs=opus'
            ) ? {
                mimeType: 'audio/webm;codecs=opus'
            } : {};

            mediaRecorder = new MediaRecorder(stream, options);
            audioChunks = [];
            recordingStartedAt = Date.now();

            mediaRecorder.addEventListener('dataavailable', event => {
                if (event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            });

            mediaRecorder.addEventListener('stop', () => {
                clearRecordingTimer();
                stopTracks();

                if (audioChunks.length && mediaRecorder) {
                    const audioBlob = new Blob(audioChunks, {
                        type: mediaRecorder.mimeType || 'audio/webm'
                    });

                    voiceUrl = URL.createObjectURL(audioBlob);
                    voicePreview.src = voiceUrl;
                    voicePreview.hidden = false;

                    voiceStatus.textContent = 'Recording ready to preview';

                    setStatus(
                        'Voice recording created. Sending audio requires server-side upload support.'
                    );
                }

                voiceButton.disabled = false;

                voiceButton.innerHTML =
                    '<i class="fa-solid fa-microphone"></i><span>Voice</span>';
            });

            recorderPanel.hidden = false;
            voicePreview.hidden = true;

            voiceStatus.textContent = 'Recording...';
            voiceButton.disabled = true;

            voiceButton.innerHTML =
                '<i class="fa-solid fa-circle"></i><span>Recording</span>';

            mediaRecorder.start();

            timerInterval = setInterval(() => {
                voiceTimer.textContent = formatDuration(
                    Date.now() - recordingStartedAt
                );
            }, 250);

            setStatus('Recording. Press Stop when you finish.');
        } catch (error) {
            stopTracks();

            setStatus(
                'Microphone access failed. Allow microphone permission and try again.'
            );
        }
    });

    stopVoiceButton.addEventListener('click', () => {
        if (mediaRecorder ?.state === 'recording') {
            mediaRecorder.stop();
        }
    });

    cancelVoiceButton.addEventListener('click', () => {
        const recorder = mediaRecorder;

        if (recorder && recorder.state === 'recording') {
            recorder.addEventListener(
                'stop',
                () => {
                    audioChunks = [];
                    resetRecorder();
                    setStatus('Voice recording cancelled.');
                }, {
                    once: true
                }
            );

            recorder.stop();
        } else {
            audioChunks = [];
            resetRecorder();
            setStatus('Voice recording cancelled.');
        }

        voicePreview.pause();
        voicePreview.hidden = true;
        voicePreview.removeAttribute('src');
        voicePreview.load();

        if (voiceUrl) {
            URL.revokeObjectURL(voiceUrl);
            voiceUrl = null;
        }
    });

    window.addEventListener('beforeunload', () => {
        clearRecordingTimer();
        stopTracks();

        if (voiceUrl) {
            URL.revokeObjectURL(voiceUrl);
        }
    });

    messageForm ?.addEventListener('submit', event => {
        if (selectedFile) {
            event.preventDefault();

            setStatus(
                'Image and file sending require backend upload support.'
            );

            return;
        }

        if (mediaRecorder ?.state === 'recording') {
            event.preventDefault();
            setStatus('Stop the voice recording before sending a message.');
        }
    });
});
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.media-menu-trigger');

    if (trigger) {
        const tools = trigger.closest('.media-delete-tools');
        const actions = tools ?.querySelector('.media-message-actions');

        if (!actions) return;

        const willOpen = actions.hidden;

        document.querySelectorAll('.media-message-actions').forEach(menu => {
            menu.hidden = true;
        });

        document.querySelectorAll('.media-menu-trigger').forEach(button => {
            button.setAttribute('aria-expanded', 'false');
        });

        actions.hidden = !willOpen;
        trigger.setAttribute('aria-expanded', String(willOpen));
        return;
    }

    if (!event.target.closest('.media-delete-tools')) {
        document.querySelectorAll('.media-message-actions').forEach(menu => {
            menu.hidden = true;
        });

        document.querySelectorAll('.media-menu-trigger').forEach(button => {
            button.setAttribute('aria-expanded', 'false');
        });
    }
});
document.addEventListener('click', function (event) {
    const replyButton = event.target.closest('[data-message-action="reply"]');

    if (!replyButton) return;

    const messageId = replyButton.dataset.replyId;
    const userName = replyButton.dataset.replyUser || 'Unknown user';
    const messageBody = replyButton.dataset.replyBody || 'Message';

    const replyIdInput = document.getElementById('replyToMessageId');
    const replyPreview = document.getElementById('replyPreview');
    const replyUser = document.getElementById('replyPreviewUser');
    const replyText = document.getElementById('replyPreviewText');
    const messageInput = document.getElementById('messageInput');

    if (!replyIdInput || !replyPreview || !replyUser || !replyText) return;

    replyIdInput.value = messageId;
    replyUser.textContent = `Replying to ${userName}`;
    replyText.textContent = messageBody;
    replyPreview.hidden = false;

    document.querySelectorAll('.message-actions').forEach(menu => {
        menu.hidden = true;
    });

    document.querySelectorAll('.message-menu-trigger').forEach(button => {
        button.setAttribute('aria-expanded', 'false');
    });

    messageInput ?.focus();
});

document.getElementById('cancelReply') ?.addEventListener('click', function () {
    const replyIdInput = document.getElementById('replyToMessageId');
    const replyPreview = document.getElementById('replyPreview');

    if (replyIdInput) replyIdInput.value = '';
    if (replyPreview) replyPreview.hidden = true;
});
document.addEventListener('click', function (event) {
    const trigger = event.target.closest('.message-menu-trigger');

    if (trigger) {
        const tools = trigger.closest('.message-tools');
        const menu = tools ?.querySelector('.message-actions');

        if (!menu) return;

        const willOpen = menu.hidden;

        document.querySelectorAll('.message-actions').forEach(item => {
            item.hidden = true;
        });

        document.querySelectorAll('.message-menu-trigger').forEach(button => {
            button.setAttribute('aria-expanded', 'false');
        });

        menu.hidden = !willOpen;
        trigger.setAttribute('aria-expanded', String(willOpen));

        event.stopPropagation();
        return;
    }

    if (!event.target.closest('.message-tools')) {
        document.querySelectorAll('.message-actions').forEach(menu => {
            menu.hidden = true;
        });

        document.querySelectorAll('.message-menu-trigger').forEach(button => {
            button.setAttribute('aria-expanded', 'false');
        });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const cameraButton = document.getElementById('cameraButton');
    const modal = document.getElementById('cameraModal');
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    const preview = document.getElementById('cameraPreview');
    const status = document.getElementById('cameraStatus');

    const captureButton = document.getElementById('capturePhotoButton');
    const retakeButton = document.getElementById('retakePhotoButton');
    const useButton = document.getElementById('usePhotoButton');
    const closeButton = document.getElementById('closeCameraButton');

    let stream = null;
    let capturedBlob = null;

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }

        video.srcObject = null;
    }

    async function openCamera() {
        if (!navigator.mediaDevices ?.getUserMedia) {
            status.textContent = 'Camera unavailable. Use HTTPS or localhost and a supported browser.';
            modal.hidden = false;
            captureButton.hidden = true;
            return;
        }

        modal.hidden = false;
        status.textContent = 'Requesting camera permission...';
        captureButton.hidden = false;
        captureButton.disabled = true;
        preview.hidden = true;
        video.hidden = false;
        retakeButton.hidden = true;
        useButton.hidden = true;
        capturedBlob = null;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: 'user',
                    width: {
                        ideal: 1280
                    },
                    height: {
                        ideal: 720
                    }
                },
                audio: false
            });

            video.srcObject = stream;
            await video.play();

            status.textContent = 'Camera ready. Take your photo.';
            captureButton.disabled = false;
        } catch (error) {
            stopCamera();
            captureButton.hidden = true;

            if (error.name === 'NotAllowedError') {
                status.textContent = 'Camera permission denied. Allow camera access in your browser settings.';
            } else if (error.name === 'NotFoundError') {
                status.textContent = 'No webcam was found on this laptop.';
            } else {
                status.textContent = 'Unable to open camera: ' + error.message;
            }
        }
    }

    function closeCamera() {
        stopCamera();
        modal.hidden = true;
        capturedBlob = null;
    }

    cameraButton ?.addEventListener('click', openCamera);
    closeButton ?.addEventListener('click', closeCamera);

    modal.addEventListener('click', event => {
        if (event.target === modal) closeCamera();
    });

    captureButton.addEventListener('click', () => {
        if (!video.videoWidth || !video.videoHeight) {
            status.textContent = 'Please wait for the camera to initialize.';
            return;
        }

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        canvas.getContext('2d').drawImage(
            video, 0, 0, canvas.width, canvas.height
        );

        canvas.toBlob(blob => {
            if (!blob) {
                status.textContent = 'Could not capture the photo. Please try again.';
                return;
            }

            capturedBlob = blob;
            preview.src = URL.createObjectURL(blob);
            preview.hidden = false;
            video.hidden = true;

            captureButton.hidden = true;
            retakeButton.hidden = false;
            useButton.hidden = false;
            status.textContent = 'Photo captured. Use it to continue.';
            stopCamera();
        }, 'image/jpeg', 0.92);
    });

    retakeButton.addEventListener('click', openCamera);

    useButton.addEventListener('click', () => {
        if (!capturedBlob) return;

        const imageInput = document.getElementById('imageInput');

        if (!imageInput) {
            status.textContent = 'Could not find #imageInput. Connect this button to your existing image-upload handler.';
            return;
        }

        const file = new File(
            [capturedBlob],
            'camera-' + Date.now() + '.jpg', {
                type: 'image/jpeg'
            }
        );

        const transfer = new DataTransfer();
        transfer.items.add(file);
        imageInput.files = transfer.files;
        imageInput.dispatchEvent(new Event('change', {
            bubbles: true
        }));

        closeCamera();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modal.hidden) closeCamera();
    });
});


const replyPreview = document.getElementById('replyPreview');
const replyPreviewUser = document.getElementById('replyPreviewUser');
const replyPreviewText = document.getElementById('replyPreviewText');
const cancelReply = document.getElementById('cancelReply');

let replyingToMessageId = null;

function clearReplyPreview() {
    replyingToMessageId = null;

    if (replyPreview) {
        replyPreview.hidden = true;
    }

    if (replyPreviewUser) {
        replyPreviewUser.textContent = 'Replying to';
    }

    if (replyPreviewText) {
        replyPreviewText.textContent = '';
    }
}

document.addEventListener('click', function (event) {
    const target = event.target;

    if (!(target instanceof Element)) return;

    const replyButton = target.closest('.reply-message-btn');

    if (!replyButton) return;

    const messageId = replyButton.dataset.messageId;

    if (!messageId || !replyPreview) return;

    replyingToMessageId = messageId;

    const userName = replyButton.dataset.userName || 'Unknown user';
    const messageText = replyButton.dataset.messageText || 'Message';

    if (replyPreviewUser) {
        replyPreviewUser.textContent = `Replying to ${userName}`;
    }

    if (replyPreviewText) {
        replyPreviewText.textContent = messageText;
    }

    replyPreview.hidden = false;

    const input = document.querySelector(
        '#messageInput, #message-input, textarea[name="body"], input[name="body"]'
    );

    input ?.focus();
});

cancelReply ?.addEventListener('click', function (event) {
    event.preventDefault();
    clearReplyPreview();
});

window.Echo
    .private(`conversation.${config.conversationId}`)
    .listen('.message.deleted', (event) => {
        if (
            String(event.conversation_id) !==
            String(config.conversationId)
        ) {
            return;
        }

        removeMessageFromChat(event.id);
    });

function removeMessageFromChat(messageId) {
    if (!list || !messageId) return;

    const row = list.querySelector(
        `[data-message-id="${CSS.escape(String(messageId))}"]`
    );

    if (row) {
        row.remove();
    }
}
