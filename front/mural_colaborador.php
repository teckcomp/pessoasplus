<?php

/**
 * Pessoas+ - Mural do colaborador (BM.3, real), nas duas interfaces.
 *
 * PESSOASPLUS_BUILD_BP3B
 * PESSOASPLUS_BUILD_BM3
 * PESSOASPLUS_BUILD_BM3_2
 * PESSOASPLUS_BUILD_BM4_7
 *
 * Publicacoes no ar cujo publico alcanca a pessoa (D-72), por lugar (D-59);
 * abrir o mural marca as publicacoes como vistas (novidade da entrada, D-54).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\EntradaMural;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Menu;
use GlpiPlugin\Pessoasplus\Publicacao;

global $CFG_GLPI;

Session::checkRight(Menu::$rightname, READ);

$uid   = (int) Session::getLoginUserID();
$nome  = trim((string) ($_SESSION['glpifirstname'] ?? ''));
$hoje  = date('Y-m-d');
$mural = Publicacao::muralDe($uid, $hoje);

$ids = [];
foreach (array_merge($mural['destaque'] === null ? [] : [$mural['destaque']], $mural['grandes'], $mural['cartoes'], $mural['laterais']) as $p) {
    $ids[] = $p['id'];
}
Publicacao::marcarVistas($ids, $uid);

$pendencias = Comunicado::pendenciasDe($uid, $hoje);
$atrasadas  = count(array_filter($pendencias, static fn ($p) => $p['tom'] === 'atraso'));
$resumo = [];
if (count($pendencias) > 0) {
    $resumo[] = ['icone' => 'ti ti-speakerphone', 'valor' => count($pendencias), 'texto' => (count($pendencias) === 1 ? 'ciência pendente' : 'ciências pendentes') . ($atrasadas > 0 ? ' · ' . $atrasadas . ' em atraso' : ''), 'tom' => $atrasadas > 0 ? 'atraso' : 'alerta', 'area' => 'minha_area'];
}
$pesquisas = Formulario::pendentesDe($uid, $hoje);
if (count($pesquisas) > 0) {
    $resumo[] = ['icone' => 'ti ti-chart-bar', 'valor' => count($pesquisas), 'texto' => count($pesquisas) === 1 ? 'pesquisa aberta' : 'pesquisas abertas', 'tom' => 'info', 'area' => 'minha_area'];
}
$filtros = [['chave' => 'todos', 'rotulo' => 'Todas']];
$tiposVistos = [];
foreach ($mural['cartoes'] as $c) {
    if (!isset($tiposVistos[$c['tipo']])) {
        $tiposVistos[$c['tipo']] = true;
        $filtros[] = ['chave' => $c['tipo'], 'rotulo' => $c['tipo_rotulo']];
    }
}
$resumo[] = ['icone' => 'ti ti-sparkles', 'valor' => $mural['nao_vistas'], 'texto' => $mural['nao_vistas'] === 1 ? 'publicação nova' : 'publicações novas', 'tom' => $mural['nao_vistas'] > 0 ? 'info' : 'neutro', 'area' => ''];

$dados = $mural + [
    'referencia'   => date('d/m/Y'),
    'pesquisas'    => $pesquisas,
    'filtros'      => $filtros,
    'resumo'       => $resumo,
    'quem'         => $nome === '' ? 'você' : $nome,
    'url_inicio'   => EntradaMural::urlContinuar($_SESSION, Pagina::interfaceAtual(), (string) ($CFG_GLPI['root_doc'] ?? '')),
    'url_ouvidoria' => Pagina::url(MenuSimplificado::PAGINA_OUVIDORIA),
    'url_minha_area' => Pagina::url(MenuSimplificado::PAGINA),
];

// BM.3-2: ?diag=1 mostra, para o proprio usuario, por que a entrada desviou ou nao.
$dados['diag'] = null;
if (($_GET['diag'] ?? '') === '1') {
    /** @var DBmysql $DB */
    global $DB;
    $linhas = [];
    foreach ($DB->request(['FROM' => Publicacao::getTable(), 'WHERE' => ['is_deleted' => 0]]) as $p) {
        $pid = (int) $p['plugin_pessoasplus_publicos_id'];
        $linhas[] = [
            'titulo'   => (string) $p['titulo'],
            'estado'   => Publicacao::estado($p, $hoje),
            'alcanca'  => $pid > 0 && in_array($uid, \GlpiPlugin\Pessoasplus\Publico::resolver($pid), true),
            'vista'    => countElementsInTable('glpi_plugin_pessoasplus_vistas', ['plugin_pessoasplus_publicacoes_id' => (int) $p['id'], 'users_id' => $uid]) > 0,
        ];
    }
    $dados['diag'] = [
        'usuario'       => $uid,
        'perfil'        => (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) . ' ' . (string) ($_SESSION['glpiactiveprofile']['name'] ?? ''),
        'direito_usar'  => (bool) Session::haveRight(\GlpiPlugin\Pessoasplus\Install::RIGHT_BASE, READ),
        'direito_abrir' => (bool) Session::haveRight(EntradaMural::DIREITO, READ),
        'decidido'      => (string) ($_SESSION[EntradaMural::SESSAO_DECIDIDO] ?? '(não decidido nesta sessão)'),
        'novidade_antes' => $mural['nao_vistas'],
        'publicacoes'   => $linhas,
    ];
}

$pp = Pagina::contexto('mural_colaborador', 'Mural', 'O que está acontecendo na empresa, em um lugar só', $dados);

Pagina::cabecalho('Mural', MenuSimplificado::CHAVE_MURAL);
TemplateRenderer::getInstance()->display('@pessoasplus/casca/mural_colaborador.html.twig', ['pp' => $pp]);
Pagina::rodape();
