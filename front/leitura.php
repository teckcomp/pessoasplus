<?php

/**
 * Pessoas+ - leitura de comunicado com ciencia (casca), nas duas interfaces.
 *
 * PESSOASPLUS_BUILD_BP2B
 * PESSOASPLUS_BUILD_BP0C
 *
 * Na casca, confirmar nao grava nada: a tela mostra como fica o recibo.
 * Na mobilia (B2.2, B1.8) a confirmacao grava usuario, versao, data e hora
 * do servidor e interface, somente inclusao, e gera o comprovante (D-50).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\DadosDemo;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Menu;

Session::checkRight(Menu::$rightname, READ);

$id    = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$dados = $id === false ? null : Fontes::leitura($id);
if ($dados === null) {
    Html::redirect(Pagina::url(MenuSimplificado::PAGINA));
}
$dados['url_minha_area']  = Pagina::url(MenuSimplificado::PAGINA);
$dados['url_comprovante'] = Pagina::url('/plugins/pessoasplus/front/comprovante.php') . '?id=' . (int) $id . '&p=' . DadosDemo::PESSOA_LOGADA;

$pp = Pagina::contexto(
    'minha_area',
    $dados['titulo'],
    $dados['tipo'] . ' ' . $dados['codigo'] . ' · publicada em ' . $dados['publicado'],
    $dados
);

Pagina::cabecalho('Minha área');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/leitura.html.twig', ['pp' => $pp]);
Pagina::rodape();
