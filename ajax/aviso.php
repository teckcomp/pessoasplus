<?php

/**
 * Pessoas+ - conteudo do aviso ao entrar, consultado pelo public/aviso.js.
 *
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2D
 * PESSOASPLUS_BUILD_BP3C
 *
 * Script legado de plugin: o firewall do GLPI 11 ja exige usuario autenticado
 * (Firewall::FALLBACK_STRATEGY_FOR_LEGACY_SCRIPTS). Somente leitura do ponto
 * de vista dos dados; so marca na sessao quando o aviso volta (D-49).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Casca\Aviso;
use GlpiPlugin\Pessoasplus\Install;

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();

$resposta = Aviso::consumir(
    $_SESSION,
    (bool) Session::haveRight(Install::RIGHT_BASE, READ),
    time(),
    Aviso::vemDoMural((string) ($_SERVER['HTTP_REFERER'] ?? ''))
);

echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
