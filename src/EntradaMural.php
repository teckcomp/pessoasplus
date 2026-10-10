<?php

/**
 * Pessoas+ - Mural do colaborador ao entrar (D-54).
 *
 * PESSOASPLUS_BUILD_BP3C
 *
 * PESSOASPLUS_BUILD_BM3
 * PESSOASPLUS_BUILD_BM3_2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use Session;

/**
 * Desvia a primeira ida a pagina inicial da sessao para o Mural do
 * colaborador, para quem tem o direito "Abrir o Mural ao entrar".
 *
 * Conferido no fonte do GLPI 11.0.6:
 * - o login redireciona de forma fixa para /front/central.php ou
 *   /Helpdesk (Auth.php:1576-1587); com "abrir chamado ao entrar" vai para
 *   /ServiceCatalog ou para o formulario de chamado, que nao desviamos;
 * - /Helpdesk e rota GET (Helpdesk\IndexController.php:59-63);
 * - o post_init roda no PostBootEvent (InitializePlugins -> Plugin::init,
 *   Plugin.php:432), antes do listener que converte RedirectException em
 *   resposta: o desvio usa header()+exit, como o Task+ (T-44);
 * - os plugins sao carregados sem ORDER (Plugin.php:347): o Task+ pode
 *   desviar /front/central.php para a tela Hoje antes ou depois de nos.
 *   Por isso a tela Hoje tambem conta como pagina inicial, e a marca
 *   "ja decidido nesta sessao" e gravada antes do desvio: o "Continuar"
 *   volta a pagina inicial sem novo desvio, em qualquer ordem;
 * - no front controller do 11, PHP_SELF vale /index.php; o caminho certo
 *   vem de REQUEST_URI (mesma regra do Task+).
 *
 * A decisao fica em metodos puros, testados no harness; postInit() so
 * coleta o contexto e obedece.
 */
final class EntradaMural
{
    /** Direito "Abrir o Mural ao entrar". Nasce 0 para todos os perfis. */
    public const DIREITO = 'pessoasplus_mural_entrada';

    /** Marca de que a primeira ida a pagina inicial ja foi decidida. */
    public const SESSAO_DECIDIDO = 'plugin_pessoasplus_mural_entrada';

    /** Chave (nunca URL) da pagina inicial que a pessoa ia abrir. */
    public const SESSAO_DESTINO = 'plugin_pessoasplus_mural_destino';

    /**
     * Paginas iniciais reconhecidas: chave => caminho relativo a raiz.
     * O destino guardado e sempre uma destas chaves, entao o "Continuar"
     * nunca leva a um endereco vindo da requisicao (sem redirecionamento aberto).
     */
    public const INICIOS = [
        'central'  => '/front/central.php',
        'helpdesk' => '/Helpdesk',
        'taskplus' => '/plugins/taskplus/front/today.php',
    ];

    /**
     * Qual pagina inicial esta requisicao abre, ou null se nao e uma
     * ida a pagina inicial que possa ser desviada.
     *
     * Desvia so GET, sem query (fica de fora central.php?embed, a
     * "Visao Geral" do Task+ com taskplus_home=0, abas e filtros), sem
     * cabecalho enviado e fora de AJAX. /ServiceCatalog nunca entra.
     */
    public static function inicioDoPedido(
        bool $cli,
        string $metodo,
        ?string $uri,
        string $raiz,
        bool $cabecalhos_enviados,
        bool $ajax
    ): ?string {
        if ($cli || $cabecalhos_enviados || $ajax) {
            return null;
        }
        if (strtoupper($metodo) !== 'GET') {
            return null;
        }
        $uri = (string) $uri;
        if ($uri === '') {
            return null;
        }
        $query = parse_url($uri, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            return null;
        }
        $caminho = parse_url($uri, PHP_URL_PATH);
        if (!is_string($caminho)) {
            return null;
        }
        foreach (self::INICIOS as $chave => $relativo) {
            if ($caminho === $raiz . $relativo) {
                return $chave;
            }
        }

        return null;
    }

    /**
     * Decide e marca a sessao. So a primeira ida a pagina inicial conta:
     * depois dela nada mais e desviado nesta sessao, desviando ou nao.
     *
     * @param array<string, mixed> $sessao sessao do PHP, por referencia
     */
    public static function decidir(array &$sessao, ?string $inicio, bool $tem_direito, bool $tem_novidade, int $perfil = 0): bool
    {
        if ($inicio === null || !isset(self::INICIOS[$inicio])) {
            return false;
        }
        // BM.3-2: a decisao vale por perfil ativo. Trocar de perfil sem sair
        // (Super-Admin -> Auditoria) conta como nova entrada (T-81).
        $marca = 'p' . $perfil;
        if (!empty($sessao[self::SESSAO_DECIDIDO]) && (string) $sessao[self::SESSAO_DECIDIDO] === $marca) {
            return false;
        }

        // Marca antes de desviar: e o que impede o laco com o Task+.
        $sessao[self::SESSAO_DECIDIDO] = $marca;

        if (!$tem_direito || !$tem_novidade) {
            return false;
        }
        $sessao[self::SESSAO_DESTINO] = $inicio;

        return true;
    }

    /**
     * Destino do botao "Continuar para a pagina inicial". Sem destino
     * guardado (Mural aberto pelo menu), a pagina inicial da interface.
     *
     * @param array<string, mixed> $sessao
     */
    public static function urlContinuar(array $sessao, string $interface, string $raiz): string
    {
        $chave = (string) ($sessao[self::SESSAO_DESTINO] ?? '');
        if (isset(self::INICIOS[$chave])) {
            return $raiz . self::INICIOS[$chave];
        }

        return $raiz . ($interface === 'helpdesk' ? self::INICIOS['helpdesk'] : self::INICIOS['central']);
    }

    /**
     * Ha publicacao que a pessoa ainda nao viu? Na casca, sempre: o
     * desvio acontece uma vez por sessao. Na mobilia (B3.2) passa a
     * consultar as publicacoes do publico da pessoa.
     */
    public static function temNovidade(): bool
    {
        // BM.3: so desvia quando ha publicacao no ar, no publico da pessoa, nao vista.
        return Publicacao::temNovidadePara((int) \Session::getLoginUserID());
    }

    /**
     * Hook post_init. Registrado no setup.php so para quem tem os dois
     * direitos; confere de novo aqui porque o hook roda em toda requisicao.
     *
     * @param mixed $dados argumento do Plugin::doHook (ignorado)
     */
    public static function postInit($dados = null): void
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        if (Session::getLoginUserID() === false) {
            return;
        }
        $raiz   = (string) ($CFG_GLPI['root_doc'] ?? '');
        $inicio = self::inicioDoPedido(
            PHP_SAPI === 'cli',
            (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : null,
            $raiz,
            headers_sent(),
            strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        );
        if ($inicio === null) {
            return;
        }

        $tem_direito = (bool) Session::haveRight(Install::RIGHT_BASE, READ)
            && (bool) Session::haveRight(self::DIREITO, READ);

        if (!self::decidir($_SESSION, $inicio, $tem_direito, self::temNovidade(), (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0))) {
            return;
        }

        header('Location: ' . $raiz . MenuSimplificado::PAGINA_MURAL, true, 302);
        exit;
    }
}
