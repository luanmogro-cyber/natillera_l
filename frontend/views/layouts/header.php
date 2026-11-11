<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#183d31">
    <meta name="description" content="La Semilla — Natillera comunitaria. Administra aportes, reuniones y socios de tu grupo de ahorro.">
    <title><?= htmlspecialchars($pageTitle ?? 'Natillera', ENT_QUOTES, 'UTF-8') ?> | La Semilla</title>
    <link rel="icon" type="image/png" href="/img/logo.png">
    <link rel="apple-touch-icon" href="/img/logo.png">
    <link rel="manifest" href="/manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    <script>
    (function() {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('_tab') || <?= json_encode((string) ($currentTabId ?? ''), JSON_UNESCAPED_SLASHES) ?>;
        if (tab && /^[a-zA-Z0-9]{8,48}$/.test(tab)) {
            try { window.sessionStorage.setItem('nl_tab', tab); } catch (e) {}
            window.__NL_TAB = tab;
        } else {
            try {
                var stored = window.sessionStorage.getItem('nl_tab');
                if (stored && /^[a-zA-Z0-9]{8,48}$/.test(stored)) {
                    window.__NL_TAB = stored;
                }
            } catch (e) {}
        }

        if (window.fetch) {
            var origFetch = window.fetch;
            window.fetch = function(input, init) {
                var activeTab = window.__NL_TAB;
                if (activeTab && typeof input === 'string' && input.startsWith('/') && !input.includes('_tab=')) {
                    input += (input.includes('?') ? '&' : '?') + '_tab=' + encodeURIComponent(activeTab);
                }
                return origFetch.call(this, input, init);
            };
        }
    })();
    </script>
