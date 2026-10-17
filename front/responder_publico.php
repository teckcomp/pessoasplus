<?php

/**
 * Pessoas+ - resposta pela pagina publica, sem login (BM.4, T-45).
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_10
 * PESSOASPLUS_BUILD_BM4_2
 * PESSOASPLUS_BUILD_BM4_17 (D-97: bilhete no corpo do POST; participacao + bilhete na transacao da resposta)
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
use GlpiPlugin\Pessoasplus\Bilhete;
use GlpiPlugin\Pessoasplus\Casca\Identidade;
use GlpiPlugin\Pessoasplus\Casca\Navegacao;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\OuvidoriaConfig;
use GlpiPlugin\Pessoasplus\Participacao;

global $CFG_GLPI, $DB;

$token = preg_replace('/[^a-f0-9]/', '', (string) ($_POST['t'] ?? $_GET['t'] ?? ''));
$form  = new Formulario();
$ok    = $token !== '' && strlen($token) === 32 && $form->getFromDBByCrit(['token' => $token, 'is_deleted' => 0, 'publico_sem_login' => 1]);
$hoje  = date('Y-m-d');
$aberto = $ok && Formulario::estado($form->fields, $hoje) === 'aberto';

$erro = '';
$recibo = null;
$altcha = AltchaManager::getInstance();
$desafio = $altcha->isEnabled();

// BM.4-17 (D-97): bilhete de uso unico. Chega no corpo do POST (o JS copia do fragmento #b=);
// identifica o DONO so para gravar a participacao e apagar o bilhete, na mesma transacao da resposta.
$bilheteClaro = preg_replace('/[^a-f0-9]/', '', (string) ($_POST['b'] ?? ''));
$donoBilhete  = ($ok && $bilheteClaro !== '') ? Bilhete::dono($bilheteClaro, (int) $form->getID()) : 0;
// Pesquisa sem "divulgar por QR": so responde quem veio pelo botao (bilhete). Ouvidoria nao entra nessa regra.
$somenteBilhete = $ok && !$form->ehOuvidoria() && !$form->divulgaQr();

if ($ok && $aberto && isset($_POST['enviar'])) {
    if ($bilheteClaro !== '' && $donoBilhete === 0) {
        $erro = 'Seu acesso pelo botão Responder expirou ou já foi usado. Volte ao Pessoas+ e clique em Responder de novo.';
    } elseif ($somenteBilhete && $donoBilhete === 0) {
        $erro = 'Esta pesquisa é respondida pelo botão Responder, dentro do Pessoas+.';
    }
    if ($erro === '' && $desafio) {
        $solucao = (string) ($_POST['altcha'] ?? '');
        if ($solucao === '' || !$altcha->verifySolution($solucao)) {
            $erro = 'Confirme que você não é um robô e envie de novo.';
        } else {
            $altcha->removeChallenge($solucao);
        }
    }
    // Limite diario por endereco so para quem chega sem bilhete (QR): com bilhete a propria emissao
    // logada e o controle, e um NAT corporativo nao pode bloquear a campanha inteira.
    if ($erro === '' && $donoBilhete === 0 && !$form->registrarEnvioPublico(OuvidoriaConfig::enderecoRemoto())) { // BM.4-10: atras do proxy, X-Forwarded-For
        $erro = 'Limite de envios por hoje atingido para este endereço. Tente amanhã ou use a Ouvidoria logado.';
    }
    if ($erro === '') {
        $DB->beginTransaction();
        $r = $form->responder($_POST, 0, true, 'publico');
        if (isset($r['erro'])) {
            $DB->rollBack();
            $erro = $r['erro'];
        } else {
            if ($donoBilhete > 0) {
                // Participacao sem nada da resposta (D-31) e bilhete apagado: depois do commit nao existe
                // mais nenhuma linha que ligue este usuario a este envio.
                Participacao::registrar((int) $form->getID(), $donoBilhete, Participacao::ORIGEM_ENVIO);
                Bilhete::consumir((int) $form->getID(), $donoBilhete);
            }
            $DB->commit();
            $recibo = $r + ['com_bilhete' => $donoBilhete > 0];
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
    'somente_bilhete' => $somenteBilhete,
    'divulga_qr' => $ok && !$form->ehOuvidoria() && $form->divulgaQr(),
    'bilhete_ok' => $donoBilhete > 0,
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
