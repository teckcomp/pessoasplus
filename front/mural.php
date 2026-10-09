<?php

/**
 * Pessoas+ - area "Mural e celebracoes" do RH (casca).
 *
 * PESSOASPLUS_BUILD_BP3A
 * PESSOASPLUS_BUILD_BP0C
 * PESSOASPLUS_BUILD_BP3B
 *
 * O colaborador ve o mural na aba "Mural" da pagina inicial do GLPI
 * (Casca\MuralAba) e na pagina "Para mim > Mural" (front/mural_colaborador.php).
 * Esta pagina e a visao do RH, "Publicacoes do mural" (D-55): o mesmo
 * conteudo com a contagem agregada de visualizacoes.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('mural');

$pp = Pagina::contexto(
    'mural',
    'Publicações do mural',
    'O que o colaborador vê no Mural e na aba Mural da página inicial, nas duas interfaces',
    Fontes::mural()
);
$pp['url_inicio'] = Pagina::url('/front/central.php');
$pp['url_mural']  = Pagina::url(MenuSimplificado::PAGINA_MURAL);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/mural.html.twig', ['pp' => $pp]);
Pagina::rodape();
