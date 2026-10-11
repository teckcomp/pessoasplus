<?php

/**
 * Pessoas+ - campanha de conferencia de itens em cautela, area do RH (BM.6-2, D-60).
 *
 * PESSOASPLUS_BUILD_BM6_2
 * PESSOASPLUS_BUILD_BM6_3
 *
 * GET sem id: nova campanha. GET ?id=: campanha (rascunho editavel; aberta/encerrada: painel
 * de pessoas, divergencias). POST add/update/abrir/encerrar/delete.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Conferencia;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Publico;
use GlpiPlugin\Pessoasplus\PublicoRegra;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('cautela');

$urlLista = Pagina::url('/plugins/pessoasplus/front/cautela.php');
$urlForm  = Pagina::url('/plugins/pessoasplus/front/conferencia.php');
$conf     = new Conferencia();
$lerJson  = static function (string $campo): array {
    $lido = json_decode((string) ($_POST[$campo] ?? '[]'), true);

    return is_array($lido) ? $lido : [];
};

if (isset($_POST['add'])) {
    $conf->check(-1, CREATE, $_POST);
    $id = $conf->add($_POST);
    if ($id) {
        $conf->definirPublico($lerJson('regras_json'), (int) ($_POST['publico_modelo_id'] ?? 0));
        Session::addMessageAfterRedirect('Campanha salva como rascunho.', false, INFO);
        Html::redirect($urlForm . '?id=' . (int) $id);
    }
    Html::redirect($urlForm);
} elseif (isset($_POST['update']) || isset($_POST['abrir'])) {
    $conf->check((int) $_POST['id'], UPDATE);
    if ($conf->update($_POST)) {
        if ((string) $conf->fields['situacao'] === 'rascunho') {
            $conf->definirPublico($lerJson('regras_json'), (int) ($_POST['publico_modelo_id'] ?? 0));
        }
        if (isset($_POST['abrir'])) {
            if ($conf->abrir()) {
                Session::addMessageAfterRedirect('Campanha aberta. Quem tem itens em cautela vê a conferência na Minha área.', false, INFO);
            }
        } else {
            Session::addMessageAfterRedirect('Campanha salva.', false, INFO);
        }
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['encerrar'])) {
    $conf->check((int) $_POST['id'], UPDATE);
    if ($conf->encerrar()) {
        Session::addMessageAfterRedirect('Campanha encerrada. As respostas ficam como estão.', false, INFO);
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['delete'])) {
    $conf->check((int) $_POST['id'], DELETE);
    $conf->delete(['id' => (int) $_POST['id']], true);
    Session::addMessageAfterRedirect('Rascunho excluído.', false, INFO);
    Html::redirect($urlLista);
}

$id   = (int) ($_GET['id'] ?? 0);
$nova = $id <= 0;
$hoje = date('Y-m-d');
if ($nova) {
    Permissoes::exigirGerencia('cautela');
    $conf->getEmpty();
    $conf->fields['situacao'] = 'rascunho';
    $conf->fields['titulo']   = '';
    $conf->fields['inicio']   = $hoje;
    $conf->fields['fim']      = date('Y-m-d', strtotime('+14 days'));
    $conf->fields['publico_modo'] = 'todos';
    $conf->fields['recorrencia']  = 'nenhuma';
    $regras = [];
} else {
    if (!$conf->getFromDB($id) || (int) $conf->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Campanha não encontrada.', false, WARNING);
        Html::redirect($urlLista);
    }
    $conf->check($id, READ);
    $regras = $conf->regrasDoPublico();
}
$f = $conf->fields;
$hex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$modelos = [];
foreach (Publico::modelos() as $m) {
    $modelos[] = ['id' => $m['id'], 'nome' => $m['nome'], 'total' => $m['total'], 'regras' => json_encode($m['regras'], $hex)];
}
$tiposRegra = [];
foreach (PublicoRegra::TIPOS as $chave => $rotulo) {
    $tiposRegra[] = ['chave' => $chave, 'rotulo' => is_array($rotulo) ? $rotulo[0] : $rotulo];
}
$gerencia = Permissoes::gerencia('cautela');
$editavel = $gerencia && (string) $f['situacao'] === 'rascunho';
$tela     = Conferencia::paraTela($f + ['id' => $id], $hoje);
$pessoas  = $nova ? [] : $conf->pessoas();
$resumo   = ['pessoas' => count($pessoas), 'conferiram' => 0, 'parciais' => 0, 'divergencias' => 0, 'nao_conferiram' => 0];
foreach ($pessoas as $p) {
    if ($p['respondidos'] === 0) {
        $resumo['nao_conferiram']++;
    } elseif ($p['respondidos'] < $p['itens']) {
        $resumo['parciais']++;
    } else {
        $resumo['conferiram']++;
    }
    $resumo['divergencias'] += $p['divergentes'];
}

$dados = $tela + [
    'nova'       => $nova,
    'gerencia'   => $gerencia,
    'editavel'   => $editavel,
    'f'          => ['titulo' => (string) $f['titulo'], 'orientacoes' => (string) ($f['orientacoes'] ?? ''), 'inicio' => (string) ($f['inicio'] ?? ''), 'fim' => (string) ($f['fim'] ?? ''), 'publico_modelo_id' => (int) ($f['publico_modelo_id'] ?? 0), 'publico_modo' => (string) (($f['publico_modo'] ?? '') ?: 'todos'), 'recorrencia' => (string) (($f['recorrencia'] ?? '') ?: 'nenhuma')],
    'modos'      => array_map(static fn ($k, $v) => ['chave' => $k, 'rotulo' => $v], array_keys(Conferencia::MODOS_PUBLICO), Conferencia::MODOS_PUBLICO),
    'recorrencias' => array_map(static fn ($k, $v) => ['chave' => $k, 'rotulo' => $v[0]], array_keys(Conferencia::RECORRENCIAS), Conferencia::RECORRENCIAS),
    'com_itens'  => count(Conferencia::usuariosComItens()),
    'proximo'    => Conferencia::proximoPeriodo((string) ($f['inicio'] ?? ''), (string) ($f['fim'] ?? ''), (string) (($f['recorrencia'] ?? '') ?: 'nenhuma')),
    'tipos_regra' => $tiposRegra,
    'modelos'    => $modelos,
    'regras'     => Publico::rotular($regras),
    'regras_json' => json_encode(Publico::rotular($regras), $hex),
    'opcoes_json' => json_encode(Publico::opcoes(), $hex),
    'previa'     => Publico::previa($regras),
    'pessoas'    => $pessoas,
    'resumo'     => $resumo,
    'divergencias' => $nova ? [] : $conf->divergencias(),
    'url_form'   => $urlForm,
    'url_lista'  => $urlLista,
    'url_previa' => Pagina::url('/plugins/pessoasplus/ajax/publico_previa.php'),
];

$pp = Pagina::contexto('cautela', $nova ? 'Nova conferência' : $tela['titulo'], $nova ? 'Quem confere, até quando e o que dizer' : 'Conferência de itens em cautela · ' . $tela['periodo'], $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/conferencia.html.twig', ['pp' => $pp]);
Pagina::rodape();
