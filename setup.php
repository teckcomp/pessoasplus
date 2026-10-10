<?php

/**
 * Pessoas+ - plugin de RH para o GLPI 11
 *
 * PESSOASPLUS_BUILD_B01_2
 * PESSOASPLUS_BUILD_B01B
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BP3A
 * PESSOASPLUS_BUILD_BP3B
 * PESSOASPLUS_BUILD_BP3C
 * PESSOASPLUS_BUILD_BM1
 * PESSOASPLUS_BUILD_BM2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\MuralAba;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\EntradaMural;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Menu;
use GlpiPlugin\Pessoasplus\PerfilDireitos;

define('PLUGIN_PESSOASPLUS_VERSION', '0.1.0');

// Faixa de compatibilidade. O core compara com ">= min" e "< max".
define('PLUGIN_PESSOASPLUS_MIN_GLPI', '11.0.6');
define('PLUGIN_PESSOASPLUS_MAX_GLPI', '12.0');

/**
 * Inicializacao do plugin. Chamada pelo core apenas quando o plugin esta ativo.
 *
 * Observacao (GLPI 11): o hook 'csrf_compliant' nao e mais lido pelo core
 * (Glpi\Plugin\HookManager::enableCSRF esta marcado como deprecated), por isso
 * ele nao e declarado aqui.
 */
function plugin_init_pessoasplus(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    // Entrada no menu "Ferramentas" da interface padrao.
    // Html::generateMenuSession() chama Menu::getMenuContent() para montar o item.
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['pessoasplus'] = [
        'tools' => Menu::class,
    ];

    // Aba "Pessoas+" na tela de perfil, para conceder os direitos do plugin
    // sem SQL (B0.1b, D-29). Plugin::registerClass com 'addtabon' registra
    // a aba padrao em Profile (conferido no 11.0.6, Plugin.php:1726).
    Plugin::registerClass(PerfilDireitos::class, ['addtabon' => [Profile::class]]);

    // BM.1: ficha do colaborador. 'document_types' poe a classe em
    // $CFG_GLPI['document_types'] (Plugin.php:1702-1714), o que libera a aba
    // nativa de Documentos (D-37); 'addtabon' User poe a aba "Pessoas+" na
    // ficha do usuario. A classe confere o direito de novo ao exibir.
    Plugin::registerClass(Colaborador::class, ['addtabon' => [User::class], 'document_types' => true]);
    // BM.2: anexos do rascunho pela aba nativa de Documentos (D-09).
    Plugin::registerClass(Comunicado::class, ['document_types' => true]);

    // Daqui para baixo, so para usuario logado com o direito do Pessoas+.
    // A sessao ja esta aberta quando o plugin_init roda (T-36), entao quem
    // nao usa o Pessoas+ nao carrega nada disto (base compartilhada).
    if (Session::getLoginUserID() === false || !Session::haveRight(Install::RIGHT_BASE, READ)) {
        return;
    }

    // Aba "Mural" na pagina inicial das duas interfaces (V-12, T-41).
    // A home nao tem hook, mas Central e HomePageTabs aceitam aba por
    // 'addtabon'. A classe confere o direito de novo ao exibir.
    Plugin::registerClass(MuralAba::class, ['addtabon' => MuralAba::TELAS]);

    // "Minha area" e "Mural" no primeiro nivel do menu simplificado (V-01, T-37, D-55).
    $PLUGIN_HOOKS[Hooks::REDEFINE_MENUS]['pessoasplus'] = [MenuSimplificado::class, 'redefinir'];

    // Aviso ao entrar, nas duas interfaces (V-02). Os arquivos ficam em
    // public/ e sao servidos em /plugins/pessoasplus/<arquivo> (T-22, T-35).
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['pessoasplus'] = ['aviso.js'];
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['pessoasplus']        = ['aviso.css'];

    // Mural do colaborador ao entrar (D-54, BP.3c): so para quem tem
    // "Abrir o Mural ao entrar", que nasce desmarcado. O post_init roda em
    // toda requisicao (Plugin.php:432); a decisao fica em EntradaMural.
    if (Session::haveRight(EntradaMural::DIREITO, READ)) {
        $PLUGIN_HOOKS[Hooks::POST_INIT]['pessoasplus'] = [EntradaMural::class, 'postInit'];
    }
}

/**
 * Informacoes do plugin, lidas pelo core (tela de plugins e console).
 *
 * @return array
 */
function plugin_version_pessoasplus(): array
{
    return [
        'name'           => 'Pessoas+',
        'version'        => PLUGIN_PESSOASPLUS_VERSION,
        'author'         => 'Teckcomp',
        'license'        => 'GPLv2+',
        'homepage'       => 'https://github.com/teckcomp/pessoasplus',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_PESSOASPLUS_MIN_GLPI,
                'max' => PLUGIN_PESSOASPLUS_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.2',
            ],
        ],
    ];
}

/**
 * Pre-requisitos verificados antes de instalar e antes de ativar.
 * A faixa de versao ja e verificada pelo core a partir de 'requirements'.
 *
 * @return bool
 */
function plugin_pessoasplus_check_prerequisites(): bool
{
    return true;
}

/**
 * Configuracao minima necessaria para o plugin funcionar.
 * Enquanto retornar true o plugin instala direto como "nao ativado",
 * sem passar pelo estado "a configurar".
 *
 * @param bool $verbose
 *
 * @return bool
 */
function plugin_pessoasplus_check_config($verbose = false): bool
{
    return true;
}
