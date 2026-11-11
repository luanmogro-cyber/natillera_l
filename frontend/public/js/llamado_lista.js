(() => {
    "use strict";

    const root = document.querySelector("#llamado-lista");
    if (!root) return;

    const meetingSelect = document.querySelector("#meeting-select");
    const saveButton = document.querySelector("#save-list");
    const enableEditButton = document.querySelector("#enable-edit");
    const lockedBanner = document.querySelector("#locked-banner");
    const status = document.querySelector("#save-status");
    const cards = [...root.querySelectorAll("[data-member]")];
    const cuotaValue = Number(root.dataset.cuota ?? 0);
    const alreadySaved = root.dataset.alreadySaved === "1";
    let editModeEnabled = !alreadySaved;

    const currency = new Intl.NumberFormat("es-CO", {
        style: "currency",
        currency: "COP",
        maximumFractionDigits: 0
    });

    const cardValues = (card) => {
        const paid = card.querySelector("[data-paid-checkbox]");
        const savings = card.querySelector("[data-savings]");
        const autoprestamo = card.querySelector("[data-autoprestamo-checkbox]");

        autoprestamo.checked = !paid.checked;
        autoprestamo.disabled = paid.checked || !editModeEnabled;
        return {
            socio_id: Number(card.dataset.memberId),
            pago_cuota: paid.checked,
            ahorro_voluntario: Number(savings.value),
            autoprestamo: autoprestamo.checked
        };
    };

    const serializeEntries = (entries) => JSON.stringify(entries);
    let initialSnapshot = serializeEntries(cards.map(cardValues));

    const updateSummary = () => {
        let paidCount = 0;
        let paidTotal = 0;
        let savingsTotal = 0;

        cards.forEach((card) => {
            const values = cardValues(card);
            if (values.pago_cuota) {
                paidCount += 1;
                paidTotal += cuotaValue;
            }
            savingsTotal += values.ahorro_voluntario;
        });

        const count = document.querySelector("#paid-count");
        const total = document.querySelector("#paid-total");
        const savings = document.querySelector("#savings-total");
        if (count) count.textContent = `${paidCount} / ${cards.length}`;
        if (total) total.textContent = currency.format(paidTotal);
        if (savings) savings.textContent = currency.format(savingsTotal);
    };

    cards.forEach((card) => {
        card.querySelector("[data-paid-checkbox]").addEventListener("change", updateSummary);
        card.querySelector("[data-savings]").addEventListener("input", updateSummary);
    });

    if (enableEditButton) {
        enableEditButton.addEventListener("click", () => {
            editModeEnabled = true;
            cards.forEach((card) => {
                card.querySelector("[data-paid-checkbox]").disabled = false;
                card.querySelector("[data-savings]").disabled = false;
            });
            updateSummary();

            if (lockedBanner) {
                lockedBanner.classList.remove("alert-warning");
                lockedBanner.classList.add("alert-info");
                lockedBanner.innerHTML = `
                    <div>
                        <strong>✏️ Modo edición habilitado:</strong>
                        <span class="d-block small">Modifique los aportes requeridos y presione <strong>Guardar cambios</strong> para actualizar el registro.</span>
                    </div>
                `;
            }
            if (saveButton) {
                saveButton.classList.remove("btn-outline-secondary");
                saveButton.classList.add("btn-primary");
                saveButton.textContent = "💾 Guardar cambios";
            }
        });
    }

    if (meetingSelect) {
        meetingSelect.addEventListener("change", () => {
            const url = new URL(window.location.href);
            url.searchParams.set("reunion_id", meetingSelect.value);
            window.location.assign(url);
        });
    }

    const showTechnicalAlert = (title, text, icon = "info") => {
        if (status) status.textContent = text;
        if (window.Swal) {
            return window.Swal.fire({
                icon,
                title,
                text,
                confirmButtonText: "Entendido",
                confirmButtonColor: "#123d32"
            });
        }
        window.alert(`${title}\n\n${text}`);
        return Promise.resolve();
    };

    if (saveButton) {
        saveButton.addEventListener("click", async () => {
            if (alreadySaved && !editModeEnabled) {
                await showTechnicalAlert(
                    "Llamado a lista ya consolidado",
                    "El registro de asistencia y recaudo para esta reunión ya fue procesado y almacenado en la base de datos. Active la opción 'Habilitar edición' si requiere efectuar una corrección.",
                    "warning"
                );
                return;
            }

            const entries = cards.map(cardValues);

            if (alreadySaved && serializeEntries(entries) === initialSnapshot) {
                await showTechnicalAlert(
                    "Sin modificaciones detectadas",
                    "Los valores actuales coinciden íntegramente con el llamado a lista ya guardado. Realice al menos una modificación antes de volver a guardar.",
                    "info"
                );
                return;
            }

            if (entries.some((entry) => !Number.isSafeInteger(entry.ahorro_voluntario) || entry.ahorro_voluntario < 0)) {
                await showTechnicalAlert(
                    "Revise los ahorros",
                    "Ingrese valores enteros y no negativos para cada ahorro voluntario.",
                    "error"
                );
                return;
            }

            const confirmation = window.Swal
                ? await window.Swal.fire({
                    title: alreadySaved ? "¿Actualizar el llamado a lista?" : "¿Guardar el llamado a lista?",
                    text: `Se ${alreadySaved ? "actualizará" : "consolidará"} el registro de ${entries.length} socios para esta reunión.`,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonText: alreadySaved ? "Sí, actualizar" : "Sí, guardar",
                    cancelButtonText: "Seguir revisando",
                    confirmButtonColor: "#123d32"
                })
                : { isConfirmed: window.confirm("¿Guardar el llamado a lista de todos los socios?") };
            if (!confirmation.isConfirmed) return;

            const prevLabel = saveButton.textContent;
            saveButton.disabled = true;
            saveButton.textContent = "Guardando…";
            try {
                const response = await fetch(root.dataset.saveUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-Token": root.dataset.csrfToken
                    },
                    body: JSON.stringify({
                        reunion_id: Number(root.dataset.reunionId),
                        modo_edicion: alreadySaved && editModeEnabled,
                        entries
                    })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || "No fue posible guardar el llamado.");

                if (status) status.textContent = result.message;
                if (window.Swal) {
                    await window.Swal.fire({
                        icon: "success",
                        title: alreadySaved ? "Registro actualizado" : "Registro consolidado",
                        text: result.message,
                        confirmButtonText: result.next_reunion_id ? "Ir al siguiente llamado" : "Aceptar",
                        confirmButtonColor: "#123d32"
                    });
                } else {
                    window.alert(result.message);
                }

                // Actualizar la vista al siguiente llamado pendiente o recargar estado consolidado
                const nextUrl = new URL(window.location.href);
                if (result.next_reunion_id) {
                    nextUrl.searchParams.set("reunion_id", String(result.next_reunion_id));
                } else {
                    nextUrl.searchParams.set("reunion_id", String(root.dataset.reunionId));
                }
                window.location.assign(nextUrl);
            } catch (error) {
                const message = error instanceof Error ? error.message : "Ocurrió un error al guardar.";
                await showTechnicalAlert("No se pudo guardar", message, "error");
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = prevLabel;
            }
        });
    }

    updateSummary();
})();
