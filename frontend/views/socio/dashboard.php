<main class="content-page">
    <header class="welcome-banner member-welcome">
        <div class="welcome-copy">
            <p class="eyebrow">TU HISTORIA EN LA NATILLERA</p>
            <h1>Hola, <?= htmlspecialchars((string) $currentUser['nombre'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Consulta aquí tu resumen oficial: tus ahorros, tus aportes, reuniones asistidas y el estado de tus préstamos.</p>
        </div>
        <img src="/img/semilla.svg" alt="" class="welcome-illustration">
    </header>

    <!-- TARJETAS DE RESUMEN PRINCIPAL -->
    <section class="stat-grid member-savings-grid mb-4" aria-label="Resumen personal">
        <article class="stat-card">
            <span class="stat-icon stat-icon-green" aria-hidden="true">$</span>
            <span class="stat-label">Total ahorrado</span>
            <strong>$<?= number_format((int) $resumen['total_ahorrado'], 0, ',', '.') ?></strong>
            <span class="stat-foot">Cuotas + Ahorro voluntario</span>
        </article>
        <article class="stat-card">
            <span class="stat-icon stat-icon-green" aria-hidden="true">✓</span>
            <span class="stat-label">Cuotas obligatorias</span>
            <strong>$<?= number_format((int) $resumen['cuotas'], 0, ',', '.') ?></strong>
            <span class="stat-foot">Aportes a reuniones</span>
        </article>
        <article class="stat-card">
            <span class="stat-icon stat-icon-gold" aria-hidden="true">＋</span>
            <span class="stat-label">Ahorro voluntario</span>
            <strong>$<?= number_format((int) $resumen['ahorro_voluntario'], 0, ',', '.') ?></strong>
            <span class="stat-foot">Tus ahorros adicionales</span>
        </article>
        <article class="stat-card stat-card-next">
            <span class="stat-icon stat-icon-cream" aria-hidden="true">▦</span>
            <span class="stat-label">Asistencia a reuniones</span>
            <strong><?= (int) $resumen['reuniones_asistidas'] ?> <small class="fs-6 text-muted">/ <?= (int) $resumen['total_reuniones'] ?></small></strong>
            <span class="stat-foot"><?= (int) $resumen['porcentaje_asistencia'] ?>% de participación</span>
        </article>
        <?php if ((int)$resumen['saldo_prestamos_activo'] > 0): ?>
            <article class="stat-card border-warning">
                <span class="stat-icon stat-icon-gold" aria-hidden="true">⚠</span>
                <span class="stat-label">Saldo préstamo activo</span>
                <strong class="text-warning-emphasis">$<?= number_format((int) $resumen['saldo_prestamos_activo'], 0, ',', '.') ?></strong>
                <span class="stat-foot">Capital pendiente por pagar</span>
            </article>
        <?php endif; ?>
    </section>

    <!-- NAVEGACIÓN DE SECCIONES (TABS) -->
    <ul class="nav nav-pills mb-4 gap-2" id="socioTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="reuniones-tab" data-bs-toggle="pill" data-bs-target="#tab-reuniones" type="button" role="tab" aria-controls="tab-reuniones" aria-selected="true">
                ▦ Reuniones y Aportes (<?= count($resumen['historial_reuniones']) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="prestamos-tab" data-bs-toggle="pill" data-bs-target="#tab-prestamos" type="button" role="tab" aria-controls="tab-prestamos" aria-selected="false">
                💵 Mis Préstamos (<?= count($resumen['prestamos']) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="actividades-tab" data-bs-toggle="pill" data-bs-target="#tab-actividades" type="button" role="tab" aria-controls="tab-actividades" aria-selected="false">
                ✳ Actividades (<?= count($resumen['actividades']) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="socioTabsContent">
        <!-- TAB 1: REUNIONES Y ASISTENCIA -->
        <div class="tab-pane fade show active" id="tab-reuniones" role="tabpanel" aria-labelledby="reuniones-tab">
            <article class="panel-card mb-4">
                <div class="panel-heading">
                    <span class="panel-icon panel-icon-green" aria-hidden="true">▦</span>
                    <div>
                        <h2>Historial de reuniones celebradas</h2>
                        <p>Total de reuniones realizadas en la natillera: <strong><?= (int) $resumen['total_reuniones'] ?></strong> · Has asistido y pagado cuota en: <strong><?= (int) $resumen['reuniones_asistidas'] ?></strong> (<?= (int) $resumen['porcentaje_asistencia'] ?>%)</p>
                    </div>
                </div>

                <?php if ($resumen['historial_reuniones'] === []): ?>
                    <div class="empty-state">
                        <img src="/img/semilla.svg" alt="" loading="lazy">
                        <p>Aún no hay reuniones registradas en la natillera.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Concepto</th>
                                    <th>Valor cuota</th>
                                    <th>Cuota obligatoria</th>
                                    <th>Ahorro voluntario</th>
                                    <th>Autopréstamo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumen['historial_reuniones'] as $reunion): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars((string) $reunion['fecha'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small class="d-block text-muted"><?= htmlspecialchars(substr((string)$reunion['hora'], 0, 5), ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars((string) $reunion['concepto'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>$<?= number_format((int) $reunion['valor_cuota'], 0, ',', '.') ?></td>
                                        <td>
                                            <?php if ((int) $reunion['pago_cuota'] === 1): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                    ✓ Pagó cuota ($<?= number_format((int) $reunion['valor_cuota'], 0, ',', '.') ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                                    ✕ Pendiente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int) $reunion['ahorro_voluntario'] > 0): ?>
                                                <strong class="text-success">+$<?= number_format((int) $reunion['ahorro_voluntario'], 0, ',', '.') ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">$0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int) $reunion['autoprestamo'] === 1): ?>
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Autopréstamo</span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        </div>

        <!-- TAB 2: PRÉSTAMOS -->
        <div class="tab-pane fade" id="tab-prestamos" role="tabpanel" aria-labelledby="prestamos-tab">
            <article class="panel-card mb-4">
                <div class="panel-heading">
                    <span class="panel-icon panel-icon-gold" aria-hidden="true">$</span>
                    <div>
                        <h2>Mis préstamos</h2>
                        <p>Total prestado: <strong>$<?= number_format((int) $resumen['total_prestado'], 0, ',', '.') ?></strong> · Saldo actual: <strong>$<?= number_format((int) $resumen['saldo_prestamos_activo'], 0, ',', '.') ?></strong> · Intereses pagados: <strong>$<?= number_format((int) $resumen['total_intereses_pagados'] + (int) $resumen['total_mora_pagada'], 0, ',', '.') ?></strong></p>
                    </div>
                </div>

                <?php if ($resumen['prestamos'] === []): ?>
                    <div class="empty-state">
                        <img src="/img/semilla.svg" alt="" loading="lazy">
                        <p>No tienes préstamos registrados.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($resumen['prestamos'] as $prestamo): ?>
                            <div class="col-12 col-lg-6">
                                <div class="card h-100 border shadow-sm">
                                    <div class="card-header d-flex justify-content-between align-items-center bg-white">
                                        <div>
                                            <span class="fw-bold">Préstamo #<?= (int) $prestamo['id'] ?></span>
                                            <?php if (!empty($prestamo['prestatario_tercero'])): ?>
                                                <small class="d-block text-muted">A favor de: <?= htmlspecialchars($prestamo['prestatario_tercero'], ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if ($prestamo['estado'] === 'pagado'): ?>
                                                <span class="badge bg-success">Pagado completamente</span>
                                            <?php elseif (!empty($prestamo['en_mora'])): ?>
                                                <span class="badge bg-danger">En mora (<?= (int) $prestamo['meses_mora'] ?> mes<?= (int) $prestamo['meses_mora'] > 1 ? 'es' : '' ?>)</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Al día</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- CIFRAS PRINCIPALES -->
                                        <div class="row text-center mb-3 g-2">
                                            <div class="col-4 border-end">
                                                <small class="text-muted d-block">Monto inicial</small>
                                                <strong class="fs-6">$<?= number_format((int) $prestamo['monto'], 0, ',', '.') ?></strong>
                                            </div>
                                            <div class="col-4 border-end">
                                                <small class="text-muted d-block">Saldo capital</small>
                                                <strong class="fs-6 <?= (int)$prestamo['saldo_capital'] > 0 ? 'text-primary' : 'text-success' ?>">
                                                    $<?= number_format((int) $prestamo['saldo_capital'], 0, ',', '.') ?>
                                                </strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted d-block">Total a liquidar</small>
                                                <strong class="fs-6 <?= (int)$prestamo['total_a_liquidar'] > 0 ? 'text-danger' : 'text-success' ?>">
                                                    $<?= number_format((int) $prestamo['total_a_liquidar'], 0, ',', '.') ?>
                                                </strong>
                                            </div>
                                        </div>

                                        <!-- DETALLES DE FECHAS Y PLAZO -->
                                        <ul class="list-group list-group-flush mb-3 small">
                                            <li class="list-group-item d-flex justify-content-between px-0">
                                                <span>Fecha de entrega:</span>
                                                <strong><?= htmlspecialchars($prestamo['fecha_entrega'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between px-0">
                                                <span>Plazo acordado:</span>
                                                <strong><?= (int) $prestamo['plazo_meses'] ?> mes(es)</strong>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between px-0">
                                                <span>Fecha límite / vencimiento:</span>
                                                <strong class="<?= !empty($prestamo['en_mora']) ? 'text-danger fw-bold' : '' ?>">
                                                    <?= htmlspecialchars((string) $prestamo['fecha_vencimiento'], ENT_QUOTES, 'UTF-8') ?>
                                                    <?= !empty($prestamo['en_mora']) ? ' (Vencido)' : '' ?>
                                                </strong>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between px-0">
                                                <span>Tasa ordinaria mensual:</span>
                                                <strong><?= number_format((int) $prestamo['tasa_mensual_bp'] / 100, 2, ',', '.') ?>%</strong>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between px-0">
                                                <span>Intereses ordinarios causados:</span>
                                                <span>$<?= number_format((int) $prestamo['interes_estimado'], 0, ',', '.') ?> (Pagado: $<?= number_format((int) $prestamo['interes_recibido'], 0, ',', '.') ?>)</span>
                                            </li>
                                            <?php if (!empty($prestamo['en_mora']) || (int)($prestamo['mora_recibida'] ?? 0) > 0): ?>
                                                <li class="list-group-item d-flex justify-content-between px-0 text-danger">
                                                    <span>Interés de mora (10% mensual):</span>
                                                    <strong>$<?= number_format((int) $prestamo['mora_estimada'], 0, ',', '.') ?> (<?= (int) $prestamo['meses_mora'] ?> mes de mora)</strong>
                                                </li>
                                            <?php endif; ?>
                                        </ul>

                                        <!-- COMPROBANTE Y FIRMA -->
                                        <?php if (!empty($prestamo['foto_evidencia']) || !empty($prestamo['firma_evidencia'])): ?>
                                            <div class="mb-3">
                                                <button type="button" class="btn btn-sm btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#modal-evidencia-<?= (int) $prestamo['id'] ?>">
                                                    📷 Ver comprobante y firma de entrega
                                                </button>
                                            </div>

                                            <!-- Modal Evidencia -->
                                            <div class="modal fade" id="modal-evidencia-<?= (int) $prestamo['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Evidencia de entrega · Préstamo #<?= (int) $prestamo['id'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                            <?php if (!empty($prestamo['foto_evidencia'])): ?>
                                                                <p class="fw-semibold mb-1">Foto / Documento de entrega:</p>
                                                                <img src="<?= htmlspecialchars($prestamo['foto_evidencia'], ENT_QUOTES, 'UTF-8') ?>" alt="Comprobante" class="img-fluid rounded border mb-3" style="max-height: 250px;">
                                                            <?php endif; ?>
                                                            <?php if (!empty($prestamo['firma_evidencia'])): ?>
                                                                <p class="fw-semibold mb-1">Firma digital del socio:</p>
                                                                <div class="border rounded p-2 bg-light d-inline-block">
                                                                    <img src="<?= htmlspecialchars($prestamo['firma_evidencia'], ENT_QUOTES, 'UTF-8') ?>" alt="Firma digital" class="img-fluid" style="max-height: 120px;">
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- HISTORIAL DE PAGOS DEL PRÉSTAMO -->
                                        <div class="mt-2">
                                            <h6 class="fw-bold mb-2 small text-uppercase text-secondary">Abonos y pagos realizados (<?= count($prestamo['pagos']) ?>)</h6>
                                            <?php if ($prestamo['pagos'] === []): ?>
                                                <p class="text-muted small mb-0">Aún no se registran pagos para este préstamo.</p>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered align-middle mb-0 small">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Fecha</th>
                                                                <th>Capital</th>
                                                                <th>Interés</th>
                                                                <th>Mora</th>
                                                                <th>Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($prestamo['pagos'] as $pago): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars((string) $pago['fecha_pago'], ENT_QUOTES, 'UTF-8') ?></td>
                                                                    <td class="text-success">+$<?= number_format((int) $pago['abono_capital'], 0, ',', '.') ?></td>
                                                                    <td>$<?= number_format((int) $pago['abono_interes'], 0, ',', '.') ?></td>
                                                                    <td>$<?= number_format((int) $pago['abono_mora'], 0, ',', '.') ?></td>
                                                                    <td><strong>$<?= number_format((int) $pago['total_pagado'], 0, ',', '.') ?></strong></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>

        <!-- TAB 3: ACTIVIDADES -->
        <div class="tab-pane fade" id="tab-actividades" role="tabpanel" aria-labelledby="actividades-tab">
            <article class="panel-card mb-4">
                <div class="panel-heading">
                    <span class="panel-icon panel-icon-gold" aria-hidden="true">✳</span>
                    <div>
                        <h2>Participación en actividades comunitarias</h2>
                        <p>Actividades en las que has sido partícipe con el grupo.</p>
                    </div>
                </div>

                <?php if ($resumen['actividades'] === []): ?>
                    <div class="empty-state">
                        <img src="/img/semilla.svg" alt="" loading="lazy">
                        <p>No tienes registro de participación en actividades aún.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Actividad</th>
                                    <th>Participantes</th>
                                    <th>Ganancia neta grupo</th>
                                    <th>Tu reparto asignado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumen['actividades'] as $act): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars((string) $act['fecha'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td><?= htmlspecialchars((string) $act['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $act['participantes'] ?> personas</td>
                                        <td>$<?= number_format((int) $act['neto'], 0, ',', '.') ?></td>
                                        <td>
                                            <strong class="text-success">
                                                $<?= number_format((int) $act['reparto'], 0, ',', '.') ?>
                                            </strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </div>

    <div class="alert alert-light border text-center small text-muted py-2 mt-4" role="status">
        🔒 <strong>Módulo de consulta:</strong> Este resumen refleja exclusivamente los registros oficiales ingresados por Presidencia, Tesorería y Secretaría.
    </div>
</main>
