<?php

/**
 * Pessoas+ - entrega um anexo congelado (BM.2).
 *
 * PESSOASPLUS_BUILD_BM2
 *
 * GET ?id=<anexo>. Quem baixa: destinatario da versao ou quem le Comunicados.
 * Arquivo servido pelo core (Toolbox::getFileAsResponse), fora do DocumentRoot;
 * o script devolve o Response (LegacyFileLoadController.php:64-86, T-73).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Anexo;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Session::checkRight(Install::RIGHT_BASE, READ);

$anexo = new Anexo();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !$anexo->getFromDB($id)) {
    throw new \Glpi\Exception\Http\NotFoundHttpException();
}
$vid = (int) $anexo->fields['plugin_pessoasplus_versoes_id'];
if (!Comunicado::eDestinatario($vid, (int) Session::getLoginUserID()) && !Permissoes::le('comunicados')) {
    Session::checkRight('pessoasplus_comunicados', READ);
}
$arquivo = GLPI_PLUGIN_DOC_DIR . '/' . (string) $anexo->fields['caminho'];
if (!is_file($arquivo) || !hash_equals((string) $anexo->fields['hash'], (string) hash_file('sha256', $arquivo))) {
    throw new \Glpi\Exception\Http\NotFoundHttpException('Anexo ausente ou alterado: o selo não confere.');
}
// Script legado pode devolver um Response do Symfony: o controller o envia e descarta a saida (T-73).
return Toolbox::getFileAsResponse($arquivo, (string) $anexo->fields['nome'], (string) ($anexo->fields['mime'] ?: null));
