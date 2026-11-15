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
    const loanCajaError = document.querySelector("#caja-error");
    const loanSubmitBtn = document.querySelector("#btn-submit-prestamo");
    const cajaBadge = document.querySelector("#caja-disponible-badge");

    if (loanForm && loanSocioSelect && loanMontoInput) {
        const formatCop = new Intl.NumberFormat("es-CO", { maximumFractionDigits: 0 });
        const cajaDisponible = cajaBadge ? Number(cajaBadge.dataset.disponible || 0) : Infinity;

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

            // 1. Validar cupo individual del socio
            const excedeCupo = monto > disponible || disponible <= 0;
            if (excedeCupo && (monto > 0 || disponible <= 0)) {
                loanMontoInput.classList.add("is-invalid");
                if (loanCupoError) {
                    loanCupoError.textContent = disponible <= 0
                        ? "Este socio no tiene cupo disponible para solicitar préstamos."
                        : `⚠️ El monto ingresado ($${formatCop.format(monto)}) supera el cupo disponible ($${formatCop.format(disponible)}). Reduce el valor para poder continuar.`;
                    loanCupoError.classList.remove("d-none");
                }
                if (loanCajaError) loanCajaError.classList.add("d-none");
                if (loanSubmitBtn) loanSubmitBtn.disabled = true;
                return false;
            }

            // 2. Validar fondos disponibles en la caja de la natillera
            if (monto > cajaDisponible) {
                loanMontoInput.classList.add("is-invalid");
                if (loanCajaError) {
                    loanCajaError.innerHTML = `⚠️ <strong>Fondos insuficientes en la natillera:</strong> La caja disponible actual es de $${formatCop.format(cajaDisponible)} y estás solicitando $${formatCop.format(monto)}. No es posible prestar más dinero del disponible en caja.`;
                    loanCajaError.classList.remove("d-none");
                }
                if (loanCupoError) loanCupoError.classList.add("d-none");
                if (loanSubmitBtn) loanSubmitBtn.disabled = true;
                return false;
            }

            loanMontoInput.classList.remove("is-invalid");
            if (loanCupoError) loanCupoError.classList.add("d-none");
            if (loanCajaError) loanCajaError.classList.add("d-none");
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

    // ── 14. Búsqueda en tiempo real de participantes en Actividades ───────────
    const searchInput = document.querySelector("#buscar-participante");
    const checklist = document.querySelector("#participant-checklist");
    if (searchInput && checklist) {
        const clearBtn = document.querySelector("#btn-limpiar-busqueda-participante");
        const countSelectedEl = document.querySelector("#contador-participantes-seleccionados");
        const filterStatusEl = document.querySelector("#contador-filtro-participantes");
        const emptyMsgEl = document.querySelector("#sin-participantes-filtro");
        const btnMarcarVisibles = document.querySelector("#btn-marcar-visibles");
        const btnDesmarcarTodos = document.querySelector("#btn-desmarcar-todos");

        const options = Array.from(checklist.querySelectorAll(".participant-option"));
        const checkboxes = options.map((opt) => opt.querySelector("input[type='checkbox']")).filter(Boolean);

        const normalizeStr = (text) =>
            (text || "")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .trim();

        const updateSelectedCount = () => {
            const count = checkboxes.filter((cb) => cb.checked).length;
            if (countSelectedEl) {
                countSelectedEl.textContent = count === 1 ? "1 seleccionado" : `${count} seleccionados`;
                countSelectedEl.className = count > 0 ? "badge bg-success" : "badge bg-light text-dark border";
            }
        };

        const filterParticipants = () => {
            const query = normalizeStr(searchInput.value);
            let visibleCount = 0;

            if (clearBtn) {
                clearBtn.classList.toggle("d-none", query === "");
            }

            options.forEach((opt) => {
                const nombre = opt.dataset.nombre || normalizeStr(opt.textContent);
                const numero = opt.dataset.numero || "";
                const normName = normalizeStr(nombre);
                const matches = query === "" || normName.includes(query) || (numero !== "" && numero.includes(query));

                if (matches) {
                    opt.classList.remove("d-none");
                    visibleCount++;
                } else {
                    opt.classList.add("d-none");
                }
            });

            if (emptyMsgEl) {
                emptyMsgEl.classList.toggle("d-none", visibleCount > 0);
            }

            if (filterStatusEl) {
                if (query === "") {
                    filterStatusEl.textContent = `Mostrando todos (${options.length})`;
                } else {
                    filterStatusEl.textContent = `${visibleCount} de ${options.length} encontrados`;
                }
            }
        };

        searchInput.addEventListener("input", filterParticipants);
        searchInput.addEventListener("search", filterParticipants);
        searchInput.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                searchInput.value = "";
                filterParticipants();
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener("click", () => {
                searchInput.value = "";
                filterParticipants();
                searchInput.focus();
            });
        }

        checkboxes.forEach((cb) => {
            cb.addEventListener("change", updateSelectedCount);
        });

        if (btnMarcarVisibles) {
            btnMarcarVisibles.addEventListener("click", () => {
                options.forEach((opt) => {
                    if (!opt.classList.contains("d-none")) {
                        const cb = opt.querySelector("input[type='checkbox']");
                        if (cb) cb.checked = true;
                    }
                });
                updateSelectedCount();
            });
        }

        if (btnDesmarcarTodos) {
            btnDesmarcarTodos.addEventListener("click", () => {
                checkboxes.forEach((cb) => {
                    cb.checked = false;
                });
                updateSelectedCount();
            });
        }

        // Estado inicial
        updateSelectedCount();
        filterParticipants();
    }

    // ── 15. Bloqueo de horas pasadas en programación de reuniones ─────────────
    const meetingForm = document.querySelector("#meeting-form");
    const meetingFecha = document.querySelector("#fecha");
    const meetingHora = document.querySelector("#hora");
    const meetingAlert = document.querySelector("#hora-invalida-alerta");
    const meetingSubmitBtn = document.querySelector("#btn-programar-reunion");

    if (meetingForm && meetingFecha && meetingHora) {
        const getNowStr = () => {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, "0");
            const d = String(now.getDate()).padStart(2, "0");
            const hh = String(now.getHours()).padStart(2, "0");
            const mm = String(now.getMinutes()).padStart(2, "0");
            return {
                today: `${y}-${m}-${d}`,
                currentTime: `${hh}:${mm}`,
            };
        };

        const validateMeetingDateTime = () => {
            const { today, currentTime } = getNowStr();
            const selectedFecha = meetingFecha.value;
            const selectedHora = meetingHora.value;

            // Si la fecha es hoy, la hora no puede ser menor o igual a la hora actual
            if (selectedFecha === today) {
                meetingHora.min = currentTime;

                if (selectedHora && selectedHora <= currentTime) {
                    meetingHora.classList.add("is-invalid");
                    if (meetingAlert) {
                        meetingAlert.innerHTML = `⚠️ <strong>Hora no permitida:</strong> No puedes programar una reunión en una hora que ya pasó el día de hoy (hora actual: <strong>${currentTime}</strong> y seleccionaste <strong>${selectedHora}</strong>). Por favor elige una hora futura para hoy o selecciona una fecha posterior.`;
                        meetingAlert.classList.remove("d-none");
                    }
                    if (meetingSubmitBtn) meetingSubmitBtn.disabled = true;
                    return false;
                }
            } else {
                meetingHora.removeAttribute("min");
            }

            meetingHora.classList.remove("is-invalid");
            if (meetingAlert) meetingAlert.classList.add("d-none");
            if (meetingSubmitBtn) meetingSubmitBtn.disabled = false;
            return true;
        };

        meetingFecha.addEventListener("change", validateMeetingDateTime);
        meetingHora.addEventListener("input", validateMeetingDateTime);
        meetingHora.addEventListener("change", validateMeetingDateTime);

        meetingForm.addEventListener("submit", (e) => {
            if (!validateMeetingDateTime()) {
                e.preventDefault();
                meetingHora.focus();
            }
        });

        validateMeetingDateTime();
    }
})();
