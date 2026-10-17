/* Pessoas+ - abas do resultado da pesquisa (BM.4-11, D-92 desenho B). Arquivo proprio. PESSOASPLUS_BUILD_BM4_11 */
(function () {
    'use strict';

    function iniciar() {
        var nav = document.querySelector('.pp-casca [data-pp-abas]');
        if (!nav) {
            return;
        }
        var botoes = nav.querySelectorAll('[data-pp-aba]');
        var paineis = document.querySelectorAll('.pp-casca [data-pp-painel]');
        var nomes = [];
        Array.prototype.forEach.call(botoes, function (b) { nomes.push(b.getAttribute('data-pp-aba')); });

        function mostrar(nome, gravar) {
            if (nomes.indexOf(nome) === -1) {
                nome = nomes[0];
            }
            Array.prototype.forEach.call(botoes, function (b) {
                var ativa = b.getAttribute('data-pp-aba') === nome;
                b.classList.toggle('is-ativa', ativa);
                b.setAttribute('aria-selected', ativa ? 'true' : 'false');
            });
            Array.prototype.forEach.call(paineis, function (p) {
                p.hidden = p.getAttribute('data-pp-painel') !== nome;
            });
            if (gravar && window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState(null, '', '#' + nome);
            }
        }
        Array.prototype.forEach.call(botoes, function (b) {
            b.addEventListener('click', function () { mostrar(b.getAttribute('data-pp-aba'), true); });
        });
        var inicial = (window.location.hash || '').replace('#', '');
        // Filtro de situacao (?situacao=) e aba das respostas: abre direto nelas.
        if (inicial === '' && /[?&]situacao=/.test(window.location.search)) {
            inicial = 'respostas';
        }
        mostrar(inicial || nomes[0], false);
        window.addEventListener('hashchange', function () { mostrar((window.location.hash || '').replace('#', ''), false); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
