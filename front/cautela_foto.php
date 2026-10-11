<?php

/**
 * Pessoas+ - entrega a foto de uma resposta de conferencia (BM.6-2).
 *
 * PESSOASPLUS_BUILD_BM6_2
 *
 * GET ?id=<resposta>. Quem ve: o dono da resposta, quem le Cautela, o gestor da pasta (D-36).
 * O arquivo fica fora do webroot (GLPI_PLUGIN_DOC_DIR), nunca servido direto.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Conferencia;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Session::checkRight(Install::RIGHT_BASE, READ);

$r = Conferencia::respostaPorId((int) ($_GET['id'] ?? 0));
if ($r === null || (string) $r['foto_arquivo'] === '') {
    http_response_code(404);
    exit('Foto não encontrada.');
}
$uid  = (int) Session::getLoginUserID();
$pode = Permissoes::le('cautela') || (int) $r['users_id'] === $uid;
if (!$pode) {
    $col = new Colaborador();
    $pode = $col->getFromDB((int) $r['plugin_pessoasplus_colaboradores_id']) && $col->canViewItem();
}
if (!$pode) {
    Session::checkRight('pessoasplus_cautela', READ); // 403 do core
}
$arquivo = (string) $r['foto_arquivo'];
if (str_contains($arquivo, '..') || !str_starts_with($arquivo, Conferencia::PASTA . '/')) {
    http_response_code(404);
    exit('Foto não encontrada.');
}
$caminho = GLPI_PLUGIN_DOC_DIR . '/' . $arquivo;
if (!is_file($caminho)) {
    http_response_code(404);
    exit('Foto não encontrada.');
}
$mime = str_ends_with($arquivo, '.png') ? 'image/png' : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($caminho));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($caminho);
