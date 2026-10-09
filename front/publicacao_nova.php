<?php

/**
 * Pessoas+ - nova publicacao do mural, ou edicao de uma existente (casca).
 *
 * PESSOASPLUS_BUILD_BP3D
 *
 * Quadro 2 do canvas "Pessoas+ - Mural do colaborador": tipo, conteudo,
 * lugar no mural (destaque fixo, grande, cartao, lateral), fixar no topo,
 * periodo, publico, interacao e previa no lugar escolhido (D-57, D-59).
 * Na casca nada e gravado: salvar e publicar mostram o que vai acontecer.
 * Na mobilia: B3.1 (publicacao, imagem, lugar, fixacao), B1.4 (publico)
 * e B3.3 (comentarios).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirGerencia('mural');

$id    = isset($_GET['id']) ? (int) $_GET['id'] : null;
$dados = Fontes::novaPublicacao($id !== null && $id > 0 ? $id : null);

// Imagens da casca ficam em public/demo/, servidas sem "/public" na URL (T-22, T-48).
foreach ($dados['imagens'] as $i => $img) {
    $dados['imagens'][$i]['url'] = Pagina::url('/plugins/pessoasplus/demo/' . $img['arquivo']) . '?v=' . Navegacao::BUILD;
}
$dados['url_lista'] = Pagina::url('/plugins/pessoasplus/front/mural.php');
// Fixadas por lugar, lidas pelo JS da previa. Texto de usuario em <script> exige os HEX (T-15).
$dados['fixadas_json'] = (string) json_encode(
    $dados['fixadas'],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$editando = $dados['form']['id'] > 0;

$pp = Pagina::contexto(
    'mural',
    $editando ? 'Editar publicação' : 'Nova publicação',
    'Tipo, conteúdo, lugar no mural, público e interação, com a prévia de como o colaborador vai ver',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/publicacao_nova.html.twig', ['pp' => $pp]);
Pagina::rodape();
