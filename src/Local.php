<?php

/**
 * Pessoas+ - lista livre: Local de trabalho (D-69).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

final class Local extends ListaLivre
{
    /** O core pluraliza em ingles (DbUtils::getPlural): nome fixo (T-63). */
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_locais';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Local de trabalho';
    }

    public static function chave(): string
    {
        return 'local';
    }

    public static function rotulo(): string
    {
        return 'Local de trabalho';
    }
}
