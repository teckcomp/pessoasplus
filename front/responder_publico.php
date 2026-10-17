<?php

/**
 * Pessoas+ - resposta pela pagina publica, sem login (BM.4, T-45).
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_10
 * PESSOASPLUS_BUILD_BM4_2
 *
 * GET ?t=<token>. Estrategia no_check registrada no setup.php (T-82).
 * Sempre anonimo. Desafio ALTCHA do core quando ligado em Configurar >
 * Geral > Seguranca; limite de envios por dia sem IP em claro (D-05).
 * Sem Html::header (T-40): pagina propria, so com o CSS do plugin.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Altcha\AltchaManager;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Identidade;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\OuvidoriaConfig;

global $CFG_GLPI;

$token = preg_replace('/[^a-f0-9]/', '', (string) ($_POST['t'] ?? $_GET['t'] ?? ''));
$form  = new Formulario();
$ok    = $token !== '' && strlen($token) === 32 && $form->getFromDBByCrit(['token' => $token, 'is_deleted' => 0, 'publico_sem_login' => 1]);
$hoje  = date('Y-m-d');
$aberto = $ok && Formulario::estado($form->fields, $hoje) === 'aberto';

$erro = '';
$recibo = null;
$altcha = AltchaManager::getInstance();
$desafio = $altcha->isEnabled();

if ($ok && $aberto && isset($_POST['enviar'])) {
    if ($desafio) {
        $solucao = (string) ($_POST['altcha'] ?? '');
        if ($solucao === '' || !$altcha->verifySolution($solucao)) {
            $erro = 'Confirme que você não é um robô e envie de novo.';
        } else {
            $altcha->removeChallenge($solucao);
        }
    }
    if ($erro === '' && !$form->registrarEnvioPublico(OuvidoriaConfig::enderecoRemoto())) { // BM.4-10: atras do proxy, X-Forwarded-For
        $erro = 'Limite de envios por hoje atingido para este endereço. Tente amanhã ou use a Ouvidoria logado.';
    }
    if ($erro === '') {
        $r = $form->responder($_POST, 0, true, 'publico');
        if (isset($r['erro'])) {
            $erro = $r['erro'];
        } else {
            $recibo = $r;
        }
    }
}

$dados = [
    'ok'        => $ok,
    'aberto'    => $aberto,
    'titulo'    => $ok ? (string) $form->fields['titulo'] : 'Formulário indisponível',
    'descricao' => $ok ? (string) ($form->fields['descricao'] ?? '') : '',
    'ouvidoria' => $ok && $form->ehOuvidoria(),
    'perguntas' => $ok ? $form->perguntas() : [],
    'erro'      => $erro,
    'recibo'    => $recibo,
    'desafio'   => $desafio,
    'token'     => $token,
    // BM.4-10 (D-91): chegou pelo endereco curto -> o formulario e os links ficam no endereco curto.
    'url_aqui'  => OuvidoriaConfig::pelaCanal() ? OuvidoriaConfig::urlAnonima('responder', $token) : Pagina::url('/plugins/pessoasplus/front/responder_publico.php'),
    'url_protocolo' => OuvidoriaConfig::pelaCanal() ? OuvidoriaConfig::urlAnonima('protocolo') : Pagina::url('/plugins/pessoasplus/front/protocolo.php'),
    'url_altcha_js' => Pagina::url('/lib/altcha.js'),
    'url_altcha_desafio' => Pagina::url('/Altcha/Challenge'),
    'csrf'      => Session::getNewCSRFToken(),
    'diag'      => null,
];
if (($_GET['diag'] ?? '') === '1' && $ok) {
    $amostra = '';
    try {
        $amostra = json_encode($altcha->generateChallenge(), JSON_UNESCAPED_SLASHES) ?: '';
    } catch (\Throwable $e) {
        $amostra = 'ERRO: ' . $e->getMessage();
    }
    $dados['diag'] = ['ligado' => $desafio, 'modo' => defined('GLPI_ALTCHA_MODE') ? (string) GLPI_ALTCHA_MODE : '(padrão)', 'url' => $dados['url_altcha_desafio'], 'desafio' => mb_substr($amostra, 0, 300)];
}

TemplateRenderer::getInstance()->display('@pessoasplus/casca/responder_publico.html.twig', ['pp' => [
    'css'        => Pagina::url('/plugins/pessoasplus/pessoasplus.css') . '?v=' . Navegacao::BUILD,
    'js'         => Pagina::url('/plugins/pessoasplus/pessoasplus.js') . '?v=' . Navegacao::BUILD,
    'identidade' => Identidade::atual(),
    'dados'      => $dados,
]]);
