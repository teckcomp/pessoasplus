<?php

/**
 * Pessoas+ - dados ficticios de "Chegadas e checklists" (casca, BP.4a).
 *
 * PESSOASPLUS_BUILD_BP4A
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

use DateTimeImmutable;

/**
 * Movimentacoes (chegadas e transferencias) e modelos de checklist da
 * demonstracao, contando a mesma historia das Telas 4 e 5 da proposta:
 * Lucas Ferreira chega em 01/10 com 4 de 7 itens e 1 atrasado.
 *
 * Como o checklist e montado (proposta D-65, a confirmar):
 * - a movimentacao recebe TODOS os modelos cuja regra casa com a pessoa
 *   (vinculo, setor, empregador, entidade, cargo);
 * - cada necessidade de TI marcada no assistente (Tela 4) vira um chamado
 *   nativo e um item que se conclui quando o chamado e solucionado (I-04);
 * - o item "Ciencia das normativas" se conclui quando a pessoa confirma
 *   todas as normativas atribuidas (I-01).
 *
 * O prazo de cada item e relativo a data de inicio (negativo = antes).
 * A situacao e calculada contra HOJE, para a casca contar a mesma
 * historia em qualquer data. Nenhum item guarda dado de saude.
 */
final class DemoChegadas
{
    /** Data de referencia: o dia em que Lucas Ferreira comeca (Tela 5). */
    public const HOJE = '2026-10-01';

    /** Itens vencendo dentro desta janela contam como "vencem em breve". */
    public const JANELA_DIAS = 7;

    /**
     * Situacao de um item: chave => [rotulo do selo, tom].
     */
    public const SITUACOES_ITEM = [
        'feito'    => ['Feito', 'ok'],
        'auto'     => ['Auto', 'info'],
        'atrasado' => ['Atrasado', 'atraso'],
        'hoje'     => ['Vence hoje', 'alerta'],
        'aberto'   => ['Aberto', 'neutro'],
    ];

