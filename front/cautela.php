<?php

/**
 * Pessoas+ - itens em cautela, area do RH (BM.6-1).
 *
 * PESSOASPLUS_BUILD_BM6
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Cautela;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('cautela');

/** @var DBmysql $DB */
global $DB;

$situacao = isset(Cautela::SITUACOES[$_GET['situacao'] ?? '']) ? (string) $_GET['situacao'] : '';
$termos   = Cautela::lista($situacao !== '' ? ['situacao' => $situacao] : []);
$resumo   = array_fill_keys(array_keys(Cautela::SITUACOES), 0);
foreach (Cautela::lista([]) as $t) {
    $resumo[$t['situacao']] = ($resumo[$t['situacao']] ?? 0) + 1;
}

// Colaboradores para o seletor "Novo termo" (ativos, com ou sem usuario; sem usuario avisa na emissao).
$colaboradores = [];
foreach ($DB->request(['SELECT' => ['id', 'nome', 'users_id', 'situacao'], 'FROM' => Colaborador::getTable(), 'WHERE' => ['is_deleted' => 0, 'NOT' => ['situacao' => 'desligado']], 'ORDER' => 'nome']) as $c) {
    $colaboradores[] = ['id' => (int) $c['id'], 'nome' => (string) $c['nome'], 'com_usuario' => (int) $c['users_id'] > 0];
}

$chips = [];
foreach (Cautela::SITUACOES as $chave => $par) {
    $chips[] = ['chave' => $chave, 'rotulo' => $par[0], 'total' => $resumo[$chave] ?? 0];
}

$dados = [
    'termos'        => $termos,
    'situacao'      => $situacao,
    'chips'         => $chips,
    'total'         => array_sum($resumo),
    'gerencia'      => Permissoes::gerencia('cautela'),
    'colaboradores' => $colaboradores,
    'url_aqui'      => Pagina::url('/plugins/pessoasplus/front/cautela.php'),
    'url_termo'     => Pagina::url('/plugins/pessoasplus/front/cautela_termo.php'),
];

$pp = Pagina::contexto('cautela', 'Itens em cautela', 'Termos de entrega e devolução com ciência do colaborador', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/cautela.html.twig', ['pp' => $pp]);
Pagina::rodape();
