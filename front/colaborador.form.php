<?php

/**
 * Pessoas+ - pasta do colaborador (BM.1): capa, Dados, Documentos e Historico.
 *
 * PESSOASPLUS_BUILD_BM1
 * PESSOASPLUS_BUILD_BM1_2
 *
 * URL derivada pelo core de GlpiPlugin\Pessoasplus\Colaborador
 * (Toolbox::getItemTypeFormURL). GET ?id= abre a pasta; sem id (ou ?users_id=)
 * abre a ficha nova. POST add/update/delete passam pelo CSRF do core (T-11)
 * e pelos direitos da classe (Colaborador::check).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Cargo;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Empregador;
use GlpiPlugin\Pessoasplus\Evento;
use GlpiPlugin\Pessoasplus\Fichario;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Jornada;
use GlpiPlugin\Pessoasplus\Local;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Session::checkRight(Install::RIGHT_BASE, READ);

$colaborador = new Colaborador();
$urlLista    = Pagina::url('/plugins/pessoasplus/front/fichario.php');

if (isset($_POST['add'])) {
    $colaborador->check(-1, CREATE, $_POST);
    $id = $colaborador->add($_POST);
    if ($id) {
        Session::addMessageAfterRedirect('Ficha criada.', false, INFO);
        Html::redirect(Pagina::url(Colaborador::getFormURLWithID((int) $id, false)));
    }
    Html::redirect(Pagina::url(Colaborador::getFormURL(false)) . (empty($_POST['users_id']) ? '' : '?users_id=' . (int) $_POST['users_id']));
} elseif (isset($_POST['update'])) {
    $colaborador->check((int) $_POST['id'], UPDATE);
    if ($colaborador->update($_POST)) {
        Session::addMessageAfterRedirect('Ficha salva.', false, INFO);
    }
    Html::redirect(Pagina::url(Colaborador::getFormURLWithID((int) $_POST['id'], false)));
} elseif (isset($_POST['delete'])) {
    $colaborador->check((int) $_POST['id'], DELETE);
    if ($colaborador->delete($_POST)) {
        Session::addMessageAfterRedirect('Ficha excluída.', false, INFO);
    }
    Html::redirect($urlLista);
}

$id       = (int) ($_GET['id'] ?? 0);
$nova     = $id <= 0;
$gerencia = Permissoes::gerencia('fichario');

if ($nova) {
    if (!$gerencia) {
        Session::checkRight(Permissoes::MODULOS['fichario'][0], Permissoes::GERENCIAR); // 403
    }
    $colaborador->getEmpty();
    $ficha = $colaborador->paraTela();
    $usersPre = max(0, (int) ($_GET['users_id'] ?? 0));
    if ($usersPre > 0) {
        $existente = Colaborador::porUsuario($usersPre);
        if ($existente !== null) {
            Html::redirect(Pagina::url(Colaborador::getFormURLWithID((int) $existente->getID(), false)));
        }
        $u = new User();
        if ($u->getFromDB($usersPre)) {
            $ficha['users_id'] = $usersPre;
            $ficha['nome']     = (string) formatUserName($usersPre, (string) $u->fields['name'], (string) $u->fields['realname'], (string) $u->fields['firstname']);
            $ficha['situacao'] = 'ativo';
            $emails = UserEmail::getAllForUser($usersPre);
            $ficha['email'] = (string) ($emails[0] ?? '');
            $ficha['telefone'] = (string) ($u->fields['mobile'] ?: $u->fields['phone'] ?: '');
            $ficha['matricula'] = (string) ($u->fields['registration_number'] ?? '');
        }
    }
    $aba = 'dados';
} else {
    if (!$colaborador->getFromDB($id) || (int) $colaborador->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Ficha não encontrada.', false, WARNING);
        Html::redirect($urlLista);
    }
    $colaborador->check($id, READ); // 403 se nao pode ver (D-36)
    $ficha = $colaborador->paraTela();
    // D-67: a Jornada e a aba inicial da pasta; Dados, Documentos e Historico por ?aba=.
    $aba   = in_array($_GET['aba'] ?? '', ['dados', 'documentos', 'historico'], true) ? $_GET['aba'] : 'jornada';
}

$opcoes = Fichario::opcoesFormulario();

$documentosHtml = '';
$podeDocumentos = false;
if (!$nova && $aba === 'documentos') {
    $podeDocumentos = Document::canView();
    if ($podeDocumentos) {
        ob_start();
        Document_Item::showForItem($colaborador);
        $documentosHtml = (string) ob_get_clean();
    }
}

$situacoes = [];
foreach (Colaborador::SITUACOES as $chave => $par) {
    $situacoes[] = ['chave' => $chave, 'rotulo' => $par[0]];
}
$vinculos = [];
foreach (Colaborador::VINCULOS as $chave => $rotulo) {
    $vinculos[] = ['chave' => $chave, 'rotulo' => $rotulo];
}

$urlForm = Pagina::url(Colaborador::getFormURL(false));
$dados = [
    'nova'            => $nova,
    'gerencia'        => $gerencia,
    'ficha'           => $ficha,
    'aba'             => $aba,
    'url_aba'         => [
        'jornada'    => $urlForm . '?id=' . $id,
        'dados'      => $nova ? $urlForm : $urlForm . '?id=' . $id . '&aba=dados',
        'documentos' => $urlForm . '?id=' . $id . '&aba=documentos',
        'historico'  => $urlForm . '?id=' . $id . '&aba=historico',
    ],
    'url_form'        => $urlForm,
    'url_lista'       => $urlLista,
    'url_ajax_lista'  => Pagina::url('/plugins/pessoasplus/ajax/lista.php'),
    'situacoes'       => $situacoes,
    'vinculos'        => $vinculos,
    'empregadores'    => Empregador::ativos(),
    'cargos'          => Cargo::ativos(),
    'locais'          => Local::ativos(),
    'grupos'          => $opcoes['grupos'],
    'usuarios'        => $opcoes['usuarios'],
    'gestores_json'   => json_encode($opcoes['gestores_por_grupo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
    'documentos_html' => $documentosHtml,
    'pode_documentos' => $podeDocumentos,
    'eventos'         => $nova ? [] : Evento::doColaborador($id),
];
$dados['jornada'] = $nova ? ['trechos' => [], 'atual' => ''] : Jornada::montar($ficha, $dados['eventos']);

$pp = Pagina::contexto(
    'fichario',
    $nova ? 'Nova ficha' : $ficha['nome'],
    $nova ? 'Quem é, onde trabalha e a quem responde' : 'Pasta do colaborador no Fichário',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/colaborador.html.twig', ['pp' => $pp]);
Pagina::rodape();
