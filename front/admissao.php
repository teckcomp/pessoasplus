<?php

/**
 * Pessoas+ - acoes do RH sobre documentos de admissao (BM.8-1a, D-89, D-90).
 *
 * PESSOASPLUS_BUILD_BM8_1
 *
 * POST: acao=aceitar|recusar|dispensar|reabrir com id (documento) e motivo;
 *       acao=gerar com colaborador (recria as pendencias que faltam para o vinculo).
 * Sempre volta para a aba Documentos da pasta.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\AdmissaoDoc;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Session::checkRight(Install::RIGHT_BASE, READ);
if (!Permissoes::gerencia('fichario')) {
    Session::checkRight(Permissoes::MODULOS['fichario'][0], Permissoes::GERENCIAR); // 403
}
$urlLista = Pagina::url('/plugins/pessoasplus/front/fichario.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Html::redirect($urlLista);
}
$uid  = (int) Session::getLoginUserID();
$acao = (string) ($_POST['acao'] ?? '');

if ($acao === 'gerar') {
    $c = new Colaborador();
    $cid = (int) ($_POST['colaborador'] ?? 0);
    if ($cid <= 0 || !$c->getFromDB($cid) || (int) $c->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Ficha não encontrada.', false, WARNING);
        Html::redirect($urlLista);
    }
    $n = AdmissaoDoc::gerarPendencias($c);
    Session::addMessageAfterRedirect($n === 0 ? 'Nada a gerar: todos os documentos do vínculo já têm linha.' : $n . ($n === 1 ? ' pendência gerada.' : ' pendências geradas.'), false, INFO);
    Html::redirect(Pagina::url(Colaborador::getFormURLWithID($cid, false)) . '&aba=documentos');
}

if ($acao === 'avulso') { // D-100: pendencia de qualquer tipo ativo, fora da lista do vinculo
    $c = new Colaborador();
    $cid = (int) ($_POST['colaborador'] ?? 0);
    if ($cid <= 0 || !$c->getFromDB($cid) || (int) $c->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Ficha não encontrada.', false, WARNING);
        Html::redirect($urlLista);
    }
    $erro = AdmissaoDoc::criarAvulsa($c, (string) ($_POST['titulo'] ?? ''));
    Session::addMessageAfterRedirect($erro !== '' ? $erro : 'Pendência adicionada; envie o arquivo pela linha nova.', false, $erro !== '' ? ERROR : INFO);
    Html::redirect(Pagina::url(Colaborador::getFormURLWithID($cid, false)) . '&aba=documentos');
}

$doc = new AdmissaoDoc();
$id  = (int) ($_POST['id'] ?? 0);
if ($id <= 0 || !$doc->getFromDB($id)) {
    Session::addMessageAfterRedirect('Documento não encontrado.', false, WARNING);
    Html::redirect($urlLista);
}
$cid  = (int) $doc->fields['plugin_pessoasplus_colaboradores_id'];
$erro = $doc->avaliar($acao, $uid, (string) ($_POST['motivo'] ?? ''));
if ($erro !== '') {
    Session::addMessageAfterRedirect($erro, false, ERROR);
} else {
    $msg = ['aceitar' => 'Documento aceito.', 'recusar' => 'Documento recusado; o colaborador vê o motivo e uma nova pendência de reenvio.', 'dispensar' => 'Documento dispensado.', 'reabrir' => 'Pendência reaberta.'][$acao] ?? 'Feito.';
    Session::addMessageAfterRedirect($msg, false, INFO);
}
Html::redirect(Pagina::url(Colaborador::getFormURLWithID($cid, false)) . '&aba=documentos');
