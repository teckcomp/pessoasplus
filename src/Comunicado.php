<?php

/**
 * Pessoas+ - comunicado e normativa (M1): rascunho, publicacao congelada,
 * ciencia, nova versao e revogacao (BM.2).
 *
 * PESSOASPLUS_BUILD_BM2
 * PESSOASPLUS_BUILD_BM2_2
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;
use Document;
use Document_Item;
use Session;

/**
 * Ciclo (D-04, D-09, D-50):
 *
 *  rascunho --publicar--> publicado (versao 1 vigente) --nova versao--> versao 2 vigente,
 *  versao 1 substituida (ciencias dela continuam ligadas a ela) --revogar--> revogado
 *  (versao vigente encerrada, sai das pendencias, evidencia fica).
 *
 * Ao publicar: o conteudo e copiado para a versao com SHA-256; cada anexo
 * (Document nativo ligado ao rascunho) e copiado para
 * files/_plugins/pessoasplus/comunicados/<id>/v<n>/ com SHA-256; o selo da
 * versao e o SHA-256 de (hash do conteudo + hashes dos anexos); o publico e
 * resolvido (D-72) e a lista de destinatarios e congelada. Nada disso muda
 * depois, mesmo que o documento original seja alterado ou excluido.
 *
 * So quem tem Comunicados: Gerenciar emite (RH, D-74 ate 13/10).
 */
final class Comunicado extends CommonDBTM
{
    public static $rightname = 'pessoasplus_comunicados';

    public $dohistory = false;

    public const TIPOS = [
        'comunicado' => ['Comunicado', 'Ciente'],
        'normativa'  => ['Normativa', 'Ciente'],
    ];

    public const SITUACOES = [
        'rascunho'  => ['Rascunho', 'neutro'],
        'publicado' => ['Publicado', 'ok'],
        'revogado'  => ['Revogado', 'atraso'],
    ];

