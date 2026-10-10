<?php

/**
 * Pessoas+ - Ciência de comunicado (BM.2).
 *
 * PESSOASPLUS_BUILD_BM2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;

final class Ciencia extends CommonDBTM
{
    public static $rightname = 'pessoasplus_comunicados';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_ciencias';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Ciência';
    }

    /** Somente inclusao: a evidencia nao se altera nem se apaga pela aplicacao. */
    public function canUpdateItem(): bool
    {
        return false;
    }

    public function canDeleteItem(): bool
    {
        return false;
    }

    public function canPurgeItem(): bool
    {
        return false;
    }
}
