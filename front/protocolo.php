<?php

/**
 * Pessoas+ - consulta de protocolo sem login (BM.4, T-45, T-55).
 *
 * PESSOASPLUS_BUILD_BM4
 *
 * GET mostra o formulario; POST protocolo + codigo consulta. Nada na URL.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Identidade;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;

$resultado = null;
$consultou = false;
if (isset($_POST['consultar'])) {
    $consultou = true;
    $resultado = Formulario::consultar((string) ($_POST['protocolo'] ?? ''), (string) ($_POST['codigo'] ?? ''));
}

TemplateRenderer::getInstance()->display('@pessoasplus/casca/protocolo.html.twig', ['pp' => [
    'css'        => Pagina::url('/plugins/pessoasplus/pessoasplus.css') . '?v=' . Navegacao::BUILD,
    'identidade' => Identidade::atual(),
    'dados'      => ['resultado' => $resultado, 'consultou' => $consultou, 'url_aqui' => Pagina::url('/plugins/pessoasplus/front/protocolo.php'), 'csrf' => Session::getNewCSRFToken()],
]]);
