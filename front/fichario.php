<?php

/**
 * Pessoas+ - Fichario: grade de pastas por setor (BM.1, D-34).
 *
 * PESSOASPLUS_BUILD_BM1
 * PESSOASPLUS_BUILD_BM1_4 (D-85: cartao da grade no padrao creme do mockup; CSS proprio fichario_cartao.css)
 *
 * RH com Fichario Ler ve todas as pastas; gestor ve as dos grupos que
 * gere (D-36); colaborador sem nenhum dos dois cai na propria pasta.
 * Filtros por GET (busca, setor, situacao, vinculo): o link funciona sem JS.
 *
 * PESSOASPLUS_BUILD_BM8_1 (lixeira; atalho para documentos de admissao)
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Fichario;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Session::checkRight(Install::RIGHT_BASE, READ);

if (!Permissoes::le('fichario') && !Permissoes::gestor()) {
    $propria = Colaborador::porUsuario((int) Session::getLoginUserID());
    if ($propria !== null) {
        Html::redirect(Pagina::url($propria->getFormURLWithID((int) $propria->getID(), false)));
    }
    Session::checkRight(Permissoes::MODULOS['fichario'][0], READ); // 403 do core
}

$filtros = Fichario::filtros($_GET);
$grade   = Fichario::grade($filtros);
$base    = Pagina::url('/plugins/pessoasplus/front/fichario.php');

$situacoes = [];
foreach (Colaborador::SITUACOES as $chave => $par) {
    $situacoes[] = ['chave' => $chave, 'rotulo' => $par[0], 'total' => $grade['resumo'][$chave] ?? 0];
}
$vinculos = [];
foreach (Colaborador::VINCULOS as $chave => $rotulo) {
    $vinculos[] = ['chave' => $chave, 'rotulo' => $rotulo];
}

$dados = [
    'filtros'   => $filtros,
    'setores'   => $grade['setores'],
    'total'     => $grade['total'],
    'resumo'    => $grade['resumo'],
    'situacoes' => $situacoes,
    'vinculos'  => $vinculos,
    'opcoes_setor' => Fichario::setoresComFicha(),
    'url_base'  => $base,
    'url_nova'  => Pagina::url(Colaborador::getFormURL(false)),
    'escopo'    => Fichario::escopo(),
    'filtrado'  => $filtros['busca'] !== '' || $filtros['setor'] > 0 || $filtros['situacao'] !== '' || $filtros['vinculo'] !== '',
    'lixeira'   => $filtros['lixeira'],
    'na_lixeira' => Fichario::naLixeira(),
    'url_tipos_admissao' => Pagina::url('/plugins/pessoasplus/front/admissao_tipos.php'),
    'cartao_css' => Pagina::url('/plugins/pessoasplus/fichario_cartao.css') . '?v=bm1-4',
];

$pp = Pagina::contexto(
    'fichario',
    'Fichário',
    'Uma pasta por colaborador, agrupadas por setor',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/fichario.html.twig', ['pp' => $pp]);
Pagina::rodape();
