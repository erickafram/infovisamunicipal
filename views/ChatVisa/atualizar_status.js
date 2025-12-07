// atualizar_status.js - Versão com Debug Logs
const PING_INTERVAL = 30000; // 30 segundos
const INACTIVITY_TIMEOUT = 300000; // 5 minutos
const DEBOUNCE_TIME = 500; // 500ms

const loggedUserId = document.getElementById('logged-user-id')?.value;
if (!loggedUserId) {
    console.error("Elemento 'logged-user-id' não encontrado ou vazio.");
} else {
    console.log("loggedUserId:", loggedUserId);
}

let activityTimeout;
let debounceTimeout;

// Função para enviar status com debug
const sendStatus = async (status) => {
    console.log("Enviando status:", status);
    if (!loggedUserId) return;

    const data = new URLSearchParams();
    data.append('status', status);
    data.append('usuario_id', loggedUserId);
    data.append('force', 'true');

    try {
        const success = navigator.sendBeacon('/visamunicipal/views/ChatVisa/atualizar_status.php', data);
        console.log("navigator.sendBeacon:", success);
        if (!success) {
            await Promise.race([
                fetch('/visamunicipal/views/ChatVisa/atualizar_status.php', {
                    method: 'POST',
                    body: data,
                    keepalive: true
                }),
                new Promise((_, reject) => setTimeout(() => reject(new Error('Timeout')), 1000))
            ]);
            console.log("Fetch fallback enviado com sucesso.");
        }
    } catch (error) {
        console.error("Falha ao enviar status:", error);
        new Image().src = `/visamunicipal/views/ChatVisa/atualizar_status.php?${data.toString()}`;
    }
};

const handleUserActivity = () => {
    console.log("handleUserActivity disparado.");
    clearTimeout(activityTimeout);
    clearTimeout(debounceTimeout);

    debounceTimeout = setTimeout(() => {
        if (document.visibilityState === 'visible') {
            console.log("Heartbeat: Página visível, enviando 'online'.");
            sendStatus('online');
        }
    }, DEBOUNCE_TIME);

    activityTimeout = setTimeout(() => {
        console.log("Inatividade detectada: enviando 'offline'.");
        sendStatus('offline');
    }, INACTIVITY_TIMEOUT);
};

const checkConnectivity = () => {
    if (!navigator.onLine) {
        console.log("Navegador offline, enviando 'offline'.");
        sendStatus('offline');
        return false;
    }
    return true;
};

const maintainPresence = () => {
    if (checkConnectivity() && document.visibilityState === 'visible') {
        console.log("maintainPresence: enviando 'online'.");
        sendStatus('online');
    }
};

const setupEventListeners = () => {
    const activityEvents = [
        'mousemove', 'keydown', 'click', 'scroll',
        'touchstart', 'touchmove', 'touchend'
    ];

    activityEvents.forEach(event => {
        document.addEventListener(event, () => {
            console.log(`Evento ${event} disparado.`);
            handleUserActivity();
        });
    });

    document.addEventListener('visibilitychange', () => {
        console.log("visibilitychange: estado =", document.visibilityState);
        if (document.visibilityState === 'hidden') {
            sendStatus('offline');
        } else {
            handleUserActivity();
        }
    });

    window.addEventListener('online', () => {
        console.log("Evento online disparado.");
        handleUserActivity();
    });
    window.addEventListener('offline', () => {
        console.log("Evento offline disparado.");
        sendStatus('offline');
    });

    window.addEventListener('beforeunload', () => {
        console.log("beforeunload disparado.");
        sendStatus('offline');
    });
    window.addEventListener('pagehide', () => {
        console.log("pagehide disparado.");
        sendStatus('offline');
    });
    window.addEventListener('unload', () => {
        console.log("unload disparado.");
        sendStatus('offline');
    });
};

const initializePresenceSystem = () => {
    if (!loggedUserId) {
        console.error("ID de usuário não encontrado!");
        return;
    }
    setupEventListeners();
    handleUserActivity();
    setInterval(maintainPresence, PING_INTERVAL);
    sendStatus('online');
};

document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM completamente carregado.");
    initializePresenceSystem();
});
window.addEventListener('load', () => {
    console.log("Evento load disparado.");
    sendStatus('online');
    handleUserActivity();
});
