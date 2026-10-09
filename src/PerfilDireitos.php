<?php

/**
 * Pessoas+ - aba de direitos do plugin na tela de perfil.
 *
 * PESSOASPLUS_BUILD_B01B
 * PESSOASPLUS_BUILD_BP0C
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

    /** Rotulos das colunas: iguais em todas as linhas para formar uma coluna so. */
    public const LER       = [READ => 'Ler'];
    public const GERENCIAR = [READ => 'Ler', UPDATE => 'Gerenciar'];

    /**
     * Linhas da matriz "papeis" para a interface do perfil (D-51).
     * Na simplificada so existe o uso: gestor fica para a D-52 e a
     * configuracao so existe na interface padrao.
     *
     * @return array<int, array{rights: array<int, string>, label: string, field: string}>
     */
    public static function direitosPapeis(string $interface): array
    {
        $linhas = [
            ['rights' => self::LER, 'label' => 'Usar o Pessoas+ (colaborador)', 'field' => Install::RIGHT_BASE],
        ];
        if ($interface === 'helpdesk') {
            return $linhas;
        }
        $linhas[] = ['rights' => self::LER, 'label' => 'Atuar como gestor (grupos que gere)', 'field' => Permissoes::GESTOR];
        $linhas[] = ['rights' => self::GERENCIAR, 'label' => 'Configurar o Pessoas+', 'field' => Install::RIGHT_CONFIG];

        return $linhas;
    }

    /**
     * Linhas da matriz "areas do RH": um direito por modulo. Vazia na
     * interface simplificada, onde as areas do RH nao existem.
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
                ['canedit' => $pode, 'title' => 'Pessoas+ · áreas do RH']
            );
            echo '<p class="text-muted px-3 pb-2 mb-0">As áreas do RH exigem também "Usar o Pessoas+". '
                . 'Ler mostra a área; Gerenciar permite criar, editar, publicar e lembrar. '
                . 'Mudanças valem no próximo login do usuário.</p>';
        } else {
            echo '<p class="text-muted px-3 pb-2 mb-0">Perfil da interface simplificada: '
                . 'gestor, áreas do RH e configuração só existem na interface padrão.</p>';
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
