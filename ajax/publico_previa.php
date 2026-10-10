<?php

/**
 * Pessoas+ - previa de publico: quantos e quem (BM.1, F2).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * POST regras_json=[...] -> {"total":n,"nomes":[...],"mais":n,"regras":[...]}.
 * Somente leitura. Exige ler comunicados, mural ou Fichario.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Publico;

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

$hex = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

if (!Publico::podeLer()) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão.'], $hex);
    return;
}

$regras = json_decode((string) ($_POST['regras_json'] ?? '[]'), true);
if (!is_array($regras)) {
    $regras = [];
}
$previa = Publico::previa($regras);
$previa['regras'] = Publico::rotular($regras);

echo json_encode($previa, $hex);
