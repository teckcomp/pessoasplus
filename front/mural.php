<?php

/**
 * Pessoas+ - "Publicacoes do mural", area do RH (BM.3, real).
 *
 * PESSOASPLUS_BUILD_BP3A
 * PESSOASPLUS_BUILD_BM3
 *
 * GET situacao=no_ar|agendada|rascunho|encerrada, busca=. POST topo (id):
 * "Trazer ao topo" atualiza so o carimbo de fixacao (D-59).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Publicacao;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('mural');

$base = Pagina::url('/plugins/pessoasplus/front/mural.php');

if (isset($_POST['topo'])) {
    $p = new Publicacao();
    $p->check((int) $_POST['id'], UPDATE);
    if ($p->trazerAoTopo()) {
        Session::addMessageAfterRedirect('Publicação trazida ao topo.', false, INFO);
    }
    Html::redirect($base);
}

$filtros = [
    'situacao' => isset(Publicacao::ESTADOS[$_GET['situacao'] ?? '']) ? (string) $_GET['situacao'] : '',
    'busca'    => mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 100),
];
$lista = Publicacao::lista($filtros);
$estados = [];
foreach (Publicacao::ESTADOS as $chave => $par) {
    $estados[] = ['chave' => $chave, 'rotulo' => $par[0], 'total' => $lista['resumo'][$chave]];
}

$dados = [
    'publicacoes' => $lista['publicacoes'],
    'resumo'      => $lista['resumo'],
    'estados'     => $estados,
    'filtros'     => $filtros,
    'url_base'    => $base,
    'url_nova'    => Pagina::url('/plugins/pessoasplus/front/publicacao_nova.php'),
    'url_mural'   => Pagina::url('/plugins/pessoasplus/front/mural_colaborador.php'),
    'gerencia'    => Permissoes::gerencia('mural'),
];

$pp = Pagina::contexto('mural', 'Publicações do mural', 'O que está no ar, o que vem e os rascunhos', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/mural.html.twig', ['pp' => $pp]);
Pagina::rodape();
