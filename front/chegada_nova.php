<?php

/**
 * Pessoas+ - assistente de chegada (casca, Tela 4 da proposta).
 *
 * PESSOASPLUS_BUILD_BP4B
 *
 * Seis etapas (dados gerais, empregador e vinculo, necessidades de TI,
 * foto, recrutamento e vaga, documentos) e a etapa final que mostra o que
 * sera criado: ficha, modelos de checklist que casam com a pessoa (D-65),
 * chamados de TI (I-04) e normativas de admissao (I-01).
 * ?id=2 retoma o rascunho do Bruno na etapa em que parou; qualquer outro
 * id volta para a lista. Na casca nada e gravado: o "Rascunho salvo" e o
 * "Concluir" mostram o que vai acontecer.
 * Na mobilia: B4.5 a B4.8.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirGerencia('chegadas');

$lista_url = Pagina::url('/plugins/pessoasplus/front/chegadas.php');
$id        = null;
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false) {
        Html::redirect($lista_url);
    }
}
$dados = Fontes::novaChegada($id);
if ($dados === null) {
    Html::redirect($lista_url);
}
$dados['url_lista'] = $lista_url;
// Configuracao lida pelo JS. Texto em <script> exige os HEX (T-15).
$dados['config_json'] = (string) json_encode(
    $dados['config'],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$pp = Pagina::contexto(
    'chegadas',
    $dados['rascunho'] === null ? 'Nova chegada' : 'Nova chegada · ' . $dados['rascunho']['pessoa'],
    'O RH declara quem chega; o sistema cria a ficha, distribui o checklist, abre os chamados de TI e atribui as normativas',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/chegada_nova.html.twig', ['pp' => $pp]);
Pagina::rodape();
