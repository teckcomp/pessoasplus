<?php

/**
 * Pessoas+ - papeis e direitos (D-51).
 *
 * PESSOASPLUS_BUILD_BP0C
 * PESSOASPLUS_BUILD_BP3B
 * PESSOASPLUS_BUILD_BP3C
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use Session;

/**
 * Tres papeis, representados por direitos nativos do GLPI (glpi_profilerights):
 *
 * - colaborador: "Usar o Pessoas+" (pessoasplus, Ler). Minha area, aba Mural,
 *   as proprias ciencias. E pre-requisito de tudo o mais;
 * - gestor: "Atuar como gestor" (pessoasplus_gestor, Ler). Tudo o que ve da
 *   equipe passa pela tela "Minha equipe", com escopo pelos grupos em que e
 *   gestor (is_manager). Nao abre as areas do RH. So na interface padrao
 *   enquanto a D-52 estiver pendente;
 * - RH: um direito por modulo, Ler (ver a area) e Gerenciar (UPDATE: criar,
 *   editar, publicar, lembrar).
 *
 * O direito e conferido com a sessao: mudanca no perfil so vale depois de
 * novo login (Session::haveRight le $_SESSION['glpiactiveprofile']).
 */
final class Permissoes
{
    public const GESTOR = 'pessoasplus_gestor';

    /** Valor do direito "Gerenciar" nas linhas de modulo. */
    public const GERENCIAR = UPDATE;

    /**
     * Modulos do RH: chave da area => [direito, rotulo, tem Gerenciar].
     * A chave e a mesma da navegacao (Casca\Navegacao) e de Fontes.
     */
    public const MODULOS = [
        'comunicados'   => ['pessoasplus_comunicados', 'Comunicados e normativas', true],
        'mural'         => ['pessoasplus_mural', 'Publicações do mural', true],
        'pesquisas'     => ['pessoasplus_pesquisas', 'Campanhas de pesquisa', true],
        'chegadas'      => ['pessoasplus_chegadas', 'Chegadas e checklists', true],
        'ferias'        => ['pessoasplus_ferias', 'Férias e ausências', true],
        'desligamentos' => ['pessoasplus_desligamentos', 'Desligamentos', true],
        'cautela'       => ['pessoasplus_cautela', 'Itens em cautela', true],
        'fichario'      => ['pessoasplus_fichario', 'Fichário', true],
        'registro'      => ['pessoasplus_registro', 'Registro funcional', true],
        'desempenho'    => ['pessoasplus_desempenho', 'Desempenho e PDI', true],
        'indicadores'   => ['pessoasplus_indicadores', 'Indicadores', false],
    ];

    /**
     * Todos os direitos do plugin, para instalar e desinstalar.
     *
     * @return string[]
     */
    public static function todosOsDireitos(): array
    {
        return array_merge(
            [Install::RIGHT_BASE, self::GESTOR, EntradaMural::DIREITO],
            array_column(self::MODULOS, 0),
            [Install::RIGHT_CONFIG]
        );
    }

    public static function usa(): bool
    {
        return Session::getLoginUserID() !== false
            && (bool) Session::haveRight(Install::RIGHT_BASE, READ);
    }

    public static function le(string $modulo): bool
    {
        return isset(self::MODULOS[$modulo])
            && self::usa()
            && (bool) Session::haveRight(self::MODULOS[$modulo][0], READ);
    }

    public static function gerencia(string $modulo): bool
    {
        return self::le($modulo)
            && self::MODULOS[$modulo][2]
            && (bool) Session::haveRight(self::MODULOS[$modulo][0], self::GERENCIAR);
    }

    public static function gestor(): bool
    {
        return self::usa()
            && Session::getCurrentInterface() !== 'helpdesk'
            && (bool) Session::haveRight(self::GESTOR, READ);
    }

    public static function configura(): bool
    {
        return self::usa()
            && Session::getCurrentInterface() !== 'helpdesk'
            && (bool) Session::haveRight(Install::RIGHT_CONFIG, READ);
    }

    /** Le pelo menos um modulo do RH: ve o Painel do RH. */
    public static function rh(): bool
    {
        if (Session::getCurrentInterface() === 'helpdesk') {
            return false;
        }
        foreach (array_keys(self::MODULOS) as $modulo) {
            if (self::le($modulo)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mapa para os templates: toda chave existe (T-06).
     *
     * @return array<string, array{ler: bool, gerenciar: bool}>
     */
    public static function mapa(): array
    {
        $saida = [];
        foreach (array_keys(self::MODULOS) as $modulo) {
            $saida[$modulo] = ['ler' => self::le($modulo), 'gerenciar' => self::gerencia($modulo)];
        }

        return $saida;
    }

    /**
     * Acesso por area da navegacao (chaves de Casca\Navegacao::mapa).
     *
     * @return array<string, bool>
     */
    public static function areas(): array
    {
        $saida = [
            'inicio'            => self::rh(),
            'minha_area'        => self::usa(),
            'mural_colaborador' => self::usa(),
            'ouvidoria'         => self::usa(),
            'equipe'            => self::gestor(),
            'configuracao'      => self::configura(),
        ];
        foreach (array_keys(self::MODULOS) as $modulo) {
            $saida[$modulo] = self::le($modulo);
        }

        return $saida;
    }

    /**
     * Barra a pagina com 403 (AccessDeniedHttpException, via o core).
     * Usa o proprio direito do modulo para a mensagem do GLPI.
     */
    public static function exigirLeitura(string $modulo): void
    {
        Session::checkRight(Install::RIGHT_BASE, READ);
        Session::checkRight(self::MODULOS[$modulo][0], READ);
    }

    public static function exigirGerencia(string $modulo): void
    {
        self::exigirLeitura($modulo);
        Session::checkRight(self::MODULOS[$modulo][0], self::GERENCIAR);
    }

    /**
     * Filtra o Painel do RH pelo que o usuario le (indicadores, alertas,
     * processos, agenda) e gerencia (atalhos). Item com area '' e de todos.
     *
     * @param array<string, mixed>                              $dados
     * @param array<string, array{ler: bool, gerenciar: bool}> $pode
     *
     * @return array<string, mixed>
     */
    public static function filtrarPainel(array $dados, array $pode): array
    {
        $le = static fn (array $item): bool => $item['area'] === '' || ($pode[$item['area']]['ler'] ?? false);
        foreach (['indicadores', 'alertas', 'processos', 'agenda'] as $lista) {
            $dados[$lista] = array_values(array_filter($dados[$lista], $le));
        }
        $dados['atalhos'] = array_values(array_filter(
            $dados['atalhos'],
            static fn (array $a): bool => $pode[$a['area']]['gerenciar'] ?? false
        ));

        return $dados;
    }
}
