<?php

/**
 * Pessoas+ - publicacao do mural (M2, BM.3): lugar, fixacao, periodo, publico,
 * imagem de capa, botoes, visualizacoes.
 *
 * PESSOASPLUS_BUILD_BM3
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;
use Document;
use Session;

/**
 * Regras (D-59, D-63):
 * - lugar: destaque (faixa no topo, um por vez: vale o de inicio mais
 *   recente), grande (largura da coluna, capa e ate 2 botoes), cartao
 *   (grade, padrao), lateral (bloco curto a direita);
 * - fixada: entre fixadas, a de fixacao mais recente primeiro; "Trazer ao
 *   topo" so atualiza o carimbo; destaque nao se fixa;
 * - imagem so em grande e cartao; botoes so em grande;
 * - situacao gravada: rascunho | publicada. Estado na tela deriva das
 *   datas: agendada (inicio futuro), no ar, encerrada (fim passado);
 * - publico resolvido na consulta (D-72): quem entra no grupo passa a ver.
 * Comentarios, curtidas e celebracoes automaticas ficam para depois de 13/10 (D-74).
 */
final class Publicacao extends CommonDBTM
{
    public static $rightname = 'pessoasplus_mural';

    public $dohistory = false;

    /** chave => [rotulo, icone, tom] */
    public const TIPOS = [
        'aviso'          => ['Aviso', 'ti ti-speakerphone', 'alerta'],
        'evento'         => ['Evento', 'ti ti-calendar-event', 'info'],
        'reconhecimento' => ['Reconhecimento', 'ti ti-award', 'ok'],
        'treinamento'    => ['Treinamento', 'ti ti-school', 'neutro'],
        'campanha'       => ['Campanha do mês', 'ti ti-heart-handshake', 'info'],
        'celebracao'     => ['Celebração', 'ti ti-confetti', 'ok'],
    ];

    /** chave => [rotulo, descricao] */
    public const LUGARES = [
        'destaque' => ['Destaque fixo', 'Faixa no topo do mural. Um por vez: vale o de início mais recente.'],
        'grande'   => ['Grande', 'Largura da coluna, com imagem de capa e até 2 botões.'],
        'cartao'   => ['Cartão', 'Grade de cartões. O padrão.'],
        'lateral'  => ['Lateral', 'Bloco curto na coluna da direita.'],
    ];

