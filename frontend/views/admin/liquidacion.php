<main class="content-page">
    <header class="page-title-row">
        <div>
            <p class="eyebrow">CERRAMOS UN AÑO Y RECONOCEMOS CADA APORTE</p>
            <h1>Liquidación anual</h1>
            <p>Las utilidades de intereses se reparten proporcionalmente al ahorro de cada socio.</p>
        </div>
        <span class="date-stamp" aria-hidden="true">◷</span>
    </header>

    <?php if (is_array($flashMessage ?? null)): ?>
        <div class="notice notice-<?= htmlspecialchars($flashMessage['type'], ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flashMessage['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php $filasLiquidacion = $cierre !== null ? $cierre['socios'] : $socios; ?>

    <section class="settlement-toolbar">
        <form method="get" action="/admin/liquidacion" class="settlement-year-form">
            <label for="settlement-year">Año a consultar</label>
            <select class="form-select" id="settlement-year" name="ano">
                <?php for ($optionYear = $anoActual; $optionYear >= max(2000, $anoActual - 10); $optionYear--): ?>
                    <option value="<?= $optionYear ?>" <?= $optionYear === $ano ? 'selected' : '' ?>><?= $optionYear ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <div class="settlement-total"><span>Intereses y mora en <?= (int) $ano ?></span><strong>$<?= number_format((int) $totalIntereses, 0, ',', '.') ?></strong></div>
        <div class="settlement-total"><span>Ahorro usado para ponderar</span><strong>$<?= number_format((int) $totalAhorros, 0, ',', '.') ?></strong></div>
        <?php if ($filasLiquidacion !== [] && (int) $totalAhorros > 0): ?>
            <div class="d-flex gap-2 align-items-center no-print">
                <a class="btn btn-sm btn-outline-success" href="/admin/liquidacion/exportar?ano=<?= (int) $ano ?>">📥 Exportar Excel (CSV)</a>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">🖨️ Imprimir / PDF</button>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($cierre !== null): ?>
        <div class="notice notice-success settlement-locked" role="status">
            Liquidación guardada el <?= htmlspecialchars(substr((string) $cierre['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?>. Este corte ya no se puede modificar.
        </div>
    <?php endif; ?>

    <?php if ($filasLiquidacion === []): ?>
        <div class="panel-card empty-state">
            <img src="/img/semilla.svg" alt="" loading="lazy">
            <p>No hay socios ni ahorros registrados para ese año.</p>
        </div>
    <?php elseif ((int) $totalAhorros <= 0): ?>
        <div class="panel-card empty-state">
            <img src="/img/semilla.svg" alt="" loading="lazy">
            <p>Hay socios registrados, pero todavía no hay ahorro anual para distribuir las utilidades proporcionalmente.</p>
        </div>
    <?php else: ?>
        <article class="panel-card settlement-panel">
            <div class="panel-heading">
                <span class="panel-icon panel-icon-gold" aria-hidden="true">✳</span>
                <div><h2>Reparto proporcional</h2><p>La suma de las participaciones coincide exactamente con el total de intereses.</p></div>
            </div>
            <div class="table-responsive">
                <table class="table member-table settlement-table align-middle mb-0">
                    <thead>
                    <tr><th scope="col">N°</th><th scope="col">Socio</th><th scope="col">Ahorro del año</th><th scope="col">Peso</th><th scope="col">Utilidad</th><th scope="col">Total a entregar</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filasLiquidacion as $i => $socio): ?>
                        <?php
                        $ahorro = (int) ($socio['ahorro_base'] ?? 0);
                        $utilidad = (int) ($socio['utilidad_repartida'] ?? 0);
                        $porcentaje = (int) $totalAhorros > 0 ? $ahorro * 100 / (int) $totalAhorros : 0;
                        $num = $socio['numero'] ?? ($i + 1);
                        ?>
                        <tr>
                            <td class="text-muted fw-semibold">#<?= (int) $num ?></td>
                            <td class="settlement-person"><?= htmlspecialchars($socio['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>$<?= number_format($ahorro, 0, ',', '.') ?></td>
                            <td><?= number_format($porcentaje, 2, ',', '.') ?>%</td>
                            <td class="settlement-payout">$<?= number_format($utilidad, 0, ',', '.') ?></td>
                            <td class="fw-bold text-success">$<?= number_format($ahorro + $utilidad, 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr><th></th><th>Total</th><th>$<?= number_format((int) $totalAhorros, 0, ',', '.') ?></th><th>100%</th><th>$<?= number_format((int) $totalIntereses, 0, ',', '.') ?></th><th>$<?= number_format((int) $totalAhorros + (int) $totalIntereses, 0, ',', '.') ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </article>
        <?php if ($cierre === null): ?>
            <?php if ($ano === $anoActual): ?>
                <p class="settlement-info">Vista previa del año en curso. Podrás confirmar el cierre cuando comience el próximo año.</p>
            <?php elseif ($totalAhorros > 0): ?>
                <form method="post" action="/admin/liquidacion" class="settlement-confirm">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="ano" value="<?= (int) $ano ?>">
                    <p>Al confirmar, se guardará una copia inmodificable de los saldos y el reparto de <?= (int) $ano ?>.</p>
                    <button class="btn btn-primary" type="submit">Confirmar liquidación <?= (int) $ano ?></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
