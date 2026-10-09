<?php

/**
 * Pessoas+ - Ouvidoria (casca), nas duas interfaces.
 *
 * PESSOASPLUS_BUILD_BP3E
 *
 * Pagina "Para mim > Ouvidoria" (D-56): quatro portas (duvida, sugestao,
 * reclamacao, relato de conduta), como funciona, acompanhamento por
 * protocolo, respaldo legal (a validar com o juridico), informativos do
 * Codex+, QR e contatos do RH. So exige "Usar o Pessoas+".
 *
 * Os formularios chegam no B7.9, sobre o motor de questionario da E7 (D-58),
 * com modo anonimo (D-31) e pagina publica sem login (T-45). Na casca, a
 * consulta de protocolo roda so no navegador: nada e enviado ao servidor.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\MenuSimplificado;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Menu;

Session::checkRight(Menu::$rightname, READ);

$dados = Fontes::ouvidoria();

// Protocolos lidos pelo JS da consulta. Texto em <script> exige os HEX (T-15).
$dados['protocolos_json'] = (string) json_encode(
    $dados['protocolos'],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
$dados['exemplos'] = array_keys($dados['protocolos']);
unset($dados['protocolos']);

$pp = Pagina::contexto(
    'ouvidoria',
    'Ouvidoria',
    'Um lugar só para falar com o RH: dúvidas, sugestões, reclamações e relatos. Você escolhe se quer se identificar. Todo envio gera um protocolo para acompanhar a resposta.',
    $dados
);

Pagina::cabecalho('Ouvidoria', MenuSimplificado::CHAVE_OUVIDORIA);
TemplateRenderer::getInstance()->display('@pessoasplus/casca/ouvidoria.html.twig', ['pp' => $pp]);
Pagina::rodape();
