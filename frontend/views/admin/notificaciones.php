<main class="content-page">
    <header class="page-title-row">
        <div>
            <p class="eyebrow">MENSAJES PARA MANTENERNOS CERCA</p>
            <h1>Notificaciones</h1>
            <p>Envía un aviso importante a un socio o a toda la comunidad.</p>
        </div>
        <span class="date-stamp" aria-hidden="true">♧</span>
    </header>

    <?php if (is_array($flashMessage ?? null)): ?>
        <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$pushConfigurado): ?>
        <div class="notice notice-warning push-configuration-note">
            Para activar el envío push, configura las llaves VAPID en el archivo local <code>backend/.env</code>.
            Ejecuta <code>php bin/generate_vapid_keys.php</code> desde la carpeta <code>backend</code> y copia las dos llaves resultantes a <code>backend/.env</code>
            y reinicia Apache. No compartas ni publiques la llave privada.
        </div>
    <?php endif; ?>

    <section class="management-grid notification-layout">
        <article class="panel-card">
            <div class="panel-heading">
                <span class="panel-icon" aria-hidden="true">＋</span>
                <div><h2>Redactar aviso</h2><p>El destinatario debe haber habilitado push en su dispositivo.</p></div>
            </div>
            <form method="post" action="/admin/notificaciones" class="form-stack">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label class="field-label" for="destinatario">Enviar a</label>
                <select class="form-select" id="destinatario" name="destinatario" required>
                    <option value="todos">Todos los miembros (excepto usted)</option>
                    <?php foreach ($socios as $socio): ?>
                        <option value="<?= (int) $socio['id'] ?>"><?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="field-label" for="titulo">Título</label>
                <input class="form-control" id="titulo" name="titulo" maxlength="100" placeholder="Ej. Reunión de quincena" required>
                <label class="field-label" for="mensaje">Mensaje</label>
                <textarea class="form-control" id="mensaje" name="mensaje" maxlength="500" rows="4" placeholder="Escribe el mensaje para la comunidad..." required></textarea>
                <span class="field-hint">Máximo 500 caracteres.</span>
                <button class="btn btn-primary" type="submit" <?= !$pushConfigurado ? 'disabled' : '' ?>>Enviar notificación <span aria-hidden="true">→</span></button>
            </form>
        </article>

        <article class="panel-card notification-history-panel">
            <div class="panel-heading">
                <span class="panel-icon panel-icon-gold" aria-hidden="true">◷</span>
                <div><h2>Envíos recientes</h2><p>Últimas 20 notificaciones enviadas desde este equipo</p></div>
            </div>
            <?php if ($notificaciones === []): ?>
                <div class="empty-state">
                    <img src="/img/semilla.svg" alt="" loading="lazy">
                    <p>Cuando envíes un aviso, el resultado quedará registrado aquí.</p>
                </div>
            <?php else: ?>
                <div class="notification-list">
                    <?php foreach ($notificaciones as $notificacion): ?>
                        <article class="notification-row">
                            <span class="notification-dot" aria-hidden="true"></span>
                            <div class="notification-copy">
                                <strong><?= htmlspecialchars($notificacion['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <p><?= htmlspecialchars($notificacion['mensaje'], ENT_QUOTES, 'UTF-8') ?></p>
                                <small>
                                    <?= $notificacion['socio_nombre'] === null ? 'Todos los socios' : htmlspecialchars($notificacion['socio_nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars((string) $notificacion['created_at'], ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </div>
                            <span class="notification-result">
                                <?= (int) $notificacion['enviados'] ?> ok
                                <?php if ((int) $notificacion['fallidos'] > 0): ?>
                                    <small><?= (int) $notificacion['fallidos'] ?> fallidos</small>
                                <?php endif; ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>