    /**
     * Modelos de checklist: chave => dados. 'itens' usa a mesma forma dos
     * itens das movimentacoes, sem situacao.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function modelos(): array
    {
        return [
            'base_clt' => [
                'nome'      => 'Chegada · base CLT',
                'tipo'      => 'Chegada',
                'ativo'     => true,
                'regra'     => [['Vínculo', 'CLT'], ['Empregador', 'Resolutto'], ['Setor', 'qualquer']],
                'uso'       => 14,
                'nota'      => '',
                'itens'     => [
                    self::itemModelo('Contrato e documentos', 'RH', -5, true, 'contrato assinado (anexo)', 'manual'),
                    self::itemModelo('Integração com o gestor', 'Gestor do setor', 1, true, '', 'manual'),
                    self::itemModelo('Ciência das normativas de admissão', 'Colaborador', 7, true, '', 'ciencias'),
                ],
            ],
            'campo' => [
                'nome'      => 'Chegada · equipe de campo',
                'tipo'      => 'Chegada',
                'ativo'     => true,
                'regra'     => [['Vínculo', 'CLT'], ['Setor', 'Campo Norte, Campo Sul']],
                'uso'       => 9,
                'nota'      => 'Soma-se à base CLT para quem vai para o campo.',
                'itens'     => [
                    self::itemModelo('Separar EPI e crachá', 'Almoxarifado', -1, true, 'termo de entrega assinado (anexo)', 'manual'),
                ],
            ],
            'pj' => [
                'nome'      => 'Chegada · prestador PJ',
                'tipo'      => 'Chegada',
                'ativo'     => true,
                'regra'     => [['Vínculo', 'PJ'], ['Setor', 'qualquer']],
                'uso'       => 3,
                'nota'      => 'Itens de prestador PJ a validar com o jurídico (risco de caracterização de vínculo, seção 8 da proposta).',
                'itens'     => [
                    self::itemModelo('Contrato de prestação de serviços', 'RH', -5, true, 'contrato assinado (anexo)', 'manual'),
                    self::itemModelo('Apresentação ao responsável pelo contrato', 'Gestor do setor', 1, true, '', 'manual'),
                    self::itemModelo('Ciência das normas aplicáveis a prestadores', 'Colaborador', 7, true, '', 'ciencias'),
                ],
            ],
            'transferencia' => [
                'nome'      => 'Transferência entre setores',
                'tipo'      => 'Transferência',
                'ativo'     => true,
                'regra'     => [['Movimentação', 'transferência'], ['Vínculo', 'CLT']],
                'uso'       => 6,
                'nota'      => 'O prazo conta da data em que a transferência vale.',
                'itens'     => [
                    self::itemModelo('Comunicar a transferência à pessoa', 'RH', -10, true, 'comunicado com ciência', 'manual'),
                    self::itemModelo('Ajustar escala de plantão', 'Gestor de destino', -5, true, '', 'manual'),
                    self::itemModelo('Conferir itens em cautela do setor de origem', 'Almoxarifado', -5, true, '', 'manual'),
                    self::itemModelo('Transferir chamados em aberto', 'Gestor de origem', -3, true, '', 'manual'),
                    self::itemModelo('Atualizar veículo e rota', 'Frota', -3, false, '', 'manual'),
                    self::itemModelo('Ciência das normas do novo setor', 'Colaborador', 7, true, '', 'ciencias'),
                ],
            ],
            'aprendiz' => [
                'nome'      => 'Chegada · jovem aprendiz',
                'tipo'      => 'Chegada',
                'ativo'     => false,
                'regra'     => [],
                'uso'       => 0,
                'nota'      => 'Rascunho sem regra: não é aplicado a ninguém.',
                'itens'     => [
                    self::itemModelo('Termo de compromisso com a instituição formadora', 'RH', -5, true, 'termo assinado (anexo)', 'manual'),
                    self::itemModelo('Integração com o gestor', 'Gestor do setor', 1, true, '', 'manual'),
                ],
            ],
        ];
    }

    /**
     * Quem recebe o que: exemplos de perfil e os modelos que casam.
     *
     * @return array<int, array{perfil: string, modelos: string[], extra: string}>
     */
    public static function exemplosRegra(): array
    {
        return [
            ['perfil' => 'CLT · Campo Norte', 'modelos' => ['base_clt', 'campo'], 'extra' => '+ itens das necessidades de TI'],
            ['perfil' => 'CLT · Suporte N1', 'modelos' => ['base_clt'], 'extra' => '+ itens das necessidades de TI'],
            ['perfil' => 'PJ · Projetos', 'modelos' => ['pj'], 'extra' => '+ itens das necessidades de TI'],
            ['perfil' => 'Transferência · CLT', 'modelos' => ['transferencia'], 'extra' => ''],
        ];
    }

