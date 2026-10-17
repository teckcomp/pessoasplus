<?php

/**
 * Pessoas+ - entrega um documento de admissao (BM.8-1a, D-99).
 *
 * PESSOASPLUS_BUILD_BM8_1
 *
 * GET ?id=<documento>. So quem gerencia o Fichario ou o proprio colaborador da ficha.
 * O arquivo fica fora do webroot e NAO passa por Gestao > Documentos do GLPI.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\AdmissaoDoc;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;
use Symfony\Component\HttpFoundation\Response;

Session::checkRight(Install::RIGHT_BASE, READ);

$doc = new AdmissaoDoc();
$id  = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !$doc->getFromDB($id) || (string) $doc->fields['arquivo'] === '') {
    throw new \Glpi\Exception\Http\NotFoundHttpException('Documento não encontrado.');
}
if (!$doc->podeVer((int) Session::getLoginUserID())) {
    Session::checkRight(Permissoes::MODULOS['fichario'][0], Permissoes::GERENCIAR); // 403 do core
}
$relativo = (string) $doc->fields['arquivo'];
if (str_contains($relativo, '..') || !str_starts_with($relativo, AdmissaoDoc::PASTA . '/')) {
    throw new \Glpi\Exception\Http\NotFoundHttpException('Documento não encontrado.');
}
$caminho = GLPI_PLUGIN_DOC_DIR . '/' . $relativo;
if (!is_file($caminho)) {
    throw new \Glpi\Exception\Http\NotFoundHttpException('Arquivo não encontrado no servidor.');
}
$ext  = strtolower((string) pathinfo($caminho, PATHINFO_EXTENSION));
$mime = match ($ext) { 'pdf' => 'application/pdf', 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg' };
$nome = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($doc->fields['nome_original'] ?: ('documento.' . $ext)));
$inline = ($_GET['baixar'] ?? '') !== '1';

return new Response((string) file_get_contents($caminho), 200, [
    'Content-Type'            => $mime,
    'Content-Length'          => (string) filesize($caminho),
    'Content-Disposition'     => ($inline ? 'inline' : 'attachment') . '; filename="' . $nome . '"',
    'Cache-Control'           => 'private, no-store',
    'X-Content-Type-Options'  => 'nosniff',
]);
