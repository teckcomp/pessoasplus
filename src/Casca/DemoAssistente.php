<?php

/**
 * Pessoas+ - dados ficticios do assistente de chegada (casca, BP.4b).
 *
 * PESSOASPLUS_BUILD_BP4B
 * PESSOASPLUS_BUILD_BP4B_2 (D-69: sem entidade; empregador, cargo e local em listas livres do plugin)
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

/**
 * Tela 4 da proposta: assistente em seis etapas (dados, vinculo, TI, foto,
 * vaga, documentos) e a etapa final "o que sera criado".
 *
 * Tudo o que o JS precisa para montar o resumo final e calculado aqui,
 * no servidor, para existir uma so regra: a matriz vinculo x setor diz
 * quais modelos de checklist casam (D-65, todos os que casam) e quais
 * normativas "exigir na admissao" ficam pendentes (I-01).
 *
 * Cada campo traz um exemplo de preenchimento (D-68, proposta): o mesmo
 * texto serve de orientacao ao RH na producao.
 *
 * Nenhum campo de saude: o exame admissional (ASO) fica fora (D-66).
 */
final class DemoAssistente
{
    /** Rotulos curtos do indicador de etapas, como na Tela 4. */
    public const ETAPAS = [
        1 => ['Dados', 'Dados gerais'],
        2 => ['Vínculo', 'Empregador e vínculo'],
        3 => ['TI', 'Necessidades de TI para o primeiro dia'],
        4 => ['Foto', 'Foto'],
        5 => ['Vaga', 'Recrutamento e vaga'],
        6 => ['Documentos', 'Documentos exigidos pelo vínculo'],
        7 => ['Conferir', 'Conferir e concluir'],
    ];

    /** Rascunho que pode ser retomado: o do Bruno, parado na etapa 2. */
    public const RASCUNHO = 2;

    /**
     * Setores (grupo principal no GLPI) e o gestor do grupo
     * (glpi_groups_users.is_manager), sugerido na etapa 2.
     */
    public const SETORES = [
        'Campo Norte'    => 'Renata Costa',
        'Campo Sul'      => 'Paulo Mendes',
        'Suporte N1'     => 'Sérgio Lopes',
        'Administrativo' => 'Cláudia Ramos',
        'Projetos'       => 'Tatiane Moura',
    ];

    /**
     * Tipo de vinculo e o comportamento que ele liga (F1-04).
     *
     * @var array<string, array{ferias: bool, de_acordo: bool, medida: bool, inicio: string, nota: string}>
     */
    public const VINCULOS = [
        'CLT' => [
            'ferias'    => true,
            'de_acordo' => true,
            'medida'    => true,
            'inicio'    => 'Data de admissão',
            'nota'      => '',
        ],
        'PJ' => [
            'ferias'    => false,
            'de_acordo' => false,
            'medida'    => false,
            'inicio'    => 'Início do contrato',
            'nota'      => 'Prestador PJ: sem férias com aprovação do gestor, sem "De acordo" e sem medida disciplinar (só notificação contratual). Os itens do checklist de PJ estão a validar com o jurídico, pelo risco de caracterizar vínculo.',
        ],
        'Estágio' => [
            'ferias'    => false,
            'de_acordo' => true,
            'medida'    => false,
            'inicio'    => 'Início do estágio',
            'nota'      => 'Estágio tem recesso, não férias da CLT, e termo de compromisso com a instituição de ensino. Regras a validar com o RH e o jurídico.',
        ],
        'Jovem aprendiz' => [
            'ferias'    => true,
            'de_acordo' => true,
            'medida'    => true,
            'inicio'    => 'Data de admissão',
            'nota'      => 'O modelo "Chegada · jovem aprendiz" ainda está em rascunho, sem regra: hoje nenhum modelo casa com este vínculo.',
        ],
    ];

