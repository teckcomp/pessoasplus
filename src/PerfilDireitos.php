<?php

/**
 * Pessoas+ - aba de direitos do plugin na tela de perfil.
 *
 * PESSOASPLUS_BUILD_B01B
 * PESSOASPLUS_BUILD_BP0C
 * PESSOASPLUS_BUILD_BP3C
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonGLPI;
use Html;
use Profile;
use Session;

/**
 * Mostra os direitos do Pessoas+ numa aba propria do perfil, usando a
 * matriz nativa do GLPI (Profile::displayRightsChoiceMatrix).
 *
 * A gravacao e do proprio core: o formulario posta em front/profile.form.php
 * com "update", e Profile::prepareInputForUpdate converte os campos
 * "_pessoasplus" e "_pessoasplus_config" em direitos, como faz com todos
 * os direitos registrados em glpi_profilerights (conferido no 11.0.6).
 */
final class PerfilDireitos extends CommonGLPI
{
    /** Quem pode ver a aba: quem pode ver perfis. */
    public static $rightname = 'profile';

    public static function getTypeName($nb = 0)
    {
        return 'Pessoas+';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof Profile || $item->getID() <= 0) {
            return '';
        }
        if (!Session::haveRight('profile', READ)) {
            return '';
        }

        return self::createTabEntry('Pessoas+', 0, null, 'ti ti-users');
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Profile) {
            return false;
        }
        self::mostrar($item);

        return true;
    }

    /**
     * Rotulos das colunas. Numa mesma matriz, todas as linhas usam os
     * mesmos rotulos para o mesmo valor, senao o core cria coluna extra
     * (Profile.php:3612, T-43).
     */
    public const HABILITADO = [READ => 'Habilitado'];
    public const LER        = [READ => 'Ler'];
    public const GERENCIAR  = [READ => 'Ler', UPDATE => 'Gerenciar'];

    /**
     * Matriz "papeis" (D-51, D-55): uma coluna so, "Habilitado".
     * Na simplificada: uso e Mural ao entrar. Gestor fica para a D-52.
     *
     * @return array<int, array{rights: array<int, string>, label: string, field: string}>
     */
    public static function direitosPapeis(string $interface): array
    {
        $linhas = [
            ['rights' => self::HABILITADO, 'label' => 'Usar o Pessoas+ (colaborador)', 'field' => Install::RIGHT_BASE],
        ];
        if ($interface !== 'helpdesk') {
            $linhas[] = ['rights' => self::HABILITADO, 'label' => 'Atuar como gestor (grupos que gere)', 'field' => Permissoes::GESTOR];
        }
        $linhas[] = ['rights' => self::HABILITADO, 'label' => 'Abrir o Mural ao entrar', 'field' => EntradaMural::DIREITO];

        return $linhas;
    }

    /**
     * Matriz "gestao do RH e configuracao": um direito por modulo e, no
     * fim, a configuracao (movida da matriz de papeis, D-55). Vazia na
     * interface simplificada, onde nada disso existe.
     *
     * @return array<int, array{rights: array<int, string>, label: string, field: string}>
     */
    public static function direitosAreas(string $interface): array
    {
        if ($interface === 'helpdesk') {
            return [];
        }
        $linhas = [];
        foreach (Permissoes::MODULOS as $modulo) {
            $linhas[] = [
                'rights' => $modulo[2] ? self::GERENCIAR : self::LER,
                'label'  => $modulo[1],
                'field'  => $modulo[0],
            ];
        }
        $linhas[] = ['rights' => self::GERENCIAR, 'label' => 'Configurar o Pessoas+', 'field' => Install::RIGHT_CONFIG];

        return $linhas;
    }

    public static function podeEditar(): bool
    {
        return (bool) Session::haveRightsOr('profile', [CREATE, UPDATE, PURGE]);
    }

    public static function mostrar(Profile $perfil): void
    {
        $pode      = self::podeEditar();
        $interface = (string) ($perfil->fields['interface'] ?? 'central');

        echo '<div class="asset">';
        if ($pode) {
            echo '<form method="post" action="' . htmlescape(Profile::getFormURL()) . '">';
            echo Html::hidden('id', ['value' => $perfil->getID()]);
        }

        echo '<div class="card-body p-0">';
        $perfil->displayRightsChoiceMatrix(
            self::direitosPapeis($interface),
            ['canedit' => $pode, 'title' => 'Pessoas+ · papéis']
        );
        $areas = self::direitosAreas($interface);
        if ($areas !== []) {
            $perfil->displayRightsChoiceMatrix(
                $areas,
                ['canedit' => $pode, 'title' => 'Pessoas+ · gestão do RH e configuração']
            );
            echo '<p class="text-muted px-3 pb-2 mb-0">As áreas do RH exigem também "Usar o Pessoas+". '
                . 'Ler mostra a área; Gerenciar permite criar, editar, publicar e lembrar. '
                . '"Abrir o Mural ao entrar" leva ao Mural na primeira ida à página inicial de cada sessão. '
                . 'Mudanças valem no próximo login do usuário.</p>';
        } else {
            echo '<p class="text-muted px-3 pb-2 mb-0">Perfil da interface simplificada: '
                . 'gestor, áreas do RH e configuração só existem na interface padrão. '
                . '"Abrir o Mural ao entrar" vale aqui também. Mudanças valem no próximo login.</p>';
        }
        echo '</div>';

        if ($pode) {
            echo '<div class="card-body mx-n2 border-top d-flex flex-row-reverse align-items-start flex-wrap">';
            echo Html::submit('Salvar', ['name' => 'update', 'class' => 'btn btn-primary']);
            echo '</div>';
            Html::closeForm();
        }
        echo '</div>';
    }
}