    public const PASTA = 'pessoasplus/publicacoes';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_publicacoes';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Publicações' : 'Publicação';
    }

    public static function getIcon()
    {
        return 'ti ti-news';
    }

    // ----------------------------------------------------------------- direitos

    public static function canCreate(): bool
    {
        return Permissoes::gerencia('mural');
    }

    public static function canView(): bool
    {
        return Permissoes::le('mural');
    }

    public static function canUpdate(): bool
    {
        return Permissoes::gerencia('mural');
    }

    public static function canDelete(): bool
    {
        return Permissoes::gerencia('mural');
    }

    public static function canPurge(): bool
    {
        return false;
    }

    public function canCreateItem(): bool
    {
        return Permissoes::gerencia('mural');
    }

    public function canViewItem(): bool
    {
        return Permissoes::le('mural');
    }

    public function canUpdateItem(): bool
    {
        return Permissoes::gerencia('mural');
    }

    public function canDeleteItem(): bool
    {
        return Permissoes::gerencia('mural');
    }

    public function canPurgeItem(): bool
    {
        return false;
    }

    // ----------------------------------------------------------------- entrada

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|false
     */
    private function normalizar(array $input, bool $novo)
    {
        if (array_key_exists('titulo', $input)) {
            $input['titulo'] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $input['titulo']) ?? ''), 0, 255);
            if ($input['titulo'] === '') {
                Session::addMessageAfterRedirect('Informe o título.', false, ERROR);

                return false;
            }
        } elseif ($novo) {
            Session::addMessageAfterRedirect('Informe o título.', false, ERROR);

            return false;
        }
        if (array_key_exists('chamada', $input)) {
            $input['chamada'] = mb_substr(trim((string) $input['chamada']), 0, 240);
        }
        if (array_key_exists('tipo', $input) && !isset(self::TIPOS[$input['tipo']])) {
            $input['tipo'] = 'aviso';
        }
        if (array_key_exists('lugar', $input) && !isset(self::LUGARES[$input['lugar']])) {
            $input['lugar'] = 'cartao';
        }
        foreach (['inicio', 'fim'] as $campo) {
            if (array_key_exists($campo, $input)) {
                $valor = trim((string) $input[$campo]);
                if ($valor === '') {
                    $input[$campo] = 'NULL';
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                    Session::addMessageAfterRedirect('Data inválida no período.', false, ERROR);

                    return false;
                }
            }
        }
        $inicio = ($input['inicio'] ?? null) === 'NULL' ? null : ($input['inicio'] ?? $this->fields['inicio'] ?? null);
        $fim    = ($input['fim'] ?? null) === 'NULL' ? null : ($input['fim'] ?? $this->fields['fim'] ?? null);
        if ($inicio && $fim && $fim < $inicio) {
            Session::addMessageAfterRedirect('O fim do período é anterior ao início.', false, ERROR);

            return false;
        }
        $lugar = (string) ($input['lugar'] ?? $this->fields['lugar'] ?? 'cartao');
        if (array_key_exists('fixada', $input)) {
            $input['fixada'] = $lugar === 'destaque' ? 0 : (int) (bool) $input['fixada'];
            $eraFixada = (int) ($this->fields['fixada'] ?? 0) === 1;
            if ($input['fixada'] === 1 && !$eraFixada) {
                $input['fixada_em'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
            } elseif ($input['fixada'] === 0) {
                $input['fixada_em'] = 'NULL';
            }
        }
        if (array_key_exists('imagem_alt', $input)) {
            $input['imagem_alt'] = mb_substr(trim((string) $input['imagem_alt']), 0, 255);
        }
        if (array_key_exists('botoes_json', $input)) {
            $botoes = $lugar === 'grande' ? array_slice(Comunicado::normalizarLinks((string) $input['botoes_json']), 0, 2) : [];
            $input['botoes'] = json_encode($botoes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            unset($input['botoes_json']);
        }
        if (array_key_exists('conteudo', $input)) {
            $input['conteudo'] = (string) $input['conteudo'];
        }
        unset($input['situacao'], $input['imagem_caminho']);

        return $input;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->normalizar($input, true);
        if ($input === false) {
            return false;
        }
        $input['situacao'] = 'rascunho';
        $input['users_id'] = (int) (Session::getLoginUserID() ?: 0);
        $input['tipo']     = $input['tipo'] ?? 'aviso';
        $input['lugar']    = $input['lugar'] ?? 'cartao';
        if (empty($input['entities_id'])) {
            $input['entities_id'] = (int) ($_SESSION['glpiactive_entity'] ?? 0);
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        return $this->normalizar($input, false);
    }

    public function post_addItem()
    {
        Evento::registrar('mural', 'publicacao_criada', $this, 0, ['titulo' => $this->fields['titulo']]);
    }

    public function post_deleteItem()
    {
        Evento::registrar('mural', 'publicacao_excluida', $this, 0, ['titulo' => $this->fields['titulo']]);
    }

    // ----------------------------------------------------------------- publico

    /**
     * @param array<int, array<string, mixed>> $regras
     */
    public function definirPublico(array $regras, int $modeloId = 0): void
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($modeloId > 0) {
            $regras = Publico::regras($modeloId);
        }
        $publicoId = (int) ($this->fields['plugin_pessoasplus_publicos_id'] ?? 0);
        if ($publicoId <= 0) {
            $p = new Publico();
            $publicoId = (int) $p->add([
                'nome'      => 'Público de: ' . mb_substr((string) $this->fields['titulo'], 0, 200),
                'itemtype'  => self::class,
                'items_id'  => (int) $this->getID(),
                'is_modelo' => 0,
            ]);
            $this->fields['plugin_pessoasplus_publicos_id'] = $publicoId;
        }
        Publico::salvarRegras($publicoId, $regras);
        $DB->update(self::getTable(), ['plugin_pessoasplus_publicos_id' => $publicoId, 'publico_modelo_id' => $modeloId], ['id' => (int) $this->getID()]);
        $this->fields['publico_modelo_id'] = $modeloId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function regrasDoPublico(): array
    {
        $publicoId = (int) ($this->fields['plugin_pessoasplus_publicos_id'] ?? 0);

        return $publicoId > 0 ? Publico::regras($publicoId) : [];
    }

    // ----------------------------------------------------------------- imagem

    /**
     * Copia um Document nativo (imagem) para a pasta da publicacao e grava
     * o caminho. 0 remove a imagem. Devolve mensagem de erro ou ''.
     */
    public function definirImagem(int $documentsId): string
    {
        /** @var DBmysql $DB */
        global $DB;

        $id = (int) $this->getID();
        if ($documentsId <= 0) {
            $DB->update(self::getTable(), ['imagem_caminho' => ''], ['id' => $id]);
            $this->fields['imagem_caminho'] = '';

            return '';
        }
        $doc = new Document();
        if (!$doc->getFromDB($documentsId)) {
            return 'Documento não encontrado.';
        }
        $mime = (string) ($doc->fields['mime'] ?? '');
        if (!str_starts_with($mime, 'image/')) {
            return 'O documento escolhido não é uma imagem.';
        }
        $origem = GLPI_DOC_DIR . '/' . (string) $doc->fields['filepath'];
        if ((string) $doc->fields['filepath'] === '' || !is_file($origem)) {
            return 'Arquivo da imagem não encontrado.';
        }
        $ext = strtolower(pathinfo((string) ($doc->fields['filename'] ?: $origem), PATHINFO_EXTENSION)) ?: 'img';
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'img';
        $pasta = GLPI_PLUGIN_DOC_DIR . '/' . self::PASTA . '/' . $id;
        if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            return 'Não foi possível criar a pasta da imagem.';
        }
        $destino = $pasta . '/capa-' . $documentsId . '.' . $ext;
        if (!copy($origem, $destino)) {
            return 'Não foi possível copiar a imagem.';
        }
        $caminho = substr($destino, strlen(GLPI_PLUGIN_DOC_DIR) + 1);
        $DB->update(self::getTable(), ['imagem_caminho' => $caminho], ['id' => $id]);
        $this->fields['imagem_caminho'] = $caminho;

        return '';
    }

    /**
     * Imagens (Document nativo) ligadas a publicacao, para o seletor de capa.
     *
     * @return array<int, array{id: int, nome: string, escolhida: bool}>
     */
    public function imagensDisponiveis(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $atual = (string) ($this->fields['imagem_caminho'] ?? '');
        $it = $DB->request([
            'SELECT'     => ['glpi_documents.id', 'glpi_documents.filename', 'glpi_documents.mime'],
            'FROM'       => 'glpi_documents_items',
            'INNER JOIN' => ['glpi_documents' => ['ON' => ['glpi_documents' => 'id', 'glpi_documents_items' => 'documents_id']]],
            'WHERE'      => ['glpi_documents_items.itemtype' => self::class, 'glpi_documents_items.items_id' => (int) $this->getID()],
            'ORDER'      => 'glpi_documents.id',
        ]);
        foreach ($it as $d) {
            if (!str_starts_with((string) $d['mime'], 'image/')) {
                continue;
            }
            $saida[] = [
                'id'       => (int) $d['id'],
                'nome'     => (string) $d['filename'],
                'escolhida' => $atual !== '' && str_contains($atual, '/capa-' . (int) $d['id'] . '.'),
            ];
        }

        return $saida;
    }

    public function urlImagem(): string
    {
        if ((string) ($this->fields['imagem_caminho'] ?? '') === '') {
            return '';
        }

        return \Html::getPrefixedUrl('/plugins/pessoasplus/front/imagem.php') . '?id=' . (int) $this->getID() . '&v=' . substr(md5((string) $this->fields['imagem_caminho'] . (string) $this->fields['date_mod']), 0, 8);
    }

    // ----------------------------------------------------------------- publicar / fixar

    public function publicar(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('mural')) {
            return false;
        }
        if (trim((string) $this->fields['chamada']) === '') {
            Session::addMessageAfterRedirect('Escreva a chamada: é o que aparece no mural.', false, ERROR);

            return false;
        }
        if ((string) ($this->fields['inicio'] ?? '') === '') {
            Session::addMessageAfterRedirect('Informe o início do período de exibição.', false, ERROR);

            return false;
        }
        if (Publico::resolverRegras($this->regrasDoPublico()) === []) {
            Session::addMessageAfterRedirect('O público está vazio: ninguém veria a publicação.', false, ERROR);

            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->update(self::getTable(), ['situacao' => 'publicada', 'date_mod' => $agora], ['id' => (int) $this->getID()]);
        $this->fields['situacao'] = 'publicada';
        Evento::registrar('mural', 'publicacao_publicada', $this, 0, ['titulo' => $this->fields['titulo'], 'lugar' => $this->fields['lugar']]);

        return true;
    }

    public function despublicar(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('mural')) {
            return false;
        }
        $DB->update(self::getTable(), ['situacao' => 'rascunho'], ['id' => (int) $this->getID()]);
        $this->fields['situacao'] = 'rascunho';
        Evento::registrar('mural', 'publicacao_despublicada', $this, 0, ['titulo' => $this->fields['titulo']]);

        return true;
    }

    /** "Trazer ao topo": so atualiza o carimbo de fixacao (D-59). */
    public function trazerAoTopo(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('mural') || (int) $this->fields['fixada'] !== 1) {
            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->update(self::getTable(), ['fixada_em' => $agora], ['id' => (int) $this->getID()]);
        $this->fields['fixada_em'] = $agora;

        return true;
    }

    // ----------------------------------------------------------------- estado e consultas

    /**
     * @param array<string, mixed> $p linha da publicacao
     */
    public static function estado(array $p, string $hoje): string
    {
        if (($p['situacao'] ?? 'rascunho') !== 'publicada') {
            return 'rascunho';
        }
        $inicio = (string) ($p['inicio'] ?? '');
        $fim    = (string) ($p['fim'] ?? '');
        if ($inicio !== '' && $inicio > $hoje) {
            return 'agendada';
        }
        if ($fim !== '' && $fim < $hoje) {
            return 'encerrada';
        }

        return 'no_ar';
    }

    public const ESTADOS = [
        'no_ar'     => ['No ar', 'ok'],
        'agendada'  => ['Agendada', 'info'],
        'rascunho'  => ['Rascunho', 'neutro'],
        'encerrada' => ['Encerrada', 'neutro'],
    ];

    public static function periodoLegivel(?string $inicio, ?string $fim): string
    {
        $i = $inicio ? date('d/m/Y', strtotime($inicio)) : '';
        $f = $fim ? date('d/m/Y', strtotime($fim)) : '';
        if ($i !== '' && $f !== '') {
            return 'de ' . $i . ' a ' . $f;
        }
        if ($i !== '') {
            return 'desde ' . $i;
        }

        return $f !== '' ? 'até ' . $f : 'sem período';
    }

    /**
     * Linha pronta para as telas (toda chave existe, T-06).
     *
     * @param array<string, mixed> $p
     *
     * @return array<string, mixed>
     */
    public static function paraTela(array $p, string $hoje): array
    {
        $estado = self::estado($p, $hoje);
        $tipo   = self::TIPOS[$p['tipo']] ?? [$p['tipo'], 'ti ti-news', 'neutro'];
        $pub    = new self();
        $pub->fields = $p;
        $botoes = [];
        if (($p['lugar'] ?? '') === 'grande') {
            $botoes = Comunicado::linksParaTela((string) ($p['botoes'] ?? ''));
        }

        return [
            'id'             => (int) $p['id'],
            'titulo'         => (string) $p['titulo'],
            'chamada'        => (string) $p['chamada'],
            'tipo'           => (string) $p['tipo'],
            'tipo_rotulo'    => $tipo[0],
            'icone'          => $tipo[1],
            'tom'            => $tipo[2],
            'lugar'          => (string) $p['lugar'],
            'lugar_rotulo'   => self::LUGARES[$p['lugar']][0] ?? $p['lugar'],
            'estado'         => $estado,
            'estado_rotulo'  => self::ESTADOS[$estado][0],
            'estado_tom'     => self::ESTADOS[$estado][1],
            'periodo'        => self::periodoLegivel($p['inicio'] ?? null, $p['fim'] ?? null),
            'inicio'         => (string) ($p['inicio'] ?? ''),
            'fim'            => (string) ($p['fim'] ?? ''),
            'fixada'         => (int) ($p['fixada'] ?? 0) === 1,
            'fixada_em'      => (string) ($p['fixada_em'] ?? ''),
            'imagem_url'     => ($p['lugar'] === 'grande' || $p['lugar'] === 'cartao') ? $pub->urlImagem() : '',
            'imagem_alt'     => (string) ($p['imagem_alt'] ?? ''),
            'botoes'         => $botoes,
            'tem_conteudo'   => trim(strip_tags((string) ($p['conteudo'] ?? ''))) !== '',
            'autor'          => (int) ($p['users_id'] ?? 0) > 0 ? (string) getUserName((int) $p['users_id']) : 'RH',
            'visualizacoes'  => countElementsInTable('glpi_plugin_pessoasplus_vistas', ['plugin_pessoasplus_publicacoes_id' => (int) $p['id']]),
            'url'            => \Html::getPrefixedUrl('/plugins/pessoasplus/front/publicacao.php') . '?id=' . (int) $p['id'],
            'url_editar'     => \Html::getPrefixedUrl('/plugins/pessoasplus/front/publicacao_nova.php') . '?id=' . (int) $p['id'],
        ];
    }

    /**
     * Ordem do mural (D-59): fixadas pelo carimbo mais recente, depois inicio
     * mais recente, depois id.
     *
     * @param array<int, array<string, mixed>> $linhas
     *
     * @return array<int, array<string, mixed>>
     */
    public static function ordenar(array $linhas): array
    {
        usort($linhas, static function (array $a, array $b): int {
            $fa = (int) ($a['fixada'] ?? 0);
            $fb = (int) ($b['fixada'] ?? 0);
            if ($fa !== $fb) {
                return $fb <=> $fa;
            }
            if ($fa === 1) {
                $c = strcmp((string) ($b['fixada_em'] ?? ''), (string) ($a['fixada_em'] ?? ''));
                if ($c !== 0) {
                    return $c;
                }
            }
            $c = strcmp((string) ($b['inicio'] ?? ''), (string) ($a['inicio'] ?? ''));

            return $c !== 0 ? $c : ((int) $b['id'] <=> (int) $a['id']);
        });

        return $linhas;
    }

    /**
     * Lista da area do RH, com filtros (situacao, periodo, busca).
     *
     * @param array{situacao: string, busca: string} $filtros
     *
     * @return array{publicacoes: array<int, array<string, mixed>>, resumo: array<string, int>}
     */
    public static function lista(array $filtros, ?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje ??= date('Y-m-d');
        $where = ['is_deleted' => 0];
        if ($filtros['busca'] !== '') {
            $like = '%' . $filtros['busca'] . '%';
            $where[] = ['OR' => [['titulo' => ['LIKE', $like]], ['chamada' => ['LIKE', $like]]]];
        }
        $linhas = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => $where]) as $p) {
            $linhas[] = $p;
        }
        $resumo = array_fill_keys(array_keys(self::ESTADOS), 0);
        $saida  = [];
        foreach (self::ordenar($linhas) as $p) {
            $t = self::paraTela($p, $hoje);
            $resumo[$t['estado']]++;
            if ($filtros['situacao'] !== '' && $filtros['situacao'] !== $t['estado']) {
                continue;
            }
            $saida[] = $t;
        }
        // Lugar: destaque, grande, cartao, lateral (ordem da D-59) dentro da ordem geral.
        $peso = array_flip(array_keys(self::LUGARES));
        usort($saida, static fn ($a, $b) => ($peso[$a['lugar']] <=> $peso[$b['lugar']]) ?: 0);

        return ['publicacoes' => $saida, 'resumo' => $resumo];
    }

    /**
     * Publicacoes no ar que alcancam o usuario (publico resolvido agora, D-72),
     * agrupadas por lugar e na ordem do mural. Destaque: um so (D-63).
     *
     * @return array{destaque: ?array<string, mixed>, grandes: array<int, array<string, mixed>>, cartoes: array<int, array<string, mixed>>, laterais: array<int, array<string, mixed>>, nao_vistas: int, total: int}
     */
    public static function muralDe(int $uid, ?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje ??= date('Y-m-d');
        $vistas = [];
        foreach ($DB->request(['SELECT' => ['plugin_pessoasplus_publicacoes_id'], 'FROM' => 'glpi_plugin_pessoasplus_vistas', 'WHERE' => ['users_id' => $uid]]) as $v) {
            $vistas[(int) $v['plugin_pessoasplus_publicacoes_id']] = true;
        }
        $alcance = []; // cache por publico: publicos_id => bool
        $linhas  = [];
        $it = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['is_deleted' => 0, 'situacao' => 'publicada'],
        ]);
        foreach ($it as $p) {
            if (self::estado($p, $hoje) !== 'no_ar') {
                continue;
            }
            $pid = (int) $p['plugin_pessoasplus_publicos_id'];
            if (!isset($alcance[$pid])) {
                $alcance[$pid] = $pid > 0 && in_array($uid, Publico::resolver($pid), true);
            }
            if (!$alcance[$pid]) {
                continue;
            }
            $linhas[] = $p;
        }
        $saida = ['destaque' => null, 'grandes' => [], 'cartoes' => [], 'laterais' => [], 'nao_vistas' => 0, 'total' => 0];
        foreach (self::ordenar($linhas) as $p) {
            $t = self::paraTela($p, $hoje);
            $t['nova'] = !isset($vistas[$t['id']]);
            if ($t['nova']) {
                $saida['nao_vistas']++;
            }
            $saida['total']++;
            switch ($t['lugar']) {
                case 'destaque':
                    // Vale o de inicio mais recente (D-63): a ordem ja e por inicio desc.
                    if ($saida['destaque'] === null) {
                        $saida['destaque'] = $t;
                    }
                    break;
                case 'grande':
                    $saida['grandes'][] = $t;
                    break;
                case 'lateral':
                    $saida['laterais'][] = $t;
                    break;
                default:
                    $saida['cartoes'][] = $t;
            }
        }

        return $saida;
    }

    /** O usuario alcanca a publicacao (publico resolvido agora)? */
    public static function alcanca(int $publicacaoId, int $uid): bool
    {
        $p = new self();
        if (!$p->getFromDB($publicacaoId) || (int) $p->fields['is_deleted'] === 1) {
            return false;
        }
        $pid = (int) $p->fields['plugin_pessoasplus_publicos_id'];

        return $pid > 0 && in_array($uid, Publico::resolver($pid), true);
    }

    /** Marca como vista (uma linha por pessoa; repetir nao faz nada). */
    public static function marcarVistas(array $ids, int $uid): void
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($uid <= 0 || $ids === []) {
            return;
        }
        $ja = [];
        foreach ($DB->request(['SELECT' => ['plugin_pessoasplus_publicacoes_id'], 'FROM' => 'glpi_plugin_pessoasplus_vistas', 'WHERE' => ['users_id' => $uid, 'plugin_pessoasplus_publicacoes_id' => $ids]]) as $v) {
            $ja[(int) $v['plugin_pessoasplus_publicacoes_id']] = true;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        foreach ($ids as $id) {
            if (!isset($ja[(int) $id])) {
                $DB->insert('glpi_plugin_pessoasplus_vistas', ['plugin_pessoasplus_publicacoes_id' => (int) $id, 'users_id' => $uid, 'data' => $agora]);
            }
        }
    }

    /** Ha publicacao no ar, no publico do usuario, que ele ainda nao viu? (D-54) */
    public static function temNovidadePara(int $uid): bool
    {
        return $uid > 0 && self::muralDe($uid)['nao_vistas'] > 0;
    }
}
