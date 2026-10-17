<?php

/**
 * Pessoas+ - Participação (BM.4): "ja respondeu", sem ligacao com a resposta (D-31).
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_17 (T-100: sem id sequencial; origem envio | declarado, D-97)
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;

final class Participacao extends CommonDBTM
{
    public static $rightname = 'pessoasplus_pesquisas';

    /** Gravada no envio (logado identificado, ou anonimo com bilhete). */
    public const ORIGEM_ENVIO = 'envio';
    /** Autodeclaracao "Ja respondi pelo QR" na Minha area (D-97). */
    public const ORIGEM_DECLARADO = 'declarado';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_participacoes';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Participação';
    }

    public static function existe(int $formId, int $uid): bool
    {
        return countElementsInTable(self::getTable(), ['plugin_pessoasplus_formularios_id' => $formId, 'users_id' => $uid]) > 0;
    }

    /**
     * Grava "ja respondeu" (so o dia, T-100). Idempotente: se ja existe, nao muda nada.
     * Nunca recebe nada da resposta: e isso que garante a D-31.
     */
    public static function registrar(int $formId, int $uid, string $origem = self::ORIGEM_ENVIO): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($uid <= 0 || $formId <= 0 || self::existe($formId, $uid)) {
            return false;
        }
        $DB->insert(self::getTable(), ['plugin_pessoasplus_formularios_id' => $formId, 'users_id' => $uid, 'data' => date('Y-m-d'), 'origem' => $origem === self::ORIGEM_DECLARADO ? self::ORIGEM_DECLARADO : self::ORIGEM_ENVIO]);

        return true;
    }

    /** Quantos, no formulario, vieram por autodeclaracao. */
    public static function declarados(int $formId): int
    {
        return countElementsInTable(self::getTable(), ['plugin_pessoasplus_formularios_id' => $formId, 'origem' => self::ORIGEM_DECLARADO]);
    }
}
