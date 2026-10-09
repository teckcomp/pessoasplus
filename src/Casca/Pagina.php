<?php

/**
 * Pessoas+ - montagem comum das paginas da casca.
 *
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP2A
 * PESSOASPLUS_BUILD_BP0C
 * PESSOASPLUS_BUILD_BP3B
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus\Casca;

use GlpiPlugin\Pessoasplus\Menu;
use GlpiPlugin\Pessoasplus\Permissoes;
use Html;
use Session;

/**
 * Tudo que toda pagina do Pessoas+ repete: cabecalho do GLPI conforme a
 * interface do perfil, variavel "pp" do template e rodape.
 *
 * Na interface simplificada a pagina abre com Html::helpHeader, marcando
 * o item do menu informado (Minha area por padrao, ou Mural), e sem a
 * navegacao interna do RH. Na padrao, abre em Ferramentas com a navegacao completa.
 */
final class Pagina
{
    public static function interfaceAtual(): string
    {
        $interface = Session::getCurrentInterface();

        return $interface === 'helpdesk' ? 'helpdesk' : 'central';
    }

    /**
     * Telas do RH e do emissor nao existem na interface simplificada:
     * quem chega por la vai para a Minha area.
     */
    public static function somenteInterfacePadrao(): void
    {
        if (self::interfaceAtual() === 'helpdesk') {
            Html::redirect(self::url(MenuSimplificado::PAGINA));
        }
    }

    public static function url(string $caminho): string
    {
        return Html::getPrefixedUrl($caminho);
    }

    /**
     * @param array<string, mixed> $dados
     *
     * @return array<string, mixed>
     */
    public static function contexto(string $modulo, string $titulo, string $subtitulo, array $dados): array
    {
        $url          = static fn (string $caminho): string => self::url($caminho);
        $simplificada = self::interfaceAtual() === 'helpdesk';
        $pode_config  = Permissoes::configura();
        $acesso       = Permissoes::areas();
        $com_menu     = !$simplificada && Navegacao::precisaDeMenu($acesso);

        return [
            'css'         => $url('/plugins/pessoasplus/pessoasplus.css') . '?v=' . Navegacao::BUILD,
            'js'          => $url('/plugins/pessoasplus/pessoasplus.js') . '?v=' . Navegacao::BUILD,
            'demo'        => Fontes::emDemonstracao($modulo),
            'identidade'  => Identidade::atual(),
            'pode_config' => $pode_config,
            'pagina'      => ['titulo' => $titulo, 'subtitulo' => $subtitulo],
            'pode'        => Permissoes::mapa(),
            'nav'         => $com_menu ? Navegacao::montar($modulo, $acesso, $url) : [],
            'areas'       => Navegacao::areas($url, $acesso),
            'dados'       => $dados,
        ];
    }

    /**
     * @param string $setor item do menu simplificado a marcar como ativo
     */
    public static function cabecalho(string $titulo, string $setor = MenuSimplificado::CHAVE): void
    {
        if (self::interfaceAtual() === 'helpdesk') {
            Html::helpHeader($titulo, $setor);

            return;
        }

        Html::header(Menu::getTypeName(), '', 'tools', strtolower(Menu::class));
    }

    public static function rodape(): void
    {
        if (self::interfaceAtual() === 'helpdesk') {
            Html::helpFooter();

            return;
        }

        Html::footer();
    }
}
