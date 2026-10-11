<?php

/**
 * Pessoas+ - contatos e informativos da Ouvidoria (BM.4-6), area do RH.
 *
 * PESSOASPLUS_BUILD_BM4_6
 *
 * Quem gerencia Campanhas de pesquisa edita. Gravado em glpi_configs.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\OuvidoriaConfig;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirGerencia('pesquisas');

$urlAqui = Pagina::url('/plugins/pessoasplus/front/ouvidoria_config.php');
if (isset($_POST['gravar'])) {
    OuvidoriaConfig::gravar($_POST);
    Session::addMessageAfterRedirect('Contatos e informativos da Ouvidoria gravados.', false, INFO);
    Html::redirect($urlAqui);
}

$valores = OuvidoriaConfig::ler();
$campos = [];
foreach (OuvidoriaConfig::CAMPOS as $chave => $def) {
    $campos[] = ['chave' => $chave, 'rotulo' => $def[0], 'tipo' => $def[2], 'valor' => $valores[$chave]];
}

$pp = Pagina::contexto('pesquisas', 'Ouvidoria: contatos e informativos', 'O que aparece na página da Ouvidoria para os colaboradores', [
    'campos'    => $campos,
    'url_aqui'  => $urlAqui,
    'url_lista' => Pagina::url('/plugins/pessoasplus/front/formularios.php'),
]);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/ouvidoria_config.html.twig', ['pp' => $pp]);
Pagina::rodape();
