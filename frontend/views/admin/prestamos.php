<main class="content-page">
    <header class="page-title-row">
        <div>
            <p class="eyebrow">APOYO PARA LOS PROYECTOS DE NUESTRA GENTE</p>
            <h1>Préstamos y Cobranza</h1>
            <p>Registra nuevos préstamos con evidencia fotográfica y firma, gestiona plazos, cobro de cuotas/intereses y mora del 10% mensual por retraso.</p>
        </div>
        <span class="date-stamp" aria-hidden="true">$</span>
    </header>

    <?php if (is_array($flashMessage ?? null)): ?>
        <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="interest-goal">
        <div>
            <p class="eyebrow">META DE INTERESES · <?= (int) $ano ?></p>
            <h2>Intereses recibidos por socio</h2>
        </div>
        <div class="goal-member-list">
            <?php if ($socios === []): ?>
                <p class="empty-inline">Primero registra socios para habilitar préstamos.</p>
            <?php endif; ?>
            <?php foreach ($socios as $socio): ?>
                <?php $porcentaje = min(100, (int) floor((int) $socio['intereses_ano'] * 100 / $metaInteres)); ?>
                <div class="goal-row">
                    <div class="goal-row-heading">
                        <strong><?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>$<?= number_format((int) $socio['intereses_ano'], 0, ',', '.') ?> <small>/ $<?= number_format($metaInteres, 0, ',', '.') ?></small></span>
                    </div>
                    <div class="goal-track" role="progressbar" aria-label="Avance de meta para <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>" aria-valuenow="<?= $porcentaje ?>" aria-valuemin="0" aria-valuemax="100">
                        <span style="width: <?= $porcentaje ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="management-grid loan-layout">
        <!-- FORMULARIO NUEVO PRÉSTAMO -->
        <article class="panel-card">
            <div class="panel-heading">
                <span class="panel-icon" aria-hidden="true">＋</span>
                <div>
                    <h2>Nuevo préstamo</h2>
                    <p>Tasa estándar 10% mensual. Se fija el plazo de pago; pasado el plazo se cobra mora del 10% mensual.</p>
                </div>
            </div>
            <?php if ($socios === []): ?>
                <div class="empty-state"><p>Agrega socios antes de registrar un préstamo.</p></div>
            <?php else: ?>
                <form method="post" action="/admin/prestamos" enctype="multipart/form-data" class="form-stack" id="form-nuevo-prestamo">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    
                    <label class="field-label" for="socio_id">Socio responsable / fiador</label>
                    <select class="form-select" id="socio_id" name="socio_id" required>
                        <?php foreach ($socios as $socio): ?>
                            <?php
                            $disponible = max(0, $socio['credito']['limite'] - $socio['saldo_activo']);
                            $limite = (int) $socio['credito']['limite'];
                            $tasaTexto = number_format((int) $socio['credito']['tasa_mensual_bp'] / 100, 2, ',', '.');
                            ?>
                            <option value="<?= (int) $socio['id'] ?>"
                                    data-disponible="<?= $disponible ?>"
                                    data-limite="<?= $limite ?>"
                                    data-tasa="<?= $tasaTexto ?>"
                                    <?= $disponible === 0 ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?> · cupo $<?= number_format($disponible, 0, ',', '.') ?><?= $disponible === 0 ? ' (Sin cupo)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="cupo-info" class="small text-muted mb-1"></div>

                    <label class="field-label" for="prestatario_tercero">Prestatario (opcional)</label>
                    <input class="form-control" id="prestatario_tercero" name="prestatario_tercero" maxlength="120" placeholder="Déjalo vacío si el préstamo es para el socio fiador">

                    <label class="field-label" for="monto">Monto del préstamo</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input class="form-control js-format-miles" id="monto" name="monto" type="text" inputmode="numeric" placeholder="Ej. 1.000.000" required>
                    </div>
                    <div id="cupo-error" class="alert alert-danger py-2 px-3 small mt-2 mb-1 d-none" role="alert"></div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="field-label" for="fecha_entrega">Fecha de entrega</label>
                            <input class="form-control" id="fecha_entrega" name="fecha_entrega" type="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="field-label" for="plazo_meses">Plazo de pago</label>
                            <select class="form-select" id="plazo_meses" name="plazo_meses" required>
                                <option value="1" selected>1 mes</option>
                                <option value="2">2 meses</option>
                                <option value="3">3 meses</option>
                                <option value="6">6 meses</option>
                                <option value="12">12 meses</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-light border small text-muted py-2 mb-2">
                        ⏱ <strong>Plazo y mora:</strong> Si no se paga dentro del tiempo fijado, el sistema cobrará un <strong>10% mensual de interés de mora</strong> por cada mes de retraso sobre el monto prestado.
                    </div>

                    <!-- EVIDENCIA FOTOGRÁFICA -->
                    <label class="field-label" for="foto_evidencia">Foto de entrega / recibo (opcional)</label>
                    <input class="form-control" type="file" id="foto_evidencia" name="foto_evidencia" accept="image/*" capture="environment">
                    <div id="foto-preview-box" class="d-none mt-2 text-center border rounded p-2 bg-light">
                        <img id="foto-preview-img" src="" alt="Vista previa" style="max-height: 140px;" class="img-fluid rounded">
                    </div>

                    <!-- FIRMA DIGITAL -->
                    <label class="field-label mt-2">Firma digital del prestatario / socio (opcional)</label>
                    <div class="signature-box border rounded p-2 bg-light text-center">
                        <canvas id="signature-canvas" width="400" height="130" class="border rounded bg-white w-100" style="touch-action: none; cursor: crosshair;"></canvas>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <small class="text-muted">Firme con el dedo (celular) o con el ratón</small>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="btn-clear-sig">Limpiar firma</button>
                        </div>
                        <input type="hidden" name="firma_evidencia" id="firma_evidencia">
                    </div>

                    <button class="btn btn-primary mt-3" type="submit" id="btn-submit-prestamo">
                        Registrar préstamo <span aria-hidden="true">→</span>
                    </button>
                </form>
            <?php endif; ?>
        </article>

        <!-- LISTA E HISTORIAL DE PRÉSTAMOS -->
        <article class="panel-card loans-panel">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="panel-heading mb-0">
                    <span class="panel-icon panel-icon-gold" aria-hidden="true">$</span>
                    <div>
                        <h2>Historial y cobranza</h2>
                        <p><?= count($prestamos) ?> préstamos registrados</p>
                    </div>
                </div>
                <?php if ($prestamos !== []): ?>
                    <a href="/admin/prestamos/exportar" class="btn btn-sm btn-outline-success no-print">📥 Exportar cartera (CSV)</a>
                <?php endif; ?>
            </div>
            <?php if ($prestamos === []): ?>
                <div class="empty-state">
                    <img src="/img/semilla.svg" alt="" loading="lazy">
                    <p>Los préstamos registrados aparecerán aquí con su saldo, intereses y abonos recibidos.</p>
                </div>
            <?php else: ?>
                <div class="loan-list">
                    <?php foreach ($prestamos as $prestamo): ?>
                        <?php
                        $esPendiente = ($prestamo['estado'] === 'pendiente');
                        $esRechazado = ($prestamo['estado'] === 'rechazado');
                        $cardBorder  = $esPendiente ? 'border border-warning border-2' : (!empty($prestamo['en_mora']) ? 'border-danger' : '');
                        ?>
                        <article class="loan-card <?= $cardBorder ?>">
                            <div class="loan-card-top">
                                <div>
                                    <strong><?= htmlspecialchars($prestamo['prestatario_tercero'] ?: $prestamo['socio_nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= $prestamo['prestatario_tercero'] ? 'Fiador: ' . htmlspecialchars($prestamo['socio_nombre'], ENT_QUOTES, 'UTF-8') : 'Préstamo directo a socio' ?></span>
                                </div>
                                <div class="d-flex gap-1 align-items-center flex-wrap">
                                    <?php if ($esPendiente): ?>
                                        <span class="badge bg-warning text-dark">⏳ Pendiente de aprobación</span>
                                    <?php elseif ($esRechazado): ?>
                                        <span class="badge bg-danger">✗ Rechazado</span>
                                    <?php elseif ($prestamo['estado'] === 'activo'): ?>
                                        <?php if (!empty($prestamo['en_mora'])): ?>
                                            <span class="badge bg-danger">🔴 En mora (<?= (int) $prestamo['meses_mora'] ?> mes<?= (int)$prestamo['meses_mora'] > 1 ? 'es' : '' ?>)</span>
                                        <?php else: ?>
                                            <span class="status-pill status-loan-active">✓ Al día</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-pill">✓ Pagado</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="loan-numbers">
                                <span>Capital prestado<strong>$<?= number_format((int) $prestamo['monto'], 0, ',', '.') ?></strong></span>
                                <?php if (!$esPendiente && !$esRechazado): ?>
                                <span>Saldo capital<strong class="<?= (int)$prestamo['saldo_capital'] > 0 ? 'text-primary' : 'text-success' ?>">$<?= number_format((int) $prestamo['saldo_capital'], 0, ',', '.') ?></strong></span>
                                <?php endif; ?>
                                <span>Tasa mensual<strong><?= number_format((int) $prestamo['tasa_mensual_bp'] / 100, 2, ',', '.') ?>%</strong></span>
                            </div>

                            <div class="bg-light p-2 rounded small mb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Entrega: <strong><?= htmlspecialchars($prestamo['fecha_entrega'], ENT_QUOTES, 'UTF-8') ?></strong> (Plazo <?= (int) $prestamo['plazo_meses'] ?>m)</span>
                                    <span>Vence: <strong class="<?= !empty($prestamo['en_mora']) ? 'text-danger fw-bold' : '' ?>"><?= htmlspecialchars((string) $prestamo['fecha_vencimiento'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                                </div>
                                <?php if (!$esPendiente && !$esRechazado): ?>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Interés ordinario: <strong>$<?= number_format((int) $prestamo['interes_estimado'], 0, ',', '.') ?></strong> (Pagado: $<?= number_format((int) $prestamo['interes_recibido'], 0, ',', '.') ?>)</span>
                                    <?php if (!empty($prestamo['en_mora']) || (int)($prestamo['mora_recibida'] ?? 0) > 0): ?>
                                        <span class="text-danger fw-semibold">Mora (10% mensual): <strong>$<?= number_format((int) $prestamo['mora_estimada'], 0, ',', '.') ?></strong></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($prestamo['estado'] === 'activo'): ?>
                                    <div class="d-flex justify-content-between border-top pt-1 text-danger">
                                        <span>Total pendiente hoy para liquidar:</span>
                                        <strong>$<?= number_format((int) $prestamo['total_a_liquidar'], 0, ',', '.') ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($esPendiente): ?>
                                    <div class="alert alert-warning mb-0 py-1 px-2 small mt-1">
                                        ⚠️ Este préstamo está <strong>pendiente de aprobación</strong> del Presidente. Hasta que sea aprobado, no aparece en el historial del socio.
                                    </div>
                                <?php endif; ?>
                                <?php if ($esRechazado && !empty($prestamo['observacion_aprobacion'])): ?>
                                    <div class="alert alert-danger mb-0 py-1 px-2 small mt-1">
                                        Motivo del rechazo: <?= htmlspecialchars($prestamo['observacion_aprobacion'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ACCIONES -->
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <?php if ($esPendiente && ($currentUser['rol'] ?? '') === 'Presidente'): ?>
                                    <!-- Botones APROBAR / RECHAZAR -->
                                    <button type="button" class="btn btn-sm btn-success fw-semibold"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-aprobar-<?= (int) $prestamo['id'] ?>">
                                        ✅ Aprobar préstamo
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-rechazar-<?= (int) $prestamo['id'] ?>">
                                        ✗ Rechazar
                                    </button>
                                <?php elseif ($prestamo['estado'] === 'activo'): ?>
                                    <button type="button" class="btn btn-sm btn-success"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-pago-<?= (int) $prestamo['id'] ?>">
                                        💵 Recibir pago / abono
                                    </button>
                                <?php endif; ?>

                                <?php if (!$esRechazado): ?>
                                    <?php if (!empty($prestamo['foto_evidencia']) || !empty($prestamo['firma_evidencia'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-ver-evidencia-<?= (int) $prestamo['id'] ?>">
                                            📷 Ver evidencia
                                        </button>
                                    <?php elseif (!$esPendiente): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-subir-evidencia-<?= (int) $prestamo['id'] ?>">
                                            ＋ Adjuntar firma/foto
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- HISTORIAL DE ABONOS -->
                            <?php if (!empty($prestamo['pagos'])): ?>
                                <details class="mt-2 small">
                                    <summary class="text-secondary" style="cursor:pointer;">Ver historial de <?= count($prestamo['pagos']) ?> pago(s)</summary>
                                    <div class="table-responsive mt-1">
                                        <table class="table table-sm table-bordered bg-white mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Capital</th>
                                                    <th>Interés</th>
                                                    <th>Mora</th>
                                                    <th>Total</th>
                                                    <th>Recibido por</th>
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
                                                        <td><small><?= htmlspecialchars((string) $pago['registrado_por_nombre'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            <?php endif; ?>

                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>

<!-- MODALES DE PRÉSTAMOS (fuera de grids/tarjetas para centrado perfecto en pantalla) -->
<?php foreach ($prestamos as $prestamo): ?>
    <?php
    $esPendiente = ($prestamo['estado'] === 'pendiente');
    $esRechazado = ($prestamo['estado'] === 'rechazado');
    ?>
    <?php if ($esPendiente): ?>
        <div class="modal fade" id="modal-aprobar-<?= (int) $prestamo['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form method="post" action="/admin/prestamos/aprobar" class="modal-content border-success">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="prestamo_id" value="<?= (int) $prestamo['id'] ?>">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">✅ Aprobar préstamo #<?= (int) $prestamo['id'] ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            Prestatario: <strong><?= htmlspecialchars($prestamo['prestatario_tercero'] ?: $prestamo['socio_nombre'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                            Monto: <strong>$<?= number_format((int) $prestamo['monto'], 0, ',', '.') ?></strong><br>
                            Plazo: <strong><?= (int) $prestamo['plazo_meses'] ?> mes(es)</strong> · Vence: <strong><?= htmlspecialchars((string) $prestamo['fecha_vencimiento'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </p>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Observación (opcional)</label>
                            <input class="form-control form-control-sm" type="text" name="observacion" placeholder="Ej. Aprobado en reunión del <?= date('d/m/Y') ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm fw-semibold">✅ Confirmar aprobación</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modal-rechazar-<?= (int) $prestamo['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form method="post" action="/admin/prestamos/rechazar" class="modal-content border-danger">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="prestamo_id" value="<?= (int) $prestamo['id'] ?>">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">✗ Rechazar préstamo #<?= (int) $prestamo['id'] ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">
                            Solicitante: <strong><?= htmlspecialchars($prestamo['prestatario_tercero'] ?: $prestamo['socio_nombre'], ENT_QUOTES, 'UTF-8') ?></strong><br>
                            Monto: <strong>$<?= number_format((int) $prestamo['monto'], 0, ',', '.') ?></strong>
                        </p>
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-danger">Motivo del rechazo</label>
                            <input class="form-control form-control-sm border-danger" type="text" name="observacion" placeholder="Ej. Cupo insuficiente" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger btn-sm">✗ Confirmar rechazo</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($prestamo['estado'] === 'activo'): ?>
        <div class="modal fade" id="modal-pago-<?= (int) $prestamo['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form method="post" action="/admin/prestamos/pago" class="modal-content">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="prestamo_id" value="<?= (int) $prestamo['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">💵 Recibir pago · Préstamo #<?= (int) $prestamo['id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="bg-light border rounded p-2 mb-3 small">
                            <div>Socio / Prestatario: <strong><?= htmlspecialchars($prestamo['prestatario_tercero'] ?: $prestamo['socio_nombre'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                            <div class="d-flex justify-content-between mt-1">
                                <span>Saldo capital: <strong class="text-primary">$<?= number_format((int) $prestamo['saldo_capital'], 0, ',', '.') ?></strong></span>
                                <span>Interés pendiente: <strong>$<?= number_format((int) $prestamo['interes_pendiente'], 0, ',', '.') ?></strong></span>
                            </div>
                            <?php if (!empty($prestamo['en_mora'])): ?>
                                <div class="text-danger mt-1">Mora pendiente (10% mensual): <strong>$<?= number_format((int) $prestamo['mora_pendiente'], 0, ',', '.') ?></strong></div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-semibold mb-1">Fecha del pago</label>
                                <input class="form-control form-control-sm" type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-semibold mb-1">Abono a capital ($)</label>
                                <input class="form-control form-control-sm js-format-miles" type="text" name="abono_capital" placeholder="0" value="0">
                            </div>
                        </div>
                        <p class="text-muted small mb-2" style="font-size: 0.76rem;">Si se paga el total del capital, el préstamo queda liquidado.</p>

                        <div class="row g-2 mb-2">
                            <div class="col-12 <?= !empty($prestamo['en_mora']) ? 'col-sm-6' : '' ?>">
                                <label class="form-label small fw-semibold mb-1">Intereses ordinarios ($)</label>
                                <input class="form-control form-control-sm js-format-miles" type="text" name="abono_interes" value="<?= number_format((int)$prestamo['interes_pendiente'], 0, ',', '.') ?>">
                            </div>
                            <?php if (!empty($prestamo['en_mora'])): ?>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label small fw-semibold text-danger mb-1">Interés de mora ($)</label>
                                    <input class="form-control form-control-sm js-format-miles border-danger" type="text" name="abono_mora" value="<?= number_format((int)$prestamo['mora_pendiente'], 0, ',', '.') ?>">
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="abono_mora" value="0">
                            <?php endif; ?>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold mb-1">Observación (opcional)</label>
                            <input class="form-control form-control-sm" type="text" name="observacion" placeholder="Ej. Cuota quincenal en reunión">
                        </div>

                        <div class="p-2 border rounded bg-light mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100 js-btn-pagar-todo"
                                    data-capital="<?= (int)$prestamo['saldo_capital'] ?>"
                                    data-interes="<?= (int)$prestamo['interes_pendiente'] ?>"
                                    data-mora="<?= (int)($prestamo['mora_pendiente'] ?? 0) ?>">
                                ⚡ Rellenar para liquidar todo ($<?= number_format((int)$prestamo['total_a_liquidar'], 0, ',', '.') ?>)
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm">Guardar pago</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($prestamo['foto_evidencia']) || !empty($prestamo['firma_evidencia'])): ?>
        <div class="modal fade" id="modal-ver-evidencia-<?= (int) $prestamo['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Evidencia de entrega · Préstamo #<?= (int) $prestamo['id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p class="small text-muted mb-2">
                            Prestatario: <strong><?= htmlspecialchars($prestamo['prestatario_tercero'] ?: $prestamo['socio_nombre'], ENT_QUOTES, 'UTF-8') ?></strong> · Monto: <strong>$<?= number_format((int) $prestamo['monto'], 0, ',', '.') ?></strong>
                        </p>
                        <?php if (!empty($prestamo['foto_evidencia'])): ?>
                            <p class="fw-semibold mb-1 small text-start">Foto del recibo / entrega:</p>
                            <img src="<?= htmlspecialchars($prestamo['foto_evidencia'], ENT_QUOTES, 'UTF-8') ?>" alt="Foto comprobante" class="img-fluid rounded border mb-3" style="max-height: 240px;">
                        <?php endif; ?>
                        <?php if (!empty($prestamo['firma_evidencia'])): ?>
                            <p class="fw-semibold mb-1 small text-start">Firma digital del socio:</p>
                            <div class="border rounded p-2 bg-light d-inline-block">
                                <img src="<?= htmlspecialchars($prestamo['firma_evidencia'], ENT_QUOTES, 'UTF-8') ?>" alt="Firma digital" class="img-fluid" style="max-height: 120px;">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($prestamo['foto_evidencia']) || empty($prestamo['firma_evidencia'])): ?>
        <div class="modal fade" id="modal-subir-evidencia-<?= (int) $prestamo['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form method="post" action="/admin/prestamos/evidencia" enctype="multipart/form-data" class="modal-content">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="prestamo_id" value="<?= (int) $prestamo['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Adjuntar evidencia · Préstamo #<?= (int) $prestamo['id'] ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Foto de entrega / recibo</label>
                            <input class="form-control form-control-sm" type="file" name="foto_evidencia" accept="image/*" capture="environment">
                        </div>
                        <div>
                            <label class="form-label small fw-semibold">Firma digital</label>
                            <div class="signature-box border rounded p-2 bg-light text-center">
                                <canvas id="extra-canvas-<?= (int) $prestamo['id'] ?>" width="350" height="120" class="border rounded bg-white w-100 js-extra-sig" data-sig-target="#extra-firma-<?= (int) $prestamo['id'] ?>" style="touch-action: none; cursor: crosshair;"></canvas>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted">Firme aquí</small>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 js-clear-extra-sig" data-canvas="extra-canvas-<?= (int) $prestamo['id'] ?>" data-sig-target="#extra-firma-<?= (int) $prestamo['id'] ?>">Limpiar</button>
                                </div>
                                <input type="hidden" name="firma_evidencia" id="extra-firma-<?= (int) $prestamo['id'] ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar evidencia</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
