<?php

/**
 * Pessoas+ - entrada de menu na interface padrao.
 *
 * PESSOASPLUS_BUILD_B01
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonGLPI;

/**
 * Classe usada apenas para montar o item de menu.
 * Nao e um item persistido, por isso estende CommonGLPI e nao CommonDBTM.
 *
 * Html::generateMenuSession() chama getMenuContent() de cada classe
 * registrada em $PLUGIN_HOOKS['menu_toadd'].
 */
final class Menu extends CommonGLPI
{
    /**
     * Direito que controla a visibilidade do menu.
     * CommonGLPI::canView() ja testa Session::haveRight($rightname, READ).
     *
     * @var string
     */
    public static $rightname = Install::RIGHT_BASE;

    /**
     * Caminho da pagina inicial do plugin, relativo a raiz do GLPI.
     * No GLPI 11 as URLs de plugin sao montadas assim; Plugin::getWebDir()
     * esta deprecated e grava aviso no log a cada chamada.
     *
     * @var string
     */
    public const PAGE = '/plugins/pessoasplus/front/menu.php';

    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return 'Pessoas+';
    }

    /**
     * @return string
     */
    public static function getIcon(): string
    {
        return 'ti ti-users';
    }

    /**
     * Conteudo do item de menu. Array vazio esconde a entrada.
     *
     * @return array
     */
    public static function getMenuContent()
    {
        if (!static::canView()) {
            return [];
        }

        return [
            'title' => static::getTypeName(),
            'page'  => self::PAGE,
            'icon'  => static::getIcon(),
        ];
    }
}
