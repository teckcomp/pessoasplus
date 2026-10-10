<?php

/**
 * Pessoas+ - fonte dos dados de cada tela.
 *
 * PESSOASPLUS_BUILD_BP0
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2A
 * PESSOASPLUS_BUILD_BP2B
 * PESSOASPLUS_BUILD_BP2C
 * PESSOASPLUS_BUILD_BP3A
 * PESSOASPLUS_BUILD_BP3B
 * PESSOASPLUS_BUILD_BP3D
 * PESSOASPLUS_BUILD_BP3E
 * PESSOASPLUS_BUILD_BP4A
 * PESSOASPLUS_BUILD_BP4B
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

/**
 * Ponto unico de onde as telas tiram seus dados.
 *
 * Na casca, tudo vem de DadosDemo. Na mobilia, cada modulo passa a ler do
 * banco aqui mesmo e sai da lista EM_DEMONSTRACAO, o que tira a faixa de
 * demonstracao das telas daquele modulo. As telas nao mudam.
 */
final class Fontes
{
    /**
     * Modulos que ainda usam dados ficticios.
     *
     * @var string[]
     */
    private const EM_DEMONSTRACAO = [
        'inicio',
        'minha_area',
        'comunicados',
        'mural',
        'mural_colaborador',
        'ouvidoria',
        'pesquisas',
        'chegadas',
        'ferias',
        'desligamentos',
        'cautela',
        'registro',
        'desempenho',
        'indicadores',
        'configuracao',
    ];

    public static function emDemonstracao(string $modulo): bool
    {
        return in_array($modulo, self::EM_DEMONSTRACAO, true);
    }

    /**
     * Dados do painel inicial do RH.
     *
     * @return array<string, mixed>
     */
    public static function painelRh(): array
    {
        return DadosDemo::painelRh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function comunicados(): array
    {
        return DadosDemo::comunicados();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function comunicado(int $id): ?array
    {
        return DadosDemo::comunicado($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function leitura(int $id): ?array
    {
        return DadosDemo::leitura($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function comprovante(int $id, ?int $pessoa): ?array
    {
        return DadosDemo::comprovante($id, $pessoa);
    }

    /**
     * @return array<string, mixed>
     */
    public static function novoComunicado(): array
    {
        return DadosDemo::novoComunicado();
    }

    /**
     * Dados da Minha area do colaborador.
     *
     * @return array<string, mixed>
     */
    public static function minhaArea(): array
    {
        return DadosDemo::minhaArea();
    }

    /**
     * Mural e celebracoes: aba da pagina inicial e area do RH.
     *
     * @return array<string, mixed>
     */
    public static function mural(): array
    {
        return DadosDemo::mural();
    }

    /**
     * Gestao das publicacoes do mural (BP.3d): lista por periodo e situacao.
     * Na mobilia (B3.1), vem da tabela de publicacoes.
     *
     * @return array<string, mixed>
     */
    public static function publicacoes(): array
    {
        return DadosDemo::publicacoesGestao();
    }

    /**
     * Formulario de publicacao: em branco, ou a publicacao $id para editar.
     *
     * @return array<string, mixed>
     */
    public static function novaPublicacao(?int $id): array
    {
        return DadosDemo::novaPublicacao($id);
    }

    /**
     * Mural do colaborador: pagina "Para mim > Mural" nas duas interfaces.
     *
     * @return array<string, mixed>
     */
    public static function muralColaborador(): array
    {
        return DadosDemo::muralColaborador();
    }

    /**
     * Ouvidoria (BP.3e): portas, protocolos, respaldo legal, informativos e
     * contatos. Na mobilia (B7.9), portas e protocolos vem do motor da E7.
     *
     * @return array<string, mixed>
     */
    public static function ouvidoria(): array
    {
        return DadosDemo::ouvidoria();
    }

    /**
     * Chegadas e checklists (BP.4a): movimentacoes, prazos e modelos de
     * checklist. Na mobilia: B4.1 a B4.4 (modelos, regras, instancias e
     * alertas) e B4.9 (transferencia).
     *
     * @return array<string, mixed>
     */
    public static function chegadas(): array
    {
        return DemoChegadas::area();
    }

    /**
     * Checklist de uma movimentacao (Tela 5). null para id desconhecido ou
     * rascunho, que ainda nao tem checklist.
     *
     * @return array<string, mixed>|null
     */
    public static function movimentacao(int $id): ?array
    {
        return DemoChegadas::movimentacao($id);
    }

    /**
     * Assistente de chegada (BP.4b, Tela 4): em branco, ou o rascunho $id
     * a retomar. null para id que nao e rascunho. Na mobilia: B4.5 a B4.8.
     *
     * @return array<string, mixed>|null
     */
    public static function novaChegada(?int $id): ?array
    {
        return DemoAssistente::dados($id);
    }

    /**
     * Conteudo do aviso ao entrar.
     *
     * @return array{titulo: string, subtitulo: string, itens: array<int, array<string, string>>}
     */
    public static function avisoEntrada(): array
    {
        return DadosDemo::avisoEntrada();
    }
}
