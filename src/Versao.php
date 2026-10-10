<?php

/**
 * Pessoas+ - Versão de comunicado (BM.2).
 *
 * PESSOASPLUS_BUILD_BM2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;

final class Versao extends CommonDBTM
{
    public static $rightname = 'pessoasplus_comunicados';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_versoes';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Versão';
    }
}
