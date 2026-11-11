<main class="content-page">
    <header class="page-title-row">
        <div>
            <p class="eyebrow">ACUERDOS CLAROS PARA CADA PERSONA</p>
            <h1>Topes de crédito</h1>
            <p>Configura el cupo máximo de préstamo y la tasa de interés mensual de cada socio. Por defecto: $2.000.000 al 10% mensual.</p>
        </div>
        <span class="date-stamp" aria-hidden="true">⌁</span>
    </header>

    <div class="alert alert-light border small text-muted mb-3">
        💡 <strong>¿Para qué sirve este módulo?</strong> Aquí defines cuánto dinero puede pedir prestado cada socio como máximo y a qué porcentaje de interés mensual cuando se le registre un préstamo en el módulo <strong>Préstamos</strong>. Las tarjetas permanecen aquí para que puedas consultarlas o ajustarlas cuando lo necesites.
    </div>

    <?php if (is_array($flashMessage ?? null)): ?>
        <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <strong><?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
    <?php endif; ?>

    <section class="credit-settings-list">
        <?php if ($socios === []): ?>
            <article class="panel-card empty-state"><p>Primero registra los socios para configurar sus condiciones.</p></article>
        <?php endif; ?>
        <?php foreach ($socios as $i => $socio): ?>
            <?php
            $limite = (int) $socio['credito']['limite'];
            $usado = (int) $socio['saldo_activo'];
            $disponible = max(0, $limite - $usado);
            $numeroMostrar = $socio['numero'] !== null ? (int) $socio['numero'] : ($i + 1);
            ?>
            <article class="credit-setting-card">
                <div class="credit-setting-member">
                    <span class="small-avatar" aria-hidden="true"></span>
                    <div>
                        <strong>#<?= $numeroMostrar ?> · <?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>
                            Usado: <strong>$<?= number_format($usado, 0, ',', '.') ?></strong> ·
                            Cupo disponible: <strong class="<?= $disponible > 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($disponible, 0, ',', '.') ?></strong>
                        </span>
                    </div>
                </div>
                <form method="post" action="/admin/creditos" class="credit-setting-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="socio_id" value="<?= (int) $socio['id'] ?>">
                    <label class="credit-field">
                        <span>Límite máximo</span>
                        <span class="input-group"><span class="input-group-text">$</span><input class="form-control js-format-miles" type="text" name="limite" inputmode="numeric" value="<?= number_format($limite, 0, ',', '.') ?>" required></span>
                    </label>
                    <label class="credit-field">
                        <span>Tasa mensual</span>
                        <span class="input-group"><input class="form-control" type="number" name="tasa" min="0" max="50" step="0.01" value="<?= number_format((int) $socio['credito']['tasa_mensual_bp'] / 100, 2, '.', '') ?>" required><span class="input-group-text">%</span></span>
                    </label>
                    <button class="btn btn-outline-success" type="submit">Guardar</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</main>
