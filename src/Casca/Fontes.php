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
        'fichario',
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
     * Mural do colaborador: pagina "Para mim > Mural" nas duas interfaces.
     *
     * @return array<string, mixed>
     */
    public static function muralColaborador(): array
    {
        return DadosDemo::muralColaborador();
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
