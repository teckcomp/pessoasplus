/* Pessoas+ - foto na capa da pasta (BM.1-3, D-84; recorte BM.1-4, D-85).
   PESSOASPLUS_BUILD_BM1_3 PESSOASPLUS_BUILD_BM1_4
   Arquivo proprio (nao toca no pessoasplus.js do servidor). Sem dependencias.

   O envio continua um POST comum (multipart) para front/foto.php. O JS cuida
   da janela (abrir, fechar, arrastar e soltar, remover x enviar, trava de
   envio duplo) e, a partir do BM.1-4, do RECORTE: ao escolher o arquivo a
   imagem aparece num canvas de 300x300 com mascara redonda; arrasta para
   posicionar e zoom para aproximar. Ao salvar, o quadrado visivel sai como
   JPG (ou PNG se a origem era PNG/GIF) de 600x600 e substitui o arquivo do
   <input type=file> via DataTransfer. Onde nao der (canvas ou DataTransfer
   ausentes, imagem que o navegador nao decodifica), vai a imagem inteira,
   como no BM.1-3. */
(function () {
    'use strict';

    var LADO = 300;      // canvas de trabalho (atributos width/height)
    var SAIDA = 600;     // quadrado enviado ao servidor
    var LIMITE = 8 * 1024 * 1024;

    function iniciar() {
        var abrir  = document.querySelector('[data-pp-foto-abrir]');
        var janela = document.querySelector('[data-pp-foto-janela]');
        if (!abrir || !janela || typeof janela.showModal !== 'function') {
            return; // sem permissao, pre-cadastro ou navegador sem <dialog>
        }

        var form      = janela.querySelector('[data-pp-foto-form]');
        var zona      = janela.querySelector('[data-pp-foto-zona]');
        var arquivo   = janela.querySelector('[data-pp-foto-arquivo]');
        var escolhido = janela.querySelector('[data-pp-foto-escolhido]');
        var nome      = janela.querySelector('[data-pp-foto-nome]');
        var tamanho   = janela.querySelector('[data-pp-foto-tamanho]');
        var remover   = janela.querySelector('[data-pp-foto-remover]');
        var salvar    = janela.querySelector('[data-pp-foto-salvar]');
        var fechar    = janela.querySelectorAll('[data-pp-foto-fechar]');
        var trocar    = janela.querySelectorAll('[data-pp-foto-trocar]');

        // Recorte
        var recorte   = janela.querySelector('[data-pp-foto-recorte]');
        var canvas    = janela.querySelector('[data-pp-foto-canvas]');
        var previas   = janela.querySelectorAll('[data-pp-foto-previa]');
        var zoomCtl   = janela.querySelector('[data-pp-foto-zoom]');
        var nomeArq   = janela.querySelector('[data-pp-foto-nome-arquivo]');
        var podeRecortar = !!(recorte && canvas && canvas.getContext && window.DataTransfer && window.File);

        var img = null;      // ImageBitmap ou HTMLImageElement carregado
        var iw = 0, ih = 0;  // tamanho natural
        var s0 = 1;          // escala que cobre o canvas (zoom 1)
        var zoom = 1;
        var ox = 0, oy = 0;  // canto da imagem no canvas (em px do canvas)
        var enviando = false;

        function temArquivo() {
            return !!(arquivo && arquivo.files && arquivo.files.length > 0);
        }

        function formatarTamanho(bytes) {
            if (bytes < 1024) { return bytes + ' B'; }
            if (bytes < 1024 * 1024) { return Math.round(bytes / 1024) + ' KB'; }
            return (bytes / (1024 * 1024)).toFixed(1).replace('.', ',') + ' MB';
        }

        // ---- recorte ------------------------------------------------------
        function escala() { return s0 * zoom; }

        function limitar() {
            var w = iw * escala(), h = ih * escala();
            ox = Math.min(0, Math.max(LADO - w, ox));
            oy = Math.min(0, Math.max(LADO - h, oy));
        }

        function desenhar() {
            if (!img) { return; }
            var k = escala();
            var ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, LADO, LADO);
            ctx.drawImage(img, ox, oy, iw * k, ih * k);
            Array.prototype.forEach.call(previas, function (p) {
                var f = p.width / LADO;
                var c = p.getContext('2d');
                c.clearRect(0, 0, p.width, p.height);
                c.drawImage(img, ox * f, oy * f, iw * k * f, ih * k * f);
            });
        }

        function definirZoom(z) {
            // mantem o ponto do centro do circulo parado
            var antes = escala();
            zoom = Math.min(3, Math.max(1, z));
            var depois = escala();
            var cx = LADO / 2, cy = LADO / 2;
            ox = cx - (cx - ox) * (depois / antes);
            oy = cy - (cy - oy) * (depois / antes);
            limitar();
            desenhar();
        }

        function soltarImagem() {
            if (img && typeof img.close === 'function') { try { img.close(); } catch (e) { /* nada */ } }
            img = null;
        }

        function carregarPorImg(file) {
            return new Promise(function (resolve, reject) {
                var url = URL.createObjectURL(file);
                var el = new Image();
                el.onload = function () { URL.revokeObjectURL(url); resolve(el); };
                el.onerror = function () { URL.revokeObjectURL(url); reject(new Error('nao decodificou')); };
                el.src = url;
            });
        }

        function carregar(file) {
            return new Promise(function (resolve, reject) {
                if (window.createImageBitmap) {
                    // imageOrientation 'from-image' aplica a rotacao EXIF de foto de celular
                    createImageBitmap(file, { imageOrientation: 'from-image' }).then(resolve, function () {
                        carregarPorImg(file).then(resolve, reject);
                    });
                } else {
                    carregarPorImg(file).then(resolve, reject);
                }
            });
        }

        function mostrarLinhaSimples() {
            nome.textContent    = arquivo.files[0].name;
            tamanho.textContent = '· ' + formatarTamanho(arquivo.files[0].size) + ' · será enviada inteira';
            escolhido.hidden    = false;
        }

        function mostrarRecorte(file) {
            soltarImagem();
            carregar(file).then(function (bitmap) {
                if (!temArquivo() || arquivo.files[0] !== file) { return; } // o usuario ja trocou
                img = bitmap;
                iw = bitmap.width || bitmap.naturalWidth;
                ih = bitmap.height || bitmap.naturalHeight;
                if (!iw || !ih) { throw new Error('sem dimensao'); }
                s0 = Math.max(LADO / iw, LADO / ih);
                zoom = 1;
                zoomCtl.value = '1';
                ox = (LADO - iw * s0) / 2;
                oy = (LADO - ih * s0) / 2;
                limitar();
                desenhar();
                nomeArq.textContent = file.name + ' · ' + formatarTamanho(file.size);
                recorte.hidden = false;
                escolhido.hidden = true;
            }).catch(function () {
                // navegador nao decodifica (HEIC, por exemplo): manda inteiro, o servidor decide
                soltarImagem();
                recorte.hidden = true;
                if (temArquivo()) { mostrarLinhaSimples(); }
            });
        }

        function exportar() {
            // Promise<File|null>: o quadrado visivel em SAIDA x SAIDA
            return new Promise(function (resolve) {
                if (!img) { resolve(null); return; }
                var saida = document.createElement('canvas');
                saida.width = SAIDA; saida.height = SAIDA;
                var f = SAIDA / LADO, k = escala();
                var c = saida.getContext('2d');
                var png = /image\/(png|gif)/.test(arquivo.files[0].type || '');
                if (!png) { c.fillStyle = '#ffffff'; c.fillRect(0, 0, SAIDA, SAIDA); }
                c.drawImage(img, ox * f, oy * f, iw * k * f, ih * k * f);
                var tipo = png ? 'image/png' : 'image/jpeg';
                saida.toBlob(function (blob) {
                    if (!blob) { resolve(null); return; }
                    try {
                        resolve(new File([blob], png ? 'foto-recortada.png' : 'foto-recortada.jpg', { type: tipo }));
                    } catch (e) {
                        resolve(null);
                    }
                }, tipo, 0.9);
            });
        }

        // arrastar no canvas (pointer events cobre mouse e toque)
        var arrastando = false, px = 0, py = 0;
        if (podeRecortar) {
            canvas.addEventListener('pointerdown', function (e) {
                if (!img) { return; }
                arrastando = true; px = e.clientX; py = e.clientY;
                canvas.classList.add('is-arrastando');
                try { canvas.setPointerCapture(e.pointerId); } catch (err) { /* nada */ }
                e.preventDefault();
            });
            canvas.addEventListener('pointermove', function (e) {
                if (!arrastando) { return; }
                var r = canvas.clientWidth / LADO || 1; // canvas pode estar reduzido por CSS no celular
                ox += (e.clientX - px) / r; oy += (e.clientY - py) / r;
                px = e.clientX; py = e.clientY;
                limitar(); desenhar();
            });
            ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(function (ev) {
                canvas.addEventListener(ev, function () { arrastando = false; canvas.classList.remove('is-arrastando'); });
            });
            canvas.addEventListener('wheel', function (e) {
                if (!img) { return; }
                e.preventDefault();
                definirZoom(zoom + (e.deltaY < 0 ? 0.1 : -0.1));
                zoomCtl.value = String(zoom);
            }, { passive: false });
            zoomCtl.addEventListener('input', function () { definirZoom(parseFloat(zoomCtl.value) || 1); });
        }

        // ---- janela -------------------------------------------------------
        function atualizar() {
            var vaiRemover = !!(remover && remover.checked);
            if (temArquivo() && !vaiRemover) {
                zona.hidden = true;
                if (podeRecortar) {
                    mostrarRecorte(arquivo.files[0]); // assincrono; mostra recorte ou linha simples
                } else {
                    mostrarLinhaSimples();
                }
            } else {
                soltarImagem();
                if (recorte) { recorte.hidden = true; }
                escolhido.hidden = true;
                zona.hidden = vaiRemover;
            }
            salvar.disabled    = !(temArquivo() || vaiRemover);
            salvar.textContent = vaiRemover ? 'Remover foto' : 'Salvar foto';
        }

        function limpar() {
            if (arquivo) { arquivo.value = ''; }
            if (remover) { remover.checked = false; }
            enviando = false;
            janela.classList.remove('is-enviando');
            atualizar();
        }

        abrir.addEventListener('click', function () {
            limpar();
            janela.showModal();
        });
        Array.prototype.forEach.call(fechar, function (b) {
            b.addEventListener('click', function () { janela.close(); });
        });
        janela.addEventListener('close', limpar);
        janela.addEventListener('click', function (e) {
            if (e.target === janela) { janela.close(); }
        });

        if (arquivo) { arquivo.addEventListener('change', atualizar); }
        if (remover) { remover.addEventListener('change', atualizar); }
        Array.prototype.forEach.call(trocar, function (b) {
            b.addEventListener('click', function () {
                arquivo.value = '';
                atualizar();
                arquivo.click();
            });
        });

        ['dragenter', 'dragover'].forEach(function (ev) {
            zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.add('is-arrastando'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.remove('is-arrastando'); });
        });
        zona.addEventListener('drop', function (e) {
            if (!e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) { return; }
            try { arquivo.files = e.dataTransfer.files; } catch (err) { return; }
            atualizar();
        });

        form.addEventListener('submit', function (e) {
            var vaiRemover = !!(remover && remover.checked);
            if (enviando) { e.preventDefault(); return; }
            if (!temArquivo() && !vaiRemover) { e.preventDefault(); return; }
            if (vaiRemover) {
                if (arquivo) { arquivo.value = ''; } // remover ganha: nao manda arquivo junto
                enviando = true;
                janela.classList.add('is-enviando');
                return; // envio normal
            }
            if (arquivo.files[0].size > LIMITE) {
                e.preventDefault();
                window.alert('A imagem passa de 8 MB. Reduza o tamanho antes de enviar.');
                return;
            }
            if (!podeRecortar || !img || recorte.hidden) {
                enviando = true;
                janela.classList.add('is-enviando');
                return; // sem recorte: envio normal, imagem inteira
            }
            // Com recorte: segura o envio, troca o arquivo pelo quadrado e reenvia
            e.preventDefault();
            enviando = true;
            janela.classList.add('is-enviando');
            exportar().then(function (file) {
                if (file) {
                    try {
                        var dt = new DataTransfer();
                        dt.items.add(file);
                        arquivo.files = dt.files;
                    } catch (err) {
                        // navegador nao deixa trocar: vai a imagem inteira
                    }
                }
                form.submit(); // submit() nao dispara este listener de novo
            });
        });

        atualizar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
