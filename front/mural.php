<?php

/**
 * Pessoas+ - "Publicacoes do mural", gestao do RH (casca).
 *
 * PESSOASPLUS_BUILD_BP3A
 * PESSOASPLUS_BUILD_BP0C
 * PESSOASPLUS_BUILD_BP3B
 * PESSOASPLUS_BUILD_BP3D
 *
 * Lista das publicacoes por periodo e situacao (rascunho, agendada,
 * publicada, encerrada), com o lugar no mural, a ordem entre as fixadas,
 * a contagem agregada de visualizacoes (nunca quem viu) e a acao "Trazer
 * ao topo" (D-59). Ler o modulo mostra a lista; Gerenciar mostra "Nova
 * publicacao", "Editar" e "Trazer ao topo".
 *
 * O colaborador ve o mural em front/mural_colaborador.php e na aba Mural
 * da pagina inicial (Casca\MuralAba). Na mobilia: B3.1 e B3.2.
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

$dados = Fontes::publicacoes();
$dados['url_inicio'] = Pagina::url('/front/central.php');
$dados['url_mural']  = Pagina::url(MenuSimplificado::PAGINA_MURAL);
$dados['url_nova']   = Pagina::url('/plugins/pessoasplus/front/publicacao_nova.php');

$pp = Pagina::contexto(
    'mural',
    'Publicações do mural',
    'O que está no ar, o que vem e o que já saiu, na ordem em que o colaborador vê',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/mural.html.twig', ['pp' => $pp]);
Pagina::rodape();
