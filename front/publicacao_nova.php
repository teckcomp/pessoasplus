<?php

/**
 * Pessoas+ - nova publicacao / editar publicacao do mural (BM.3, real).
 *
 * PESSOASPLUS_BUILD_BP3D
 * PESSOASPLUS_BUILD_BM3
 * PESSOASPLUS_BUILD_BM3_2
 * PESSOASPLUS_BUILD_BM4_2
 * PESSOASPLUS_BUILD_BM4_8
 *
 * POST add | update | publicar | despublicar | delete; imagem (documents_id)
 * escolhe a capa entre as imagens anexadas pela aba nativa de Documentos.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Pessoasplus\Casca\Pagina;
use GlpiPlugin\Pessoasplus\Comunicado;
use GlpiPlugin\Pessoasplus\Formulario;
use GlpiPlugin\Pessoasplus\Permissoes;
use GlpiPlugin\Pessoasplus\Publicacao;
use GlpiPlugin\Pessoasplus\Publico;
use GlpiPlugin\Pessoasplus\PublicoRegra;

Pagina::somenteInterfacePadrao();
Permissoes::exigirGerencia('mural');

$pub      = new Publicacao();
$urlLista = Pagina::url('/plugins/pessoasplus/front/mural.php');
$urlForm  = Pagina::url('/plugins/pessoasplus/front/publicacao_nova.php');

$lerRegras = static function (array $post): array {
    $r = json_decode((string) ($post['regras_json'] ?? '[]'), true);

    return is_array($r) ? $r : [];
};

if (isset($_POST['add'])) {
    $pub->check(-1, CREATE, $_POST);
    $id = $pub->add($_POST);
    if ($id) {
        $pub->definirPublico($lerRegras($_POST), (int) ($_POST['publico_modelo_id'] ?? 0));
        Session::addMessageAfterRedirect('Rascunho salvo. Anexe a imagem na aba Imagem e publique quando estiver pronto.', false, INFO);
        Html::redirect($urlForm . '?id=' . (int) $id);
    }
    Html::redirect($urlForm);
} elseif (isset($_POST['update']) || isset($_POST['publicar'])) {
    $pub->check((int) $_POST['id'], UPDATE);
    if ($pub->update($_POST)) {
        $pub->definirPublico($lerRegras($_POST), (int) ($_POST['publico_modelo_id'] ?? 0));
        $pub->getFromDB((int) $_POST['id']);
        if (array_key_exists('imagem', $_POST)) {
            $erro = $pub->definirImagem((int) $_POST['imagem']);
            if ($erro !== '') {
                Session::addMessageAfterRedirect($erro, false, WARNING);
            }
        }
        // BM.3-2: sem capa escolhida e uma unica imagem anexada -> ela vira a capa.
        if ((string) $pub->fields['imagem_caminho'] === '' && in_array($pub->fields['lugar'], ['grande', 'cartao'], true)) {
            $imagens = $pub->imagensDisponiveis();
            if (count($imagens) === 1) {
                $pub->definirImagem((int) $imagens[0]['id']);
            }
        }
        if (isset($_POST['publicar'])) {
            // BM.4-8 (I-10): reconhecimento so sai no mural com a concordancia da pessoa.
            if ((string) $pub->fields['tipo'] === 'reconhecimento' && (int) ($_POST['concordancia'] ?? 0) !== 1) {
                Session::addMessageAfterRedirect('Confirme a concordância da pessoa reconhecida (seção Interação) antes de publicar.', false, ERROR);
            } elseif ($pub->publicar()) {
                Session::addMessageAfterRedirect('Publicado no mural.', false, INFO);
                Html::redirect($urlLista);
            }
        } else {
            Session::addMessageAfterRedirect('Salvo.', false, INFO);
        }
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['capa'])) {
    // BM.3-2: "Usar como capa" na aba Imagem.
    $pub->check((int) $_POST['id'], UPDATE);
    $erro = $pub->definirImagem((int) $_POST['capa']);
    Session::addMessageAfterRedirect($erro === '' ? ((int) $_POST['capa'] > 0 ? 'Imagem definida como capa.' : 'Capa removida.') : $erro, false, $erro === '' ? INFO : WARNING);
    Html::redirect($urlForm . '?id=' . (int) $_POST['id'] . '&aba=imagem');
} elseif (isset($_POST['despublicar'])) {
    $pub->check((int) $_POST['id'], UPDATE);
    if ($pub->despublicar()) {
        Session::addMessageAfterRedirect('Tirada do mural; voltou a rascunho.', false, INFO);
    }
    Html::redirect($urlForm . '?id=' . (int) $_POST['id']);
} elseif (isset($_POST['delete'])) {
    $pub->check((int) $_POST['id'], DELETE);
    if ($pub->delete($_POST)) {
        Session::addMessageAfterRedirect('Publicação excluída.', false, INFO);
    }
    Html::redirect($urlLista);
}

$id   = (int) ($_GET['id'] ?? 0);
$nova = $id <= 0;
$hoje = date('Y-m-d');
if ($nova) {
    $pub->getEmpty();
    $pub->fields['tipo']   = 'aviso';
    $pub->fields['lugar']  = 'cartao';
    $pub->fields['inicio'] = $hoje;
    $regras = [];
    $aba = 'conteudo';
} else {
    if (!$pub->getFromDB($id) || (int) $pub->fields['is_deleted'] === 1) {
        Session::addMessageAfterRedirect('Publicação não encontrada.', false, WARNING);
        Html::redirect($urlLista);
    }
    $pub->check($id, READ);
    $regras = $pub->regrasDoPublico();
    $aba = ($_GET['aba'] ?? '') === 'imagem' ? 'imagem' : 'conteudo';
}
$f = $pub->fields;

ob_start();
Html::textarea(['name' => 'conteudo', 'value' => (string) ($f['conteudo'] ?? ''), 'enable_richtext' => true, 'enable_images' => false, 'rows' => 10]);
$editorHtml = (string) ob_get_clean();

$anexosHtml = '';
if (!$nova && $aba === 'imagem' && Document::canView()) {
    ob_start();
    Document_Item::showForItem($pub);
    $anexosHtml = (string) ob_get_clean();
}

$hex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
// BM.4-8: descricoes do quadro 2 do canvas (so texto de tela).
$descricoes = [
    'aviso'          => 'Informação do dia a dia: escala, prazo, mudança de rotina.',
    'evento'         => 'Reunião, confraternização, treinamento com data e lugar.',
    'reconhecimento' => 'Elogio público a uma pessoa ou equipe, com a concordância dela.',
    'treinamento'    => 'Conteúdo para aprender: vídeo, manual, trilha.',
    'campanha'       => 'Campanha do mês, com arte e período longo.',
    'celebracao'     => 'Data comemorativa da empresa, com arte própria.',
];
$tipos = [];
foreach (Publicacao::TIPOS as $chave => $t) {
    $tipos[] = ['chave' => $chave, 'rotulo' => $t[0], 'icone' => $t[1], 'descricao' => $descricoes[$chave] ?? ''];
}
// BM.4-8: fixadas no ar por lugar (texto "Fixadas agora em ...") e destaque atual (D-63), sem a propria publicacao.
$fixadasPorLugar = array_fill_keys(array_keys(Publicacao::LUGARES), []);
$destaqueAtual   = ['titulo' => '', 'periodo' => ''];
foreach (Publicacao::lista(['situacao' => 'no_ar', 'busca' => ''], $hoje)['publicacoes'] as $p) {
    if ((int) $p['id'] === $id) {
        continue;
    }
    if ($p['fixada'] && isset($fixadasPorLugar[$p['lugar']])) {
        $fixadasPorLugar[$p['lugar']][] = $p['titulo'];
    }
    if ($p['lugar'] === 'destaque' && $destaqueAtual['titulo'] === '') {
        $destaqueAtual = ['titulo' => $p['titulo'], 'periodo' => $p['periodo']];
    }
}
$imagens  = $nova ? [] : $pub->imagensDisponiveis();
$imagemId = 0;
foreach ($imagens as $i) {
    if (!empty($i['escolhida'])) {
        $imagemId = (int) $i['id'];
    }
}
$lugares = [];
foreach (Publicacao::LUGARES as $chave => $l) {
    $lugares[] = ['chave' => $chave, 'rotulo' => $l[0], 'descricao' => $l[1]];
}
$tiposRegra = [];
foreach (PublicoRegra::TIPOS as $chave => $rotulo) {
    $tiposRegra[] = ['chave' => $chave, 'rotulo' => $rotulo];
}
$modelos = [];
foreach (Publico::modelos() as $m) {
    $modelos[] = ['id' => $m['id'], 'nome' => $m['nome'], 'total' => $m['total'], 'regras' => json_encode($m['regras'], $hex)];
}
$botoes = Comunicado::normalizarLinks((string) ($f['botoes'] ?? ''));
// BM.4-2: atalhos para ligar o botao a um formulario aberto (pagina publica quando existe).
$atalhos = [];
foreach (Formulario::lista($hoje) as $fm) {
    if ($fm['estado'] !== 'aberto') {
        continue;
    }
    $atalhos[] = ['rotulo' => ($fm['tipo'] === 'ouvidoria' ? 'Ouvidoria: ' : 'Responder: ') . $fm['titulo'], 'url' => $fm['url_publica'] !== '' ? $fm['url_publica'] : $fm['url_responder'], 'detalhe' => $fm['url_publica'] !== '' ? 'página pública, sem login' : 'logado'];
}

$dados = [
    'nova'        => $nova,
    'id'          => $id,
    'publicada'   => ($f['situacao'] ?? '') === 'publicada',
    'estado'      => $nova ? 'rascunho' : Publicacao::estado($f, $hoje),
    'f'           => [
        'titulo'  => (string) ($f['titulo'] ?? ''),
        'chamada' => (string) ($f['chamada'] ?? ''),
        'tipo'    => (string) ($f['tipo'] ?? 'aviso'),
        'lugar'   => (string) ($f['lugar'] ?? 'cartao'),
        'inicio'  => (string) ($f['inicio'] ?? ''),
        'fim'     => (string) ($f['fim'] ?? ''),
        'fixada'  => (int) ($f['fixada'] ?? 0) === 1,
        'concordancia' => ($f['situacao'] ?? '') === 'publicada' && ($f['tipo'] ?? '') === 'reconhecimento',
        'imagem_alt' => (string) ($f['imagem_alt'] ?? ''),
        'publico_modelo_id' => (int) ($f['publico_modelo_id'] ?? 0),
    ],
    'imagem_url'  => $nova ? '' : $pub->urlImagem(),
    'imagem_id'   => $imagemId,
    'imagens'     => $imagens,
    'hoje'        => $hoje,
    'fixadas_json' => json_encode($fixadasPorLugar, $hex),
    'destaque'    => $destaqueAtual,
    'botoes'      => $botoes,
    'botoes_json' => json_encode($botoes, $hex),
    'atalhos'     => $atalhos,
    'aba'         => $aba,
    'url_aba'     => ['conteudo' => $urlForm . '?id=' . $id, 'imagem' => $urlForm . '?id=' . $id . '&aba=imagem'],
    'editor_html' => $editorHtml,
    'anexos_html' => $anexosHtml,
    'pode_documentos' => Document::canView(),
    'tipos'       => $tipos,
    'lugares'     => $lugares,
    'tipos_regra' => $tiposRegra,
    'modelos'     => $modelos,
    'regras'      => Publico::rotular($regras),
    'regras_json' => json_encode(Publico::rotular($regras), $hex),
    'opcoes_json' => json_encode(Publico::opcoes(), $hex),
    'previa'      => Publico::previa($regras),
    'url_form'    => $urlForm,
    'url_lista'   => $urlLista,
    'url_previa'  => Pagina::url('/plugins/pessoasplus/ajax/publico_previa.php'),
];

$pp = Pagina::contexto('mural', $nova ? 'Nova publicação' : (string) $f['titulo'], 'Escolha o lugar no mural, o período e quem vê', $dados);

Pagina::cabecalho('Pessoas+');
TemplateRenderer::getInstance()->display('@pessoasplus/casca/publicacao_nova.html.twig', ['pp' => $pp]);
Pagina::rodape();
