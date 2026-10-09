<?php

/**
 * Pessoas+ - dados ficticios da casca.
 *
 * PESSOASPLUS_BUILD_BP0
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2A
 * PESSOASPLUS_BUILD_BP2B
 * PESSOASPLUS_BUILD_BP2C
 * PESSOASPLUS_BUILD_BP3A
 * PESSOASPLUS_BUILD_BP3B
 * PESSOASPLUS_BUILD_BP0C
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

/**
 * Dados de demonstracao. Nomes e numeros sao ficticios e seguem os da
 * proposta apresentada a diretoria, para que a casca e a proposta contem
 * a mesma historia. Nenhum dado real de colaborador entra aqui.
 *
 * Datas fixas em setembro e outubro de 2026, alinhadas as telas da proposta.
 */
final class DadosDemo
{
    /**
     * Na demonstracao, a pessoa do painel do comunicado 1 que representa o
     * usuario logado (e confirmada): e o comprovante da propria pessoa, que
     * dispensa o direito de Comunicados. Na mobilia, compara-se o users_id.
     */
    public const PESSOA_LOGADA = 7;

    /**
     * @return array<string, mixed>
     */
    public static function painelRh(): array
    {
        return [
            'referencia'  => '26/09/2026',
            'indicadores' => [
                self::indicador('Colaboradores ativos', '48', '2 chegadas previstas', 'ti ti-users', 'neutro', 'fichario'),
                self::indicador('Ciências pendentes', '7', '2 em atraso', 'ti ti-file-alert', 'atraso', 'comunicados'),
                self::indicador('Chegadas em andamento', '2', '1 item atrasado', 'ti ti-door-enter', 'alerta', 'chegadas'),
                self::indicador('Férias a vencer', '3', 'nos próximos 90 dias', 'ti ti-beach', 'alerta', 'ferias'),
                self::indicador('Desligamentos em curso', '1', '3 de 5 setores', 'ti ti-door-exit', 'neutro', 'desligamentos'),
                self::indicador('Cautelas a conferir', '4', 'campanha de setembro', 'ti ti-tool', 'alerta', 'cautela'),
            ],
            'alertas' => [
                self::alerta('atraso', 'Política de uso de veículos · rev. 02', '2 ciências em atraso: Rafael Souza (Suporte N1) e mais 1', 'comunicados', 'venceu em 20/09'),
                self::alerta('atraso', 'Chegada · Lucas Ferreira', 'Separar EPI e crachá está atrasado (Almoxarifado)', 'chegadas', 'início em 01/10'),
                self::alerta('alerta', 'Nada consta · Mariana Alves', 'Financeiro apontou pendência: adiantamento sem prestação de contas', 'desligamentos', 'último dia 30/09'),
                self::alerta('alerta', 'Pesquisa de clima · setembro', 'Adesão em 62% (30 de 48). Lembrete disponível para quem falta', 'pesquisas', 'encerra em 30/09'),
                self::alerta('alerta', 'Férias · Carlos Lima', 'Período concessivo vence em 11/2026, sem férias marcadas', 'ferias', 'vence em 11/2026'),
                self::alerta('info', 'Conferência de cautela · setembro', '4 pessoas ainda não confirmaram os itens sob sua guarda', 'cautela', 'até 30/09'),
            ],
            'processos' => [
                self::processo('Chegada', 'Lucas Ferreira', 'Técnico de campo · Campo Norte', 'Checklist de chegada', 4, 7, 'início 01/10', 'atraso', 'Atrasado', 'chegadas'),
                self::processo('Chegada', 'Bruno Teixeira', 'Técnico N2 · Suporte N1', 'Assistente de chegada', 2, 6, 'início 13/10', 'neutro', 'Rascunho', 'chegadas'),
                self::processo('Desligamento', 'Mariana Alves', 'Técnica de campo · Campo Sul', 'Nada consta', 3, 5, 'último dia 30/09', 'alerta', 'Pendência', 'desligamentos'),
                self::processo('Transferência', 'Juliana Prado', 'Campo Sul → Campo Norte', 'Checklist de transferência', 5, 6, 'efetiva em 05/10', 'ok', 'Em dia', 'chegadas'),
                self::processo('Férias', 'Rafael Souza', 'Suporte N1 · 23 a 31/12', 'Aguarda decisão do gestor', 1, 3, 'responder até 02/10', 'alerta', 'Sobreposição', 'ferias'),
            ],
            'agenda' => [
                self::evento('30', 'SET', 'Encerra a pesquisa de clima de setembro', 'Pesquisas', 'ti ti-chart-bar', 'pesquisas'),
                self::evento('30', 'SET', 'Último dia de Mariana Alves', 'Desligamento', 'ti ti-door-exit', 'desligamentos'),
                self::evento('01', 'OUT', 'Chegada de Lucas Ferreira', 'Chegada', 'ti ti-door-enter', 'chegadas'),
                self::evento('05', 'OUT', 'Juliana Prado passa para Campo Norte', 'Transferência', 'ti ti-arrows-exchange', 'chegadas'),
                self::evento('12', 'OUT', 'Feriado: Nossa Senhora Aparecida', 'Calendário do GLPI', 'ti ti-calendar-event', ''),
                self::evento('15', 'OUT', 'Carlos Lima completa 5 anos de casa', 'Celebração', 'ti ti-confetti', 'mural'),
                self::evento('20', 'OUT', 'Revisão da normativa NOR0007 · Jornada e horas', 'Codex+', 'ti ti-file-certificate', 'comunicados'),
            ],
            'atalhos' => [
                self::atalho('comunicados', 'Novo comunicado', 'ti ti-speakerphone', 'BP.2c', 'comunicado_novo.php'),
                self::atalho('chegadas', 'Nova chegada', 'ti ti-door-enter', 'BP.4'),
                self::atalho('desligamentos', 'Declarar desligamento', 'ti ti-door-exit', 'BP.8'),
                self::atalho('pesquisas', 'Nova pesquisa', 'ti ti-chart-bar', 'BP.7'),
            ],
        ];
    }

