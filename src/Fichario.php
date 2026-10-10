<?php

/**
 * Pessoas+ - Fichario: grade de pastas por setor (D-34, D-35, D-36).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use DBmysql;
use Session;
use User;

/**
 * O Fichario e uma visao sobre as fichas, sem tabela propria. Esta classe
 * monta a grade: filtra pelo escopo do papel (RH tudo; gestor so os grupos
 * que gere; colaborador so a propria pasta) e agrupa por setor.
 */
final class Fichario
{
    public const SEM_SETOR = 'Sem setor definido';

    /**
     * Filtros aceitos pela tela: busca livre, setor (groups_id), situacao e vinculo.
     *
     * @param array<string, mixed> $get
     *
     * @return array{busca: string, setor: int, situacao: string, vinculo: string}
     */
    public static function filtros(array $get): array
    {
        $situacao = (string) ($get['situacao'] ?? '');
        $vinculo  = (string) ($get['vinculo'] ?? '');

        return [
            'busca'    => mb_substr(trim((string) ($get['busca'] ?? '')), 0, 100),
            'setor'    => max(0, (int) ($get['setor'] ?? 0)),
            'situacao' => isset(Colaborador::SITUACOES[$situacao]) ? $situacao : '',
            'vinculo'  => isset(Colaborador::VINCULOS[$vinculo]) ? $vinculo : '',
        ];
    }

    /**
     * Escopo do papel: null = tudo (RH); lista de grupos (gestor);
     * ['proprio' => id] (colaborador). Vazio = nada.
     *
     * @return array{tudo: bool, grupos: int[], users_id: int}
     */
    public static function escopo(): array
    {
        $eu = (int) Session::getLoginUserID();
        if (Permissoes::le('fichario')) {
            return ['tudo' => true, 'grupos' => [], 'users_id' => 0];
        }
        $grupos = Permissoes::gestor() ? Colaborador::gruposGeridos($eu) : [];

        return ['tudo' => false, 'grupos' => $grupos, 'users_id' => $eu];
    }

    /**
     * Grade agrupada por setor, ja filtrada.
     *
     * @param array{busca: string, setor: int, situacao: string, vinculo: string} $filtros
     *
     * @return array{setores: array<int, array{id: int, nome: string, pastas: array<int, array<string, mixed>>}>, total: int, resumo: array<string, int>}
     */
    public static function grade(array $filtros): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $tabela = Colaborador::getTable();
        $where  = ["$tabela.is_deleted" => 0];

        $escopo = self::escopo();
        if (!$escopo['tudo']) {
            $ou = [];
            if ($escopo['users_id'] > 0) {
                $ou[] = ["$tabela.users_id" => $escopo['users_id']];
            }
            if ($escopo['grupos'] !== []) {
                $ou[] = ["$tabela.groups_id" => $escopo['grupos']];
            }
            // Sem escopo nenhum: filtro que nao some (T-10).
            $where[] = $ou === [] ? ["$tabela.id" => 0] : ['OR' => $ou];
        }
        if ($filtros['setor'] > 0) {
            $where["$tabela.groups_id"] = $filtros['setor'];
        }
        if ($filtros['situacao'] !== '') {
            $where["$tabela.situacao"] = $filtros['situacao'];
        }
        if ($filtros['vinculo'] !== '') {
            $where["$tabela.vinculo"] = $filtros['vinculo'];
        }
        if ($filtros['busca'] !== '') {
            $like = '%' . $filtros['busca'] . '%';
            $where[] = ['OR' => [
                ["$tabela.nome" => ['LIKE', $like]],
                ["$tabela.email" => ['LIKE', $like]],
                ["$tabela.matricula" => ['LIKE', $like]],
                ['glpi_groups.completename' => ['LIKE', $like]],
                ['glpi_plugin_pessoasplus_cargos.name' => ['LIKE', $like]],
            ]];
        }

        $it = $DB->request([
            'SELECT'    => [
                "$tabela.*",
                'glpi_groups.completename AS setor_nome',
                'glpi_plugin_pessoasplus_cargos.name AS cargo_nome',
                'glpi_users.picture AS foto_arquivo',
                'glpi_users.name AS usuario_login',
            ],
            'FROM'      => $tabela,
            'LEFT JOIN' => [
                'glpi_groups'                  => ['ON' => ['glpi_groups' => 'id', $tabela => 'groups_id']],
                'glpi_plugin_pessoasplus_cargos' => ['ON' => ['glpi_plugin_pessoasplus_cargos' => 'id', $tabela => 'plugin_pessoasplus_cargos_id']],
                'glpi_users'                   => ['ON' => ['glpi_users' => 'id', $tabela => 'users_id']],
            ],
            'WHERE'     => $where,
            'ORDER'     => ['glpi_groups.completename', "$tabela.nome"],
        ]);

