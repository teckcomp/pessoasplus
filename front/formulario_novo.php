<?php

/**
 * Pessoas+ - novo formulario / editar (BM.4). Perguntas em perguntas_json.
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_9 (D-92: previa "como vai aparecer" antes de abrir)
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Publico;
use GlpiPlugin\Pessoasplus\PublicoRegra;

Pagina::somenteInterfacePadrao();
Permissoes::exigirLeitura('pesquisas');

$form     = new Formulario();
$urlLista = Pagina::url('/plugins/pessoasplus/front/formularios.php');
$urlForm  = Pagina::url('/plugins/pessoasplus/front/formulario_novo.php');

$lerJson = static function (string $campo): array {
    $r = json_decode((string) ($_POST[$campo] ?? '[]'), true);

    return is_array($r) ? $r : [];
};

if (isset($_POST['add'])) {
    $form->check(-1, CREATE, $_POST);
    $id = $form->add($_POST);
    if ($id) {
        $form->definirPublico($lerJson('regras_json'), (int) ($_POST['publico_modelo_id'] ?? 0));
        $form->salvarPerguntas($lerJson('perguntas_json'));
        Session::addMessageAfterRedirect('Rascunho salvo.', false, INFO);
        Html::redirect($urlForm . '?id=' . (int) $id);
    }
    Html::redirect($urlForm);
} elseif (isset($_POST['update']) || isset($_POST['abrir'])) {
    $form->check((int) $_POST['id'], UPDATE);
    if ($form->update($_POST)) {
        $form->getFromDB((int) $_POST['id']);
        if (!$form->ehOuvidoria()) {
            $form->definirPublico($lerJson('regras_json'), (int) ($_POST['publico_modelo_id'] ?? 0));
        }
        $form->salvarPerguntas($lerJson('perguntas_json'));
        if (isset($_POST['abrir'])) {
            if ($form->abrir()) {
                Session::addMessageAfterRedirect('Formulário aberto.', false, INFO);
                Html::redirect($urlLista);
            }
        } else {
            Session::addMessageAfterRedirect('Salvo.', false, INFO);
        }
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['encerrar'])) {
    $form->check((int) $_POST['id'], UPDATE);
    if ($form->encerrar()) {
        Session::addMessageAfterRedirect('Formulário encerrado.', false, INFO);
    }
    Html::redirect($urlLista);
} elseif (isset($_POST['delete'])) {
    $form->check((int) $_POST['id'], DELETE);
    if ($form->delete($_POST)) {
        Session::addMessageAfterRedirect('Rascunho excluído.', false, INFO);
    }
    Html::redirect($urlLista);
}

$id   = (int) ($_GET['id'] ?? 0);
$nova = $id <= 0;
$hoje = date('Y-m-d');
if ($nova) {
    Permissoes::exigirGerencia('pesquisas');
    $form->getEmpty();
    $form->fields['modo'] = 'identificado';
    $form->fields['limite_dia'] = 20;
    $form->fields['tipo'] = 'pesquisa';
    $regras = [];
    $perguntas = [];
} else {
    if (!$form->getFromDB($id) || (int) $form->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Formulário não encontrado.', false, WARNING);
        Html::redirect($urlLista);
    }
    $form->check($id, READ);
    $regras = $form->regrasDoPublico();
    $perguntas = $form->perguntas();
}
$f = $form->fields;
$hex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$lista = static function (array $mapa): array {
    $saida = [];
    foreach ($mapa as $chave => $rotulo) {
        $saida[] = ['chave' => $chave, 'rotulo' => is_array($rotulo) ? $rotulo[0] : $rotulo];
    }

    return $saida;
};
$modelos = [];
foreach (Publico::modelos() as $m) {
    $modelos[] = ['id' => $m['id'], 'nome' => $m['nome'], 'total' => $m['total'], 'regras' => json_encode($m['regras'], $hex)];
}

$dados = [
    'nova'       => $nova,
    'id'         => $id,
    'ouvidoria'  => !$nova && $form->ehOuvidoria(),
    'gerencia'   => $nova ? true : $form->canUpdateItem(),
    'estado'     => $nova ? 'rascunho' : Formulario::estado($f, $hoje),
    'tela'       => $nova ? [] : Formulario::paraTela($f, $hoje) + ['resumo' => $form->resumoFila()],
    'f'          => [
        'titulo' => (string) ($f['titulo'] ?? ''), 'descricao' => (string) ($f['descricao'] ?? ''), 'modo' => (string) ($f['modo'] ?? 'identificado'),
        'inicio' => (string) ($f['inicio'] ?? ''), 'fim' => (string) ($f['fim'] ?? ''), 'publico_sem_login' => (int) ($f['publico_sem_login'] ?? 0) === 1,
        'limite_dia' => (int) ($f['limite_dia'] ?? 20), 'publico_modelo_id' => (int) ($f['publico_modelo_id'] ?? 0), 'porta' => (string) ($f['porta'] ?? ''),
    ],
    'modos'      => $lista(Formulario::MODOS),
    'tipos_pergunta' => $lista(Formulario::TIPOS_PERGUNTA),
    'perguntas_json' => json_encode($perguntas, $hex),
    'tipos_regra' => $lista(PublicoRegra::TIPOS),
    'modelos'    => $modelos,
    'regras'     => Publico::rotular($regras),
    'regras_json' => json_encode(Publico::rotular($regras), $hex),
    'opcoes_json' => json_encode(Publico::opcoes(), $hex),
    'previa'     => Publico::previa($regras),
    'url_form'   => $urlForm,
    'url_lista'  => $urlLista,
    'url_previa' => Pagina::url('/plugins/pessoasplus/ajax/publico_previa.php'),
    'url_previa_form' => Pagina::url('/plugins/pessoasplus/ajax/formulario_previa.php'),
    'previa_css' => Pagina::url('/plugins/pessoasplus/formulario_previa.css') . '?v=bm4-9',
    'previa_js'  => Pagina::url('/plugins/pessoasplus/formulario_previa.js') . '?v=bm4-9',
];

$pp = Pagina::contexto('pesquisas', $nova ? 'Novo formulário' : (string) $f['titulo'], $dados['ouvidoria'] ? 'Porta da Ouvidoria: perguntas e modo' : 'Perguntas, modo, período e quem responde', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/formulario_novo.html.twig', ['pp' => $pp]);
Pagina::rodape();
