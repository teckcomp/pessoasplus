<?php

/**
 * Pessoas+ - rotina de instalacao.
 *
 * PESSOASPLUS_BUILD_B01
 * PESSOASPLUS_BUILD_BP0C
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use Migration;
use ProfileRight;

/**
 * Toda alteracao de schema, de direitos e de tarefas automaticas do plugin
 * acontece aqui, de forma idempotente: rodar duas vezes precisa ser inofensivo.
 * Higiene de dados nao entra nesta classe.
 */
final class Install
{
    /** Direito de uso do modulo (ver a area do Pessoas+). */
    public const RIGHT_BASE = 'pessoasplus';

    /** Direito de configurar o plugin (listas, parametros). */
    public const RIGHT_CONFIG = 'pessoasplus_config';

    /**
     * @param Migration $migration
     * @param array     $params
     *
     * @return bool
     */
    public static function install(Migration $migration, array $params = []): bool
    {
        self::installRights($migration);

        $migration->executeMigration();

        return true;
    }

    /**
     * Cria os direitos do plugin.
     *
     * Migration::addRight so cria a linha para os perfis que ainda nao a tem,
     * e so concede o valor aos perfis que atendem $requiredrights (por padrao
     * quem pode ler e alterar a configuracao, ou seja, o super-admin).
     * Os demais perfis nascem com o direito em zero, de proposito.
     *
     * @param Migration $migration
     *
     * @return void
     */
    private static function installRights(Migration $migration): void
    {
        $migration->addRight(self::RIGHT_BASE, READ, ['config' => READ | UPDATE]);
        $migration->addRight(self::RIGHT_CONFIG, READ | UPDATE, ['config' => READ | UPDATE]);

        // D-51 (BP.0c): gestor e um direito por modulo do RH. Mesma regra:
        // so o super-admin nasce com eles; os outros perfis ficam em zero.
        $migration->addRight(Permissoes::GESTOR, READ, ['config' => READ | UPDATE]);
        foreach (Permissoes::MODULOS as $modulo) {
            $valor = $modulo[2] ? READ | Permissoes::GERENCIAR : READ;
            $migration->addRight($modulo[0], $valor, ['config' => READ | UPDATE]);
        }
    }

    /**
     * Remove o que o plugin criou. Por enquanto, apenas os direitos:
     * nenhuma tabela de dominio existe ainda.
     *
     * @return bool
     */
    public static function uninstall(): bool
    {
        ProfileRight::deleteProfileRights(Permissoes::todosOsDireitos());

        return true;
    }
}
