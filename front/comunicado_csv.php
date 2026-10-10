<?php

/**
 * Pessoas+ - exportacao CSV do acompanhamento de uma versao (BM.2).
 *
 * PESSOASPLUS_BUILD_BM2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Versao;
use Symfony\Component\HttpFoundation\Response;

Permissoes::exigirLeitura('comunicados');

$versao = new Versao();
$vid = (int) ($_GET['id'] ?? 0);
if ($vid <= 0 || !$versao->getFromDB($vid)) {
    throw new \Glpi\Exception\Http\NotFoundHttpException();
}
$painel = Comunicado::painel($versao);

$saida = fopen('php://temp', 'w+');
fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel em pt-BR
fputcsv($saida, ['Comunicado', 'Versão', 'Selo da versão', 'Colaborador', 'Setor', 'Situação', 'Resposta', 'Justificativa', 'Data e hora', 'Interface', 'Selo da ciência', 'Integridade'], ';');
foreach ($painel['pessoas'] as $p) {
    $c = Comunicado::cienciaDe($vid, $p['users_id']);
    fputcsv($saida, [
        $painel['titulo'], $painel['numero'], $painel['selo'], $p['nome'], $p['grupo'], $p['rotulo'],
        $c !== null ? ((int) $c['concorda'] === 1 ? 'Ciente' : 'Não concordo') : '',
        $c !== null ? (string) $c['justificativa'] : '',
        $c !== null ? (string) $c['data'] : '',
        $c !== null ? ($c['interface'] === 'helpdesk' ? 'Simplificada' : 'Padrão') : '',
        $c !== null ? (string) $c['selo'] : '',
        $c !== null ? ($p['integro'] ? 'ok' : 'DIVERGE') : '',
    ], ';');
}
rewind($saida);
$csv = (string) stream_get_contents($saida);
fclose($saida);

// Script legado devolvendo Response (T-73): sem header() nem exit.
return new Response($csv, 200, [
    'Content-Type'        => 'text/csv; charset=UTF-8',
    'Content-Disposition' => 'attachment; filename="ciencia-v' . $vid . '.csv"',
    'Cache-Control'       => 'no-store',
]);
