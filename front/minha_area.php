<?php

/**
 * Pessoas+ - Minha area do colaborador (BM.2, real).
 *
 * PESSOASPLUS_BUILD_BP1
 * PESSOASPLUS_BUILD_BM2
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_7
 *
 * Pendencias de ciencia, normativas vigentes e comprovantes, nas duas
 * interfaces. Ferias, pesquisas e tarefas ficam para depois de 13/10 (D-74).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Menu;

Session::checkRight(Menu::$rightname, READ);

$uid   = (int) Session::getLoginUserID();
$nome  = trim((string) ($_SESSION['glpifirstname'] ?? ''));
$ficha = Colaborador::porUsuario($uid);

$dados = [
    'ciencias'    => Comunicado::pendenciasDe($uid),
    'normativas'  => Comunicado::normativasDe($uid),
    'comprovantes' => Comunicado::cienciasDe($uid),
    'pesquisas'   => Formulario::pendentesDe($uid),
    'ficha'       => $ficha === null ? null : $ficha->paraTela(),
    'registros'   => [],
    'url_ficha'   => $ficha === null || Session::getCurrentInterface() === 'helpdesk' ? '' : Pagina::url(Colaborador::getFormURLWithID((int) $ficha->getID(), false)),
];

if ($ficha !== null) {
    $t = $ficha->paraTela();
    $dados['registros'] = [
        ['icone' => 'ti ti-briefcase', 'rotulo' => 'Cargo', 'valor' => $t['cargo'] !== '' ? $t['cargo'] : '—'],
        ['icone' => 'ti ti-folder', 'rotulo' => 'Setor', 'valor' => $t['setor'] !== '' ? $t['setor'] : '—'],
        ['icone' => 'ti ti-user-star', 'rotulo' => 'Gestor', 'valor' => $t['gestor'] !== '' ? $t['gestor'] : '—'],
        ['icone' => 'ti ti-file-text', 'rotulo' => 'Vínculo', 'valor' => $t['vinculo_rotulo']],
        ['icone' => 'ti ti-calendar', 'rotulo' => 'Admissão', 'valor' => $t['data_inicio'] !== '' ? date('d/m/Y', strtotime($t['data_inicio'])) : '—'],
        ['icone' => 'ti ti-building', 'rotulo' => 'Empregador', 'valor' => $t['empregador'] !== '' ? $t['empregador'] : '—'],
    ];
}

$pp = Pagina::contexto(
    'minha_area',
    $nome === '' ? 'Minha área' : 'Olá, ' . $nome,
    'Tudo o que o RH espera de você, num lugar só',
    $dados
);

Pagina::cabecalho('Minha área');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/minha_area.html.twig', ['pp' => $pp]);
Pagina::rodape();
