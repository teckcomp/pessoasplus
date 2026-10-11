<?php

/**
 * Pessoas+ - fila de um formulario / porta da Ouvidoria (BM.4), area do RH.
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_5
 *
 * GET ?id=<formulario>[&situacao=]. POST tratar (resposta_id, situacao,
 * resposta_rh, complemento). Relato de conduta: so quem configura (D-79).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Session::checkRight(\GlpiPlugin\Pessoasplus\Install::RIGHT_BASE, READ);

$form = new Formulario();
$urlLista = Pagina::url('/plugins/pessoasplus/front/formularios.php');
$urlAqui  = Pagina::url('/plugins/pessoasplus/front/fila.php');

if (isset($_POST['tratar'])) {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$form->getFromDB($id) || !$form->podeLerFila()) {
        Session::checkRight('pessoasplus_pesquisas', READ);
    }
    if ($form->tratar((int) $_POST['resposta_id'], (string) ($_POST['situacao'] ?? ''), (string) ($_POST['resposta_rh'] ?? ''), (string) ($_POST['complemento'] ?? ''))) {
        Session::addMessageAfterRedirect('Tratamento registrado. Quem enviou vê pelo protocolo.', false, INFO);
    }
    Html::redirect($urlAqui . '?id=' . $id);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0 || !$form->getFromDB($id) || (int) $form->fields['is_deleted'] === 1) {
    Html::redirect($urlLista);
}
if (!$form->podeLerFila()) {
    Session::checkRight($form->ehOuvidoria() && (Formulario::PORTAS[$form->fields['porta']][5] ?? '') === 'config' ? 'pessoasplus_config' : 'pessoasplus_pesquisas', READ);
}
$situacao = (string) ($_GET['situacao'] ?? '');
$hoje = date('Y-m-d');
$situacoes = [];
foreach (Formulario::SITUACOES_RESPOSTA as $chave => $par) {
    $situacoes[] = ['chave' => $chave, 'rotulo' => $par[0]];
}
$dados = Formulario::paraTela($form->fields, $hoje) + [
    'respostas' => $form->fila($situacao),
    'resumo'    => $form->resumoFila(),
    'filtro'    => isset(Formulario::SITUACOES_RESPOSTA[$situacao]) ? $situacao : '',
    'situacoes' => $situacoes,
    'cobranca'  => $form->ehOuvidoria() ? null : $form->cobranca(),
    'gerencia'  => $form->canUpdateItem(),
    'url_lista' => $urlLista,
    'url_aqui'  => $urlAqui . '?id=' . $id,
    'perguntas' => $form->perguntas(),
    'resultado' => $form->ehOuvidoria() ? [] : $form->resultado(),
];

$pp = Pagina::contexto('pesquisas', $dados['titulo'], $form->ehOuvidoria() ? 'Fila da Ouvidoria · ' . $dados['porta_rotulo'] : 'Respostas da campanha', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/fila.html.twig', ['pp' => $pp]);
Pagina::rodape();
