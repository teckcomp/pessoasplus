/* Pessoas+ - comportamento da casca. PESSOASPLUS_BUILD_BP0 PESSOASPLUS_BUILD_BP2A PESSOASPLUS_BUILD_BP2B PESSOASPLUS_BUILD_BP2C PESSOASPLUS_BUILD_BP3A PESSOASPLUS_BUILD_BP3B PESSOASPLUS_BUILD_BP3C
 * Sem variavel global (T-30). Botoes com data-pp-demo mostram um aviso
 * de que a acao chega num bloco futuro, em vez de nao fazer nada.
 */
(function () {
    'use strict';

    /* Marca o elemento como ja iniciado e diz se era a primeira vez.
     * Na pagina inicial do GLPI o mural chega por AJAX, numa aba, e pode
     * aparecer duas vezes (aba Mural e aba Tudo): este script roda a cada
     * carga, entao cada bloco so recebe os eventos uma vez. */
    function primeiraVez(el) {
        if (el.hasAttribute('data-pp-pronto')) {
            return false;
        }
        el.setAttribute('data-pp-pronto', '');
        return true;
    }

    function iniciar() {
        document.querySelectorAll('.pp-casca').forEach(function (casca) {
            if (!primeiraVez(casca)) {
                return;
            }
            var aviso = casca.querySelector('.pp-aviso');
            var temporizador = null;

            casca.addEventListener('click', function (evento) {
                var alvo = evento.target.closest('[data-pp-demo]');
                if (!alvo || !aviso) {
                    return;
                }
                evento.preventDefault();
                aviso.textContent = 'Demonstração. ' + alvo.getAttribute('data-pp-demo');
                aviso.hidden = false;
                if (temporizador) {
                    clearTimeout(temporizador);
                }
                temporizador = setTimeout(function () {
                    aviso.hidden = true;
                }, 4000);
            });
        });
    }

    /* Filtros por chip e busca por texto em listas marcadas com
     * [data-pp-filtravel]. Itens: [data-pp-item] com data-pp-situacao
     * (palavras separadas por espaco). "todos" mostra tudo. */
    function normalizar(texto) {
        return String(texto || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function aplicarFiltro(bloco) {
        var ativo = bloco.querySelector('[data-pp-filtro].is-ativo');
        var filtro = ativo ? ativo.getAttribute('data-pp-filtro') : 'todos';
        var campo = bloco.querySelector('[data-pp-busca]');
        var busca = normalizar(campo ? campo.value : '').trim();
        var visiveis = 0;
        bloco.querySelectorAll('[data-pp-item]').forEach(function (item) {
            var tokens = (item.getAttribute('data-pp-situacao') || '').split(/\s+/);
            var ok = filtro === 'todos' || tokens.indexOf(filtro) !== -1;
            if (ok && busca !== '') {
                ok = normalizar(item.textContent).indexOf(busca) !== -1;
            }
            item.hidden = !ok;
            if (ok) {
                visiveis++;
            }
        });
        var vazio = bloco.querySelector('[data-pp-vazio]');
        if (vazio) {
            vazio.hidden = visiveis !== 0;
        }
        bloco.querySelectorAll('[data-pp-mostra-em]').forEach(function (extra) {
            extra.hidden = extra.getAttribute('data-pp-mostra-em') !== filtro || busca !== '';
        });
    }

    function iniciarFiltros() {
        document.querySelectorAll('.pp-casca [data-pp-filtravel]').forEach(function (bloco) {
            if (!primeiraVez(bloco)) {
                return;
            }
            bloco.addEventListener('click', function (evento) {
                var chip = evento.target.closest('[data-pp-filtro]');
                if (!chip || !bloco.contains(chip)) {
                    return;
                }
                bloco.querySelectorAll('[data-pp-filtro]').forEach(function (outro) {
                    outro.classList.toggle('is-ativo', outro === chip);
                    outro.setAttribute('aria-pressed', outro === chip ? 'true' : 'false');
                });
                aplicarFiltro(bloco);
            });
            var campo = bloco.querySelector('[data-pp-busca]');
            if (campo) {
                campo.addEventListener('input', function () { aplicarFiltro(bloco); });
            }
            aplicarFiltro(bloco);
        });
    }

    /* Ciencia na casca: a marcacao libera o botao; confirmar mostra o
     * recibo com a hora do navegador. Nada e enviado ao servidor. */
    function iniciarCiencia() {
        document.querySelectorAll('.pp-casca [data-pp-ciencia]').forEach(function (bloco) {
            var marcar = bloco.querySelector('[data-pp-ciencia-marcar]');
            var botao = bloco.querySelector('[data-pp-ciencia-confirmar]');
            var form = bloco.querySelector('[data-pp-ciencia-form]');
            var recibo = bloco.querySelector('[data-pp-ciencia-ok]');
            var hora = bloco.querySelector('[data-pp-ciencia-hora]');
            if (!marcar || !botao || !form || !recibo) {
                return;
            }
            marcar.addEventListener('change', function () {
                botao.disabled = !marcar.checked;
            });
            botao.addEventListener('click', function () {
                if (!marcar.checked) {
                    return;
                }
                if (hora) {
                    var agora = new Date();
                    var dois = function (n) { return (n < 10 ? '0' : '') + n; };
                    hora.textContent = dois(agora.getDate()) + '/' + dois(agora.getMonth() + 1) + '/' + agora.getFullYear()
                        + ' ' + dois(agora.getHours()) + ':' + dois(agora.getMinutes());
                }
                form.hidden = true;
                recibo.hidden = false;
            });
        });
        document.querySelectorAll('.pp-casca [data-pp-imprimir]').forEach(function (botao) {
            botao.addEventListener('click', function () { window.print(); });
        });
    }

    /* Novo comunicado: tipo alterna texto livre e documento do Codex+,
     * a pre-visualizacao acompanha o que e digitado e o publico e contado
     * ao vivo. Publico vazio nunca vira "todos": mostra zero e trava
     * o botao Publicar. Nada e enviado ao servidor na casca. */
    function iniciarNovo() {
        document.querySelectorAll('.pp-casca [data-pp-novo]').forEach(function (raiz) {
            var q = function (sel) { return raiz.querySelector(sel); };
            var titulo = q('[data-pp-titulo]');
            var texto = q('[data-pp-texto]');
            var vinculo = q('[data-pp-vinculo]');
            var publicar = q('[data-pp-publicar]');

            function tipoAtual() {
                return raiz.querySelector('input[name="pp_tipo"]:checked');
            }

            function atualizarPrevia() {
                var tipo = tipoAtual();
                var normativa = tipo && tipo.value === 'normativa';
                q('[data-pp-bloco-texto]').hidden = normativa;
                q('[data-pp-bloco-codex]').hidden = !normativa;
                q('[data-pp-prev-tipo]').textContent = tipo ? tipo.getAttribute('data-pp-rotulo') : '';
                q('[data-pp-prev-resposta]').textContent = tipo ? tipo.getAttribute('data-pp-resposta') : '';
                if (normativa) {
                    var doc = raiz.querySelector('input[name="pp_codex"]:checked');
                    q('[data-pp-prev-titulo]').textContent = doc ? doc.getAttribute('data-pp-codex-titulo') + ' · ' + doc.value : 'Escolha um documento do Codex+';
                    q('[data-pp-prev-texto]').textContent = 'Texto da revisão vigente no Codex+.';
                } else {
                    q('[data-pp-prev-titulo]').textContent = (titulo && titulo.value.trim()) || 'Sem título';
                    var corpo = texto ? texto.value.trim() : '';
                    q('[data-pp-prev-texto]').textContent = corpo.length > 140 ? corpo.slice(0, 140) + '…' : corpo;
                }
            }

            function contarPublico() {
                var modo = vinculo ? vinculo.value : 'todos';
                var total = 0;
                raiz.querySelectorAll('[data-pp-grupo]').forEach(function (g) {
                    if (!g.checked) {
                        return;
                    }
                    var clt = parseInt(g.getAttribute('data-pp-clt'), 10) || 0;
                    var pj = parseInt(g.getAttribute('data-pp-pj'), 10) || 0;
                    total += modo === 'clt' ? clt : (modo === 'pj' ? pj : clt + pj);
                });
                q('[data-pp-total]').textContent = String(total);
                q('[data-pp-prev-total]').textContent = String(total);
                q('[data-pp-zero]').hidden = total !== 0;
                q('[data-pp-resultado]').hidden = total === 0;
                if (publicar) {
                    publicar.disabled = total === 0;
                }
            }

            raiz.addEventListener('change', function () {
                atualizarPrevia();
                contarPublico();
            });
            raiz.addEventListener('input', atualizarPrevia);
            atualizarPrevia();
            contarPublico();
        });
    }

    /* Mural do colaborador (BP.3b): curtidas e comentarios so na tela,
     * para a demonstracao ter a sensacao de rede social (D-57). Nada e
     * enviado ao servidor; todo texto entra por textContent. */
    function el(tag, classe, texto) {
        var e = document.createElement(tag);
        if (classe) {
            e.className = classe;
        }
        if (texto !== undefined && texto !== null) {
            e.textContent = String(texto);
        }
        return e;
    }

    function alternarCurtida(botao) {
        var ligado = botao.getAttribute('aria-pressed') === 'true';
        var contador = botao.querySelector('[data-pp-curtidas]');
        if (contador) {
            var n = parseInt(contador.textContent, 10) || 0;
            contador.textContent = String(ligado ? Math.max(0, n - 1) : n + 1);
        }
        botao.setAttribute('aria-pressed', ligado ? 'false' : 'true');
    }

    function novoComentario(quem, iniciais, texto, resposta) {
        var li = el('li', 'pp-mc-comentario' + (resposta ? ' is-resposta' : ''));
        var avatar = el('span', 'pp-mc-avatar' + (resposta ? ' is-pequeno' : ''), iniciais);
        avatar.setAttribute('aria-hidden', 'true');
        li.appendChild(avatar);
        var balao = el('div', 'pp-mc-balao');
        var cab = el('div', 'pp-mc-quem');
        cab.appendChild(el('strong', null, quem));
        cab.appendChild(el('span', null, resposta ? ' · resposta · agora' : ' · agora'));
        balao.appendChild(cab);
        balao.appendChild(el('p', null, texto));
        li.appendChild(balao);
        return li;
    }

    function iniciarMural() {
        document.querySelectorAll('.pp-casca [data-pp-post]').forEach(function (post) {
            if (!primeiraVez(post)) {
                return;
            }
            post.addEventListener('click', function (evento) {
                var curtir = evento.target.closest('[data-pp-curtir], [data-pp-curtir-comentario]');
                if (curtir && post.contains(curtir)) {
                    alternarCurtida(curtir);
                    return;
                }
                var responder = evento.target.closest('[data-pp-responder]');
                if (responder && post.contains(responder)) {
                    var form = post.querySelector('[data-pp-compor]');
                    var campo = form ? form.querySelector('[data-pp-compor-texto]') : null;
                    if (campo) {
                        var item = responder.closest('.pp-mc-comentario');
                        form.setAttribute('data-pp-respondendo', item ? Array.prototype.indexOf.call(item.parentNode.children, item) : '');
                        campo.placeholder = 'Responder a ' + (item ? item.querySelector('.pp-mc-quem strong').textContent : '') + '…';
                        campo.focus();
                    }
                }
            });
            var form = post.querySelector('[data-pp-compor]');
            if (!form) {
                return;
            }
            form.addEventListener('submit', function (evento) {
                evento.preventDefault();
                var campo = form.querySelector('[data-pp-compor-texto]');
                var texto = campo ? campo.value.trim() : '';
                if (texto === '') {
                    return;
                }
                var quem = form.getAttribute('data-pp-quem') || 'Você';
                var iniciais = form.getAttribute('data-pp-iniciais') || '?';
                var lista = post.querySelector('[data-pp-comentarios]');
                var indice = form.getAttribute('data-pp-respondendo');
                var alvo = (indice !== null && indice !== '' && lista) ? lista.children[parseInt(indice, 10)] : null;
                if (alvo) {
                    var respostas = alvo.querySelector('[data-pp-respostas]');
                    if (respostas) {
                        respostas.appendChild(novoComentario(quem, iniciais, texto, true));
                        respostas.hidden = false;
                    }
                } else if (lista) {
                    lista.appendChild(novoComentario(quem, iniciais, texto, false));
                }
                var total = post.querySelector('[data-pp-total-comentarios]');
                if (total) {
                    total.textContent = String((parseInt(total.textContent, 10) || 0) + 1);
                }
                campo.value = '';
                campo.placeholder = 'Escreva uma mensagem de parabéns…';
                form.removeAttribute('data-pp-respondendo');
            });
        });
    }

    /* BP.3c: lateral do Mural que acompanha a rolagem. Se a coluna cabe
     * na janela, gruda no alto (top 12 px, do CSS). Se nao cabe, o top
     * fica negativo na medida certa: a coluna rola junto ate o fim dela
     * aparecer e so entao gruda, sem cortar nada. */
    var MARGEM_LATERAL = 12;

    function calcularTopoLateral(alturaJanela, alturaColuna, margem) {
        var sobra = alturaJanela - alturaColuna - margem;
        return sobra >= margem ? margem : sobra;
    }

    function ajustarLateral(coluna) {
        var topo = calcularTopoLateral(window.innerHeight, coluna.offsetHeight, MARGEM_LATERAL);
        coluna.style.top = topo + 'px';
    }

    function iniciarLateral() {
        var colunas = [];
        document.querySelectorAll('.pp-casca [data-pp-lateral-fixa]').forEach(function (coluna) {
            if (!primeiraVez(coluna)) {
                return;
            }
            colunas.push(coluna);
            ajustarLateral(coluna);
        });
        if (colunas.length === 0) {
            return;
        }
        var pendente = false;
        var reajustar = function () {
            if (pendente) {
                return;
            }
            pendente = true;
            window.requestAnimationFrame(function () {
                pendente = false;
                colunas.forEach(ajustarLateral);
            });
        };
        window.addEventListener('resize', reajustar);
        // Imagens e comentarios novos mudam a altura da coluna.
        window.addEventListener('load', reajustar);
        colunas.forEach(function (coluna) {
            coluna.addEventListener('click', reajustar);
        });
    }

    function tudo() {
        iniciar();
        iniciarFiltros();
        iniciarCiencia();
        iniciarNovo();
        iniciarMural();
        iniciarLateral();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tudo);
    } else {
        tudo();
    }
})();
