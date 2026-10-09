<?php

/**
 * Pessoas+ - lista de comunicados e normativas do emissor (casca).
 *
 * PESSOASPLUS_BUILD_BP2A
 * PESSOASPLUS_BUILD_BP2B_2
 * PESSOASPLUS_BUILD_BP2C
 * PESSOASPLUS_BUILD_BP0C
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('comunicados');

$lista = Fontes::comunicados();
foreach ($lista as $i => $c) {
    $lista[$i]['url'] = $c['detalhe'] ? Pagina::url('/plugins/pessoasplus/front/comunicado.php') . '?id=' . (int) $c['id'] : '';
    $lista[$i]['url_comprovante'] = ($c['detalhe'] && $c['confirmados'] > 0)
        ? Pagina::url('/plugins/pessoasplus/front/comprovante.php') . '?id=' . (int) $c['id']
        : '';
}

$pp = Pagina::contexto(
    'comunicados',
    'Comunicados e normativas',
    'Quem já confirmou, quem falta e o que está em atraso, por comunicado',
    [
        'comunicados' => $lista,
        'url_novo'    => Pagina::url('/plugins/pessoasplus/front/comunicado_novo.php'),
    ]
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/comunicados.html.twig', ['pp' => $pp]);
Pagina::rodape();
