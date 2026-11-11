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
                    <p>Estado consolidado del fondo en tiempo real.</p>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100">
                        <small class="text-muted d-block">Cuotas obligatorias</small>
                        <strong class="fs-6 text-dark">$<?= number_format((int) $balance['cuotas_obligatorias'], 0, ',', '.') ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100">
                        <small class="text-muted d-block">Ahorro voluntario</small>
                        <strong class="fs-6 text-dark">$<?= number_format((int) $balance['ahorro_voluntario'], 0, ',', '.') ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100">
                        <small class="text-muted d-block">Intereses y mora</small>
                        <strong class="fs-6 text-success">+$<?= number_format((int) $balance['intereses_mora'], 0, ',', '.') ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100">
                        <small class="text-muted d-block">Neto actividades</small>
                        <strong class="fs-6 <?= (int) $balance['actividades_neto'] >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format((int) $balance['actividades_neto'], 0, ',', '.') ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border bg-light h-100">
                        <small class="text-muted d-block">Capital en préstamos</small>
                        <strong class="fs-6 text-primary">$<?= number_format((int) $balance['capital_en_calle'], 0, ',', '.') ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded border border-success bg-success-subtle h-100">
                        <small class="text-success-emphasis d-block fw-semibold">Disponible en caja</small>
                        <strong class="fs-6 text-success-emphasis">$<?= number_format((int) $balance['caja_disponible'], 0, ',', '.') ?></strong>
                    </div>
                </div>
            </div>
        </section>
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