    /**
     * Lista de comunicados e normativas do emissor.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function comunicados(): array
    {
        $lista = [
            [1, 'Política de uso de veículos da frota', 'Normativa', 'NOR0004 · rev. 02', 'Campo Norte, Campo Sul, Suporte N1, Administrativo', '01/09', '20/09', 41, 48, 'andamento', 'Em atraso', 'atraso'],
            [2, 'Escala de plantão do feriado', 'Comunicado', 'RH', 'Suporte N1, Campo Norte', '24/09', '30/09', 9, 19, 'andamento', 'Em andamento', 'info'],
            [3, 'Acordo de banco de horas 2026', 'Concordância', 'RH · "De acordo"', 'Vínculo CLT', '22/09', '05/10', 30, 38, 'andamento', 'Em andamento', 'info'],
            [4, 'Novo horário do almoxarifado', 'Comunicado', 'Almoxarifado', 'Todos', '15/09', '18/09', 48, 48, 'concluido', 'Concluído', 'ok'],
            [5, 'Código de conduta', 'Normativa', 'POL0002 · rev. 01', 'Todos', '02/06', '16/06', 48, 48, 'concluido', 'Vigente', 'ok'],
            [6, 'Campanha Outubro Rosa', 'Comunicado', 'RH', 'Todos', '—', '—', 0, 0, 'rascunho', 'Rascunho', 'neutro'],
        ];

        $saida = [];
        foreach ($lista as $c) {
            $saida[] = [
                'id'         => $c[0],
                'titulo'     => $c[1],
                'tipo'       => $c[2],
                'origem'     => $c[3],
                'publico'    => $c[4],
                'publicado'  => $c[5],
                'prazo'      => $c[6],
                'confirmados' => $c[7],
                'total'      => $c[8],
                'percentual' => $c[8] > 0 ? (int) round($c[7] * 100 / $c[8]) : 0,
                'fase'       => $c[9],
                'situacao'   => $c[10],
                'tom'        => $c[11],
                'detalhe'    => $c[0] === 1,
            ];
        }

        return $saida;
    }

    /**
     * Painel do emissor de um comunicado (Tela 2 da proposta).
     * Na demonstracao so o comunicado 1 tem painel.
     *
     * Numeros coerentes entre si: 48 no publico, 41 confirmaram (85%),
     * 7 faltam: 2 em atraso (estavam no publico na publicacao, prazo
     * 20/09) e 5 pendentes que entraram nos grupos depois (P-04), cada
     * um com prazo proprio de 10 dias.
     *
     * @return array<string, mixed>|null
     */
    public static function comunicado(int $id): ?array
    {
        if ($id !== 1) {
            return null;
        }

        $grupos = [
            ['Campo Norte', 11, 12],
            ['Campo Sul', 8, 10],
            ['Suporte N1', 10, 14],
            ['Administrativo', 12, 12],
        ];
        $por_grupo = [];
        foreach ($grupos as $g) {
            $pct = (int) round($g[1] * 100 / $g[2]);
            $por_grupo[] = [
                'grupo'       => $g[0],
                'confirmados' => $g[1],
                'total'       => $g[2],
                'percentual'  => $pct,
                'tom'         => $pct >= 90 ? 'ok' : ($pct >= 75 ? 'info' : 'alerta'),
            ];
        }

        $pessoas = [
            // nome, grupo, situacao, leitura, prazo, avisos, confirmado_em
            ['Rafael Souza', 'Suporte N1', 'atrasado', 'Abriu em 03/09, não confirmou', 'venceu em 20/09', 9, ''],
            ['Tiago Rocha', 'Campo Norte', 'atrasado', 'Não abriu', 'venceu em 20/09', 7, ''],
            ['Juliana Prado', 'Campo Sul', 'pendente', 'Não abriu · incluída no grupo em 22/09', 'até 02/10', 3, ''],
            ['Bruna Costa', 'Campo Sul', 'pendente', 'Abriu em 24/09, não confirmou', 'até 03/10', 2, ''],
            ['Sandra Melo', 'Suporte N1', 'pendente', 'Não abriu · incluída no grupo em 23/09', 'até 03/10', 2, ''],
            ['Diego Martins', 'Suporte N1', 'pendente', 'Abriu em 25/09, não confirmou', 'até 04/10', 1, ''],
            ['Felipe Araújo', 'Suporte N1', 'pendente', 'Não abriu · incluído no grupo em 25/09', 'até 05/10', 1, ''],
            ['Carlos Lima', 'Campo Norte', 'confirmado', 'Ciente', '', 0, '02/09 às 08:17'],
            ['Ana Ribeiro', 'Administrativo', 'confirmado', 'Ciente', '', 0, '02/09 às 09:12'],
            ['Marcos Dias', 'Campo Sul', 'confirmado', 'Ciente', '', 1, '03/09 às 14:36'],
            ['Helena Duarte', 'Administrativo', 'confirmado', 'Ciente', '', 0, '04/09 às 10:05'],
            ['Eduardo Nunes', 'Suporte N1', 'confirmado', 'Ciente', '', 2, '05/09 às 16:40'],
        ];
        $lista = [];
        foreach ($pessoas as $p) {
            $lista[] = [
                'nome'      => $p[0],
                'iniciais'  => self::iniciais($p[0]),
                'grupo'     => $p[1],
                'situacao'  => $p[2],
                'rotulo'    => $p[2] === 'atrasado' ? 'Atrasado' : ($p[2] === 'pendente' ? 'Pendente' : 'Confirmou ' . $p[6]),
                'tom'       => $p[2] === 'atrasado' ? 'atraso' : ($p[2] === 'pendente' ? 'alerta' : 'ok'),
                'leitura'   => $p[3],
                'prazo'     => $p[4],
                'avisos'    => $p[5],
                'confirmado_em' => $p[6] === '' ? '' : str_replace(' às ', '/2026 ', $p[6]),
            ];
        }

        return [
            'id'        => 1,
            'titulo'    => 'Política de uso de veículos da frota',
            'versao'    => 'rev. 02',
            'codigo'    => 'NOR0004:02',
            'tipo'      => 'Normativa',
            'situacao'  => 'Publicado',
            'publicado' => '01/09/2026 por Gestão de RH',
            'prazo'     => '20/09/2026',
            'selo'      => '7f3a9c1e5b7d4e8a9c217d4e5f6a8b90c3d2e1f0a9b8c7d6e5f4a3b2c1d0e9f8',
            'numeros'   => [
                ['rotulo' => 'No público', 'valor' => 48, 'tom' => 'neutro'],
                ['rotulo' => 'Ciência', 'valor' => 41, 'tom' => 'ok'],
                ['rotulo' => 'Pendentes', 'valor' => 5, 'tom' => 'alerta'],
                ['rotulo' => 'Em atraso', 'valor' => 2, 'tom' => 'atraso'],
            ],
            'adesao'    => 85,
            'por_grupo' => $por_grupo,
            'pessoas'   => $lista,
            'omitidos'  => 36,
        ];
    }

