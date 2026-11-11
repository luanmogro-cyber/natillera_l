(() => {
    "use strict";

    // ── 1. Concepto/monto de reuniones ────────────────────────────────────────
    const concept = document.querySelector("#concepto");
    const amount  = document.querySelector("#valor_cuota");
    const preview = document.querySelector("#meeting-amount");
    if (concept && amount && preview) {
        const formatter = new Intl.NumberFormat("es-CO", { maximumFractionDigits: 0 });
        const updateAmount = () => {
            const selected = concept.selectedOptions[0];
            const value = Number(selected.dataset.valor);
            amount.value = String(value);
            preview.textContent = `$${formatter.format(value)}`;
        };
        concept.addEventListener("change", updateAmount);
        updateAmount();
    }

    // ── 2. Select de año en liquidación ──────────────────────────────────────
    const yearSelect = document.querySelector("#settlement-year");
    if (yearSelect) {
        yearSelect.addEventListener("change", () => yearSelect.form?.requestSubmit());
    }

    // ── 4. Toggle ojo en campos de contraseña ─────────────────────────────────
    document.querySelectorAll(".password-wrapper").forEach((wrapper) => {
        const input  = wrapper.querySelector("input[type='password'], input.pw-field");
        const toggle = wrapper.querySelector(".pw-toggle");
        if (!input || !toggle) return;

        toggle.addEventListener("click", () => {
            const isHidden = input.type === "password";
            input.type = isHidden ? "text" : "password";
            toggle.setAttribute("aria-label", isHidden ? "Ocultar contraseña" : "Ver contraseña");
            toggle.classList.toggle("pw-visible", isHidden);
        });
    });

    // ── 5. Service Worker + Offline sync ─────────────────────────────────────
    if ("serviceWorker" in navigator) {
        navigator.serviceWorker.register("/js/sw.js", { scope: "/" })
            .then((reg) => {
                // Prefetch de páginas al hover sobre nav links
                document.querySelectorAll(".nav-link[href]").forEach((link) => {
                    let prefetched = false;
                    link.addEventListener("mouseenter", () => {
                        if (prefetched) return;
                        prefetched = true;
                        const a = document.createElement("link");
                        a.rel  = "prefetch";
                        a.href = link.href;
                        document.head.appendChild(a);
                    });
                });

                // Escuchar mensajes del SW
                navigator.serviceWorker.addEventListener("message", (event) => {
                    if (event.data?.type === "OFFLINE_QUEUED") {
                        showOfflineBanner("Sin conexión. Tu acción se guardó y se enviará al reconectar.");
                    }
                    if (event.data?.type === "SYNC_DONE") {
                        showOfflineBanner("✓ Cambios sincronizados con el servidor.", "success");
                        setTimeout(hideOfflineBanner, 3500);
                    }
                });
            })
            .catch(() => {});

        // Detectar reconexión y disparar reenvío
        window.addEventListener("online", () => {
            hideOfflineBanner();
            navigator.serviceWorker.ready.then((reg) => {
                // Intentar Background Sync nativo
                if ("sync" in reg) {
                    reg.sync.register("natillera-sync").catch(() => {});
                } else {
                    // Fallback: mensaje manual al SW
                    reg.active?.postMessage({ type: "ONLINE_REPLAY" });
                }
            });
        });

        window.addEventListener("offline", () => {
            showOfflineBanner("Sin conexión. Puedes seguir navegando.");
        });

        // Al cerrar sesión, limpiar la cola
        document.querySelectorAll(".logout-form").forEach((form) => {
            form.addEventListener("submit", () => {
                navigator.serviceWorker.ready.then((reg) => {
                    reg.active?.postMessage({ type: "SESSION_ENDED" });
                });
            });
        });
    }

    // ── 6. Banner offline ─────────────────────────────────────────────────────
    function showOfflineBanner(msg, type = "warning") {
        let banner = document.getElementById("offline-banner");
        if (!banner) {
            banner = document.createElement("div");
            banner.id = "offline-banner";
            document.body.appendChild(banner);
        }
        banner.textContent = msg;
        banner.className   = "offline-banner offline-banner-" + type + " offline-banner-show";
    }

    function hideOfflineBanner() {
        const banner = document.getElementById("offline-banner");
        if (banner) banner.classList.remove("offline-banner-show");
    }

    // ── 8. Formato de punto de mil para valores de dinero ───────────────────
    const formatMilesNumber = (val) => {
        const clean = String(val).replace(/\D/g, "");
        if (!clean) return "";
        return new Intl.NumberFormat("es-CO").format(clean);
    };

    const attachMilesFormatters = () => {
        document.querySelectorAll(".js-format-miles").forEach((input) => {
            if (input.dataset.milesAttached) return;
            input.dataset.milesAttached = "1";

            input.addEventListener("input", () => {
                input.value = formatMilesNumber(input.value);
            });
            if (input.value) {
                input.value = formatMilesNumber(input.value);
            }
        });
    };
    attachMilesFormatters();

    // ── 9. Preview de foto de evidencia ──────────────────────────────────────
    const fotoInput = document.querySelector("#foto_evidencia");
    const previewBox = document.querySelector("#foto-preview-box");
    const previewImg = document.querySelector("#foto-preview-img");
    if (fotoInput && previewBox && previewImg) {
        fotoInput.addEventListener("change", () => {
            const file = fotoInput.files?.[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target?.result;
                    previewBox.classList.remove("d-none");
                };
                reader.readAsDataURL(file);
            } else {
                previewBox.classList.add("d-none");
                previewImg.src = "";
            }
        });
    }

    // ── 10. Pad de Firma Digital Interactiva ─────────────────────────────────
    const setupSignaturePad = (canvas, clearBtn, targetInput) => {
        if (!canvas) return;
        const ctx = canvas.getContext("2d");
        if (!ctx) return;

        ctx.strokeStyle = "#123d32";
        ctx.lineWidth = 2.5;
        ctx.lineCap = "round";
        ctx.lineJoin = "round";

        let isDrawing = false;
        let hasDrawn = false;
        let lastX = 0;
        let lastY = 0;

        const getPos = (e) => {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: (clientX - rect.left) * scaleX,
                y: (clientY - rect.top) * scaleY
            };
        };

        const startDrawing = (e) => {
            isDrawing = true;
            const pos = getPos(e);
            lastX = pos.x;
            lastY = pos.y;
            e.preventDefault();
        };

        const draw = (e) => {
            if (!isDrawing) return;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
            hasDrawn = true;
            e.preventDefault();
        };

        const stopDrawing = () => {
            if (isDrawing && hasDrawn && targetInput) {
                targetInput.value = canvas.toDataURL("image/png");
            }
            isDrawing = false;
        };

        const clear = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
            if (targetInput) targetInput.value = "";
        };

        canvas.addEventListener("mousedown", startDrawing);
        canvas.addEventListener("mousemove", draw);
        window.addEventListener("mouseup", stopDrawing);

        canvas.addEventListener("touchstart", startDrawing, { passive: false });
        canvas.addEventListener("touchmove", draw, { passive: false });
        window.addEventListener("touchend", stopDrawing);

        if (clearBtn) {
            clearBtn.addEventListener("click", clear);
        }
    };

    const mainSigCanvas = document.querySelector("#signature-canvas");
    const mainSigClear = document.querySelector("#btn-clear-sig");
    const mainSigTarget = document.querySelector("#firma_evidencia");
    if (mainSigCanvas) {
        setupSignaturePad(mainSigCanvas, mainSigClear, mainSigTarget);
    }

    document.querySelectorAll(".js-extra-sig").forEach((canvas) => {
        const targetSelector = canvas.dataset.sigTarget;
        const targetInput = targetSelector ? document.querySelector(targetSelector) : null;
        const clearBtn = canvas.parentElement?.querySelector(".js-clear-extra-sig");
        setupSignaturePad(canvas, clearBtn, targetInput);
    });

    // ── 12. Propagación de _tab para sesiones independientes por pestaña ────────
    // Usa exclusivamente el parámetro de URL / sessionStorage (nunca cookies),
    // garantizando que cada pestaña mantenga su usuario aislado.
    const nlTab = (() => {
        const fromUrl = new URLSearchParams(window.location.search).get("_tab");
        if (fromUrl && /^[a-zA-Z0-9]{8,48}$/.test(fromUrl)) {
            try { window.sessionStorage.setItem("nl_tab", fromUrl); } catch (e) {}
            return fromUrl;
        }
        if (window.__NL_TAB && /^[a-zA-Z0-9]{8,48}$/.test(window.__NL_TAB)) {
            return window.__NL_TAB;
        }
        try {
            const fromStorage = window.sessionStorage.getItem("nl_tab");
            if (fromStorage && /^[a-zA-Z0-9]{8,48}$/.test(fromStorage)) {
                return fromStorage;
            }
        } catch (e) {}
        return null;
    })();

    const injectTabInForm = (form) => {
        if (!nlTab || !form) return;
        const action = form.getAttribute("action") || "";
        if (action.startsWith("/") && !action.includes("_tab=")) {
            const sep = action.includes("?") ? "&" : "?";
            form.setAttribute("action", action + sep + "_tab=" + encodeURIComponent(nlTab));
        }
        if (!form.querySelector("[name='_tab']")) {
            const inp = document.createElement("input");
            inp.type = "hidden";
            inp.name = "_tab";
            inp.value = nlTab;
            form.appendChild(inp);
        }
    };

    if (nlTab) {
        document.querySelectorAll("form").forEach(injectTabInForm);
        document.addEventListener("submit", (e) => {
            if (e.target instanceof HTMLFormElement) {
                injectTabInForm(e.target);
            }
        });

        document.querySelectorAll("a[href]").forEach((a) => {
            const href = a.getAttribute("href") || "";
            if (href.startsWith("/") && !href.includes("_tab=")) {
                const sep = href.includes("?") ? "&" : "?";
                a.setAttribute("href", href + sep + "_tab=" + encodeURIComponent(nlTab));
            }
        });
    }

    // ── 14. Validación en tiempo real del cupo disponible en Préstamos ───────
    const loanForm = document.querySelector("#form-nuevo-prestamo");
    const loanSocioSelect = document.querySelector("#socio_id");
    const loanMontoInput = document.querySelector("#monto");
    const loanCupoInfo = document.querySelector("#cupo-info");
    const loanCupoError = document.querySelector("#cupo-error");
    const loanSubmitBtn = document.querySelector("#btn-submit-prestamo");

    if (loanForm && loanSocioSelect && loanMontoInput) {
        const formatCop = new Intl.NumberFormat("es-CO", { maximumFractionDigits: 0 });
        // Si la opción actual está deshabilitada, seleccionar la primera disponible
        if (loanSocioSelect.selectedOptions[0]?.disabled) {
            const firstEnabled = [...loanSocioSelect.options].find((opt) => !opt.disabled);
            if (firstEnabled) firstEnabled.selected = true;
        }

        const validateCupo = () => {
            const selected = loanSocioSelect.selectedOptions[0];
            const disponible = selected && !selected.disabled ? Number(selected.dataset.disponible ?? 0) : 0;
            const tasa = selected?.dataset.tasa || "10,00";
            const rawDigits = loanMontoInput.value.replace(/\D/g, "");
            const monto = rawDigits ? Number(rawDigits) : 0;

            if (loanCupoInfo) {
                loanCupoInfo.innerHTML = disponible > 0
                    ? `Cupo disponible: <strong class="text-success">$${formatCop.format(disponible)}</strong> · Tasa: <strong>${tasa}% mensual</strong>`
                    : `<strong class="text-danger">Este socio no tiene cupo disponible.</strong>`;
            }

            const excede = monto > disponible || disponible <= 0;
            if (excede && (monto > 0 || disponible <= 0)) {
                loanMontoInput.classList.add("is-invalid");
                if (loanCupoError) {
                    loanCupoError.textContent = disponible <= 0
                        ? "Este socio no tiene cupo disponible para solicitar préstamos."
                        : `⚠️ El monto ingresado ($${formatCop.format(monto)}) supera el cupo disponible ($${formatCop.format(disponible)}). Reduce el valor para poder continuar.`;
                    loanCupoError.classList.remove("d-none");
                }
                if (loanSubmitBtn) loanSubmitBtn.disabled = true;
                return false;
            }

            loanMontoInput.classList.remove("is-invalid");
            if (loanCupoError) loanCupoError.classList.add("d-none");
            if (loanSubmitBtn) loanSubmitBtn.disabled = false;
            return true;
        };

        loanSocioSelect.addEventListener("change", validateCupo);
        loanMontoInput.addEventListener("input", validateCupo);
        loanForm.addEventListener("submit", (event) => {
            if (!validateCupo()) {
                event.preventDefault();
                loanMontoInput.focus();
            }
        });
        validateCupo();
    }

    // ── 13. Asegurar que todos los modales Bootstrap se monten en document.body ──
    document.addEventListener("show.bs.modal", (event) => {
        const modalEl = event.target;
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    });

    // ── 11. Botón Rellenar para liquidar todo en modal de pago ────────────────
    document.querySelectorAll(".js-btn-pagar-todo").forEach((btn) => {
        btn.addEventListener("click", () => {
            const form = btn.closest("form");
            if (!form) return;
            const capitalInput = form.querySelector("[name='abono_capital']");
            const interesInput = form.querySelector("[name='abono_interes']");
            const moraInput = form.querySelector("[name='abono_mora']");
            const obsInput = form.querySelector("[name='observacion']");

            if (capitalInput) {
                capitalInput.value = formatMilesNumber(btn.dataset.capital || 0);
            }
            if (interesInput) {
                interesInput.value = formatMilesNumber(btn.dataset.interes || 0);
            }
            if (moraInput) {
                moraInput.value = formatMilesNumber(btn.dataset.mora || 0);
            }
            if (obsInput && !obsInput.value) {
                obsInput.value = "Liquidación total del préstamo";
            }
        });
    });
})();
