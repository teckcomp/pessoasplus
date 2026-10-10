<?php

/**
 * Pessoas+ - novo comunicado / editar rascunho / preparar nova versao (BM.2).
 *
 * PESSOASPLUS_BUILD_BM2
 * PESSOASPLUS_BUILD_BM2_2
 *
 * GET sem id: novo (?tipo=normativa pre-seleciona). GET ?id=: edita.
 * POST add | update | publicar (com motivo quando ja publicado) | delete.
 * O publico vai em regras_json (+ publico_modelo_id quando veio de um salvo).
 * Anexos: aba nativa de Documentos do GLPI ligada ao comunicado (D-37/D-09);
 * sao copiados e congelados ao publicar.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Publico;
use GlpiPlugin\Pessoasplus\PublicoRegra;

Pagina::somenteInterfacePadrao();
Permissoes::exigirGerencia('comunicados');

$com      = new Comunicado();
$urlLista = Pagina::url('/plugins/pessoasplus/front/comunicados.php');
$urlForm  = Pagina::url('/plugins/pessoasplus/front/comunicado_novo.php');

$lerRegras = static function (array $post): array {
    $r = json_decode((string) ($post['regras_json'] ?? '[]'), true);

    return is_array($r) ? $r : [];
};

if (isset($_POST['add'])) {
    $com->check(-1, CREATE, $_POST);
    $id = $com->add($_POST);
    if ($id) {
        $com->definirPublico($lerRegras($_POST), (int) ($_POST['publico_modelo_id'] ?? 0));
        Session::addMessageAfterRedirect('Rascunho salvo. Anexe arquivos na aba Anexos e publique quando estiver pronto.', false, INFO);
        Html::redirect($urlForm . '?id=' . (int) $id);
    }
    Html::redirect($urlForm);
} elseif (isset($_POST['update']) || isset($_POST['publicar'])) {
    $com->check((int) $_POST['id'], UPDATE);
    if ($com->update($_POST)) {
        $com->definirPublico($lerRegras($_POST), (int) ($_POST['publico_modelo_id'] ?? 0));
        $com->getFromDB((int) $_POST['id']);
        if (isset($_POST['publicar'])) {
            $vid = $com->publicar((string) ($_POST['motivo'] ?? ''));
            if ($vid > 0) {
                Session::addMessageAfterRedirect('Publicado: versão ' . (int) $com->fields['versao_atual'] . '. Os destinatários já veem a pendência.', false, INFO);
                Html::redirect(Pagina::url('/plugins/pessoasplus/front/comunicado.php') . '?id=' . (int) $_POST['id']);
            }
        } else {
            Session::addMessageAfterRedirect('Rascunho salvo.', false, INFO);
        }
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['delete'])) {
    $com->check((int) $_POST['id'], DELETE);
    if ($com->delete($_POST)) {
        Session::addMessageAfterRedirect('Rascunho excluído.', false, INFO);
    }
    Html::redirect($urlLista);
}

$id   = (int) ($_GET['id'] ?? 0);
$nova = $id <= 0;
if ($nova) {
    $com->getEmpty();
    $com->fields['tipo']       = ($_GET['tipo'] ?? '') === 'normativa' ? 'normativa' : 'comunicado';
    $com->fields['prazo_dias'] = 7;
    $regras = [];
    $aba = 'conteudo';
} else {
    if (!$com->getFromDB($id) || (int) $com->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Comunicado não encontrado.', false, WARNING);
        Html::redirect($urlLista);
    }
    $com->check($id, READ);
    $regras = $com->regrasDoPublico();
    $aba = ($_GET['aba'] ?? '') === 'anexos' ? 'anexos' : 'conteudo';
}
$f = $com->fields;

// Editor rico do core (TinyMCE), capturado para dentro do template.
ob_start();
Html::textarea([
    'name'            => 'conteudo',
    'value'           => (string) ($f['conteudo'] ?? ''),
    'enable_richtext' => true,
    'enable_images'   => false,
    'rows'            => 14,
    'cols'            => 100,
]);
$editorHtml = (string) ob_get_clean();

$anexosHtml = '';
if (!$nova && $aba === 'anexos' && Document::canView()) {
    ob_start();
    Document_Item::showForItem($com);
    $anexosHtml = (string) ob_get_clean();
}

$hex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$tipos = [];
foreach (Comunicado::TIPOS as $chave => $par) {
    $tipos[] = ['chave' => $chave, 'rotulo' => $par[0]];
}
$tiposRegra = [];
foreach (PublicoRegra::TIPOS as $chave => $rotulo) {
    $tiposRegra[] = ['chave' => $chave, 'rotulo' => $rotulo];
}
$modelos = [];
foreach (Publico::modelos() as $m) {
    $modelos[] = ['id' => $m['id'], 'nome' => $m['nome'], 'total' => $m['total'], 'regras' => json_encode($m['regras'], $hex)];
}

$dados = [
    'nova'           => $nova,
    'id'             => $id,
    'publicado'      => ($f['situacao'] ?? 'rascunho') === 'publicado',
    'revogado'       => ($f['situacao'] ?? '') === 'revogado',
    'versao_atual'   => (int) ($f['versao_atual'] ?? 0),
    'f'              => [
        'tipo'               => (string) ($f['tipo'] ?? 'comunicado'),
        'titulo'             => (string) ($f['titulo'] ?? ''),
        'prazo_dias'         => (int) ($f['prazo_dias'] ?? 7),
        'exige_concordancia' => (int) ($f['exige_concordancia'] ?? 0) === 1,
        'vigencia_inicio'    => (string) ($f['vigencia_inicio'] ?? ''),
        'vigencia_fim'       => (string) ($f['vigencia_fim'] ?? ''),
        'exigir_admissao'    => (int) ($f['exigir_admissao'] ?? 0) === 1,
        'referencia'         => (string) ($f['referencia'] ?? ''),
        'publico_modelo_id'  => (int) ($f['publico_modelo_id'] ?? 0),
    ],
    'links'          => Comunicado::normalizarLinks((string) ($f['links'] ?? '')),
    'links_json'     => json_encode(Comunicado::normalizarLinks((string) ($f['links'] ?? '')), $hex),
    'aba'            => $aba,
    'url_aba'        => ['conteudo' => $urlForm . '?id=' . $id, 'anexos' => $urlForm . '?id=' . $id . '&aba=anexos'],
    'editor_html'    => $editorHtml,
    'anexos_html'    => $anexosHtml,
    'pode_documentos' => Document::canView(),
    'tipos'          => $tipos,
    'tipos_regra'    => $tiposRegra,
    'modelos'        => $modelos,
    'regras'         => Publico::rotular($regras),
    'regras_json'    => json_encode(Publico::rotular($regras), $hex),
    'opcoes_json'    => json_encode(Publico::opcoes(), $hex),
    'previa'         => Publico::previa($regras),
    'url_form'       => $urlForm,
    'url_lista'      => $urlLista,
    'url_painel'     => $nova ? '' : Pagina::url('/plugins/pessoasplus/front/comunicado.php') . '?id=' . $id,
    'url_previa'     => Pagina::url('/plugins/pessoasplus/ajax/publico_previa.php'),
];

$pp = Pagina::contexto(
    'comunicados',
    $nova ? ($com->fields['tipo'] === 'normativa' ? 'Nova normativa' : 'Novo comunicado') : (string) $f['titulo'],
    $dados['publicado'] ? 'Publicado na versão ' . $dados['versao_atual'] . '. Alterações aqui viram a próxima versão ao publicar.' : 'Escreva, escolha quem recebe e publique',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/comunicado_novo.html.twig', ['pp' => $pp]);
Pagina::rodape();
