"use strict";

// ─── Versión del caché ───────────────────────────────────────────────────────
const CACHE_VERSION = "natillera-v4";
const STATIC_CACHE  = CACHE_VERSION + "-static";
const PAGES_CACHE   = CACHE_VERSION + "-pages";
const SYNC_QUEUE    = "natillera-sync-queue";

// Recursos estáticos que se cachean en la instalación
const STATIC_ASSETS = [
    "/css/app.css",
    "/img/semilla.svg",
    "/img/logo.png",
    "/js/app.js",
    "/js/llamado_lista.js",
    "/js/push_client.js",
];

// Páginas que se cachean cuando se visitan
const CACHEABLE_PAGES = [
    "/panel",
    "/socio",
    "/admin/socios",
    "/admin/equipo",
    "/admin/reuniones",
    "/admin/llamado-lista",
    "/admin/prestamos",
    "/admin/liquidacion",
    "/admin/actividades",
    "/admin/creditos",
    "/admin/notificaciones",
    "/login",
];

// ─── INSTALL ─────────────────────────────────────────────────────────────────
self.addEventListener("install", (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch(() => {
                // Si algún asset falla, continuar igual
            });
        }).then(() => self.skipWaiting())
    );
});

// ─── ACTIVATE ────────────────────────────────────────────────────────────────
self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((k) => k !== STATIC_CACHE && k !== PAGES_CACHE)
                    .map((k) => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

// ─── FETCH ───────────────────────────────────────────────────────────────────
self.addEventListener("fetch", (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Solo interceptar solicitudes al mismo origen
    if (url.origin !== self.location.origin) return;

    // Bootstrap CDN y externos: red primero
    if (req.url.includes("cdn.jsdelivr.net") || req.url.includes("sweetalert2")) return;

    // POST interceptado para offline queue
    if (req.method === "POST") {
        event.respondWith(handlePost(req));
        return;
    }

    // Recursos estáticos: red primero con fallback a caché para reflejar actualizaciones al instante
    if (isStaticAsset(url.pathname)) {
        event.respondWith(
            fetchAndCache(req, STATIC_CACHE).catch(() => caches.match(req))
        );
        return;
    }

    // Páginas HTML: red primero, fallback a caché
    if (isNavigationRequest(req)) {
        event.respondWith(
            fetch(req)
                .then((response) => {
                    if (response.ok && CACHEABLE_PAGES.includes(url.pathname)) {
                        const clone = response.clone();
                        caches.open(PAGES_CACHE).then((c) => c.put(req, clone));
                    }
                    return response;
                })
                .catch(() => {
                    return caches.match(req).then((cached) => {
                        if (cached) return cached;
                        // Fallback offline: buscar /panel cacheado
                        return caches.match("/panel") || caches.match("/login");
                    });
                })
        );
        return;
    }
});

// ─── Helpers ─────────────────────────────────────────────────────────────────
function isStaticAsset(pathname) {
    return pathname.startsWith("/css/") ||
           pathname.startsWith("/js/")  ||
           pathname.startsWith("/img/") ||
           pathname.endsWith(".svg")    ||
           pathname.endsWith(".png")    ||
           pathname.endsWith(".ico");
}

function isNavigationRequest(req) {
    return req.mode === "navigate" ||
           (req.headers.get("accept") || "").includes("text/html");
}

async function fetchAndCache(req, cacheName) {
    const response = await fetch(req);
    if (response.ok) {
        const cache = await caches.open(cacheName);
        cache.put(req, response.clone());
    }
    return response;
}

// ─── Manejo de POST offline ──────────────────────────────────────────────────
async function handlePost(req) {
    try {
        // Intentar enviar al servidor directamente
        const response = await fetch(req.clone());
        return response;
    } catch {
        // Sin conexión: guardar en cola IndexedDB
        const body = await req.text();
        await enqueueRequest({
            url: req.url,
            body,
            timestamp: Date.now(),
        });

        // Notificar al cliente que está en modo offline
        const clients = await self.clients.matchAll({ type: "window" });
        clients.forEach((client) => client.postMessage({ type: "OFFLINE_QUEUED", url: req.url }));

        // Respuesta temporal para no romper la UI
        return new Response(
            JSON.stringify({ offline: true, queued: true }),
            {
                status: 202,
                headers: { "Content-Type": "application/json" },
            }
        );
    }
}

// ─── Cola de sincronización con IndexedDB ────────────────────────────────────
function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open("natillera-sync", 1);
        req.onupgradeneeded = (e) => {
            e.target.result.createObjectStore(SYNC_QUEUE, {
                keyPath: "id",
                autoIncrement: true,
            });
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function enqueueRequest(data) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_QUEUE, "readwrite");
        tx.objectStore(SYNC_QUEUE).add(data);
        tx.oncomplete = resolve;
        tx.onerror = () => reject(tx.error);
    });
}

async function dequeueAll() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(SYNC_QUEUE, "readwrite");
        const store = tx.objectStore(SYNC_QUEUE);
        const items = [];
        store.openCursor().onsuccess = (e) => {
            const cursor = e.target.result;
            if (cursor) {
                items.push(cursor.value);
                store.delete(cursor.primaryKey);
                cursor.continue();
            }
        };
        tx.oncomplete = () => resolve(items);
        tx.onerror = () => reject(tx.error);
    });
}

// ─── Background Sync: reenviar cuando vuelva internet ────────────────────────
self.addEventListener("sync", (event) => {
    if (event.tag === "natillera-sync") {
        event.waitUntil(replayQueue());
    }
});

// Fallback: también al reconectar (online event via message)
self.addEventListener("message", (event) => {
    if (event.data?.type === "ONLINE_REPLAY") {
        replayQueue().then(() => {
            event.source?.postMessage({ type: "SYNC_DONE" });
        });
    }
    // Limpiar cola si el usuario cerró sesión
    if (event.data?.type === "SESSION_ENDED") {
        clearQueue();
    }
});

async function replayQueue() {
    const items = await dequeueAll();
    for (const item of items) {
        try {
            await fetch(item.url, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: item.body,
            });
        } catch {
            // Si falla de nuevo, volver a encolar
            await enqueueRequest(item);
        }
    }
    // Notificar a los clientes
    const clients = await self.clients.matchAll({ type: "window" });
    clients.forEach((c) => c.postMessage({ type: "SYNC_DONE" }));
}

async function clearQueue() {
    const db = await openDB();
    const tx = db.transaction(SYNC_QUEUE, "readwrite");
    tx.objectStore(SYNC_QUEUE).clear();
}

// ─── Push Notifications (original) ──────────────────────────────────────────
self.addEventListener("push", (event) => {
    let message = {};
    if (event.data) {
        try {
            message = event.data.json();
        } catch {
            message = { body: event.data.text() };
        }
    }

    const title = typeof message.title === "string" ? message.title : "La Semilla";
    const options = {
        body: typeof message.body === "string" ? message.body : "Tienes una actualización de la natillera.",
        icon: "/img/logo.png",
        badge: "/img/logo.png",
        data: { url: typeof message.url === "string" ? message.url : "/panel" },
        tag: "natillera-notification",
        renotify: false,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    let target = new URL(event.notification.data?.url || "/panel", self.location.origin);
    if (target.origin !== self.location.origin) {
        target = new URL("/panel", self.location.origin);
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
        for (const client of windows) {
            if ("focus" in client) {
                await client.navigate(target.href);
                return client.focus();
            }
        }
        return self.clients.openWindow(target.href);
    })());
});
