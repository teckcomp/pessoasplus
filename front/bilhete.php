<?php

/**
 * Pessoas+ - saida para a pagina publica com bilhete de uso unico (BM.4-17, D-97).
 *
 * PESSOASPLUS_BUILD_BM4_17
 *
 * GET  ?id=<formulario>  : emite o bilhete e redireciona para <endereco publico>#b=<bilhete>.
 *                          O fragmento nao sai do navegador: nao chega ao servidor nem ao access.log.
 * POST declarar=1, id=   : "Ja respondi pelo QR" (D-97): grava SO a participacao (origem declarado),
 *                          nenhuma resposta, e volta para a Minha area.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Bilhete;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Participacao;

Session::checkRight(Install::RIGHT_BASE, READ);

$uid      = (int) Session::getLoginUserID();
$urlMinha = Pagina::url(MenuSimplificado::PAGINA);
$form     = new Formulario();
$id       = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0 || !$form->getFromDB($id) || (int) $form->fields['is_deleted'] === 1 || $form->ehOuvidoria()) {
    Session::addMessageAfterRedirect('Pesquisa não encontrada.', false, WARNING);
    Html::redirect($urlMinha);
}
if (!$form->alcanca($uid)) {
    Session::checkRight('pessoasplus_pesquisas', READ); // 403: fora do publico
}
if (Formulario::estado($form->fields, date('Y-m-d')) !== 'aberto') {
    Session::addMessageAfterRedirect('Esta pesquisa não está aberta.', false, WARNING);
    Html::redirect($urlMinha);
}

if (isset($_POST['declarar'])) {
    // Autodeclaracao: so vale quando a pesquisa e divulgada por QR (senao nao ha como ter respondido por fora).
    if (!$form->divulgaQr()) {
        Session::addMessageAfterRedirect('Esta pesquisa não é divulgada por QR; responda pelo botão Responder.', false, WARNING);
    } elseif ($form->jaRespondeu($uid)) {
        Session::addMessageAfterRedirect('Sua participação já estava registrada.', false, INFO);
    } else {
        Participacao::registrar($id, $uid, Participacao::ORIGEM_DECLARADO);
        Session::addMessageAfterRedirect('Registrado: você declarou ter respondido pelo QR. Nenhuma resposta foi gravada por aqui.', false, INFO);
    }
    Html::redirect($urlMinha);
}

if (!$form->respondeFora()) {
    // Identificada ou "a escolha": a tela logada continua sendo o caminho.
    Html::redirect(Pagina::url('/plugins/pessoasplus/front/responder.php') . '?id=' . $id);
}
if ($form->jaRespondeu($uid)) {
    Session::addMessageAfterRedirect('Você já respondeu a esta pesquisa.', false, INFO);
    Html::redirect($urlMinha);
}

$bilhete = Bilhete::emitir($id, $uid);
// Conferido na 11.0.6 (T-101): Html::redirect lanca RedirectException -> Glpi\Http\RedirectResponse,
// que poe a URL inteira no Location sem mexer nela; o fragmento #b= chega ao navegador intacto.
Html::redirect($form->urlPublica() . Bilhete::fragmento($bilhete));
