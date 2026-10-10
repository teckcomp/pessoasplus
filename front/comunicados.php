<?php

/**
 * Pessoas+ - lista de comunicados e normativas, area do RH (BM.2, real).
 *
 * PESSOASPLUS_BUILD_BM2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('comunicados');

$dados = [
    'comunicados' => Comunicado::lista(),
    'url_novo'    => Pagina::url('/plugins/pessoasplus/front/comunicado_novo.php'),
];

$pp = Pagina::contexto(
    'comunicados',
    'Comunicados e normativas',
    'O que foi publicado, quem precisa dar ciência e quem já confirmou',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/comunicados.html.twig', ['pp' => $pp]);
Pagina::rodape();
