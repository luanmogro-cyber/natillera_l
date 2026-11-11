<main class="auth-page">
    <section class="auth-card setup-card">
        <div class="auth-art">
            <img src="/img/logo.png" alt="Logo La Semilla — Natillera comunitaria" class="auth-art-logo">
            <div class="art-copy">
                <span class="art-kicker">Un comienzo que crece</span>
                <p>Una comunidad, muchos sueños y un lugar para cuidarlos juntos.</p>
            </div>
        </div>
        <div class="auth-form-panel">
            <a class="auth-brand" href="/" aria-label="La Semilla, inicio">
                <img src="/img/logo.png" alt="Logo La Semilla">
                <span>La Semilla<small>Natillera comunitaria</small></span>
            </a>
            <div class="auth-intro">
                <p class="eyebrow">PRIMER PASO</p>
                <h1>Creemos tu espacio</h1>
                <p>Configura el acceso de Presidencia. Este nombre y esta contraseña serán tus datos de ingreso y también se creará tu perfil de socio.</p>
            </div>
            <?php if (is_array($flashMessage ?? null)): ?>
                <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
                    <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/instalar" class="form-stack">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label class="field-label" for="nombre">Tu nombre para ingresar</label>
                <input class="form-control form-control-lg" id="nombre" name="nombre" autocomplete="name" maxlength="100" required>
                <label class="field-label" for="password">Contraseña</label>
                <div class="password-wrapper">
                    <input class="form-control form-control-lg pw-field" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                    <button type="button" class="pw-toggle" aria-label="Ver contraseña" tabindex="-1">👁</button>
                </div>
                <p class="field-hint">Usa al menos 12 caracteres. Después podrás ingresar socios, clientes, secretarias y tesoreras.</p>
                <button class="btn btn-primary btn-lg w-100" type="submit">Crear mi natillera <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-footnote"><span aria-hidden="true">✳</span> Tus datos quedan guardados en tu equipo.</p>
        </div>
    </section>
</main>