</head>
<body>
<?php
$tabQuery = !empty($currentTabId) ? '?_tab=' . rawurlencode((string) $currentTabId) : '';
?>
<?php if (is_array($currentUser ?? null)): ?>
    <?php $rolActual = $currentUser['rol'] ?? ''; ?>
    <?php $vistaPersonalPresidente = $rolActual === 'Presidente' && ($_SESSION['vista_presidencial'] ?? false); ?>
    <?php
    // Mensajes contextuales por módulo
    $moduleMsgs = [
        'panel'         => '📊 Aquí tienes el pulso de tu natillera hoy.',
        'socio'         => '🌱 Este es el resumen de tu camino en la natillera.',
        'socios'        => '👥 Cada socio es una semilla que hace crecer el grupo.',
        'usuarios'      => '🔑 Solo el Presidente puede registrar y gestionar accesos.',
        'llamado'       => '✅ Registra los aportes de cada quincena con cuidado.',
        'reuniones'     => '📅 Organiza los encuentros que unen a la comunidad.',
        'prestamos'     => '💵 Lleva el control de los préstamos con transparencia.',
        'liquidacion'   => '🏁 El cierre anual: reparte los frutos del esfuerzo compartido.',
        'actividades'   => '🎉 Cada actividad fortalece el vínculo del grupo.',
        'creditos'      => '⚙️ Define los límites de crédito de cada socio.',
        'notificaciones'=> '🔔 Mantén al equipo informado en tiempo real.',
    ];
    $currentMsg = $moduleMsgs[$activePage ?? ''] ?? null;
    ?>
    <div class="app-shell">
        <aside class="app-sidebar" aria-label="Navegación principal">
            <a class="brand-lockup" href="/panel<?= $tabQuery ?>">
                <span class="brand-mark"><img src="/img/logo.png" alt="La Semilla"></span>
                <span><strong>La Semilla</strong><small>Natillera comunitaria</small></span>
            </a>
            <p class="sidebar-caption">TU ESPACIO</p>
            <nav class="sidebar-nav">
                <a class="nav-link<?= in_array(($activePage ?? ''), ['panel', 'socio'], true) ? ' active' : '' ?>" href="<?= ($rolActual === 'Socio' || $vistaPersonalPresidente ? '/socio' : '/panel') . $tabQuery ?>">
                    <span class="nav-icon" aria-hidden="true">⌂</span> <?= $rolActual === 'Socio' || $vistaPersonalPresidente ? 'Mi ahorro' : 'Resumen' ?>
                </a>
                <?php if (!$vistaPersonalPresidente && in_array($rolActual, ['Presidente', 'Tesorera'], true)): ?>
                    <a class="nav-link<?= ($activePage ?? '') === 'llamado' ? ' active' : '' ?>" href="/admin/llamado-lista<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">✓</span> Llamado a lista
                    </a>
                    <a class="nav-link<?= ($activePage ?? '') === 'prestamos' ? ' active' : '' ?>" href="/admin/prestamos<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">$</span> Préstamos
                    </a>
                    <a class="nav-link<?= ($activePage ?? '') === 'liquidacion' ? ' active' : '' ?>" href="/admin/liquidacion<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">◷</span> Liquidación
                    </a>
                <?php endif; ?>
                <?php if (!$vistaPersonalPresidente && in_array($rolActual, ['Presidente', 'Secretaria Actividades'], true)): ?>
                    <a class="nav-link<?= ($activePage ?? '') === 'actividades' ? ' active' : '' ?>" href="/admin/actividades<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">✳</span> Actividades
                    </a>
                <?php endif; ?>
                <?php if ($rolActual === 'Presidente' && !$vistaPersonalPresidente): ?>
                    <a class="nav-link<?= ($activePage ?? '') === 'socios' ? ' active' : '' ?>" href="/admin/socios<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">♧</span> Socios
                    </a>
                    <a class="nav-link<?= ($activePage ?? '') === 'usuarios' ? ' active' : '' ?>" href="/admin/equipo<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">◎</span> Equipo
                    </a>
                <?php endif; ?>
                <?php if (!$vistaPersonalPresidente && in_array($rolActual, ['Presidente', 'Secretaria General'], true)): ?>
                    <a class="nav-link<?= ($activePage ?? '') === 'reuniones' ? ' active' : '' ?>" href="/admin/reuniones<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">▦</span> Reuniones
                    </a>
                    <a class="nav-link<?= ($activePage ?? '') === 'creditos' ? ' active' : '' ?>" href="/admin/creditos<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">⌁</span> Crédito
                    </a>
                    <a class="nav-link<?= ($activePage ?? '') === 'notificaciones' ? ' active' : '' ?>" href="/admin/notificaciones<?= $tabQuery ?>">
                        <span class="nav-icon" aria-hidden="true">♧</span> Notificaciones
                    </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-note">
                <span class="note-leaf" aria-hidden="true">✳</span>
                <p>Pequeños aportes, grandes sueños compartidos.</p>
            </div>
        </aside>
        <div class="app-workspace">
            <header class="topbar">
                <div class="topbar-left">
                    <span class="topbar-context"><?= $vistaPersonalPresidente ? 'Mi cuenta de socio' : 'Natillera comunitaria' ?></span>
                    <?php if ($currentMsg): ?>
                        <span class="module-tip"><?= htmlspecialchars($currentMsg, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <div class="topbar-account">
                    <?php if ($rolActual === 'Presidente' && is_int($currentUser['socio_id'] ?? null)): ?>
                        <form method="post" action="/presidente/vista<?= $tabQuery ?>" class="view-toggle-form">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <button class="btn-view-toggle" type="submit"><?= $vistaPersonalPresidente ? 'Volver a administración' : 'Mi cuenta de socio' ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if (($vapidPublicKey ?? '') !== ''): ?>
                        <button class="push-toggle" type="button"
                                id="enable-push"
                                data-vapid-public-key="<?= htmlspecialchars($vapidPublicKey, ENT_QUOTES, 'UTF-8') ?>"
                                data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="Activar notificaciones en este dispositivo"
                                title="Activar notificaciones">♢</button>
                    <?php endif; ?>
                    <img class="account-avatar" src="/img/logo.png" alt="" aria-hidden="true">
                    <span class="account-name">
                        <strong><?= htmlspecialchars((string) $currentUser['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <small><?= htmlspecialchars((string) $rolActual, ENT_QUOTES, 'UTF-8') ?></small>
                    </span>
                    <form method="post" action="/logout<?= $tabQuery ?>" class="logout-form">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-logout" aria-label="Cerrar sesión" title="Cerrar sesión">↗</button>
                    </form>
                </div>
            </header>
<?php else: ?>
    <div class="auth-shell">
<?php endif; ?>