    /**
     * Regra de cada modelo de checklist de chegada (D-65). Chave igual a
     * DemoChegadas::modelos(). null em setores = qualquer setor.
     * Modelo inativo ou de transferencia nao entra no assistente.
     *
     * @var array<string, array{vinculos: string[], setores: string[]|null}>
     */
    public const REGRAS = [
        'base_clt' => ['vinculos' => ['CLT'], 'setores' => null],
        'campo'    => ['vinculos' => ['CLT'], 'setores' => ['Campo Norte', 'Campo Sul']],
        'pj'       => ['vinculos' => ['PJ'], 'setores' => null],
    ];

    /**
     * Normativas vigentes marcadas "exigir na admissao" e o publico delas.
     *
     * @var array<int, array{codigo: string, titulo: string, vinculos: string[]|null, setores: string[]|null}>
     */
    public const NORMATIVAS = [
        ['codigo' => 'REG0001:03', 'titulo' => 'Regimento interno', 'vinculos' => ['CLT', 'Estágio', 'Jovem aprendiz'], 'setores' => null],
        ['codigo' => 'POL0002:01', 'titulo' => 'Código de conduta', 'vinculos' => null, 'setores' => null],
        ['codigo' => 'NOR0004:02', 'titulo' => 'Uso de veículos da frota', 'vinculos' => null, 'setores' => ['Campo Norte', 'Campo Sul']],
    ];

    /**
     * Catalogo de necessidades de TI (D-10): cada item vira um chamado de
     * requisicao nativo na categoria e um item do checklist que se conclui
     * quando o chamado e solucionado (I-04).
     *
     * @var array<int, array{chave: string, titulo: string, categoria: string, item: string, dias: int, dica: string}>
     */
    public const TI = [
        ['chave' => 'notebook', 'titulo' => 'Notebook', 'categoria' => 'Requisição › Equipamentos', 'item' => 'Preparar notebook', 'dias' => -2, 'dica' => 'Para quem trabalha com sistemas o dia todo. O modelo do equipamento a TI define no chamado.'],
        ['chave' => 'conta', 'titulo' => 'Conta de rede e e-mail', 'categoria' => 'Requisição › Acessos', 'item' => 'Criar conta e e-mail', 'dias' => -3, 'dica' => 'Quase sempre marcado: sem conta a pessoa não confirma as normativas de admissão.'],
        ['chave' => 'glpi', 'titulo' => 'Acesso ao GLPI', 'categoria' => 'Requisição › Acessos', 'item' => 'Acesso ao GLPI', 'dias' => -1, 'dica' => 'O perfil sugerido vem do cargo; a TI confirma no chamado.'],
        ['chave' => 'celular', 'titulo' => 'Celular corporativo', 'categoria' => 'Requisição › Telefonia', 'item' => 'Entregar celular corporativo', 'dias' => -1, 'dica' => 'Para equipe de campo e plantão. Entra também na cautela do Fichário.'],
    ];

    /**
     * Cargo => perfil do GLPI sugerido no item "Acesso ao GLPI".
     */
    public const CARGOS = [
        'Técnico de campo'        => 'Técnico de campo',
        'Técnico N2'              => 'Técnico',
        'Analista administrativo' => 'Self-Service',
        'Analista de projetos'    => 'Técnico',
        'Estagiário de suporte'   => 'Observador',
    ];

    /**
     * Documentos exigidos por tipo de vinculo (lista configuravel na
     * mobilia). Sem ASO e sem dado bancario: saude fica fora (D-66) e
     * conta bancaria e assunto da folha (PP-1).
     *
     * @var array<string, string[]>
     */
    public const DOCUMENTOS = [
        'CLT'            => ['Documento de identidade com CPF', 'Carteira de trabalho digital', 'Comprovante de residência', 'Contrato de trabalho assinado'],
        'PJ'             => ['Contrato de prestação de serviços assinado', 'Cartão CNPJ', 'Contrato social ou CCMEI', 'Documento de identidade do responsável'],
        'Estágio'        => ['Termo de compromisso de estágio', 'Declaração de matrícula', 'Documento de identidade com CPF', 'Apólice do seguro de acidentes pessoais'],
        'Jovem aprendiz' => ['Contrato de aprendizagem', 'Declaração de matrícula escolar', 'Comprovante de inscrição no programa de aprendizagem', 'Documento de identidade com CPF'],
    ];