    /**
     * Movimentacoes da demonstracao. 'itens' so existe fora do rascunho.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function movimentacoesBrutas(): array
    {
        return [
            1 => [
                'tipo'       => 'chegada',
                'pessoa'     => 'Lucas Ferreira',
                'cargo'      => 'Técnico de campo',
                'setor'      => 'Campo Norte',
                'origem'     => '',
                'vinculo'    => 'CLT',
                'empregador' => 'Resolutto',
                'gestor'     => 'Renata Costa',
                'inicio'     => '2026-10-01',
                'rascunho'   => false,
                'concluida'  => '',
                'modelos'    => ['base_clt', 'campo'],
                'chamados'   => [
                    ['numero' => '18.501', 'titulo' => 'Notebook', 'categoria' => 'Requisição › Equipamentos', 'situacao' => 'Solucionado'],
                    ['numero' => '18.502', 'titulo' => 'Conta de rede e e-mail', 'categoria' => 'Requisição › Acessos', 'situacao' => 'Solucionado'],
                    ['numero' => '18.503', 'titulo' => 'Acesso ao GLPI · perfil Técnico de campo', 'categoria' => 'Requisição › Acessos', 'situacao' => 'Solucionado'],
                ],
                'normativas' => [
                    ['codigo' => 'REG0001:03', 'titulo' => 'Regimento interno', 'confirmada' => '29/09 às 16:42'],
                    ['codigo' => 'POL0002:01', 'titulo' => 'Código de conduta', 'confirmada' => ''],
                    ['codigo' => 'NOR0004:02', 'titulo' => 'Uso de veículos da frota', 'confirmada' => ''],
                ],
                'muda'       => [],
                'itens'      => [
                    self::item('Contrato e documentos', 'RH', -5, 'base_clt', 'feito', '25/09 · Gestão de RH', 'contrato assinado (anexo)'),
                    self::item('Criar conta e e-mail', 'TI', -3, 'ti', 'feito', '27/09 · chamado #18.502 solucionado', ''),
                    self::item('Preparar notebook', 'TI', -2, 'ti', 'feito', '29/09 · chamado #18.501 solucionado', ''),
                    self::item('Acesso ao GLPI · perfil Técnico de campo', 'TI', -1, 'ti', 'feito', '29/09 · chamado #18.503 solucionado', ''),
                    self::item('Separar EPI e crachá', 'Almoxarifado', -1, 'campo', 'aberto', '', 'termo de entrega assinado (anexo)'),
                    self::item('Ciência das normativas de admissão', 'Colaborador', 7, 'base_clt', 'auto', '1 de 3 confirmadas', ''),
                    self::item('Integração com o gestor', 'Gestor · Renata Costa', 1, 'base_clt', 'aberto', '', ''),
                ],
            ],
            2 => [
                'tipo'       => 'chegada',
                'pessoa'     => 'Bruno Teixeira',
                'cargo'      => 'Técnico N2',
                'setor'      => 'Suporte N1',
                'origem'     => '',
                'vinculo'    => 'CLT',
                'empregador' => 'Resolutto',
                'gestor'     => 'Sérgio Lopes',
                'inicio'     => '2026-10-13',
                'rascunho'   => true,
                'etapa'      => 2,
                'parado'     => 3,
                'concluida'  => '',
                'modelos'    => [],
                'chamados'   => [],
                'normativas' => [],
                'muda'       => [],
            ],
            3 => [
                'tipo'       => 'transferencia',
                'pessoa'     => 'Juliana Prado',
                'cargo'      => 'Técnica de campo',
                'setor'      => 'Campo Norte',
                'origem'     => 'Campo Sul',
                'vinculo'    => 'CLT',
                'empregador' => 'Resolutto',
                'gestor'     => 'Renata Costa',
                'inicio'     => '2026-10-05',
                'rascunho'   => false,
                'concluida'  => '',
                'modelos'    => ['transferencia'],
                'chamados'   => [],
                'normativas' => [
                    ['codigo' => 'NOR0014:00', 'titulo' => 'Plantão do Campo Norte', 'confirmada' => '', 'nota' => 'fica pendente em 05/10'],
                    ['codigo' => 'NOR0004:02', 'titulo' => 'Uso de veículos da frota', 'confirmada' => '05/03 às 09:02', 'nota' => 'revisão já confirmada, não reabre'],
                ],
                'muda'       => [
                    ['rotulo' => 'Grupo no GLPI', 'de' => 'Campo Sul', 'para' => 'Campo Norte'],
                    ['rotulo' => 'Gestor', 'de' => 'Paulo Mendes', 'para' => 'Renata Costa'],
                    ['rotulo' => 'Comunicados do setor', 'de' => 'Campo Sul', 'para' => 'Campo Norte'],
                ],
                'itens'      => [
                    self::item('Comunicar a transferência à pessoa', 'RH', -10, 'transferencia', 'feito', '24/09 · Gestão de RH', 'comunicado com ciência'),
                    self::item('Ajustar escala de plantão', 'Gestor de destino · Renata Costa', -5, 'transferencia', 'feito', '29/09 · Renata Costa', ''),
                    self::item('Conferir itens em cautela do setor de origem', 'Almoxarifado', -5, 'transferencia', 'feito', '30/09 · Almoxarifado', ''),
                    self::item('Transferir chamados em aberto', 'Gestor de origem · Paulo Mendes', -3, 'transferencia', 'feito', '30/09 · Paulo Mendes', ''),
                    self::item('Atualizar veículo e rota', 'Frota', -3, 'transferencia', 'feito', '01/10 · Frota', ''),
                    self::item('Ciência das normas do Campo Norte', 'Colaborador', 7, 'transferencia', 'auto', 'abre em 05/10, quando a transferência vale', ''),
                ],
            ],
            4 => [
                'tipo'       => 'transferencia',
                'pessoa'     => 'Marcos Dias',
                'cargo'      => 'Técnico de campo',
                'setor'      => 'Campo Norte',
                'origem'     => 'Suporte N1',
                'vinculo'    => 'CLT',
                'empregador' => 'Resolutto',
                'gestor'     => 'Renata Costa',
                'inicio'     => '2026-09-15',
                'rascunho'   => false,
                'concluida'  => '18/09',
                'modelos'    => ['transferencia'],
                'chamados'   => [],
                'normativas' => [
                    ['codigo' => 'NOR0014:00', 'titulo' => 'Plantão do Campo Norte', 'confirmada' => '16/09 às 08:10', 'nota' => ''],
                    ['codigo' => 'NOR0009:00', 'titulo' => 'Segurança em campo', 'confirmada' => '18/09 às 07:55', 'nota' => ''],
                ],
                'muda'       => [
                    ['rotulo' => 'Grupo no GLPI', 'de' => 'Suporte N1', 'para' => 'Campo Norte'],
                    ['rotulo' => 'Gestor', 'de' => 'Sérgio Lopes', 'para' => 'Renata Costa'],
                ],
                'itens'      => [
                    self::item('Comunicar a transferência à pessoa', 'RH', -10, 'transferencia', 'feito', '04/09 · Gestão de RH', 'comunicado com ciência'),
                    self::item('Ajustar escala de plantão', 'Gestor de destino · Renata Costa', -5, 'transferencia', 'feito', '09/09 · Renata Costa', ''),
                    self::item('Conferir itens em cautela do setor de origem', 'Almoxarifado', -5, 'transferencia', 'feito', '10/09 · Almoxarifado', ''),
                    self::item('Transferir chamados em aberto', 'Gestor de origem · Sérgio Lopes', -3, 'transferencia', 'feito', '11/09 · Sérgio Lopes', ''),
                    self::item('Atualizar veículo e rota', 'Frota', -3, 'transferencia', 'feito', '12/09 · Frota', ''),
                    self::item('Ciência das normas do Campo Norte', 'Colaborador', 7, 'transferencia', 'feito', '18/09 · 2 de 2 confirmadas', ''),
                ],
            ],
            5 => [
                'tipo'       => 'chegada',
                'pessoa'     => 'Felipe Andrade',
                'cargo'      => 'Analista administrativo',
                'setor'      => 'Administrativo',
                'origem'     => '',
                'vinculo'    => 'CLT',
                'empregador' => 'Resolutto',
                'gestor'     => 'Cláudia Ramos',
                'inicio'     => '2026-09-01',
                'rascunho'   => false,
                'concluida'  => '05/09',
                'modelos'    => ['base_clt'],
                'chamados'   => [
                    ['numero' => '18.214', 'titulo' => 'Notebook', 'categoria' => 'Requisição › Equipamentos', 'situacao' => 'Solucionado'],
                    ['numero' => '18.215', 'titulo' => 'Conta de rede e e-mail', 'categoria' => 'Requisição › Acessos', 'situacao' => 'Solucionado'],
                ],
                'normativas' => [
                    ['codigo' => 'REG0001:03', 'titulo' => 'Regimento interno', 'confirmada' => '01/09 às 10:15'],
                    ['codigo' => 'POL0002:01', 'titulo' => 'Código de conduta', 'confirmada' => '02/09 às 09:30'],
                ],
                'muda'       => [],
                'itens'      => [
                    self::item('Contrato e documentos', 'RH', -5, 'base_clt', 'feito', '26/08 · Gestão de RH', 'contrato assinado (anexo)'),
                    self::item('Criar conta e e-mail', 'TI', -3, 'ti', 'feito', '28/08 · chamado #18.215 solucionado', ''),
                    self::item('Preparar notebook', 'TI', -2, 'ti', 'feito', '29/08 · chamado #18.214 solucionado', ''),
                    self::item('Integração com o gestor', 'Gestor · Cláudia Ramos', 1, 'base_clt', 'feito', '02/09 · Cláudia Ramos', ''),
                    self::item('Ciência das normativas de admissão', 'Colaborador', 7, 'base_clt', 'feito', '02/09 · 2 de 2 confirmadas', ''),
                ],
            ],
        ];
    }

    /**
     * Lista da area "Chegadas e checklists" (aba Movimentacoes) e dados da
     * aba Modelos.
     *
     * @return array<string, mixed>
     */
    public static function area(): array
    {
        $linhas  = [];
        $prazos  = [];
        $resumo  = ['chegadas' => 0, 'rascunhos' => 0, 'transferencias' => 0, 'atrasados' => 0, 'em_breve' => 0];
        $filtros = ['andamento' => 0, 'atraso' => 0, 'rascunho' => 0, 'concluida' => 0];

        foreach (self::movimentacoesBrutas() as $id => $bruta) {
            $mov   = self::montar($id, $bruta);
            $fase  = $mov['fase'];
            $chave = $fase;
            if ($mov['atrasados'] > 0) {
                $chave .= ' atraso';
            }
            $filtros[$fase]++;
            if ($mov['atrasados'] > 0) {
                $filtros['atraso']++;
            }
            if ($fase !== 'concluida') {
                $resumo[$mov['tipo'] === 'chegada' ? 'chegadas' : 'transferencias']++;
            }
            if ($fase === 'rascunho') {
                $resumo['rascunhos']++;
            }
            $resumo['atrasados'] += $mov['atrasados'];
            $resumo['em_breve']  += $mov['em_breve'];

            foreach ($mov['itens'] as $item) {
                if (in_array($item['situacao'], ['atrasado', 'hoje', 'aberto'], true)) {
                    $prazos[] = [
                        'ordem'       => $item['data_iso'],
                        'titulo'      => $item['titulo'],
                        'pessoa'      => $mov['pessoa'],
                        'tipo'        => $mov['tipo_rotulo'],
                        'responsavel' => $item['responsavel'],
                        'data'        => $item['data'],
                        'selo'        => $item['situacao'] === 'aberto' ? 'Até ' . $item['data'] : $item['selo'],
                        'tom'         => $item['situacao'] === 'aberto' ? 'neutro' : $item['tom'],
                        'mov'         => $id,
                    ];
                }
            }
            if ($fase === 'rascunho') {
                $limite   = self::somarDias($bruta['inicio'], -7);
                $prazos[] = [
                    'ordem'       => $limite->format('Y-m-d'),
                    'titulo'      => 'Concluir o assistente de chegada',
                    'pessoa'      => $mov['pessoa'],
                    'tipo'        => $mov['tipo_rotulo'],
                    'responsavel' => 'RH · parado na etapa ' . $bruta['etapa'] . ' há ' . $bruta['parado'] . ' dias, para os chamados de TI saírem a tempo',
                    'data'        => $limite->format('d/m'),
                    'selo'        => 'Até ' . $limite->format('d/m'),
                    'tom'         => 'info',
                    'mov'         => 0,
                ];
            }
            $linhas[] = $mov + ['filtro' => $chave];
        }

        usort($prazos, static fn (array $a, array $b): int => strcmp($a['ordem'], $b['ordem']));

        $modelos = [];
        foreach (self::modelos() as $chave => $m) {
            $modelos[] = [
                'chave' => $chave,
                'nome'  => $m['nome'],
                'tipo'  => $m['tipo'],
                'ativo' => $m['ativo'],
                'regra' => array_map(static fn (array $r): array => ['campo' => $r[0], 'valor' => $r[1]], $m['regra']),
                'uso'   => $m['uso'],
                'nota'  => $m['nota'],
                'itens' => $m['itens'],
            ];
        }

        $nomes    = array_map(static fn (array $m): string => $m['nome'], self::modelos());
        $exemplos = [];
        foreach (self::exemplosRegra() as $e) {
            $total = 0;
            foreach ($e['modelos'] as $chave) {
                $total += count(self::modelos()[$chave]['itens']);
            }
            $exemplos[] = [
                'perfil'  => $e['perfil'],
                'modelos' => array_map(static fn (string $c): string => $nomes[$c], $e['modelos']),
                'itens'   => $total,
                'extra'   => $e['extra'],
            ];
        }

        $hoje = new DateTimeImmutable(self::HOJE);

        return [
            'referencia'     => $hoje->format('d/m/Y'),
            'janela'         => self::JANELA_DIAS,
            'resumo'         => $resumo,
            'filtros'        => [
                ['chave' => 'todos', 'rotulo' => 'Todas', 'total' => count($linhas)],
                ['chave' => 'andamento', 'rotulo' => 'Em andamento', 'total' => $filtros['andamento']],
                ['chave' => 'atraso', 'rotulo' => 'Com atraso', 'total' => $filtros['atraso']],
                ['chave' => 'rascunho', 'rotulo' => 'Rascunhos', 'total' => $filtros['rascunho']],
                ['chave' => 'concluida', 'rotulo' => 'Concluídas', 'total' => $filtros['concluida']],
            ],
            'movimentacoes'  => $linhas,
            'prazos'         => $prazos,
            'modelos'        => $modelos,
            'exemplos'       => $exemplos,
        ];
    }

