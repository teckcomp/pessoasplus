<?php

/**
 * Pessoas+ - "Publicacoes do mural", area do RH (BM.3, real).
 *
 * PESSOASPLUS_BUILD_BP3A
 * PESSOASPLUS_BUILD_BM3
 * PESSOASPLUS_BUILD_BM4_7
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

// BM.4-7: filtros no navegador (como na casca); o servidor manda tudo.
$hoje  = date('Y-m-d');
$lista = Publicacao::lista(['situacao' => '', 'busca' => ''], $hoje);
$publicoTotal = count(\GlpiPlugin\Pessoasplus\Publico::resolverRegras([['tipo' => 'todos']]));
$ordem = [];
$meses = [];
$fixadas = 0;
foreach ($lista['publicacoes'] as $i => $p) {
    $pub = new Publicacao();
    $pub->getFromDB($p['id']);
    $alcance = count(\GlpiPlugin\Pessoasplus\Publico::resolverRegras($pub->regrasDoPublico()));
    $regras  = count($pub->regrasDoPublico());
    $lista['publicacoes'][$i]['publico_total']  = $alcance;
    $lista['publicacoes'][$i]['publico_rotulo'] = $regras . ($regras === 1 ? ' regra' : ' regras');
    $lista['publicacoes'][$i]['percentual']     = $alcance > 0 ? min(100, (int) round($p['visualizacoes'] * 100 / $alcance)) : 0;
    $lista['publicacoes'][$i]['autor']          = $p['autor'];
    $lista['publicacoes'][$i]['ordem'] = 0;
    if ($p['fixada'] && $p['estado'] === 'no_ar') {
        $ordem[$p['lugar']] = ($ordem[$p['lugar']] ?? 0) + 1;
        $lista['publicacoes'][$i]['ordem'] = $ordem[$p['lugar']];
        $fixadas++;
    }
    $m = $p['inicio'] !== '' ? substr($p['inicio'], 0, 7) : '';
    $lista['publicacoes'][$i]['meses'] = $m;
    if ($m !== '') {
        $meses[$m] = true;
    }
}
krsort($meses);
$periodos = [['valor' => 'todos', 'rotulo' => 'Todos os períodos', 'atual' => false]];
foreach (array_keys($meses) as $m) {
    $periodos[] = ['valor' => $m, 'rotulo' => date('m/Y', strtotime($m . '-01')), 'atual' => $m === substr($hoje, 0, 7)];
}
$filtros = [['chave' => 'todos', 'rotulo' => 'Todas', 'total' => count($lista['publicacoes'])]];
foreach (Publicacao::ESTADOS as $chave => $par) {
    $filtros[] = ['chave' => $chave, 'rotulo' => $par[0], 'total' => $lista['resumo'][$chave]];
}

$dados = [
    'publicacoes' => $lista['publicacoes'],
    'resumo'      => $lista['resumo'] + ['fixadas' => $fixadas],
    'filtros'     => $filtros,
    'periodos'    => $periodos,
    'publico'     => $publicoTotal,
    'referencia'  => date('d/m/Y'),
    'url_base'    => $base,
    'url_nova'    => Pagina::url('/plugins/pessoasplus/front/publicacao_nova.php'),
    'url_mural'   => Pagina::url('/plugins/pessoasplus/front/mural_colaborador.php'),
    'url_inicio'  => Pagina::url('/front/central.php'),
    'gerencia'    => Permissoes::gerencia('mural'),
];

$pp = Pagina::contexto('mural', 'Publicações do mural', 'O que está no ar, o que vem e os rascunhos', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/mural.html.twig', ['pp' => $pp]);
Pagina::rodape();