        $setores = [];
        $resumo  = array_fill_keys(array_keys(Colaborador::SITUACOES), 0);
        $total   = 0;
        foreach ($it as $linha) {
            $sit     = Colaborador::SITUACOES[$linha['situacao']] ?? [$linha['situacao'], 'neutro'];
            $setorId = (int) $linha['groups_id'];
            $setorNome = (string) ($linha['setor_nome'] ?? '');
            if ($setorNome === '') {
                $setorId   = 0;
                $setorNome = self::SEM_SETOR;
            }
            if (!isset($setores[$setorId])) {
                $setores[$setorId] = ['id' => $setorId, 'nome' => $setorNome, 'pastas' => []];
            }
            $setores[$setorId]['pastas'][] = [
                'id'              => (int) $linha['id'],
                'nome'            => (string) $linha['nome'],
                'iniciais'        => Colaborador::iniciais((string) $linha['nome']),
                'cargo'           => (string) ($linha['cargo_nome'] ?? ''),
                'vinculo_rotulo'  => Colaborador::VINCULOS[$linha['vinculo']] ?? (string) $linha['vinculo'],
                'situacao'        => (string) $linha['situacao'],
                'situacao_rotulo' => $sit[0],
                'situacao_tom'    => $sit[1],
                'foto'            => (int) $linha['users_id'] > 0 ? (string) User::getThumbnailURLForPicture($linha['foto_arquivo'] ?? null) : '',
                'sem_usuario'     => (int) $linha['users_id'] === 0,
                'url'             => Colaborador::getFormURLWithID((int) $linha['id']),
            ];
            $resumo[$linha['situacao']] = ($resumo[$linha['situacao']] ?? 0) + 1;
            $total++;
        }

        // "Sem setor" por ultimo.
        $lista = array_values($setores);
        usort($lista, static fn ($a, $b) => ($a['id'] === 0) <=> ($b['id'] === 0) ?: strcasecmp($a['nome'], $b['nome']));

        return ['setores' => $lista, 'total' => $total, 'resumo' => $resumo];
    }

    /**
     * Setores (grupos) que tem pelo menos uma ficha no escopo, para o filtro.
     *
     * @return array<int, array{id: int, nome: string}>
     */
    public static function setoresComFicha(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $tabela = Colaborador::getTable();
        $where  = ["$tabela.is_deleted" => 0, "$tabela.groups_id" => ['>', 0]];
        $escopo = self::escopo();
        if (!$escopo['tudo']) {
            $where["$tabela.groups_id"] = $escopo['grupos'] === [] ? [0] : $escopo['grupos'];
        }
        $saida = [];
        $it = $DB->request([
            'SELECT'     => ['glpi_groups.id', 'glpi_groups.completename'],
            'DISTINCT'   => true,
            'FROM'       => $tabela,
            'INNER JOIN' => ['glpi_groups' => ['ON' => ['glpi_groups' => 'id', $tabela => 'groups_id']]],
            'WHERE'      => $where,
            'ORDER'      => 'glpi_groups.completename',
        ]);
        foreach ($it as $g) {
            $saida[] = ['id' => (int) $g['id'], 'nome' => (string) $g['completename']];
        }

        return $saida;
    }

    /**
     * Grupos e usuarios para os selects do formulario da ficha.
     *
     * @return array{grupos: array<int, array{id: int, nome: string}>, usuarios: array<int, array{id: int, nome: string}>, gestores_por_grupo: array<int, int>}
     */
    public static function opcoesFormulario(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $grupos = [];
        foreach ($DB->request(['SELECT' => ['id', 'completename'], 'FROM' => 'glpi_groups', 'ORDER' => 'completename']) as $g) {
            $grupos[] = ['id' => (int) $g['id'], 'nome' => (string) $g['completename']];
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
            $nome = (string) formatUserName((int) $u['id'], (string) $u['name'], (string) $u['realname'], (string) $u['firstname']);
            $usuarios[] = ['id' => (int) $u['id'], 'nome' => $nome . ' (' . $u['name'] . ')'];
        }
        // Primeiro gestor (is_manager) de cada grupo, para sugerir ao escolher o setor.
        $gestores = [];
        $it = $DB->request([
            'SELECT' => ['groups_id', 'users_id'],
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => ['is_manager' => 1],
            'ORDER'  => ['groups_id', 'id'],
        ]);
        foreach ($it as $gu) {
            $gestores[(int) $gu['groups_id']] ??= (int) $gu['users_id'];
        }

        return ['grupos' => $grupos, 'usuarios' => $usuarios, 'gestores_por_grupo' => $gestores];
    }
}
