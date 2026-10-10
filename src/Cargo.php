<?php

/**
 * Pessoas+ - lista livre: Cargo (D-69).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

final class Cargo extends ListaLivre
{
    /** O core pluraliza em ingles (DbUtils::getPlural): nome fixo (T-63). */
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_cargos';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Cargo';
    }

    public static function chave(): string
    {
        return 'cargo';
    }

    public static function rotulo(): string
    {
        return 'Cargo';
    }
}
