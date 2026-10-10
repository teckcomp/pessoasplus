<?php

/**
 * Pessoas+ - lista livre: Empregador (D-69).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

final class Empregador extends ListaLivre
{
    /** O core pluraliza em ingles (DbUtils::getPlural): nome fixo (T-63). */
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_empregadores';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Empregador';
    }

    public static function chave(): string
    {
        return 'empregador';
    }

    public static function rotulo(): string
    {
        return 'Empregador';
    }
}
