<?php if (is_array($currentUser ?? null)): ?>
        </div>
    </div>
<?php else: ?>
    </div>
<?php endif; ?>
<!-- Bootstrap Bundle con Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (($activePage ?? '') === 'llamado'): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/js/llamado_lista.js" defer></script>
<?php endif; ?>
<?php if (is_array($currentUser ?? null) && ($vapidPublicKey ?? '') !== ''): ?>
    <script src="/js/push_client.js" defer></script>
<?php endif; ?>
<!-- app.js carga en todas las páginas para SW, ojo contraseña y transiciones -->
<script src="/js/app.js" defer></script>
<!-- Sesión por pestaña: usa sessionStorage (aislado por pestaña) y NUNCA cookies compartidas -->
<?php if (!is_array($currentUser ?? null)): ?>
<script>
(function() {
    var urlParams = new URLSearchParams(window.location.search);
    var tab = urlParams.get('_tab');
    if (!tab || !/^[a-zA-Z0-9]{8,48}$/.test(tab)) {
        try { tab = window.sessionStorage.getItem('nl_tab'); } catch (e) { tab = null; }
        if (!tab || !/^[a-zA-Z0-9]{8,48}$/.test(tab)) {
            var arr = new Uint8Array(8);
            window.crypto.getRandomValues(arr);
            tab = Array.from(arr).map(function(b){ return b.toString(16).padStart(2,'0'); }).join('');
        }
        urlParams.set('_tab', tab);
        var newUrl = window.location.pathname + '?' + urlParams.toString();
        history.replaceState(null, '', newUrl);
    }
    try { window.sessionStorage.setItem('nl_tab', tab); } catch (e) {}
    window.__NL_TAB = tab;
    document.querySelectorAll('form').forEach(function(form) {
        if (!form.querySelector('[name="_tab"]')) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = '_tab'; inp.value = tab;
            form.appendChild(inp);
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
