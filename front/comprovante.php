<?php

/**
 * Pessoas+ - comprovante de ciencia (BM.2, Tela 12), pagina propria para imprimir.
 *
 * PESSOASPLUS_BUILD_BP2B
 * PESSOASPLUS_BUILD_BM2
 *
 * GET ?id=<versao>[&u=<usuario>]. Individual (u) da propria pessoa: quem usa o
 * Pessoas+. Coletivo e de terceiros: quem le Comunicados. Sem Html::header
 * (T-40). PDF nativo fica para depois de 13/10 (D-74): imprimir/salvar em PDF.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Identidade;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Versao;

Session::checkRight(Install::RIGHT_BASE, READ);

$eu  = (int) Session::getLoginUserID();
$vid = (int) ($_GET['id'] ?? 0);
$u   = isset($_GET['u']) ? (int) $_GET['u'] : 0;

if ($u !== $eu) {
    Permissoes::exigirLeitura('comunicados');
}
$versao = new Versao();
if ($vid <= 0 || !$versao->getFromDB($vid)) {
    Html::redirect(Pagina::url(MenuSimplificado::PAGINA));
}
$com = new Comunicado();
$com->getFromDB((int) $versao->fields['plugin_pessoasplus_comunicados_id']);
$painel = Comunicado::painel($versao);

$linhas = [];
foreach ($painel['pessoas'] as $p) {
    if ($p['situacao'] !== 'confirmado' && $p['situacao'] !== 'discorda') {
        continue;
    }
    if ($u > 0 && $p['users_id'] !== $u) {
        continue;
    }
    $c = Comunicado::cienciaDe($vid, $p['users_id']);
    $linhas[] = [
        'nome'      => $p['nome'],
        'grupo'     => $p['grupo'],
        'resposta'  => $p['rotulo'] . ($p['justificativa'] !== '' ? ' — ' . $p['justificativa'] : ''),
        'data'      => $c !== null ? date('d/m/Y H:i:s', strtotime((string) $c['data'])) : '',
        'interface' => $c !== null && $c['interface'] === 'helpdesk' ? 'Simplificada' : 'Padrão',
        'avisos'    => $p['integro'] ? 'selo ok' : 'SELO DIVERGE',
        'selo'      => $c !== null ? substr((string) $c['selo'], 0, 16) : '',
    ];
}
if ($u > 0 && $linhas === []) {
    Session::addMessageAfterRedirect('Ainda não há ciência registrada para esse comprovante.', false, WARNING);
    Html::redirect(Pagina::url(MenuSimplificado::PAGINA));
}

$dados = [
    'individual' => $u > 0,
    'titulo'     => $painel['titulo'],
    'codigo'     => 'versão ' . $painel['numero'],
    'tipo'       => Comunicado::TIPOS[$com->fields['tipo']][0] ?? $com->fields['tipo'],
    'publicado'  => $painel['publicado'],
    'selo'       => $painel['selo'],
    'protocolo'  => sprintf('PP-%s-V%d%s', date('Y', strtotime((string) $versao->fields['data_publicacao'])), $vid, $u > 0 ? '-U' . $u : '-COL'),
    'linhas'     => $linhas,
    'omitidos'   => 0,
    'emitido'    => date('d/m/Y H:i'),
    'anexos'     => $painel['anexos'],
    'total'      => count($painel['pessoas']),
];

TemplateRenderer::getInstance()->display('@pessoasplus/casca/comprovante.html.twig', ['pp' => [
    'css'        => Pagina::url('/plugins/pessoasplus/pessoasplus.css') . '?v=' . Navegacao::BUILD,
    'js'         => Pagina::url('/plugins/pessoasplus/pessoasplus.js') . '?v=' . Navegacao::BUILD,
    'demo'       => false,
    'identidade' => Identidade::atual(),
    'dados'      => $dados,
]]);
