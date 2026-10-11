<?php

/**
 * Pessoas+ - formularios e Ouvidoria, area do RH (BM.4).
 *
 * PESSOASPLUS_BUILD_BM4
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('pesquisas');

$lista = Formulario::lista();
$dados = [
    'ouvidoria' => array_values(array_filter($lista, static fn ($f) => $f['tipo'] === 'ouvidoria')),
    'pesquisas' => array_values(array_filter($lista, static fn ($f) => $f['tipo'] !== 'ouvidoria')),
    'gerencia'  => Permissoes::gerencia('pesquisas'),
    'url_novo'  => Pagina::url('/plugins/pessoasplus/front/formulario_novo.php'),
    'url_config' => Pagina::url('/plugins/pessoasplus/front/ouvidoria_config.php'),
];

$pp = Pagina::contexto('pesquisas', 'Formulários e Ouvidoria', 'Filas da Ouvidoria por porta e campanhas de pesquisa', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/formularios.html.twig', ['pp' => $pp]);
Pagina::rodape();
