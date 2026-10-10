<?php

/**
 * Pessoas+ - leitura com ciencia (BM.2): conteudo congelado, anexos e confirmacao.
 *
 * PESSOASPLUS_BUILD_BM2
 * PESSOASPLUS_BUILD_BM2_2
 *
 * GET ?id=<versao>. Nas duas interfaces. Quem abre: destinatario da versao
 * (confirma) ou quem le Comunicados (so ve). POST confirmar: concorda=1|0,
 * justificativa (obrigatoria quando 0). A leitura e registrada na trilha
 * uma vez por sessao.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use Glpi\RichText\RichText;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Versao;

Session::checkRight(Install::RIGHT_BASE, READ);

$uid      = (int) Session::getLoginUserID();
$urlMinha = Pagina::url(MenuSimplificado::PAGINA);
$urlAqui  = Pagina::url('/plugins/pessoasplus/front/leitura.php');

if (isset($_POST['confirmar'])) {
    $vid = (int) ($_POST['id'] ?? 0);
    $ok  = Comunicado::registrarCiencia($vid, (string) ($_POST['concorda'] ?? '1') === '1', (string) ($_POST['justificativa'] ?? ''));
    if ($ok > 0) {
        Session::addMessageAfterRedirect('Ciência registrada.', false, INFO);
    }
    Html::redirect($urlAqui . '?id=' . $vid);
}

$vid = (int) ($_GET['id'] ?? 0);
$versao = new Versao();
if ($vid <= 0 || !$versao->getFromDB($vid)) {
    Session::addMessageAfterRedirect('Comunicado não encontrado.', false, WARNING);
    Html::redirect($urlMinha);
}
$destinatario = Comunicado::eDestinatario($vid, $uid);
if (!$destinatario && !Permissoes::le('comunicados')) {
    Session::checkRight('pessoasplus_comunicados', READ); // 403 do core
}
$com = new Comunicado();
$com->getFromDB((int) $versao->fields['plugin_pessoasplus_comunicados_id']);

Comunicado::registrarLeitura($vid);
$ciencia = Comunicado::cienciaDe($vid, $uid);
$hoje    = date('Y-m-d');
$prazo   = (string) $versao->fields['prazo'];

$dados = [
    'versao_id'      => $vid,
    'titulo'         => (string) $versao->fields['titulo'],
    'tipo'           => Comunicado::TIPOS[$com->fields['tipo']][0] ?? $com->fields['tipo'],
    'codigo'         => 'v' . (int) $versao->fields['numero'],
    'versao'         => 'versão ' . (int) $versao->fields['numero'],
    'vigente'        => (string) $versao->fields['situacao'] === 'vigente',
    'situacao'       => ['vigente' => 'Vigente', 'substituida' => 'Substituída', 'revogada' => 'Revogada'][$versao->fields['situacao']] ?? $versao->fields['situacao'],
    'publicado'      => date('d/m/Y H:i', strtotime((string) $versao->fields['data_publicacao'])),
    'prazo'          => $prazo !== '' ? date('d/m/Y', strtotime($prazo)) : '—',
    'atrasado'       => $prazo !== '' && $prazo < $hoje,
    'selo'           => (string) $versao->fields['selo'],
    'conteudo_html'  => RichText::getSafeHtml((string) $versao->fields['conteudo']),
    'anexos'         => Comunicado::anexosDaVersao($vid),
    'links'          => Comunicado::linksParaTela((string) ($versao->fields['links'] ?? '')),
    'destinatario'   => $destinatario,
    'exige_concordancia' => (int) $versao->fields['exige_concordancia'] === 1,
    'resposta'       => (int) $versao->fields['exige_concordancia'] === 1 ? 'Li e estou de acordo' : 'Li e estou ciente',
    'ciencia'        => $ciencia === null ? null : [
        'resposta'  => (int) $ciencia['concorda'] === 1 ? 'Ciente' : 'Não concordo',
        'concorda'  => (int) $ciencia['concorda'] === 1,
        'data'      => date('d/m/Y H:i', strtotime((string) $ciencia['data'])),
        'selo'      => (string) $ciencia['selo'],
        'justificativa' => (string) $ciencia['justificativa'],
        'integra'   => Comunicado::cienciaIntegra($ciencia, (string) $versao->fields['selo']),
    ],
    'url_minha_area' => $urlMinha,
    'url_aqui'       => $urlAqui,
    'url_comprovante' => Pagina::url('/plugins/pessoasplus/front/comprovante.php') . '?id=' . $vid . '&u=' . $uid,
];

$pp = Pagina::contexto('minha_area', (string) $versao->fields['titulo'], $dados['tipo'] . ' · ' . $dados['versao'], $dados);

Pagina::cabecalho('Minha área');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/leitura.html.twig', ['pp' => $pp]);
Pagina::rodape();
