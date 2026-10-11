<?php

/**
 * Pessoas+ - conferir meus itens em cautela, item a item (BM.6-2, D-60).
 *
 * PESSOASPLUS_BUILD_BM6_2
 *
 * Nas duas interfaces. GET ?id=<campanha> (o proprio colaborador) ou
 * ?id=<campanha>&colaborador=<ficha> (RH registrando por alguem). Cada item e
 * um POST proprio: progresso salvo item a item. Foto: o navegador reduz antes
 * de enviar (pessoasplus.js); sem JS o arquivo vai inteiro ate FOTO_MAX.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Conferencia;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Session::checkRight(Install::RIGHT_BASE, READ);

$uid      = (int) Session::getLoginUserID();
$urlMinha = Pagina::url(MenuSimplificado::PAGINA);
$urlAqui  = Pagina::url('/plugins/pessoasplus/front/conferir.php');

$cid  = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$conf = new Conferencia();
if ($cid <= 0 || !$conf->getFromDB($cid) || (int) $conf->fields['is_deleted'] === 1) {
    Session::addMessageAfterRedirect('Conferência não encontrada.', false, WARNING);
    Html::redirect($urlMinha);
}

// Por quem: o proprio (ficha ligada ao usuario) ou, para o RH, a ficha pedida.
$porOutro  = (int) ($_POST['colaborador'] ?? $_GET['colaborador'] ?? 0);
$colab     = null;
if ($porOutro > 0 && Permissoes::gerencia('cautela')) {
    $c = new Colaborador();
    if ($c->getFromDB($porOutro) && (int) $c->fields['is_deleted'] === 0) {
        $colab = $c;
    }
} else {
    $porOutro = 0;
    $colab = Colaborador::porUsuario($uid);
}
if ($colab === null) {
    Session::addMessageAfterRedirect($porOutro > 0 ? 'Ficha não encontrada.' : 'Você não tem ficha no Fichário; nada a conferir.', false, WARNING);
    Html::redirect($urlMinha);
}
$colId   = (int) $colab->getID();
$proprio = (int) $colab->fields['users_id'] === $uid;
if (!$proprio && !Permissoes::gerencia('cautela')) {
    Session::checkRight('pessoasplus_cautela', READ); // 403 do core
}
$conf->check($cid, READ);
$volta = $urlAqui . '?id=' . $cid . ($porOutro > 0 ? '&colaborador=' . $porOutro : '');

if (isset($_POST['responder'])) {
    $erro = $conf->responder($colId, (string) ($_POST['chave'] ?? ''), (string) ($_POST['situacao'] ?? ''), (string) ($_POST['observacao'] ?? ''), $_FILES['foto'] ?? null, $uid, Session::getCurrentInterface());
    if ($erro === '') {
        Session::addMessageAfterRedirect('Item conferido.', false, INFO);
    } else {
        Session::addMessageAfterRedirect($erro, false, ERROR);
    }
    Html::redirect($volta . '#item-' . substr((string) ($_POST['chave'] ?? ''), 0, 12));
}

$itens  = $conf->itensPara($colId);
$resp   = count(array_filter($itens, static fn ($i) => $i['resposta'] !== ''));
$tela   = Conferencia::paraTela($conf->fields);
$ct     = $colab->paraTela();
$respostas = [];
foreach (Conferencia::RESPOSTAS as $chave => $par) {
    $respostas[] = ['chave' => $chave, 'rotulo' => $par[0], 'tom' => $par[1], 'icone' => $par[2]];
}
$dados = $tela + [
    'colaborador'   => $ct['nome'],
    'colaborador_id' => $colId,
    'por_outro'     => $porOutro > 0,
    'recebe'        => $conf->recebeRespostas(),
    'itens'         => $itens,
    'total'         => count($itens),
    'respondidos'   => $resp,
    'concluida'     => $itens !== [] && $resp === count($itens),
    'respostas'     => $respostas,
    'url_aqui'      => $volta,
    'url_minha_area' => $urlMinha,
    'url_campanha'  => Pagina::url('/plugins/pessoasplus/front/conferencia.php') . '?id=' . $cid,
    'url_pasta'     => Pagina::url(Colaborador::getFormURLWithID($colId, false)) . '&aba=cautela',
    'foto_max'      => Conferencia::FOTO_MAX,
];

$pp = Pagina::contexto('minha_area', $tela['titulo'], ($porOutro > 0 ? 'Conferindo por ' . $ct['nome'] . ' · ' : 'Confira seus itens em cautela · ') . $tela['periodo'], $dados);

Pagina::cabecalho('Minha área');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/conferir.html.twig', ['pp' => $pp]);
Pagina::rodape();
