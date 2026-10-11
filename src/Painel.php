<?php

/**
 * Pessoas+ - Painel do RH com dados reais (BM.7).
 *
 * PESSOASPLUS_BUILD_BM7
 *
 * Mesma estrutura que a casca (DadosDemo::painelRh): indicadores, alertas,
 * processos, agenda, atalhos; cada item traz 'area' para o filtro por direito
 * (Permissoes::filtrarPainel) e 'url' para abrir o que o item descreve.
 * Nada de notificacao ativa: o painel e o lugar que o RH abre e ve tudo.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use DBmysql;

final class Painel
{
    /** Dias para "vence em breve". */
    public const BREVE = 3;
    /** Dias de um termo esperando ciencia antes de virar alerta. */
    public const TERMO_PARADO = 3;
    /** Dias de um relato da Ouvidoria em "recebido" antes de virar atraso. */
    public const OUVIDORIA_PARADA = 5;
    /** Janela da agenda. */
    public const AGENDA_DIAS = 30;
    /** Adesao abaixo disto perto do fim vira alerta. */
    public const ADESAO_MINIMA = 70;

    /**
     * @return array{referencia: string, indicadores: array, alertas: array, processos: array, agenda: array, atalhos: array}
     */
    public static function montar(?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje ??= date('Y-m-d');
        $base  = \Html::getPrefixedUrl('/plugins/pessoasplus/front/');
        $ind = $alertas = $proc = $agenda = [];

        // ------------------------------------------------------------ Fichario
        $ativos = countElementsInTable(Colaborador::getTable(), ['is_deleted' => 0, 'situacao' => 'ativo']);
        $pre    = [];
        foreach ($DB->request(['SELECT' => ['id', 'nome', 'data_inicio'], 'FROM' => Colaborador::getTable(), 'WHERE' => ['is_deleted' => 0, 'situacao' => 'pre_cadastro'], 'ORDER' => 'data_inicio']) as $c) {
            $pre[] = $c;
        }
        $afastados = countElementsInTable(Colaborador::getTable(), ['is_deleted' => 0, 'situacao' => 'afastado']);
        $ind[] = self::indicador('Colaboradores ativos', (string) $ativos, (count($pre) > 0 ? count($pre) . (count($pre) === 1 ? ' pré-cadastro' : ' pré-cadastros') : 'nenhum pré-cadastro') . ($afastados > 0 ? ' · ' . $afastados . ' afastado' . ($afastados > 1 ? 's' : '') : ''), 'ti ti-users', 'neutro', 'fichario', $base . 'fichario.php');
        foreach ($pre as $c) {
            $di = (string) ($c['data_inicio'] ?? '');
            if ($di !== '' && $di >= $hoje && $di <= self::mais($hoje, self::AGENDA_DIAS)) {
                $agenda[] = self::evento($di, 'Chegada prevista: ' . $c['nome'], 'Fichário', 'ti ti-door-enter', 'fichario', $base . 'colaborador.form.php?id=' . (int) $c['id']);
            }
            if ($di !== '' && $di < $hoje) {
                $alertas[] = self::alerta('alerta', 'Pré-cadastro · ' . $c['nome'], 'Data de início ' . self::data($di) . ' já passou e a ficha continua em pré-cadastro', 'fichario', 'desde ' . self::data($di), $base . 'colaborador.form.php?id=' . (int) $c['id']);
            }
        }

        // ------------------------------------------------------------ Comunicados
        $pendentes = $atrasadas = 0;
        foreach (Comunicado::lista($hoje) as $c) {
            if ($c['fase'] !== 'andamento' || $c['total'] === 0) {
                continue;
            }
            $faltam = $c['total'] - $c['confirmados'];
            $pendentes += $faltam;
            $prazoIso = self::isoDe($c['prazo']);
            $vencido  = $prazoIso !== '' && $prazoIso < $hoje;
            if ($vencido) {
                $atrasadas += $faltam;
                $alertas[] = self::alerta('atraso', $c['tipo'] . ' · ' . $c['titulo'], $faltam . ($faltam === 1 ? ' pessoa sem ciência' : ' pessoas sem ciência') . ' de ' . $c['total'], 'comunicados', 'venceu em ' . $c['prazo'], $c['url']);
            } elseif ($prazoIso !== '' && $prazoIso <= self::mais($hoje, self::BREVE)) {
                $alertas[] = self::alerta('alerta', $c['tipo'] . ' · ' . $c['titulo'], $faltam . ($faltam === 1 ? ' pessoa ainda sem ciência' : ' pessoas ainda sem ciência') . ' (' . $c['percentual'] . '% confirmado)', 'comunicados', 'vence em ' . $c['prazo'], $c['url']);
            }
            if ($prazoIso !== '' && $prazoIso >= $hoje && $prazoIso <= self::mais($hoje, self::AGENDA_DIAS)) {
                $agenda[] = self::evento($prazoIso, 'Prazo de ciência: ' . $c['titulo'], $c['tipo'], 'ti ti-speakerphone', 'comunicados', $c['url']);
            }
            $proc[] = self::processo($c['tipo'], $c['titulo'], $c['origem'] . ' · publicado em ' . $c['publicado'], 'Ciências', $c['confirmados'], $c['total'], $c['prazo'] !== '—' ? 'até ' . $c['prazo'] : 'sem prazo', $vencido ? 'atraso' : 'info', $vencido ? 'Em atraso' : 'Em andamento', 'comunicados', $c['url']);
        }
        $ind[] = self::indicador('Ciências pendentes', (string) $pendentes, $atrasadas > 0 ? $atrasadas . ' em atraso' : 'nenhuma em atraso', 'ti ti-file-alert', $atrasadas > 0 ? 'atraso' : ($pendentes > 0 ? 'alerta' : 'ok'), 'comunicados', $base . 'comunicados.php');

        // ------------------------------------------------------------ Ouvidoria e pesquisas
        $abertosOuv = $recebidos = 0;
        $pesqAbertas = 0;
        $somaAdesao = 0;
        foreach (Formulario::lista($hoje) as $f) {
            $r = $f['resumo'];
            if ($f['tipo'] === 'ouvidoria') {
                $emAberto = (int) $r['recebido'] + (int) $r['em_analise'] + (int) $r['complemento'];
                $abertosOuv += $emAberto;
                $recebidos  += (int) $r['recebido'];
                if ((int) $r['recebido'] > 0) {
                    $form = new Formulario();
                    if ($form->getFromDB((int) $f['id'])) {
                        $maisAntiga = '';
                        foreach ($form->fila('recebido') as $resp) {
                            $d = self::isoDe((string) $resp['data']);
                            if ($d !== '' && ($maisAntiga === '' || $d < $maisAntiga)) {
                                $maisAntiga = $d;
                            }
                        }
                        $parado = $maisAntiga !== '' && $maisAntiga <= self::mais($hoje, -self::OUVIDORIA_PARADA);
                        $alertas[] = self::alerta($parado ? 'atraso' : 'alerta', 'Ouvidoria · ' . $f['titulo'], (int) $r['recebido'] . ((int) $r['recebido'] === 1 ? ' relato recebido sem tratamento' : ' relatos recebidos sem tratamento'), 'pesquisas', $maisAntiga !== '' ? 'desde ' . self::data($maisAntiga) : 'novo', $f['url_fila']);
                    }
                }
                continue;
            }
            if ($f['estado'] !== 'aberto') {
                continue;
            }
            $pesqAbertas++;
            $form = new Formulario();
            $cob  = $form->getFromDB((int) $f['id']) ? $form->cobranca() : ['total' => 0, 'responderam' => 0];
            $pct  = $cob['total'] > 0 ? (int) round($cob['responderam'] * 100 / $cob['total']) : 0;
            $somaAdesao += $pct;
            $fim = (string) $f['fim'];
            if ($fim !== '' && $fim <= self::mais($hoje, self::BREVE) && $pct < self::ADESAO_MINIMA) {
                $alertas[] = self::alerta('alerta', 'Pesquisa · ' . $f['titulo'], 'Adesão em ' . $pct . '% (' . $cob['responderam'] . ' de ' . $cob['total'] . ')', 'pesquisas', 'encerra em ' . self::data($fim), $f['url_fila']);
            }
            if ($fim !== '' && $fim >= $hoje && $fim <= self::mais($hoje, self::AGENDA_DIAS)) {
                $agenda[] = self::evento($fim, 'Encerra: ' . $f['titulo'], 'Pesquisa', 'ti ti-chart-bar', 'pesquisas', $f['url_fila']);
            }
            $proc[] = self::processo('Pesquisa', $f['titulo'], $f['modo_rotulo'] . ' · ' . $f['periodo'], 'Respostas', $cob['responderam'], $cob['total'], $fim !== '' ? 'até ' . self::data($fim) : 'sem fim', $pct >= self::ADESAO_MINIMA ? 'ok' : 'info', $pct . '% de adesão', 'pesquisas', $f['url_fila']);
        }
        $ind[] = self::indicador('Ouvidoria em aberto', (string) $abertosOuv, $recebidos > 0 ? $recebidos . ' sem tratamento' : 'tudo em tratamento', 'ti ti-message-circle-heart', $recebidos > 0 ? 'alerta' : 'ok', 'pesquisas', $base . 'formularios.php');
        $ind[] = self::indicador('Pesquisas abertas', (string) $pesqAbertas, $pesqAbertas > 0 ? 'adesão média ' . (int) round($somaAdesao / $pesqAbertas) . '%' : 'nenhuma campanha no ar', 'ti ti-chart-bar', 'neutro', 'pesquisas', $base . 'formularios.php');

        // ------------------------------------------------------------ Cautela
        $termosPend = Cautela::lista(['situacao' => 'emitido']);
        foreach ($termosPend as $t) {
            $emitido = self::isoDe($t['emitido_em']);
            if ($emitido !== '' && $emitido <= self::mais($hoje, -self::TERMO_PARADO)) {
                $alertas[] = self::alerta('alerta', 'Cautela · ' . $t['colaborador'], $t['titulo'] . ' aguarda ciência desde ' . $t['emitido_em'], 'cautela', 'há ' . self::dias($emitido, $hoje) . ' dias', $t['url']);
            }
        }
        $confPend = $divergencias = 0;
        foreach (Conferencia::lista() as $c) {
            if ($c['situacao'] !== 'aberta') {
                continue;
            }
            $conf = new Conferencia();
            if (!$conf->getFromDB($c['id'])) {
                continue;
            }
            $pessoas = $conf->pessoas();
            $faltam  = count(array_filter($pessoas, static fn ($p) => $p['respondidos'] < $p['itens']));
            $div     = array_sum(array_map(static fn ($p) => $p['divergentes'], $pessoas));
            $confPend += $faltam;
            $divergencias += $div;
            $fim = (string) $c['fim'];
            if ($faltam > 0) {
                $alertas[] = self::alerta($c['atrasada'] ? 'atraso' : 'alerta', 'Conferência · ' . $c['titulo'], $faltam . ($faltam === 1 ? ' pessoa ainda não conferiu' : ' pessoas ainda não conferiram') . ' os itens' . ($div > 0 ? ' · ' . $div . ($div === 1 ? ' divergência' : ' divergências') : ''), 'cautela', $fim !== '' ? ($c['atrasada'] ? 'venceu em ' : 'até ') . self::data($fim) : 'sem prazo', $c['url']);
            } elseif ($div > 0) {
                $alertas[] = self::alerta('alerta', 'Conferência · ' . $c['titulo'], $div . ($div === 1 ? ' divergência sem desfecho' : ' divergências sem desfecho'), 'cautela', 'conferida', $c['url']);
            }
            if ($fim !== '' && $fim >= $hoje && $fim <= self::mais($hoje, self::AGENDA_DIAS)) {
                $agenda[] = self::evento($fim, 'Prazo da conferência: ' . $c['titulo'], 'Cautela', 'ti ti-clipboard-check', 'cautela', $c['url']);
            }
            $proc[] = self::processo('Conferência', $c['titulo'], $c['publico_modo_rotulo'] . ($c['recorrencia'] !== 'nenhuma' ? ' · ' . $c['recorrencia_rotulo'] : ''), 'Pessoas conferidas', count($pessoas) - $faltam, count($pessoas), $fim !== '' ? 'até ' . self::data($fim) : 'sem prazo', $c['atrasada'] ? 'atraso' : ($faltam === 0 ? 'ok' : 'info'), $c['atrasada'] ? 'Prazo vencido' : ($faltam === 0 ? 'Conferida' : 'Em andamento'), 'cautela', $c['url']);
        }
        foreach ($termosPend as $t) {
            $proc[] = self::processo($t['tipo_rotulo'], $t['colaborador'], $t['setor'] !== '' ? $t['setor'] : 'sem setor', 'Aguardando ciência', 0, 1, 'emitido em ' . $t['emitido_em'], 'alerta', 'Aguardando ciência', 'cautela', $t['url']);
        }
        $ind[] = self::indicador('Cautela a conferir', (string) ($confPend + count($termosPend)), (count($termosPend) > 0 ? count($termosPend) . (count($termosPend) === 1 ? ' termo sem ciência' : ' termos sem ciência') : 'nenhum termo pendente') . ($divergencias > 0 ? ' · ' . $divergencias . ' diverg.' : ''), 'ti ti-tool', $divergencias > 0 || $confPend > 0 ? 'alerta' : 'ok', 'cautela', $base . 'cautela.php');

        // ------------------------------------------------------------ Mural
        $noAr = $agendadas = 0;
        foreach (Publicacao::lista(['situacao' => '', 'busca' => ''], $hoje)['publicacoes'] as $p) {
            if ($p['estado'] === 'no_ar') {
                $noAr++;
            } elseif ($p['estado'] === 'agendada') {
                $agendadas++;
                $ini = (string) ($p['inicio'] ?? '');
                if ($ini !== '' && $ini <= self::mais($hoje, self::AGENDA_DIAS)) {
                    $agenda[] = self::evento($ini, 'Entra no mural: ' . $p['titulo'], 'Mural', 'ti ti-news', 'mural', $base . 'publicacao_nova.php?id=' . (int) $p['id']);
                }
            }
        }
        $ind[] = self::indicador('Mural no ar', (string) $noAr, $agendadas > 0 ? $agendadas . ($agendadas === 1 ? ' agendada' : ' agendadas') : 'nenhuma agendada', 'ti ti-news', 'neutro', 'mural', $base . 'mural.php');

        // ------------------------------------------------------------ ordem
        $peso = ['atraso' => 0, 'alerta' => 1, 'info' => 2];
        usort($alertas, static fn ($a, $b) => ($peso[$a['tom']] <=> $peso[$b['tom']]) ?: strcmp($a['titulo'], $b['titulo']));
        usort($agenda, static fn ($a, $b) => strcmp($a['iso'], $b['iso']) ?: strcmp($a['titulo'], $b['titulo']));
        $pesoProc = ['atraso' => 0, 'alerta' => 1, 'info' => 2, 'ok' => 3, 'neutro' => 4];
        usort($proc, static fn ($a, $b) => ($pesoProc[$a['tom']] <=> $pesoProc[$b['tom']]) ?: strcmp($a['pessoa'], $b['pessoa']));

        return [
            'referencia'  => self::data($hoje),
            'indicadores' => $ind,
            'alertas'     => $alertas,
            'processos'   => $proc,
            'agenda'      => $agenda,
            'atalhos'     => [
                ['area' => 'comunicados', 'rotulo' => 'Novo comunicado', 'icone' => 'ti ti-speakerphone', 'bloco' => '', 'pagina' => 'comunicado_novo.php'],
                ['area' => 'mural', 'rotulo' => 'Nova publicação', 'icone' => 'ti ti-news', 'bloco' => '', 'pagina' => 'publicacao_nova.php'],
                ['area' => 'pesquisas', 'rotulo' => 'Nova pesquisa', 'icone' => 'ti ti-chart-bar', 'bloco' => '', 'pagina' => 'formulario_novo.php'],
                ['area' => 'cautela', 'rotulo' => 'Nova conferência', 'icone' => 'ti ti-clipboard-check', 'bloco' => '', 'pagina' => 'conferencia.php'],
                ['area' => 'fichario', 'rotulo' => 'Nova ficha', 'icone' => 'ti ti-user-plus', 'bloco' => '', 'pagina' => 'colaborador.form.php'],
            ],
        ];
    }

    // ----------------------------------------------------------------- construtores

    private static function indicador(string $rotulo, string $valor, string $detalhe, string $icone, string $tom, string $area, string $url): array
    {
        return ['rotulo' => $rotulo, 'valor' => $valor, 'detalhe' => $detalhe, 'icone' => $icone, 'tom' => $tom, 'area' => $area, 'url' => $url];
    }

    private static function alerta(string $tom, string $titulo, string $detalhe, string $area, string $prazo, string $url): array
    {
        return ['tom' => $tom, 'titulo' => $titulo, 'detalhe' => $detalhe, 'area' => $area, 'prazo' => $prazo, 'url' => $url];
    }

    private static function processo(string $tipo, string $pessoa, string $contexto, string $etapa, int $feitos, int $total, string $prazo, string $tom, string $situacao, string $area, string $url): array
    {
        return [
            'tipo' => $tipo, 'pessoa' => $pessoa, 'iniciais' => Colaborador::iniciais($pessoa), 'contexto' => $contexto, 'etapa' => $etapa,
            'feitos' => $feitos, 'total' => $total, 'percentual' => $total > 0 ? (int) round($feitos * 100 / $total) : 0,
            'prazo' => $prazo, 'tom' => $tom, 'situacao' => $situacao, 'area' => $area, 'url' => $url,
        ];
    }

    private static function evento(string $iso, string $titulo, string $origem, string $icone, string $area, string $url): array
    {
        $meses = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
        $ts = strtotime($iso);

        return ['iso' => $iso, 'dia' => date('d', $ts), 'mes' => $meses[(int) date('n', $ts) - 1], 'titulo' => $titulo, 'origem' => $origem, 'icone' => $icone, 'area' => $area, 'url' => $url];
    }

    // ----------------------------------------------------------------- datas

    private static function mais(string $iso, int $dias): string
    {
        return date('Y-m-d', strtotime($iso . ' ' . ($dias >= 0 ? '+' : '') . $dias . ' days'));
    }

    private static function dias(string $de, string $ate): int
    {
        return (int) round((strtotime($ate) - strtotime($de)) / 86400);
    }

    private static function data(string $iso): string
    {
        return $iso !== '' ? date('d/m/Y', strtotime($iso)) : '—';
    }

    /** Aceita 'Y-m-d', 'Y-m-d H:i:s', 'd/m/Y' ou 'd/m/Y H:i'; devolve 'Y-m-d' ou ''. */
    private static function isoDe(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '' || $texto === '—') {
            return '';
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $texto, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $texto, $m)) {
            return $m[1];
        }

        return '';
    }
}
