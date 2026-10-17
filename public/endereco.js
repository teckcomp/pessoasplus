/* Pessoas+ - editor: pesquisa anonima mantem a pagina publica ligada (BM.4-17, D-98). O servidor tambem forca. */
(function () {
    var form = document.querySelector('form[data-pp-formulario]');
    if (!form) { return; }
    var caixa = form.querySelector('[data-pp-publica-marcar] input[type=checkbox]');
    var aviso = form.querySelector('[data-pp-publica-auto]');
    function aplicar() {
        var m = form.querySelector('input[name="modo"]:checked');
        var anonimo = !!m && m.value === 'anonimo';
        if (caixa) {
            if (anonimo) { caixa.checked = true; }
            caixa.readOnly = anonimo;
            caixa.setAttribute('aria-disabled', anonimo ? 'true' : 'false');
        }
        if (aviso) { aviso.hidden = !anonimo; }
    }
    form.querySelectorAll('input[name="modo"]').forEach(function (r) { r.addEventListener('change', aplicar); });
    if (caixa) { caixa.addEventListener('click', function (e) { if (caixa.readOnly) { e.preventDefault(); caixa.checked = true; } }); }
    aplicar();
})();
