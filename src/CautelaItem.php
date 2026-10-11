<?php

/**
 * Pessoas+ - item de um termo de cautela (BM.6-1).
 *
 * PESSOASPLUS_BUILD_BM6
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;

final class CautelaItem extends CommonDBTM
{
    public static $rightname = 'pessoasplus_cautela';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_cautela_itens';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Itens do termo' : 'Item do termo';
    }
}
