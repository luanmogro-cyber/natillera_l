<main class="auth-page auth-login">
    <section class="auth-card">
        <div class="auth-art">
            <img src="/img/logo.png" alt="Logo La Semilla — Natillera comunitaria" class="auth-art-logo">
            <div class="art-copy">
                <span class="art-kicker">Crecer es mejor en comunidad</span>
                <p>Cuidamos cada aporte. Celebramos cada paso que damos en equipo.</p>
            </div>
        </div>
        <div class="auth-form-panel">
            <a class="auth-brand" href="/" aria-label="La Semilla, inicio">
                <img src="/img/logo.png" alt="Logo La Semilla">
                <span>La Semilla<small>Natillera comunitaria</small></span>
            </a>
            <div class="auth-intro">
                <p class="eyebrow">QUÉ BUENO VERTE</p>
                <h1>Ingresa a tu espacio</h1>
                <p>Administra los aportes y sigue haciendo crecer los proyectos de todos.</p>
            </div>
            <?php if (is_array($flashMessage ?? null)): ?>
                <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
                    <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <form method="post" action="/login" class="form-stack">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label class="field-label" for="nombre">Nombre</label>
                <input class="form-control form-control-lg" id="nombre" name="nombre" autocomplete="username" maxlength="100" required>
                <label class="field-label" for="password">Contraseña</label>
                <div class="password-wrapper">
                    <input class="form-control form-control-lg pw-field" id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" aria-label="Ver contraseña" tabindex="-1">👁</button>
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit">Ingresar <span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-footnote"><span aria-hidden="true">✳</span> Un espacio privado para nuestra comunidad.</p>
        </div>
    </section>
</main>
