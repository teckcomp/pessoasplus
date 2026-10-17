<?php

/**
 * Pessoas+ - previa do formulario como quem responde vai ver (BM.4-9, D-92).
 *
 * PESSOASPLUS_BUILD_BM4_9
 *
 * POST titulo, descricao, modo, porta, perguntas_json=[...] -> {"html": "..."}.
 * Renderiza _formulario_previa.html.twig com as perguntas do editor (ainda nao
 * salvas), pelo mesmo _perguntas.html.twig da pagina de resposta. Nao grava
 * nada. Exige gerenciar pesquisas (quem edita o formulario). CSRF pelo core.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Permissoes;

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

$hex = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

if (!Permissoes::gerencia('pesquisas')) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão.'], $hex);
    return;
}

$lidas = json_decode((string) ($_POST['perguntas_json'] ?? '[]'), true);
$modo  = (string) ($_POST['modo'] ?? 'identificado');
if (!isset(Formulario::MODOS[$modo])) {
    $modo = 'identificado';
}
$porta = (string) ($_POST['porta'] ?? '');

$html = TemplateRenderer::getInstance()->render('@pessoasplus/casca/_formulario_previa.html.twig', [
    'titulo'      => mb_substr(trim((string) ($_POST['titulo'] ?? '')), 0, 255),
    'descricao'   => mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 2000),
    'modo'        => $modo,
    'modo_rotulo' => Formulario::MODOS[$modo],
    'porta'       => isset(Formulario::PORTAS[$porta]) ? $porta : '',
    'ouvidoria'   => isset(Formulario::PORTAS[$porta]),
    'perguntas'   => Formulario::previaPerguntas(is_array($lidas) ? $lidas : []),
]);

echo json_encode(['html' => $html], $hex);
