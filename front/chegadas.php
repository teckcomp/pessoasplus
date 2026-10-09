<?php

/**
 * Pessoas+ - "Chegadas e checklists", area do RH (casca).
 *
 * PESSOASPLUS_BUILD_BP4A
 * PESSOASPLUS_BUILD_BP4B
 *
 * Duas abas: Movimentacoes (chegadas e transferencias com o progresso do
 * checklist e os prazos) e Modelos de checklist (itens com responsavel,
 * prazo relativo, obrigatoriedade e evidencia, e as regras que decidem
 * quem recebe cada modelo). Ler o modulo mostra tudo; Gerenciar mostra
 * "Nova chegada", "Novo modelo", "Editar", "Duplicar" e "Lembrar".
 *
 * A aba vem por ?aba=, montada no servidor: o link funciona sem JS e
 * qualquer valor desconhecido cai em Movimentacoes.
 * Na mobilia: B4.1 a B4.4 e B4.9. "Nova chegada" e "Continuar" abrem o
 * assistente (front/chegada_nova.php, BP.4b).
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

$base  = Pagina::url('/plugins/pessoasplus/front/chegadas.php');
$aba   = ($_GET['aba'] ?? '') === 'modelos' ? 'modelos' : 'movimentacoes';
$dados = Fontes::chegadas();

$dados['aba']            = $aba;
$dados['url_aba']        = ['movimentacoes' => $base, 'modelos' => $base . '?aba=modelos'];
$dados['url_checklist']  = Pagina::url('/plugins/pessoasplus/front/movimentacao.php') . '?id=';
$dados['url_nova']       = Pagina::url('/plugins/pessoasplus/front/chegada_nova.php');

$pp = Pagina::contexto(
    'chegadas',
    'Chegadas e checklists',
    'Quem está chegando ou mudando de setor, o que cada setor precisa fazer e até quando',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/chegadas.html.twig', ['pp' => $pp]);
Pagina::rodape();
