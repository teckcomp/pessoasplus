<?php

/**
 * Pessoas+ - fila de um formulario / porta da Ouvidoria (BM.4), area do RH.
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_5
 * PESSOASPLUS_BUILD_BM5
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
$tela      = Formulario::paraTela($form->fields, $hoje);
$resumo    = $form->resumoFila();
$cobranca  = $form->ehOuvidoria() ? null : $form->cobranca();
$ehPesquisa = !$form->ehOuvidoria();
// BM.5: regras de anonimato na tela (minimo 5 e texto aberto so apos o encerramento).
$restricao = $ehPesquisa ? $form->restricaoAnonimato((int) $resumo['total'], $tela['estado']) : ['anonimo' => false, 'bloqueado' => false, 'textos_liberados' => true, 'faltam' => 0];
$resultado = $ehPesquisa && !$restricao['bloqueado'] ? $form->resultado() : [];
if (!$restricao['textos_liberados']) {
    foreach ($resultado as &$q) {
        $q['textos_ocultos'] = count($q['textos']);
        $q['textos'] = [];
    }
    unset($q);
} else {
    foreach ($resultado as &$q) {
        $q['textos_ocultos'] = 0;
    }
    unset($q);
}
$indicadores = $ehPesquisa ? Formulario::indicadores($resultado, $cobranca, (int) $resumo['total']) : ['kpis' => [], 'top5' => []];
// Respostas individuais: Ouvidoria sempre; pesquisa identificada sempre; pesquisa anonima so encerrada (regra 5).
$mostraIndividuais = !$ehPesquisa || $restricao['textos_liberados'] || !$restricao['anonimo'];
$individuais = $mostraIndividuais ? $form->fila($situacao) : [];
if ($restricao['anonimo'] && $individuais !== []) {
    // Regra 2 do anonimato: sem ordem de chegada; embaralha a lista a cada abertura.
    shuffle($individuais);
}
$dados = $tela + [
    'respostas' => $individuais,
    'mostra_individuais' => $mostraIndividuais,
    'restricao' => $restricao,
    'minimo_anonimo' => Formulario::MINIMO_ANONIMO,
    'indicadores' => $indicadores['kpis'],
    'top5'      => $indicadores['top5'],
    'resumo'    => $resumo,
    'filtro'    => isset(Formulario::SITUACOES_RESPOSTA[$situacao]) ? $situacao : '',
    'situacoes' => $situacoes,
    'cobranca'  => $cobranca,
    'gerencia'  => $form->canUpdateItem(),
    'url_lista' => $urlLista,
    'url_aqui'  => $urlAqui . '?id=' . $id,
    'perguntas' => $form->perguntas(),
    'resultado' => $resultado,
];

$pp = Pagina::contexto('pesquisas', $dados['titulo'], $form->ehOuvidoria() ? 'Fila da Ouvidoria · ' . $dados['porta_rotulo'] : 'Respostas da campanha', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/fila.html.twig', ['pp' => $pp]);
Pagina::rodape();
