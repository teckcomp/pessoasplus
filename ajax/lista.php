<?php

/**
 * Pessoas+ - inclusao inline nas listas livres (D-69, BM.1).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * POST lista=empregador|cargo|local, nome=... -> {"id":n,"nome":"..."}.
 * Chamado por fetch com X-Requested-With e X-Glpi-Csrf-Token: o core
 * valida o token no cabecalho e o preserva (CheckCsrfListener, T-62).
 * Exige Gerenciar no Fichario.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\ListaLivre;
use GlpiPlugin\Pessoasplus\Permissoes;

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

$hex = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

if (!Permissoes::gerencia('fichario') && !Permissoes::gerencia('chegadas')) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão para incluir itens nesta lista.'], $hex);
    return;
}

$classe = ListaLivre::classePorChave((string) ($_POST['lista'] ?? ''));
$nome   = trim((string) ($_POST['nome'] ?? ''));
if ($classe === null || $nome === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Lista ou nome inválido.'], $hex);
    return;
}

$id = $classe::obterOuCriar($nome);
if ($id <= 0) {
    http_response_code(500);
    echo json_encode(['erro' => 'Não foi possível gravar o item.'], $hex);
    return;
}

echo json_encode(['id' => $id, 'nome' => $classe::nome($id)], $hex);
