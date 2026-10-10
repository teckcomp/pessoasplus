/* Pessoas+ - comportamento da casca. PESSOASPLUS_BUILD_BP0 PESSOASPLUS_BUILD_BP2A PESSOASPLUS_BUILD_BP2B PESSOASPLUS_BUILD_BP2C PESSOASPLUS_BUILD_BP3A PESSOASPLUS_BUILD_BP3B PESSOASPLUS_BUILD_BP3C PESSOASPLUS_BUILD_BP3D PESSOASPLUS_BUILD_BP3E PESSOASPLUS_BUILD_BP4B PESSOASPLUS_BUILD_BP4B_2 PESSOASPLUS_BUILD_BP4B_3 PESSOASPLUS_BUILD_BM1 PESSOASPLUS_BUILD_BM1_2 PESSOASPLUS_BUILD_BM2 PESSOASPLUS_BUILD_BM2_2 PESSOASPLUS_BUILD_BM3
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
        var periodo = bloco.querySelector('[data-pp-periodo]');
        var mes = periodo ? periodo.value : 'todos';
        var visiveis = 0;
        bloco.querySelectorAll('[data-pp-item]').forEach(function (item) {
            var tokens = (item.getAttribute('data-pp-situacao') || '').split(/\s+/);
            var ok = filtro === 'todos' || tokens.indexOf(filtro) !== -1;
            if (ok && busca !== '') {
                ok = normalizar(item.textContent).indexOf(busca) !== -1;
            }
            if (ok && mes !== 'todos') {
                ok = (item.getAttribute('data-pp-meses') || '').split(/\s+/).indexOf(mes) !== -1;
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
            var periodo = bloco.querySelector('[data-pp-periodo]');
            if (periodo) {
                periodo.addEventListener('change', function () { aplicarFiltro(bloco); });
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
    /* Soma o publico dos grupos marcados, conforme o vinculo escolhido.
     * Usado no novo comunicado (BP.2c) e na nova publicacao (BP.3d). */
    function totalPublico(raiz) {
        var vinculo = raiz.querySelector('[data-pp-vinculo]');
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
        return total;
    }

    function iniciarNovo() {
        document.querySelectorAll('.pp-casca [data-pp-novo]').forEach(function (raiz) {
            var q = function (sel) { return raiz.querySelector(sel); };
            var titulo = q('[data-pp-titulo]');
            var texto = q('[data-pp-texto]');
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
                var total = totalPublico(raiz);
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

    /* BP.3d: "Trazer ao topo" na lista de publicacoes. A linha passa a ser
     * a primeira fixada do seu lugar e as posicoes sao renumeradas na tela.
     * Nada e gravado na casca: o aviso de demonstracao sai pelo data-pp-demo
     * do proprio botao (iniciar). Na mobilia so o carimbo de fixacao muda. */
    function renumerarFixadas(corpo, lugar) {
        var linhas = corpo.querySelectorAll('[data-pp-ordem-grupo="' + lugar + '"]');
        Array.prototype.forEach.call(linhas, function (linha, i) {
            var ordem = linha.querySelector('[data-pp-ordem]');
            if (ordem) {
                ordem.textContent = (i + 1) + 'º';
            }
            var botao = linha.querySelector('[data-pp-topo]');
            if (botao) {
                botao.disabled = i === 0;
                botao.title = i === 0 ? 'Já é a primeira fixada deste lugar' : 'Trazer ao topo';
            }
        });
    }

    function iniciarTopo() {
        document.querySelectorAll('.pp-casca [data-pp-publicacoes]').forEach(function (bloco) {
            if (bloco.hasAttribute('data-pp-topo-pronto')) {
                return;
            }
            bloco.setAttribute('data-pp-topo-pronto', '');
            bloco.addEventListener('click', function (evento) {
                var botao = evento.target.closest('[data-pp-topo]');
                if (!botao || botao.disabled || !bloco.contains(botao)) {
                    return;
                }
                var linha = botao.closest('tr');
                var lugar = linha ? linha.getAttribute('data-pp-ordem-grupo') : null;
                if (!linha || !lugar) {
                    return;
                }
                var corpo = linha.parentNode;
                var primeira = corpo.querySelector('[data-pp-ordem-grupo="' + lugar + '"]');
                if (primeira && primeira !== linha) {
                    corpo.insertBefore(linha, primeira);
                }
                renumerarFixadas(corpo, lugar);
            });
        });
    }

    /* BP.3d: nova publicacao. A previa acompanha tipo, lugar, texto,
     * imagem, botoes, periodo e comentarios; campos que nao valem para o
     * lugar ou o tipo escolhido somem. Publicar fica travado enquanto houver
     * pendencia: titulo, periodo valido, publico maior que zero e, em
     * reconhecimento, a concordancia da pessoa (I-10). Todo texto entra por
     * textContent. Nada e enviado ao servidor na casca. */
    function dataCurta(iso) {
        return /^\d{4}-\d{2}-\d{2}$/.test(iso) ? iso.slice(8, 10) + '/' + iso.slice(5, 7) : '';
    }

    function lerJson(raiz, seletor) {
        var no = raiz.querySelector(seletor);
        if (!no) {
            return {};
        }
        try {
            var dados = JSON.parse(no.textContent || '{}');
            return dados && typeof dados === 'object' && !Array.isArray(dados) ? dados : {};
        } catch (e) {
            return {};
        }
    }

    function pendenciasPublicacao(estado) {
        var lista = [];
        if (estado.titulo === '') {
            lista.push('Escreva o título.');
        }
        if (estado.inicio === '' || estado.fim === '') {
            lista.push('Informe o período de exibição.');
        } else if (estado.fim < estado.inicio) {
            lista.push('O fim do período vem antes do início.');
        }
        if (estado.publico === 0) {
            lista.push('Escolha pelo menos um grupo do público.');
        }
        if (estado.tipo === 'reconhecimento' && !estado.concordancia) {
            lista.push('Confirme a concordância da pessoa reconhecida.');
        }
        return lista;
    }

    function textoFixadas(lugarRotulo, fixadas, fixar) {
        var nomes = Array.isArray(fixadas) ? fixadas : [];
        if (nomes.length === 0) {
            return fixar
                ? 'Nenhuma publicação fixada em ' + lugarRotulo + ' agora: esta fica em 1º.'
                : 'Nenhuma publicação fixada em ' + lugarRotulo + ' agora.';
        }
        var hoje = nomes.map(function (n, i) { return (i + 1) + 'º ' + n; }).join(', ');
        var texto = 'Fixadas agora em ' + lugarRotulo + ': ' + hoje + '.';
        if (fixar) {
            texto += ' Ao publicar, esta fica em 1º e “' + nomes[0] + '” passa a 2º.';
        }
        return texto;
    }

    function iniciarPublicacao() {
        document.querySelectorAll('.pp-casca [data-pp-pubform]').forEach(function (raiz) {
            if (!primeiraVez(raiz)) {
                return;
            }
            var casca = raiz.closest('.pp-casca') || document;
            var fixadasPorLugar = lerJson(casca, '[data-pp-fixadas-json]');
            var q = function (sel) { return raiz.querySelector(sel); };
            var hoje = raiz.getAttribute('data-pp-hoje') || '';
            var titulo = q('[data-pp-titulo]');
            var chamada = q('[data-pp-chamada]');
            var inicio = q('[data-pp-inicio]');
            var fim = q('[data-pp-fim]');
            var fixar = q('[data-pp-fixar]');
            var comentarios = q('[data-pp-comentarios]');
            var concordancia = q('[data-pp-concordancia]');
            var publicar = q('[data-pp-publicar]');
            var pv = q('[data-pp-pv]');

            function marcado(nome) {
                return raiz.querySelector('input[name="' + nome + '"]:checked');
            }

            function definir(sel, texto) {
                var no = q(sel);
                if (no) {
                    no.textContent = texto;
                }
            }

            function atualizar() {
                var tipoEl = marcado('pp_pub_tipo');
                var lugarEl = marcado('pp_pub_lugar');
                var imagemEl = marcado('pp_pub_imagem');
                var tipo = tipoEl ? tipoEl.value : '';
                var lugar = lugarEl ? lugarEl.value : 'cartao';
                var lugarRotulo = lugarEl ? lugarEl.getAttribute('data-pp-rotulo') : '';
                var fixada = !!(fixar && fixar.checked) && lugar !== 'destaque';
                var comImagem = lugar === 'grande' || lugar === 'cartao';
                var urlImagem = imagemEl && imagemEl.value !== '' ? (imagemEl.getAttribute('data-pp-url') || '') : '';

                raiz.querySelectorAll('[data-pp-so-lugar]').forEach(function (no) {
                    no.hidden = no.getAttribute('data-pp-so-lugar') !== lugar;
                });
                raiz.querySelectorAll('[data-pp-so-tipo]').forEach(function (no) {
                    no.hidden = no.getAttribute('data-pp-so-tipo') !== tipo;
                });
                var blocoImagem = q('[data-pp-com-imagem]');
                if (blocoImagem) {
                    blocoImagem.hidden = !comImagem;
                }
                var blocoFixar = q('[data-pp-fixar-bloco]');
                if (blocoFixar) {
                    blocoFixar.hidden = lugar === 'destaque';
                }
                definir('[data-pp-fixadas-info]', textoFixadas(lugarRotulo, fixadasPorLugar[lugar], fixada));

                var ligados = !!(comentarios && comentarios.checked);
                raiz.querySelectorAll('[data-pp-depende-comentarios]').forEach(function (no) {
                    no.disabled = !ligados;
                });

                var corpo = chamada ? chamada.value.trim() : '';
                definir('[data-pp-contador]', String(chamada ? chamada.value.length : 0));

                // Mapa e previa
                raiz.querySelectorAll('[data-pp-zona]').forEach(function (zona) {
                    zona.classList.toggle('is-alvo', zona.getAttribute('data-pp-zona') === lugar);
                });
                if (pv) {
                    pv.className = 'pp-pv is-' + lugar + ' pp-pv-tipo-' + tipo;
                    var img = q('[data-pp-pv-imagem]');
                    var capa = q('[data-pp-pv-capa]');
                    var mostraImagem = comImagem && urlImagem !== '';
                    if (img) {
                        img.hidden = !mostraImagem;
                        if (mostraImagem && img.getAttribute('src') !== urlImagem) {
                            img.setAttribute('src', urlImagem);
                        }
                    }
                    if (capa) {
                        capa.hidden = mostraImagem || !comImagem;
                    }
                    var icone = q('[data-pp-pv-icone]');
                    if (icone && tipoEl) {
                        icone.className = tipoEl.getAttribute('data-pp-icone') || '';
                    }
                    var rotulo = tipoEl ? tipoEl.getAttribute('data-pp-rotulo') : '';
                    if (lugar === 'destaque') {
                        rotulo = 'Destaque fixo · ' + rotulo;
                    } else if (fixada) {
                        rotulo += ' · Fixada no topo';
                    }
                    definir('[data-pp-pv-rotulo]', rotulo);
                    definir('[data-pp-pv-titulo]', (titulo && titulo.value.trim()) || 'Sem título');
                    var limite = lugar === 'grande' || lugar === 'destaque' ? 240 : 120;
                    definir('[data-pp-pv-chamada]', corpo.length > limite ? corpo.slice(0, limite) + '…' : corpo);

                    var botoes = q('[data-pp-pv-botoes]');
                    if (botoes) {
                        while (botoes.firstChild) {
                            botoes.removeChild(botoes.firstChild);
                        }
                        if (lugar === 'grande') {
                            raiz.querySelectorAll('[data-pp-botao-rotulo]').forEach(function (campo, i) {
                                var texto = campo.value.trim();
                                if (texto !== '') {
                                    botoes.appendChild(el('span', 'pp-pv-botao' + (i === 0 ? ' is-primario' : ''), texto));
                                }
                            });
                        }
                        botoes.hidden = botoes.childNodes.length === 0;
                    }
                    var periodo = inicio && fim && inicio.value && fim.value
                        ? (inicio.value === fim.value ? 'em ' + dataCurta(inicio.value) : 'de ' + dataCurta(inicio.value) + ' a ' + dataCurta(fim.value))
                        : 'sem período';
                    definir('[data-pp-pv-meta]', 'RH · ' + periodo + ' · ' + (ligados ? 'comentários ligados' : 'comentários desligados'));
                }

                // Publico e pendencias
                var total = totalPublico(raiz);
                definir('[data-pp-total]', String(total));
                definir('[data-pp-prev-total]', String(total));
                var zero = q('[data-pp-zero]');
                var resultado = q('[data-pp-resultado]');
                if (zero) {
                    zero.hidden = total !== 0;
                }
                if (resultado) {
                    resultado.hidden = total === 0;
                }

                var pendencias = pendenciasPublicacao({
                    titulo: titulo ? titulo.value.trim() : '',
                    inicio: inicio ? inicio.value : '',
                    fim: fim ? fim.value : '',
                    publico: total,
                    tipo: tipo,
                    concordancia: !!(concordancia && concordancia.checked)
                });
                var lista = q('[data-pp-pendencias]');
                if (lista) {
                    while (lista.firstChild) {
                        lista.removeChild(lista.firstChild);
                    }
                    pendencias.forEach(function (p) { lista.appendChild(el('li', null, p)); });
                    lista.hidden = pendencias.length === 0;
                }
                if (publicar) {
                    publicar.disabled = pendencias.length > 0;
                    if (!publicar.hasAttribute('data-pp-rotulo-fixo')) {
                        var agendar = inicio && inicio.value !== '' && hoje !== '' && inicio.value > hoje;
                        definir('[data-pp-publicar-rotulo]', agendar ? 'Agendar para ' + dataCurta(inicio.value) : 'Publicar');
                    }
                }
            }

            raiz.addEventListener('change', atualizar);
            raiz.addEventListener('input', atualizar);
            atualizar();
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

    /* Ouvidoria (BP.3e): consulta de protocolo so no navegador. Os
     * protocolos de demonstracao vem em [data-pp-protocolos-json]. Todo
     * texto entra por textContent (nunca innerHTML). Na mobilia (B7.9) a
     * consulta vai ao servidor por POST, nunca pela URL (access.log). */
    var PADRAO_PROTOCOLO = /^OUV-\d{4}-\d{4}$/;

    function normalizarProtocolo(texto) {
        return String(texto || '').trim().toUpperCase().replace(/\s+/g, '');
    }

    function mostrarProtocolo(caixa, numero, achado) {
        while (caixa.firstChild) {
            caixa.removeChild(caixa.firstChild);
        }
        caixa.classList.remove('is-erro');
        caixa.hidden = false;

        if (!PADRAO_PROTOCOLO.test(numero)) {
            caixa.classList.add('is-erro');
            caixa.appendChild(el('p', null, 'Confira o número: o protocolo tem o formato OUV-AAAA-NNNN.'));
            return;
        }
        if (!achado || typeof achado !== 'object') {
            caixa.classList.add('is-erro');
            caixa.appendChild(el('p', null, 'Protocolo ' + numero + ' não encontrado. Confira o número digitado.'));
            return;
        }

        var topo = el('div', 'pp-ouv-resultado-topo');
        topo.appendChild(el('strong', null, numero));
        topo.appendChild(el('span', 'pp-selo pp-tom-' + String(achado.tom || 'info'), String(achado.situacao || '')));
        caixa.appendChild(topo);
        caixa.appendChild(el('span', 'pp-ouv-resultado-meta',
            String(achado.porta || '') + ' · ' + String(achado.modo || '') + ' · última atualização do RH em ' + String(achado.atualizado || '')));
        caixa.appendChild(el('p', null, achado.resposta ? 'Resposta do RH: ' + achado.resposta : 'O RH ainda não respondeu. Volte a consultar mais tarde.'));
        if (achado.complemento) {
            var pedido = el('div', 'pp-ouv-complemento');
            pedido.appendChild(el('p', null, 'Pedido de complemento: ' + achado.complemento));
            var botao = el('button', 'pp-botao is-compacto', 'Responder ao pedido');
            botao.type = 'button';
            botao.setAttribute('data-pp-demo', 'Responder ao pedido de complemento sem se identificar: chega no bloco B7.9.');
            pedido.appendChild(botao);
            caixa.appendChild(pedido);
        }
    }

    function iniciarOuvidoria() {
        document.querySelectorAll('.pp-casca [data-pp-ouvidoria]').forEach(function (bloco) {
            if (!primeiraVez(bloco)) {
                return;
            }
            var form = bloco.querySelector('[data-pp-ouv-consulta]');
            var campo = bloco.querySelector('[data-pp-ouv-protocolo]');
            var caixa = bloco.querySelector('[data-pp-ouv-resultado]');
            if (!form || !campo || !caixa) {
                return;
            }
            var protocolos = lerJson(bloco, '[data-pp-protocolos-json]');
            form.addEventListener('submit', function (evento) {
                evento.preventDefault();
                var numero = normalizarProtocolo(campo.value);
                campo.value = numero;
                var achado = Object.prototype.hasOwnProperty.call(protocolos, numero) ? protocolos[numero] : null;
                mostrarProtocolo(caixa, numero, achado);
            });
        });
    }

    /* BP.4b: assistente de chegada (Tela 4). Todas as etapas vem no HTML e
     * aparece uma por vez; so avanca com os obrigatorios da etapa
     * preenchidos, e o indicador deixa voltar a qualquer etapa ja
     * alcancada. O resumo (lateral e etapa final) vem da matriz calculada
     * no servidor (DemoAssistente): modelos que casam (D-65), chamados de
     * TI (I-04) e normativas de admissao (I-01). Nada e enviado ao
     * servidor na casca; todo texto entra por textContent. */
    var ASSIST_FINAL = 7;

    function dataLonga(iso) {
        return /^\d{4}-\d{2}-\d{2}$/.test(iso) ? iso.slice(8, 10) + '/' + iso.slice(5, 7) + '/' + iso.slice(0, 4) : '';
    }

    function diasEntre(deIso, ateIso) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(deIso) || !/^\d{4}-\d{2}-\d{2}$/.test(ateIso)) {
            return null;
        }
        return Math.round((Date.parse(ateIso + 'T00:00:00Z') - Date.parse(deIso + 'T00:00:00Z')) / 86400000);
    }

    function iniciaisDe(nome) {
        var partes = String(nome || '').trim().split(/\s+/).filter(function (p) { return p !== ''; });
        if (partes.length === 0) {
            return '?';
        }
        var primeira = partes[0].charAt(0);
        var ultima = partes.length > 1 ? partes[partes.length - 1].charAt(0) : '';
        return (primeira + ultima).toUpperCase();
    }

    /* Estado do formulario lido da tela. */
    function lerAssistente(raiz) {
        var valor = function (campo) {
            var radios = raiz.querySelectorAll('[data-pp-campo="' + campo + '"][type="radio"]');
            if (radios.length > 0) {
                var marcado = '';
                radios.forEach(function (r) { if (r.checked) { marcado = r.value; } });
                return marcado;
            }
            var no = raiz.querySelector('[data-pp-campo="' + campo + '"]');
            return no ? String(no.value || '').trim() : '';
        };
        var estado = {};
        ['nome', 'email', 'telefone', 'conta', 'usuario', 'local', 'empregador', 'vinculo',
            'cargo', 'setor', 'gestor', 'inicio', 'origem', 'abertura', 'obs_vaga', 'obs_ti'].forEach(function (c) {
            estado[c] = valor(c);
        });
        estado.ti = [];
        raiz.querySelectorAll('[data-pp-ti]').forEach(function (caixa) {
            if (caixa.checked) {
                estado.ti.push(caixa.value);
            }
        });
        estado.docs = [];
        var tabela = estado.vinculo === '' ? null : raiz.querySelector('[data-pp-assist-docs="' + estado.vinculo + '"]');
        if (tabela) {
            tabela.querySelectorAll('[data-pp-doc]').forEach(function (sel) { estado.docs.push(sel.value); });
        }
        return estado;
    }

    /* Pendencias que impedem sair da etapa. */
    function pendenciasEtapa(etapa, estado) {
        var lista = [];
        if (etapa === 1) {
            if (estado.nome.length < 3) {
                lista.push('Informe o nome completo.');
            }
            if (estado.email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(estado.email)) {
                lista.push('O e-mail para contato não parece válido.');
            }
            if (estado.conta === 'existe' && estado.usuario === '') {
                lista.push('Escolha o usuário do GLPI, ou marque pré-cadastro.');
            }
        }
        if (etapa === 2) {
            if (estado.empregador === '') {
                lista.push('Escolha o empregador.');
            }
            if (estado.vinculo === '') {
                lista.push('Escolha o tipo de vínculo.');
            }
            if (estado.cargo === '') {
                lista.push('Escolha o cargo.');
            }
            if (estado.setor === '') {
                lista.push('Escolha o setor.');
            }
            if (estado.gestor === '') {
                lista.push('Informe o gestor imediato.');
            }
            if (estado.inicio === '') {
                lista.push('Informe a data de início.');
            }
        }
        return lista;
    }

    /* O que sera criado, a partir do estado e da configuracao. */
    function resumoChegada(estado, cfg) {
        var combo = (cfg.matriz && cfg.matriz[estado.vinculo] && cfg.matriz[estado.vinculo][estado.setor]) || null;
        var modelos = [];
        var itensModelos = 0;
        (combo ? combo.modelos : []).forEach(function (chave) {
            var m = (cfg.modelos || {})[chave];
            if (m) {
                modelos.push(m);
                itensModelos += m.itens;
            }
        });
        var chamados = [];
        (cfg.ti || []).forEach(function (t) {
            if (estado.ti.indexOf(t.chave) !== -1) {
                chamados.push(t);
            }
        });
        var normativas = (combo ? combo.normativas : []).map(function (codigo) {
            return { codigo: codigo, titulo: (cfg.normativas || {})[codigo] || codigo };
        });
        var pendentes = estado.docs.filter(function (s) { return s === 'pendente'; }).length;
        return {
            definido: combo !== null,
            modelos: modelos,
            chamados: chamados,
            normativas: normativas,
            itens: itensModelos + chamados.length,
            docsPendentes: pendentes,
            docsTotal: estado.docs.length
        };
    }

    function preencherLista(lista, linhas) {
        if (!lista) {
            return;
        }
        while (lista.firstChild) {
            lista.removeChild(lista.firstChild);
        }
        linhas.forEach(function (linha) {
            var li = el('li');
            li.appendChild(el('strong', '', linha[0]));
            if (linha[1]) {
                li.appendChild(el('small', 'pp-sub', linha[1]));
            }
            lista.appendChild(li);
        });
    }

    function iniciarAssistente() {
        document.querySelectorAll('.pp-casca [data-pp-assistente]').forEach(function (raiz) {
            if (!primeiraVez(raiz)) {
                return;
            }
            var casca = raiz.closest('.pp-casca') || document;
            var cfg = lerJson(casca, '[data-pp-assistente-json]');
            var q = function (sel) { return raiz.querySelector(sel); };
            var definir = function (sel, texto) {
                var no = q(sel);
                if (no) {
                    no.textContent = texto;
                }
            };
            var inicial = parseInt(raiz.getAttribute('data-pp-etapa-inicial'), 10);
            var atual = inicial >= 1 && inicial <= ASSIST_FINAL ? inicial : 1;
            var alcancada = atual;
            var gestorSugerido = '';
            var setor = q('[data-pp-campo="setor"]');
            var gestor = q('[data-pp-campo="gestor"]');
            if (setor && gestor && cfg.setores && cfg.setores[setor.value] === gestor.value) {
                gestorSugerido = gestor.value;
            }

            function atualizarCampos() {
                var estado = lerAssistente(raiz);
                definir('[data-pp-assist-nome]', estado.nome !== '' ? ' · ' + estado.nome : '');
                definir('[data-pp-assist-iniciais]', iniciaisDe(estado.nome));
                definir('[data-pp-assist-perfil]', (cfg.cargos || {})[estado.cargo] || 'a definir pelo cargo');
                raiz.querySelectorAll('[data-pp-assist-so-conta]').forEach(function (bloco) {
                    bloco.hidden = bloco.getAttribute('data-pp-assist-so-conta') !== estado.conta;
                });
                var vinculo = (cfg.vinculos || {})[estado.vinculo] || null;
                var nota = q('[data-pp-assist-vinculo-nota]');
                if (nota) {
                    nota.textContent = vinculo ? vinculo.nota : '';
                    nota.hidden = !vinculo || vinculo.nota === '';
                }
                definir('[data-pp-assist-inicio-rotulo]', vinculo ? vinculo.inicio : 'Data de admissão');
                raiz.querySelectorAll('[data-pp-assist-docs]').forEach(function (tabela) {
                    tabela.hidden = tabela.getAttribute('data-pp-assist-docs') !== estado.vinculo;
                });
                var docsVazio = q('[data-pp-assist-docs-vazio]');
                if (docsVazio) {
                    docsVazio.hidden = estado.vinculo !== '';
                }
                var dias = diasEntre(estado.abertura, estado.inicio);
                definir('[data-pp-assist-dias-vaga]', dias === null
                    ? 'Ex.: 25/08/2026. Com a data de início, mostra quanto tempo a vaga levou.'
                    : (dias < 0 ? 'A abertura da vaga está depois da data de início.' : 'Da abertura da vaga ao início: ' + dias + (dias === 1 ? ' dia.' : ' dias.')));
                atualizarResumo(estado);
            }

            function atualizarResumo(estado) {
                var r = resumoChegada(estado, cfg);
                [
                    ['itens', r.itens, 'item no checklist', 'itens no checklist'],
                    ['chamados', r.chamados.length, 'chamado de TI', 'chamados de TI'],
                    ['normativas', r.normativas.length, 'normativa para ciência', 'normativas para ciência'],
                    ['docs', r.docsPendentes, 'documento pendente', 'documentos pendentes']
                ].forEach(function (c) {
                    definir('[data-pp-assist-r-' + c[0] + ']', String(c[1]));
                    definir('[data-pp-assist-r-' + c[0] + '-rotulo]', c[1] === 1 ? c[2] : c[3]);
                });
                definir('[data-pp-assist-r-modelos]', !r.definido
                    ? 'Escolha vínculo e setor para ver os modelos que se aplicam.'
                    : (r.modelos.length === 0
                        ? 'Nenhum modelo casa com ' + estado.vinculo + ' · ' + estado.setor + '.'
                        : 'Modelos: ' + r.modelos.map(function (m) { return m.nome; }).join(' + ') + '.'));

                // Etapa final
                var ficha = q('[data-pp-assist-ficha]');
                if (ficha) {
                    while (ficha.firstChild) {
                        ficha.removeChild(ficha.firstChild);
                    }
                    var situacao = estado.conta === 'existe'
                        ? 'Ligada ao usuário ' + (estado.usuario || '—') + ', ativa a partir do início'
                        : 'Pré-cadastro, ligado ao usuário quando a TI criar a conta';
                    [
                        ['Pessoa', estado.nome || '—'],
                        ['Vínculo', (estado.vinculo || '—') + ' · ' + (estado.empregador || '—')],
                        ['Cargo e setor', (estado.cargo || '—') + ' · ' + (estado.setor || '—')],
                        ['Gestor imediato', estado.gestor || '—'],
                        [(((cfg.vinculos || {})[estado.vinculo] || {}).inicio) || 'Início', dataLonga(estado.inicio) || '—'],
                        ['Situação da ficha', situacao]
                    ].forEach(function (par) {
                        ficha.appendChild(el('dt', '', par[0]));
                        ficha.appendChild(el('dd', '', par[1]));
                    });
                }
                definir('[data-pp-assist-total-itens]', String(r.itens));
                var linhasModelos = r.modelos.map(function (m) {
                    return [m.nome, m.itens + (m.itens === 1 ? ' item' : ' itens') + ' · regra: ' + m.regra];
                });
                if (r.chamados.length > 0) {
                    linhasModelos.push(['Necessidades de TI', r.chamados.length + (r.chamados.length === 1 ? ' item' : ' itens') + ' que concluem quando o chamado é solucionado']);
                }
                preencherLista(q('[data-pp-assist-modelos]'), linhasModelos);
                var semModelo = q('[data-pp-assist-sem-modelo]');
                if (semModelo) {
                    semModelo.hidden = !r.definido || r.modelos.length > 0;
                    semModelo.textContent = 'Nenhum modelo de checklist casa com ' + estado.vinculo + ' · ' + estado.setor
                        + '. O checklist terá só os itens de TI. Crie ou ative um modelo em Modelos de checklist.';
                }
                definir('[data-pp-assist-total-chamados]', String(r.chamados.length));
                preencherLista(q('[data-pp-assist-chamados]'), r.chamados.length === 0
                    ? [['Nenhum chamado', 'nenhuma necessidade de TI marcada']]
                    : r.chamados.map(function (t) {
                        var titulo = t.chave === 'glpi' ? t.titulo + ' · perfil ' + ((cfg.cargos || {})[estado.cargo] || 'a definir') : t.titulo;
                        return [titulo, t.categoria];
                    }));
                definir('[data-pp-assist-total-normativas]', String(r.normativas.length));
                preencherLista(q('[data-pp-assist-normativas]'), r.normativas.map(function (n) { return [n.codigo + ' · ' + n.titulo, '']; }));
                definir('[data-pp-assist-normativas-nota]', estado.conta === 'existe'
                    ? 'Ficam pendentes de ciência assim que a chegada for concluída.'
                    : 'Ficam pendentes de ciência assim que a conta for criada e ligada à ficha.');
                definir('[data-pp-assist-docs-resumo]', r.docsTotal === 0
                    ? 'Escolha o vínculo para ver os documentos.'
                    : r.docsPendentes + ' de ' + r.docsTotal + ' documentos pendentes, acompanhados no painel da movimentação.');
                definir('[data-pp-assist-avisos]', 'Gestor (' + (estado.gestor || '—') + '), TI'
                    + (r.chamados.length > 0 ? ' (pelos chamados)' : '') + ' e RH. Cada responsável vê as suas tarefas na Minha área.');
            }

            function mostrar(etapa, focar) {
                atual = etapa;
                raiz.querySelectorAll('[data-pp-etapa]').forEach(function (painel) {
                    painel.hidden = parseInt(painel.getAttribute('data-pp-etapa'), 10) !== atual;
                });
                raiz.querySelectorAll('[data-pp-passo]').forEach(function (passo) {
                    var n = parseInt(passo.getAttribute('data-pp-passo'), 10);
                    passo.classList.toggle('is-atual', n === atual);
                    passo.classList.toggle('is-feito', n < alcancada && n !== atual);
                    var botao = passo.querySelector('[data-pp-ir]');
                    if (botao) {
                        botao.disabled = n > alcancada;
                        if (n === atual) {
                            botao.setAttribute('aria-current', 'step');
                        } else {
                            botao.removeAttribute('aria-current');
                        }
                    }
                });
                var voltar = q('[data-pp-voltar]');
                if (voltar) {
                    voltar.disabled = atual === 1;
                }
                var avancar = q('[data-pp-avancar]');
                var concluir = q('[data-pp-concluir]');
                if (avancar) {
                    avancar.hidden = atual === ASSIST_FINAL;
                }
                if (concluir) {
                    concluir.hidden = atual !== ASSIST_FINAL;
                }
                definir('[data-pp-assist-contador]', atual === ASSIST_FINAL ? 'Conferir e concluir' : 'Etapa ' + atual + ' de 6');
                var erro = q('[data-pp-assist-erro]');
                if (erro) {
                    erro.hidden = true;
                }
                atualizarCampos();
                if (focar) {
                    var titulo = q('[data-pp-etapa="' + atual + '"] .pp-assist-etapa-titulo');
                    var cartao = q('.pp-assist-cartao');
                    if (cartao && typeof cartao.scrollIntoView === 'function') {
                        cartao.scrollIntoView({ block: 'start', behavior: 'smooth' });
                    }
                    if (titulo) {
                        titulo.setAttribute('tabindex', '-1');
                        titulo.focus({ preventScroll: true });
                    }
                }
            }

            function avancar() {
                var pendencias = pendenciasEtapa(atual, lerAssistente(raiz));
                var erro = q('[data-pp-assist-erro]');
                if (pendencias.length > 0) {
                    if (erro) {
                        erro.textContent = pendencias.join(' ');
                        erro.hidden = false;
                    }
                    return false;
                }
                var proxima = Math.min(atual + 1, ASSIST_FINAL);
                alcancada = Math.max(alcancada, proxima);
                var salvo = q('[data-pp-assist-salvo]');
                if (salvo) {
                    salvo.hidden = false;
                    definir('[data-pp-assist-salvo-texto]', 'Rascunho salvo');
                }
                mostrar(proxima, true);
                return true;
            }

            function preencherExemplo() {
                var ex = cfg.exemplo || {};
                var form = ex.form || {};
                Object.keys(form).forEach(function (campo) {
                    var radios = raiz.querySelectorAll('[data-pp-campo="' + campo + '"][type="radio"]');
                    if (radios.length > 0) {
                        radios.forEach(function (r) { r.checked = r.value === form[campo]; });
                        return;
                    }
                    var no = raiz.querySelector('[data-pp-campo="' + campo + '"]');
                    if (no) {
                        no.value = form[campo];
                    }
                });
                gestorSugerido = form.gestor || '';
                var ti = Array.isArray(ex.ti) ? ex.ti : [];
                raiz.querySelectorAll('[data-pp-ti]').forEach(function (caixa) {
                    caixa.checked = ti.indexOf(caixa.value) !== -1;
                });
                var docs = Array.isArray(ex.documentos) ? ex.documentos : [];
                var tabela = raiz.querySelector('[data-pp-assist-docs="' + (form.vinculo || '') + '"]');
                if (tabela) {
                    tabela.querySelectorAll('[data-pp-doc]').forEach(function (sel, i) {
                        if (docs[i]) {
                            sel.value = docs[i];
                        }
                    });
                }
                alcancada = ASSIST_FINAL;
                var texto = q('[data-pp-assist-exemplo-texto]');
                if (texto) {
                    while (texto.firstChild) {
                        texto.removeChild(texto.firstChild);
                    }
                    texto.appendChild(el('strong', '', 'Exemplo carregado: ' + (form.nome || '')));
                    texto.appendChild(el('span', '', 'Todas as etapas estão preenchidas. Navegue pelo indicador e troque o que quiser: nada é gravado.'));
                }
                mostrar(atual, false);
            }

            raiz.addEventListener('click', function (evento) {
                var ir = evento.target.closest('[data-pp-ir]');
                if (ir && raiz.contains(ir) && !ir.disabled) {
                    var n = parseInt(ir.getAttribute('data-pp-ir'), 10);
                    if (n > alcancada) {
                        return;
                    }
                    // Pular para frente confere as etapas do caminho: pode ter
                    // apagado um obrigatorio depois de passar por ela.
                    var estado = lerAssistente(raiz);
                    for (var k = Math.min(atual, n); k < n; k++) {
                        var pend = pendenciasEtapa(k, estado);
                        if (pend.length > 0) {
                            mostrar(k, true);
                            var caixa = q('[data-pp-assist-erro]');
                            if (caixa) {
                                caixa.textContent = pend.join(' ');
                                caixa.hidden = false;
                            }
                            return;
                        }
                    }
                    mostrar(n, true);
                    return;
                }
                if (evento.target.closest('[data-pp-avancar]')) {
                    avancar();
                    return;
                }
                if (evento.target.closest('[data-pp-voltar]')) {
                    if (atual > 1) {
                        mostrar(atual - 1, true);
                    }
                    return;
                }
                if (evento.target.closest('[data-pp-assist-exemplo]')) {
                    preencherExemplo();
                }
            });

            raiz.addEventListener('change', function (evento) {
                if (evento.target === setor && gestor && cfg.setores) {
                    // Troca o gestor so se ele estiver vazio ou ainda for a sugestao anterior.
                    if (gestor.value.trim() === '' || gestor.value === gestorSugerido) {
                        gestorSugerido = cfg.setores[setor.value] || '';
                        gestor.value = gestorSugerido;
                    }
                }
                atualizarCampos();
            });
            raiz.addEventListener('input', atualizarCampos);

            /* Listas livres do plugin (D-69): o "+" abre uma linha para
             * digitar o novo item, que entra na lista e fica escolhido.
             * Na casca vale so nesta tela; na mobilia grava na lista (B0.3). */
            raiz.querySelectorAll('[data-pp-lista]').forEach(function (bloco) {
                var select = bloco.querySelector('select');
                var mais = bloco.querySelector('[data-pp-lista-mais]');
                if (!select || !mais) {
                    return;
                }
                var rotulo = bloco.getAttribute('data-pp-lista') || 'item';
                var linha = el('span', 'pp-lista-nova');
                linha.hidden = true;
                var campo = document.createElement('input');
                campo.type = 'text';
                campo.maxLength = 80;
                campo.setAttribute('aria-label', 'Novo ' + rotulo.toLowerCase());
                campo.placeholder = 'Novo ' + rotulo.toLowerCase();
                var adicionar = el('button', 'pp-botao is-compacto is-primario', 'Adicionar');
                adicionar.type = 'button';
                var cancelar = el('button', 'pp-botao is-compacto', 'Cancelar');
                cancelar.type = 'button';
                linha.appendChild(campo);
                linha.appendChild(adicionar);
                linha.appendChild(cancelar);
                bloco.appendChild(linha);
                mais.setAttribute('aria-expanded', 'false');

                function fechar() {
                    linha.hidden = true;
                    campo.value = '';
                    mais.setAttribute('aria-expanded', 'false');
                }
                function incluir() {
                    var texto = campo.value.trim();
                    if (texto === '') {
                        campo.focus();
                        return;
                    }
                    var existente = null;
                    Array.prototype.forEach.call(select.options, function (o) {
                        if (normalizar(o.value) === normalizar(texto)) {
                            existente = o;
                        }
                    });
                    if (!existente) {
                        existente = document.createElement('option');
                        existente.value = texto;
                        existente.textContent = texto;
                        select.appendChild(existente);
                    }
                    select.value = existente.value;
                    fechar();
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    var aviso = casca.querySelector('.pp-aviso');
                    if (aviso) {
                        aviso.textContent = 'Demonstração. “' + existente.value + '” entrou na lista só nesta tela. Na versão final grava na lista de ' + rotulo.toLowerCase() + ' do plugin.';
                        aviso.hidden = false;
                        setTimeout(function () { aviso.hidden = true; }, 4000);
                    }
                }
                mais.addEventListener('click', function () {
                    linha.hidden = !linha.hidden;
                    mais.setAttribute('aria-expanded', linha.hidden ? 'false' : 'true');
                    if (!linha.hidden) {
                        campo.focus();
                    }
                });
                adicionar.addEventListener('click', incluir);
                cancelar.addEventListener('click', fechar);
                campo.addEventListener('keydown', function (evento) {
                    if (evento.key === 'Enter') {
                        evento.preventDefault();
                        incluir();
                    } else if (evento.key === 'Escape') {
                        fechar();
                    }
                });
            });

            mostrar(atual, false);
        });
    }


    /* ------------------------------------------------------------------
     * BM.1 - Fichario e publicos (dados reais).
     * ------------------------------------------------------------------ */


    /* Selects com busca: o select2 vem no bundle base do GLPI em toda pagina
     * (lib/bundles/base.js, T-67). Sem jQuery/select2 o select nativo segue
     * funcionando. O select2 dispara 'change' pelo jQuery, que nao chega ao
     * addEventListener nativo: ouvir e definir valor sempre por estes dois. */
    function temSelect2() {
        return !!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2);
    }
    function tornarBuscavel(select) {
        if (!temSelect2() || !select) {
            return;
        }
        var $s = window.jQuery(select);
        if ($s.hasClass('select2-hidden-accessible')) {
            $s.select2('destroy');
        }
        $s.select2({ width: '100%', dropdownParent: window.jQuery(select.closest('.pp-casca') || document.body) });
    }
    function ouvirMudanca(select, fn) {
        if (temSelect2()) {
            window.jQuery(select).on('change', fn);
        } else {
            select.addEventListener('change', fn);
        }
    }
    function definirValor(select, valor) {
        select.value = valor;
        if (temSelect2() && window.jQuery(select).hasClass('select2-hidden-accessible')) {
            window.jQuery(select).trigger('change.select2');
        }
    }
    function iniciarBuscaveis() {
        document.querySelectorAll('.pp-casca select[data-pp-buscavel]').forEach(function (select) {
            if (!primeiraVez(select)) {
                return;
            }
            tornarBuscavel(select);
        });
    }

    /* Estrada da jornada (D-67): clique no marco mostra o trecho. */
    function iniciarJornada() {
        document.querySelectorAll('.pp-casca [data-pp-jornada]').forEach(function (raiz) {
            if (!primeiraVez(raiz)) {
                return;
            }
            function mostrar(chave) {
                raiz.querySelectorAll('[data-pp-trecho-corpo]').forEach(function (corpo) {
                    corpo.hidden = corpo.getAttribute('data-pp-trecho-corpo') !== chave;
                });
                raiz.querySelectorAll('[data-pp-trecho]').forEach(function (marco) {
                    marco.classList.toggle('is-aberto', marco.getAttribute('data-pp-trecho') === chave);
                });
            }
            raiz.querySelectorAll('[data-pp-trecho]').forEach(function (marco) {
                marco.addEventListener('click', function () { mostrar(marco.getAttribute('data-pp-trecho')); });
                marco.addEventListener('keydown', function (evento) {
                    if (evento.key === 'Enter' || evento.key === ' ') {
                        evento.preventDefault();
                        mostrar(marco.getAttribute('data-pp-trecho'));
                    }
                });
            });
            var atual = raiz.querySelector('[data-pp-trecho].is-atual');
            if (atual) {
                atual.classList.add('is-aberto');
            }
        });
    }


    /* BM.2: formulario do comunicado e leitura com ciencia. */
    function iniciarComunicado() {
        document.querySelectorAll('.pp-casca [data-pp-comunicado]').forEach(function (raiz) {
            /* O mesmo form ja foi marcado por iniciarPublico: marca propria. */
            if (raiz.hasAttribute('data-pp-pronto-com')) {
                return;
            }
            raiz.setAttribute('data-pp-pronto-com', '');
            var soNormativa = raiz.querySelector('[data-pp-so-normativa]');
            raiz.querySelectorAll('input[name="tipo"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (soNormativa) {
                        soNormativa.hidden = radio.value !== 'normativa' || !radio.checked;
                    }
                });
            });
            var motivoCampo = raiz.querySelector('[data-pp-motivo-campo]');
            raiz.querySelectorAll('[data-pp-motivo]').forEach(function (botao) {
                botao.addEventListener('click', function (evento) {
                    var motivo = window.prompt(botao.getAttribute('data-pp-motivo') || 'Motivo:', '');
                    if (motivo === null || motivo.trim() === '') {
                        evento.preventDefault();
                        return;
                    }
                    if (motivoCampo) {
                        motivoCampo.value = motivo.trim();
                    }
                });
            });
        });
        document.querySelectorAll('.pp-casca [data-pp-leitura]').forEach(function (raiz) {
            if (!primeiraVez(raiz)) {
                return;
            }
            var marcar = raiz.querySelector('[data-pp-ciencia-marcar]');
            var botao = raiz.querySelector('[data-pp-ciencia-confirmar]');
            var just = raiz.querySelector('[data-pp-justificativa]');
            var radios = raiz.querySelectorAll('[data-pp-concorda]');
            function discorda() {
                var d = false;
                radios.forEach(function (r) { if (r.checked && r.value === '0') { d = true; } });
                return d;
            }
            function atualizar() {
                if (just) {
                    just.hidden = !discorda();
                    var area = just.querySelector('textarea');
                    if (area) {
                        area.required = discorda();
                    }
                }
                if (botao && marcar) {
                    botao.disabled = !marcar.checked;
                    var texto = botao.querySelector('span');
                    if (texto && radios.length) {
                        texto.textContent = discorda() ? 'Registrar "Não concordo"' : (botao.getAttribute('data-pp-texto') || texto.textContent);
                    }
                }
            }
            if (botao) {
                var t = botao.querySelector('span');
                if (t) {
                    botao.setAttribute('data-pp-texto', t.textContent);
                }
            }
            if (marcar) {
                marcar.addEventListener('change', atualizar);
            }
            radios.forEach(function (r) { r.addEventListener('change', atualizar); });
            atualizar();
        });
    }


    /* BM.2-2: documentos relacionados (botoes com link) no comunicado. */
    function iniciarLinks() {
        document.querySelectorAll('.pp-casca [data-pp-links]').forEach(function (raiz) {
            if (!primeiraVez(raiz)) {
                return;
            }
            var campo = raiz.querySelector('[data-pp-links-campo]');
            var lista = raiz.querySelector('[data-pp-links-lista]');
            var links = [];
            try {
                var lidos = JSON.parse((raiz.querySelector('[data-pp-links-json]') || {}).textContent || '[]');
                links = Array.isArray(lidos) ? lidos : [];
            } catch (e) {
                links = [];
            }
            var editavel = !!raiz.querySelector('[data-pp-link-adicionar]');
            function render() {
                if (campo) {
                    campo.value = JSON.stringify(links);
                }
                if (!lista) {
                    return;
                }
                lista.innerHTML = '';
                if (links.length === 0) {
                    var vazio = el('li', 'pp-sub', 'Nenhum documento relacionado.');
                    vazio.setAttribute('data-pp-links-vazio', '');
                    lista.appendChild(vazio);
                }
                links.forEach(function (l, i) {
                    var li = el('li', 'pp-chip is-fixo');
                    var codex = /\/plugins\/codexplus\//.test(l.url || '');
                    li.appendChild(document.createTextNode((codex ? 'Codex+: ' : '') + (l.rotulo || l.url)));
                    li.title = l.url || '';
                    if (editavel) {
                        var x = el('button', 'pp-publ-remover', '×');
                        x.type = 'button';
                        x.setAttribute('aria-label', 'Remover documento');
                        x.addEventListener('click', function () {
                            links.splice(i, 1);
                            render();
                        });
                        li.appendChild(x);
                    }
                    lista.appendChild(li);
                });
            }
            var adicionar = raiz.querySelector('[data-pp-link-adicionar]');
            if (adicionar) {
                var rotulo = raiz.querySelector('[data-pp-link-rotulo]');
                var url = raiz.querySelector('[data-pp-link-url]');
                var incluir = function () {
                    var u = (url.value || '').trim();
                    if (!/^(https?:\/\/\S+|\/\S*)$/i.test(u)) {
                        avisar(raiz, 'Informe um endereço válido (http://, https:// ou começando com /).');
                        url.focus();
                        return;
                    }
                    if (links.length >= 10) {
                        avisar(raiz, 'No máximo 10 documentos relacionados.');
                        return;
                    }
                    links.push({ rotulo: (rotulo.value || '').trim(), url: u });
                    rotulo.value = '';
                    url.value = '';
                    render();
                    rotulo.focus();
                };
                adicionar.addEventListener('click', incluir);
                [rotulo, url].forEach(function (c) {
                    c.addEventListener('keydown', function (evento) {
                        if (evento.key === 'Enter') {
                            evento.preventDefault();
                            incluir();
                        }
                    });
                });
            }
            render();
        });
    }


    /* BM.3: formulario da publicacao real: o lugar decide imagem, botoes e fixacao. */
    function iniciarPublicacaoReal() {
        document.querySelectorAll('.pp-casca [data-pp-pubreal]').forEach(function (raiz) {
            if (raiz.hasAttribute('data-pp-pronto-pub')) {
                return;
            }
            raiz.setAttribute('data-pp-pronto-pub', '');
            var imagem = raiz.querySelector('[data-pp-so-imagem]');
            var botoes = raiz.querySelector('[data-pp-so-botoes]');
            var fixavel = raiz.querySelector('[data-pp-so-fixavel]');
            function aplicar() {
                var marcado = raiz.querySelector('input[name="lugar"]:checked');
                var lugar = marcado ? marcado.value : 'cartao';
                if (imagem) {
                    imagem.hidden = !(lugar === 'grande' || lugar === 'cartao');
                }
                if (botoes) {
                    botoes.hidden = lugar !== 'grande';
                }
                if (fixavel) {
                    fixavel.hidden = lugar === 'destaque';
                    var caixa = fixavel.querySelector('input[type="checkbox"]');
                    if (caixa && lugar === 'destaque') {
                        caixa.checked = false;
                    }
                }
            }
            raiz.querySelectorAll('input[name="lugar"]').forEach(function (r) {
                r.addEventListener('change', aplicar);
            });
            aplicar();
        });
    }

    /* Confirmacao antes de acoes destrutivas: botoes com data-pp-confirmar. */
    function iniciarConfirmacoes() {
        document.querySelectorAll('.pp-casca [data-pp-confirmar]').forEach(function (botao) {
            if (!primeiraVez(botao)) {
                return;
            }
            botao.addEventListener('click', function (evento) {
                if (!window.confirm(botao.getAttribute('data-pp-confirmar') || 'Confirma?')) {
                    evento.preventDefault();
                }
            });
        });
    }

    /* POST de mesma origem com o token CSRF no cabecalho: o core valida e
     * preserva o token em requisicoes AJAX (CheckCsrfListener, T-62). */
    function postar(url, csrf, dados) {
        var corpo = new URLSearchParams();
        Object.keys(dados).forEach(function (k) { corpo.append(k, dados[k]); });
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Glpi-Csrf-Token': csrf
            },
            body: corpo.toString()
        }).then(function (r) {
            return r.json().then(function (json) {
                if (!r.ok) {
                    throw new Error((json && json.erro) || ('Erro ' + r.status));
                }
                return json;
            });
        });
    }

    function avisar(raiz, texto) {
        var casca = raiz.closest('.pp-casca');
        var aviso = casca ? casca.querySelector('.pp-aviso') : null;
        if (!aviso) {
            return;
        }
        aviso.textContent = texto;
        aviso.hidden = false;
        setTimeout(function () { aviso.hidden = true; }, 5000);
    }

    /* Lista livre real (D-69): o "+" grava em ajax/lista.php e escolhe o item. */
    function listaLivreReal(raiz, bloco, urlAjax, csrf) {
        var select = bloco.querySelector('select');
        var mais = bloco.querySelector('[data-pp-lista-mais]');
        var chave = bloco.getAttribute('data-pp-lista-chave');
        if (!select || !mais || !chave) {
            return;
        }
        var rotulo = bloco.getAttribute('data-pp-lista') || 'item';
        var linha = el('span', 'pp-lista-nova');
        linha.hidden = true;
        var campo = document.createElement('input');
        campo.type = 'text';
        campo.maxLength = 80;
        campo.setAttribute('aria-label', 'Novo ' + rotulo.toLowerCase());
        campo.placeholder = 'Novo ' + rotulo.toLowerCase();
        var adicionar = el('button', 'pp-botao is-compacto is-primario', 'Adicionar');
        adicionar.type = 'button';
        var cancelar = el('button', 'pp-botao is-compacto', 'Cancelar');
        cancelar.type = 'button';
        linha.appendChild(campo);
        linha.appendChild(adicionar);
        linha.appendChild(cancelar);
        bloco.appendChild(linha);
        mais.setAttribute('aria-expanded', 'false');

        function fechar() {
            linha.hidden = true;
            campo.value = '';
            mais.setAttribute('aria-expanded', 'false');
        }
        function incluir() {
            var texto = campo.value.trim();
            if (texto === '') {
                campo.focus();
                return;
            }
            adicionar.disabled = true;
            postar(urlAjax, csrf, { lista: chave, nome: texto }).then(function (json) {
                var existente = null;
                Array.prototype.forEach.call(select.options, function (o) {
                    if (String(o.value) === String(json.id)) {
                        existente = o;
                    }
                });
                if (!existente) {
                    existente = document.createElement('option');
                    existente.value = String(json.id);
                    existente.textContent = json.nome;
                    select.appendChild(existente);
                }
                definirValor(select, String(json.id));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                fechar();
            }).catch(function (e) {
                avisar(raiz, 'Não foi possível incluir: ' + e.message);
            }).then(function () {
                adicionar.disabled = false;
            });
        }
        mais.addEventListener('click', function () {
            linha.hidden = !linha.hidden;
            mais.setAttribute('aria-expanded', linha.hidden ? 'false' : 'true');
            if (!linha.hidden) {
                campo.focus();
            }
        });
        adicionar.addEventListener('click', incluir);
        cancelar.addEventListener('click', fechar);
        campo.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                incluir();
            } else if (evento.key === 'Escape') {
                fechar();
            }
        });
    }

    /* Formulario da ficha: listas livres reais e gestor sugerido pelo setor. */
    function iniciarFicha() {
        document.querySelectorAll('.pp-casca [data-pp-ficha]').forEach(function (raiz) {
            if (!primeiraVez(raiz)) {
                return;
            }
            var csrf = raiz.getAttribute('data-pp-csrf') || '';
            var urlAjax = raiz.getAttribute('data-pp-ajax-lista') || '';
            if (raiz.getAttribute('data-pp-gerencia') === '1') {
                raiz.querySelectorAll('[data-pp-lista]').forEach(function (bloco) {
                    listaLivreReal(raiz, bloco, urlAjax, csrf);
                });
            }
            var gestores = lerJson(raiz, '[data-pp-gestores-json]');
            var setor = raiz.querySelector('[data-pp-setor]');
            var gestor = raiz.querySelector('[data-pp-gestor]');
            if (setor && gestor) {
                var sugerido = '';
                ouvirMudanca(setor, function () {
                    var novo = gestores[setor.value] ? String(gestores[setor.value]) : '';
                    if (novo === '') {
                        return;
                    }
                    if (gestor.value === '0' || gestor.value === '' || gestor.value === sugerido) {
                        definirValor(gestor, novo);
                        sugerido = novo;
                    }
                });
            }
        });
    }

    /* Editor de regras do publico + previa ao vivo. */
    function iniciarPublico() {
        document.querySelectorAll('.pp-casca [data-pp-publico]').forEach(function (raiz) {
            if (!primeiraVez(raiz)) {
                return;
            }
            var csrf = raiz.getAttribute('data-pp-csrf') || '';
            var urlPrevia = raiz.getAttribute('data-pp-url-previa') || '';
            var edita = raiz.getAttribute('data-pp-gerencia') === '1';
            var campo = raiz.querySelector('[data-pp-regras-campo]');
            var lista = raiz.querySelector('[data-pp-regras-lista]');
            var opcoes = lerJson(raiz, '[data-pp-opcoes-json]');
            var regras = [];
            try {
                var lidas = JSON.parse((raiz.querySelector('[data-pp-regras-json]') || {}).textContent || '[]');
                regras = Array.isArray(lidas) ? lidas : [];
            } catch (e) {
                regras = [];
            }
            var tipos = {
                todos: 'Todos os usuários ativos', grupo: 'Grupo (setor)', perfil: 'Perfil',
                entidade: 'Entidade', usuario: 'Usuário', vinculo: 'Tipo de vínculo', empregador: 'Empregador'
            };

            function rotuloDe(r) {
                if (r.rotulo) {
                    return r.rotulo;
                }
                if (r.tipo === 'todos') {
                    return 'Todos os usuários ativos';
                }
                var lista = Array.isArray(opcoes[r.tipo]) ? opcoes[r.tipo] : [];
                var achado = '';
                var alvo = r.tipo === 'vinculo' ? r.valor_texto : String(r.valor_id);
                lista.forEach(function (o) {
                    if (String(o.id) === String(alvo)) {
                        achado = o.nome;
                    }
                });
                return (achado || alvo) + (r.incluir_filhos ? ' (e subníveis)' : '');
            }

            function render() {
                if (campo) {
                    campo.value = JSON.stringify(regras);
                }
                if (!lista) {
                    return;
                }
                lista.innerHTML = '';
                if (regras.length === 0) {
                    var vazio = el('li', 'pp-sub', 'Nenhuma regra ainda.');
                    vazio.setAttribute('data-pp-regras-vazio', '');
                    lista.appendChild(vazio);
                }
                regras.forEach(function (r, i) {
                    var li = el('li', 'pp-chip is-fixo' + (r.is_exclusao ? ' pp-publ-exclusao' : ''));
                    li.appendChild(document.createTextNode((r.is_exclusao ? 'exceto ' : '') + (tipos[r.tipo] || r.tipo).toLowerCase() + ': ' + rotuloDe(r)));
                    if (edita) {
                        var x = el('button', 'pp-publ-remover', '×');
                        x.type = 'button';
                        x.setAttribute('aria-label', 'Remover regra');
                        x.addEventListener('click', function () {
                            regras.splice(i, 1);
                            render();
                            previa();
                        });
                        li.appendChild(x);
                    }
                    lista.appendChild(li);
                });
            }

            var tempo = null;
            function previa() {
                if (!urlPrevia) {
                    return;
                }
                if (tempo) {
                    clearTimeout(tempo);
                }
                tempo = setTimeout(function () {
                    postar(urlPrevia, csrf, { regras_json: JSON.stringify(regras) }).then(function (json) {
                        var total = raiz.querySelector('[data-pp-previa-total]');
                        var rot = raiz.querySelector('[data-pp-previa-rotulo]');
                        var nomes = raiz.querySelector('[data-pp-previa-nomes]');
                        if (total) {
                            total.textContent = String(json.total);
                        }
                        if (rot) {
                            rot.textContent = json.total === 1 ? 'pessoa' : 'pessoas';
                        }
                        if (nomes) {
                            nomes.innerHTML = '';
                            (Array.isArray(json.nomes) ? json.nomes : []).forEach(function (n) {
                                nomes.appendChild(el('li', '', n));
                            });
                            if (json.mais > 0) {
                                nomes.appendChild(el('li', 'pp-sub', 'e mais ' + json.mais));
                            }
                        }
                    }).catch(function (e) {
                        avisar(raiz, 'Prévia indisponível: ' + e.message);
                    });
                }, 250);
            }

            var nova = raiz.querySelector('[data-pp-regra-nova]');
            if (edita && nova) {
                var tipo = nova.querySelector('[data-pp-regra-tipo]');
                var valor = nova.querySelector('[data-pp-regra-valor]');
                var filhos = nova.querySelector('[data-pp-regra-filhos]');
                var filhosRotulo = nova.querySelector('[data-pp-regra-filhos-rotulo]');
                var exclusao = nova.querySelector('[data-pp-regra-exclusao]');
                var adicionar = nova.querySelector('[data-pp-regra-adicionar]');

                var preencherValor = function () {
                    valor.innerHTML = '';
                    var t = tipo.value;
                    var semValor = t === 'todos';
                    valor.disabled = semValor;
                    if (semValor) {
                        valor.appendChild(new Option('—', ''));
                    } else {
                        (Array.isArray(opcoes[t]) ? opcoes[t] : []).forEach(function (o) {
                            valor.appendChild(new Option(o.nome, String(o.id)));
                        });
                    }
                    tornarBuscavel(valor);
                    if (filhosRotulo) {
                        filhosRotulo.hidden = !(t === 'grupo' || t === 'entidade');
                    }
                    if (exclusao) {
                        exclusao.disabled = semValor;
                        if (semValor) {
                            exclusao.checked = false;
                        }
                    }
                };
                ouvirMudanca(tipo, preencherValor);
                preencherValor();

                adicionar.addEventListener('click', function () {
                    var t = tipo.value;
                    var r = { tipo: t, valor_id: 0, valor_texto: '', incluir_filhos: filhos && filhos.checked && (t === 'grupo' || t === 'entidade') ? 1 : 0, is_exclusao: exclusao && exclusao.checked ? 1 : 0 };
                    if (t === 'vinculo') {
                        r.valor_texto = valor.value;
                    } else if (t !== 'todos') {
                        r.valor_id = parseInt(valor.value, 10) || 0;
                        if (r.valor_id <= 0) {
                            avisar(raiz, 'Escolha um valor para a regra.');
                            return;
                        }
                    }
                    var repetida = regras.some(function (x) {
                        return x.tipo === r.tipo && String(x.valor_id) === String(r.valor_id) && x.valor_texto === r.valor_texto && !!x.is_exclusao === !!r.is_exclusao;
                    });
                    if (repetida) {
                        avisar(raiz, 'Essa regra já está na lista.');
                        return;
                    }
                    regras.push(r);
                    render();
                    previa();
                });
            }

            /* BM.2: comunicado parte de um publico salvo (copia as regras). */
            var modeloSelect = raiz.querySelector('[data-pp-modelo-select]');
            var modeloCampo = raiz.querySelector('[data-pp-modelo-campo]');
            if (modeloSelect) {
                ouvirMudanca(modeloSelect, function () {
                    var opcao = modeloSelect.options[modeloSelect.selectedIndex];
                    if (modeloCampo) {
                        modeloCampo.value = modeloSelect.value;
                    }
                    if (!opcao || modeloSelect.value === '0') {
                        return;
                    }
                    try {
                        var lidas = JSON.parse(opcao.getAttribute('data-pp-regras') || '[]');
                        regras = Array.isArray(lidas) ? lidas : [];
                    } catch (e) {
                        regras = [];
                    }
                    render();
                    previa();
                });
            }

            render();
        });
    }

    function tudo() {
        iniciar();
        iniciarFiltros();
        iniciarCiencia();
        iniciarNovo();
        iniciarTopo();
        iniciarPublicacao();
        iniciarMural();
        iniciarLateral();
        iniciarOuvidoria();
        iniciarAssistente();
        iniciarConfirmacoes();
        iniciarFicha();
        iniciarPublico();
        iniciarBuscaveis();
        iniciarJornada();
        iniciarComunicado();
        iniciarLinks();
        iniciarPublicacaoReal();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tudo);
    } else {
        tudo();
    }
})();