    public const ORIGENS = ['Indicação de colaborador', 'Site ou portal de vagas', 'Agência de recrutamento', 'Banco de talentos', 'Recontratação'];

    public const LOCAIS = ['Sede', 'Base Norte', 'Base Sul', 'Remoto'];

    public const EMPREGADORES = ['Resolutto'];

    /**
     * Usuarios do GLPI sem ficha, para o caso "a conta ja existe".
     */
    public const USUARIOS_SEM_FICHA = ['bruno.teixeira', 'carla.nunes', 'diego.santos'];

    /**
     * Modelos de checklist de chegada que casam com o vinculo e o setor
     * (D-65: todos os que casam, em ordem de cadastro).
     *
     * @return string[]
     */
    public static function modelosPara(string $vinculo, string $setor): array
    {
        $ativos = DemoChegadas::modelos();
        $saida  = [];
        foreach (self::REGRAS as $chave => $regra) {
            if (!($ativos[$chave]['ativo'] ?? false) || ($ativos[$chave]['tipo'] ?? '') !== 'Chegada') {
                continue;
            }
            if (!in_array($vinculo, $regra['vinculos'], true)) {
                continue;
            }
            if ($regra['setores'] !== null && !in_array($setor, $regra['setores'], true)) {
                continue;
            }
            $saida[] = $chave;
        }

        return $saida;
    }

    /**
     * Normativas "exigir na admissao" cujo publico inclui a pessoa.
     *
     * @return string[] codigos
     */
    public static function normativasPara(string $vinculo, string $setor): array
    {
        $saida = [];
        foreach (self::NORMATIVAS as $n) {
            if ($n['vinculos'] !== null && !in_array($vinculo, $n['vinculos'], true)) {
                continue;
            }
            if ($n['setores'] !== null && !in_array($setor, $n['setores'], true)) {
                continue;
            }
            $saida[] = $n['codigo'];
        }

        return $saida;
    }

    /**
     * Tudo o que a tela e o JS precisam. $id = rascunho a retomar, ou null.
     * Devolve null se o id nao for um rascunho conhecido.
     *
     * @return array<string, mixed>|null
     */
    public static function dados(?int $id): ?array
    {
        if ($id !== null && $id !== self::RASCUNHO) {
            return null;
        }

        $modelos = [];
        foreach (DemoChegadas::modelos() as $chave => $m) {
            if (!isset(self::REGRAS[$chave])) {
                continue;
            }
            $modelos[$chave] = [
                'nome'  => $m['nome'],
                'itens' => count($m['itens']),
                'regra' => implode(' · ', array_map(static fn (array $r): string => $r[0] . ' ' . $r[1], $m['regra'])),
            ];
        }

        $matriz = [];
        foreach (array_keys(self::VINCULOS) as $vinculo) {
            foreach (array_keys(self::SETORES) as $setor) {
                $matriz[$vinculo][$setor] = [
                    'modelos'    => self::modelosPara($vinculo, $setor),
                    'normativas' => self::normativasPara($vinculo, $setor),
                ];
            }
        }

        $normativas = [];
        foreach (self::NORMATIVAS as $n) {
            $normativas[$n['codigo']] = $n['titulo'];
        }

        $ti = [];
        foreach (self::TI as $t) {
            $ti[] = $t + ['prazo' => DemoChegadas::prazoRelativo($t['dias'])];
        }

        $vinculos = [];
        foreach (self::VINCULOS as $nome => $v) {
            $vinculos[] = ['nome' => $nome] + $v + ['documentos' => self::DOCUMENTOS[$nome]];
        }

        $config = [
            'setores'    => self::SETORES,
            'cargos'     => self::CARGOS,
            'vinculos'   => self::VINCULOS,
            'documentos' => self::DOCUMENTOS,
            'modelos'    => $modelos,
            'normativas' => $normativas,
            'matriz'     => $matriz,
            'ti'         => array_map(static fn (array $t): array => ['chave' => $t['chave'], 'titulo' => $t['titulo'], 'categoria' => $t['categoria'], 'item' => $t['item']], self::TI),
            'exemplo'    => self::exemplo(),
        ];

        $rascunho = $id === null ? null : self::rascunhoBruno();

        return [
            'etapas'       => array_map(static fn (array $e): array => ['curto' => $e[0], 'titulo' => $e[1]], self::ETAPAS),
            'etapa'        => $rascunho === null ? 1 : $rascunho['etapa'],
            'rascunho'     => $rascunho,
            'form'         => $rascunho === null ? self::formVazio() : $rascunho['form'],
            'setores'      => array_keys(self::SETORES),
            'cargos'       => array_keys(self::CARGOS),
            'vinculos'     => $vinculos,
            'empregadores' => self::EMPREGADORES,
            'locais'       => self::LOCAIS,
            'usuarios'     => self::USUARIOS_SEM_FICHA,
            'origens'      => self::ORIGENS,
            'ti'           => $ti,
            'config'       => $config,
        ];
    }

