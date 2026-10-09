<?php

/**
 * Pessoas+ - Mural do colaborador (casca), nas duas interfaces.
 *
 * PESSOASPLUS_BUILD_BP3B
 *
 * Pagina "Para mim > Mural" (D-55): o mural como quadro, visto por quem
 * entra. So exige "Usar o Pessoas+". Sem contagem de visualizacoes e sem
 * nada do RH. A area do RH continua em front/mural.php.
 *
 * O botao "Continuar para a pagina inicial" leva a home da interface
 * atual. No BP.3c (D-54) passa a voltar ao destino guardado na sessao.
 * O login do core redireciona de forma fixa para /front/central.php ou
 * /Helpdesk (Auth.php:1576-1587, T-44); para quem tem a home do Task+,
 * central.php ja desvia para a tela Hoje.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Menu;

Session::checkRight(Menu::$rightname, READ);

$nome  = trim((string) ($_SESSION['glpifirstname'] ?? ''));
$dados = Fontes::muralColaborador();

// Imagens da casca ficam em public/demo/ e sao servidas sem "/public" na URL (T-22).
$imagem = static fn (string $arquivo): string => $arquivo === ''
    ? ''
    : Pagina::url('/plugins/pessoasplus/demo/' . $arquivo) . '?v=' . Navegacao::BUILD;

$dados['campanha']['imagem_url'] = $imagem($dados['campanha']['imagem']);
foreach ($dados['aniversarios'] as $i => $a) {
    $dados['aniversarios'][$i]['imagem_url'] = $imagem($a['imagem']);
}
$dados['quem']           = $nome === '' ? 'você' : $nome;
$dados['quem_iniciais']  = $nome === '' ? '?' : mb_strtoupper(mb_substr($nome, 0, 1));
$dados['url_inicio']     = Pagina::interfaceAtual() === 'helpdesk'
    ? Pagina::url('/Helpdesk')
    : Pagina::url('/front/central.php');

$pp = Pagina::contexto(
    'mural_colaborador',
    'Mural',
    'O que está acontecendo na empresa, em um lugar só',
    $dados
);

Pagina::cabecalho('Mural', MenuSimplificado::CHAVE_MURAL);
TemplateRenderer::getInstance()->display('@pessoasplus/casca/mural_colaborador.html.twig', ['pp' => $pp]);
Pagina::rodape();
