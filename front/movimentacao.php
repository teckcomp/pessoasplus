<?php

/**
 * Pessoas+ - checklist de uma movimentacao (casca, Tela 5 da proposta).
 *
 * PESSOASPLUS_BUILD_BP4A
 *
 * Itens com responsavel, prazo relativo ao inicio e situacao; itens que se
 * concluem sozinhos (chamado de TI solucionado, I-04; ciencias confirmadas,
 * I-01); dados da movimentacao, modelos aplicados, chamados, normativas e,
 * na transferencia, o que muda na data em que ela vale (B4.9).
 * Rascunho e id desconhecido voltam para a lista.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('chegadas');

$lista_url = Pagina::url('/plugins/pessoasplus/front/chegadas.php');
$id        = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
$dados     = $id === false ? null : Fontes::movimentacao($id);
if ($dados === null) {
    Html::redirect($lista_url);
}
$dados['url_lista']   = $lista_url;
$dados['url_modelos'] = $lista_url . '?aba=modelos';

$pp = Pagina::contexto(
    'chegadas',
    $dados['tipo_rotulo'] . ' · ' . $dados['pessoa'],
    $dados['contexto'] . ' · ' . $dados['data_rotulo'],
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/movimentacao.html.twig', ['pp' => $pp]);
Pagina::rodape();
