<?php

/**
 * Pessoas+ - painel do emissor: acompanhamento da ciencia (BM.2, Tela 2).
 *
 * PESSOASPLUS_BUILD_BM2
 * PESSOASPLUS_BUILD_BM2_2
 *
 * GET ?id=<comunicado>[&versao=<versao>] (sem versao: a vigente, ou a ultima).
 * POST revogar (motivo). Nova versao e feita pelo formulario (comunicado_novo.php).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use Glpi\RichText\RichText;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Versao;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('comunicados');

$com      = new Comunicado();
$urlLista = Pagina::url('/plugins/pessoasplus/front/comunicados.php');
$urlAqui  = Pagina::url('/plugins/pessoasplus/front/comunicado.php');

if (isset($_POST['revogar'])) {
    $com->check((int) $_POST['id'], UPDATE);
    if ($com->revogar((string) ($_POST['motivo'] ?? ''))) {
        Session::addMessageAfterRedirect('Comunicado revogado. As pendências foram encerradas; as ciências registradas permanecem.', false, INFO);
    }
    Html::redirect($urlAqui . '?id=' . (int) $_POST['id']);
}

$id = (int) ($_GET['id'] ?? 0);
if (!$com->getFromDB($id) || (int) $com->fields['is_deleted'] === 1) {
    Session::addMessageAfterRedirect('Comunicado não encontrado.', false, WARNING);
    Html::redirect($urlLista);
}
$com->check($id, READ);

if ((string) $com->fields['situacao'] === 'rascunho') {
    Html::redirect(Pagina::url('/plugins/pessoasplus/front/comunicado_novo.php') . '?id=' . $id);
}

$versao = new Versao();
$vid = (int) ($_GET['versao'] ?? 0);
if ($vid <= 0 || !$versao->getFromDB($vid) || (int) $versao->fields['plugin_pessoasplus_comunicados_id'] !== $id) {
    $vig = $com->versaoVigente();
    if ($vig === null) {
        if (!$versao->getFromDBByCrit(['plugin_pessoasplus_comunicados_id' => $id])) {
            Html::redirect($urlLista);
        }
    } else {
        $versao = $vig;
    }
}

$painel = Comunicado::painel($versao);
$dados = $painel + [
    'comunicado_id'   => $id,
    'tipo'            => Comunicado::TIPOS[$com->fields['tipo']][0] ?? $com->fields['tipo'],
    'situacao_com'    => Comunicado::SITUACOES[$com->fields['situacao']][0] ?? $com->fields['situacao'],
    'tom_com'         => Comunicado::SITUACOES[$com->fields['situacao']][1] ?? 'neutro',
    'gerencia'        => Permissoes::gerencia('comunicados'),
    'pode_revogar'    => Permissoes::gerencia('comunicados') && (string) $com->fields['situacao'] === 'publicado',
    'conteudo_html'   => RichText::getSafeHtml((string) $versao->fields['conteudo']),
    'versoes'         => $com->versoes(),
    'links'           => Comunicado::linksParaTela((string) ($versao->fields['links'] ?? '')),
    'url_lista'       => $urlLista,
    'url_aqui'        => $urlAqui . '?id=' . $id,
    'url_editar'      => Pagina::url('/plugins/pessoasplus/front/comunicado_novo.php') . '?id=' . $id,
    'url_csv'         => Pagina::url('/plugins/pessoasplus/front/comunicado_csv.php') . '?id=' . (int) $versao->getID(),
    'url_comprovante' => Pagina::url('/plugins/pessoasplus/front/comprovante.php') . '?id=' . (int) $versao->getID(),
];

$pp = Pagina::contexto('comunicados', (string) $versao->fields['titulo'], $dados['tipo'] . ' · acompanhamento da ciência', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/comunicado.html.twig', ['pp' => $pp]);
Pagina::rodape();
