<?php

/**
 * Pessoas+ - comprovante de ciencia (casca, Tela 12), individual ou coletivo.
 *
 * PESSOASPLUS_BUILD_BP2B
 * PESSOASPLUS_BUILD_BP0C
 *
 * Pagina propria, sem a moldura do GLPI, pronta para imprimir ou salvar em
 * PDF pelo navegador. O PDF gerado no servidor e anexado ao Fichario vem na
 * mobilia (B1.9, D-50), depois da verificacao do motor de PDF (V-03).
 * A saida deste script vira a resposta como esta
 * (LegacyFileLoadController.php:64), sem Html::header.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\DadosDemo;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\Identidade;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Menu;
use GlpiPlugin\Pessoasplus\Permissoes;

Session::checkRight(Menu::$rightname, READ);

$id     = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$pessoa = isset($_GET['p']) ? filter_var($_GET['p'], FILTER_VALIDATE_INT) : null;

// O comprovante da propria pessoa e de quem usa o Pessoas+; o coletivo e o
// de outra pessoa sao do emissor (direito de Comunicados, D-51).
if ($pessoa !== DadosDemo::PESSOA_LOGADA) {
    Permissoes::exigirLeitura('comunicados');
}
$dados  = ($id === false || $pessoa === false) ? null : Fontes::comprovante($id, $pessoa);
if ($dados === null) {
    Html::redirect(Pagina::url(MenuSimplificado::PAGINA));
}

TemplateRenderer::getInstance()->display('@pessoasplus/casca/comprovante.html.twig', ['pp' => [
    'css'        => Pagina::url('/plugins/pessoasplus/pessoasplus.css') . '?v=' . Navegacao::BUILD,
    'js'         => Pagina::url('/plugins/pessoasplus/pessoasplus.js') . '?v=' . Navegacao::BUILD,
    'demo'       => Fontes::emDemonstracao('comunicados'),
    'identidade' => Identidade::atual(),
    'dados'      => $dados,
]]);
