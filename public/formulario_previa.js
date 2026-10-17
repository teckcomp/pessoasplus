/* Pessoas+ - previa do formulario como quem responde vai ver (BM.4-9, D-92). Arquivo proprio. PESSOASPLUS_BUILD_BM4_9 */
(function () {
    'use strict';

    function iniciar() {
        var form = document.querySelector('.pp-casca form[data-pp-formulario]');
        var botao = form ? form.querySelector('[data-pp-previa-form]') : null;
        var janela = document.querySelector('.pp-casca [data-pp-previa-form-janela]');
        if (!form || !botao || !janela || typeof janela.showModal !== 'function') {
            if (botao) { botao.hidden = true; }
            return;
        }
        var conteudo = janela.querySelector('[data-pp-previa-form-conteudo]');
        var fechar = janela.querySelectorAll('[data-pp-previa-form-fechar]');
        var url = form.getAttribute('data-pp-url-previa-form') || '';
        var campo = form.querySelector('[data-pp-perguntas-campo]');
        var carregando = false;

        function valor(nome) {
            var el = form.querySelector('[name="' + nome + '"]');
            return el ? (el.value || '') : '';
        }
        function modo() {
            var marcado = form.querySelector('input[name="modo"]:checked');
            return marcado ? marcado.value : valor('modo');
        }
        function abrir() {
            if (carregando) { return; }
            carregando = true;
            botao.disabled = true;
            var corpo = new URLSearchParams();
            corpo.append('titulo', valor('titulo'));
            corpo.append('descricao', valor('descricao'));
            corpo.append('modo', modo());
            corpo.append('porta', form.getAttribute('data-pp-porta') || '');
            corpo.append('perguntas_json', campo ? (campo.value || '[]') : '[]');
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Glpi-Csrf-Token': form.getAttribute('data-pp-csrf') || ''
                },
                body: corpo.toString()
            }).then(function (r) {
                return r.json().then(function (json) {
                    if (!r.ok) { throw new Error((json && json.erro) || ('Erro ' + r.status)); }
                    return json;
                });
            }).then(function (json) {
                conteudo.innerHTML = json && typeof json.html === 'string' ? json.html : '';
                janela.showModal();
            }).catch(function (e) {
                conteudo.innerHTML = '';
                var p = document.createElement('p');
                p.className = 'pp-nota';
                p.textContent = 'Não foi possível montar a prévia: ' + (e && e.message ? e.message : 'erro');
                conteudo.appendChild(p);
                janela.showModal();
            }).then(function () {
                carregando = false;
                botao.disabled = false;
            });
        }
        botao.addEventListener('click', abrir);
        Array.prototype.forEach.call(fechar, function (b) {
            b.addEventListener('click', function () { janela.close(); });
        });
        janela.addEventListener('click', function (ev) {
            if (ev.target === janela) { janela.close(); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