    /**
     * Checklist de uma movimentacao (Tela 5). Rascunho e id desconhecido
     * devolvem null: o rascunho ainda nao tem checklist, so o assistente.
     *
     * @return array<string, mixed>|null
     */
    public static function movimentacao(int $id): ?array
    {
        $brutas = self::movimentacoesBrutas();
        if (!isset($brutas[$id]) || $brutas[$id]['rascunho']) {
            return null;
        }
        $mov   = self::montar($id, $brutas[$id]);
        $nomes = [];
        foreach ($brutas[$id]['modelos'] as $chave) {
            $nomes[] = self::modelos()[$chave]['nome'];
        }
        $mov['modelos_nomes'] = $nomes;
        $mov['tem_ti']        = $brutas[$id]['chamados'] !== [];
        $mov['referencia']    = (new DateTimeImmutable(self::HOJE))->format('d/m/Y');

        $historico = [];
        foreach ($mov['itens'] as $item) {
            if ($item['situacao'] === 'feito') {
                $historico[] = ['quando' => $item['quando'], 'texto' => $item['titulo']];
            }
        }
        $mov['historico'] = array_reverse($historico);

        return $mov;
    }

    /**
     * Calcula situacao, prazos e progresso de uma movimentacao.
     *
     * @param array<string, mixed> $b
     *
     * @return array<string, mixed>
     */
    private static function montar(int $id, array $b): array
    {
        $chegada = $b['tipo'] === 'chegada';
        $inicio  = new DateTimeImmutable($b['inicio']);
        $itens   = [];
        $feitos  = 0;
        $atras   = 0;
        $breve   = 0;
        $proximo = null;

        foreach ($b['itens'] ?? [] as $item) {
            $data     = self::somarDias($b['inicio'], $item['dias']);
            $situacao = self::situacaoItem($item['estado'], $data->format('Y-m-d'));
            [$selo, $tom] = self::SITUACOES_ITEM[$situacao];
            if ($situacao === 'feito') {
                $feitos++;
            }
            if ($situacao === 'atrasado') {
                $atras++;
            }
            if (in_array($situacao, ['hoje', 'aberto'], true) && self::dentroDaJanela($data->format('Y-m-d'))) {
                $breve++;
            }
            if (in_array($situacao, ['atrasado', 'hoje', 'aberto'], true)
                && ($proximo === null || $data->format('Y-m-d') < $proximo['iso'])) {
                $proximo = ['iso' => $data->format('Y-m-d'), 'titulo' => $item['titulo'], 'data' => $data->format('d/m'), 'tom' => $tom];
            }
            $itens[] = [
                'titulo'      => $item['titulo'],
                'responsavel' => $item['responsavel'],
                'relativo'    => self::prazoRelativo($item['dias']),
                'data'        => $data->format('d/m'),
                'data_iso'    => $data->format('Y-m-d'),
                'origem'      => self::origem($item['origem']),
                'situacao'    => $situacao,
                'selo'        => $selo,
                'tom'         => $tom,
                'quando'      => $item['quando'],
                'evidencia'   => $item['evidencia'],
                'automatico'  => $item['origem'] === 'ti' || $item['estado'] === 'auto',
            ];
        }

        $total = count($itens);
        if ($b['rascunho']) {
            $fase     = 'rascunho';
            $situacao = ['Rascunho', 'neutro'];
        } elseif ($b['concluida'] !== '') {
            $fase     = 'concluida';
            $situacao = ['Concluída em ' . $b['concluida'], 'ok'];
        } else {
            $fase     = 'andamento';
            $situacao = $atras > 0 ? ['Atrasado', 'atraso'] : ['Em dia', 'ok'];
        }

        $data_rotulo = $chegada ? 'início ' . $inicio->format('d/m') : 'vale a partir de ' . $inicio->format('d/m');

        return [
            'id'           => $id,
            'tipo'         => $b['tipo'],
            'tipo_rotulo'  => $chegada ? 'Chegada' : 'Transferência',
            'icone'        => $chegada ? 'ti ti-door-enter' : 'ti ti-arrows-exchange',
            'pessoa'       => $b['pessoa'],
            'iniciais'     => DadosDemo::iniciais($b['pessoa']),
            'cargo'        => $b['cargo'],
            'setor'        => $b['setor'],
            'contexto'     => $chegada ? $b['cargo'] . ' · ' . $b['setor'] : $b['origem'] . ' → ' . $b['setor'],
            'origem'       => $b['origem'],
            'vinculo'      => $b['vinculo'],
            'empregador'   => $b['empregador'],
            'gestor'       => $b['gestor'],
            'data'         => $inicio->format('d/m'),
            'data_rotulo'  => $data_rotulo,
            'fase'         => $fase,
            'situacao'     => $situacao[0],
            'tom'          => $situacao[1],
            'itens'        => $itens,
            'feitos'       => $feitos,
            'total'        => $total,
            'percentual'   => $total > 0 ? (int) round($feitos * 100 / $total) : 0,
            'atrasados'    => $atras,
            'em_breve'     => $breve,
            'proximo'      => $proximo ?? ['iso' => '', 'titulo' => '', 'data' => '', 'tom' => 'neutro'],
            'etapa'        => (int) ($b['etapa'] ?? 0),
            'parado'       => (int) ($b['parado'] ?? 0),
            'chamados'     => $b['chamados'],
            'normativas'   => array_map(
                static fn (array $n): array => $n + ['nota' => ''],
                $b['normativas']
            ),
            'muda'         => $b['muda'],
        ];
    }

