<?php

/**
 * Pessoas+ - navegacao interna da casca.
 *
 * PESSOASPLUS_BUILD_BP0
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2A
 * PESSOASPLUS_BUILD_BP2B
 * PESSOASPLUS_BUILD_BP2C
 * PESSOASPLUS_BUILD_BP3A
 * PESSOASPLUS_BUILD_BP0C
 * PESSOASPLUS_BUILD_BP3B
 * PESSOASPLUS_BUILD_BP3C
 * PESSOASPLUS_BUILD_BP3D
 * PESSOASPLUS_BUILD_BP3E
 * PESSOASPLUS_BUILD_BP4A
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

/**
 * Mapa de todas as areas do Pessoas+.
 *
 * Cada area aponta para a sua pagina em front/. Enquanto a pagina nao
 * existe (bloco da casca ainda nao entregue), a area aparece no menu
 * como "em breve", sem link. Ao entregar um bloco, basta preencher 'pagina'.
 */
final class Navegacao
{
    /** Raiz das paginas do plugin, relativa a raiz do GLPI (T-21). */
    public const RAIZ = '/plugins/pessoasplus/front/';

    /** Build da casca, usado para quebrar o cache do CSS e do JS. */
    public const BUILD = 'bp4a-1';

    /**
     * Grupos e areas, na ordem em que aparecem (menu por papel, D-55):
     * "Para mim" e de todo colaborador, "Gestao da equipe" do gestor,
     * "Gestao do RH" por modulo e "Administracao" de quem configura.
     * 'pagina' null = ainda nao entregue. Quem ve cada area e decidido por
     * Permissoes::areas() (D-51); 'config' ficou so como marcacao.
     *
     * @return array<int, array{grupo: string, itens: array<int, array{chave: string, rotulo: string, icone: string, pagina: ?string, bloco: string, config: bool}>}>
     */
    public static function mapa(): array
    {
        return [
            [
                'grupo' => 'Para mim',
                'itens' => [
                    // D-62: o Mural vem primeiro, porque tem prioridade na entrada (D-54).
                    self::item('mural_colaborador', 'Mural', 'ti ti-layout-board', 'mural_colaborador.php', 'BP.3b'),
                    self::item('minha_area', 'Minha área', 'ti ti-user-check', 'minha_area.php', 'BP.1'),
                    self::item('ouvidoria', 'Ouvidoria', 'ti ti-message-circle-heart', 'ouvidoria.php', 'BP.3e'),
                ],
            ],
            [
                'grupo' => 'Gestão da equipe',
                'itens' => [
                    self::item('equipe', 'Minha equipe', 'ti ti-users-group', null, 'BP.11'),
                ],
            ],
            [
                'grupo' => 'Gestão do RH',
                'itens' => [
                    self::item('inicio', 'Painel do RH', 'ti ti-layout-dashboard', 'menu.php', 'BP.0'),
                    self::item('comunicados', 'Comunicados e normativas', 'ti ti-speakerphone', 'comunicados.php', 'BP.2'),
                    self::item('mural', 'Publicações do mural', 'ti ti-news', 'mural.php', 'BP.3'),
                    self::item('pesquisas', 'Campanhas de pesquisa', 'ti ti-chart-bar', null, 'BP.7'),
                    self::item('chegadas', 'Chegadas e checklists', 'ti ti-door-enter', 'chegadas.php', 'BP.4a'),
                    self::item('ferias', 'Férias e ausências', 'ti ti-beach', null, 'BP.5'),
                    self::item('desligamentos', 'Desligamentos', 'ti ti-door-exit', null, 'BP.8'),
                    self::item('cautela', 'Itens em cautela', 'ti ti-tool', null, 'BP.10'),
                    self::item('fichario', 'Fichário', 'ti ti-folders', null, 'BP.6'),
                    self::item('registro', 'Registro funcional', 'ti ti-timeline', null, 'BP.8'),
                    self::item('desempenho', 'Desempenho e PDI', 'ti ti-target-arrow', null, 'BP.9'),
                    self::item('indicadores', 'Indicadores', 'ti ti-chart-line', null, 'BP.9'),
                ],
            ],
            [
                'grupo' => 'Administração',
                'itens' => [
                    self::item('configuracao', 'Configuração', 'ti ti-settings', null, 'BP.10', true),
                ],
            ],
        ];
    }

    /**
     * Monta a navegacao pronta para o template, so com as areas que o
     * usuario pode abrir.
     *
     * @param string              $ativa  chave da area aberta
     * @param array<string, bool> $acesso chave da area => pode abrir (Permissoes::areas)
     * @param callable            $url    recebe caminho relativo a raiz, devolve URL
     *
     * @return array<int, array{grupo: string, itens: array<int, array<string, mixed>>}>
     */
    public static function montar(string $ativa, array $acesso, callable $url): array
    {
        $saida = [];
        foreach (self::mapa() as $grupo) {
            $itens = [];
            foreach ($grupo['itens'] as $item) {
                if (!($acesso[$item['chave']] ?? false)) {
                    continue;
                }
                $itens[] = [
                    'chave'  => $item['chave'],
                    'rotulo' => $item['rotulo'],
                    'icone'  => $item['icone'],
                    'url'    => $item['pagina'] === null ? '' : (string) $url(self::RAIZ . $item['pagina']),
                    'pronta' => $item['pagina'] !== null,
                    'ativa'  => $item['chave'] === $ativa,
                    'bloco'  => $item['bloco'],
                ];
            }
            if ($itens !== []) {
                $saida[] = ['grupo' => $grupo['grupo'], 'itens' => $itens];
            }
        }

        return $saida;
    }

    /**
     * Desde a D-55 todo colaborador tem menu lateral na interface padrao
     * ("Para mim": Mural, Minha area, Ouvidoria). Fica sem menu so quem
     * nao alcanca nenhuma area, caso que o 403 do core ja barra antes.
     *
     * @param array<string, bool> $acesso
     */
    public static function precisaDeMenu(array $acesso): bool
    {
        foreach ($acesso as $pode) {
            if ($pode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mapa chave => URL de todas as areas, com string vazia para as que
     * ainda nao tem pagina ou que o usuario nao pode abrir. Usado nos links
     * de alertas e indicadores: toda chave existe (T-06), e nenhum link
     * leva a um 403.
     *
     * @param callable            $url    recebe caminho relativo a raiz, devolve URL
     * @param array<string, bool> $acesso
     *
     * @return array<string, string>
     */
    public static function areas(callable $url, array $acesso): array
    {
        $saida = [];
        foreach (self::mapa() as $grupo) {
            foreach ($grupo['itens'] as $item) {
                $pode = $acesso[$item['chave']] ?? false;
                $saida[$item['chave']] = ($item['pagina'] === null || !$pode) ? '' : (string) $url(self::RAIZ . $item['pagina']);
            }
        }

        return $saida;
    }

    /**
     * @return array{chave: string, rotulo: string, icone: string, pagina: ?string, bloco: string, config: bool}
     */
    private static function item(
        string $chave,
        string $rotulo,
        string $icone,
        ?string $pagina,
        string $bloco,
        bool $config = false
    ): array {
        return [
            'chave'  => $chave,
            'rotulo' => $rotulo,
            'icone'  => $icone,
            'pagina' => $pagina,
            'bloco'  => $bloco,
            'config' => $config,
        ];
    }
}
