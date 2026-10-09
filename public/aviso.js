/* Pessoas+ - aviso ao entrar e lembretes durante a sessao (Tela 1, D-49).
 * PESSOASPLUS_BUILD_BP1 PESSOASPLUS_BUILD_BP2D PESSOASPLUS_BUILD_BP3C
 *
 * Carregado em todas as paginas, mas so para quem tem o direito do
 * Pessoas+ (o setup.php so registra o arquivo nesse caso). O servidor
 * decide se o aviso aparece agora e em quantos segundos pode voltar; o JS
 * agenda uma nova consulta para esse momento, entao o aviso volta mesmo
 * para quem fica o dia todo na mesma pagina. Enquanto houver pendencia,
 * um contador fica visivel em todas as paginas. Nunca bloqueia nada.
 * Sem variavel global (T-30). Todo texto entra por textContent.
 */
(function () {
    'use strict';

    // Paginas que ja mostram as pendencias: sem aviso e sem contador.
    // O Mural do colaborador entrou no BP.3c: ele tem prioridade na
    // entrada (D-54) e o aviso abre na primeira pagina fora dele.
    var PAGINAS_PROPRIAS = [
        '/plugins/pessoasplus/front/minha_area.php',
        '/plugins/pessoasplus/front/leitura.php',
        '/plugins/pessoasplus/front/mural_colaborador.php'
    ];
    var temporizador = null;

    function raiz() {
        var cfg = window.CFG_GLPI || {};
        return String(cfg.root_doc || '').replace(/\/+$/, '');
    }

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

    function aplicarCores(alvo, dados) {
        var cor = /^#[0-9a-f]{6}$/i;
        if (cor.test(dados.cor || '')) { alvo.style.setProperty('--pp-ae-acento', dados.cor); }
        if (cor.test(dados.cor_escura || '')) { alvo.style.setProperty('--pp-ae-escuro', dados.cor_escura); }
        if (cor.test(dados.cor_clara || '')) { alvo.style.setProperty('--pp-ae-claro', dados.cor_clara); }
    }

    function fechar(fundo, anterior) {
        if (fundo && fundo.parentNode) {
            fundo.parentNode.removeChild(fundo);
        }
        if (anterior && typeof anterior.focus === 'function') {
            anterior.focus();
        }
    }

    function atualizarContador(dados) {
        var atual = document.querySelector('.pp-aviso-contador');
        var qtd = parseInt(dados.pendentes, 10) || 0;
        if (qtd <= 0) {
            if (atual && atual.parentNode) {
                atual.parentNode.removeChild(atual);
            }
            return;
        }
        var atraso = (parseInt(dados.atrasados, 10) || 0) > 0;
        var contador = atual || el('a', 'pp-aviso-contador');
        contador.className = 'pp-aviso-contador' + (atraso ? ' is-atraso' : '');
        contador.href = String(dados.url_ler || '');
        contador.textContent = '';
        contador.appendChild(el('strong', null, qtd));
        contador.appendChild(el('span', null, qtd === 1 ? 'ciência pendente' : 'ciências pendentes'));
        if (atraso) {
            contador.appendChild(el('em', null, dados.atrasados + ' em atraso'));
        }
        contador.setAttribute('aria-label', qtd + ' ciências pendentes. Abrir a Minha área');
        if (!atual) {
            document.body.appendChild(contador);
        }
    }

    function mostrar(dados) {
        if (document.querySelector('.pp-aviso-entrada')) {
            return;
        }
        var anterior = document.activeElement;
        var fundo = el('div', 'pp-aviso-entrada');
        aplicarCores(fundo, dados);

        var caixa = el('div', 'pp-ae-caixa');
        caixa.setAttribute('role', 'dialog');
        caixa.setAttribute('aria-modal', 'true');
        caixa.setAttribute('aria-labelledby', 'pp-ae-titulo');

        var topo = el('div', 'pp-ae-topo');
        var icone = el('span', 'pp-ae-icone');
        icone.setAttribute('aria-hidden', 'true');
        icone.appendChild(el('i', 'ti ti-speakerphone'));
        var textos = el('div', 'pp-ae-textos');
        var titulo = el('h2', 'pp-ae-titulo', dados.titulo);
        titulo.id = 'pp-ae-titulo';
        textos.appendChild(titulo);
        textos.appendChild(el('p', 'pp-ae-subtitulo', dados.subtitulo));
        topo.appendChild(icone);
        topo.appendChild(textos);
        if (dados.demo) {
            topo.appendChild(el('span', 'pp-ae-demo', 'Demonstração'));
        }
        caixa.appendChild(topo);

        var lista = el('ul', 'pp-ae-lista');
        (Array.isArray(dados.itens) ? dados.itens : []).forEach(function (item) {
            var li = el('li');
            var txt = el('div', 'pp-ae-item');
            txt.appendChild(el('strong', null, item.titulo));
            txt.appendChild(el('span', null, item.detalhe));
            li.appendChild(txt);
            var tom = /^(atraso|alerta|info|ok|neutro)$/.test(item.tom || '') ? item.tom : 'neutro';
            li.appendChild(el('span', 'pp-ae-selo pp-ae-' + tom, item.selo));
            lista.appendChild(li);
        });
        caixa.appendChild(lista);

        if (dados.motivo) {
            caixa.appendChild(el('p', 'pp-ae-motivo', dados.motivo));
        }

        var acoes = el('div', 'pp-ae-acoes');
        if (dados.exibicao) {
            acoes.appendChild(el('span', 'pp-ae-exibicao', 'Exibido ' + dados.exibicao + (dados.exibicao === 1 ? ' vez' : ' vezes') + ' nesta sessão'));
        }
        var depois = el('button', 'pp-ae-botao', 'Lembrar depois');
        depois.type = 'button';
        var ler = el('a', 'pp-ae-botao is-primario', 'Ler agora');
        ler.href = String(dados.url_ler || '');
        acoes.appendChild(depois);
        acoes.appendChild(ler);
        caixa.appendChild(acoes);

        fundo.appendChild(caixa);
        document.body.appendChild(fundo);

        depois.addEventListener('click', function () { fechar(fundo, anterior); });
        fundo.addEventListener('click', function (evento) {
            if (evento.target === fundo) { fechar(fundo, anterior); }
        });
        caixa.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') { fechar(fundo, anterior); }
        });
        ler.focus();
    }

    function agendar(segundos) {
        if (temporizador) {
            clearTimeout(temporizador);
            temporizador = null;
        }
        var s = parseInt(segundos, 10);
        if (s > 0) {
            // Folga de 2 s para a consulta chegar depois do fim do intervalo.
            temporizador = setTimeout(consultar, (s + 2) * 1000);
        }
    }

    function consultar() {
        window.fetch(raiz() + '/plugins/pessoasplus/ajax/aviso.php', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (resposta) {
            return resposta.ok ? resposta.json() : null;
        }).then(function (dados) {
            if (!dados) {
                return;
            }
            atualizarContador(dados);
            if (dados.mostrar === true) {
                mostrar(dados);
            }
            agendar(dados.volta_em);
        }).catch(function () {
            // Aviso e acessorio: falha de rede nunca atrapalha a pagina.
        });
    }

    function iniciar() {
        // Nao roda dentro de iframes/modais do GLPI nem nas paginas em que
        // as pendencias ja estao na tela (Minha area, leitura e Mural).
        if (window.self !== window.top) {
            return;
        }
        var caminho = window.location.pathname;
        if (PAGINAS_PROPRIAS.some(function (p) { return caminho.indexOf(p) !== -1; })) {
            return;
        }
        if (typeof window.fetch !== 'function') {
            return;
        }
        consultar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
