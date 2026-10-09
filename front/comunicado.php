<?php

/**
 * Pessoas+ - painel do emissor de um comunicado (casca, Tela 2).
 *
 * PESSOASPLUS_BUILD_BP2A
 * PESSOASPLUS_BUILD_BP2B
 * PESSOASPLUS_BUILD_BP0C
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('comunicados');

$lista_url = Pagina::url('/plugins/pessoasplus/front/comunicados.php');
$id        = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$dados     = $id === false ? null : Fontes::comunicado($id);
if ($dados === null) {
    Html::redirect($lista_url);
}
$dados['url_lista']       = $lista_url;
$dados['url_comprovante'] = Pagina::url('/plugins/pessoasplus/front/comprovante.php') . '?id=' . (int) $dados['id'];

$pp = Pagina::contexto(
    'comunicados',
    $dados['titulo'],
    $dados['tipo'] . ' ' . $dados['codigo'] . ' · publicada em ' . $dados['publicado'] . ' · prazo ' . $dados['prazo'],
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/comunicado.html.twig', ['pp' => $pp]);
Pagina::rodape();
