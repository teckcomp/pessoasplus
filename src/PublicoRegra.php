<?php

/**
 * Pessoas+ - regra de publico (F2).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;

final class PublicoRegra extends CommonDBTM
{
    public static $rightname = 'pessoasplus_fichario';

    /** Tipos aceitos e o que o valor significa. */
    public const TIPOS = [
        'todos'      => 'Todos os usuários ativos',
        'grupo'      => 'Grupo (setor)',
        'perfil'     => 'Perfil',
        'entidade'   => 'Entidade',
        'usuario'    => 'Usuário',
        'vinculo'    => 'Tipo de vínculo',
        'empregador' => 'Empregador',
    ];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_publicos_regras';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Regra de público';
    }

    /**
     * Normaliza uma regra vinda da tela. Devolve null se for invalida.
     *
     * @param array<string, mixed> $r
     *
     * @return array{tipo: string, valor_id: int, valor_texto: string, incluir_filhos: int, is_exclusao: int}|null
     */
    public static function normalizar(array $r): ?array
    {
        $tipo = (string) ($r['tipo'] ?? '');
        if (!isset(self::TIPOS[$tipo])) {
            return null;
        }
        $valorId    = max(0, (int) ($r['valor_id'] ?? 0));
        $valorTexto = mb_substr(trim((string) ($r['valor_texto'] ?? '')), 0, 100);
        $exclusao   = !empty($r['is_exclusao']) ? 1 : 0;
        $filhos     = !empty($r['incluir_filhos']) ? 1 : 0;

        switch ($tipo) {
            case 'todos':
                if ($exclusao) {
                    return null; // excluir todos nao faz sentido
                }
                $valorId = 0;
                $valorTexto = '';
                $filhos = 0;
                break;
            case 'vinculo':
                if (!isset(Colaborador::VINCULOS[$valorTexto])) {
                    return null;
                }
                $valorId = 0;
                $filhos = 0;
                break;
            case 'grupo':
            case 'entidade':
                if ($valorId <= 0) {
                    return null;
                }
                $valorTexto = '';
                break;
            default:
                if ($valorId <= 0) {
                    return null;
                }
                $valorTexto = '';
                $filhos = 0;
        }

        return [
            'tipo'           => $tipo,
            'valor_id'       => $valorId,
            'valor_texto'    => $valorTexto,
            'incluir_filhos' => $filhos,
            'is_exclusao'    => $exclusao,
        ];
    }
}