    /**
     * Conteudo congelado de um comunicado, para a tela de leitura.
     * Texto ficticio de demonstracao: nao e uma politica real da empresa.
     *
     * @return array<string, mixed>|null
     */
    public static function leitura(int $id): ?array
    {
        $painel = self::comunicado($id);
        if ($painel === null) {
            return null;
        }

        return [
            'id'        => $id,
            'titulo'    => $painel['titulo'],
            'codigo'    => $painel['codigo'],
            'tipo'      => $painel['tipo'],
            'versao'    => $painel['versao'],
            'publicado' => $painel['publicado'],
            'prazo'     => $painel['prazo'],
            'selo'      => $painel['selo'],
            'resposta'  => 'Ciente',
            'quiz'      => ['exigido' => true, 'nota' => 80, 'minima' => 70],
            'secoes'    => [
                ['1. Objetivo', 'Definir as regras de uso dos veículos da frota, para segurança de quem dirige, de quem é transportado e de terceiros.'],
                ['2. Quem pode dirigir', 'Somente colaboradores autorizados pelo gestor da área, com habilitação válida e compatível com o veículo.'],
                ['3. Uso em serviço', 'O veículo é usado apenas em atividades de trabalho e nas rotas autorizadas para o atendimento do dia. Desvios precisam de autorização prévia do gestor.'],
                ['4. Inspeção diária', 'Antes da primeira saída do dia, o condutor preenche o checklist de inspeção (anexo) e comunica qualquer avaria ao gestor.'],
                ['5. Ocorrências', 'Acidentes, avarias e autuações são comunicados ao gestor no mesmo dia, com fotos quando possível.'],
            ],
            'anexos'    => [
                ['nome' => 'Checklist de inspeção diária do veículo.pdf', 'tamanho' => '184 KB', 'hash' => 'a91c4e07d2b6f3581e9c'],
            ],
        ];
    }

