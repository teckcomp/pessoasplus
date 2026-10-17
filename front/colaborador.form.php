<?php

/**
 * Pessoas+ - pasta do colaborador (BM.1): capa, Dados, Documentos e Historico.
 *
 * PESSOASPLUS_BUILD_BM8_1 (admissao na aba Documentos; lixeira: restaurar/purgar, T-97; D-100 sem Document nativo)
 *
 * PESSOASPLUS_BUILD_BM1
 * PESSOASPLUS_BUILD_BM1_2
 * PESSOASPLUS_BUILD_BM1_3 (D-84: foto da capa pela pasta; foto_cheia, url_foto, foto.css/js)
 * PESSOASPLUS_BUILD_BM6 (aba Cautela)
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
use GlpiPlugin\Pessoasplus\AdmissaoDoc;
use GlpiPlugin\Pessoasplus\Cargo;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Cautela;
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
        Session::addMessageAfterRedirect('Ficha enviada à lixeira. Pode ser restaurada ou excluída definitivamente pelo Fichário › Lixeira.', false, INFO);
    }
    Html::redirect($urlLista);
} elseif (isset($_POST['restore'])) { // BM.8-1c (T-97)
    $colaborador->check((int) $_POST['id'], DELETE);
    if ($colaborador->restore($_POST)) {
        Session::addMessageAfterRedirect('Ficha restaurada.', false, INFO);
    }
    Html::redirect(Pagina::url(Colaborador::getFormURLWithID((int) $_POST['id'], false)));
} elseif (isset($_POST['purge'])) { // BM.8-1c (T-97): purga definitiva; vinculos do core e arquivos de admissao caem junto
    $colaborador->check((int) $_POST['id'], PURGE);
    if ($colaborador->delete($_POST, true)) {
        Session::addMessageAfterRedirect('Ficha excluída definitivamente. A trilha de eventos fica.', false, INFO);
    }
    Html::redirect($urlLista . '?lixeira=1');
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
    // BM.8-1c: ficha na lixeira abre so para quem gerencia, com faixa e acoes de restaurar/purgar.
    if (!$colaborador->getFromDB($id) || ((int) $colaborador->fields['is_deleted'] === 1 && !$gerencia)) {
        Session::addMessageAfterRedirect('Ficha não encontrada.', false, WARNING);
        Html::redirect($urlLista);
    }
    $colaborador->check($id, READ); // 403 se nao pode ver (D-36)
    $ficha = $colaborador->paraTela();
    // D-67: a Jornada e a aba inicial da pasta; Dados, Documentos e Historico por ?aba=.
    $aba   = in_array($_GET['aba'] ?? '', ['dados', 'documentos', 'historico', 'cautela'], true) ? $_GET['aba'] : 'jornada';
}

$opcoes = Fichario::opcoesFormulario();

// BM.1-3: caminho do arquivo da foto do usuario ligado (campo nativo glpi_users.picture).
$fotoArquivo = '';
if ((int) $ficha['users_id'] > 0) {
    $u = new User();
    if ($u->getFromDB((int) $ficha['users_id'])) {
        $fotoArquivo = (string) ($u->fields['picture'] ?? '');
    }
}

// BM.8-1a (D-89/D-99): documentos de admissao em tabela e pasta proprias, acima dos nativos.
$admissao = null;
if (!$nova && $aba === 'documentos') {
    $admissao = [
        'docs'      => AdmissaoDoc::doColaborador($id),
        'resumo'    => AdmissaoDoc::resumo($id),
        'gerencia'  => $gerencia,
        'url_envio' => Pagina::url('/plugins/pessoasplus/front/admissao_envio.php'),
        'url_acao'  => Pagina::url('/plugins/pessoasplus/front/admissao.php'),
        'url_tipos' => Pagina::url('/plugins/pessoasplus/front/admissao_tipos.php'),
        'limite_mb' => (int) (AdmissaoDoc::LIMITE_BYTES / 1024 / 1024),
        'extensoes' => '.' . implode(',.', AdmissaoDoc::EXTENSOES),
        'css'       => Pagina::url('/plugins/pessoasplus/admissao.css') . '?v=bm8-1',
    ];
}
// D-100 (17/10/2026): documentos nativos do GLPI saem da pasta; todo documento do RH usa a pasta propria.

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
        'cautela'    => $urlForm . '?id=' . $id . '&aba=cautela',
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
    'admissao'        => $admissao,
    'na_lixeira'      => !$nova && (int) $colaborador->fields['is_deleted'] === 1,
    'css_admissao'    => Pagina::url('/plugins/pessoasplus/admissao.css') . '?v=bm8-1',
    'eventos'         => $nova ? [] : Evento::doColaborador($id),
    // BM.6-1: aba Cautela: ativos do GLPI no nome da pessoa (V-13), itens em posse e termos.
    'cautela'         => $nova || $aba !== 'cautela' ? null : [
        'ativos'      => Cautela::ativosDoUsuario((int) $ficha['users_id']),
        'posse'       => Cautela::emPosseDe($id),
        'termos'      => Cautela::lista(['plugin_pessoasplus_colaboradores_id' => $id]),
        'gerencia'    => Permissoes::gerencia('cautela'),
        'url_novo'    => Pagina::url('/plugins/pessoasplus/front/cautela_termo.php') . '?colaborador=' . $id,
    ],
    // BM.1-3 (D-84): foto da capa. Imagem cheia na pasta (a miniatura do core tem 71 px);
    // envio/remocao por front/foto.php; estaticos proprios para nao tocar nos do servidor.
    'url_foto'        => Pagina::url('/plugins/pessoasplus/front/foto.php'),
    'foto_css'        => Pagina::url('/plugins/pessoasplus/foto.css') . '?v=bm1-3',
    'foto_js'         => Pagina::url('/plugins/pessoasplus/foto.js') . '?v=bm1-3',
];
$dados['ficha']['foto_cheia'] = (int) $ficha['users_id'] > 0 ? (string) (Toolbox::getPictureUrl($fotoArquivo, true) ?? '') : '';
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
