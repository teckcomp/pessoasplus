<?php

/**
 * Pessoas+ - trilha de eventos (D-44), somente inclusao.
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
 * Cada modulo registra aqui o que aconteceu (quem, quando, em que interface,
 * sobre que item e que colaborador). Nada e alterado nem apagado pela
 * aplicacao: a tabela so cresce. E a base do historico da pasta do
 * Fichario e da jornada (BJ.1).
 */
final class Evento extends CommonDBTM
{
    public static $rightname = 'pessoasplus_fichario';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_eventos';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Evento';
    }

    /** Rotulos por acao, para a tela. Acao desconhecida mostra a chave. */
    public const ROTULOS = [
        'ficha_criada'       => 'Ficha criada',
        'ficha_alterada'     => 'Ficha alterada',
        'ficha_vinculada'    => 'Ficha ligada a um usuário do GLPI',
        'ficha_excluida'     => 'Ficha excluída',
        'publico_criado'     => 'Público criado',
        'publico_alterado'   => 'Público alterado',
        'publico_excluido'   => 'Público excluído',
        'comunicado_criado'      => 'Comunicado criado',
        'comunicado_publicado'   => 'Comunicado publicado',
        'comunicado_nova_versao' => 'Nova versão publicada',
        'comunicado_revogado'    => 'Comunicado revogado',
        'comunicado_excluido'    => 'Rascunho excluído',
        'leitura_registrada'     => 'Leitura registrada',
        'ciencia_confirmada'     => 'Ciência confirmada',
        'ciencia_nao_concorda'   => 'Ciência registrada com "Não concordo"',
        'publicacao_criada'      => 'Publicação criada',
        'publicacao_publicada'   => 'Publicação no mural',
        'publicacao_despublicada' => 'Publicação tirada do mural',
        'publicacao_excluida'    => 'Publicação excluída',
    ];

    /**
     * Grava um evento. Nunca lanca excecao para a tela: falha de trilha
     * vai para o log do GLPI e a acao principal segue.
     *
     * @param string               $modulo        chave do modulo (fichario, comunicados, mural, ...)
     * @param string               $acao          chave da acao (ver ROTULOS)
     * @param CommonDBTM|null      $item          item sobre o qual aconteceu
     * @param int                  $colaboradorId ficha ligada ao evento (0 = nenhuma)
     * @param array<string, mixed> $detalhes      o que mudou, em JSON
     */
    public static function registrar(
        string $modulo,
        string $acao,
        ?CommonDBTM $item = null,
        int $colaboradorId = 0,
        array $detalhes = []
    ): int {
        $evento = new self();
        $id     = $evento->add([
            'modulo'                              => mb_substr($modulo, 0, 30),
            'acao'                                => mb_substr($acao, 0, 50),
            'itemtype'                            => $item === null ? '' : $item::class,
            'items_id'                            => $item === null ? 0 : (int) $item->getID(),
            'plugin_pessoasplus_colaboradores_id' => $colaboradorId,
            'users_id'                            => (int) (Session::getLoginUserID() ?: 0),
            'interface'                           => Session::getCurrentInterface() === 'helpdesk' ? 'helpdesk' : 'central',
            'data'                                => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
            'detalhes'                            => json_encode($detalhes, JSON_UNESCAPED_UNICODE),
        ]);

        return $id === false ? 0 : (int) $id;
    }

    /**
     * Eventos de um colaborador, do mais recente para o mais antigo,
     * prontos para o template (toda chave existe, T-06).
     *
     * @return array<int, array{id: int, data: string, modulo: string, acao: string, rotulo: string, autor: string, interface: string, detalhes: array<string, mixed>}>
     */
    public static function doColaborador(int $colaboradorId, int $limite = 200): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $it = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['plugin_pessoasplus_colaboradores_id' => $colaboradorId],
            'ORDER' => ['data DESC', 'id DESC'],
            'LIMIT' => $limite,
        ]);
        foreach ($it as $e) {
            $detalhes = json_decode((string) $e['detalhes'], true);
            $saida[] = [
                'id'        => (int) $e['id'],
                'data'      => (string) $e['data'],
                'modulo'    => (string) $e['modulo'],
                'acao'      => (string) $e['acao'],
                'rotulo'    => self::ROTULOS[$e['acao']] ?? (string) $e['acao'],
                'autor'     => (int) $e['users_id'] > 0 ? (string) getUserName((int) $e['users_id']) : 'Sistema',
                'interface' => (string) $e['interface'],
                'detalhes'  => is_array($detalhes) ? $detalhes : [],
            ];
        }

        return $saida;
    }

    /** Bloqueia alteracao e exclusao pela aplicacao: somente inclusao. */
    public function canUpdateItem(): bool
    {
        return false;
    }

    public function canPurgeItem(): bool
    {
        return false;
    }

    public function canDeleteItem(): bool
    {
        return false;
    }
}
