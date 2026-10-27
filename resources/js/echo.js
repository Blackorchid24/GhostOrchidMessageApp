
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const key = import.meta.env.VITE_PUSHER_APP_KEY;
const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'ap1';

if (key) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster,
        forceTLS: true,
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo.connector.pusher.connection.bind('connected', () => {
        console.log('[GhostOrchid] Pusher connected successfully.');
    });

    window.Echo.connector.pusher.connection.bind('error', (error) => {
        console.error('[GhostOrchid] Pusher connection error:', error);
    });
} else {
    console.error('[GhostOrchid] Pusher app key is missing.');
}
