<?php

/**
 * Pessoas+ - foto do colaborador na capa da pasta (BM.1-3, D-84).
 *
 * PESSOASPLUS_BUILD_BM1_3
 * PESSOASPLUS_BUILD_BM1_3_2 (T-88: sem Document::ALLOWED_IMAGE_EXTENSIONS, inexistente na 11.0.6/11.0.8)
 *
 * Recebe o POST da janela "Foto do colaborador" (templates/casca/_foto.html.twig)
 * e grava a foto no USUARIO DO GLPI ligado a ficha, pelo proprio core:
 * User::update() com `_picture` (arquivo ja em GLPI_TMP_DIR) ou `_blank_picture`.
 * Conversao para jpg/png, miniatura `_min` (71 px) e limpeza do arquivo antigo
 * ficam com User::prepareInputForUpdate (conferido no fonte 11.0, V-11).
 *
 * Quem pode: quem gerencia o Fichario (Permissoes::gerencia). O core nao
 * checa direito em CommonDBTM::update, entao a checagem e feita aqui.
 * Pre-cadastro (users_id = 0) nao tem onde guardar a foto: volta com aviso.
 * CSRF: o core valida o POST sozinho (T-11).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Colaborador;
use GlpiPlugin\Pessoasplus\Evento;
use GlpiPlugin\Pessoasplus\Install;
use GlpiPlugin\Pessoasplus\Permissoes;

Pagina::somenteInterfacePadrao();
Session::checkRight(Install::RIGHT_BASE, READ);
if (!Permissoes::gerencia('fichario')) {
    Session::checkRight(Permissoes::MODULOS['fichario'][0], Permissoes::GERENCIAR); // 403
}

$urlLista = Pagina::url('/plugins/pessoasplus/front/fichario.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Html::redirect($urlLista);
}

$id          = (int) ($_POST['id'] ?? 0);
$colaborador = new Colaborador();
if ($id <= 0 || !$colaborador->getFromDB($id) || (int) $colaborador->fields['is_deleted'] === 1) {
    Session::addMessageAfterRedirect('Ficha não encontrada.', false, WARNING);
    Html::redirect($urlLista);
}
$colaborador->check($id, READ); // 403 se nao pode ver a pasta (D-36)

$urlVolta = Pagina::url(Colaborador::getFormURLWithID($id, false));
$usersId  = (int) $colaborador->fields['users_id'];

if ($usersId <= 0) {
    Session::addMessageAfterRedirect('A foto fica disponível depois de ligar a ficha a um usuário do GLPI.', false, WARNING);
    Html::redirect($urlVolta);
}

$usuario = new User();
if (!$usuario->getFromDB($usersId)) {
    Session::addMessageAfterRedirect('Usuário do GLPI ligado à ficha não foi encontrado.', false, WARNING);
    Html::redirect($urlVolta);
}

$fotoAntes = (string) ($usuario->fields['picture'] ?? '');

// ---- Remover ---------------------------------------------------------------
if (!empty($_POST['remover'])) {
    if ($fotoAntes === '') {
        Session::addMessageAfterRedirect('Esta pessoa não tem foto para remover.', false, INFO);
        Html::redirect($urlVolta);
    }
    if ($usuario->update(['id' => $usersId, '_blank_picture' => 1])) {
        Evento::registrar('fichario', 'foto_removida', $colaborador, $id, ['users_id' => $usersId]);
        Session::addMessageAfterRedirect('Foto removida. A capa volta a mostrar as iniciais.', false, INFO);
    } else {
        Session::addMessageAfterRedirect('Não foi possível remover a foto.', false, ERROR);
    }
    Html::redirect($urlVolta);
}

// ---- Enviar ----------------------------------------------------------------
$arquivo = $_FILES['foto'] ?? null;
if (!is_array($arquivo) || !isset($arquivo['error']) || (int) $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
    Session::addMessageAfterRedirect('Escolha uma imagem antes de salvar.', false, WARNING);
    Html::redirect($urlVolta);
}

$erro = (int) $arquivo['error'];
if ($erro !== UPLOAD_ERR_OK) {
    $motivo = in_array($erro, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'A imagem é maior do que o servidor aceita (limite do PHP: ' . ini_get('upload_max_filesize') . ').'
        : 'O envio falhou (código ' . $erro . '). Tente de novo.';
    Session::addMessageAfterRedirect($motivo, false, ERROR);
    Html::redirect($urlVolta);
}

$limiteBytes = 8 * 1024 * 1024; // 8 MB: foto de celular cabe; nada maior faz sentido para um avatar
if ((int) $arquivo['size'] > $limiteBytes) {
    Session::addMessageAfterRedirect('A imagem passa de 8 MB. Reduza o tamanho antes de enviar.', false, ERROR);
    Html::redirect($urlVolta);
}

// Lista propria: Document::ALLOWED_IMAGE_EXTENSIONS so existe depois da 11.0.8
// (T-88, conferido na tag 11.0.6). Mesmos tipos que Document::isImage aceita.
$extensoesAceitas = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
$extensao = strtolower((string) pathinfo((string) $arquivo['name'], PATHINFO_EXTENSION));
if ($extensao === 'jpeg') {
    $extensao = 'jpg';
}
if (!in_array($extensao, $extensoesAceitas, true)) {
    Session::addMessageAfterRedirect('Use uma imagem JPG, PNG, GIF, BMP ou WEBP.', false, ERROR);
    Html::redirect($urlVolta);
}

// O core espera o arquivo em GLPI_TMP_DIR e o nome (sem caminho) em `_picture`.
// Nome proprio e imprevisivel: nada do nome original entra no disco.
$nomeTmp = 'pp_foto_' . $usersId . '_' . bin2hex(random_bytes(6)) . '.' . $extensao;
$caminho = GLPI_TMP_DIR . '/' . $nomeTmp;

if (!is_uploaded_file((string) $arquivo['tmp_name']) || !move_uploaded_file((string) $arquivo['tmp_name'], $caminho)) {
    Session::addMessageAfterRedirect('Não foi possível guardar o arquivo enviado. Verifique as permissões de files/_tmp.', false, ERROR);
    Html::redirect($urlVolta);
}

if (!Document::isImage($caminho)) {
    @unlink($caminho);
    Session::addMessageAfterRedirect('O arquivo não é uma imagem válida.', false, ERROR);
    Html::redirect($urlVolta);
}

// `_picture` vai como lista: fora da API o core le $input['_picture'][0], e um
// texto simples viraria so a primeira letra do nome (T-87).
$ok = $usuario->update(['id' => $usersId, '_picture' => [$nomeTmp]]);
$fotoDepois = (string) ($usuario->fields['picture'] ?? '');

if ($ok && $fotoDepois !== '' && $fotoDepois !== $fotoAntes) {
    Evento::registrar('fichario', 'foto_alterada', $colaborador, $id, ['users_id' => $usersId, 'tinha_foto' => $fotoAntes !== '']);
    Session::addMessageAfterRedirect('Foto atualizada. Ela vale em todo o GLPI, não só no Pessoas+.', false, INFO);
} else {
    // O core ja explicou o motivo (arquivo invalido, falha ao mover). Garante que o temporario nao fique.
    @unlink($caminho);
    if ($ok) {
        Session::addMessageAfterRedirect('O GLPI não aceitou a imagem. Veja a mensagem acima.', false, WARNING);
    } else {
        Session::addMessageAfterRedirect('Não foi possível salvar a foto no usuário do GLPI.', false, ERROR);
    }
}

Html::redirect($urlVolta);
