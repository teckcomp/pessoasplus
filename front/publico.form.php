<?php

/**
 * Pessoas+ - formulario de publico salvo (BM.1, F2).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * As regras vao no campo regras_json (lista de {tipo, valor_id, valor_texto,
 * incluir_filhos, is_exclusao}); o servidor normaliza e descarta o invalido.
 * A previa (quantos e quem) vem de ajax/publico_previa.php.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Publico;
use GlpiPlugin\Pessoasplus\PublicoRegra;

Pagina::somenteInterfacePadrao();
Session::checkRight(Install::RIGHT_BASE, READ);

$publico  = new Publico();
$urlLista = Pagina::url('/plugins/pessoasplus/front/publicos.php');

$lerRegras = static function (array $post): array {
    $regras = json_decode((string) ($post['regras_json'] ?? '[]'), true);

    return is_array($regras) ? $regras : [];
};

if (isset($_POST['add'])) {
    $publico->check(-1, CREATE, $_POST);
    $_POST['is_modelo'] = 1;
    $id = $publico->add($_POST);
    if ($id) {
        Publico::salvarRegras((int) $id, $lerRegras($_POST));
        Session::addMessageAfterRedirect('Público criado.', false, INFO);
        Html::redirect(Pagina::url(Publico::getFormURLWithID((int) $id, false)));
    }
    Html::redirect(Pagina::url(Publico::getFormURL(false)));
} elseif (isset($_POST['update'])) {
    $publico->check((int) $_POST['id'], UPDATE);
    if ($publico->update($_POST)) {
        Publico::salvarRegras((int) $_POST['id'], $lerRegras($_POST));
        Session::addMessageAfterRedirect('Público salvo.', false, INFO);
    }
    Html::redirect(Pagina::url(Publico::getFormURLWithID((int) $_POST['id'], false)));
} elseif (isset($_POST['purge'])) {
    $publico->check((int) $_POST['id'], PURGE);
    if ($publico->delete(['id' => (int) $_POST['id']], true)) {
        Session::addMessageAfterRedirect('Público excluído.', false, INFO);
    }
    Html::redirect($urlLista);
}

$id   = (int) ($_GET['id'] ?? 0);
$nova = $id <= 0;
if ($nova) {
    if (!Publico::podeGerenciar()) {
        Session::checkRight('pessoasplus_comunicados', UPDATE); // 403
    }
    $publico->getEmpty();
    $regras = [];
} else {
    if (!$publico->getFromDB($id) || (int) $publico->fields['is_modelo'] !== 1) {
        Session::addMessageAfterRedirect('Público não encontrado.', false, WARNING);
        Html::redirect($urlLista);
    }
    $publico->check($id, READ);
    $regras = Publico::regras($id);
}

$tipos = [];
foreach (PublicoRegra::TIPOS as $chave => $rotulo) {
    $tipos[] = ['chave' => $chave, 'rotulo' => $rotulo];
}
$hex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$dados = [
    'nova'         => $nova,
    'gerencia'     => Publico::podeGerenciar(),
    'id'           => $id,
    'nome'         => (string) ($publico->fields['nome'] ?? ''),
    'regras'       => Publico::rotular($regras),
    'regras_json'  => json_encode(Publico::rotular($regras), $hex),
    'opcoes_json'  => json_encode(Publico::opcoes(), $hex),
    'tipos'        => $tipos,
    'previa'       => Publico::previa($regras),
    'url_form'     => Pagina::url(Publico::getFormURL(false)),
    'url_lista'    => $urlLista,
    'url_previa'   => Pagina::url('/plugins/pessoasplus/ajax/publico_previa.php'),
];

$pp = Pagina::contexto(
    'publicos',
    $nova ? 'Novo público' : $dados['nome'],
    'Inclua por regra, exclua quem não deve receber e confira quem fica',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/publico.html.twig', ['pp' => $pp]);
Pagina::rodape();
