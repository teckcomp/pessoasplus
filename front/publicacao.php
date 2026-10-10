<?php

/**
 * Pessoas+ - publicacao completa do mural (BM.3), nas duas interfaces.
 *
 * PESSOASPLUS_BUILD_BM3
 *
 * Quem abre: alguem do publico (no ar) ou quem le Publicacoes do mural.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use Glpi\RichText\RichText;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Menu;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Publicacao;

Session::checkRight(Menu::$rightname, READ);

$uid = (int) Session::getLoginUserID();
$id  = (int) ($_GET['id'] ?? 0);
$pub = new Publicacao();
$urlMural = Pagina::url(MenuSimplificado::PAGINA_MURAL);
if ($id <= 0 || !$pub->getFromDB($id) || (int) $pub->fields['is_deleted'] === 1) {
    Session::addMessageAfterRedirect('Publicação não encontrada.', false, WARNING);
    Html::redirect($urlMural);
}
$hoje   = date('Y-m-d');
$noAr   = Publicacao::estado($pub->fields, $hoje) === 'no_ar';
$doRh   = Permissoes::le('mural');
if (!$doRh && !($noAr && Publicacao::alcanca($id, $uid))) {
    Session::checkRight('pessoasplus_mural', READ); // 403
}
if (!$doRh || $noAr) {
    Publicacao::marcarVistas([$id], $uid);
}
$t = Publicacao::paraTela($pub->fields, $hoje);
$dados = $t + [
    'conteudo_html' => RichText::getSafeHtml((string) ($pub->fields['conteudo'] ?? '')),
    'url_mural'     => $urlMural,
    'do_rh'         => $doRh,
];

$pp = Pagina::contexto('mural_colaborador', $t['titulo'], $t['tipo_rotulo'] . ' · ' . $t['periodo'], $dados);

Pagina::cabecalho('Mural', MenuSimplificado::CHAVE_MURAL);
TemplateRenderer::getInstance()->display('@pessoasplus/casca/publicacao.html.twig', ['pp' => $pp]);
Pagina::rodape();
