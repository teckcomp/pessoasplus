<?php

/**
 * Pessoas+ - rotina de instalacao.
 *
 * PESSOASPLUS_BUILD_B01
 * PESSOASPLUS_BUILD_BP0C
 * PESSOASPLUS_BUILD_BP3C
 * PESSOASPLUS_BUILD_BM1
 * PESSOASPLUS_BUILD_BM2
 * PESSOASPLUS_BUILD_BM2_2
 * PESSOASPLUS_BUILD_BM3
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use DBmysql;
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
     * Tabelas do plugin, na ordem de criacao (BM.1).
     * Prefixo glpi_plugin_pessoasplus_ (contexto 3.2).
     *
     * @var string[]
     */
    public const TABELAS = [
        'glpi_plugin_pessoasplus_empregadores',
        'glpi_plugin_pessoasplus_cargos',
        'glpi_plugin_pessoasplus_locais',
        'glpi_plugin_pessoasplus_colaboradores',
        'glpi_plugin_pessoasplus_eventos',
        'glpi_plugin_pessoasplus_publicos',
        'glpi_plugin_pessoasplus_publicos_regras',
        'glpi_plugin_pessoasplus_comunicados',
        'glpi_plugin_pessoasplus_versoes',
        'glpi_plugin_pessoasplus_anexos',
        'glpi_plugin_pessoasplus_destinatarios',
        'glpi_plugin_pessoasplus_ciencias',
        'glpi_plugin_pessoasplus_publicacoes',
        'glpi_plugin_pessoasplus_vistas',
    ];

    /**
     * @param Migration $migration
     * @param array     $params
     *
     * @return bool
     */
    public static function install(Migration $migration, array $params = []): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        self::installTables($DB, $migration);
        self::installRights($migration);

        $migration->executeMigration();

        return true;
    }

    /**
     * Cria as tabelas que ainda nao existem (idempotente, T-05).
     * Charset e engine seguem o padrao do core 11 (utf8mb4, InnoDB, ROW_FORMAT=DYNAMIC).
     */
    private static function installTables(DBmysql $DB, Migration $migration): void
    {
        $fim = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";

        // Listas livres (D-69): empregador, cargo e local de trabalho.
        foreach (['empregadores', 'cargos', 'locais'] as $lista) {
            $tabela = 'glpi_plugin_pessoasplus_' . $lista;
            if (!$DB->tableExists($tabela)) {
                $migration->displayMessage("Criando $tabela");
                $DB->doQuery("CREATE TABLE `$tabela` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL DEFAULT '',
                    `is_active` tinyint NOT NULL DEFAULT '1',
                    `comment` text,
                    `date_creation` timestamp NULL DEFAULT NULL,
                    `date_mod` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `name` (`name`),
                    KEY `is_active` (`is_active`)
                ) $fim");
            }
        }

        // Ficha do colaborador (F1). users_id = 0 e pre-cadastro sem usuario.
        // Setor e sempre um grupo do GLPI (D-35, D-69). Sem dado de saude.
        $tabela = 'glpi_plugin_pessoasplus_colaboradores';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` int unsigned NOT NULL DEFAULT '0',
                `is_recursive` tinyint NOT NULL DEFAULT '0',
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `nome` varchar(255) NOT NULL DEFAULT '',
                `email` varchar(255) NOT NULL DEFAULT '',
                `telefone` varchar(50) NOT NULL DEFAULT '',
                `matricula` varchar(50) NOT NULL DEFAULT '',
                `vinculo` varchar(30) NOT NULL DEFAULT 'clt',
                `situacao` varchar(30) NOT NULL DEFAULT 'pre_cadastro',
                `plugin_pessoasplus_empregadores_id` int unsigned NOT NULL DEFAULT '0',
                `plugin_pessoasplus_cargos_id` int unsigned NOT NULL DEFAULT '0',
                `plugin_pessoasplus_locais_id` int unsigned NOT NULL DEFAULT '0',
                `groups_id` int unsigned NOT NULL DEFAULT '0',
                `users_id_gestor` int unsigned NOT NULL DEFAULT '0',
                `data_inicio` date DEFAULT NULL,
                `data_fim` date DEFAULT NULL,
                `observacoes` text,
                `is_deleted` tinyint NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `users_id` (`users_id`),
                KEY `nome` (`nome`),
                KEY `groups_id` (`groups_id`),
                KEY `situacao` (`situacao`),
                KEY `vinculo` (`vinculo`),
                KEY `entities_id` (`entities_id`),
                KEY `is_deleted` (`is_deleted`)
            ) $fim");
        }

        // Trilha de eventos: somente inclusao (D-44). Cada modulo grava aqui.
        $tabela = 'glpi_plugin_pessoasplus_eventos';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `modulo` varchar(30) NOT NULL DEFAULT '',
                `acao` varchar(50) NOT NULL DEFAULT '',
                `itemtype` varchar(100) NOT NULL DEFAULT '',
                `items_id` int unsigned NOT NULL DEFAULT '0',
                `plugin_pessoasplus_colaboradores_id` int unsigned NOT NULL DEFAULT '0',
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `interface` varchar(10) NOT NULL DEFAULT '',
                `data` timestamp NULL DEFAULT NULL,
                `detalhes` text,
                PRIMARY KEY (`id`),
                KEY `item` (`itemtype`,`items_id`),
                KEY `colaborador` (`plugin_pessoasplus_colaboradores_id`),
                KEY `modulo` (`modulo`),
                KEY `data` (`data`)
            ) $fim");
        }

        // Publico por regras (F2), resolvido na consulta (D-72).
        $tabela = 'glpi_plugin_pessoasplus_publicos';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `nome` varchar(255) NOT NULL DEFAULT '',
                `itemtype` varchar(100) NOT NULL DEFAULT '',
                `items_id` int unsigned NOT NULL DEFAULT '0',
                `is_modelo` tinyint NOT NULL DEFAULT '1',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `item` (`itemtype`,`items_id`),
                KEY `is_modelo` (`is_modelo`)
            ) $fim");
        }

        $tabela = 'glpi_plugin_pessoasplus_publicos_regras';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `plugin_pessoasplus_publicos_id` int unsigned NOT NULL DEFAULT '0',
                `tipo` varchar(20) NOT NULL DEFAULT '',
                `valor_id` int unsigned NOT NULL DEFAULT '0',
                `valor_texto` varchar(100) NOT NULL DEFAULT '',
                `incluir_filhos` tinyint NOT NULL DEFAULT '0',
                `is_exclusao` tinyint NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `publico` (`plugin_pessoasplus_publicos_id`)
            ) $fim");
        }

        // BM.2 - comunicados e normativas (M1).
        $tabela = 'glpi_plugin_pessoasplus_comunicados';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` int unsigned NOT NULL DEFAULT '0',
                `tipo` varchar(20) NOT NULL DEFAULT 'comunicado',
                `titulo` varchar(255) NOT NULL DEFAULT '',
                `conteudo` longtext,
                `situacao` varchar(20) NOT NULL DEFAULT 'rascunho',
                `exige_concordancia` tinyint NOT NULL DEFAULT '0',
                `prazo_dias` int NOT NULL DEFAULT '7',
                `plugin_pessoasplus_publicos_id` int unsigned NOT NULL DEFAULT '0',
                `publico_modelo_id` int unsigned NOT NULL DEFAULT '0',
                `versao_atual` int NOT NULL DEFAULT '0',
                `vigencia_inicio` date DEFAULT NULL,
                `vigencia_fim` date DEFAULT NULL,
                `exigir_admissao` tinyint NOT NULL DEFAULT '0',
                `referencia` varchar(100) NOT NULL DEFAULT '',
                `links` text,
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `is_deleted` tinyint NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `situacao` (`situacao`),
                KEY `tipo` (`tipo`),
                KEY `users_id` (`users_id`),
                KEY `is_deleted` (`is_deleted`)
            ) $fim");
        }

        // Versao congelada: conteudo, hash do conteudo e selo (conteudo + anexos).
        $tabela = 'glpi_plugin_pessoasplus_versoes';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `plugin_pessoasplus_comunicados_id` int unsigned NOT NULL DEFAULT '0',
                `numero` int NOT NULL DEFAULT '1',
                `titulo` varchar(255) NOT NULL DEFAULT '',
                `conteudo` longtext,
                `hash_conteudo` char(64) NOT NULL DEFAULT '',
                `links` text,
                `selo` char(64) NOT NULL DEFAULT '',
                `exige_concordancia` tinyint NOT NULL DEFAULT '0',
                `motivo` text,
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `data_publicacao` timestamp NULL DEFAULT NULL,
                `prazo` date DEFAULT NULL,
                `situacao` varchar(20) NOT NULL DEFAULT 'vigente',
                `data_encerramento` timestamp NULL DEFAULT NULL,
                `motivo_encerramento` text,
                PRIMARY KEY (`id`),
                KEY `comunicado` (`plugin_pessoasplus_comunicados_id`,`numero`),
                KEY `situacao` (`situacao`)
            ) $fim");
        }

        $tabela = 'glpi_plugin_pessoasplus_anexos';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `plugin_pessoasplus_versoes_id` int unsigned NOT NULL DEFAULT '0',
                `nome` varchar(255) NOT NULL DEFAULT '',
                `mime` varchar(255) NOT NULL DEFAULT '',
                `tamanho` int unsigned NOT NULL DEFAULT '0',
                `caminho` varchar(255) NOT NULL DEFAULT '',
                `hash` char(64) NOT NULL DEFAULT '',
                `documents_id` int unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `versao` (`plugin_pessoasplus_versoes_id`)
            ) $fim");
        }

        // BM.2-2: documentos relacionados (links para o Codex+ ou qualquer URL).
        // Idempotente: so adiciona onde a coluna ainda nao existe (T-05).
        foreach (['glpi_plugin_pessoasplus_comunicados', 'glpi_plugin_pessoasplus_versoes'] as $t) {
            if ($DB->tableExists($t) && !$DB->fieldExists($t, 'links')) {
                $migration->addField($t, 'links', 'text');
                $migration->migrationOneTable($t);
            }
        }

        // Lista congelada de quem precisa dar ciencia na versao.
        $tabela = 'glpi_plugin_pessoasplus_destinatarios';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `plugin_pessoasplus_versoes_id` int unsigned NOT NULL DEFAULT '0',
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `groups_id` int unsigned NOT NULL DEFAULT '0',
                `data_inclusao` timestamp NULL DEFAULT NULL,
                `origem` varchar(20) NOT NULL DEFAULT 'publicacao',
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicidade` (`plugin_pessoasplus_versoes_id`,`users_id`),
                KEY `users_id` (`users_id`)
            ) $fim");
        }

        // Ciencia: somente inclusao, com selo da propria linha.
        $tabela = 'glpi_plugin_pessoasplus_ciencias';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `plugin_pessoasplus_versoes_id` int unsigned NOT NULL DEFAULT '0',
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `concorda` tinyint NOT NULL DEFAULT '1',
                `justificativa` text,
                `interface` varchar(10) NOT NULL DEFAULT '',
                `data` timestamp NULL DEFAULT NULL,
                `selo` char(64) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicidade` (`plugin_pessoasplus_versoes_id`,`users_id`),
                KEY `users_id` (`users_id`)
            ) $fim");
        }

        // BM.3 - mural (M2): publicacoes com lugar, fixacao, periodo e publico.
        $tabela = 'glpi_plugin_pessoasplus_publicacoes';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `entities_id` int unsigned NOT NULL DEFAULT '0',
                `titulo` varchar(255) NOT NULL DEFAULT '',
                `chamada` varchar(255) NOT NULL DEFAULT '',
                `conteudo` longtext,
                `tipo` varchar(20) NOT NULL DEFAULT 'aviso',
                `lugar` varchar(20) NOT NULL DEFAULT 'cartao',
                `situacao` varchar(20) NOT NULL DEFAULT 'rascunho',
                `inicio` date DEFAULT NULL,
                `fim` date DEFAULT NULL,
                `fixada` tinyint NOT NULL DEFAULT '0',
                `fixada_em` timestamp NULL DEFAULT NULL,
                `imagem_caminho` varchar(255) NOT NULL DEFAULT '',
                `imagem_alt` varchar(255) NOT NULL DEFAULT '',
                `botoes` text,
                `plugin_pessoasplus_publicos_id` int unsigned NOT NULL DEFAULT '0',
                `publico_modelo_id` int unsigned NOT NULL DEFAULT '0',
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `is_deleted` tinyint NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `situacao` (`situacao`),
                KEY `lugar` (`lugar`),
                KEY `periodo` (`inicio`,`fim`),
                KEY `is_deleted` (`is_deleted`)
            ) $fim");
        }

        // Quem ja viu cada publicacao (novidade na entrada, D-54; contagem agregada).
        $tabela = 'glpi_plugin_pessoasplus_vistas';
        if (!$DB->tableExists($tabela)) {
            $migration->displayMessage("Criando $tabela");
            $DB->doQuery("CREATE TABLE `$tabela` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `plugin_pessoasplus_publicacoes_id` int unsigned NOT NULL DEFAULT '0',
                `users_id` int unsigned NOT NULL DEFAULT '0',
                `data` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicidade` (`plugin_pessoasplus_publicacoes_id`,`users_id`),
                KEY `users_id` (`users_id`)
            ) $fim");
        }
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

        // D-54 (BP.3c): "Abrir o Mural ao entrar" nasce 0 para TODOS os
        // perfis, inclusive o super-admin: e opcao de perfil, nao permissao.
        // Com valor 0 o addRight grava 0 tanto em quem atende $requiredrights
        // quanto em quem nao atende (Migration.php:1248-1256).
        $migration->addRight(EntradaMural::DIREITO, 0, ['config' => READ | UPDATE]);
    }

    /**
     * Remove o que o plugin criou: direitos e tabelas.
     * Desinstalar APAGA as fichas, eventos e publicos: na producao, so com backup.
     *
     * @return bool
     */
    public static function uninstall(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        ProfileRight::deleteProfileRights(Permissoes::todosOsDireitos());

        foreach (array_reverse(self::TABELAS) as $tabela) {
            if ($DB->tableExists($tabela)) {
                $DB->doQuery("DROP TABLE `$tabela`");
            }
        }

        return true;
    }
}
