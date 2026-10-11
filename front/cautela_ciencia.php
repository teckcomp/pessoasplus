<?php

/**
 * Pessoas+ - ciencia do termo de cautela pelo colaborador (BM.6-1).
 *
 * PESSOASPLUS_BUILD_BM6
 *
 * Nas duas interfaces. GET ?id=<termo>. Quem abre: o dono do termo (assina),
 * quem le Cautela e o gestor (so veem). Registro imutavel, com selo.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Cautela;
use GlpiPlugin\Pessoasplus\Install;

Session::checkRight(Install::RIGHT_BASE, READ);

$uid      = (int) Session::getLoginUserID();
$urlMinha = Pagina::url(MenuSimplificado::PAGINA);
$urlAqui  = Pagina::url('/plugins/pessoasplus/front/cautela_ciencia.php');

if (isset($_POST['confirmar'])) {
    $tid   = (int) ($_POST['id'] ?? 0);
    $termo = new Cautela();
    if ($tid > 0 && $termo->getFromDB($tid) && (int) ($_POST['confirmo'] ?? 0) === 1) {
        if ($termo->registrarCiencia($uid, Session::getCurrentInterface())) {
            Session::addMessageAfterRedirect('Ciência registrada. O comprovante fica na Minha área.', false, INFO);
        } else {
            Session::addMessageAfterRedirect('Não foi possível registrar: o termo não está aguardando a sua ciência.', false, WARNING);
        }
    } else {
        Session::addMessageAfterRedirect('Marque a confirmação antes de registrar.', false, WARNING);
    }
    Html::redirect($urlAqui . '?id=' . $tid);
}

$tid   = (int) ($_GET['id'] ?? 0);
$termo = new Cautela();
if ($tid <= 0 || !$termo->getFromDB($tid) || (int) $termo->fields['is_deleted'] === 1) {
    Session::addMessageAfterRedirect('Termo não encontrado.', false, WARNING);
    Html::redirect($urlMinha);
}
$termo->check($tid, READ); // 403 se nao e o dono, nem RH, nem gestor (D-36)

$tela  = Cautela::paraTela($termo->fields, $termo->itens());
$dados = $tela + [
    'dono'            => (int) $termo->fields['users_id'] === $uid,
    'integridade'     => $termo->integridade(),
    'url_minha_area'  => $urlMinha,
    'url_aqui'        => $urlAqui,
];

$pp = Pagina::contexto('minha_area', $tela['titulo'], $tela['tipo_rotulo'] . ($tela['emitido_em'] !== '' ? ' · emitido em ' . $tela['emitido_em'] : ''), $dados);

Pagina::cabecalho('Minha área');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/cautela_ciencia.html.twig', ['pp' => $pp]);
Pagina::rodape();
