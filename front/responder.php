<?php

/**
 * Pessoas+ - responder um formulario logado (BM.4), nas duas interfaces.
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_17 (D-97: anonima com pagina publica sai para bilhete.php)
 *
 * GET ?id=<formulario> ou ?porta=<duvida|sugestao|reclamacao|conduta>.
 * POST enviar: p<id>=..., anonimo=1 quando o modo e "escolha".
 * Apos enviar: protocolo + codigo, mostrados UMA vez.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Install;

Session::checkRight(Install::RIGHT_BASE, READ);

$uid  = (int) Session::getLoginUserID();
$form = new Formulario();
$urlOuv = Pagina::url(MenuSimplificado::PAGINA_OUVIDORIA);
$urlMinha = Pagina::url(MenuSimplificado::PAGINA);

if (isset($_POST['enviar'])) {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$form->getFromDB($id) || !$form->alcanca($uid)) {
        Session::checkRight('pessoasplus_pesquisas', READ); // 403
    }
    if ($form->respondeFora()) { // BM.4-17: so pela pagina publica com bilhete
        Html::redirect(Pagina::url('/plugins/pessoasplus/front/bilhete.php') . '?id=' . $id);
    }
    $r = $form->responder($_POST, $uid, (string) ($_POST['anonimo'] ?? '0') === '1');
    if (isset($r['erro'])) {
        Session::addMessageAfterRedirect($r['erro'], false, ERROR);
        Html::redirect(Pagina::url('/plugins/pessoasplus/front/responder.php') . '?id=' . $id);
    }
    // Protocolo e codigo ficam na sessao so para a proxima tela (uma vez).
    $_SESSION['plugin_pessoasplus_recibo'] = $r + ['id' => $id];
    Html::redirect(Pagina::url('/plugins/pessoasplus/front/responder.php') . '?id=' . $id . '&enviado=1');
}

$porta = (string) ($_GET['porta'] ?? '');
if ($porta !== '') {
    $f = Formulario::porta($porta);
    if ($f === null) {
        Html::redirect($urlOuv);
    }
    $form = $f;
} else {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0 || !$form->getFromDB($id) || (int) $form->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Formulário não encontrado.', false, WARNING);
        Html::redirect($urlMinha);
    }
}
if (!$form->alcanca($uid)) {
    Session::checkRight('pessoasplus_pesquisas', READ); // 403: fora do publico
}
// BM.4-17 (D-97): pesquisa anonima com pagina publica nao e respondida aqui dentro do GLPI.
if ($form->respondeFora() && ($_GET['enviado'] ?? '') !== '1') {
    Html::redirect(Pagina::url('/plugins/pessoasplus/front/bilhete.php') . '?id=' . (int) $form->getID());
}
$hoje  = date('Y-m-d');
$tela  = Formulario::paraTela($form->fields, $hoje);
$recibo = null;
if (($_GET['enviado'] ?? '') === '1' && isset($_SESSION['plugin_pessoasplus_recibo']) && (int) $_SESSION['plugin_pessoasplus_recibo']['id'] === (int) $form->getID()) {
    $recibo = $_SESSION['plugin_pessoasplus_recibo'];
    unset($_SESSION['plugin_pessoasplus_recibo']);
}

$dados = $tela + [
    'perguntas'    => $form->perguntas(),
    'aberto'       => $tela['estado'] === 'aberto',
    'ja_respondeu' => !$form->ehOuvidoria() && $form->jaRespondeu($uid),
    'ouvidoria'    => $form->ehOuvidoria(),
    'porta_info'   => $form->ehOuvidoria() ? (Formulario::PORTAS[$form->fields['porta']] ?? null) : null,
    'recibo'       => $recibo,
    'url_aqui'     => Pagina::url('/plugins/pessoasplus/front/responder.php'),
    'url_voltar'   => $form->ehOuvidoria() ? $urlOuv : $urlMinha,
];

$pp = Pagina::contexto($form->ehOuvidoria() ? 'ouvidoria' : 'minha_area', $tela['titulo'], $form->ehOuvidoria() ? 'Ouvidoria' : $tela['modo_rotulo'] . ' · ' . $tela['periodo'], $dados);

Pagina::cabecalho($form->ehOuvidoria() ? 'Ouvidoria' : 'Minha área', $form->ehOuvidoria() ? MenuSimplificado::CHAVE_OUVIDORIA : MenuSimplificado::CHAVE);
TemplateRenderer::getInstance()->display('@pessoasplus/casca/responder.html.twig', ['pp' => $pp]);
Pagina::rodape();
