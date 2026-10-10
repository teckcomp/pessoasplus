<?php

/**
 * Pessoas+ - Minha area do colaborador (BM.2, real).
 *
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BM2
 *
 * Pendencias de ciencia, normativas vigentes e comprovantes, nas duas
 * interfaces. Ferias, pesquisas e tarefas ficam para depois de 13/10 (D-74).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Menu;

Session::checkRight(Menu::$rightname, READ);

$uid   = (int) Session::getLoginUserID();
$nome  = trim((string) ($_SESSION['glpifirstname'] ?? ''));
$ficha = Colaborador::porUsuario($uid);

$dados = [
    'ciencias'    => Comunicado::pendenciasDe($uid),
    'normativas'  => Comunicado::normativasDe($uid),
    'comprovantes' => Comunicado::cienciasDe($uid),
    'ficha'       => $ficha === null ? null : $ficha->paraTela(),
    'url_ficha'   => $ficha === null || Session::getCurrentInterface() === 'helpdesk' ? '' : Pagina::url(Colaborador::getFormURLWithID((int) $ficha->getID(), false)),
];

$pp = Pagina::contexto(
    'minha_area',
    $nome === '' ? 'Minha área' : 'Olá, ' . $nome,
    'Tudo o que o RH espera de você, num lugar só',
    $dados
);

Pagina::cabecalho('Minha área');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/minha_area.html.twig', ['pp' => $pp]);
Pagina::rodape();
