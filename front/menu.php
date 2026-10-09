<?php

/**
 * Pessoas+ - pagina inicial do RH (casca).
 *
 * PESSOASPLUS_BUILD_BP0
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2C
 * PESSOASPLUS_BUILD_BP0C
 *
 * No GLPI 11 o kernel ja esta carregado quando este arquivo e incluido
 * pelo roteador legado, entao nao se inclui inc/includes.php (T-24).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Menu;
use GlpiPlugin\Pessoasplus\Permissoes;

Session::checkRight(Menu::$rightname, READ);

// O Painel do RH e de quem le pelo menos um modulo, na interface padrao
// (D-51). Quem so usa o Pessoas+ chega aqui pelo menu Ferramentas e vai
// para a Minha area, em vez de receber um 403.
if (!Permissoes::rh()) {
    Html::redirect(Pagina::url(MenuSimplificado::PAGINA));
}

$dados = Permissoes::filtrarPainel(Fontes::painelRh(), Permissoes::mapa());
foreach ($dados['atalhos'] as $i => $a) {
    $dados['atalhos'][$i]['url'] = $a['pagina'] === '' ? '' : Pagina::url('/plugins/pessoasplus/front/' . $a['pagina']);
}

$pp = Pagina::contexto(
    'inicio',
    'Painel do RH',
    'Processos em andamento, prazos e o que vem nos próximos dias',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/inicio.html.twig', ['pp' => $pp]);
Pagina::rodape();