    /**
     * Comprovante de ciencia (Tela 12 da proposta). Com $pessoa, o
     * individual daquela pessoa; sem, o coletivo com as confirmacoes.
     *
     * @return array<string, mixed>|null
     */
    public static function comprovante(int $id, ?int $pessoa): ?array
    {
        $painel = self::comunicado($id);
        if ($painel === null) {
            return null;
        }

        $confirmados = [];
        foreach ($painel['pessoas'] as $i => $p) {
            if ($p['situacao'] === 'confirmado') {
                $confirmados[$i] = $p;
            }
        }
        if ($pessoa !== null && !isset($confirmados[$pessoa])) {
            return null;
        }

        $linhas = [];
        foreach ($pessoa === null ? $confirmados : [$pessoa => $confirmados[$pessoa]] as $i => $p) {
            $linhas[] = [
                'nome'      => $p['nome'],
                'grupo'     => $p['grupo'],
                'resposta'  => 'Ciente',
                'data'      => $p['confirmado_em'],
                'interface' => $i % 2 === 0 ? 'Padrão' : 'Simplificada',
                'avisos'    => $p['avisos'],
            ];
        }

        return [
            'individual' => $pessoa !== null,
            'titulo'     => $painel['titulo'],
            'codigo'     => $painel['codigo'],
            'tipo'       => $painel['tipo'],
            'publicado'  => $painel['publicado'],
            'selo'       => $painel['selo'],
            'protocolo'  => $pessoa !== null ? sprintf('PP-2026-%06d', 1200 + $pessoa) : 'PP-2026-COL-000001',
            'linhas'     => $linhas,
            'omitidos'   => $pessoa !== null ? 0 : $painel['omitidos'],
            'emitido'    => '26/09/2026',
        ];
    }

