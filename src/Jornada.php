<?php

/**
 * Pessoas+ - jornada do colaborador em 6 trechos (Figura 2 da proposta, D-67).
 *
 * PESSOASPLUS_BUILD_BM1_2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

/**
 * Monta a estrada da pasta: um marco por trecho, com os eventos da trilha
 * (Evento) distribuidos por modulo, trecho atual pela situacao da ficha e
 * trechos nao alcancados em cinza. Sem tabela propria: le a trilha (D-44).
 */
final class Jornada
{
    /** Trechos na ordem da estrada: chave => [rotulo, icone, descricao]. */
    public const TRECHOS = [
        'chegada'     => ['Chegada', 'ti ti-door-enter', 'Pré-cadastro, admissão, primeiro dia'],
        'integracao'  => ['Integração', 'ti ti-list-check', 'Checklist, normativas de admissão, ciências iniciais'],
        'dia_a_dia'   => ['Dia a dia', 'ti ti-sun', 'Comunicados, mural, reconhecimentos, treinamentos'],
        'pausas'      => ['Pausas', 'ti ti-beach', 'Férias e afastamentos'],
        'movimentacao' => ['Movimentação', 'ti ti-arrows-exchange', 'Transferência de setor, mudança de cargo ou gestor'],
        'saida'       => ['Saída', 'ti ti-door-exit', 'Nada consta, desligamento'],
    ];

    /** Modulo da trilha => trecho. Acao especifica pode sobrescrever. */
    private const POR_MODULO = [
        'fichario'      => 'chegada',
        'chegadas'      => 'integracao',
        'comunicados'   => 'dia_a_dia',
        'mural'         => 'dia_a_dia',
        'pesquisas'     => 'dia_a_dia',
        'registro'      => 'dia_a_dia',
        'desempenho'    => 'dia_a_dia',
        'ferias'        => 'pausas',
        'transferencias' => 'movimentacao',
        'desligamentos' => 'saida',
        'cautela'       => 'dia_a_dia',
    ];

    private const POR_ACAO = [
        'ficha_alterada' => 'dia_a_dia',
        'ficha_excluida' => 'saida',
    ];

    /**
     * @param array<string, mixed>              $ficha   Colaborador::paraTela()
     * @param array<int, array<string, mixed>>  $eventos Evento::doColaborador()
     *
     * @return array{trechos: array<int, array<string, mixed>>, atual: string}
     */
    public static function montar(array $ficha, array $eventos): array
    {
        $atual   = self::trechoAtual($ficha);
        $chaves  = array_keys(self::TRECHOS);
        $posAtual = (int) array_search($atual, $chaves, true);

        $porTrecho = array_fill_keys($chaves, []);
        foreach ($eventos as $e) {
            $trecho = self::POR_ACAO[$e['acao']] ?? self::POR_MODULO[$e['modulo']] ?? 'dia_a_dia';
            $porTrecho[$trecho][] = $e;
        }

        $saida = [];
        foreach ($chaves as $i => $chave) {
            [$rotulo, $icone, $descricao] = self::TRECHOS[$chave];
            $saida[] = [
                'chave'     => $chave,
                'rotulo'    => $rotulo,
                'icone'     => $icone,
                'descricao' => $descricao,
                'alcancado' => $i <= $posAtual || $porTrecho[$chave] !== [],
                'atual'     => $chave === $atual,
                'eventos'   => $porTrecho[$chave],
                'total'     => count($porTrecho[$chave]),
                // Posicao do marco na estrada (viewBox 1000x260), em S suave.
                'x'         => 90 + $i * 164,
                'y'         => $i % 2 === 0 ? 150 : 95,
            ];
        }

        return ['trechos' => $saida, 'atual' => $atual];
    }

    /**
     * @param array<string, mixed> $ficha
     */
    public static function trechoAtual(array $ficha): string
    {
        switch ($ficha['situacao']) {
            case 'desligado':
            case 'em_desligamento':
                return 'saida';
            case 'afastado':
                return 'pausas';
            case 'pre_cadastro':
                return 'chegada';
        }
        // Ativo: nos primeiros 30 dias ainda esta chegando/integrando.
        $inicio = (string) ($ficha['data_inicio'] ?? '');
        if ($inicio !== '') {
            $dias = (int) floor((time() - strtotime($inicio)) / 86400);
            if ($dias < 0) {
                return 'chegada';
            }
            if ($dias <= 30) {
                return 'integracao';
            }
        }

        return 'dia_a_dia';
    }
}
