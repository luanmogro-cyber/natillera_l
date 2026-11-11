<main class="content-page">
    <header class="page-title-row">
        <div>
            <p class="eyebrow">QUIÉNES HACEN POSIBLE TODO</p>
            <h1>Socios y Números</h1>
            <p>Administra los participantes de la natillera y su número asignado. El llamado a lista se ordena de menor a mayor según este número.</p>
        </div>
        <div class="capacity-badge"><strong><?= (int) $sociosActivos ?></strong><span>de 50 cupos</span></div>
    </header>

    <?php if (is_array($flashMessage ?? null)): ?>
        <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="management-grid">
        <?php if ($sociosActivos < 50): ?>
            <article class="panel-card add-members-panel">
                <div class="panel-heading">
                    <span class="panel-icon" aria-hidden="true">＋</span>
                    <div><h2>Agregar socios</h2><p>Asigna un número inicial (se asignan en orden) y escribe un nombre por línea.</p></div>
                </div>
                <form method="post" action="/admin/socios" class="form-stack">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label class="field-label" for="numero_inicial">Número asignado (desde)</label>
                    <input class="form-control" id="numero_inicial" name="numero_inicial" type="number" min="1" max="999" value="<?= (int) $siguienteNumero ?>" required>
                    <label class="field-label" for="nombres">Nombres completos</label>
                    <textarea class="form-control" id="nombres" name="nombres" rows="5" maxlength="6500" placeholder="María Fernanda López&#10;Carlos Andrés Ramírez&#10;..." required></textarea>
                    <div class="form-bottom">
                        <span class="field-hint"><?= 50 - (int) $sociosActivos ?> cupos disponibles</span>
                        <button class="btn btn-primary" type="submit">Guardar socios <span aria-hidden="true">→</span></button>
                    </div>
                </form>
            </article>
        <?php else: ?>
            <article class="panel-card capacity-full">
                <span class="panel-icon" aria-hidden="true">✳</span>
                <h2>¡Ya son 50!</h2>
                <p>La natillera alcanzó su capacidad de socios.</p>
            </article>
        <?php endif; ?>

        <article class="panel-card members-panel">
            <div class="panel-heading">
                <span class="panel-icon panel-icon-gold" aria-hidden="true">♧</span>
                <div><h2>Lista de socios (orden de menor a mayor)</h2><p><?= count($socios) ?> personas registradas</p></div>
            </div>
            <?php if ($socios === []): ?>
                <div class="empty-state">
                    <img src="/img/semilla.svg" alt="" loading="lazy">
                    <p>Cuando agregues a los socios, aparecerán aquí.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table member-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 145px;">N° asignado</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Acceso / Rol</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($socios as $indice => $socio): ?>
                            <?php $numero = $socio['numero'] !== null ? (int) $socio['numero'] : ($indice + 1); ?>
                            <tr>
                                <td>
                                    <form method="post" action="/admin/socios/numero" class="d-flex align-items-center gap-1">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="socio_id" value="<?= (int) $socio['id'] ?>">
                                        <input class="form-control form-control-sm text-center fw-bold"
                                               type="number" name="numero" min="1" max="999"
                                               value="<?= $numero ?>"
                                               style="width: 68px;"
                                               aria-label="Número de <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success py-1 px-2" title="Guardar número">✓</button>
                                    </form>
                                </td>
                                <td class="member-name-cell"><span class="small-avatar" aria-hidden="true"></span><?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if (!empty($socio['usuario_rol'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <?= htmlspecialchars((string) $socio['usuario_rol'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <small class="d-block text-muted">Usuario: <?= htmlspecialchars((string) $socio['usuario_nombre'], ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin acceso (crear en Equipo)</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-pill<?= (int) $socio['activo'] === 1 ? '' : ' status-inactive' ?>"><?= (int) $socio['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>
