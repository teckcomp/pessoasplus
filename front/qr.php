<?php

/**
 * Pessoas+ - QR da pagina publica de um formulario, em SVG (BM.4).
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_3
 *
 * GET ?id=<formulario>[&cartaz=1]. Biblioteca bacon/bacon-qr-code do core (T-83).
 * cartaz=1 devolve uma pagina imprimivel com o QR e o endereco.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Identidade;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Permissoes;
use Symfony\Component\HttpFoundation\Response;
// PESSOASPLUS_BUILD_BM4_17 (D-98: QR e cartaz so quando o formulario divulga)

Session::checkRight(\GlpiPlugin\Pessoasplus\Install::RIGHT_BASE, READ);

$form  = new Formulario();
$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['t'] ?? ''));
$id    = (int) ($_GET['id'] ?? 0);
if ($token !== '' && strlen($token) === 32) {
    // BM.4-3: por token, qualquer usuario do Pessoas+ (o QR so codifica a URL publica, que e publica por definicao).
    if (!$form->getFromDBByCrit(['token' => $token, 'is_deleted' => 0, 'publico_sem_login' => 1]) || !$form->divulgaQr()) {
        throw new \Glpi\Exception\Http\NotFoundHttpException('Este formulário não é divulgado por QR.');
    }
} else {
    Permissoes::exigirLeitura('pesquisas');
    if ($id <= 0 || !$form->getFromDB($id) || $form->urlPublica() === '' || !$form->podeLerFila() || !$form->divulgaQr()) {
        throw new \Glpi\Exception\Http\NotFoundHttpException('Este formulário não é divulgado por QR.');
    }
}
$url = $form->urlPublica();
$svg = (new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd())))->writeString($url);

if (($_GET['cartaz'] ?? '') !== '1') {
    return new Response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store']);
}

TemplateRenderer::getInstance()->display('@pessoasplus/casca/cartaz.html.twig', ['pp' => [
    'css'        => Pagina::url('/plugins/pessoasplus/pessoasplus.css') . '?v=' . Navegacao::BUILD,
    'identidade' => Identidade::atual(),
    'dados'      => ['titulo' => (string) $form->fields['titulo'], 'descricao' => (string) ($form->fields['descricao'] ?? ''), 'url' => $url, 'svg' => $svg, 'ouvidoria' => $form->ehOuvidoria()],
]]);
