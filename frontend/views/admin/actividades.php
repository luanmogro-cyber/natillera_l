<main class="content-page">
    <header class="page-title-row">
        <div>
            <p class="eyebrow">IDEAS QUE NOS REÚNEN</p>
            <h1>Actividades</h1>
            <p>Controla los ingresos, gastos y el reparto entre quienes participaron.</p>
        </div>
        <span class="date-stamp" aria-hidden="true">✳</span>
    </header>

    <?php if (is_array($flashMessage ?? null)): ?>
        <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="management-grid activity-layout">
        <article class="panel-card">
            <div class="panel-heading">
                <span class="panel-icon" aria-hidden="true">＋</span>
                <div><h2>Nueva actividad</h2><p>Marca solo a los socios participantes.</p></div>
            </div>
            <?php if ($participantes === []): ?>
                <div class="empty-state"><p>Agrega socios antes de crear actividades.</p></div>
            <?php else: ?>
                <form method="post" action="/admin/actividades" class="form-stack">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label class="field-label" for="actividad-nombre">Nombre de la actividad</label>
                    <input class="form-control" id="actividad-nombre" name="nombre" maxlength="100" placeholder="Ej. Tamalada de diciembre" required>
                    <label class="field-label" for="actividad-fecha">Fecha</label>
                    <input class="form-control" id="actividad-fecha" name="fecha" type="date" value="<?= date('Y-m-d') ?>" required>
                    <fieldset class="participant-fieldset">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <legend class="field-label mb-0" style="float:none; width:auto; border:0; padding:0;">¿Quiénes participaron?</legend>
                            <span class="badge bg-light text-dark border" id="contador-participantes-seleccionados">0 seleccionados</span>
                        </div>

                        <!-- Barra de búsqueda en tiempo real -->
                        <div class="participant-search-box mb-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-muted">🔍</span>
                                <input type="search"
                                       id="buscar-participante"
                                       class="form-control form-control-sm"
                                       placeholder="Buscar por nombre o # de socio..."
                                       autocomplete="off"
                                       aria-label="Buscar participante por nombre o número">
                                <button class="btn btn-outline-secondary d-none" type="button" id="btn-limpiar-busqueda-participante" title="Limpiar filtro">✕</button>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1 px-1 text-muted" style="font-size: 0.73rem;">
                                <span id="contador-filtro-participantes">Mostrando todos (<?= count($participantes) ?>)</span>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-link p-0 text-decoration-none" id="btn-marcar-visibles" style="font-size: 0.73rem;">Marcar visibles</button>
                                    <span>·</span>
                                    <button type="button" class="btn btn-link p-0 text-decoration-none text-danger" id="btn-desmarcar-todos" style="font-size: 0.73rem;">Desmarcar todos</button>
                                </div>
                            </div>
                        </div>

                        <div class="participant-checklist" id="participant-checklist">
                            <?php foreach ($participantes as $socio): ?>
                                <label class="participant-option"
                                       data-nombre="<?= htmlspecialchars(mb_strtolower($socio['nombre_completo'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>"
                                       data-numero="<?= htmlspecialchars((string) ($socio['numero'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="checkbox" name="participantes[]" value="<?= (int) $socio['id'] ?>">
                                    <span>
                                        <?php if (!empty($socio['numero'])): ?>
                                            <strong class="text-secondary-emphasis me-1">#<?= (int) $socio['numero'] ?></strong>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            <div id="sin-participantes-filtro" class="text-center text-muted small py-3 d-none" style="grid-column: 1 / -1;">
                                No se encontraron socios que coincidan con la búsqueda.
                            </div>
                        </div>
                    </fieldset>
                    <button class="btn btn-primary" type="submit">Crear actividad <span aria-hidden="true">→</span></button>
                </form>
            <?php endif; ?>
        </article>

        <article class="panel-card activities-panel">
            <div class="panel-heading">
                <span class="panel-icon panel-icon-gold" aria-hidden="true">✳</span>
                <div><h2>Actividades registradas</h2><p><?= count($actividades) ?> encuentros especiales</p></div>
            </div>
            <?php if ($actividades === []): ?>
                <div class="empty-state">
                    <img src="/img/semilla.svg" alt="" loading="lazy">
                    <p>Cuando registres una actividad, sus cuentas aparecerán aquí.</p>
                </div>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($actividades as $actividad): ?>
                        <a class="activity-row<?= $actividadSeleccionada !== null && (int) $actividadSeleccionada['id'] === (int) $actividad['id'] ? ' selected' : '' ?>"
                           href="/admin/actividades?actividad_id=<?= (int) $actividad['id'] ?>">
                            <span class="activity-mark" aria-hidden="true">✳</span>
                            <span class="activity-description">
                                <strong><?= htmlspecialchars($actividad['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars($actividad['fecha'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $actividad['participantes'] ?> participantes</small>
                            </span>
                            <span class="activity-net<?= (int) $actividad['neto'] < 0 ? ' negative' : '' ?>">
                                <small>Neto</small>
                                $<?= number_format((int) $actividad['neto'], 0, ',', '.') ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>

    <?php if ($actividadSeleccionada !== null): ?>
        <section class="activity-detail panel-card">
            <header class="activity-detail-header">
                <div>
                    <p class="eyebrow">DETALLE DE LA ACTIVIDAD</p>
                    <h2><?= htmlspecialchars($actividadSeleccionada['nombre'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p><?= htmlspecialchars($actividadSeleccionada['fecha'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="activity-net-total">
                    <span>Ganancia neta</span>
                    <strong>$<?= number_format((int) $actividadSeleccionada['neto'], 0, ',', '.') ?></strong>
                </div>
            </header>

            <div class="activity-totals">
                <div><span>Ingresos</span><strong>$<?= number_format((int) $actividadSeleccionada['ingresos'], 0, ',', '.') ?></strong></div>
                <div><span>Gastos</span><strong>$<?= number_format((int) $actividadSeleccionada['gastos'], 0, ',', '.') ?></strong></div>
                <div><span>Participantes</span><strong><?= (int) $actividadSeleccionada['participantes'] ?></strong></div>
            </div>

            <div class="activity-detail-columns">
                <section>
                    <h3>Reparto entre participantes</h3>
                    <?php if ((int) $actividadSeleccionada['neto'] < 0): ?>
                        <p class="field-hint">Los gastos superan los ingresos; no hay ganancia neta para repartir.</p>
                    <?php elseif ($actividadSeleccionada['lista_participantes'] === []): ?>
                        <p class="field-hint">Esta actividad no tiene participantes.</p>
                    <?php else: ?>
                        <div class="share-list">
                            <?php foreach ($actividadSeleccionada['lista_participantes'] as $socio): ?>
                                <div>
                                    <span>
                                        <?php if (!empty($socio['numero'])): ?>
                                            <strong class="text-secondary-emphasis me-1">#<?= (int) $socio['numero'] ?></strong>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <strong>$<?= number_format((int) $socio['reparto'], 0, ',', '.') ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <section>
                    <h3>Registrar ingreso o gasto</h3>
                    <form method="post" action="/admin/actividades" class="form-stack movement-form">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="accion" value="movimiento">
                        <input type="hidden" name="actividad_id" value="<?= (int) $actividadSeleccionada['id'] ?>">
                        <label class="field-label" for="movimiento-tipo">Movimiento</label>
                        <select class="form-select" id="movimiento-tipo" name="tipo" required>
                            <option value="ingreso">Ingreso</option>
                            <option value="gasto">Gasto</option>
                        </select>
                        <label class="field-label" for="movimiento-concepto">Concepto</label>
                        <input class="form-control" id="movimiento-concepto" name="concepto" maxlength="120" placeholder="Ej. Venta de tamales" required>
                        <label class="field-label" for="movimiento-valor">Valor</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input class="form-control js-format-miles" id="movimiento-valor" name="valor" type="text" inputmode="numeric" placeholder="Ej. 50.000" required>
                        </div>
                        <button class="btn btn-primary" type="submit">Guardar movimiento</button>
                    </form>
                    <h3 class="movement-history-title">Movimientos registrados</h3>
                    <?php if ($movimientos === []): ?>
                        <p class="field-hint">Todavía no se han registrado movimientos.</p>
                    <?php else: ?>
                        <div class="movement-list">
                            <?php foreach ($movimientos as $movimiento): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded bg-light border">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="movement-sign <?= $movimiento['tipo'] === 'ingreso' ? 'income' : 'expense' ?>" aria-hidden="true"><?= $movimiento['tipo'] === 'ingreso' ? '+' : '−' ?></span>
                                        <span class="movement-label">
                                            <strong><?= htmlspecialchars($movimiento['concepto'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small class="d-block text-muted"><?= $movimiento['tipo'] === 'ingreso' ? 'Ingreso' : 'Gasto' ?> · <?= htmlspecialchars(substr((string)$movimiento['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></small>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <strong class="movement-value fs-6 <?= $movimiento['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?>">
                                            <?= $movimiento['tipo'] === 'ingreso' ? '+' : '−' ?>$<?= number_format((int) $movimiento['valor'], 0, ',', '.') ?>
                                        </strong>
                                        <form method="post" action="/admin/actividades" onsubmit="return confirm('¿Eliminar este movimiento?');" style="margin:0;">
                                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="accion" value="eliminar_movimiento">
                                            <input type="hidden" name="actividad_id" value="<?= (int) $actividadSeleccionada['id'] ?>">
                                            <input type="hidden" name="movimiento_id" value="<?= (int) $movimiento['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar" aria-label="Eliminar movimiento">✕</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </section>
    <?php endif; ?>
</main>
