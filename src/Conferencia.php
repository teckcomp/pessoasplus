<?php

/**
 * Pessoas+ - conferencia periodica dos itens em cautela (D-60; BM.6-2).
 *
 * PESSOASPLUS_BUILD_BM6_2
 * PESSOASPLUS_BUILD_BM6_3
 *
 * O RH abre uma campanha por publico e prazo. Cada pessoa do publico que tem
 * itens em posse (entregas assinadas menos devolucoes) confere item a item:
 * "esta comigo", "com avaria" ou "nao esta comigo", com observacao e foto
 * (obrigatoria nos itens marcados no termo). A resposta pode ser do proprio
 * colaborador ou do RH por ele; o registro diz quem respondeu, a interface e
 * a hora do servidor. Avaria e "nao esta comigo" viram pendencia ao RH.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;
use Session;

final class Conferencia extends CommonDBTM
{
    public static $rightname = 'pessoasplus_cautela';
    public $dohistory = false;

    /** Subpasta em GLPI_PLUGIN_DOC_DIR para as fotos. */
    public const PASTA = 'pessoasplus/conferencias';

    public const SITUACOES = [
        'rascunho'  => ['Rascunho', 'neutro'],
        'aberta'    => ['Aberta', 'ok'],
        'encerrada' => ['Encerrada', 'neutro'],
    ];

    public const RESPOSTAS = [
        'comigo'   => ['Está comigo', 'ok', 'ti ti-circle-check'],
        'avaria'   => ['Com avaria', 'alerta', 'ti ti-alert-triangle'],
        'nao_esta' => ['Não está comigo', 'atraso', 'ti ti-circle-x'],
    ];

    /** Modo do publico: todos com itens em cautela, ou recorte por regras. */
    public const MODOS_PUBLICO = [
        'todos'   => 'Todos que têm itens em cautela',
        'recorte' => 'Recorte por público (grupo, perfil, pessoa…)',
    ];

    /** Recorrencia: chave => [rotulo, intervalo DateInterval]. */
    public const RECORRENCIAS = [
        'nenhuma'    => ['Não repetir', ''],
        'mensal'     => ['Mensal', 'P1M'],
        'trimestral' => ['Trimestral', 'P3M'],
        'semestral'  => ['Semestral', 'P6M'],
        'anual'      => ['Anual', 'P1Y'],
    ];

    /** Limite da foto depois da compressao no navegador (bytes). */
    public const FOTO_MAX = 3 * 1024 * 1024;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_cautela_conferencias';
    }

    public static function tabelaRespostas(): string
    {
        return 'glpi_plugin_pessoasplus_cautela_conferencias_respostas';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Conferências' : 'Conferência';
    }

    public static function getIcon()
    {
        return 'ti ti-clipboard-check';
    }

    public static function canCreate(): bool
    {
        return Permissoes::gerencia('cautela');
    }

    public static function canView(): bool
    {
        return Permissoes::usa();
    }

    public static function canUpdate(): bool
    {
        return Permissoes::gerencia('cautela');
    }

    public static function canDelete(): bool
    {
        return Permissoes::gerencia('cautela');
    }

    public static function canPurge(): bool
    {
        return false;
    }

    public function canCreateItem(): bool
    {
        return Permissoes::gerencia('cautela');
    }

    /** RH que le Cautela ve tudo; o colaborador ve a campanha de que participa. */
    public function canViewItem(): bool
    {
        if (!Permissoes::usa()) {
            return false;
        }
        if (Permissoes::le('cautela')) {
            return true;
        }

        // Quem esta no publico abre a pagina mesmo sem itens (ve "nada a conferir"), nao um 403.
        return (string) $this->fields['situacao'] === 'aberta' && in_array((int) Session::getLoginUserID(), $this->usuariosDoPublico(), true);
    }

    public function canUpdateItem(): bool
    {
        return Permissoes::gerencia('cautela') && (string) $this->fields['situacao'] !== 'encerrada';
    }

    public function canDeleteItem(): bool
    {
        return Permissoes::gerencia('cautela') && (string) $this->fields['situacao'] === 'rascunho';
    }

    public function canPurgeItem(): bool
    {
        return false;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->normalizar($input);
        $input['situacao']      = 'rascunho';
        $input['users_id_rh']   = (int) Session::getLoginUserID();
        $input['entities_id']   = (int) ($_SESSION['glpiactive_entity'] ?? 0);
        $input['date_creation'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $input['date_mod']      = $input['date_creation'];
        if (($input['titulo'] ?? '') === '') {
            $input['titulo'] = 'Conferência ' . date('m/Y');
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        $input = $this->normalizar($input);
        unset($input['situacao'], $input['users_id_rh']);
        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        return $input;
    }

    private function normalizar(array $input): array
    {
        if (array_key_exists('titulo', $input)) {
            $input['titulo'] = mb_substr(trim((string) $input['titulo']), 0, 255);
        }
        if (array_key_exists('orientacoes', $input)) {
            $input['orientacoes'] = mb_substr(trim((string) $input['orientacoes']), 0, 2000);
        }
        if (array_key_exists('publico_modo', $input) && !isset(self::MODOS_PUBLICO[$input['publico_modo']])) {
            $input['publico_modo'] = 'todos';
        }
        if (array_key_exists('recorrencia', $input) && !isset(self::RECORRENCIAS[$input['recorrencia']])) {
            $input['recorrencia'] = 'nenhuma';
        }
        foreach (['inicio', 'fim'] as $c) {
            if (array_key_exists($c, $input)) {
                $v = trim((string) $input[$c]);
                $input[$c] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
            }
        }
        if (isset($input['inicio'], $input['fim']) && $input['inicio'] !== null && $input['fim'] !== null && $input['fim'] < $input['inicio']) {
            $input['fim'] = $input['inicio'];
        }

        return $input;
    }

    public function cleanDBonPurge()
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->delete(self::tabelaRespostas(), ['plugin_pessoasplus_cautela_conferencias_id' => (int) $this->getID()]);
    }

    // ----------------------------------------------------------------- publico

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

    public function regrasDoPublico(): array
    {
        $publicoId = (int) ($this->fields['plugin_pessoasplus_publicos_id'] ?? 0);

        return $publicoId > 0 ? Publico::regras($publicoId) : [];
    }

    /** Usuarios do publico (D-72: resolvido a cada abertura). Modo "todos": quem tem itens em cautela. */
    public function usuariosDoPublico(): array
    {
        if ((($this->fields['publico_modo'] ?? '') ?: 'todos') === 'todos') {
            return self::usuariosComItens();
        }

        return Publico::resolverRegras($this->regrasDoPublico());
    }

    /** Usuarios (ligados a fichas) com pelo menos um item em posse. */
    public static function usuariosComItens(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $it = $DB->request([
            'SELECT'   => ['plugin_pessoasplus_colaboradores_id', 'users_id'],
            'DISTINCT' => true,
            'FROM'     => Cautela::getTable(),
            'WHERE'    => ['is_deleted' => 0, 'situacao' => 'assinado', 'tipo' => 'entrega', 'users_id' => ['>', 0]],
        ]);
        foreach ($it as $t) {
            $uid = (int) $t['users_id'];
            if (isset($saida[$uid])) {
                continue;
            }
            if (Cautela::emPosseDe((int) $t['plugin_pessoasplus_colaboradores_id']) !== []) {
                $saida[$uid] = $uid;
            }
        }
        $lista = array_values($saida);
        sort($lista);

        return $lista;
    }

    public function participa(int $uid): bool
    {
        if ($uid <= 0 || !in_array($uid, $this->usuariosDoPublico(), true)) {
            return false;
        }
        $col = Colaborador::porUsuario($uid);

        return $col !== null && Cautela::emPosseDe((int) $col->getID()) !== [];
    }

    // ----------------------------------------------------------------- ciclo

    public function abrir(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('cautela') || (string) $this->fields['situacao'] !== 'rascunho') {
            return false;
        }
        if ($this->usuariosDoPublico() === []) {
            Session::addMessageAfterRedirect((($this->fields['publico_modo'] ?? '') ?: 'todos') === 'todos' ? 'Ninguém tem itens em cautela com ciência registrada: nada a conferir ainda.' : 'O público está vazio: ninguém conferiria.', false, ERROR);

            return false;
        }
        if ((string) ($this->fields['inicio'] ?? '') === '') {
            Session::addMessageAfterRedirect('Informe o início do prazo.', false, ERROR);

            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->update(self::getTable(), ['situacao' => 'aberta', 'aberta_em' => $agora, 'date_mod' => $agora], ['id' => (int) $this->getID()]);
        $this->fields['situacao'] = 'aberta';

        return true;
    }

    public function encerrar(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('cautela') || (string) $this->fields['situacao'] !== 'aberta') {
            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->update(self::getTable(), ['situacao' => 'encerrada', 'encerrada_em' => $agora, 'date_mod' => $agora], ['id' => (int) $this->getID()]);
        $this->fields['situacao'] = 'encerrada';

        return true;
    }

    /** Aberta e dentro do prazo (fim vazio = sem fim). */
    public function recebeRespostas(?string $hoje = null): bool
    {
        $hoje ??= date('Y-m-d');
        if ((string) $this->fields['situacao'] !== 'aberta') {
            return false;
        }
        $inicio = (string) ($this->fields['inicio'] ?? '');
        $fim    = (string) ($this->fields['fim'] ?? '');

        return ($inicio === '' || $inicio <= $hoje) && ($fim === '' || $fim >= $hoje);
    }

    // ----------------------------------------------------------------- itens e respostas

    /** Chave estavel do item em posse (a mesma de Cautela::emPosseDe). */
    public static function chaveItem(array $i): string
    {
        $texto = ($i['origem'] ?? '') === 'glpi'
            ? ($i['itemtype'] ?? '') . '#' . (int) ($i['items_id'] ?? 0)
            : 'L|' . mb_strtolower((string) ($i['descricao'] ?? '')) . '|' . ($i['patrimonio'] ?? '') . '|' . ($i['serie'] ?? '');

        return substr(hash('sha256', $texto), 0, 32);
    }

    /**
     * Itens que a pessoa deve conferir nesta campanha, com a resposta ja dada (se houver).
     *
     * @return array<int, array<string, mixed>>
     */
    public function itensPara(int $colaboradorId): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $respostas = [];
        foreach ($DB->request(['FROM' => self::tabelaRespostas(), 'WHERE' => ['plugin_pessoasplus_cautela_conferencias_id' => (int) $this->getID(), 'plugin_pessoasplus_colaboradores_id' => $colaboradorId]]) as $r) {
            $respostas[(string) $r['chave']] = $r;
        }
        $saida = [];
        foreach (Cautela::emPosseDe($colaboradorId) as $i) {
            $chave = self::chaveItem($i);
            $r = $respostas[$chave] ?? null;
            $sit = $r !== null ? (string) $r['situacao'] : '';
            $saida[] = $i + [
                'chave'          => $chave,
                'resposta'       => $sit,
                'resposta_rotulo' => $sit !== '' ? self::RESPOSTAS[$sit][0] : 'Pendente',
                'resposta_tom'   => $sit !== '' ? self::RESPOSTAS[$sit][1] : 'neutro',
                'observacao'     => $r !== null ? (string) $r['observacao'] : '',
                'foto'           => $r !== null && (string) $r['foto_arquivo'] !== '',
                'foto_url'       => $r !== null && (string) $r['foto_arquivo'] !== '' ? \Html::getPrefixedUrl('/plugins/pessoasplus/front/cautela_foto.php') . '?id=' . (int) $r['id'] : '',
                'respondido_em'  => $r !== null ? date('d/m/Y H:i', strtotime((string) $r['data'])) : '',
                'respondido_por' => $r !== null ? ((int) $r['users_id_resposta'] === (int) $r['users_id'] ? 'a própria pessoa' : (string) getUserName((int) $r['users_id_resposta'])) : '',
                'resposta_id'    => $r !== null ? (int) $r['id'] : 0,
            ];
        }

        return $saida;
    }

    /**
     * Registra (ou substitui, enquanto a campanha esta aberta) a resposta de um item.
     *
     * @param array{nome: string, tmp_name: string, size: int, error: int}|null $foto arquivo enviado (JPEG/PNG) ou null
     *
     * @return string '' se gravou, ou a mensagem de erro
     */
    public function responder(int $colaboradorId, string $chave, string $situacao, string $observacao, ?array $foto, int $quemResponde, string $interface): string
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!$this->recebeRespostas()) {
            return 'A campanha não está recebendo respostas.';
        }
        $col = new Colaborador();
        if (!$col->getFromDB($colaboradorId)) {
            return 'Colaborador não encontrado.';
        }
        $uid = (int) $col->fields['users_id'];
        $proprio = $quemResponde === $uid && $uid > 0;
        if (!$proprio && !Permissoes::gerencia('cautela')) {
            return 'Só a própria pessoa ou o RH registram a conferência.';
        }
        if (!isset(self::RESPOSTAS[$situacao])) {
            return 'Escolha a situação do item.';
        }
        $item = null;
        foreach (Cautela::emPosseDe($colaboradorId) as $i) {
            if (self::chaveItem($i) === $chave) {
                $item = $i;
                break;
            }
        }
        if ($item === null) {
            return 'Item não está mais em posse desta pessoa.';
        }
        $observacao = mb_substr(trim(preg_replace('/\s+/u', ' ', $observacao) ?? ''), 0, 1000);
        if ($situacao !== 'comigo' && $observacao === '') {
            return 'Descreva a avaria ou onde o item está.';
        }
        $existente = null;
        foreach ($DB->request(['FROM' => self::tabelaRespostas(), 'WHERE' => ['plugin_pessoasplus_cautela_conferencias_id' => (int) $this->getID(), 'plugin_pessoasplus_colaboradores_id' => $colaboradorId, 'chave' => $chave]]) as $r) {
            $existente = $r;
        }
        $fotoArquivo = $existente !== null ? (string) $existente['foto_arquivo'] : '';
        $fotoHash    = $existente !== null ? (string) $existente['foto_hash'] : '';
        if ($foto !== null && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $erro = self::guardarFoto($foto, (int) $this->getID(), $colaboradorId, $chave, $fotoArquivo, $fotoHash);
            if ($erro !== '') {
                return $erro;
            }
        }
        if ($item['foto_obrigatoria'] && $situacao !== 'nao_esta' && $fotoArquivo === '') {
            return 'Este item exige foto na conferência.';
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $linha = [
            'plugin_pessoasplus_cautela_conferencias_id' => (int) $this->getID(),
            'plugin_pessoasplus_colaboradores_id'        => $colaboradorId,
            'users_id'          => $uid,
            'chave'             => $chave,
            'plugin_pessoasplus_cautela_termos_id' => (int) ($item['termo_id'] ?? 0),
            'origem'            => (string) $item['origem'],
            'itemtype'          => (string) $item['itemtype'],
            'items_id'          => (int) $item['items_id'],
            'descricao'         => (string) $item['descricao'],
            'patrimonio'        => (string) $item['patrimonio'],
            'serie'             => (string) $item['serie'],
            'quantidade'        => (int) $item['quantidade'],
            'situacao'          => $situacao,
            'observacao'        => $observacao,
            'foto_arquivo'      => $fotoArquivo,
            'foto_hash'         => $fotoHash,
            'data'              => $agora,
            'users_id_resposta' => $quemResponde,
            'interface'         => $interface === 'helpdesk' ? 'helpdesk' : 'central',
        ];
        if ($existente !== null) {
            $DB->update(self::tabelaRespostas(), $linha, ['id' => (int) $existente['id']]);
        } else {
            $DB->insert(self::tabelaRespostas(), $linha);
        }
        if ($situacao !== 'comigo') {
            Evento::registrar('cautela', 'conferencia_divergencia', $this, $colaboradorId, ['item' => $item['descricao'], 'situacao' => self::RESPOSTAS[$situacao][0], 'observacao' => $observacao, 'conferencia' => (string) $this->fields['titulo']]);
        }
        $itens = $this->itensPara($colaboradorId);
        $faltam = count(array_filter($itens, static fn ($i) => $i['resposta'] === ''));
        if ($faltam === 0 && $itens !== []) {
            Evento::registrar('cautela', 'conferencia_concluida', $this, $colaboradorId, ['conferencia' => (string) $this->fields['titulo'], 'itens' => count($itens), 'por' => $proprio ? 'propria pessoa' : 'RH']);
        }

        return '';
    }

    /** Guarda a foto (JPEG/PNG, ate FOTO_MAX) fora do webroot, com hash. */
    private static function guardarFoto(array $foto, int $confId, int $colaboradorId, string $chave, string &$arquivo, string &$hash): string
    {
        if ((int) $foto['error'] !== UPLOAD_ERR_OK) {
            return 'Falha no envio da foto (código ' . (int) $foto['error'] . ').';
        }
        if ((int) $foto['size'] > self::FOTO_MAX) {
            return 'Foto maior que ' . (int) (self::FOTO_MAX / 1024 / 1024) . ' MB. Tente de novo: o navegador reduz a foto antes de enviar.';
        }
        $info = @getimagesize((string) $foto['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return 'A foto precisa ser JPEG ou PNG.';
        }
        $ext   = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $pasta = GLPI_PLUGIN_DOC_DIR . '/' . self::PASTA . '/' . $confId;
        if (!is_dir($pasta) && !@mkdir($pasta, 0755, true)) {
            return 'Não foi possível criar a pasta das fotos.';
        }
        $nome    = $colaboradorId . '_' . substr($chave, 0, 12) . '_' . date('YmdHis') . '.' . $ext;
        $destino = $pasta . '/' . $nome;
        if (!@move_uploaded_file((string) $foto['tmp_name'], $destino) && !@rename((string) $foto['tmp_name'], $destino)) {
            return 'Não foi possível gravar a foto.';
        }
        // A foto anterior deste item sai: a conferencia guarda a ultima.
        if ($arquivo !== '' && is_file(GLPI_PLUGIN_DOC_DIR . '/' . $arquivo)) {
            @unlink(GLPI_PLUGIN_DOC_DIR . '/' . $arquivo);
        }
        $arquivo = self::PASTA . '/' . $confId . '/' . $nome;
        $hash    = hash_file('sha256', $destino) ?: '';

        return '';
    }

    public static function respostaPorId(int $id): ?array
    {
        /** @var DBmysql $DB */
        global $DB;

        foreach ($DB->request(['FROM' => self::tabelaRespostas(), 'WHERE' => ['id' => $id]]) as $r) {
            return $r;
        }

        return null;
    }

    // ----------------------------------------------------------------- visao do RH

    /**
     * Pessoas da campanha: publico com itens em posse, progresso e divergencias.
     *
     * @return array<int, array{colaborador_id: int, users_id: int, nome: string, setor: string, itens: int, respondidos: int, divergentes: int, situacao: string, tom: string, url_conferir: string}>
     */
    public function pessoas(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $uids = $this->usuariosDoPublico();
        if ($uids === []) {
            return [];
        }
        $respostas = [];
        foreach ($DB->request(['FROM' => self::tabelaRespostas(), 'WHERE' => ['plugin_pessoasplus_cautela_conferencias_id' => (int) $this->getID()]]) as $r) {
            $respostas[(int) $r['plugin_pessoasplus_colaboradores_id']][] = $r;
        }
        $saida = [];
        $base  = \Html::getPrefixedUrl('/plugins/pessoasplus/front/conferir.php') . '?id=' . (int) $this->getID() . '&colaborador=';
        foreach ($DB->request(['SELECT' => ['id', 'nome', 'users_id', 'groups_id'], 'FROM' => Colaborador::getTable(), 'WHERE' => ['users_id' => $uids, 'is_deleted' => 0], 'ORDER' => 'nome']) as $c) {
            $cid   = (int) $c['id'];
            $itens = Cautela::emPosseDe($cid);
            if ($itens === []) {
                continue;
            }
            $chaves = array_flip(array_map([self::class, 'chaveItem'], $itens));
            $resp = 0;
            $div  = 0;
            foreach ($respostas[$cid] ?? [] as $r) {
                if (!isset($chaves[(string) $r['chave']])) {
                    continue;
                }
                $resp++;
                if ((string) $r['situacao'] !== 'comigo') {
                    $div++;
                }
            }
            $total = count($itens);
            if ($resp === 0) {
                $sit = ['Não conferiu', 'atraso'];
            } elseif ($resp < $total) {
                $sit = ['Parcial', 'alerta'];
            } elseif ($div > 0) {
                $sit = ['Com divergência', 'alerta'];
            } else {
                $sit = ['Conferido', 'ok'];
            }
            $setor = '';
            if ((int) $c['groups_id'] > 0) {
                $g = new \Group();
                if ($g->getFromDB((int) $c['groups_id'])) {
                    $setor = (string) $g->fields['completename'];
                }
            }
            $saida[] = [
                'colaborador_id' => $cid,
                'users_id'       => (int) $c['users_id'],
                'nome'           => (string) $c['nome'],
                'setor'          => $setor,
                'itens'          => $total,
                'respondidos'    => $resp,
                'divergentes'    => $div,
                'situacao'       => $sit[0],
                'tom'            => $sit[1],
                'url_conferir'   => $base . $cid,
            ];
        }

        return $saida;
    }

    /** Divergencias (avaria / nao esta) de uma campanha, para a fila do RH. */
    public function divergencias(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $nomes = [];
        foreach ($DB->request(['FROM' => self::tabelaRespostas(), 'WHERE' => ['plugin_pessoasplus_cautela_conferencias_id' => (int) $this->getID(), 'NOT' => ['situacao' => 'comigo']], 'ORDER' => 'data DESC']) as $r) {
            $cid = (int) $r['plugin_pessoasplus_colaboradores_id'];
            if (!isset($nomes[$cid])) {
                $col = new Colaborador();
                $nomes[$cid] = $col->getFromDB($cid) ? (string) $col->fields['nome'] : '#' . $cid;
            }
            $saida[] = [
                'id'          => (int) $r['id'],
                'colaborador' => $nomes[$cid],
                'colaborador_id' => $cid,
                'descricao'   => (string) $r['descricao'],
                'patrimonio'  => (string) $r['patrimonio'],
                'situacao'    => (string) $r['situacao'],
                'rotulo'      => self::RESPOSTAS[$r['situacao']][0] ?? $r['situacao'],
                'tom'         => self::RESPOSTAS[$r['situacao']][1] ?? 'neutro',
                'observacao'  => (string) $r['observacao'],
                'foto_url'    => (string) $r['foto_arquivo'] !== '' ? \Html::getPrefixedUrl('/plugins/pessoasplus/front/cautela_foto.php') . '?id=' . (int) $r['id'] : '',
                'data'        => date('d/m/Y H:i', strtotime((string) $r['data'])),
                'url_pasta'   => \Html::getPrefixedUrl('/plugins/pessoasplus/front/colaborador.form.php') . '?id=' . $cid . '&aba=cautela',
            ];
        }

        return $saida;
    }

    // ----------------------------------------------------------------- recorrencia e cron (BM.6-3)

    /** Datas da proxima campanha: mesmo dia do mes seguinte ao fim (ou ao inicio), mesma duracao. */
    public static function proximoPeriodo(string $inicio, string $fim, string $recorrencia): ?array
    {
        $intervalo = self::RECORRENCIAS[$recorrencia][1] ?? '';
        if ($intervalo === '' || $inicio === '') {
            return null;
        }
        try {
            $i = new \DateTimeImmutable($inicio);
            $novoInicio = $i->add(new \DateInterval($intervalo));
            $novoFim = null;
            if ($fim !== '') {
                $dias = (int) $i->diff(new \DateTimeImmutable($fim))->days;
                $novoFim = $novoInicio->add(new \DateInterval('P' . max(0, $dias) . 'D'));
            }
        } catch (\Exception $e) {
            return null;
        }

        return ['inicio' => $novoInicio->format('Y-m-d'), 'fim' => $novoFim !== null ? $novoFim->format('Y-m-d') : null];
    }

    /** Cria e abre a proxima campanha de uma recorrente (mesmo publico, orientacoes e duracao). */
    public function gerarProxima(): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $periodo = self::proximoPeriodo((string) ($this->fields['inicio'] ?? ''), (string) ($this->fields['fim'] ?? ''), (string) (($this->fields['recorrencia'] ?? '') ?: 'nenhuma'));
        if ($periodo === null) {
            return 0;
        }
        // Idempotente: se ja existe uma gerada desta, nao duplica.
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_pessoasplus_cautela_conferencias_id_origem' => (int) $this->getID(), 'is_deleted' => 0], 'LIMIT' => 1]) as $x) {
            return 0;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->insert(self::getTable(), [
            'entities_id'   => (int) ($this->fields['entities_id'] ?? 0),
            'titulo'        => preg_replace('/\s+\(\d{2}\/\d{4}\)$/', '', (string) $this->fields['titulo']) . ' (' . date('m/Y', strtotime($periodo['inicio'])) . ')',
            'orientacoes'   => (string) ($this->fields['orientacoes'] ?? ''),
            'situacao'      => 'aberta',
            'inicio'        => $periodo['inicio'],
            'fim'           => $periodo['fim'],
            'plugin_pessoasplus_publicos_id' => 0,
            'publico_modelo_id' => (int) ($this->fields['publico_modelo_id'] ?? 0),
            'publico_modo'  => (string) (($this->fields['publico_modo'] ?? '') ?: 'todos'),
            'recorrencia'   => (string) (($this->fields['recorrencia'] ?? '') ?: 'nenhuma'),
            'plugin_pessoasplus_cautela_conferencias_id_origem' => (int) $this->getID(),
            'users_id_rh'   => (int) ($this->fields['users_id_rh'] ?? 0),
            'aberta_em'     => $agora,
            'date_creation' => $agora,
            'date_mod'      => $agora,
        ]);
        $nid = 0;
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => self::getTable(), 'WHERE' => ['plugin_pessoasplus_cautela_conferencias_id_origem' => (int) $this->getID()], 'ORDER' => 'id DESC', 'LIMIT' => 1]) as $x) {
            $nid = (int) $x['id'];
        }
        if ($nid > 0 && (($this->fields['publico_modo'] ?? '') ?: 'todos') === 'recorte') {
            $novo = new self();
            if ($novo->getFromDB($nid)) {
                $novo->definirPublico($this->regrasDoPublico(), 0);
            }
        }

        return $nid;
    }

    /** Texto da tarefa no Configurar > Ações automáticas (CronTask.php:704). */
    public static function cronInfo($name)
    {
        return ['description' => 'Pessoas+: encerra conferências de cautela vencidas e abre as recorrentes'];
    }

    /**
     * Tarefa diaria: campanhas abertas com prazo final vencido sao encerradas;
     * se recorrentes, a proxima e aberta no dia correspondente.
     */
    public static function cronConferencias(\CronTask $task): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje = date('Y-m-d');
        $n = 0;
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_deleted' => 0, 'situacao' => 'aberta', 'NOT' => ['fim' => null], 'fim' => ['<', $hoje]]]) as $c) {
            $conf = new self();
            $conf->fields = $c;
            $agora = date('Y-m-d H:i:s');
            $DB->update(self::getTable(), ['situacao' => 'encerrada', 'encerrada_em' => $agora, 'date_mod' => $agora], ['id' => (int) $c['id']]);
            $conf->fields['situacao'] = 'encerrada';
            $n++;
            $task->log('Encerrada: ' . $c['titulo'] . ' (#' . (int) $c['id'] . ')');
            if ((($c['recorrencia'] ?? '') ?: 'nenhuma') !== 'nenhuma') {
                $nid = $conf->gerarProxima();
                if ($nid > 0) {
                    $task->log('Aberta a próxima: #' . $nid);
                    $n++;
                }
            }
        }
        $task->setVolume($n);

        return $n > 0 ? 1 : 0;
    }

    // ----------------------------------------------------------------- listas

    public static function paraTela(array $f, ?string $hoje = null): array
    {
        $hoje ??= date('Y-m-d');
        $sit  = self::SITUACOES[$f['situacao']] ?? [$f['situacao'], 'neutro'];
        $inicio = (string) ($f['inicio'] ?? '');
        $fim    = (string) ($f['fim'] ?? '');
        $base   = \Html::getPrefixedUrl('/plugins/pessoasplus/front/');
        $periodo = $inicio !== '' ? ('de ' . date('d/m/Y', strtotime($inicio)) . ($fim !== '' ? ' a ' . date('d/m/Y', strtotime($fim)) : ', sem fim')) : 'sem prazo';
        $atrasada = (string) $f['situacao'] === 'aberta' && $fim !== '' && $fim < $hoje;

        return [
            'id'              => (int) $f['id'],
            'titulo'          => (string) $f['titulo'],
            'orientacoes'     => (string) ($f['orientacoes'] ?? ''),
            'situacao'        => (string) $f['situacao'],
            'situacao_rotulo' => $atrasada ? 'Prazo vencido' : $sit[0],
            'tom'             => $atrasada ? 'atraso' : $sit[1],
            'inicio'          => $inicio,
            'fim'             => $fim,
            'periodo'         => $periodo,
            'atrasada'        => $atrasada,
            'publico_modo'    => (string) (($f['publico_modo'] ?? '') ?: 'todos'),
            'publico_modo_rotulo' => self::MODOS_PUBLICO[($f['publico_modo'] ?? '') ?: 'todos'] ?? 'Todos que têm itens em cautela',
            'recorrencia'     => (string) (($f['recorrencia'] ?? '') ?: 'nenhuma'),
            'recorrencia_rotulo' => self::RECORRENCIAS[($f['recorrencia'] ?? '') ?: 'nenhuma'][0] ?? 'Não repetir',
            'origem_id'       => (int) ($f['plugin_pessoasplus_cautela_conferencias_id_origem'] ?? 0),
            'url'             => $base . 'conferencia.php?id=' . (int) $f['id'],
            'url_conferir'    => $base . 'conferir.php?id=' . (int) $f['id'],
        ];
    }

    public static function lista(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_deleted' => 0], 'ORDER' => ['date_creation DESC', 'id DESC']]) as $c) {
            $saida[] = self::paraTela($c);
        }

        return $saida;
    }

    /** Campanhas abertas em que o usuario participa, com o progresso (Minha area). */
    public static function abertasPara(int $uid, ?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($uid <= 0) {
            return [];
        }
        $col = Colaborador::porUsuario($uid);
        if ($col === null) {
            return [];
        }
        $cid = (int) $col->getID();
        $saida = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_deleted' => 0, 'situacao' => 'aberta'], 'ORDER' => 'fim']) as $c) {
            $conf = new self();
            $conf->fields = $c;
            if (!$conf->recebeRespostas($hoje) || !in_array($uid, $conf->usuariosDoPublico(), true)) {
                continue;
            }
            $itens = $conf->itensPara($cid);
            if ($itens === []) {
                continue;
            }
            $resp = count(array_filter($itens, static fn ($i) => $i['resposta'] !== ''));
            $saida[] = self::paraTela($c, $hoje) + ['itens' => count($itens), 'respondidos' => $resp, 'concluida' => $resp === count($itens)];
        }

        return $saida;
    }
}