    /**
     * Formulario em branco. As chaves sao as mesmas do rascunho e do
     * exemplo, porque o Twig e estrito (T-06).
     *
     * @return array<string, string>
     */
    public static function formVazio(): array
    {
        return [
            'nome'       => '',
            'email'      => '',
            'telefone'   => '',
            'conta'      => 'pre',
            'usuario'    => '',
            'local'      => '',
            'empregador' => 'Resolutto',
            'vinculo'    => '',
            'cargo'      => '',
            'setor'      => '',
            'gestor'     => '',
            'inicio'     => '',
            'origem'     => '',
            'abertura'   => '',
            'obs_vaga'   => '',
            'obs_ti'     => '',
        ];
    }

    /**
     * Rascunho do Bruno Teixeira, parado na etapa 2 ha 3 dias (BP.4a).
     *
     * @return array{etapa: int, parado: int, pessoa: string, form: array<string, string>}
     */
    private static function rascunhoBruno(): array
    {
        $form = [
            'nome'     => 'Bruno Teixeira',
            'email'    => 'bruno.teixeira@exemplo.com.br',
            'conta'    => 'existe',
            'usuario'  => 'bruno.teixeira',
            'local'    => 'Sede',
            'vinculo'  => 'CLT',
            'cargo'    => 'Técnico N2',
            'setor'    => 'Suporte N1',
            'gestor'   => 'Sérgio Lopes',
            'inicio'   => '2026-10-13',
        ] + self::formVazio();

        return ['etapa' => 2, 'parado' => 3, 'pessoa' => 'Bruno Teixeira', 'form' => $form];
    }

    /**
     * Exemplo da proposta (Tela 4): Lucas Ferreira, tecnico de campo CLT
     * no Campo Norte, com 3 necessidades de TI. Preenche todas as etapas.
     *
     * @return array<string, mixed>
     */
    public static function exemplo(): array
    {
        return [
            'form'       => [
                'nome'       => 'Lucas Ferreira',
                'email'      => 'lucas.ferreira@exemplo.com.br',
                'telefone'   => '(41) 99999-0000',
                'conta'      => 'pre',
                'usuario'    => '',
                'local'      => 'Base Norte',
                'empregador' => 'Resolutto',
                'vinculo'    => 'CLT',
                'cargo'      => 'Técnico de campo',
                'setor'      => 'Campo Norte',
                'gestor'     => 'Renata Costa',
                'inicio'     => '2026-10-01',
                'origem'     => 'Indicação de colaborador',
                'abertura'   => '2026-08-25',
                'obs_vaga'   => 'Vaga de reposição na equipe da Base Norte.',
                'obs_ti'     => 'Precisa do notebook configurado com o sistema de rotas.',
            ],
            'ti'         => ['notebook', 'conta', 'glpi'],
            'documentos' => ['entregue', 'entregue', 'pendente', 'entregue'],
        ];
    }
}
