<?php

/**
 * Pessoas+ - Participação (BM.4).
 *
 * PESSOASPLUS_BUILD_BM4
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;

final class Participacao extends CommonDBTM
{
    public static $rightname = 'pessoasplus_pesquisas';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_participacoes';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Participação';
    }
}
