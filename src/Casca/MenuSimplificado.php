<?php

/**
 * Pessoas+ - item "Minha area" no menu da interface simplificada.
 *
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP3B
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

use GlpiPlugin\Pessoasplus\Install;
use Session;

/**
 * Coloca "Minha area" e "Mural" no primeiro nivel do menu simplificado,
 * logo depois de "Chamados", como na Tela 1 da proposta (D-55).
 *
 * O hook 'helpdesk_menu_entry' do core poria o item dentro de "Plugins" e
 * nao checa direito, por isso usamos 'redefine_menus' (Html.php:1853). O
 * mesmo hook tambem e aplicado ao menu da interface padrao (Html.php:1647),
 * entao o metodo so mexe no menu quando a interface e a simplificada (T-37).
 */
final class MenuSimplificado
{
    /** Chave do item no menu; tambem e o "sector" passado ao helpHeader. */
    public const CHAVE = 'pessoasplus_minha_area';

    public const PAGINA = '/plugins/pessoasplus/front/minha_area.php';

    /** Item "Mural" do colaborador (BP.3b); tambem e o "sector" do helpHeader. */
    public const CHAVE_MURAL = 'pessoasplus_mural';

    public const PAGINA_MURAL = '/plugins/pessoasplus/front/mural_colaborador.php';

    /**
     * @param mixed $menu menu montado pelo core
     *
     * @return mixed
     */
    public static function redefinir($menu)
    {
        if (!is_array($menu)) {
            return $menu;
        }
        if (Session::getCurrentInterface() !== 'helpdesk') {
            return $menu;
        }
        if (!Session::haveRight(Install::RIGHT_BASE, READ)) {
            return $menu;
        }

        $menu = self::inserirDepois($menu, 'tickets', self::CHAVE, [
            'default' => self::PAGINA,
            'title'   => 'Minha área',
            'icon'    => 'ti ti-user-check',
        ]);

        return self::inserirDepois($menu, self::CHAVE, self::CHAVE_MURAL, [
            'default' => self::PAGINA_MURAL,
            'title'   => 'Mural',
            'icon'    => 'ti ti-layout-board',
        ]);
    }

    /**
     * Insere $item depois da chave $depois, preservando a ordem. Se a chave
     * nao existir (perfil sem chamados), o item vai para o fim.
     *
     * @param array<string, mixed> $menu
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    public static function inserirDepois(array $menu, string $depois, string $chave, array $item): array
    {
        if (array_key_exists($chave, $menu)) {
            return $menu;
        }

        $saida    = [];
        $inserido = false;
        foreach ($menu as $k => $v) {
            $saida[$k] = $v;
            if ($k === $depois) {
                $saida[$chave] = $item;
                $inserido      = true;
            }
        }
        if (!$inserido) {
            $saida[$chave] = $item;
        }

        return $saida;
    }
}
