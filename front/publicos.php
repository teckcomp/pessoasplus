<?php

/**
 * Pessoas+ - publicos salvos (BM.1, F2).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * Lista os publicos-modelo com as regras e o total de pessoas alcancadas
 * hoje (resolvido na consulta, D-72). Quem gerencia comunicados, mural ou
 * Fichario cria, altera e exclui.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Publico;

Pagina::somenteInterfacePadrao();
Session::checkRight(Install::RIGHT_BASE, READ);
if (!Publico::podeLer()) {
    Session::checkRight('pessoasplus_comunicados', READ); // 403 do core
}

$dados = [
    'modelos'  => Publico::modelos(),
    'gerencia' => Publico::podeGerenciar(),
    'url_novo' => Pagina::url(Publico::getFormURL(false)),
];

$pp = Pagina::contexto(
    'publicos',
    'Públicos',
    'Quem recebe cada comunicado ou publicação: regras por grupo, perfil, entidade, vínculo, empregador ou pessoa',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/publicos.html.twig', ['pp' => $pp]);
Pagina::rodape();
