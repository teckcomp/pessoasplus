<?php

/**
 * Pessoas+ - termo de cautela: rascunho, emissao, cancelamento, devolucao (BM.6-1).
 *
 * PESSOASPLUS_BUILD_BM6
 *
 * GET ?id= abre um termo; ?colaborador= abre um rascunho novo para a pessoa.
 * Quem gerencia Cautela edita e emite; quem le (ou o proprio colaborador,
 * ou o gestor) ve. A ciencia do colaborador fica em cautela_ciencia.php.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Cautela;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Session::checkRight(Install::RIGHT_BASE, READ);

$urlLista = Pagina::url('/plugins/pessoasplus/front/cautela.php');
$urlForm  = Pagina::url('/plugins/pessoasplus/front/cautela_termo.php');
$termo    = new Cautela();

if (isset($_POST['add'])) {
    $termo->check(-1, CREATE, $_POST);
    $id = $termo->add($_POST);
    if ($id) {
        $termo->definirItens(Cautela::normalizarItens((string) ($_POST['itens_json'] ?? '[]')));
        Session::addMessageAfterRedirect('Rascunho salvo.', false, INFO);
        Html::redirect($urlForm . '?id=' . (int) $id);
    }
    Html::redirect($urlForm . '?colaborador=' . (int) ($_POST['plugin_pessoasplus_colaboradores_id'] ?? 0));
} elseif (isset($_POST['update'])) {
    $termo->check((int) $_POST['id'], UPDATE);
    if ($termo->update($_POST)) {
        $termo->definirItens(Cautela::normalizarItens((string) ($_POST['itens_json'] ?? '[]')));
        Session::addMessageAfterRedirect('Rascunho salvo.', false, INFO);
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['emitir'])) {
    $termo->check((int) $_POST['id'], UPDATE);
    if ($termo->update($_POST)) {
        $termo->definirItens(Cautela::normalizarItens((string) ($_POST['itens_json'] ?? '[]')));
        if ($termo->emitir()) {
            Session::addMessageAfterRedirect('Termo emitido. O colaborador vê a pendência na Minha área.', false, INFO);
        }
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['cancelar'])) {
    Permissoes::exigirGerencia('cautela');
    $termo->check((int) $_POST['id'], READ);
    if ($termo->cancelar((string) ($_POST['motivo'] ?? ''))) {
        Session::addMessageAfterRedirect('Termo cancelado.', false, INFO);
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['delete'])) {
    $termo->check((int) $_POST['id'], DELETE);
    $termo->delete(['id' => (int) $_POST['id']], true);
    Session::addMessageAfterRedirect('Rascunho excluído.', false, INFO);
    Html::redirect($urlLista);
} elseif (isset($_POST['devolucao'])) {
    // Nova devolucao a partir de um termo assinado: rascunho com os mesmos itens, o RH tira o que nao voltou.
    Permissoes::exigirGerencia('cautela');
    $termo->check((int) $_POST['id'], READ);
    if ((string) $termo->fields['situacao'] === 'assinado' && (string) $termo->fields['tipo'] === 'entrega') {
        $novo = new Cautela();
        $nid  = $novo->add([
            'plugin_pessoasplus_colaboradores_id' => (int) $termo->fields['plugin_pessoasplus_colaboradores_id'],
            'tipo'        => 'devolucao',
            'titulo'      => 'Devolução: ' . (string) $termo->fields['titulo'],
            'observacoes' => 'Referente ao termo de entrega #' . (int) $termo->getID() . '.',
        ]);
        if ($nid) {
            $itens = [];
            foreach ($termo->itens() as $i) {
                $itens[] = ['origem' => $i['origem'], 'itemtype' => $i['itemtype'], 'items_id' => $i['items_id'], 'descricao' => $i['descricao'], 'patrimonio' => $i['patrimonio'], 'serie' => $i['serie'], 'quantidade' => $i['quantidade'], 'foto_obrigatoria' => (int) $i['foto_obrigatoria']];
            }
            $novo->definirItens($itens);
            Session::addMessageAfterRedirect('Rascunho de devolução criado com os itens do termo: retire o que não voltou e emita.', false, INFO);
            Html::redirect($urlForm . '?id=' . (int) $nid);
        }
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
}

$id   = (int) ($_GET['id'] ?? 0);
$nova = $id <= 0;
$colaborador = new Colaborador();
if ($nova) {
    Permissoes::exigirGerencia('cautela');
    $colId = (int) ($_GET['colaborador'] ?? 0);
    if ($colId <= 0 || !$colaborador->getFromDB($colId) || (int) $colaborador->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Escolha o colaborador na lista de Itens em cautela.', false, WARNING);
        Html::redirect($urlLista);
    }
    $termo->getEmpty();
    $termo->fields['plugin_pessoasplus_colaboradores_id'] = $colId;
    $termo->fields['users_id'] = (int) $colaborador->fields['users_id'];
    $termo->fields['tipo']     = ($_GET['tipo'] ?? '') === 'devolucao' ? 'devolucao' : 'entrega';
    $termo->fields['situacao'] = 'rascunho';
    $itens = [];
} else {
    if (!$termo->getFromDB($id) || (int) $termo->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Termo não encontrado.', false, WARNING);
        Html::redirect($urlLista);
    }
    $termo->check($id, READ); // 403 se nao pode ver (D-36)
    $colaborador->getFromDB((int) $termo->fields['plugin_pessoasplus_colaboradores_id']);
    $itens = $termo->itens();
}

$f        = $termo->fields;
$tela     = Cautela::paraTela($f + ['id' => $id], $itens);
$gerencia = Permissoes::gerencia('cautela');
$editavel = $gerencia && (string) $f['situacao'] === 'rascunho';
$ativos   = $editavel ? Cautela::ativosDoUsuario((int) $f['users_id']) : [];
$marcados = [];
foreach ($itens as $i) {
    if ($i['origem'] === 'glpi') {
        $marcados[$i['itemtype'] . '#' . $i['items_id']] = true;
    }
}
foreach ($ativos as &$a) {
    $a['marcado'] = isset($marcados[$a['itemtype'] . '#' . $a['items_id']]);
}
unset($a);

$tipos = [];
foreach (Cautela::TIPOS as $chave => $par) {
    $tipos[] = ['chave' => $chave, 'rotulo' => $par[0], 'icone' => $par[1]];
}
$hex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$colTela = $colaborador->getID() > 0 ? $colaborador->paraTela() : ['nome' => '', 'setor' => '', 'cargo' => '', 'users_id' => 0];

$dados = $tela + [
    'nova'         => $nova,
    'f'            => ['tipo' => (string) $f['tipo'], 'titulo' => (string) $f['titulo'], 'observacoes' => (string) ($f['observacoes'] ?? '')],
    'colab'        => ['id' => (int) $colaborador->getID(), 'nome' => $colTela['nome'], 'setor' => $colTela['setor'], 'cargo' => $colTela['cargo'], 'com_usuario' => (int) $colTela['users_id'] > 0, 'url_pasta' => Pagina::url(Colaborador::getFormURLWithID((int) $colaborador->getID(), false)) . '&aba=cautela'],
    'gerencia'     => $gerencia,
    'editavel'     => $editavel,
    'tipos'        => $tipos,
    'ativos'       => $ativos,
    'itens_json'   => json_encode(array_map(static fn ($i) => ['origem' => $i['origem'], 'itemtype' => $i['itemtype'], 'items_id' => $i['items_id'], 'descricao' => $i['descricao'], 'patrimonio' => $i['patrimonio'], 'serie' => $i['serie'], 'quantidade' => $i['quantidade'], 'foto_obrigatoria' => (int) $i['foto_obrigatoria'], 'tipo_rotulo' => $i['tipo_rotulo'], 'url' => $i['url']], $itens), $hex),
    'integridade'  => $nova ? ['termo' => true, 'ciencia' => true] : $termo->integridade(),
    'url_form'     => $urlForm,
    'url_lista'    => $urlLista,
];

$pp = Pagina::contexto('cautela', $nova ? 'Novo ' . mb_strtolower($tela['tipo_rotulo']) : $tela['titulo'], $colTela['nome'] !== '' ? $colTela['nome'] . ($colTela['setor'] !== '' ? ' · ' . $colTela['setor'] : '') : 'Itens em cautela', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/cautela_termo.html.twig', ['pp' => $pp]);
Pagina::rodape();
