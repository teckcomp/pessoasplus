<?php

/**
 * Pessoas+ - publico por regras (F2), resolvido na consulta (D-72).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;
use Session;

/**
 * Um publico e um conjunto de regras de inclusao e exclusao sobre os
 * usuarios ativos do GLPI. Grupo, perfil, entidade e usuario vem do core;
 * vinculo e empregador vem da ficha (so alcancam quem tem ficha ligada a
 * usuario e nao desligada). Sem regra de inclusao o publico e vazio: nunca
 * "todos" por omissao (T-10). Exclusao prevalece.
 *
 * is_modelo = 1: publico salvo para reutilizar ("Equipe de campo").
 * is_modelo = 0: publico proprio de um item (comunicado, publicacao), que
 * guarda a copia das regras no momento da publicacao.
 */
final class Publico extends CommonDBTM
{
    public static $rightname = 'pessoasplus_fichario';

    /** Limite de nomes devolvidos na previa. */
    public const PREVIA_NOMES = 60;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_publicos';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Públicos' : 'Público';
    }

    public static function getIcon()
    {
        return 'ti ti-users';
    }

    // ----------------------------------------------------------------- direitos

    /** Quem gerencia comunicados ou mural monta publicos. */
    public static function podeGerenciar(): bool
    {
        return Permissoes::gerencia('comunicados') || Permissoes::gerencia('mural') || Permissoes::gerencia('fichario');
    }

    public static function podeLer(): bool
    {
        return Permissoes::le('comunicados') || Permissoes::le('mural') || Permissoes::le('fichario');
    }

    public static function canCreate(): bool
    {
        return self::podeGerenciar();
    }

    public static function canView(): bool
    {
        return self::podeLer();
    }

    public static function canUpdate(): bool
    {
        return self::podeGerenciar();
    }

    public static function canDelete(): bool
    {
        return self::podeGerenciar();
    }

    public static function canPurge(): bool
    {
        return self::podeGerenciar();
    }

    public function canCreateItem(): bool
    {
        return self::podeGerenciar();
    }

    public function canViewItem(): bool
    {
        return self::podeLer();
    }

    public function canUpdateItem(): bool
    {
        return self::podeGerenciar();
    }

    public function canDeleteItem(): bool
    {
        return self::podeGerenciar();
    }

    public function canPurgeItem(): bool
    {
        return self::podeGerenciar();
    }

    // ----------------------------------------------------------------- entrada

    public function prepareInputForAdd($input)
    {
        $input['nome'] = mb_substr(trim((string) ($input['nome'] ?? '')), 0, 255);
        if ($input['nome'] === '' && !empty($input['is_modelo'])) {
            Session::addMessageAfterRedirect('Dê um nome ao público.', false, ERROR);

            return false;
        }
        $input['is_modelo'] = isset($input['is_modelo']) ? (int) (bool) $input['is_modelo'] : 1;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (array_key_exists('nome', $input)) {
            $input['nome'] = mb_substr(trim((string) $input['nome']), 0, 255);
            if ($input['nome'] === '') {
                Session::addMessageAfterRedirect('Dê um nome ao público.', false, ERROR);

                return false;
            }
        }

        return $input;
    }

    public function post_addItem()
    {
        if ((int) $this->fields['is_modelo'] === 1) {
            Evento::registrar('publicos', 'publico_criado', $this, 0, ['nome' => $this->fields['nome']]);
        }
    }

    public function post_updateItem($history = true)
    {
        if ((int) $this->fields['is_modelo'] === 1 && $this->updates !== []) {
            Evento::registrar('publicos', 'publico_alterado', $this, 0, ['nome' => $this->fields['nome']]);
        }
    }

    public function post_purgeItem()
    {
        /** @var DBmysql $DB */
        global $DB;
        $DB->delete(PublicoRegra::getTable(), ['plugin_pessoasplus_publicos_id' => (int) $this->getID()]);
        if ((int) $this->fields['is_modelo'] === 1) {
            Evento::registrar('publicos', 'publico_excluido', null, 0, ['nome' => $this->fields['nome'], 'id' => (int) $this->getID()]);
        }
    }

    // ----------------------------------------------------------------- regras

    /**
     * Substitui as regras do publico pelas informadas (ja normalizadas ou nao).
     *
     * @param array<int, array<string, mixed>> $regras
     *
     * @return int quantidade gravada
     */
    public static function salvarRegras(int $publicoId, array $regras): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->delete(PublicoRegra::getTable(), ['plugin_pessoasplus_publicos_id' => $publicoId]);
        $n = 0;
        foreach ($regras as $r) {
            $limpa = PublicoRegra::normalizar(is_array($r) ? $r : []);
            if ($limpa === null) {
                continue;
            }
            $limpa['plugin_pessoasplus_publicos_id'] = $publicoId;
            $DB->insert(PublicoRegra::getTable(), $limpa);
            $n++;
        }

        return $n;
    }

    /**
     * Regras gravadas, ja normalizadas.
     *
     * @return array<int, array{tipo: string, valor_id: int, valor_texto: string, incluir_filhos: int, is_exclusao: int}>
     */
    public static function regras(int $publicoId): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $it = $DB->request([
            'FROM'  => PublicoRegra::getTable(),
            'WHERE' => ['plugin_pessoasplus_publicos_id' => $publicoId],
            'ORDER' => 'id',
        ]);
        foreach ($it as $r) {
            $limpa = PublicoRegra::normalizar($r);
            if ($limpa !== null) {
                $saida[] = $limpa;
            }
        }

        return $saida;
    }

    /**
     * Regras com rotulo legivel, para a tela e para o JSON do editor.
     *
     * @param array<int, array<string, mixed>> $regras
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rotular(array $regras): array
    {
        $saida = [];
        foreach ($regras as $r) {
            $limpa = PublicoRegra::normalizar($r);
            if ($limpa === null) {
                continue;
            }
            $limpa['rotulo_tipo'] = PublicoRegra::TIPOS[$limpa['tipo']];
            $limpa['rotulo']      = self::rotuloValor($limpa);
            $saida[] = $limpa;
        }

        return $saida;
    }

    /**
     * @param array{tipo: string, valor_id: int, valor_texto: string, incluir_filhos: int, is_exclusao: int} $r
     */
    private static function rotuloValor(array $r): string
    {
        /** @var DBmysql $DB */
        global $DB;

        switch ($r['tipo']) {
            case 'todos':
                return 'Todos os usuários ativos';
            case 'vinculo':
                return Colaborador::VINCULOS[$r['valor_texto']] ?? $r['valor_texto'];
            case 'empregador':
                return Empregador::nome($r['valor_id']) ?: ('Empregador #' . $r['valor_id']);
            case 'usuario':
                return (string) getUserName($r['valor_id']);
            case 'grupo':
            case 'entidade':
            case 'perfil':
                $tabela = ['grupo' => 'glpi_groups', 'entidade' => 'glpi_entities', 'perfil' => 'glpi_profiles'][$r['tipo']];
                $campo  = $r['tipo'] === 'perfil' ? 'name' : 'completename';
                $it = $DB->request(['SELECT' => [$campo], 'FROM' => $tabela, 'WHERE' => ['id' => $r['valor_id']], 'LIMIT' => 1]);
                foreach ($it as $linha) {
                    return (string) $linha[$campo] . ($r['incluir_filhos'] ? ' (e subníveis)' : '');
                }

                return ucfirst($r['tipo']) . ' #' . $r['valor_id'];
        }

        return '';
    }

    // ----------------------------------------------------------------- resolucao

    /**
     * IDs dos usuarios alcancados pelo publico gravado.
     *
     * @return int[]
     */
    public static function resolver(int $publicoId): array
    {
        return self::resolverRegras(self::regras($publicoId));
    }

    /**
     * IDs dos usuarios alcancados por um conjunto de regras (nao precisam
     * estar gravadas). Inclusoes em uniao; exclusoes subtraem.
     *
     * @param array<int, array<string, mixed>> $regras
     *
     * @return int[]
     */
    public static function resolverRegras(array $regras): array
    {
        $inclui = [];
        $exclui = [];
        $temInclusao = false;
        foreach ($regras as $r) {
            $limpa = PublicoRegra::normalizar(is_array($r) ? $r : []);
            if ($limpa === null) {
                continue;
            }
            $ids = self::usuariosDaRegra($limpa);
            if ($limpa['is_exclusao']) {
                $exclui += array_fill_keys($ids, true);
            } else {
                $temInclusao = true;
                $inclui += array_fill_keys($ids, true);
            }
        }
        if (!$temInclusao) {
            return [];
        }
        $saida = array_keys(array_diff_key($inclui, $exclui));
        sort($saida);

        return $saida;
    }

    /**
     * Previa para a tela: total e os primeiros nomes.
     *
     * @param array<int, array<string, mixed>> $regras
     *
     * @return array{total: int, nomes: string[], mais: int}
     */
    public static function previa(array $regras): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $ids = self::resolverRegras($regras);
        $nomes = [];
        if ($ids !== []) {
            $it = $DB->request([
                'SELECT' => ['id', 'name', 'realname', 'firstname'],
                'FROM'   => 'glpi_users',
                'WHERE'  => ['id' => $ids],
                'ORDER'  => ['realname', 'firstname', 'name'],
                'LIMIT'  => self::PREVIA_NOMES,
            ]);
            foreach ($it as $u) {
                $nome = trim((string) $u['realname'] . ' ' . (string) $u['firstname']);
                $nomes[] = $nome !== '' ? $nome : (string) $u['name'];
            }
        }

        return ['total' => count($ids), 'nomes' => $nomes, 'mais' => max(0, count($ids) - count($nomes))];
    }

    /**
     * @param array{tipo: string, valor_id: int, valor_texto: string, incluir_filhos: int, is_exclusao: int} $r
     *
     * @return int[]
     */
    private static function usuariosDaRegra(array $r): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $ativos = ['glpi_users.is_active' => 1, 'glpi_users.is_deleted' => 0];
        $consulta = null;

        switch ($r['tipo']) {
            case 'todos':
                $consulta = ['SELECT' => 'glpi_users.id', 'FROM' => 'glpi_users', 'WHERE' => $ativos];
                break;
            case 'usuario':
                $consulta = ['SELECT' => 'glpi_users.id', 'FROM' => 'glpi_users', 'WHERE' => $ativos + ['glpi_users.id' => $r['valor_id']]];
                break;
            case 'grupo':
                $grupos = [$r['valor_id']];
                if ($r['incluir_filhos']) {
                    $grupos = array_values(array_unique(array_map('intval', array_merge($grupos, array_keys(getSonsOf('glpi_groups', $r['valor_id']))))));
                }
                $consulta = [
                    'SELECT'     => 'glpi_users.id',
                    'DISTINCT'   => true,
                    'FROM'       => 'glpi_users',
                    'INNER JOIN' => ['glpi_groups_users' => ['ON' => ['glpi_groups_users' => 'users_id', 'glpi_users' => 'id']]],
                    'WHERE'      => $ativos + ['glpi_groups_users.groups_id' => $grupos],
                ];
                break;
            case 'perfil':
                $consulta = [
                    'SELECT'     => 'glpi_users.id',
                    'DISTINCT'   => true,
                    'FROM'       => 'glpi_users',
                    'INNER JOIN' => ['glpi_profiles_users' => ['ON' => ['glpi_profiles_users' => 'users_id', 'glpi_users' => 'id']]],
                    'WHERE'      => $ativos + ['glpi_profiles_users.profiles_id' => $r['valor_id']],
                ];
                break;
            case 'entidade':
                $entidades = [$r['valor_id']];
                if ($r['incluir_filhos']) {
                    $entidades = array_values(array_unique(array_map('intval', array_merge($entidades, array_keys(getSonsOf('glpi_entities', $r['valor_id']))))));
                }
                $consulta = [
                    'SELECT'     => 'glpi_users.id',
                    'DISTINCT'   => true,
                    'FROM'       => 'glpi_users',
                    'INNER JOIN' => ['glpi_profiles_users' => ['ON' => ['glpi_profiles_users' => 'users_id', 'glpi_users' => 'id']]],
                    'WHERE'      => $ativos + ['glpi_profiles_users.entities_id' => $entidades],
                ];
                break;
            case 'vinculo':
            case 'empregador':
                $campo = $r['tipo'] === 'vinculo'
                    ? ['glpi_plugin_pessoasplus_colaboradores.vinculo' => $r['valor_texto']]
                    : ['glpi_plugin_pessoasplus_colaboradores.plugin_pessoasplus_empregadores_id' => $r['valor_id']];
                $consulta = [
                    'SELECT'     => 'glpi_users.id',
                    'DISTINCT'   => true,
                    'FROM'       => 'glpi_users',
                    'INNER JOIN' => ['glpi_plugin_pessoasplus_colaboradores' => ['ON' => ['glpi_plugin_pessoasplus_colaboradores' => 'users_id', 'glpi_users' => 'id']]],
                    'WHERE'      => $ativos + $campo + [
                        'glpi_plugin_pessoasplus_colaboradores.is_deleted' => 0,
                        'NOT' => ['glpi_plugin_pessoasplus_colaboradores.situacao' => 'desligado'],
                    ],
                ];
                break;
        }
        if ($consulta === null) {
            return [];
        }
        $ids = [];
        foreach ($DB->request($consulta) as $linha) {
            $ids[] = (int) $linha['id'];
        }

        return $ids;
    }

    // ----------------------------------------------------------------- opcoes do editor

    /**
     * Listas para montar as regras na tela.
     *
     * @return array<string, array<int, array{id: int|string, nome: string}>>
     */
    public static function opcoes(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $lista = static function (string $tabela, string $campo, array $where = []): array {
            global $DB;
            $saida = [];
            foreach ($DB->request(['SELECT' => ['id', $campo], 'FROM' => $tabela, 'WHERE' => $where, 'ORDER' => $campo]) as $l) {
                $saida[] = ['id' => (int) $l['id'], 'nome' => (string) $l[$campo]];
            }

            return $saida;
        };

        $vinculos = [];
        foreach (Colaborador::VINCULOS as $chave => $rotulo) {
            $vinculos[] = ['id' => $chave, 'nome' => $rotulo];
        }

        $usuarios = [];
        $it = $DB->request([
            'SELECT' => ['id', 'name', 'realname', 'firstname'],
            'FROM'   => 'glpi_users',
            'WHERE'  => ['is_active' => 1, 'is_deleted' => 0],
            'ORDER'  => ['realname', 'firstname', 'name'],
            'LIMIT'  => 2000,
        ]);
        foreach ($it as $u) {
            $nome = trim((string) $u['realname'] . ' ' . (string) $u['firstname']);
            $usuarios[] = ['id' => (int) $u['id'], 'nome' => ($nome !== '' ? $nome : (string) $u['name']) . ' (' . $u['name'] . ')'];
        }

        return [
            'grupo'      => $lista('glpi_groups', 'completename'),
            'perfil'     => $lista('glpi_profiles', 'name'),
            'entidade'   => $lista('glpi_entities', 'completename'),
            'usuario'    => $usuarios,
            'vinculo'    => $vinculos,
            'empregador' => Empregador::ativos(),
        ];
    }

    /**
     * Publicos salvos (modelos), com as regras rotuladas e o total.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function modelos(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_modelo' => 1], 'ORDER' => 'nome']);
        foreach ($it as $p) {
            $regras = self::regras((int) $p['id']);
            $saida[] = [
                'id'     => (int) $p['id'],
                'nome'   => (string) $p['nome'],
                'regras' => self::rotular($regras),
                'total'  => count(self::resolverRegras($regras)),
                'url'    => self::getFormURLWithID((int) $p['id']),
            ];
        }

        return $saida;
    }
}
