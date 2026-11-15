<main class="content-page dashboard-page">
    <?php
    $mesesEspanol = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $fechaProxima = $proximaReunion === null ? null : new DateTimeImmutable($proximaReunion['fecha']);
    $rolActual = $currentUser['rol'] ?? '';
    $balance = $balance ?? [];
    ?>
    <header class="welcome-banner">
        <div class="welcome-copy">
            <p class="eyebrow">EL RESUMEN DE HOY</p>
            <h1>Hola, <?= htmlspecialchars((string) $currentUser['nombre'], ENT_QUOTES, 'UTF-8') ?> <span aria-hidden="true">✳</span></h1>
            <p>Lo que hacemos juntos hoy, se convierte en oportunidades mañana.</p>
        </div>
        <img src="/img/semilla.svg" alt="" class="welcome-illustration">
    </header>

    <!-- Alertas operativas prioritarias -->
    <?php if ($rolActual === 'Presidente' && (int) ($balance['prestamos_pendientes'] ?? 0) > 0): ?>
        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" role="alert">
            <div>
                <strong>⏳ Tienes <?= (int) $balance['prestamos_pendientes'] ?> préstamo(s) pendiente(s) por aprobar.</strong>
                <span class="d-block small">Revísalos y autorízalos para que pasen a estado activo.</span>
            </div>
            <a href="/admin/prestamos" class="btn btn-sm btn-warning fw-semibold">Ir a Préstamos →</a>
        </div>
    <?php endif; ?>

    <?php if (in_array($rolActual, ['Presidente', 'Tesorera'], true) && (int) ($balance['prestamos_en_mora'] ?? 0) > 0): ?>
        <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" role="alert">
            <div>
                <strong>⚠️ Hay <?= (int) $balance['prestamos_en_mora'] ?> préstamo(s) en mora.</strong>
                <span class="d-block small">Ya superaron su fecha límite de pago y están generando mora del 10% mensual.</span>
            </div>
            <a href="/admin/prestamos" class="btn btn-sm btn-outline-danger fw-semibold">Gestionar cobranza →</a>
        </div>
    <?php endif; ?>

    <section class="stat-grid" aria-label="Resumen de la natillera">
        <article class="stat-card">
            <span class="stat-icon stat-icon-green" aria-hidden="true">♧</span>
            <span class="stat-label">Socios activos</span>
            <strong><?= (int) $sociosActivos ?><small> / 50</small></strong>
            <span class="stat-foot"><?= (int) max(0, 50 - $sociosActivos) ?> cupos disponibles</span>
        </article>
        <article class="stat-card">
            <span class="stat-icon stat-icon-gold" aria-hidden="true">▦</span>
            <span class="stat-label">Reuniones programadas</span>
            <strong><?= (int) $reunionesRegistradas ?></strong>
            <span class="stat-foot">Encuentros de nuestra comunidad</span>
        </article>
        <article class="stat-card stat-card-next">
            <span class="stat-icon stat-icon-cream" aria-hidden="true">↗</span>
            <span class="stat-label">Próximo encuentro</span>
            <?php if ($proximaReunion !== null): ?>
                <strong class="next-date"><?= htmlspecialchars($fechaProxima->format('d') . ' ' . $mesesEspanol[(int) $fechaProxima->format('n') - 1], ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="stat-foot"><?= htmlspecialchars(substr($proximaReunion['hora'], 0, 5) . ' · ' . $proximaReunion['concepto'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
                <strong class="next-date">Aún sin fecha</strong>
                <span class="stat-foot">Programa nuestro primer encuentro</span>
            <?php endif; ?>
        </article>
    </section>

    <?php if (in_array($rolActual, ['Presidente', 'Tesorera'], true) && $balance !== []): ?>
        <section class="panel-card mt-4" aria-label="Balance general de caja">
            <div class="panel-heading">
                <span class="panel-icon panel-icon-gold" aria-hidden="true">$</span>
                <div>
                    <h2>Balance general de caja</h2>
                    <p>Estado consolidado en tiempo real. <strong>Haz clic en cualquier tarjeta para ver el listado detallado.</strong></p>
                </div>
            </div>
            <div class="row g-2 g-md-3">
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100 balance-card-clickable"
                         role="button"
                         tabindex="0"
                         data-bs-toggle="modal"
                         data-bs-target="#modal-balance-cuotas"
                         title="Clic para ver detalle de cuotas obligatorias">
                        <small class="text-muted d-block">Cuotas obligatorias</small>
                        <strong class="fs-6 text-dark d-block my-1">$<?= number_format((int) $balance['cuotas_obligatorias'], 0, ',', '.') ?></strong>
                        <span class="balance-card-hint text-muted">🔍 Ver detalle →</span>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100 balance-card-clickable"
                         role="button"
                         tabindex="0"
                         data-bs-toggle="modal"
                         data-bs-target="#modal-balance-ahorro"
                         title="Clic para ver detalle de ahorro voluntario">
                        <small class="text-muted d-block">Ahorro voluntario</small>
                        <strong class="fs-6 text-dark d-block my-1">$<?= number_format((int) $balance['ahorro_voluntario'], 0, ',', '.') ?></strong>
                        <span class="balance-card-hint text-muted">🔍 Ver detalle →</span>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100 balance-card-clickable"
                         role="button"
                         tabindex="0"
                         data-bs-toggle="modal"
                         data-bs-target="#modal-balance-intereses"
                         title="Clic para ver detalle de intereses y mora">
                        <small class="text-muted d-block">Intereses y mora</small>
                        <strong class="fs-6 text-success d-block my-1">+$<?= number_format((int) $balance['intereses_mora'], 0, ',', '.') ?></strong>
                        <span class="balance-card-hint text-success">🔍 Ver detalle →</span>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100 balance-card-clickable"
                         role="button"
                         tabindex="0"
                         data-bs-toggle="modal"
                         data-bs-target="#modal-balance-actividades"
                         title="Clic para ver detalle de actividades">
                        <small class="text-muted d-block">Neto actividades</small>
                        <strong class="fs-6 <?= (int) $balance['actividades_neto'] >= 0 ? 'text-success' : 'text-danger' ?> d-block my-1">$<?= number_format((int) $balance['actividades_neto'], 0, ',', '.') ?></strong>
                        <span class="balance-card-hint text-muted">🔍 Ver detalle →</span>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100 balance-card-clickable"
                         role="button"
                         tabindex="0"
                         data-bs-toggle="modal"
                         data-bs-target="#modal-balance-calle"
                         title="Clic para ver préstamos activos en la calle">
                        <small class="text-muted d-block">Capital en préstamos</small>
                        <strong class="fs-6 text-primary d-block my-1">$<?= number_format((int) $balance['capital_en_calle'], 0, ',', '.') ?></strong>
                        <span class="balance-card-hint text-primary">🔍 Ver quién debe →</span>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border border-success bg-success-subtle h-100 balance-card-clickable"
                         role="button"
                         tabindex="0"
                         data-bs-toggle="modal"
                         data-bs-target="#modal-balance-disponible"
                         title="Clic para ver arqueo y cálculo de caja disponible">
                        <small class="text-success-emphasis d-block fw-semibold">Disponible en caja</small>
                        <strong class="fs-6 text-success-emphasis d-block my-1">$<?= number_format((int) $balance['caja_disponible'], 0, ',', '.') ?></strong>
                        <span class="balance-card-hint text-success-emphasis fw-semibold">🔍 Ver arqueo →</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODALES DE DETALLE INTERACTIVO (BALANCE GENERAL DE CAJA)
             ═══════════════════════════════════════════════════════════════════ -->

        <!-- 1. Modal Detalle Cuotas Obligatorias -->
        <div class="modal fade" id="modal-balance-cuotas" tabindex="-1" aria-labelledby="modalCuotasTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <div>
                            <h5 class="modal-title fw-bold" id="modalCuotasTitle">📋 Cuotas Obligatorias Recaudadas</h5>
                            <small class="text-muted">Desglose de aportes obligatorios quincena por quincena</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-3">
                        <?php $cuotasList = $balanceDetalles['cuotas'] ?? []; ?>
                        <?php if ($cuotasList === []): ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">Aún no hay reuniones con cuotas obligatorias registradas.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha reunión</th>
                                            <th>Concepto de quincena</th>
                                            <th class="text-end">Valor cuota</th>
                                            <th class="text-center">Socios que pagaron</th>
                                            <th class="text-end">Total recaudado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cuotasList as $c): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars((string) $c['fecha'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                <td><?= htmlspecialchars((string) $c['concepto'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-end">$<?= number_format((int) $c['valor_cuota'], 0, ',', '.') ?></td>
                                                <td class="text-center"><span class="badge bg-secondary"><?= (int) $c['total_socios_pagaron'] ?> socios</span></td>
                                                <td class="text-end fw-bold text-success">$<?= number_format((int) $c['total_recaudado'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr class="fw-bold">
                                            <td colspan="4">TOTAL CUOTAS OBLIGATORIAS:</td>
                                            <td class="text-end text-success fs-6">$<?= number_format((int) $balance['cuotas_obligatorias'], 0, ',', '.') ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Modal Detalle Ahorro Voluntario -->
        <div class="modal fade" id="modal-balance-ahorro" tabindex="-1" aria-labelledby="modalAhorroTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <div>
                            <h5 class="modal-title fw-bold" id="modalAhorroTitle">🐖 Ahorro Voluntario por Socio</h5>
                            <small class="text-muted">Ahorros adicionales aportados en las reuniones quincenales</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-3">
                        <?php $ahorroList = $balanceDetalles['ahorro'] ?? []; ?>
                        <?php if ($ahorroList === []): ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">Aún no hay registros de ahorro voluntario.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">#</th>
                                            <th>Socio</th>
                                            <th class="text-center">Quincenas con aporte</th>
                                            <th class="text-end">Total ahorro voluntario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ahorroList as $a): ?>
                                            <tr>
                                                <td><span class="badge bg-light text-dark border">#<?= (int) ($a['numero'] ?? 0) ?></span></td>
                                                <td><strong><?= htmlspecialchars((string) $a['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                <td class="text-center"><span class="badge bg-secondary"><?= (int) $a['quincenas_aportadas'] ?> aportes</span></td>
                                                <td class="text-end fw-bold text-success">$<?= number_format((int) $a['total_ahorrado'], 0, ',', '.') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr class="fw-bold">
                                            <td colspan="3">TOTAL AHORRO VOLUNTARIO:</td>
                                            <td class="text-end text-success fs-6">$<?= number_format((int) $balance['ahorro_voluntario'], 0, ',', '.') ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Modal Detalle Intereses y Mora -->
        <div class="modal fade" id="modal-balance-intereses" tabindex="-1" aria-labelledby="modalInteresesTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <div>
                            <h5 class="modal-title fw-bold text-success" id="modalInteresesTitle">📈 Rendimientos: Intereses y Mora Cobrados</h5>
                            <small class="text-muted">Utilidades recaudadas a través de los pagos de préstamos</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-3">
                        <?php $interesesList = $balanceDetalles['intereses'] ?? []; ?>
                        <?php if ($interesesList === []): ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">Aún no se han recibido pagos de intereses ni mora en préstamos.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Fecha pago</th>
                                            <th>Socio responsable</th>
                                            <th class="text-end">Interés ord. ($)</th>
                                            <th class="text-end">Mora ($)</th>
                                            <th class="text-end">Ganancia neta</th>
                                            <th>Concepto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($interesesList as $i): ?>
                                            <?php $subtotalInteres = (int) $i['abono_interes'] + (int) $i['abono_mora']; ?>
                                            <tr>
                                                <td><small class="text-muted"><?= htmlspecialchars((string) substr((string)$i['fecha_pago'], 0, 10), ENT_QUOTES, 'UTF-8') ?></small></td>
                                                <td>
                                                    <strong><?= htmlspecialchars((string) $i['socio_nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <?php if (!empty($i['socio_numero'])): ?>
                                                        <small class="text-muted">#<?= (int) $i['socio_numero'] ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">$<?= number_format((int) $i['abono_interes'], 0, ',', '.') ?></td>
                                                <td class="text-end text-danger"><?= (int) $i['abono_mora'] > 0 ? '$' . number_format((int) $i['abono_mora'], 0, ',', '.') : '-' ?></td>
                                                <td class="text-end fw-bold text-success">+$<?= number_format($subtotalInteres, 0, ',', '.') ?></td>
                                                <td><small class="text-muted"><?= htmlspecialchars((string) ($i['observacion'] ?: 'Abono a préstamo #' . $i['prestamo_id']), ENT_QUOTES, 'UTF-8') ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr class="fw-bold">
                                            <td colspan="4">TOTAL INTERESES Y MORA RECAUDADOS:</td>
                                            <td class="text-end text-success fs-6">+$<?= number_format((int) $balance['intereses_mora'], 0, ',', '.') ?></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Modal Detalle Actividades Especiales -->
        <div class="modal fade" id="modal-balance-actividades" tabindex="-1" aria-labelledby="modalActividadesTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <div>
                            <h5 class="modal-title fw-bold" id="modalActividadesTitle">🎉 Ganancia Neta de Actividades</h5>
                            <small class="text-muted">Ingresos, gastos y utilidades de eventos y actividades especiales</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-3">
                        <?php $actividadesList = $balanceDetalles['actividades'] ?? []; ?>
                        <?php if ($actividadesList === []): ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">Aún no hay actividades comunitarias registradas.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Actividad</th>
                                            <th>Fecha</th>
                                            <th class="text-center">Participantes</th>
                                            <th class="text-end">Ingresos</th>
                                            <th class="text-end">Gastos</th>
                                            <th class="text-end">Ganancia Neta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($actividadesList as $act): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars((string) $act['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                <td><small class="text-muted"><?= htmlspecialchars((string) $act['fecha'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                                <td class="text-center"><span class="badge bg-secondary"><?= (int) $act['participantes'] ?></span></td>
                                                <td class="text-end text-success">+$<?= number_format((int) $act['ingresos'], 0, ',', '.') ?></td>
                                                <td class="text-end text-danger">- $<?= number_format((int) $act['gastos'], 0, ',', '.') ?></td>
                                                <td class="text-end fw-bold <?= (int) $act['neto'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                    $<?= number_format((int) $act['neto'], 0, ',', '.') ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr class="fw-bold">
                                            <td colspan="5">NETO CONSOLIDADO ACTIVIDADES:</td>
                                            <td class="text-end fs-6 <?= (int) $balance['actividades_neto'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                $<?= number_format((int) $balance['actividades_neto'], 0, ',', '.') ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Modal Detalle Capital en Préstamos (Plata por fuera) -->
        <div class="modal fade" id="modal-balance-calle" tabindex="-1" aria-labelledby="modalCalleTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <div>
                            <h5 class="modal-title fw-bold text-primary" id="modalCalleTitle">📤 Préstamos Activos (Capital en la calle / por fuera)</h5>
                            <small class="text-muted">Dinero prestado a socios actualmente en circulación pendiente de cobrar</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-3">
                        <?php $calleList = $balanceDetalles['capital_calle'] ?? []; ?>
                        <?php if ($calleList === []): ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">¡Excelente! No hay préstamos activos pendientes en este momento (todo el capital está en caja).</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Socio responsable</th>
                                            <th class="text-end">Monto inicial</th>
                                            <th class="text-end">Saldo pendiente</th>
                                            <th class="text-center">Tasa</th>
                                            <th>Vence</th>
                                            <th class="text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($calleList as $p): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars((string) $p['socio_nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <?php if (!empty($p['socio_numero'])): ?>
                                                        <small class="text-muted">#<?= (int) $p['socio_numero'] ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">$<?= number_format((int) $p['monto'], 0, ',', '.') ?></td>
                                                <td class="text-end fw-bold text-primary">$<?= number_format((int) $p['saldo_capital'], 0, ',', '.') ?></td>
                                                <td class="text-center"><small><?= number_format((int) $p['tasa_mensual_bp'] / 100, 1) ?>%</small></td>
                                                <td><small><?= htmlspecialchars((string) $p['fecha_vencimiento'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                                <td class="text-center">
                                                    <?php if ((int) $p['en_mora'] === 1): ?>
                                                        <span class="badge bg-danger">Mora (<?= (int) $p['dias_mora'] ?>d)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Al día</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-light">
                                        <tr class="fw-bold">
                                            <td colspan="2">TOTAL CAPITAL EN LA CALLE:</td>
                                            <td class="text-end text-primary fs-6">$<?= number_format((int) $balance['capital_en_calle'], 0, ',', '.') ?></td>
                                            <td colspan="3"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <a href="/admin/prestamos" class="btn btn-outline-primary btn-sm">Ir al módulo de préstamos →</a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Modal Detalle Disponible en Caja (Arqueo completo) -->
        <div class="modal fade" id="modal-balance-disponible" tabindex="-1" aria-labelledby="modalDisponibleTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-md">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <div>
                            <h5 class="modal-title fw-bold text-white" id="modalDisponibleTitle">💵 Arqueo de Caja Disponible</h5>
                            <small class="text-white-50">Fondo líquido disponible hoy para desembolsos o gastos</small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="p-3 mb-3 bg-light rounded border">
                            <p class="text-muted small mb-2"><strong>¿De dónde sale la plata disponible en caja?</strong></p>
                            <p class="small text-muted mb-0">Es la suma de todo lo que los socios han aportado y los intereses ganados, menos el dinero que actualmente está prestado en la calle.</p>
                        </div>

                        <div class="list-group list-group-flush border rounded mb-3">
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span>(+) Cuotas obligatorias de reuniones</span>
                                <strong class="text-dark">$<?= number_format((int) $balance['cuotas_obligatorias'], 0, ',', '.') ?></strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span>(+) Ahorro voluntario de socios</span>
                                <strong class="text-dark">$<?= number_format((int) $balance['ahorro_voluntario'], 0, ',', '.') ?></strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span>(+) Intereses y mora cobrados</span>
                                <strong class="text-success">+$<?= number_format((int) $balance['intereses_mora'], 0, ',', '.') ?></strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span>(+) Utilidad neta de actividades</span>
                                <strong class="<?= (int) $balance['actividades_neto'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    $<?= number_format((int) $balance['actividades_neto'], 0, ',', '.') ?>
                                </strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2 bg-light fw-bold">
                                <span>(=) PATRIMONIO TOTAL RECAUDADO</span>
                                <strong class="fs-6 text-dark">$<?= number_format((int) $balance['total_patrimonio'], 0, ',', '.') ?></strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2 text-danger">
                                <span>(-) Menos: Capital prestado en la calle</span>
                                <strong>- $<?= number_format((int) $balance['capital_en_calle'], 0, ',', '.') ?></strong>
                            </div>
                        </div>

                        <div class="card border-success bg-success-subtle text-success-emphasis text-center p-3 rounded">
                            <small class="fw-semibold text-uppercase" style="letter-spacing: 0.05em;">Efectivo líquido en caja hoy</small>
                            <strong class="fs-3 my-1">$<?= number_format((int) $balance['caja_disponible'], 0, ',', '.') ?> <small class="fs-6">COP</small></strong>
                            <span class="small text-muted">Disponible para prestar o cubrir compromisos inmediatos.</span>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Entendido</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <section class="dashboard-bottom">
        <div class="section-heading">
            <div>
                <p class="eyebrow">SIGUIENTE PASO</p>
                <h2>Todo empieza por organizarse</h2>
            </div>
        </div>
        <div class="action-grid">
            <?php if ($rolActual === 'Presidente' && $sociosActivos < 50): ?>
                <a class="action-card" href="/admin/socios">
                    <span class="action-number">01</span><span class="action-arrow" aria-hidden="true">↗</span>
                    <strong>Registrar socios</strong>
                    <span>Agrega a las personas que hacen parte de la natillera y su número.</span>
                </a>
            <?php endif; ?>
            <?php if (in_array($rolActual, ['Presidente', 'Secretaria General'], true)): ?>
                <a class="action-card" href="/admin/reuniones">
                    <span class="action-number">02</span><span class="action-arrow" aria-hidden="true">↗</span>
                    <strong>Programar reunión</strong>
                    <span>Define la fecha, la hora y el valor de la cuota.</span>
                </a>
            <?php endif; ?>
            <?php if (in_array($rolActual, ['Presidente', 'Tesorera'], true)): ?>
                <a class="action-card action-card-featured" href="/admin/llamado-lista">
                    <span class="action-number">03</span><span class="action-arrow" aria-hidden="true">↗</span>
                    <strong>Pasar el llamado</strong>
                    <span>Registra cuotas y ahorros de la quincena.</span>
                </a>
            <?php endif; ?>
            <?php if ($rolActual === 'Secretaria Actividades'): ?>
                <a class="action-card action-card-featured" href="/admin/actividades">
                    <span class="action-number">01</span><span class="action-arrow" aria-hidden="true">↗</span>
                    <strong>Gestionar actividades</strong>
                    <span>Organiza actividades, movimientos y reparto entre participantes.</span>
                </a>
            <?php endif; ?>
        </div>
        <?php if ($sociosActivos === 0): ?>
            <p class="setup-tip"><span aria-hidden="true">✳</span> Consejo: puedes registrar varios socios a la vez, escribiendo un nombre por línea.</p>
        <?php endif; ?>
    </section>
</main>
