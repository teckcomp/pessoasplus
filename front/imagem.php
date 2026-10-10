<?php

/**
 * Pessoas+ - entrega a imagem de capa de uma publicacao (BM.3).
 *
 * PESSOASPLUS_BUILD_BM3
 *
 * GET ?id=<publicacao>. Quem ve: alguem do publico, ou quem le o mural.
 * Devolve Response (T-73) com cache de um dia; a URL leva ?v= que muda com a imagem.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Publicacao;

Session::checkRight(Install::RIGHT_BASE, READ);

$id  = (int) ($_GET['id'] ?? 0);
$pub = new Publicacao();
if ($id <= 0 || !$pub->getFromDB($id) || (string) $pub->fields['imagem_caminho'] === '') {
    throw new \Glpi\Exception\Http\NotFoundHttpException();
}
if (!Permissoes::le('mural') && !Publicacao::alcanca($id, (int) Session::getLoginUserID())) {
    Session::checkRight('pessoasplus_mural', READ);
}
$arquivo = GLPI_PLUGIN_DOC_DIR . '/' . (string) $pub->fields['imagem_caminho'];
if (!is_file($arquivo)) {
    throw new \Glpi\Exception\Http\NotFoundHttpException();
}
// O core detecta o MIME pela extensao e entrega imagem "inline" (Toolbox.php:603-611), com cache de uma semana.
return Toolbox::getFileAsResponse($arquivo, basename($arquivo), null, true);
