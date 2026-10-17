<?php

/**
 * Pessoas+ - envio de um documento de admissao (BM.8-1a, D-89, D-99).
 *
 * PESSOASPLUS_BUILD_BM8_1
 *
 * POST multipart: id (pendencia), arquivo. Quem envia: o proprio colaborador da ficha (Minha area,
 * nas duas interfaces) ou quem gerencia o Fichario (pela pasta). Volta para onde veio.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\AdmissaoDoc;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Session::checkRight(Install::RIGHT_BASE, READ);

$uid      = (int) Session::getLoginUserID();
$urlMinha = Pagina::url(MenuSimplificado::PAGINA) . '#pp-admissao';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Html::redirect($urlMinha);
}
$doc = new AdmissaoDoc();
$id  = (int) ($_POST['id'] ?? 0);
if ($id <= 0 || !$doc->getFromDB($id)) {
    Session::addMessageAfterRedirect('Pendência não encontrada.', false, WARNING);
    Html::redirect($urlMinha);
}
$ficha = new Colaborador();
if (!$ficha->getFromDB((int) $doc->fields['plugin_pessoasplus_colaboradores_id']) || (int) $ficha->fields['is_deleted'] === 1) {
    Session::addMessageAfterRedirect('Ficha não encontrada.', false, WARNING);
    Html::redirect($urlMinha);
}
$ehDono   = $uid > 0 && (int) $ficha->fields['users_id'] === $uid;
$gerencia = Permissoes::gerencia('fichario');
if (!$ehDono && !$gerencia) {
    Session::checkRight(Permissoes::MODULOS['fichario'][0], Permissoes::GERENCIAR); // 403
}
$urlVolta = ($gerencia && !$ehDono) || (string) ($_POST['origem'] ?? '') === 'pasta'
    ? Pagina::url(Colaborador::getFormURLWithID((int) $ficha->getID(), false)) . '&aba=documentos'
    : $urlMinha;

$erro = $doc->enviar(is_array($_FILES['arquivo'] ?? null) ? $_FILES['arquivo'] : [], $uid);
if ($erro !== '') {
    Session::addMessageAfterRedirect($erro, false, ERROR);
} else {
    Session::addMessageAfterRedirect('Documento enviado: ' . $doc->tipoNome() . '. O RH vai conferir.', false, INFO);
}
Html::redirect($urlVolta);