    /**
     * Opcoes da tela "Novo comunicado".
     *
     * Grupos com a divisao por vinculo, somando 48 pessoas (38 CLT e 10 PJ),
     * os mesmos numeros usados no painel do emissor e na lista. Documentos
     * do Codex+ com os codigos da Figura 3 da proposta.
     *
     * @return array<string, mixed>
     */
    public static function novoComunicado(): array
    {
        return [
            'tipos' => [
                ['valor' => 'comunicado', 'rotulo' => 'Comunicado', 'resposta' => 'Ciente', 'descricao' => 'Aviso que pede ciência.'],
                ['valor' => 'concordancia', 'rotulo' => 'Concordância', 'resposta' => 'De acordo', 'descricao' => 'Pede que a pessoa declare estar de acordo.'],
                ['valor' => 'normativa', 'rotulo' => 'Normativa vigente', 'resposta' => 'Ciente', 'descricao' => 'Documento do Codex+, com revisões.'],
            ],
            'grupos' => [
                ['id' => 'cn', 'nome' => 'Campo Norte', 'clt' => 10, 'pj' => 2],
                ['id' => 'cs', 'nome' => 'Campo Sul', 'clt' => 8, 'pj' => 2],
                ['id' => 'sn1', 'nome' => 'Suporte N1', 'clt' => 11, 'pj' => 3],
                ['id' => 'adm', 'nome' => 'Administrativo', 'clt' => 9, 'pj' => 3],
            ],
            'codex' => [
                ['codigo' => 'REG0001:03', 'titulo' => 'Regimento interno', 'situacao' => 'Vigente', 'tom' => 'ok', 'distribuida' => false],
                ['codigo' => 'POL0002:01', 'titulo' => 'Código de conduta', 'situacao' => 'Já distribuída', 'tom' => 'neutro', 'distribuida' => true],
                ['codigo' => 'POL0003:00', 'titulo' => 'Privacidade e LGPD', 'situacao' => 'Vigente', 'tom' => 'ok', 'distribuida' => false],
                ['codigo' => 'NOR0004:02', 'titulo' => 'Uso de veículos da frota', 'situacao' => 'Já distribuída', 'tom' => 'neutro', 'distribuida' => true],
                ['codigo' => 'NOR0007:01', 'titulo' => 'Jornada e horas', 'situacao' => 'Revisão em 20/10', 'tom' => 'alerta', 'distribuida' => false],
                ['codigo' => 'NOR0009:00', 'titulo' => 'Segurança em campo', 'situacao' => 'Vigente', 'tom' => 'ok', 'distribuida' => false],
                ['codigo' => 'NOR0011:00', 'titulo' => 'Uniforme e EPI', 'situacao' => 'Vigente', 'tom' => 'ok', 'distribuida' => false],
            ],
            'lembrete_horas'   => 4,
            'prazo_entrada'    => 10,
        ];
    }

    /**
     * Minha area de uma colaboradora ficticia (Juliana Prado, da proposta).
     * As pendencias de ciencia sao as mesmas do aviso ao entrar.
     *
     * @return array<string, mixed>
     */
    public static function minhaArea(): array
    {
        return [
            'ciencias'  => self::avisoEntrada()['itens'],
            'pesquisas' => [
                [
                    'titulo'     => 'Pesquisa de clima · setembro',
                    'detalhe'    => 'Anônima · 12 perguntas · encerra em 30/09',
                    'selo'       => 'Aberta',
                    'tom'        => 'info',
                    'acao'       => 'Responder',
                    'bloco'      => 'BP.7',
                    'progresso'  => -1,
                    'anonima'    => true,
                ],
                [
                    'titulo'     => 'Quiz · Política de uso de veículos',
                    'detalhe'    => '5 perguntas · nota mínima 70% · libera a ciência da normativa',
                    'selo'       => 'Obrigatório',
                    'tom'        => 'alerta',
                    'acao'       => 'Continuar',
                    'bloco'      => 'BP.7',
                    'progresso'  => 40,
                    'anonima'    => false,
                ],
            ],
            'ferias' => [
                'periodo'    => '2025/2026',
                'saldo'      => '30',
                'concessivo' => '11/2027',
                'situacao'   => 'Nenhuma solicitação em aberto',
            ],
            'tarefas' => [
                ['titulo' => 'Enviar certificado de treinamento NR-35', 'detalhe' => 'Pedido pelo RH', 'selo' => 'Vence amanhã', 'tom' => 'alerta'],
                ['titulo' => 'Confirmar itens em cautela', 'detalhe' => 'Conferência de setembro · 3 itens', 'selo' => 'Até 30/09', 'tom' => 'info'],
            ],
            'registros' => [
                ['rotulo' => 'Reconhecimentos no ano', 'valor' => '2', 'icone' => 'ti ti-award'],
                ['rotulo' => 'Comprovantes de ciência', 'valor' => '14', 'icone' => 'ti ti-file-certificate'],
                ['rotulo' => 'Tempo de casa', 'valor' => '3 anos', 'icone' => 'ti ti-calendar'],
            ],
        ];
    }

