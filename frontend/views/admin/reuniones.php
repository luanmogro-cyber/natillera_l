<main class="content-page">
    <header class="page-title-row">
        <div>
            <p class="eyebrow">NOS ENCONTRAMOS PARA CRECER</p>
            <h1>Reuniones</h1>
            <p>Programa cada quincena y deja lista la cuota de la reunión.</p>
        </div>
        <span class="date-stamp" aria-hidden="true">✳</span>
    </header>

    <?php if (is_array($flashMessage ?? null)): ?>
        <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="management-grid">
        <article class="panel-card add-meeting-panel">
            <div class="panel-heading">
                <span class="panel-icon" aria-hidden="true">＋</span>
                <div><h2>Nueva reunión</h2><p>El valor se ajusta al tipo de encuentro.</p></div>
            </div>
            <form method="post" action="/admin/reuniones" class="form-stack" id="meeting-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <label class="field-label" for="fecha">Fecha</label>
                <input class="form-control" id="fecha" name="fecha" type="date" min="<?= date('Y-m-d') ?>" required>
                <label class="field-label" for="hora">Hora de encuentro</label>
                <input class="form-control" id="hora" name="hora" type="time" value="18:00" required>
                <label class="field-label" for="concepto">Tipo de quincena</label>
                <select class="form-select" id="concepto" name="concepto" required>
                    <option value="Cuota regular" data-valor="55000">Cuota regular</option>
                    <option value="Cuota + Rifa" data-valor="60000">Cuota + Rifa</option>
                    <option value="Cuota + Ronda" data-valor="65000">Cuota + Ronda</option>
                </select>
                <div class="meeting-amount-preview">
                    <span>Cuota por socio</span>
                    <strong><span id="meeting-amount">$55.000</span> <small>COP</small></strong>
                </div>
                <input type="hidden" name="valor_cuota" id="valor_cuota" value="55000">
                <button class="btn btn-primary" type="submit">Programar reunión <span aria-hidden="true">→</span></button>
            </form>
        </article>

        <article class="panel-card meetings-panel">
            <div class="panel-heading">
                <span class="panel-icon panel-icon-gold" aria-hidden="true">▦</span>
                <div><h2>Agenda</h2><p><?= count($reuniones) ?> reuniones en el calendario</p></div>
            </div>
            <?php if ($reuniones === []): ?>
                <div class="empty-state">
                    <img src="/img/semilla.svg" alt="" loading="lazy">
                    <p>Aún no hay reuniones. Programa la primera para empezar.</p>
                </div>
            <?php else: ?>
                <div class="meeting-agenda">
                    <?php $mesesEs = [1=>'ENE',2=>'FEB',3=>'MAR',4=>'ABR',5=>'MAY',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SEP',10=>'OCT',11=>'NOV',12=>'DIC']; ?>
                    <?php foreach ($reuniones as $reunion): ?>
                        <?php $tsReunion = strtotime($reunion['fecha']); ?>
                        <article class="agenda-row">
                            <div class="agenda-date">
                                <strong><?= htmlspecialchars(date('d', $tsReunion), ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars($mesesEs[(int) date('n', $tsReunion)] ?? strtoupper(date('M', $tsReunion)), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="agenda-copy">
                                <strong><?= htmlspecialchars($reunion['concepto'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars(date('Y', $tsReunion) . ' · ' . substr($reunion['hora'], 0, 5), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="agenda-price">$<?= number_format((int) $reunion['valor_cuota'], 0, ',', '.') ?></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </section>
</main>