    /**
     * Situacao de um item contra HOJE. 'feito' e 'auto' nao vencem: o item
     * automatico conclui sozinho e nao cobra o responsavel.
     */
    public static function situacaoItem(string $estado, string $data_iso, string $hoje = self::HOJE): string
    {
        if ($estado === 'feito' || $estado === 'auto') {
            return $estado;
        }
        if ($data_iso < $hoje) {
            return 'atrasado';
        }

        return $data_iso === $hoje ? 'hoje' : 'aberto';
    }

    public static function prazoRelativo(int $dias): string
    {
        if ($dias === 0) {
            return 'no dia';
        }
        $n = abs($dias);

        return $n . ($n === 1 ? ' dia ' : ' dias ') . ($dias < 0 ? 'antes' : 'depois');
    }

    private static function dentroDaJanela(string $data_iso): bool
    {
        $limite = self::somarDias(self::HOJE, self::JANELA_DIAS)->format('Y-m-d');

        return $data_iso >= self::HOJE && $data_iso <= $limite;
    }

    private static function somarDias(string $data_iso, int $dias): DateTimeImmutable
    {
        return (new DateTimeImmutable($data_iso))->modify(($dias >= 0 ? '+' : '') . $dias . ' days');
    }

    private static function origem(string $chave): string
    {
        if ($chave === 'ti') {
            return 'necessidade de TI';
        }

        return 'modelo ' . (self::modelos()[$chave]['nome'] ?? $chave);
    }

    /**
     * @return array{titulo: string, responsavel: string, dias: int, origem: string, estado: string, quando: string, evidencia: string}
     */
    private static function item(
        string $titulo,
        string $responsavel,
        int $dias,
        string $origem,
        string $estado,
        string $quando,
        string $evidencia
    ): array {
        return [
            'titulo'      => $titulo,
            'responsavel' => $responsavel,
            'dias'        => $dias,
            'origem'      => $origem,
            'estado'      => $estado,
            'quando'      => $quando,
            'evidencia'   => $evidencia,
        ];
    }

    /**
     * @return array{titulo: string, responsavel: string, prazo: string, obrigatorio: bool, evidencia: string, conclusao: string}
     */
    private static function itemModelo(
        string $titulo,
        string $responsavel,
        int $dias,
        bool $obrigatorio,
        string $evidencia,
        string $conclusao
    ): array {
        return [
            'titulo'      => $titulo,
            'responsavel' => $responsavel,
            'prazo'       => self::prazoRelativo($dias),
            'obrigatorio' => $obrigatorio,
            'evidencia'   => $evidencia,
            'conclusao'   => $conclusao === 'ciencias' ? 'automática: quando todas as ciências forem confirmadas' : 'manual, pelo responsável',
        ];
    }
}
