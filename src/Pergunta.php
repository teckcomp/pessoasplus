<?php

/**
 * Pessoas+ - Pergunta (BM.4).
 *
 * PESSOASPLUS_BUILD_BM4
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;

final class Pergunta extends CommonDBTM
{
    public static $rightname = 'pessoasplus_pesquisas';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_perguntas';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Pergunta';
    }
}
