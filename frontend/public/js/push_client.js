(() => {
    "use strict";

    const button = document.querySelector("#enable-push");
    if (!button) return;

    const vapidPublicKey = button.dataset.vapidPublicKey;
    const csrfToken = button.dataset.csrfToken;

    const decodeApplicationKey = (encoded) => {
        const padding = "=".repeat((4 - encoded.length % 4) % 4);
        const base64 = (encoded + padding).replace(/-/g, "+").replace(/_/g, "/");
        const raw = window.atob(base64);
        return Uint8Array.from(raw, (character) => character.charCodeAt(0));
    };

    const post = async (url, data) => {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": csrfToken
            },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "No se pudo actualizar la suscripción.");
        return result;
    };

    const updateButton = (enabled) => {
        button.dataset.enabled = enabled ? "true" : "false";
        button.textContent = enabled ? "♢" : "♢";
        button.title = enabled ? "Desactivar notificaciones en este dispositivo" : "Activar notificaciones";
        button.setAttribute(
            "aria-label",
            enabled ? "Desactivar notificaciones en este dispositivo" : "Activar notificaciones en este dispositivo"
        );
        button.classList.toggle("push-enabled", enabled);
    };

    const showError = (message) => {
        button.title = message;
        window.alert(message);
    };

    const register = async () => {
        if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
            throw new Error("Este navegador no permite notificaciones push.");
        }
        return navigator.serviceWorker.register("/js/sw.js");
    };

    const syncSubscription = async (subscription) => {
        await post("/api/push/suscribir", subscription.toJSON());
        updateButton(true);
    };

    button.addEventListener("click", async () => {
        button.disabled = true;
        try {
            const registration = await register();
            const existing = await registration.pushManager.getSubscription();
            if (existing && button.dataset.enabled === "true") {
                await post("/api/push/cancelar", { endpoint: existing.endpoint });
                await existing.unsubscribe();
                updateButton(false);
                return;
            }

            if (Notification.permission === "denied") {
                throw new Error("Las notificaciones están bloqueadas para este sitio. Puedes habilitarlas desde la configuración del navegador.");
            }
            const permission = Notification.permission === "granted"
                ? "granted"
                : await Notification.requestPermission();
            if (permission !== "granted") {
                throw new Error("No se concedió permiso para mostrar notificaciones.");
            }

            const subscription = existing || await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: decodeApplicationKey(vapidPublicKey)
            });
            await syncSubscription(subscription);
        } catch (error) {
            showError(error instanceof Error ? error.message : "No se pudo activar la notificación push.");
        } finally {
            button.disabled = false;
        }
    });

    register()
        .then((registration) => registration.pushManager.getSubscription())
        .then((subscription) => {
            if (!subscription) return;
            updateButton(true);
            return syncSubscription(subscription).catch((error) => {
                console.error("No se pudo sincronizar la suscripción push.", error);
            });
        })
        .catch((error) => {
            button.title = error instanceof Error ? error.message : "No se pudo registrar el Service Worker.";
        });
})();
