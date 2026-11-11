<main class="content-page" id="llamado-lista"
      data-save-url="/admin/llamado-lista"
      data-reunion-id="<?= $reunion !== null ? (int) $reunion['id'] : '' ?>"
      data-already-saved="<?= !empty($yaGuardado) ? '1' : '0' ?>"
      data-cuota="<?= $valorCuota ?>"
      data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

    <header class="page-title-row">
        <div>
            <p class="eyebrow">ADMINISTRACIÓN · TESORERÍA</p>
            <h1>Llamado a lista</h1>
            <p>Registre cuotas, ahorros y autopréstamos en orden por número de menor a mayor.</p>
        </div>
        <span class="date-stamp" aria-hidden="true">✓</span>
    </header>

    <?php if ($reuniones !== []): ?>
        <section class="panel-card mb-4" aria-label="Reunión seleccionada">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-7">
                    <label for="meeting-select" class="field-label">Reunión de la quincena</label>
                    <select id="meeting-select" class="form-select">
                        <?php foreach ($reuniones as $opcion): ?>
                            <?php $opcionGuardada = in_array((int) $opcion['id'], $reunionesGuardadas ?? [], true); ?>
                            <option value="<?= (int) $opcion['id'] ?>"
                                <?= $reunion !== null && (int) $reunion['id'] === (int) $opcion['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcion['fecha'] . ' · ' . $opcion['concepto'], ENT_QUOTES, 'UTF-8') ?>
                                <?= $opcionGuardada ? ' [✓ Guardado]' : ' [Pendiente]' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($reunion !== null): ?>
                    <div class="col-6 col-md-2">
                        <span class="field-label d-block">Hora</span>
                        <strong class="fs-6"><?= htmlspecialchars(substr((string) $reunion['hora'], 0, 5), ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="col-6 col-md-3">
                        <span class="field-label d-block">Cuota obligatoria</span>
                        <strong class="fs-6 text-success">$<?= number_format((int) $reunion['valor_cuota'], 0, ',', '.') ?> COP</strong>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($reunion === null): ?>
        <div class="alert alert-info" role="status">
            Aún no hay reuniones registradas. Cuando se programe una reunión, aparecerá aquí el llamado a lista.
        </div>
    <?php elseif ($socios === []): ?>
        <div class="alert alert-info" role="status">
            No hay socios activos registrados para mostrar en esta reunión.
        </div>
    <?php else: ?>
        <!-- Tarjetas de resumen del recaudo (usan stat-grid / stat-card del tema) -->
        <section class="stat-grid mb-4" aria-label="Resumen del recaudo">
            <article class="stat-card">
                <span class="stat-icon stat-icon-green" aria-hidden="true">✓</span>
                <span class="stat-label">Cuotas pagadas</span>
                <strong id="paid-count">0 / <?= count($socios) ?></strong>
                <span class="stat-foot">Asistencia con pago al día</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-green" aria-hidden="true">$</span>
                <span class="stat-label">Recaudo de cuotas</span>
                <strong id="paid-total">$0</strong>
                <span class="stat-foot">Total recaudado en cuotas</span>
            </article>
            <article class="stat-card">
                <span class="stat-icon stat-icon-gold" aria-hidden="true">＋</span>
                <span class="stat-label">Ahorro voluntario</span>
                <strong id="savings-total">$0</strong>
                <span class="stat-foot">Aportes voluntarios adicionales</span>
            </article>
        </section>

        <!-- Tabla organizada por número asignado de menor a mayor -->
        <article class="panel-card" aria-label="Registro de asistencia y pagos">
            <?php if (!empty($yaGuardado)): ?>
                <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" id="locked-banner" role="alert">
                    <div>
                        <strong>🔒 Registro consolidado · Llamado a lista ya guardado:</strong>
                        <span class="d-block small">Los aportes de esta reunión ya fueron almacenados en el sistema. Para modificar o agregar algún dato, active el modo de edición.</span>
                    </div>
                    <button type="button" id="enable-edit" class="btn btn-sm btn-outline-dark fw-semibold">
                        ✏️ Habilitar edición
                    </button>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div class="panel-heading mb-0">
                    <span class="panel-icon panel-icon-gold" aria-hidden="true">▦</span>
                    <div>
                        <h2>Lista de socios (<?= count($socios) ?>)</h2>
                        <p>Ordenada por número asignado de menor a mayor.</p>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" id="save-list" class="btn <?= !empty($yaGuardado) ? 'btn-outline-secondary' : 'btn-primary' ?> px-4">
                        <?= !empty($yaGuardado) ? '🔒 Registro guardado' : '💾 Guardar llamado' ?>
                    </button>
                </div>
            </div>
            <p id="save-status" class="visually-hidden" role="status" aria-live="polite"></p>

            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" id="tabla-llamado">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col" class="text-center" style="width: 70px;">N°</th>
                            <th scope="col">Nombre completo</th>
                            <th scope="col" style="width: 170px;">Cargo</th>
                            <th scope="col" class="text-center" style="width: 115px;">Pagó cuota</th>
                            <th scope="col" style="width: 190px;">Ahorro voluntario</th>
                            <th scope="col" class="text-center" style="width: 130px;">Autopréstamo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($socios as $i => $socio): ?>
                            <?php
                            $pagado = !empty($socio['pago_cuota']);
                            $numeroMostrar = $socio['numero'] !== null ? (int) $socio['numero'] : ($i + 1);
                            $rol = $socio['rol'] ?? 'Socio';
                            $badgeClass = match ($rol) {
                                'Presidente' => 'bg-warning text-dark border border-warning-subtle',
                                'Tesorera' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                'Secretaria General', 'Secretaria Actividades' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                                default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                            };
                            ?>
                            <tr data-member
                                data-member-id="<?= (int) $socio['id'] ?>"
                                data-paid="<?= $pagado ? '1' : '0' ?>"
                                class="<?= $pagado ? 'table-success' : '' ?>">

                                <td class="text-center fw-bold">
                                    <span class="badge rounded-pill text-bg-dark px-2 py-1">#<?= $numeroMostrar ?></span>
                                </td>

                                <td class="fw-semibold">
                                    <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars($rol, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="form-check d-inline-block m-0">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               data-paid-checkbox
                                               style="width: 1.35rem; height: 1.35rem; cursor: pointer;"
                                               <?= $pagado ? 'checked' : '' ?>
                                               <?= !empty($yaGuardado) ? 'disabled' : '' ?>
                                               aria-label="Pagó cuota <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </td>

                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input class="form-control form-control-sm"
                                               type="number" min="0" step="1000" inputmode="numeric"
                                               value="<?= (int) $socio['ahorro_voluntario'] ?>"
                                               data-savings
                                               placeholder="0"
                                               <?= !empty($yaGuardado) ? 'disabled' : '' ?>
                                               aria-label="Ahorro voluntario de <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </td>

                                <td class="text-center">
                                    <div class="form-check d-inline-block m-0">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               data-autoprestamo-checkbox
                                               style="width: 1.25rem; height: 1.25rem; cursor: pointer;"
                                               <?= !empty($socio['autoprestamo']) ? 'checked' : '' ?>
                                               aria-label="Autopréstamo <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="small text-muted mt-3 mb-0">
                <span class="d-inline-block rounded me-1 border" style="width: 13px; height: 13px; background: #d1e7dd;"></span>
                Fila verde = cuota pagada. Los socios están ordenados por su número asignado de menor a mayor (para cambiar los números ve a <strong>Socios</strong>).
            </p>
        </article>

        <style>
            #tabla-llamado tbody tr { transition: background-color .18s ease; }
            #tabla-llamado th { white-space: nowrap; font-size: .84rem; }
        </style>
        <script>
        document.querySelectorAll('[data-paid-checkbox]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var row = cb.closest('tr');
                if (!row) return;
                row.dataset.paid = cb.checked ? '1' : '0';
                row.classList.toggle('table-success', cb.checked);
            });
        });
        </script>
    <?php endif; ?>
</main>