    /**
     * Mural e celebracoes (Tela 3 da proposta, secao 5.2).
     *
     * Regras da casca: aniversario so com dia e mes, de quem autorizou
     * (D-41); feriado vem do calendario nativo do GLPI; visualizacoes sao
     * so agregadas (nunca quem viu). Os cartoes de pesquisa sao os mesmos
     * da Minha area, para as duas telas contarem a mesma historia.
     *
     * @return array<string, mixed>
     */
    public static function mural(): array
    {
        $categorias = [
            'aviso'         => ['Aviso', 'alerta', 'ti ti-bell'],
            'evento'        => ['Evento', 'info', 'ti ti-calendar-event'],
            'reconhecimento' => ['Reconhecimento', 'ok', 'ti ti-award'],
            'treinamento'   => ['Treinamento', 'neutro', 'ti ti-school'],
        ];

        $lista = [
            ['reconhecimento', 'Elogio de cliente para Juliana Prado', 'Avaliação máxima na pesquisa de satisfação do chamado 4821, com o comentário "resolveu no primeiro contato e explicou tudo".', 'Publicado com a concordância da colaboradora', 'RH', 'até 10/10', 39],
            ['evento', 'Café de boas-vindas aos novos colegas', 'Quarta, 01/10, às 9h, na copa da sede. Venha conhecer quem está chegando ao Campo Norte.', '', 'RH', 'de 25/09 a 01/10', 31],
            ['treinamento', 'Direção defensiva: inscrições abertas', 'Turmas em 14/10 e 21/10 para quem dirige veículo da frota. Conta como requisito da política de uso de veículos.', '', 'Frota', 'de 22/09 a 13/10', 27],
            ['aviso', 'Novo horário do almoxarifado', 'A partir de 15/09 o almoxarifado atende das 7h30 às 16h30, com retirada de EPI só até as 16h.', '', 'Almoxarifado', 'de 15/09 a 15/10', 46],
        ];

        $publicacoes = [];
        foreach ($lista as $i => $p) {
            $cat = $categorias[$p[0]];
            $publicacoes[] = [
                'id'             => $i + 1,
                'categoria_chave' => $p[0],
                'categoria'      => $cat[0],
                'tom'            => $cat[1],
                'icone'          => $cat[2],
                'titulo'         => $p[1],
                'resumo'         => $p[2],
                'nota'           => $p[3],
                'autor'          => $p[4],
                'periodo'        => $p[5],
                'visualizacoes'  => $p[6],
            ];
        }

        $filtros = [['chave' => 'todos', 'rotulo' => 'Todas']];
        foreach ($categorias as $chave => $cat) {
            $filtros[] = ['chave' => $chave, 'rotulo' => $cat[0]];
        }

        return [
            'referencia'  => '26/09/2026',
            'publico'     => 48,
            'destaque'    => [
                'categoria'     => 'Aviso',
                'icone'         => 'ti ti-calendar-off',
                'titulo'        => 'Expediente no feriado de 12/10',
                'texto'         => 'Suporte N1 e Campo Norte seguem a escala de plantão publicada em 24/09. Os demais setores não têm expediente.',
                'autor'         => 'RH',
                'periodo'       => 'de 26/09 a 12/10',
                'visualizacoes' => 44,
            ],
            'filtros'     => $filtros,
            'publicacoes' => $publicacoes,
            'celebracoes' => [
                self::celebracao('boas_vindas', 'ti ti-door-enter', 'Boas-vindas, Lucas Ferreira', 'Técnico de campo · Campo Norte', '01/10'),
                self::celebracao('tempo_casa', 'ti ti-confetti', 'Ana Ribeiro completa 1 ano de casa', 'Administrativo', '08/10'),
                self::celebracao('aniversario', 'ti ti-cake', 'Aniversário de Fernanda Costa', 'Suporte N1', '09/10'),
                self::celebracao('feriado', 'ti ti-calendar-event', 'Feriado: Nossa Senhora Aparecida', 'Calendário do GLPI', '12/10'),
                self::celebracao('tempo_casa', 'ti ti-confetti', 'Carlos Lima completa 5 anos de casa', 'Campo Sul', '15/10'),
            ],
            'pesquisas'   => self::minhaArea()['pesquisas'],
        ];
    }

