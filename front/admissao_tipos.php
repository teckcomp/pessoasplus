<?php

/**
 * Pessoas+ - configuracao dos documentos de admissao (BM.8-1a, D-89).
 *
 * PESSOASPLUS_BUILD_BM8_1
 *
 * Lista editavel: nome, descricao, vinculos a que se aplica, obrigatorio, ativo, ordem.
 * Quem gerencia o Fichario edita; quem le, ve.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\AdmissaoTipo;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Session::checkRight(Install::RIGHT_BASE, READ);
Permissoes::exigirLeitura('fichario');

$tipo    = new AdmissaoTipo();
$urlAqui = Pagina::url('/plugins/pessoasplus/front/admissao_tipos.php');
$gerencia = Permissoes::gerencia('fichario');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$gerencia) {
        Session::checkRight(Permissoes::MODULOS['fichario'][0], Permissoes::GERENCIAR); // 403
    }
    if (isset($_POST['add'])) {
        $tipo->check(-1, CREATE, $_POST);
        if ($tipo->add($_POST)) {
            Session::addMessageAfterRedirect('Documento incluído.', false, INFO);
        }
    } elseif (isset($_POST['update'])) {
        $tipo->check((int) $_POST['id'], UPDATE);
        if ($tipo->update($_POST)) {
            Session::addMessageAfterRedirect('Documento salvo.', false, INFO);
        }
    } elseif (isset($_POST['purge'])) {
        $tipo->check((int) $_POST['id'], PURGE);
        if (countElementsInTable('glpi_plugin_pessoasplus_admissao_docs', ['plugin_pessoasplus_admissao_tipos_id' => (int) $_POST['id']]) > 0) {
            Session::addMessageAfterRedirect('Este documento já tem pendências em fichas; desative em vez de excluir.', false, WARNING);
        } elseif ($tipo->delete($_POST, true)) {
            Session::addMessageAfterRedirect('Documento excluído.', false, INFO);
        }
    }
    Html::redirect($urlAqui);
}

$vinculos = [];
foreach (Colaborador::VINCULOS as $chave => $rotulo) {
    $vinculos[] = ['chave' => $chave, 'rotulo' => $rotulo];
}
$dados = [
    'tipos'     => AdmissaoTipo::todos(),
    'vinculos'  => $vinculos,
    'gerencia'  => $gerencia,
    'url_aqui'  => $urlAqui,
    'url_fichario' => Pagina::url('/plugins/pessoasplus/front/fichario.php'),
    'css_admissao' => Pagina::url('/plugins/pessoasplus/admissao.css') . '?v=bm8-1',
];
$pp = Pagina::contexto('fichario', 'Documentos de admissão', 'O que cada vínculo precisa entregar; o colaborador envia pela Minha área e o RH confere na pasta', $dados);
Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/admissao_tipos.html.twig', ['pp' => $pp]);
Pagina::rodape();