    /** Subpasta em GLPI_PLUGIN_DOC_DIR. */
    public const PASTA = 'pessoasplus/comunicados';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_comunicados';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Comunicados' : 'Comunicado';
    }

    public static function getIcon()
    {
        return 'ti ti-speakerphone';
    }

    // ----------------------------------------------------------------- direitos

    public static function canCreate(): bool
    {
        return Permissoes::gerencia('comunicados');
    }

    public static function canView(): bool
    {
        return Permissoes::le('comunicados');
    }

    public static function canUpdate(): bool
    {
        return Permissoes::gerencia('comunicados');
    }

    public static function canDelete(): bool
    {
        return Permissoes::gerencia('comunicados');
    }

    public static function canPurge(): bool
    {
        return false;
    }

    public function canCreateItem(): bool
    {
        return Permissoes::gerencia('comunicados');
    }

    public function canViewItem(): bool
    {
        return Permissoes::le('comunicados');
    }

    public function canUpdateItem(): bool
    {
        return Permissoes::gerencia('comunicados');
    }

    /** Rascunho pode ser excluido; publicado so se revoga. */
    public function canDeleteItem(): bool
    {
        return Permissoes::gerencia('comunicados') && ($this->fields['situacao'] ?? '') === 'rascunho';
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
        if (array_key_exists('tipo', $input) && !isset(self::TIPOS[$input['tipo']])) {
            $input['tipo'] = 'comunicado';
        }
        if (array_key_exists('prazo_dias', $input)) {
            $input['prazo_dias'] = min(365, max(1, (int) $input['prazo_dias']));
        }
        foreach (['exige_concordancia', 'exigir_admissao'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $input[$flag] = (int) (bool) $input[$flag];
            }
        }
        foreach (['vigencia_inicio', 'vigencia_fim'] as $campo) {
            if (array_key_exists($campo, $input)) {
                $valor = trim((string) $input[$campo]);
                if ($valor === '') {
                    $input[$campo] = 'NULL';
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                    Session::addMessageAfterRedirect('Data de vigência inválida.', false, ERROR);

                    return false;
                }
            }
        }
        if (array_key_exists('referencia', $input)) {
            $input['referencia'] = mb_substr(trim((string) $input['referencia']), 0, 100);
        }
        if (array_key_exists('conteudo', $input)) {
            $input['conteudo'] = (string) $input['conteudo'];
        }
        // BM.2-2: documentos relacionados vem como links_json [{rotulo, url}].
        if (array_key_exists('links_json', $input)) {
            $input['links'] = json_encode(self::normalizarLinks((string) $input['links_json']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            unset($input['links_json']);
        }
        // Situacao e versao nunca vem do formulario: mudam por publicar/revogar.
        unset($input['situacao'], $input['versao_atual']);
        if (!$novo && ($this->fields['situacao'] ?? '') !== 'rascunho') {
            // Publicado: so o rascunho da proxima versao (conteudo, titulo),
            // prazo e os dados de normativa podem mudar; o publico tambem,
            // porque vale para a proxima versao.
        }

        return $input;
    }

    /**
     * Ate 10 links {rotulo, url}; URL http(s) absoluta ou caminho relativo a
     * raiz (/plugins/codexplus/...). Qualquer outra coisa e descartada.
     *
     * @return array<int, array{rotulo: string, url: string, codex: bool}>
     */
    public static function normalizarLinks(string $json): array
    {
        $lista = json_decode($json, true);
        if (!is_array($lista)) {
            return [];
        }
        $saida = [];
        foreach ($lista as $l) {
            if (!is_array($l)) {
                continue;
            }
            $url    = trim((string) ($l['url'] ?? ''));
            $rotulo = mb_substr(trim((string) ($l['rotulo'] ?? '')), 0, 120);
            if (!preg_match('#^(https?://[^\s"\'<>]+|/[^\s"\'<>]*)$#i', $url)) {
                continue;
            }
            if ($rotulo === '') {
                $rotulo = mb_substr(preg_replace('#^https?://#i', '', $url) ?? $url, 0, 120);
            }
            $saida[] = ['rotulo' => $rotulo, 'url' => $url, 'codex' => str_contains($url, '/plugins/codexplus/')];
            if (count($saida) >= 10) {
                break;
            }
        }

        return $saida;
    }

    /**
     * Links gravados, prontos para a tela (URL relativa prefixada com o root_doc).
     *
     * @return array<int, array{rotulo: string, url: string, codex: bool}>
     */
    public static function linksParaTela(?string $json): array
    {
        $saida = [];
        foreach (self::normalizarLinks((string) $json) as $l) {
            if (str_starts_with($l['url'], '/')) {
                $l['url'] = \Html::getPrefixedUrl($l['url']);
            }
            $saida[] = $l;
        }

        return $saida;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->normalizar($input, true);
        if ($input === false) {
            return false;
        }
        $input['situacao']  = 'rascunho';
        $input['users_id']  = (int) (Session::getLoginUserID() ?: 0);
        $input['tipo']      = $input['tipo'] ?? 'comunicado';
        $input['prazo_dias'] = $input['prazo_dias'] ?? 7;
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
        Evento::registrar('comunicados', 'comunicado_criado', $this, 0, ['titulo' => $this->fields['titulo'], 'tipo' => $this->fields['tipo']]);
    }

    public function post_deleteItem()
    {
        Evento::registrar('comunicados', 'comunicado_excluido', $this, 0, ['titulo' => $this->fields['titulo']]);
    }

    // ----------------------------------------------------------------- publico do comunicado

    /**
     * Grava as regras proprias do comunicado (publico is_modelo = 0),
     * copiando de um publico salvo quando informado.
     *
     * @param array<int, array<string, mixed>> $regras
     */
    public function definirPublico(array $regras, int $modeloId = 0): void
    {
        if ($modeloId > 0) {
            $regras = Publico::regras($modeloId);
        }
        $publicoId = (int) $this->fields['plugin_pessoasplus_publicos_id'];
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
        /** @var DBmysql $DB */
        global $DB;
        $DB->update(self::getTable(), [
            'plugin_pessoasplus_publicos_id' => $publicoId,
            'publico_modelo_id'              => $modeloId,
        ], ['id' => (int) $this->getID()]);
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

    // ----------------------------------------------------------------- publicar / versoes

    /**
     * Publica o rascunho como versao 1, ou cria a versao seguinte quando ja
     * esta publicado (motivo obrigatorio nesse caso). Devolve o id da versao
     * ou 0 com mensagem.
     */
    public function publicar(string $motivo = ''): int
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('comunicados')) {
            return 0;
        }
        $situacao = (string) $this->fields['situacao'];
        if ($situacao === 'revogado') {
            Session::addMessageAfterRedirect('Comunicado revogado não recebe nova versão. Crie um novo.', false, ERROR);

            return 0;
        }
        $conteudo = trim((string) ($this->fields['conteudo'] ?? ''));
        if ($conteudo === '' || trim(strip_tags($conteudo)) === '') {
            Session::addMessageAfterRedirect('Escreva o conteúdo antes de publicar.', false, ERROR);

            return 0;
        }
        $regras = $this->regrasDoPublico();
        $usuarios = Publico::resolverRegras($regras);
        if ($usuarios === []) {
            Session::addMessageAfterRedirect('O público está vazio: ninguém receberia. Ajuste as regras.', false, ERROR);

            return 0;
        }
        $novaVersao = (int) $this->fields['versao_atual'] + 1;
        if ($novaVersao > 1 && trim($motivo) === '') {
            Session::addMessageAfterRedirect('Informe o motivo da nova versão.', false, ERROR);

            return 0;
        }

        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $prazo = date('Y-m-d', strtotime($agora . ' +' . (int) $this->fields['prazo_dias'] . ' days'));

        // Encerra a versao anterior (as ciencias dela ficam ligadas a ela).
        if ($novaVersao > 1) {
            $DB->update(Versao::getTable(), [
                'situacao'            => 'substituida',
                'data_encerramento'   => $agora,
                'motivo_encerramento' => 'Substituída pela versão ' . $novaVersao,
            ], ['plugin_pessoasplus_comunicados_id' => (int) $this->getID(), 'situacao' => 'vigente']);
        }

        $hashConteudo = hash('sha256', $conteudo);
        $versao = new Versao();
        $versaoId = (int) $versao->add([
            'plugin_pessoasplus_comunicados_id' => (int) $this->getID(),
            'numero'             => $novaVersao,
            'titulo'             => (string) $this->fields['titulo'],
            'conteudo'           => $conteudo,
            'hash_conteudo'      => $hashConteudo,
            'links'              => (string) ($this->fields['links'] ?? ''),
            'selo'               => '',
            'exige_concordancia' => (int) $this->fields['exige_concordancia'],
            'motivo'             => mb_substr(trim($motivo), 0, 2000),
            'users_id'           => (int) (Session::getLoginUserID() ?: 0),
            'data_publicacao'    => $agora,
            'prazo'              => $prazo,
            'situacao'           => 'vigente',
        ]);
        if ($versaoId <= 0) {
            Session::addMessageAfterRedirect('Não foi possível criar a versão.', false, ERROR);

            return 0;
        }

        // Anexos: copia propria de cada Document ligado ao comunicado (D-09).
        $hashes = [$hashConteudo];
        if (self::normalizarLinks((string) ($this->fields['links'] ?? '')) !== []) {
            $hashes[] = hash('sha256', (string) $this->fields['links']);
        }
        foreach ($this->congelarAnexos($versaoId, $novaVersao) as $h) {
            $hashes[] = $h;
        }
        $selo = hash('sha256', implode("\n", $hashes));
        $DB->update(Versao::getTable(), ['selo' => $selo], ['id' => $versaoId]);

        // Destinatarios congelados, com o setor de cada um no momento.
        $setores = self::setorDe($usuarios);
        foreach ($usuarios as $uid) {
            $DB->insert(Destinatario::getTable(), [
                'plugin_pessoasplus_versoes_id' => $versaoId,
                'users_id'      => $uid,
                'groups_id'     => $setores[$uid] ?? 0,
                'data_inclusao' => $agora,
                'origem'        => 'publicacao',
            ]);
        }

        $DB->update(self::getTable(), [
            'situacao'     => 'publicado',
            'versao_atual' => $novaVersao,
            'date_mod'     => $agora,
        ], ['id' => (int) $this->getID()]);
        $this->fields['situacao']     = 'publicado';
        $this->fields['versao_atual'] = $novaVersao;

        Evento::registrar('comunicados', $novaVersao === 1 ? 'comunicado_publicado' : 'comunicado_nova_versao', $this, 0, [
            'titulo' => $this->fields['titulo'], 'versao' => $novaVersao, 'destinatarios' => count($usuarios), 'selo' => $selo, 'motivo' => $motivo,
        ]);

        return $versaoId;
    }

    /**
     * Copia os anexos nativos para a pasta da versao e devolve os hashes.
     *
     * @return string[]
     */
    private function congelarAnexos(int $versaoId, int $numero): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hashes = [];
        $pasta  = GLPI_PLUGIN_DOC_DIR . '/' . self::PASTA . '/' . (int) $this->getID() . '/v' . $numero;
        $it = $DB->request([
            'SELECT' => ['documents_id'],
            'FROM'   => Document_Item::getTable(),
            'WHERE'  => ['itemtype' => self::class, 'items_id' => (int) $this->getID()],
            'ORDER'  => 'id',
        ]);
        foreach ($it as $di) {
            $doc = new Document();
            if (!$doc->getFromDB((int) $di['documents_id'])) {
                continue;
            }
            $origem = GLPI_DOC_DIR . '/' . (string) $doc->fields['filepath'];
            if ((string) $doc->fields['filepath'] === '' || !is_file($origem)) {
                continue;
            }
            if (!is_dir($pasta) && !mkdir($pasta, 0775, true) && !is_dir($pasta)) {
                Session::addMessageAfterRedirect('Não foi possível criar a pasta dos anexos.', false, WARNING);
                continue;
            }
            $nome    = (string) ($doc->fields['filename'] ?: basename($origem));
            $destino = $pasta . '/' . (int) $doc->getID() . '-' . preg_replace('/[^A-Za-z0-9._-]+/', '_', $nome);
            if (!copy($origem, $destino)) {
                Session::addMessageAfterRedirect('Não foi possível copiar o anexo ' . $nome . '.', false, WARNING);
                continue;
            }
            $hash = (string) hash_file('sha256', $destino);
            $anexo = new Anexo();
            $anexo->add([
                'plugin_pessoasplus_versoes_id' => $versaoId,
                'nome'         => mb_substr($nome, 0, 255),
                'mime'         => (string) ($doc->fields['mime'] ?? ''),
                'tamanho'      => (int) filesize($destino),
                'caminho'      => substr($destino, strlen(GLPI_PLUGIN_DOC_DIR) + 1),
                'hash'         => $hash,
                'documents_id' => (int) $doc->getID(),
            ]);
            $hashes[] = $hash;
        }

        return $hashes;
    }

    /**
     * Setor principal de cada usuario: a ficha, ou o primeiro grupo do GLPI.
     *
     * @param int[] $usuarios
     *
     * @return array<int, int>
     */
    private static function setorDe(array $usuarios): array
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($usuarios === []) {
            return [];
        }
        $saida = [];
        $it = $DB->request(['SELECT' => ['users_id', 'groups_id'], 'FROM' => Colaborador::getTable(), 'WHERE' => ['users_id' => $usuarios, 'is_deleted' => 0]]);
        foreach ($it as $l) {
            if ((int) $l['groups_id'] > 0) {
                $saida[(int) $l['users_id']] = (int) $l['groups_id'];
            }
        }
        $it = $DB->request(['SELECT' => ['users_id', 'groups_id'], 'FROM' => 'glpi_groups_users', 'WHERE' => ['users_id' => $usuarios], 'ORDER' => 'id']);
        foreach ($it as $l) {
            $saida[(int) $l['users_id']] ??= (int) $l['groups_id'];
        }

        return $saida;
    }

    /** Revoga: encerra a versao vigente; pendencias somem, evidencia fica. */
    public function revogar(string $motivo): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('comunicados') || (string) $this->fields['situacao'] !== 'publicado') {
            return false;
        }
        $motivo = mb_substr(trim($motivo), 0, 2000);
        if ($motivo === '') {
            Session::addMessageAfterRedirect('Informe o motivo da revogação.', false, ERROR);

            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->update(Versao::getTable(), [
            'situacao' => 'revogada', 'data_encerramento' => $agora, 'motivo_encerramento' => $motivo,
        ], ['plugin_pessoasplus_comunicados_id' => (int) $this->getID(), 'situacao' => 'vigente']);
        $DB->update(self::getTable(), ['situacao' => 'revogado', 'date_mod' => $agora], ['id' => (int) $this->getID()]);
        $this->fields['situacao'] = 'revogado';
        Evento::registrar('comunicados', 'comunicado_revogado', $this, 0, ['titulo' => $this->fields['titulo'], 'motivo' => $motivo]);

        return true;
    }

    // ----------------------------------------------------------------- ciencia

    /**
     * Registra a ciencia do usuario logado na versao. Somente inclusao:
     * segunda tentativa e ignorada. Devolve o id ou 0.
     */
    public static function registrarCiencia(int $versaoId, bool $concorda, string $justificativa): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $uid = (int) Session::getLoginUserID();
        if ($uid <= 0 || !self::eDestinatario($versaoId, $uid)) {
            return 0;
        }
        $versao = new Versao();
        if (!$versao->getFromDB($versaoId) || (string) $versao->fields['situacao'] !== 'vigente') {
            Session::addMessageAfterRedirect('Esta versão não está mais vigente.', false, WARNING);

            return 0;
        }
        if (self::cienciaDe($versaoId, $uid) !== null) {
            return 0;
        }
        $justificativa = mb_substr(trim($justificativa), 0, 2000);
        if (!$concorda && $justificativa === '') {
            Session::addMessageAfterRedirect('Para registrar "Não concordo", escreva a justificativa.', false, ERROR);

            return 0;
        }
        $agora     = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $interface = Session::getCurrentInterface() === 'helpdesk' ? 'helpdesk' : 'central';
        $selo = hash('sha256', implode('|', [$uid, $versaoId, $versao->fields['selo'], $agora, $concorda ? 1 : 0, $justificativa, $interface]));
        $DB->insert(Ciencia::getTable(), [
            'plugin_pessoasplus_versoes_id' => $versaoId,
            'users_id'      => $uid,
            'concorda'      => $concorda ? 1 : 0,
            'justificativa' => $concorda ? '' : $justificativa,
            'interface'     => $interface,
            'data'          => $agora,
            'selo'          => $selo,
        ]);
        $id = (int) $DB->insertId();
        $ficha = Colaborador::porUsuario($uid);
        $com = new self();
        $com->getFromDB((int) $versao->fields['plugin_pessoasplus_comunicados_id']);
        Evento::registrar('comunicados', $concorda ? 'ciencia_confirmada' : 'ciencia_nao_concorda', $com, $ficha === null ? 0 : (int) $ficha->getID(), [
            'versao' => (int) $versao->fields['numero'], 'titulo' => $versao->fields['titulo'], 'selo' => $selo,
        ]);

        return $id;
    }

    public static function eDestinatario(int $versaoId, int $uid): bool
    {
        $d = new Destinatario();

        return $d->getFromDBByCrit(['plugin_pessoasplus_versoes_id' => $versaoId, 'users_id' => $uid]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function cienciaDe(int $versaoId, int $uid): ?array
    {
        $c = new Ciencia();

        return $c->getFromDBByCrit(['plugin_pessoasplus_versoes_id' => $versaoId, 'users_id' => $uid]) ? $c->fields : null;
    }

    /** Recalcula o selo da ciencia e compara com o gravado (verificacao de integridade). */
    public static function cienciaIntegra(array $c, string $seloVersao): bool
    {
        $esperado = hash('sha256', implode('|', [(int) $c['users_id'], (int) $c['plugin_pessoasplus_versoes_id'], $seloVersao, $c['data'], (int) $c['concorda'], (string) $c['justificativa'], $c['interface']]));

        return hash_equals($esperado, (string) $c['selo']);
    }

    public static function registrarLeitura(int $versaoId): void
    {
        $uid = (int) Session::getLoginUserID();
        $chave = 'plugin_pessoasplus_leitura_' . $versaoId;
        if ($uid <= 0 || !empty($_SESSION[$chave])) {
            return;
        }
        $_SESSION[$chave] = 1;
        $versao = new Versao();
        if (!$versao->getFromDB($versaoId)) {
            return;
        }
        $com = new self();
        $com->getFromDB((int) $versao->fields['plugin_pessoasplus_comunicados_id']);
        $ficha = Colaborador::porUsuario($uid);
        Evento::registrar('comunicados', 'leitura_registrada', $com, $ficha === null ? 0 : (int) $ficha->getID(), ['versao' => (int) $versao->fields['numero']]);
    }

    // ----------------------------------------------------------------- consultas

    /** Versao vigente do comunicado, ou null. */
    public function versaoVigente(): ?Versao
    {
        $v = new Versao();
        if ($v->getFromDBByCrit(['plugin_pessoasplus_comunicados_id' => (int) $this->getID(), 'situacao' => 'vigente'])) {
            return $v;
        }

        return null;
    }

    /**
     * Pendencias de ciencia do usuario: versoes vigentes de que e destinatario
     * sem ciencia registrada. Chaves fixas (T-06).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pendenciasDe(int $uid, ?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje ??= date('Y-m-d');
        $saida = [];
        $it = $DB->request([
            'SELECT'     => ['glpi_plugin_pessoasplus_versoes.*', 'glpi_plugin_pessoasplus_comunicados.tipo', 'glpi_plugin_pessoasplus_comunicados.referencia'],
            'FROM'       => Versao::getTable(),
            'INNER JOIN' => [
                Destinatario::getTable() => ['ON' => [Destinatario::getTable() => 'plugin_pessoasplus_versoes_id', Versao::getTable() => 'id']],
                self::getTable() => ['ON' => [self::getTable() => 'id', Versao::getTable() => 'plugin_pessoasplus_comunicados_id']],
            ],
            'LEFT JOIN'  => [
                Ciencia::getTable() => ['ON' => [Ciencia::getTable() => 'plugin_pessoasplus_versoes_id', Versao::getTable() => 'id', ['AND' => ['glpi_plugin_pessoasplus_ciencias.users_id' => $uid]]]],
            ],
            'WHERE'      => [
                'glpi_plugin_pessoasplus_destinatarios.users_id' => $uid,
                'glpi_plugin_pessoasplus_versoes.situacao'       => 'vigente',
                'glpi_plugin_pessoasplus_ciencias.id'            => null,
            ],
            'ORDER'      => 'glpi_plugin_pessoasplus_versoes.prazo',
        ]);
        foreach ($it as $v) {
            $saida[] = self::pendenciaParaTela($v, $hoje);
        }

        return $saida;
    }

    /**
     * @param array<string, mixed> $v linha de versao com tipo
     *
     * @return array<string, mixed>
     */
    private static function pendenciaParaTela(array $v, string $hoje): array
    {
        $prazo   = (string) ($v['prazo'] ?? '');
        $atraso  = $prazo !== '' && $prazo < $hoje;
        $noDia   = $prazo === $hoje;
        $tipo    = self::TIPOS[$v['tipo']][0] ?? $v['tipo'];

        return [
            'id'        => (int) $v['id'],
            'versao_id' => (int) $v['id'],
            'comunicados_id' => (int) $v['plugin_pessoasplus_comunicados_id'],
            'titulo'    => (string) $v['titulo'],
            'tipo'      => $tipo,
            'versao'    => (int) $v['numero'],
            'detalhe'   => $tipo . ' · versão ' . (int) $v['numero'] . ($prazo !== '' ? ' · prazo ' . date('d/m/Y', strtotime($prazo)) : ''),
            'prazo'     => $prazo,
            'selo'      => $atraso ? 'Atrasado' : ($noDia ? 'Vence hoje' : 'Pendente'),
            'tom'       => $atraso ? 'atraso' : ($noDia ? 'alerta' : 'info'),
            'url'       => \Html::getPrefixedUrl('/plugins/pessoasplus/front/leitura.php') . '?id=' . (int) $v['id'],
        ];
    }

    /**
     * Normativas vigentes que alcancam o usuario (publicadas, tipo normativa),
     * com a situacao da ciencia dele.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function normativasDe(int $uid): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $it = $DB->request([
            'SELECT'     => ['glpi_plugin_pessoasplus_versoes.*', 'glpi_plugin_pessoasplus_comunicados.referencia', 'glpi_plugin_pessoasplus_comunicados.vigencia_inicio', 'glpi_plugin_pessoasplus_comunicados.vigencia_fim', 'glpi_plugin_pessoasplus_ciencias.data AS ciencia_em', 'glpi_plugin_pessoasplus_ciencias.concorda AS ciencia_concorda'],
            'FROM'       => Versao::getTable(),
            'INNER JOIN' => [
                Destinatario::getTable() => ['ON' => [Destinatario::getTable() => 'plugin_pessoasplus_versoes_id', Versao::getTable() => 'id']],
                self::getTable() => ['ON' => [self::getTable() => 'id', Versao::getTable() => 'plugin_pessoasplus_comunicados_id']],
            ],
            'LEFT JOIN'  => [
                Ciencia::getTable() => ['ON' => [Ciencia::getTable() => 'plugin_pessoasplus_versoes_id', Versao::getTable() => 'id', ['AND' => ['glpi_plugin_pessoasplus_ciencias.users_id' => $uid]]]],
            ],
            'WHERE'      => [
                'glpi_plugin_pessoasplus_destinatarios.users_id' => $uid,
                'glpi_plugin_pessoasplus_versoes.situacao'       => 'vigente',
                'glpi_plugin_pessoasplus_comunicados.tipo'       => 'normativa',
            ],
            'ORDER'      => 'glpi_plugin_pessoasplus_versoes.titulo',
        ]);
        foreach ($it as $v) {
            $saida[] = [
                'versao_id'  => (int) $v['id'],
                'titulo'     => (string) $v['titulo'],
                'versao'     => (int) $v['numero'],
                'referencia' => (string) $v['referencia'],
                'vigencia'   => ($v['vigencia_inicio'] ? date('d/m/Y', strtotime($v['vigencia_inicio'])) : '—') . ($v['vigencia_fim'] ? ' a ' . date('d/m/Y', strtotime($v['vigencia_fim'])) : ''),
                'ciente'     => $v['ciencia_em'] !== null,
                'ciencia_em' => $v['ciencia_em'] ? date('d/m/Y H:i', strtotime($v['ciencia_em'])) : '',
                'concorda'   => (int) ($v['ciencia_concorda'] ?? 1) === 1,
                'url'        => \Html::getPrefixedUrl('/plugins/pessoasplus/front/leitura.php') . '?id=' . (int) $v['id'],
            ];
        }

        return $saida;
    }

    /**
     * Ciencias registradas pelo usuario (comprovantes individuais).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cienciasDe(int $uid): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $it = $DB->request([
            'SELECT'     => ['glpi_plugin_pessoasplus_ciencias.*', 'glpi_plugin_pessoasplus_versoes.titulo', 'glpi_plugin_pessoasplus_versoes.numero', 'glpi_plugin_pessoasplus_versoes.situacao AS versao_situacao'],
            'FROM'       => Ciencia::getTable(),
            'INNER JOIN' => [Versao::getTable() => ['ON' => [Versao::getTable() => 'id', Ciencia::getTable() => 'plugin_pessoasplus_versoes_id']]],
            'WHERE'      => ['glpi_plugin_pessoasplus_ciencias.users_id' => $uid],
            'ORDER'      => 'glpi_plugin_pessoasplus_ciencias.data DESC',
        ]);
        foreach ($it as $c) {
            $saida[] = [
                'versao_id' => (int) $c['plugin_pessoasplus_versoes_id'],
                'titulo'    => (string) $c['titulo'],
                'versao'    => (int) $c['numero'],
                'resposta'  => (int) $c['concorda'] === 1 ? 'Ciente' : 'Não concordo',
                'data'      => date('d/m/Y H:i', strtotime((string) $c['data'])),
                'vigente'   => (string) $c['versao_situacao'] === 'vigente',
                'url'       => \Html::getPrefixedUrl('/plugins/pessoasplus/front/comprovante.php') . '?id=' . (int) $c['plugin_pessoasplus_versoes_id'] . '&u=' . $uid,
            ];
        }

        return $saida;
    }

    /**
     * Lista da area do RH, com o progresso da versao vigente.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function lista(?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje ??= date('Y-m-d');
        $saida = [];
        $it = $DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_deleted' => 0], 'ORDER' => ['date_mod DESC']]);
        foreach ($it as $c) {
            $com = new self();
            $com->fields = $c;
            $vig = $com->versaoVigente();
            $total = $confirmados = 0;
            $prazo = '';
            if ($vig !== null) {
                $total       = countElementsInTable(Destinatario::getTable(), ['plugin_pessoasplus_versoes_id' => (int) $vig->getID()]);
                $confirmados = countElementsInTable(Ciencia::getTable(), ['plugin_pessoasplus_versoes_id' => (int) $vig->getID()]);
                $prazo       = (string) $vig->fields['prazo'];
            }
            $pct   = $total > 0 ? (int) round($confirmados * 100 / $total) : 0;
            $sit   = self::SITUACOES[$c['situacao']] ?? [$c['situacao'], 'neutro'];
            $fase  = $c['situacao'] === 'rascunho' ? 'rascunho' : ($c['situacao'] === 'revogado' ? 'concluido' : ($total > 0 && $confirmados >= $total ? 'concluido' : 'andamento'));
            $tom   = $c['situacao'] === 'revogado' ? 'neutro' : ($fase === 'concluido' ? 'ok' : (($prazo !== '' && $prazo < $hoje) ? 'atraso' : 'info'));
            $publico = (int) $c['plugin_pessoasplus_publicos_id'] > 0 ? count(Publico::rotular(Publico::regras((int) $c['plugin_pessoasplus_publicos_id']))) : 0;
            $saida[] = [
                'id'          => (int) $c['id'],
                'titulo'      => (string) $c['titulo'],
                'tipo'        => self::TIPOS[$c['tipo']][0] ?? $c['tipo'],
                'origem'      => (int) $c['versao_atual'] > 0 ? 'versão ' . (int) $c['versao_atual'] : 'ainda não publicado',
                'publico'     => $publico > 0 ? $publico . ($publico === 1 ? ' regra' : ' regras') : '—',
                'publicado'   => $vig !== null ? date('d/m/Y', strtotime((string) $vig->fields['data_publicacao'])) : '—',
                'prazo'       => $prazo !== '' ? date('d/m/Y', strtotime($prazo)) : '—',
                'total'       => $total,
                'confirmados' => $confirmados,
                'percentual'  => $pct,
                'tom'         => $tom,
                'situacao'    => $sit[0],
                'fase'        => $fase,
                'url'         => \Html::getPrefixedUrl('/plugins/pessoasplus/front/comunicado.php') . '?id=' . (int) $c['id'],
                'url_comprovante' => $vig !== null ? \Html::getPrefixedUrl('/plugins/pessoasplus/front/comprovante.php') . '?id=' . (int) $vig->getID() : '',
            ];
        }

        return $saida;
    }

    /**
     * Painel do emissor para uma versao.
     *
     * @return array<string, mixed>
     */
    public static function painel(Versao $versao, ?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje ??= date('Y-m-d');
        $vid  = (int) $versao->getID();
        $prazo = (string) $versao->fields['prazo'];
        $atrasoGeral = $prazo !== '' && $prazo < $hoje;

        $ciencias = [];
        foreach ($DB->request(['FROM' => Ciencia::getTable(), 'WHERE' => ['plugin_pessoasplus_versoes_id' => $vid]]) as $c) {
            $ciencias[(int) $c['users_id']] = $c;
        }
        $pessoas = [];
        $grupos  = [];
        $it = $DB->request([
            'SELECT'    => ['glpi_plugin_pessoasplus_destinatarios.*', 'glpi_users.name AS login', 'glpi_users.realname', 'glpi_users.firstname', 'glpi_groups.completename AS grupo'],
            'FROM'      => Destinatario::getTable(),
            'LEFT JOIN' => [
                'glpi_users'  => ['ON' => ['glpi_users' => 'id', Destinatario::getTable() => 'users_id']],
                'glpi_groups' => ['ON' => ['glpi_groups' => 'id', Destinatario::getTable() => 'groups_id']],
            ],
            'WHERE'     => ['glpi_plugin_pessoasplus_destinatarios.plugin_pessoasplus_versoes_id' => $vid],
            'ORDER'     => ['glpi_users.realname', 'glpi_users.firstname'],
        ]);
        foreach ($it as $d) {
            $uid  = (int) $d['users_id'];
            $nome = (string) formatUserName($uid, (string) $d['login'], (string) $d['realname'], (string) $d['firstname']);
            $grupo = (string) ($d['grupo'] ?? '') ?: 'Sem setor';
            $c = $ciencias[$uid] ?? null;
            if ($c !== null) {
                $situacao = (int) $c['concorda'] === 1 ? 'confirmado' : 'discorda';
                $rotulo   = (int) $c['concorda'] === 1 ? 'Ciente' : 'Não concorda';
                $tom      = (int) $c['concorda'] === 1 ? 'ok' : 'alerta';
                $leitura  = 'confirmou em ' . date('d/m/Y H:i', strtotime((string) $c['data']));
            } else {
                $situacao = $atrasoGeral ? 'atrasado' : 'pendente';
                $rotulo   = $atrasoGeral ? 'Em atraso' : 'Pendente';
                $tom      = $atrasoGeral ? 'atraso' : 'info';
                $leitura  = 'sem confirmação';
            }
            $pessoas[] = [
                'users_id' => $uid, 'nome' => $nome, 'iniciais' => Colaborador::iniciais($nome), 'grupo' => $grupo,
                'situacao' => $situacao, 'rotulo' => $rotulo, 'tom' => $tom, 'leitura' => $leitura,
                'prazo' => $prazo !== '' ? 'prazo ' . date('d/m/Y', strtotime($prazo)) : '', 'avisos' => 0,
                'justificativa' => $c !== null ? (string) $c['justificativa'] : '',
                'url_comprovante' => $c !== null ? \Html::getPrefixedUrl('/plugins/pessoasplus/front/comprovante.php') . '?id=' . $vid . '&u=' . $uid : '',
                'integro' => $c !== null ? self::cienciaIntegra($c, (string) $versao->fields['selo']) : true,
            ];
            $grupos[$grupo] ??= ['grupo' => $grupo, 'total' => 0, 'confirmados' => 0];
            $grupos[$grupo]['total']++;
            if ($c !== null) {
                $grupos[$grupo]['confirmados']++;
            }
        }
        $total = count($pessoas);
        $conf  = count(array_filter($pessoas, static fn ($p) => $p['situacao'] === 'confirmado' || $p['situacao'] === 'discorda'));
        $disc  = count(array_filter($pessoas, static fn ($p) => $p['situacao'] === 'discorda'));
        $falt  = $total - $conf;
        $atr   = count(array_filter($pessoas, static fn ($p) => $p['situacao'] === 'atrasado'));
        $porGrupo = [];
        foreach ($grupos as $g) {
            $g['percentual'] = $g['total'] > 0 ? (int) round($g['confirmados'] * 100 / $g['total']) : 0;
            $g['tom'] = $g['percentual'] >= 100 ? 'ok' : ($atrasoGeral ? 'atraso' : 'info');
            $porGrupo[] = $g;
        }

        return [
            'versao_id'   => $vid,
            'numero'      => (int) $versao->fields['numero'],
            'titulo'      => (string) $versao->fields['titulo'],
            'codigo'      => 'v' . (int) $versao->fields['numero'],
            'selo'        => (string) $versao->fields['selo'],
            'publicado'   => date('d/m/Y H:i', strtotime((string) $versao->fields['data_publicacao'])),
            'prazo'       => $prazo !== '' ? date('d/m/Y', strtotime($prazo)) : '—',
            'situacao'    => ['vigente' => 'Vigente', 'substituida' => 'Substituída', 'revogada' => 'Revogada'][$versao->fields['situacao']] ?? $versao->fields['situacao'],
            'vigente'     => (string) $versao->fields['situacao'] === 'vigente',
            'motivo'      => (string) ($versao->fields['motivo'] ?? ''),
            'motivo_encerramento' => (string) ($versao->fields['motivo_encerramento'] ?? ''),
            'adesao'      => $total > 0 ? (int) round($conf * 100 / $total) : 0,
            'numeros'     => [
                ['rotulo' => 'Destinatários', 'valor' => $total, 'tom' => 'neutro'],
                ['rotulo' => 'Confirmaram', 'valor' => $conf, 'tom' => 'ok'],
                ['rotulo' => 'Faltam', 'valor' => $falt, 'tom' => $atr > 0 ? 'atraso' : 'info'],
                ['rotulo' => 'Não concordam', 'valor' => $disc, 'tom' => $disc > 0 ? 'alerta' : 'neutro'],
            ],
            'contagem'    => ['faltam' => $falt, 'atrasado' => $atr, 'pendente' => $falt - $atr, 'confirmado' => $conf, 'discorda' => $disc],
            'por_grupo'   => $porGrupo,
            'pessoas'     => $pessoas,
            'anexos'      => self::anexosDaVersao($vid),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function anexosDaVersao(int $versaoId): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        foreach ($DB->request(['FROM' => Anexo::getTable(), 'WHERE' => ['plugin_pessoasplus_versoes_id' => $versaoId], 'ORDER' => 'id']) as $a) {
            $saida[] = [
                'id'      => (int) $a['id'],
                'nome'    => (string) $a['nome'],
                'tamanho' => self::tamanhoLegivel((int) $a['tamanho']),
                'hash'    => substr((string) $a['hash'], 0, 16),
                'url'     => \Html::getPrefixedUrl('/plugins/pessoasplus/front/anexo.php') . '?id=' . (int) $a['id'],
            ];
        }

        return $saida;
    }

    public static function tamanhoLegivel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return (int) round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Versoes do comunicado, da mais recente para a mais antiga.
     *
     * @return array<int, array<string, mixed>>
     */
    public function versoes(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        foreach ($DB->request(['FROM' => Versao::getTable(), 'WHERE' => ['plugin_pessoasplus_comunicados_id' => (int) $this->getID()], 'ORDER' => 'numero DESC']) as $v) {
            $saida[] = [
                'id'        => (int) $v['id'],
                'numero'    => (int) $v['numero'],
                'situacao'  => ['vigente' => 'Vigente', 'substituida' => 'Substituída', 'revogada' => 'Revogada'][$v['situacao']] ?? $v['situacao'],
                'tom'       => ['vigente' => 'ok', 'substituida' => 'neutro', 'revogada' => 'atraso'][$v['situacao']] ?? 'neutro',
                'publicado' => date('d/m/Y H:i', strtotime((string) $v['data_publicacao'])),
                'motivo'    => (string) ($v['motivo'] ?? ''),
                'url'       => \Html::getPrefixedUrl('/plugins/pessoasplus/front/comunicado.php') . '?id=' . (int) $this->getID() . '&versao=' . (int) $v['id'],
            ];
        }

        return $saida;
    }
}
