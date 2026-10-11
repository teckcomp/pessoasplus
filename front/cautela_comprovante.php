<?php

/**
 * Pessoas+ - comprovante do termo de cautela, pagina propria para imprimir (BM.6-1, T-40).
 *
 * PESSOASPLUS_BUILD_BM6
 *
 * GET ?id=<termo>. Quem abre: o dono, quem le Cautela, o gestor (D-36).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Identidade;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Cautela;
use GlpiPlugin\Pessoasplus\Install;

Session::checkRight(Install::RIGHT_BASE, READ);

$tid   = (int) ($_GET['id'] ?? 0);
$termo = new Cautela();
if ($tid <= 0 || !$termo->getFromDB($tid) || (int) $termo->fields['is_deleted'] === 1 || !in_array($termo->fields['situacao'], ['emitido', 'assinado'], true)) {
    Session::addMessageAfterRedirect('Comprovante indisponível: o termo não foi emitido.', false, WARNING);
    Html::redirect(Pagina::url(MenuSimplificado::PAGINA));
}
$termo->check($tid, READ);

$tela  = Cautela::paraTela($termo->fields, $termo->itens());
$dados = $tela + [
    'integridade' => $termo->integridade(),
    'protocolo'   => sprintf('PP-%s-C%d', date('Y', strtotime((string) $termo->fields['emitido_em'])), $tid),
    'emitido'     => date('d/m/Y H:i'),
    'colaborador_usuario' => (int) $termo->fields['users_id'] > 0 ? (string) getUserName((int) $termo->fields['users_id']) : '',
];

TemplateRenderer::getInstance()->display('@pessoasplus/casca/cautela_comprovante.html.twig', ['pp' => [
    'css'        => Pagina::url('/plugins/pessoasplus/pessoasplus.css') . '?v=' . Navegacao::BUILD,
    'js'         => Pagina::url('/plugins/pessoasplus/pessoasplus.js') . '?v=' . Navegacao::BUILD,
    'demo'       => false,
    'identidade' => Identidade::atual(),
    'dados'      => $dados,
]]);
