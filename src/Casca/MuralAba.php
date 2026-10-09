<?php

/**
 * Pessoas+ - aba "Mural" na pagina inicial das duas interfaces.
 *
 * PESSOASPLUS_BUILD_BP3A
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

use Central;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Helpdesk\HomePageTabs;
use GlpiPlugin\Pessoasplus\Install;
use Session;

/**
 * A pagina inicial do GLPI 11 nao chama hook de plugin (T-37), mas as duas
 * sao montadas com abas: Central (interface padrao, CentralController) e
 * Glpi\Helpdesk\HomePageTabs (simplificada, Helpdesk\IndexController:77).
 * Ambas passam por CommonGLPI::defineAllTabs, que acrescenta as abas
 * registradas com 'addtabon' (CommonGLPI.php:347). Conferido no 11.0.6.
 *
 * O conteudo e servido por ajax/common.tabs.php -> displayStandardTab,
 * que chama displayTabContentForItem direto pela classe, sem olhar o
 * registro: por isso o direito e conferido tambem aqui dentro.
 */
final class MuralAba extends CommonGLPI
{
    public static $rightname = Install::RIGHT_BASE;

    /** Telas iniciais onde a aba aparece. */
    public const TELAS = [Central::class, HomePageTabs::class];

    public static function getTypeName($nb = 0)
    {
        return 'Mural';
    }

    public static function getIcon()
    {
        return 'ti ti-layout-board';
    }

    public static function naTelaInicial(CommonGLPI $item): bool
    {
        foreach (self::TELAS as $classe) {
            if ($item instanceof $classe) {
                return true;
            }
        }

        return false;
    }

    public static function podeVer(): bool
    {
        return Session::getLoginUserID() !== false
            && (bool) Session::haveRight(Install::RIGHT_BASE, READ);
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!self::naTelaInicial($item) || !self::podeVer()) {
            return '';
        }

        return self::createTabEntry('Mural', 0, null, self::getIcon());
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!self::naTelaInicial($item) || !self::podeVer()) {
            return false;
        }

        TemplateRenderer::getInstance()->display(
            '@pessoasplus/casca/mural_aba.html.twig',
            ['pp' => self::contexto()]
        );

        return true;
    }

    /**
     * Contexto do template da aba. Mesma variavel "pp" das paginas (T-06),
     * sem navegacao: a aba mora na moldura do GLPI, nao na casca do RH.
     *
     * @return array<string, mixed>
     */
    public static function contexto(): array
    {
        $pp        = Pagina::contexto('mural', 'Mural', '', Fontes::mural());
        $pp['nav'] = [];

        return $pp;
    }
}
