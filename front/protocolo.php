<?php

/**
 * Pessoas+ - consulta de protocolo sem login (BM.4, T-45, T-55).
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_10 (D-91: acao pelo endereco curto)
 * PESSOASPLUS_BUILD_BM4_12 (D-93: complemento pelo remetente)
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
$aviso = '';
if (isset($_POST['consultar'])) {
    $consultou = true;
    $resultado = Formulario::consultar((string) ($_POST['protocolo'] ?? ''), (string) ($_POST['codigo'] ?? ''));
} elseif (isset($_POST['complementar'])) {
    // BM.4-12 (D-93): o remetente acrescenta ao relato; mesma prova de posse da consulta.
    $consultou = true;
    $c = Formulario::complementar((string) ($_POST['protocolo'] ?? ''), (string) ($_POST['codigo'] ?? ''), (string) ($_POST['texto'] ?? ''));
    $aviso = isset($c['erro']) ? (string) $c['erro'] : 'Complemento enviado. O RH vê junto com o relato original.';
    $resultado = Formulario::consultar((string) ($_POST['protocolo'] ?? ''), (string) ($_POST['codigo'] ?? ''));
}

TemplateRenderer::getInstance()->display('@pessoasplus/casca/protocolo.html.twig', ['pp' => [
    'css'        => Pagina::url('/plugins/pessoasplus/pessoasplus.css') . '?v=' . Navegacao::BUILD,
    'identidade' => Identidade::atual(),
    'dados'      => ['resultado' => $resultado, 'consultou' => $consultou, 'aviso' => $aviso, 'aviso_erro' => isset($c['erro']), 'protocolo_digitado' => strtoupper(trim((string) ($_POST['protocolo'] ?? ''))), 'codigo_digitado' => strtoupper(trim((string) ($_POST['codigo'] ?? ''))), 'url_aqui' => \GlpiPlugin\Pessoasplus\OuvidoriaConfig::pelaCanal() ? \GlpiPlugin\Pessoasplus\OuvidoriaConfig::urlAnonima('protocolo') : Pagina::url('/plugins/pessoasplus/front/protocolo.php'), 'csrf' => Session::getNewCSRFToken()],
]]);
