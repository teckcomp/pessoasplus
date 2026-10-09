<?php

/**
 * Pessoas+ - Minha area do colaborador (casca), nas duas interfaces.
 *
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2B
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Menu;

Session::checkRight(Menu::$rightname, READ);

$nome = trim((string) ($_SESSION['glpifirstname'] ?? ''));

$dados = Fontes::minhaArea();
foreach ($dados['ciencias'] as $i => $c) {
    $leitura = Fontes::leitura((int) $c['id']);
    $dados['ciencias'][$i]['url'] = $leitura === null
        ? ''
        : Pagina::url('/plugins/pessoasplus/front/leitura.php') . '?id=' . (int) $c['id'];
}

$pp = Pagina::contexto(
    'minha_area',
    $nome === '' ? 'Minha área' : 'Olá, ' . $nome,
    'Tudo o que o RH espera de você, num lugar só',
    $dados
);

Pagina::cabecalho('Minha área');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/minha_area.html.twig', ['pp' => $pp]);
Pagina::rodape();
