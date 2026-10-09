<?php

/**
 * Pessoas+ - funcoes de manutencao chamadas pelo core.
 *
 * PESSOASPLUS_BUILD_B01
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Install;

/**
 * Chamada por Plugin::install(). Precisa devolver true, senao o core
 * considera a instalacao falha e mantem o plugin como nao instalado.
 *
 * @param array $params parametros passados por "plugin:install -p chave=valor"
 *
 * @return bool
 */
function plugin_pessoasplus_install(array $params = []): bool
{
    $migration = new Migration(PLUGIN_PESSOASPLUS_VERSION);

    return Install::install($migration, $params);
}

/**
 * Chamada por Plugin::uninstall().
 *
 * @return bool
 */
function plugin_pessoasplus_uninstall(): bool
{
    return Install::uninstall();
}
