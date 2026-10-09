<?php

/**
 * Pessoas+ - novo comunicado ou normativa do Codex+ (casca).
 *
 * PESSOASPLUS_BUILD_BP2C
 * PESSOASPLUS_BUILD_BP0C
 *
 * Na casca nada e gravado: salvar e publicar mostram o que vai acontecer.
 * Na mobilia: B2.1 (rascunho, editor, anexos, pre-visualizacao), B2.2
 * (publicar = congelar + selar + materializar destinatarios), B1.4 a B1.6
 * (publico e regua de emissao, validada no servidor) e B2.5 (normativa).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Fontes;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Permissoes::exigirGerencia('comunicados');

$dados = Fontes::novoComunicado();
$tipo  = (string) ($_GET['tipo'] ?? 'comunicado');
if (!in_array($tipo, array_column($dados['tipos'], 'valor'), true)) {
    $tipo = 'comunicado';
}
$dados['tipo_inicial'] = $tipo;
$dados['url_lista']    = Pagina::url('/plugins/pessoasplus/front/comunicados.php');

$pp = Pagina::contexto(
    'comunicados',
    $tipo === 'normativa' ? 'Distribuir normativa do Codex+' : 'Novo comunicado',
    'Conteúdo, público, prazo e lembretes. Ao publicar, o texto é congelado e selado',
    $dados
);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/comunicado_novo.html.twig', ['pp' => $pp]);
Pagina::rodape();
