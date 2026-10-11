<?php

/**
 * Pessoas+ - Ouvidoria (BM.4, real): portas e consulta de protocolo.
 *
 * PESSOASPLUS_BUILD_BP3E
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_6
 *
 * Consulta por POST (T-55): protocolo + codigo nunca vao na URL.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Menu;
use GlpiPlugin\Pessoasplus\OuvidoriaConfig;
use GlpiPlugin\Pessoasplus\Permissoes;

Session::checkRight(Menu::$rightname, READ);

$resultado = null;
$consultou = false;
if (isset($_POST['consultar'])) {
    $consultou = true;
    $resultado = Formulario::consultar((string) ($_POST['protocolo'] ?? ''), (string) ($_POST['codigo'] ?? ''));
}

$portas = [];
foreach (Formulario::PORTAS as $chave => $def) {
    $f = Formulario::porta($chave);
    $portas[] = [
        'chave'  => $chave,
        'titulo' => $def[0],
        'texto'  => $f !== null ? (string) $f->fields['descricao'] : $def[1],
        'modo'   => $f !== null ? (Formulario::MODOS[$f->fields['modo']] ?? $f->fields['modo']) : Formulario::MODOS[$def[2]],
        'tom'    => $def[3],
        'icone'  => $def[4],
        'url'    => $f !== null ? Pagina::url('/plugins/pessoasplus/front/responder.php') . '?porta=' . $chave : '',
        'url_publica' => $f !== null ? $f->urlPublica() : '',
    ];
}

$conduta = Formulario::porta('conduta');
$dados = OuvidoriaConfig::paraTela() + [
    'portas'     => $portas,
    'qr_url'     => $conduta !== null && $conduta->urlPublica() !== '' ? Pagina::url('/plugins/pessoasplus/front/qr.php') . '?t=' . $conduta->fields['token'] : '',
    'cartaz_url' => $conduta !== null && $conduta->urlPublica() !== '' && Permissoes::le('pesquisas') ? Pagina::url('/plugins/pessoasplus/front/qr.php') . '?id=' . (int) $conduta->getID() . '&cartaz=1' : '',
    'url_config' => Permissoes::gerencia('pesquisas') ? Pagina::url('/plugins/pessoasplus/front/ouvidoria_config.php') : '',
    'resultado'  => $resultado,
    'consultou'  => $consultou,
    'url_aqui'   => Pagina::url(MenuSimplificado::PAGINA_OUVIDORIA),
    'passos'     => [
        ['titulo' => 'Você escolhe a porta', 'texto' => 'acima. Cada porta vai para uma fila diferente do RH; relato de conduta vai só para quem foi designado.'],
        ['titulo' => 'Você escolhe se quer se identificar', 'texto' => 'onde a porta permite. No modo anônimo o envio não grava usuário nem hora, só o dia.'],
        ['titulo' => 'Recebe um protocolo e um código', 'texto' => 'na tela, uma única vez. Guarde os dois: são a única forma de acompanhar um envio anônimo.'],
        ['titulo' => 'Acompanha pelo protocolo', 'texto' => 'aqui mesmo: situação, resposta do RH e pedido de complemento, sem revelar quem você é.'],
    ],
];

$pp = Pagina::contexto('ouvidoria', 'Ouvidoria', 'Dúvidas, sugestões, reclamações e relatos, com opção anônima e protocolo', $dados);

Pagina::cabecalho('Ouvidoria', MenuSimplificado::CHAVE_OUVIDORIA);
TemplateRenderer::getInstance()->display('@pessoasplus/casca/ouvidoria.html.twig', ['pp' => $pp]);
Pagina::rodape();