    /**
     * Mural do colaborador (BP.3b): o mural como quadro, visto por quem
     * entra. Reproduz o canvas "Pessoas+ - Mural do colaborador" de
     * 07/10/2026: destaque fixo, campanha do mes fixada no topo (D-59),
     * aniversarios com cartao e comentarios (D-57), publicacoes, pesquisas
     * e celebracoes. Sem contagem de visualizacoes: isso e do RH.
     *
     * As duas imagens vem de public/demo/ e sao artes reais da Resolutto
     * cedidas para a casca. Aniversario so com dia e mes, de quem
     * consentiu (D-41). Comentarios sempre identificados (D-57).
     *
     * @return array<string, mixed>
     */
    public static function muralColaborador(): array
    {
        $mural = self::mural();

        $publicacoes = [];
        foreach ($mural['publicacoes'] as $i => $p) {
            $p['comentarios'] = [5, 0, 2, 3][$i] ?? 0;
            $p['comentarios_ligados'] = $i !== 1;
            unset($p['visualizacoes']);
            $publicacoes[] = $p;
        }

        $filtros   = $mural['filtros'];
        $filtros[] = ['chave' => 'celebracao', 'rotulo' => 'Celebração'];

        $celebracoes = $mural['celebracoes'];
        array_splice($celebracoes, 2, 0, [
            self::celebracao('aniversario', 'ti ti-cake', 'Aniversário de Pedro Henrique', 'Suporte N1', 'hoje'),
        ]);

        return [
            'referencia' => '07/10/2026',
            'resumo'     => [
                ['icone' => 'ti ti-speakerphone', 'valor' => '2', 'texto' => 'ciências pendentes · 1 em atraso', 'tom' => 'atraso', 'area' => 'minha_area'],
                ['icone' => 'ti ti-chart-bar', 'valor' => '1', 'texto' => 'pesquisa aberta', 'tom' => 'info', 'area' => 'minha_area'],
                ['icone' => 'ti ti-checklist', 'valor' => '2', 'texto' => 'tarefas do checklist', 'tom' => 'neutro', 'area' => 'minha_area'],
            ],
            'destaque'   => $mural['destaque'] + ['novo' => true],
            'campanha'   => [
                'rotulo'      => 'Campanha do mês · Outubro',
                'titulo'      => 'Outubro Rosa',
                'texto'       => 'Na quinta, 16/10, às 10h, roda de conversa com a enfermeira do convênio na copa da sede, aberta a todos. Use a cor rosa nas reuniões do mês.',
                'imagem'      => 'outubro-rosa.jpg',
                'alt'         => 'Outubro Rosa: o que você pode fazer hoje? Arte da campanha',
                'autor'       => 'RH',
                'periodo'     => 'de 01/10 a 31/10',
                'fixada'      => true,
                'comentarios' => 12,
                'curtidas'    => 48,
                'acoes'       => [
                    ['rotulo' => 'Confirmar presença na roda de conversa', 'primario' => true, 'bloco' => 'BP.7', 'acao' => 'Confirmar presença (enquete ligada à publicação)'],
                    ['rotulo' => 'Ler o material da campanha', 'primario' => false, 'bloco' => 'BP.3d', 'acao' => 'Abrir o material da campanha (documento anexo)'],
                ],
            ],
            'aniversarios' => [
                [
                    'nome'        => 'Pedro Henrique',
                    'iniciais'    => 'PH',
                    'quando'      => 'Hoje, 07/10',
                    'detalhe'     => 'Suporte N1 · há 1 ano na empresa',
                    'imagem'      => 'cartao-pedro.jpg',
                    'alt'         => 'Cartão de parabéns de Pedro Henrique, 07 de outubro',
                    'cartao'      => 'Que o seu novo ano seja cheio de boas entregas e boas risadas. Deixe sua mensagem abaixo!',
                    'curtidas'    => 23,
                    'aberto'      => true,
                    'total_comentarios' => 3,
                    'comentarios' => [
                        self::comentario('Ana Ribeiro', 'Administrativo · hoje', 'Feliz aniversário, Pedro! Que venha mais um ano de muito sucesso.', 4, [
                            self::comentario('Pedro Henrique', 'resposta · hoje', 'Valeu, Ana!', 0, []),
                        ]),
                        self::comentario('Lucas Ferreira', 'Campo Norte · hoje', 'Parabéns! Obrigado pela ajuda na minha primeira semana.', 1, []),
                    ],
                ],
                [
                    'nome'        => 'Rafael Souza',
                    'iniciais'    => 'RS',
                    'quando'      => 'Sábado, 11/10',
                    'detalhe'     => 'Frota · há 5 anos na empresa',
                    'imagem'      => '',
                    'alt'         => '',
                    'cartao'      => 'Cinco anos de estrada com a gente. Que o próximo ano traga tudo de bom.',
                    'curtidas'    => 19,
                    'aberto'      => false,
                    'total_comentarios' => 7,
                    'comentarios' => [],
                ],
            ],
            'filtros'     => $filtros,
            'publicacoes' => $publicacoes,
            'pesquisas'   => $mural['pesquisas'],
            'celebracoes' => $celebracoes,
            'ouvidoria'   => [
                'texto' => 'Dúvidas, sugestões, reclamações e relatos, com opção anônima e protocolo.',
                'bloco' => 'BP.3e',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $respostas
     *
     * @return array<string, mixed>
     */
    private static function comentario(string $autor, string $detalhe, string $texto, int $curtidas, array $respostas): array
    {
        return [
            'autor'     => $autor,
            'iniciais'  => self::iniciais($autor),
            'detalhe'   => $detalhe,
            'texto'     => $texto,
            'curtidas'  => $curtidas,
            'respostas' => $respostas,
        ];
    }

    /**
     * Aviso ao entrar (Tela 1 da proposta). Datas ajustadas para o fim
     * de setembro de 2026.
     *
     * @return array{titulo: string, subtitulo: string, itens: array<int, array<string, string>>}
     */
    public static function avisoEntrada(): array
    {
        return [
            'titulo'    => '2 comunicados aguardam sua ciência',
            'subtitulo' => 'Confirme para manter seus registros em dia',
            'itens'     => [
                [
                    'id'      => 1,
                    'titulo'  => 'Política de uso de veículos da frota',
                    'detalhe' => 'Normativa · revisão 02',
                    'selo'    => 'Atrasado',
                    'tom'     => 'atraso',
                ],
                [
                    'id'      => 2,
                    'titulo'  => 'Escala de plantão do feriado',
                    'detalhe' => 'Comunicado · RH',
                    'selo'    => 'Até 30/09',
                    'tom'     => 'alerta',
                ],
            ],
        ];
    }

    /**
     * @return array{rotulo: string, valor: string, detalhe: string, icone: string, tom: string, area: string}
     */
    private static function indicador(string $rotulo, string $valor, string $detalhe, string $icone, string $tom, string $area): array
    {
        return [
            'rotulo'  => $rotulo,
            'valor'   => $valor,
            'detalhe' => $detalhe,
            'icone'   => $icone,
            'tom'     => $tom,
            'area'    => $area,
        ];
    }

    /**
     * @return array{tom: string, titulo: string, detalhe: string, area: string, prazo: string}
     */
    private static function alerta(string $tom, string $titulo, string $detalhe, string $area, string $prazo): array
    {
        return [
            'tom'     => $tom,
            'titulo'  => $titulo,
            'detalhe' => $detalhe,
            'area'    => $area,
            'prazo'   => $prazo,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private static function processo(
        string $tipo,
        string $pessoa,
        string $contexto,
        string $etapa,
        int $feitos,
        int $total,
        string $prazo,
        string $tom,
        string $situacao,
        string $area
    ): array {
        return [
            'tipo'       => $tipo,
            'pessoa'     => $pessoa,
            'iniciais'   => self::iniciais($pessoa),
            'contexto'   => $contexto,
            'etapa'      => $etapa,
            'feitos'     => $feitos,
            'total'      => $total,
            'percentual' => $total > 0 ? (int) round($feitos * 100 / $total) : 0,
            'prazo'      => $prazo,
            'tom'        => $tom,
            'situacao'   => $situacao,
            'area'       => $area,
        ];
    }

    /**
     * @return array{dia: string, mes: string, titulo: string, origem: string, icone: string, area: string}
     */
    private static function evento(string $dia, string $mes, string $titulo, string $origem, string $icone, string $area): array
    {
        return [
            'dia'    => $dia,
            'mes'    => $mes,
            'titulo' => $titulo,
            'origem' => $origem,
            'icone'  => $icone,
            'area'   => $area,
        ];
    }

    /**
     * 'pagina' e o arquivo em front/ quando a acao ja existe na casca;
     * a pagina de front resolve a URL.
     *
     * @return array{area: string, rotulo: string, icone: string, bloco: string, pagina: string}
     */
    private static function atalho(string $area, string $rotulo, string $icone, string $bloco, string $pagina = ''): array
    {
        return [
            'area'   => $area,
            'rotulo' => $rotulo,
            'icone'  => $icone,
            'bloco'  => $bloco,
            'pagina' => $pagina,
        ];
    }

    /**
     * @return array{tipo: string, icone: string, titulo: string, detalhe: string, data: string}
     */
    private static function celebracao(string $tipo, string $icone, string $titulo, string $detalhe, string $data): array
    {
        return [
            'tipo'     => $tipo,
            'icone'    => $icone,
            'titulo'   => $titulo,
            'detalhe'  => $detalhe,
            'data'     => $data,
        ];
    }

    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/u', trim($nome)) ?: [];
        if ($partes === [] || $partes[0] === '') {
            return '?';
        }
        $primeira = mb_substr($partes[0], 0, 1);
        $ultima   = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';

        return mb_strtoupper($primeira . $ultima);
    }
}
