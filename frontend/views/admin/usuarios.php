<main class="content-page">
    <header class="page-title-row">
        <div>
            <p class="eyebrow">CUENTAS DE INGRESO Y PERMISOS</p>
            <h1>Equipo y Accesos</h1>
            <p>Crea el usuario, rol y contraseña para que Tesorera, Secretarias y Socios puedan iniciar sesión en el sistema.</p>
        </div>
        <span class="date-stamp" aria-hidden="true">◎</span>
    </header>

    <?php if (is_array($flashMessage ?? null)): ?>
        <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="management-grid">
        <article class="panel-card add-members-panel">
            <div class="panel-heading">
                <span class="panel-icon" aria-hidden="true">＋</span>
                <div><h2>Crear acceso al sistema</h2><p>Asigna usuario, rol y contraseña (mínimo 12 caracteres).</p></div>
            </div>
            <form method="post" action="/admin/equipo" class="form-stack">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <?php if (!empty($sociosSinCuenta)): ?>
                    <label class="field-label" for="socio_id_existente">Vincular a socio existente (opcional)</label>
                    <select class="form-select" id="socio_id_existente" name="socio_id_existente">
                        <option value="">＋ Crear socio nuevo automáticamente</option>
                        <?php foreach ($sociosSinCuenta as $sc): ?>
                            <option value="<?= (int) $sc['id'] ?>">
                                #<?= (int) ($sc['numero'] ?? $sc['id']) ?> · <?= htmlspecialchars($sc['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <div id="nuevo-socio-numero-box">
                    <label class="field-label" for="numero_socio">Número de socio (si es nuevo)</label>
                    <input class="form-control" id="numero_socio" name="numero_socio" type="number" min="1" max="999" value="<?= (int) $siguienteNumero ?>">
                </div>

                <label class="field-label" for="nombre">Nombre de usuario para ingresar</label>
                <input class="form-control" id="nombre" name="nombre" autocomplete="name" maxlength="100" required>

                <label class="field-label" for="rol-cuenta">Rol / Permisos</label>
                <select class="form-select" id="rol-cuenta" name="rol" required>
                    <option value="Socio" <?= $sociosActivos >= 50 ? 'disabled' : '' ?>>Socio<?= $sociosActivos >= 50 ? ' (cupo completo)' : '' ?></option>
                    <option value="Tesorera">Tesorera (Llamado a lista y cobro de préstamos)</option>
                    <option value="Secretaria Actividades">Secretaria de Actividades</option>
                    <option value="Secretaria General">Secretaria General (Reuniones, créditos y notificaciones)</option>
                </select>

                <label class="field-label" for="password">Contraseña inicial</label>
                <div class="password-wrapper">
                    <input class="form-control pw-field" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                    <button type="button" class="pw-toggle" aria-label="Ver contraseña" tabindex="-1">👁</button>
                </div>

                <p class="field-hint">
                    <strong>Diferencia con Socios:</strong> en <em>Socios</em> organizas la lista y los números del grupo; aquí en <em>Equipo</em> creas la contraseña y el rol para que la persona pueda entrar al sistema.
                </p>
                <button class="btn btn-primary" type="submit">Crear cuenta de acceso <span aria-hidden="true">→</span></button>
            </form>
        </article>

        <article class="panel-card members-panel">
            <div class="panel-heading">
                <span class="panel-icon panel-icon-gold" aria-hidden="true">◎</span>
                <div><h2>Cuentas con acceso</h2><p><?= count($usuarios) ?> personas pueden iniciar sesión</p></div>
            </div>
            <div class="team-list">
                <?php foreach ($usuarios as $usuario): ?>
                    <div class="team-row">
                        <span class="team-avatar" aria-hidden="true">
                            <?= $usuario['socio_numero'] !== null ? '#' . (int) $usuario['socio_numero'] : htmlspecialchars(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1, 'UTF-8'), 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <div class="team-identity">
                            <strong><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span>
                                Usuario: <?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($usuario['socio_nombre']) && $usuario['socio_nombre'] !== $usuario['nombre']): ?>
                                    · Socio: <?= htmlspecialchars($usuario['socio_nombre'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="team-role">
                            <span class="status-pill"><?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    </section>

    <script>
    (function() {
        var sel = document.getElementById('socio_id_existente');
        var box = document.getElementById('nuevo-socio-numero-box');
        if (sel && box) {
            var toggle = function() { box.style.display = sel.value === '' ? '' : 'none'; };
            sel.addEventListener('change', toggle);
            toggle();
        }
    })();
    </script>
</main>
