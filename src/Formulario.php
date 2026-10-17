<?php

/**
 * Pessoas+ - motor de formularios (E7, D-58) e Ouvidoria (D-56, D-31, D-64), BM.4.
 *
 * PESSOASPLUS_BUILD_BM4
 * PESSOASPLUS_BUILD_BM4_9 (D-92: ranking completo das alternativas; previa do formulario)
 * PESSOASPLUS_BUILD_BM4_11 (D-92, desenho B: visao geral da campanha por dia e por setor)
 * PESSOASPLUS_BUILD_BM4_10 (D-91: urlPublica pelo endereco curto)
 * PESSOASPLUS_BUILD_BM4_12 (D-93: complemento pelo remetente via protocolo; D-94: transicoes de situacao)
 * PESSOASPLUS_BUILD_BM4_13 (D-95: respostas do RH como lista datada; conversa nos dois lados)
 * PESSOASPLUS_BUILD_BM4_17 (D-97 bilhete e "Ja respondi pelo QR"; D-98 anonimo liga a pagina publica; divulgar_qr opcional)
 * PESSOASPLUS_BUILD_BM4_15 (correcao: Ouvidoria nao grava participacao; segundo envio da mesma pessoa estourava a chave unica)
 * PESSOASPLUS_BUILD_BM4_5
 * PESSOASPLUS_BUILD_BM5
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;
use Group;
use Session;

/**
 * Um formulario tem perguntas (6 tipos), um publico, um periodo e um modo:
 *  - identificado: a resposta leva users_id;
 *  - anonimo: a resposta NAO leva users_id nem hora (so o dia) e nao ha
 *    ligacao com a participacao; a participacao registra apenas "ja
 *    respondeu", para nao responder duas vezes e para cobrar quem falta;
 *  - escolha: quem responde decide na hora.
 * tipo 'ouvidoria': uma porta por formulario (duvida, sugestao, reclamacao,
 * conduta), sempre aberta, publico = todos; gera protocolo + codigo de
 * acompanhamento (D-64), consultavel por POST (T-55) sem revelar o remetente.
 * publico_sem_login: pagina publica por token (T-45), com desafio e limite
 * por dia (sem IP em claro, D-05). Sem anexo (D-73).
 */
final class Formulario extends CommonDBTM
{
    public static $rightname = 'pessoasplus_pesquisas';

    public $dohistory = false;

    public const TIPOS_PERGUNTA = [
        'curto'    => 'Texto curto',
        'longo'    => 'Texto longo',
        'unica'    => 'Escolha única',
        'multipla' => 'Múltipla escolha',
        'escala'   => 'Escala (1 a N)',
        'simnao'   => 'Sim ou não',
    ];

    public const MODOS = [
        'identificado' => 'Identificado',
        'anonimo'      => 'Anônimo',
        'escolha'      => 'Quem responde escolhe',
    ];

    /** chave => [titulo, texto, modo padrao, tom, icone, quem le a fila] */
    public const PORTAS = [
        'duvida'     => ['Tirar uma dúvida', 'Férias, benefícios, documentos, normas.', 'identificado', 'info', 'ti ti-help-circle', 'pesquisas'],
        'sugestao'   => ['Dar uma sugestão', 'Melhorias no trabalho, no ambiente, nos processos.', 'escolha', 'info', 'ti ti-bulb', 'pesquisas'],
        'reclamacao' => ['Fazer uma reclamação', 'Algo que não está funcionando e precisa de providência.', 'escolha', 'alerta', 'ti ti-message-report', 'pesquisas'],
        'conduta'    => ['Relatar uma conduta', 'Assédio, discriminação, descumprimento de norma. Canal protegido, com protocolo e sem registro de quem enviou.', 'escolha', 'atraso', 'ti ti-shield-half', 'config'],
    ];

    public const SITUACOES_RESPOSTA = [
        'recebido'    => ['Recebido', 'neutro'],
        'em_analise'  => ['Em análise', 'info'],
        'complemento' => ['Aguardando complemento', 'alerta'],
        'respondido'  => ['Respondido', 'ok'],
        'encerrado'   => ['Encerrado', 'neutro'],
    ];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_formularios';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Formulários' : 'Formulário';
    }

    // ----------------------------------------------------------------- direitos

    public static function canCreate(): bool
    {
        return Permissoes::gerencia('pesquisas');
    }

    public static function canView(): bool
    {
        return Permissoes::le('pesquisas');
    }

    public static function canUpdate(): bool
    {
        return Permissoes::gerencia('pesquisas');
    }

    public static function canDelete(): bool
    {
        return Permissoes::gerencia('pesquisas');
    }

    public static function canPurge(): bool
    {
        return false;
    }

    public function canCreateItem(): bool
    {
        return Permissoes::gerencia('pesquisas');
    }

    public function canViewItem(): bool
    {
        return $this->podeLerFila();
    }

    public function canUpdateItem(): bool
    {
        return $this->ehOuvidoria() ? $this->podeLerFila() : Permissoes::gerencia('pesquisas');
    }

    public function canDeleteItem(): bool
    {
        return Permissoes::gerencia('pesquisas') && !$this->ehOuvidoria() && ($this->fields['situacao'] ?? '') === 'rascunho';
    }

    public function canPurgeItem(): bool
    {
        return false;
    }

    public function ehOuvidoria(): bool
    {
        return (string) ($this->fields['tipo'] ?? '') === 'ouvidoria';
    }

    /** Quem le a fila: relato de conduta so quem configura o plugin (D-79). */
    public function podeLerFila(): bool
    {
        if ($this->ehOuvidoria() && (self::PORTAS[$this->fields['porta']][5] ?? 'pesquisas') === 'config') {
            return Permissoes::configura();
        }

        return Permissoes::le('pesquisas');
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
        if (array_key_exists('descricao', $input)) {
            $input['descricao'] = mb_substr(trim((string) $input['descricao']), 0, 2000);
        }
        if (array_key_exists('modo', $input) && !isset(self::MODOS[$input['modo']])) {
            $input['modo'] = 'identificado';
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
        if (array_key_exists('publico_sem_login', $input)) {
            $input['publico_sem_login'] = (int) (bool) $input['publico_sem_login'];
        }
        if (array_key_exists('divulgar_qr', $input)) {
            $input['divulgar_qr'] = (int) (bool) $input['divulgar_qr'];
        }
        // BM.4-17 (D-98): pesquisa anonima responde SEMPRE pela pagina publica (o botao Responder
        // sai do GLPI com bilhete, D-97); por isso a pagina publica nasce ligada. QR e opcional.
        $modoFinal = (string) ($input['modo'] ?? ($this->fields['modo'] ?? ''));
        if ($modoFinal === 'anonimo' && ($novo || array_key_exists('publico_sem_login', $input) || array_key_exists('modo', $input))) {
            $input['publico_sem_login'] = 1;
        }
        if (array_key_exists('limite_dia', $input)) {
            $input['limite_dia'] = min(500, max(1, (int) $input['limite_dia']));
        }
        // Ouvidoria: tipo, porta e situacao nao mudam pela tela.
        unset($input['situacao'], $input['token'], $input['tipo'], $input['porta']);

        return $input;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->normalizar($input, true);
        if ($input === false) {
            return false;
        }
        $input['tipo']     = 'pesquisa';
        $input['situacao'] = 'rascunho';
        $input['token']    = bin2hex(random_bytes(16));
        $input['users_id'] = (int) (Session::getLoginUserID() ?: 0);
        $input['modo']     = $input['modo'] ?? 'identificado';
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
        Evento::registrar('pesquisas', 'formulario_criado', $this, 0, ['titulo' => $this->fields['titulo']]);
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
            $publicoId = (int) $p->add(['nome' => 'Público de: ' . mb_substr((string) $this->fields['titulo'], 0, 200), 'itemtype' => self::class, 'items_id' => (int) $this->getID(), 'is_modelo' => 0]);
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

    public function alcanca(int $uid): bool
    {
        $pid = (int) ($this->fields['plugin_pessoasplus_publicos_id'] ?? 0);

        return $pid > 0 && in_array($uid, Publico::resolver($pid), true);
    }

    // ----------------------------------------------------------------- perguntas

    /**
     * Substitui as perguntas pelas da tela (JSON), normalizando.
     *
     * @param array<int, array<string, mixed>> $perguntas
     */
    public function salvarPerguntas(array $perguntas): int
    {
        /** @var DBmysql $DB */
        global $DB;

        // BM.4-5 (T-85): preserva o id das perguntas existentes; com resposta
        // gravada, nenhuma pergunta e apagada (as respostas apontam para elas).
        $formId = (int) $this->getID();
        $temRespostas = countElementsInTable(Resposta::getTable(), ['plugin_pessoasplus_formularios_id' => $formId]) > 0;
        $existentes = [];
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => Pergunta::getTable(), 'WHERE' => ['plugin_pessoasplus_formularios_id' => $formId]]) as $e) {
            $existentes[(int) $e['id']] = true;
        }
        $mantidas = [];
        $n = 0;
        foreach ($perguntas as $p) {
            if (!is_array($p)) {
                continue;
            }
            $tipo = (string) ($p['tipo'] ?? '');
            $enunciado = mb_substr(trim((string) ($p['enunciado'] ?? '')), 0, 500);
            if (!isset(self::TIPOS_PERGUNTA[$tipo]) || $enunciado === '') {
                continue;
            }
            $opcoes = [];
            if ($tipo === 'unica' || $tipo === 'multipla') {
                $lista = is_array($p['opcoes'] ?? null) ? $p['opcoes'] : preg_split('/\r?\n/', (string) ($p['opcoes'] ?? ''));
                foreach ($lista as $o) {
                    $o = mb_substr(trim((string) $o), 0, 200);
                    if ($o !== '') {
                        $opcoes[] = $o;
                    }
                }
                if (count($opcoes) < 2) {
                    continue;
                }
            }
            $linha = [
                'plugin_pessoasplus_formularios_id' => $formId,
                'ordem'       => $n,
                'tipo'        => $tipo,
                'enunciado'   => $enunciado,
                'opcoes'      => json_encode($opcoes, JSON_UNESCAPED_UNICODE),
                'obrigatoria' => !empty($p['obrigatoria']) ? 1 : 0,
                'escala_max'  => in_array((int) ($p['escala_max'] ?? 5), [5, 10], true) ? (int) ($p['escala_max'] ?? 5) : 5,
            ];
            $id = (int) ($p['id'] ?? 0);
            if ($id > 0 && isset($existentes[$id])) {
                $DB->update(Pergunta::getTable(), $linha, ['id' => $id]);
                $mantidas[$id] = true;
            } else {
                $DB->insert(Pergunta::getTable(), $linha);
            }
            $n++;
        }
        foreach (array_keys($existentes) as $id) {
            if (!isset($mantidas[$id])) {
                if ($temRespostas) {
                    // Nao apaga: tira da ordem (ordem 9999) e marca o enunciado.
                    $DB->update(Pergunta::getTable(), ['ordem' => 9999], ['id' => $id]);
                } else {
                    $DB->delete(Pergunta::getTable(), ['id' => $id]);
                }
            }
        }

        return $n;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function perguntas(bool $incluirRemovidas = false): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $where = ['plugin_pessoasplus_formularios_id' => (int) $this->getID()];
        if (!$incluirRemovidas) {
            $where['ordem'] = ['<', 9999];
        }
        $saida = [];
        foreach ($DB->request(['FROM' => Pergunta::getTable(), 'WHERE' => $where, 'ORDER' => 'ordem']) as $p) {
            $opcoes = json_decode((string) $p['opcoes'], true);
            $saida[] = [
                'id'          => (int) $p['id'],
                'tipo'        => (string) $p['tipo'],
                'tipo_rotulo' => self::TIPOS_PERGUNTA[$p['tipo']] ?? $p['tipo'],
                'enunciado'   => ((int) $p['ordem'] === 9999 ? '[pergunta removida] ' : '') . (string) $p['enunciado'],
                'removida'    => (int) $p['ordem'] === 9999,
                'opcoes'      => is_array($opcoes) ? $opcoes : [],
                'obrigatoria' => (int) $p['obrigatoria'] === 1,
                'escala_max'  => (int) $p['escala_max'],
                'escala'      => range(1, max(2, (int) $p['escala_max'])),
            ];
        }

        return $saida;
    }

    // ----------------------------------------------------------------- estado

    public static function estado(array $f, string $hoje): string
    {
        if (($f['tipo'] ?? '') === 'ouvidoria') {
            return 'aberto';
        }
        if (($f['situacao'] ?? 'rascunho') !== 'aberto') {
            return (string) $f['situacao'];
        }
        if (!empty($f['inicio']) && $f['inicio'] > $hoje) {
            return 'agendado';
        }
        if (!empty($f['fim']) && $f['fim'] < $hoje) {
            return 'encerrado';
        }

        return 'aberto';
    }

    public const ESTADOS = [
        'aberto'    => ['Aberto', 'ok'],
        'agendado'  => ['Agendado', 'info'],
        'rascunho'  => ['Rascunho', 'neutro'],
        'encerrado' => ['Encerrado', 'neutro'],
    ];

    public function abrir(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('pesquisas') || $this->ehOuvidoria()) {
            return false;
        }
        if ($this->perguntas() === []) {
            Session::addMessageAfterRedirect('Inclua ao menos uma pergunta antes de abrir.', false, ERROR);

            return false;
        }
        if ((int) $this->fields['publico_sem_login'] !== 1 && Publico::resolverRegras($this->regrasDoPublico()) === []) {
            Session::addMessageAfterRedirect('O público está vazio e a página pública está desligada: ninguém responderia.', false, ERROR);

            return false;
        }
        $DB->update(self::getTable(), ['situacao' => 'aberto'], ['id' => (int) $this->getID()]);
        $this->fields['situacao'] = 'aberto';
        Evento::registrar('pesquisas', 'formulario_aberto', $this, 0, ['titulo' => $this->fields['titulo']]);

        return true;
    }

    public function encerrar(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('pesquisas') || $this->ehOuvidoria()) {
            return false;
        }
        $DB->update(self::getTable(), ['situacao' => 'encerrado'], ['id' => (int) $this->getID()]);
        $this->fields['situacao'] = 'encerrado';
        Evento::registrar('pesquisas', 'formulario_encerrado', $this, 0, ['titulo' => $this->fields['titulo']]);

        return true;
    }

    public function urlPublica(): string
    {
        global $CFG_GLPI;

        if ((int) ($this->fields['publico_sem_login'] ?? 0) !== 1 || (string) ($this->fields['token'] ?? '') === '') {
            return '';
        }

        // BM.4-10 (D-91): endereco curto quando configurado (QR, cartaz e links passam a usa-lo).
        $base = OuvidoriaConfig::canalBase();
        if ($base !== '') {
            return $base . '/' . $this->fields['token'];
        }

        return rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/') . '/plugins/pessoasplus/front/responder_publico.php?t=' . $this->fields['token'];
    }

    // ----------------------------------------------------------------- responder

    /**
     * Valida e grava uma resposta. $uid = 0 para a pagina publica.
     * Devolve ['protocolo' => ..., 'codigo' => ...] ou ['erro' => ...].
     *
     * @param array<string, mixed> $post
     *
     * @return array<string, string>
     */
    public function responder(array $post, int $uid, bool $anonimoEscolhido, string $origem = 'logado'): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje = date('Y-m-d');
        if (self::estado($this->fields, $hoje) !== 'aberto') {
            return ['erro' => 'Este formulário não está aberto.'];
        }
        $modo = (string) $this->fields['modo'];
        $anonimo = $modo === 'anonimo' || ($modo === 'escolha' && $anonimoEscolhido);
        if ($uid === 0) {
            $anonimo = true; // pagina publica nunca identifica
        }
        if ($uid > 0 && !$this->ehOuvidoria() && $this->jaRespondeu($uid)) {
            return ['erro' => 'Você já respondeu a este formulário.'];
        }

        $itens = [];
        foreach ($this->perguntas() as $p) {
            $chave = 'p' . $p['id'];
            $bruto = $post[$chave] ?? '';
            $valor = '';
            switch ($p['tipo']) {
                case 'multipla':
                    $marcadas = is_array($bruto) ? $bruto : [];
                    $validas = array_values(array_intersect(array_map('strval', $marcadas), $p['opcoes']));
                    $valor = implode(' | ', $validas);
                    break;
                case 'unica':
                    $valor = in_array((string) $bruto, $p['opcoes'], true) ? (string) $bruto : '';
                    break;
                case 'escala':
                    $n = (int) $bruto;
                    $valor = ($n >= 1 && $n <= $p['escala_max']) ? (string) $n : '';
                    break;
                case 'simnao':
                    $valor = in_array((string) $bruto, ['sim', 'nao'], true) ? (string) $bruto : '';
                    break;
                case 'longo':
                    $valor = mb_substr(trim((string) $bruto), 0, 5000);
                    break;
                default:
                    $valor = mb_substr(trim((string) $bruto), 0, 500);
            }
            if ($p['obrigatoria'] && $valor === '') {
                return ['erro' => 'Responda: ' . $p['enunciado']];
            }
            $itens[(int) $p['id']] = $valor;
        }

        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $protocolo = $this->gerarProtocolo();
        $codigo = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $DB->insert(Resposta::getTable(), [
            'plugin_pessoasplus_formularios_id' => (int) $this->getID(),
            'protocolo'   => $protocolo,
            'codigo_hash' => hash('sha256', $protocolo . '|' . $codigo),
            'users_id'    => $anonimo ? 0 : $uid,
            'anonimo'     => $anonimo ? 1 : 0,
            'origem'      => $origem,
            // Anonimo: so o dia (D-31), nunca a hora.
            'data'        => $anonimo ? substr($agora, 0, 10) . ' 00:00:00' : $agora,
            'situacao'    => 'recebido',
        ]);
        $respostaId = (int) $DB->insertId();
        foreach ($itens as $pid => $valor) {
            $DB->insert(RespostaItem::getTable(), ['plugin_pessoasplus_respostas_id' => $respostaId, 'plugin_pessoasplus_perguntas_id' => $pid, 'valor' => $valor]);
        }
        // Participacao sem ligacao com a resposta: "ja respondeu", nada mais. So em pesquisa:
        // a Ouvidoria aceita varios envios da mesma pessoa e a chave unica (formulario, usuario)
        // estourava no segundo envio (BM.4-15, correcao). Se ja houver linha, nao insere.
        if ($uid > 0 && !$this->ehOuvidoria()) {
            Participacao::registrar((int) $this->getID(), $uid, Participacao::ORIGEM_ENVIO);
        }
        // Trilha: so para resposta identificada (D-31).
        if (!$anonimo && $uid > 0) {
            $ficha = Colaborador::porUsuario($uid);
            Evento::registrar('pesquisas', $this->ehOuvidoria() ? 'ouvidoria_enviada' : 'formulario_respondido', $this, $ficha === null ? 0 : (int) $ficha->getID(), ['protocolo' => $protocolo]);
        }

        return ['protocolo' => $protocolo, 'codigo' => $codigo];
    }

    public function jaRespondeu(int $uid): bool
    {
        return Participacao::existe((int) $this->getID(), $uid);
    }

    /**
     * BM.4-17 (D-97): pesquisa anonima com pagina publica e respondida FORA do GLPI,
     * pelo endereco publico com bilhete; a tela logada responder.php nao serve para ela.
     */
    public function respondeFora(): bool
    {
        return !$this->ehOuvidoria()
            && (string) ($this->fields['modo'] ?? '') === 'anonimo'
            && (int) ($this->fields['publico_sem_login'] ?? 0) === 1
            && (string) ($this->fields['token'] ?? '') !== '';
    }

    /** QR e cartaz sao opcionais (D-98); a Ouvidoria segue a configuracao da porta. */
    public function divulgaQr(): bool
    {
        return (int) ($this->fields['publico_sem_login'] ?? 0) === 1 && (int) ($this->fields['divulgar_qr'] ?? 0) === 1;
    }

    private function gerarProtocolo(): string
    {
        /** @var DBmysql $DB */
        global $DB;

        $prefixo = $this->ehOuvidoria() ? 'OUV' : 'PSQ';
        $ano = date('Y');
        $total = countElementsInTable(Resposta::getTable(), ['protocolo' => ['LIKE', $prefixo . '-' . $ano . '-%']]);
        for ($tentativa = 0; $tentativa < 20; $tentativa++) {
            $candidato = sprintf('%s-%s-%04d', $prefixo, $ano, $total + 1 + $tentativa);
            if (countElementsInTable(Resposta::getTable(), ['protocolo' => $candidato]) === 0) {
                return $candidato;
            }
        }

        return sprintf('%s-%s-%s', $prefixo, $ano, strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));
    }

    // ----------------------------------------------------------------- consulta por protocolo (T-55)

    /**
     * @return array<string, mixed>|null
     */
    public static function consultar(string $protocolo, string $codigo): ?array
    {
        $protocolo = strtoupper(trim($protocolo));
        $codigo    = strtoupper(trim($codigo));
        if (!preg_match('/^(OUV|PSQ)-\d{4}-[A-Z0-9]{4,6}$/', $protocolo) || $codigo === '') {
            return null;
        }
        $r = new Resposta();
        if (!$r->getFromDBByCrit(['protocolo' => $protocolo])) {
            return null;
        }
        if (!hash_equals((string) $r->fields['codigo_hash'], hash('sha256', $protocolo . '|' . $codigo))) {
            return null;
        }
        $f = new self();
        $f->getFromDB((int) $r->fields['plugin_pessoasplus_formularios_id']);
        $sit = self::SITUACOES_RESPOSTA[$r->fields['situacao']] ?? [$r->fields['situacao'], 'neutro'];

        return [
            'protocolo'   => $protocolo,
            'porta'       => $f->ehOuvidoria() ? (self::PORTAS[$f->fields['porta']][0] ?? '') : (string) $f->fields['titulo'],
            'modo'        => (int) $r->fields['anonimo'] === 1 ? 'Anônimo' : 'Identificado',
            'situacao'    => $sit[0],
            'tom'         => $sit[1],
            'atualizado'  => $r->fields['data_resposta'] ? date('d/m/Y', strtotime((string) $r->fields['data_resposta'])) : date('d/m/Y', strtotime((string) $r->fields['data'])),
            'resposta'    => (string) ($r->fields['resposta_rh'] ?? ''),
            'complemento' => (string) ($r->fields['pedido_complemento'] ?? ''),
            // BM.4-12 (D-93): o que o remetente ja acrescentou e se ainda pode acrescentar.
            'complementos' => self::complementosDe((int) $r->getID()),
            // BM.4-13 (D-95): a conversa inteira, em ordem (respostas do RH e complementos do remetente).
            'conversa'    => self::conversaDe($r),
            'aceita_complemento' => (string) $r->fields['situacao'] !== 'encerrado' && count(self::complementosDe((int) $r->getID())) < self::MAXIMO_COMPLEMENTOS,
        ];
    }

    /** Complementos por resposta (D-93): limite para a trilha nao virar bate-papo. */
    public const MAXIMO_COMPLEMENTOS = 10;

    /**
     * Complementos do remetente (BM.4-12, D-93). Ficam em respostas_itens com
     * perguntas_id = 0, valor = JSON {dia, texto}: sem usuario, sem hora, so o dia (D-31).
     *
     * @return array<int, array{dia: string, texto: string}>
     */
    public static function complementosDe(int $respostaId): array
    {
        return array_values(array_filter(self::entradasDe($respostaId), static fn ($e) => $e['de'] === 'remetente'));
    }

    /**
     * Entradas da conversa gravadas em respostas_itens com perguntas_id = 0 (BM.4-13, D-95).
     * Remetente: {dia, texto} (so o dia, D-31). RH: {de: 'rh', quando, texto}.
     *
     * @return array<int, array{de: string, dia: string, quando: string, texto: string}>
     */
    public static function entradasDe(int $respostaId): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        foreach ($DB->request(['FROM' => RespostaItem::getTable(), 'WHERE' => ['plugin_pessoasplus_respostas_id' => $respostaId, 'plugin_pessoasplus_perguntas_id' => 0], 'ORDER' => 'id']) as $i) {
            $j = json_decode((string) $i['valor'], true);
            if (!is_array($j) || !isset($j['texto'])) {
                continue;
            }
            $de = (string) ($j['de'] ?? 'remetente');
            $quando = $de === 'rh' ? (string) ($j['quando'] ?? $j['dia'] ?? '') : (string) ($j['dia'] ?? '');
            $saida[] = [
                'de'     => $de === 'rh' ? 'rh' : 'remetente',
                'dia'    => $quando !== '' ? date('d/m/Y', strtotime($quando)) : '',
                'quando' => $quando !== '' ? ($de === 'rh' ? date('d/m/Y H:i', strtotime($quando)) : date('d/m/Y', strtotime($quando))) : '',
                'texto'  => (string) $j['texto'],
                'ordem'  => [$quando !== '' ? substr($quando, 0, 10) : '0000-00-00', (int) $i['id']],
            ];
        }
        // Ordem: pelo dia e, no mesmo dia, pela gravacao (o remetente so tem o dia, D-31; a migracao da resposta antiga grava depois mas e de antes).
        usort($saida, static fn ($a, $b) => ($a['ordem'][0] <=> $b['ordem'][0]) ?: ($a['ordem'][1] <=> $b['ordem'][1]));
        foreach ($saida as &$e) {
            unset($e['ordem']);
        }
        unset($e);

        return $saida;
    }

    private static function respostaObj(int $id): Resposta
    {
        $r = new Resposta();
        $r->getFromDB($id);

        return $r;
    }

    /**
     * Conversa de um protocolo (BM.4-13, D-95): entradas em ordem cronologica de gravacao.
     * Compatibilidade: resposta_rh gravada antes do BM.4-13 (campo unico) entra como
     * primeira resposta do RH quando nenhuma resposta do RH esta nos itens.
     *
     * @return array<int, array{de: string, dia: string, quando: string, texto: string}>
     */
    public static function conversaDe(Resposta $r): array
    {
        $entradas = self::entradasDe((int) $r->getID());
        $temRh = false;
        foreach ($entradas as $e) {
            if ($e['de'] === 'rh') {
                $temRh = true;
                break;
            }
        }
        $antiga = trim((string) ($r->fields['resposta_rh'] ?? ''));
        if (!$temRh && $antiga !== '') {
            $q = (string) ($r->fields['data_resposta'] ?? '');
            array_unshift($entradas, ['de' => 'rh', 'dia' => $q !== '' ? date('d/m/Y', strtotime($q)) : '', 'quando' => $q !== '' ? date('d/m/Y H:i', strtotime($q)) : '', 'texto' => $antiga]);
        }

        return $entradas;
    }

    /**
     * Remetente acrescenta ao relato pelo protocolo + codigo (BM.4-12, D-93).
     * Mesma prova de posse da consulta; grava item com perguntas_id = 0 e so o dia.
     * D-94: se o RH estava aguardando complemento, a situacao volta a "em analise".
     * Devolve ['ok' => true] ou ['erro' => mensagem].
     *
     * @return array{ok?: bool, erro?: string}
     */
    public static function complementar(string $protocolo, string $codigo, string $texto): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $protocolo = strtoupper(trim($protocolo));
        $codigo    = strtoupper(trim($codigo));
        $texto     = mb_substr(trim($texto), 0, 2000);
        if ($texto === '') {
            return ['erro' => 'Escreva o complemento antes de enviar.'];
        }
        if (!preg_match('/^(OUV|PSQ)-\d{4}-[A-Z0-9]{4,6}$/', $protocolo) || $codigo === '') {
            return ['erro' => 'Protocolo ou código não conferem.'];
        }
        $r = new Resposta();
        if (!$r->getFromDBByCrit(['protocolo' => $protocolo]) || !hash_equals((string) $r->fields['codigo_hash'], hash('sha256', $protocolo . '|' . $codigo))) {
            return ['erro' => 'Protocolo ou código não conferem.'];
        }
        if ((string) $r->fields['situacao'] === 'encerrado') {
            return ['erro' => 'Este protocolo está encerrado e não aceita mais complementos. Se precisar, abra um novo relato.'];
        }
        if (count(self::complementosDe((int) $r->getID())) >= self::MAXIMO_COMPLEMENTOS) {
            return ['erro' => 'Limite de complementos atingido para este protocolo.'];
        }
        $DB->insert(RespostaItem::getTable(), [
            'plugin_pessoasplus_respostas_id' => (int) $r->getID(),
            'plugin_pessoasplus_perguntas_id' => 0,
            'valor' => json_encode(['dia' => date('Y-m-d'), 'texto' => $texto], JSON_UNESCAPED_UNICODE),
        ]);
        $f = new self();
        $f->getFromDB((int) $r->fields['plugin_pessoasplus_formularios_id']);
        $atualiza = ['pedido_complemento' => ''];
        if ((string) $r->fields['situacao'] === 'complemento') {
            $atualiza['situacao'] = 'em_analise';
        }
        $DB->update(Resposta::getTable(), $atualiza, ['id' => (int) $r->getID()]);
        Evento::registrar('pesquisas', 'ouvidoria_complementada', $f, 0, ['protocolo' => $protocolo]);

        return ['ok' => true];
    }

    // ----------------------------------------------------------------- fila e tratamento (RH)

    /**
     * Respostas de um formulario, da mais recente para a mais antiga.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fila(string $situacao = ''): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $where = ['plugin_pessoasplus_formularios_id' => (int) $this->getID()];
        if ($situacao !== '' && isset(self::SITUACOES_RESPOSTA[$situacao])) {
            $where['situacao'] = $situacao;
        }
        $perguntas = [];
        foreach ($this->perguntas(true) as $p) {
            $perguntas[$p['id']] = $p['enunciado'];
        }
        $saida = [];
        foreach ($DB->request(['FROM' => Resposta::getTable(), 'WHERE' => $where, 'ORDER' => ['data DESC', 'id DESC']]) as $r) {
            $itens = [];
            foreach ($DB->request(['FROM' => RespostaItem::getTable(), 'WHERE' => ['plugin_pessoasplus_respostas_id' => (int) $r['id'], 'plugin_pessoasplus_perguntas_id' => ['>', 0]], 'ORDER' => 'id']) as $i) {
                $itens[] = ['pergunta' => $perguntas[(int) $i['plugin_pessoasplus_perguntas_id']] ?? '?', 'valor' => (string) $i['valor']];
            }
            $sit = self::SITUACOES_RESPOSTA[$r['situacao']] ?? [$r['situacao'], 'neutro'];
            $saida[] = [
                'id'          => (int) $r['id'],
                'protocolo'   => (string) $r['protocolo'],
                'anonimo'     => (int) $r['anonimo'] === 1,
                'quem'        => (int) $r['anonimo'] === 1 ? 'Anônimo' : (string) getUserName((int) $r['users_id']),
                'origem'      => (string) $r['origem'] === 'publico' ? 'página pública' : 'logado',
                'data'        => (int) $r['anonimo'] === 1 ? date('d/m/Y', strtotime((string) $r['data'])) : date('d/m/Y H:i', strtotime((string) $r['data'])),
                'situacao'    => (string) $r['situacao'],
                'situacao_rotulo' => $sit[0],
                'tom'         => $sit[1],
                'itens'       => $itens,
                'resposta_rh' => (string) ($r['resposta_rh'] ?? ''),
                'complemento' => (string) ($r['pedido_complemento'] ?? ''),
                'complementos' => self::complementosDe((int) $r['id']), // BM.4-12 (D-93)
                'conversa'    => self::conversaDe(self::respostaObj((int) $r['id'])), // BM.4-13 (D-95)
            ];
        }

        return $saida;
    }

    /**
     * Resultado agregado por pergunta (pesquisas): contagens, media e textos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resultado(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $respostas = [];
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => Resposta::getTable(), 'WHERE' => ['plugin_pessoasplus_formularios_id' => (int) $this->getID()]]) as $r) {
            $respostas[] = (int) $r['id'];
        }
        $saida = [];
        foreach ($this->perguntas(true) as $p) {
            $valores = [];
            if ($respostas !== []) {
                foreach ($DB->request(['SELECT' => ['valor'], 'FROM' => RespostaItem::getTable(), 'WHERE' => ['plugin_pessoasplus_perguntas_id' => $p['id'], 'plugin_pessoasplus_respostas_id' => $respostas]]) as $i) {
                    $valores[] = (string) $i['valor'];
                }
            }
            $respondidas = count(array_filter($valores, static fn ($v) => $v !== ''));
            $item = ['pergunta' => $p['enunciado'], 'tipo' => $p['tipo'], 'tipo_rotulo' => $p['tipo_rotulo'], 'respondidas' => $respondidas, 'barras' => [], 'media' => null, 'textos' => []];
            switch ($p['tipo']) {
                case 'unica':
                case 'multipla':
                case 'simnao':
                    $opcoes = $p['tipo'] === 'simnao' ? ['sim', 'nao'] : $p['opcoes'];
                    $cont = array_fill_keys($opcoes, 0);
                    foreach ($valores as $v) {
                        foreach (explode(' | ', $v) as $parte) {
                            if (isset($cont[$parte])) {
                                $cont[$parte]++;
                            }
                        }
                    }
                    foreach ($cont as $o => $n) {
                        $item['barras'][] = ['rotulo' => $p['tipo'] === 'simnao' ? ($o === 'sim' ? 'Sim' : 'Não') : $o, 'total' => $n, 'percentual' => $respondidas > 0 ? (int) round($n * 100 / $respondidas) : 0];
                    }
                    break;
                case 'escala':
                    $soma = 0;
                    $cont = array_fill(1, $p['escala_max'], 0);
                    foreach ($valores as $v) {
                        $n = (int) $v;
                        if ($n >= 1 && $n <= $p['escala_max']) {
                            $soma += $n;
                            $cont[$n]++;
                        }
                    }
                    $item['media'] = $respondidas > 0 ? round($soma / $respondidas, 1) : null;
                    foreach ($cont as $n => $t) {
                        $item['barras'][] = ['rotulo' => (string) $n, 'total' => $t, 'percentual' => $respondidas > 0 ? (int) round($t * 100 / $respondidas) : 0];
                    }
                    break;
                default:
                    $item['textos'] = array_values(array_filter($valores, static fn ($v) => $v !== ''));
            }
            // BM.5: tres formatos prontos (barras / pizza / donut), legenda e opcao mais escolhida.
            $item['graficos'] = ['barras' => '', 'pizza' => '', 'donut' => ''];
            $item['legenda']  = '';
            $item['top']      = '';
            $item['enps']     = null;
            if ($item['barras'] !== []) {
                $item['graficos'] = [
                    'barras' => Grafico::barras($item['barras'], $p['tipo'] !== 'escala'),
                    'pizza'  => Grafico::pizza($item['barras'], false, 220, $respondidas),
                    'donut'  => Grafico::pizza($item['barras'], true, 220, $respondidas),
                ];
                $item['legenda'] = Grafico::legenda($item['barras']);
                $max = 0;
                foreach ($item['barras'] as $b) {
                    if ($b['total'] > $max) {
                        $max = $b['total'];
                        $item['top'] = $b['rotulo'];
                    }
                }
                if ($p['tipo'] === 'escala' && $p['escala_max'] === 10 && $respondidas > 0) {
                    // eNPS (D-32): promotores 9-10, detratores 1-6, na escala 1..10 do plugin.
                    $prom = 0;
                    $det  = 0;
                    foreach ($item['barras'] as $b) {
                        $n = (int) $b['rotulo'];
                        if ($n >= 9) {
                            $prom += $b['total'];
                        } elseif ($n <= 6) {
                            $det += $b['total'];
                        }
                    }
                    $item['enps'] = (int) round(($prom - $det) * 100 / $respondidas);
                }
            }
            $item['padrao'] = $p['tipo'] === 'unica' ? 'pizza' : ($p['tipo'] === 'multipla' ? 'donut' : 'barras');
            $saida[] = $item;
        }

        return $saida;
    }

    /** Mínimo de respostas para mostrar agregados de formulário anônimo (D-30, proposta 5.2). */
    public const MINIMO_ANONIMO = 5;

    /**
     * Regras de anonimato aplicadas a tela de resultado (secao 3.2.1 do contexto):
     * formulario que admite anonimato so mostra agregados a partir de MINIMO_ANONIMO
     * respostas, e texto aberto / respostas individuais so depois do encerramento.
     *
     * @return array{anonimo: bool, bloqueado: bool, textos_liberados: bool, faltam: int}
     */
    public function restricaoAnonimato(int $totalRespostas, string $estado): array
    {
        $anonimo = (string) $this->fields['modo'] !== 'identificado';
        if (!$anonimo) {
            return ['anonimo' => false, 'bloqueado' => false, 'textos_liberados' => true, 'faltam' => 0];
        }
        $bloqueado = $totalRespostas < self::MINIMO_ANONIMO;

        return [
            'anonimo'          => true,
            'bloqueado'        => $bloqueado,
            'textos_liberados' => !$bloqueado && $estado === 'encerrado',
            'faltam'           => max(0, self::MINIMO_ANONIMO - $totalRespostas),
        ];
    }

    /**
     * Indicadores da campanha (BM.5): respostas, adesao, media das escalas,
     * eNPS (quando ha escala 1..10), opcao mais escolhida e top 5 geral.
     *
     * @param array<int, array<string, mixed>> $resultado  saida de resultado()
     * @param array{total: int, responderam: int}|null $cobranca
     *
     * @return array{kpis: array<int, array{icone: string, valor: string, rotulo: string, detalhe: string, tom: string}>, top5: array<int, array{rotulo: string, total: int, percentual: int, pergunta: string}>, ranking: array<int, array{rotulo: string, total: int, percentual: int, pergunta: string}>}
     */
    public static function indicadores(array $resultado, ?array $cobranca, int $totalRespostas): array
    {
        $somaMedias = 0.0;
        $nMedias    = 0;
        $enps       = null;
        $opcoes     = [];
        $top        = ['rotulo' => '', 'total' => 0, 'pergunta' => ''];
        foreach ($resultado as $q) {
            if ($q['media'] !== null) {
                $somaMedias += (float) $q['media'];
                $nMedias++;
            }
            if ($q['enps'] !== null && $enps === null) {
                $enps = (int) $q['enps'];
            }
            if (in_array($q['tipo'], ['unica', 'multipla'], true)) {
                foreach ($q['barras'] as $b) {
                    $opcoes[] = ['rotulo' => $b['rotulo'], 'total' => (int) $b['total'], 'percentual' => (int) $b['percentual'], 'pergunta' => $q['pergunta']];
                    if ((int) $b['total'] > $top['total']) {
                        $top = ['rotulo' => $b['rotulo'], 'total' => (int) $b['total'], 'pergunta' => $q['pergunta']];
                    }
                }
            }
        }
        usort($opcoes, static fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['rotulo'], $b['rotulo']));
        // BM.4-9 (D-92): ranking completo (todas as alternativas com ao menos uma marcacao); o top 5 e a cabeca dele.
        $ranking = array_values(array_filter($opcoes, static fn ($o) => $o['total'] > 0));
        $top5 = array_slice($ranking, 0, 5);

        $kpis = [];
        $kpis[] = ['icone' => 'ti ti-inbox', 'valor' => (string) $totalRespostas, 'rotulo' => $totalRespostas === 1 ? 'Resposta' : 'Respostas', 'detalhe' => 'recebidas na campanha', 'tom' => 'info'];
        if ($cobranca !== null && $cobranca['total'] > 0) {
            $pct = (int) round($cobranca['responderam'] * 100 / $cobranca['total']);
            $decl = (int) ($cobranca['declarados'] ?? 0);
            $kpis[] = ['icone' => 'ti ti-users-group', 'valor' => $pct . '%', 'rotulo' => 'Adesão', 'detalhe' => $cobranca['responderam'] . ' de ' . $cobranca['total'] . ' do público' . ($decl > 0 ? ' (' . $decl . ' por declaração pelo QR)' : ''), 'tom' => $pct >= 70 ? 'ok' : ($pct >= 40 ? 'alerta' : 'atraso')];
        } else {
            $kpis[] = ['icone' => 'ti ti-users-group', 'valor' => '—', 'rotulo' => 'Adesão', 'detalhe' => 'público não definido', 'tom' => 'neutro'];
        }
        if ($nMedias > 0) {
            $kpis[] = ['icone' => 'ti ti-chart-line', 'valor' => number_format($somaMedias / $nMedias, 1, ',', ''), 'rotulo' => 'Média das escalas', 'detalhe' => $nMedias . ($nMedias === 1 ? ' pergunta de escala' : ' perguntas de escala'), 'tom' => 'info'];
        }
        if ($enps !== null) {
            $kpis[] = ['icone' => 'ti ti-mood-smile', 'valor' => ($enps > 0 ? '+' : '') . $enps, 'rotulo' => 'eNPS', 'detalhe' => 'promotores (9-10) menos detratores (1-6)', 'tom' => $enps >= 30 ? 'ok' : ($enps >= 0 ? 'alerta' : 'atraso')];
        }
        if ($top['rotulo'] !== '') {
            $kpis[] = ['icone' => 'ti ti-trophy', 'valor' => (string) $top['total'], 'rotulo' => 'Mais escolhida: ' . $top['rotulo'], 'detalhe' => $top['pergunta'], 'tom' => 'ok'];
        }
        $perguntas = count($resultado);
        if (count($kpis) < 5) {
            $kpis[] = ['icone' => 'ti ti-list-check', 'valor' => (string) $perguntas, 'rotulo' => $perguntas === 1 ? 'Pergunta' : 'Perguntas', 'detalhe' => 'na campanha', 'tom' => 'neutro'];
        }

        return ['kpis' => array_slice($kpis, 0, 5), 'top5' => $top5, 'ranking' => $ranking];
    }

    /**
     * Visao geral da campanha (BM.4-11, D-92, desenho B): indicadores da
     * campanha inteira, respostas por dia e adesao por setor.
     *
     * Anonimato (D-31): "por dia" vem da coluna data das respostas, que no
     * modo anonimo guarda so o dia; "por setor" vem da PARTICIPACAO (quem
     * respondeu ou nao), nunca da resposta, igual a cobranca().
     *
     * @param array{total: int, responderam: int, faltam: array<int, string>}|null $cobranca  saida de cobranca(); null sem publico
     * @param array<string, mixed> $tela  saida de paraTela()
     *
     * @return array{kpis: array<int, array<string, string>>, por_dia: array<int, array{rotulo: string, total: int}>, por_dia_semana: bool, por_setor: array<int, array{setor: string, responderam: int, total: int, percentual: int}>}
     */
    public function visaoGeral(?array $cobranca, array $tela, int $totalRespostas, string $hoje): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $formId = (int) $this->getID();

        // --- respostas por dia
        $porData = [];
        $primeira = null;
        $ultima = null;
        foreach ($DB->request(['SELECT' => ['data'], 'FROM' => Resposta::getTable(), 'WHERE' => ['plugin_pessoasplus_formularios_id' => $formId]]) as $r) {
            $d = substr((string) $r['data'], 0, 10);
            if ($d === '' || $d === '0000-00-00') {
                continue;
            }
            $porData[$d] = ($porData[$d] ?? 0) + 1;
            $primeira = $primeira === null || $d < $primeira ? $d : $primeira;
            $ultima = $ultima === null || $d > $ultima ? $d : $ultima;
        }
        $inicio = (string) ($tela['inicio'] ?? '');
        $fim    = (string) ($tela['fim'] ?? '');
        $de  = $inicio !== '' ? $inicio : ($primeira ?? $hoje);
        $ate = ($fim !== '' && $fim < $hoje) ? $fim : $hoje;
        if ($primeira !== null && $primeira < $de) {
            $de = $primeira;
        }
        if ($ultima !== null && $ultima > $ate) {
            $ate = $ultima;
        }
        if ($ate < $de) {
            $ate = $de;
        }
        $porDia = [];
        $semana = false;
        $dias = (int) ((strtotime($ate) - strtotime($de)) / 86400) + 1;
        if ($dias <= 31) {
            for ($t = strtotime($de); $t <= strtotime($ate); $t += 86400) {
                $d = date('Y-m-d', $t);
                $porDia[] = ['rotulo' => date('d/m', $t), 'total' => (int) ($porData[$d] ?? 0)];
            }
        } else {
            // Mais de um mes: agrupa por semana (segunda-feira como rotulo).
            $semana = true;
            $segunda = strtotime('monday this week', strtotime($de));
            $fimT = strtotime($ate);
            for ($t = $segunda; $t <= $fimT; $t += 7 * 86400) {
                $soma = 0;
                for ($k = 0; $k < 7; $k++) {
                    $soma += (int) ($porData[date('Y-m-d', $t + $k * 86400)] ?? 0);
                }
                $porDia[] = ['rotulo' => date('d/m', $t), 'total' => $soma];
            }
        }

        // --- adesao por setor (participacao x publico)
        $porSetor = [];
        if ($cobranca !== null && $cobranca['total'] > 0) {
            $publico = Publico::resolverRegras($this->regrasDoPublico());
            $ja = [];
            foreach ($DB->request(['SELECT' => ['users_id'], 'FROM' => Participacao::getTable(), 'WHERE' => ['plugin_pessoasplus_formularios_id' => $formId]]) as $p) {
                $ja[(int) $p['users_id']] = true;
            }
            $setorDe = [];
            if ($publico !== []) {
                foreach ($DB->request(['SELECT' => ['users_id', 'groups_id'], 'FROM' => Colaborador::getTable(), 'WHERE' => ['users_id' => $publico, 'is_deleted' => 0]]) as $c) {
                    $setorDe[(int) $c['users_id']] = (int) $c['groups_id'];
                }
            }
            $nomes = [];
            $cont = [];
            foreach ($publico as $uid) {
                $gid = $setorDe[$uid] ?? 0;
                if (!isset($nomes[$gid])) {
                    $nome = 'Sem setor definido';
                    if ($gid > 0) {
                        $g = new Group();
                        if ($g->getFromDB($gid)) {
                            $nome = (string) $g->fields['completename'];
                        }
                    }
                    $nomes[$gid] = $nome;
                    $cont[$gid] = ['responderam' => 0, 'total' => 0];
                }
                $cont[$gid]['total']++;
                if (isset($ja[$uid])) {
                    $cont[$gid]['responderam']++;
                }
            }
            foreach ($cont as $gid => $c) {
                $porSetor[] = ['setor' => $nomes[$gid], 'responderam' => $c['responderam'], 'total' => $c['total'], 'percentual' => $c['total'] > 0 ? (int) round($c['responderam'] * 100 / $c['total']) : 0];
            }
            usort($porSetor, static fn ($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['setor'], $b['setor']));
        }

        // --- indicadores da campanha (desenho B)
        $kpis = [];
        $kpis[] = ['icone' => 'ti ti-inbox', 'valor' => (string) $totalRespostas, 'rotulo' => $totalRespostas === 1 ? 'Resposta' : 'Respostas', 'detalhe' => $cobranca !== null && $cobranca['total'] > 0 ? 'de ' . $cobranca['total'] . ' no público' : 'recebidas na campanha', 'tom' => 'info'];
        if ($cobranca !== null && $cobranca['total'] > 0) {
            $faltam = count($cobranca['faltam']);
            $kpis[] = ['icone' => 'ti ti-user-exclamation', 'valor' => (string) $faltam, 'rotulo' => $faltam === 1 ? 'Falta responder' : 'Faltam responder', 'detalhe' => $faltam === 0 ? 'todo o público respondeu' : 'ver em Resumo › Quem ainda não respondeu', 'tom' => $faltam === 0 ? 'ok' : 'alerta'];
        } else {
            $kpis[] = ['icone' => 'ti ti-user-exclamation', 'valor' => '—', 'rotulo' => 'Faltam responder', 'detalhe' => 'público não definido', 'tom' => 'neutro'];
        }
        $estado = (string) ($tela['estado'] ?? '');
        if ($estado === 'aberto' && $fim !== '') {
            $rest = max(0, (int) round((strtotime($fim) - strtotime($hoje)) / 86400));
            $kpis[] = ['icone' => 'ti ti-calendar-event', 'valor' => (string) $rest, 'rotulo' => $rest === 1 ? 'Dia restante' : 'Dias restantes', 'detalhe' => (string) ($tela['periodo'] ?? ''), 'tom' => $rest <= 2 ? 'alerta' : 'info'];
        } elseif ($estado === 'aberto') {
            $kpis[] = ['icone' => 'ti ti-calendar-event', 'valor' => '∞', 'rotulo' => 'Sem data de fim', 'detalhe' => 'aberta até encerrar', 'tom' => 'info'];
        } else {
            $kpis[] = ['icone' => 'ti ti-calendar-event', 'valor' => (string) ($tela['estado_rotulo'] ?? $estado), 'rotulo' => 'Situação', 'detalhe' => (string) ($tela['periodo'] ?? ''), 'tom' => (string) ($tela['estado_tom'] ?? 'neutro')];
        }
        $modo = (string) ($tela['modo'] ?? '');
        $kpis[] = ['icone' => $modo === 'identificado' ? 'ti ti-user' : 'ti ti-incognito', 'valor' => (string) (self::MODOS[$modo] ?? $modo), 'rotulo' => 'Modo', 'detalhe' => $modo === 'identificado' ? 'resposta ligada ao usuário' : ($modo === 'anonimo' ? 'só o dia é gravado (D-31)' : 'quem responde escolhe'), 'tom' => 'neutro'];

        return ['kpis' => $kpis, 'por_dia' => $porDia, 'por_dia_semana' => $semana, 'por_setor' => $porSetor];
    }

    /**
     * Previa do formulario (BM.4-9, D-92): normaliza as perguntas vindas do
     * editor (perguntas_json, ainda nao salvas) no mesmo formato de perguntas(),
     * com as mesmas regras de salvarPerguntas(), sem gravar nada. O id e a
     * posicao (1..n): serve so para o name dos campos da previa.
     *
     * @param array<int, mixed> $perguntas
     *
     * @return array<int, array<string, mixed>>
     */
    public static function previaPerguntas(array $perguntas): array
    {
        $saida = [];
        foreach ($perguntas as $p) {
            if (!is_array($p)) {
                continue;
            }
            $tipo = (string) ($p['tipo'] ?? '');
            $enunciado = mb_substr(trim((string) ($p['enunciado'] ?? '')), 0, 500);
            if (!isset(self::TIPOS_PERGUNTA[$tipo]) || $enunciado === '') {
                continue;
            }
            $opcoes = [];
            if ($tipo === 'unica' || $tipo === 'multipla') {
                $lista = is_array($p['opcoes'] ?? null) ? $p['opcoes'] : preg_split('/\r?\n/', (string) ($p['opcoes'] ?? ''));
                foreach ($lista as $o) {
                    $o = mb_substr(trim((string) $o), 0, 200);
                    if ($o !== '') {
                        $opcoes[] = $o;
                    }
                }
                if (count($opcoes) < 2) {
                    continue;
                }
            }
            $escalaMax = in_array((int) ($p['escala_max'] ?? 5), [5, 10], true) ? (int) ($p['escala_max'] ?? 5) : 5;
            $saida[] = [
                'id'          => count($saida) + 1,
                'tipo'        => $tipo,
                'tipo_rotulo' => self::TIPOS_PERGUNTA[$tipo],
                'enunciado'   => $enunciado,
                'removida'    => false,
                'opcoes'      => $opcoes,
                'obrigatoria' => !empty($p['obrigatoria']),
                'escala_max'  => $escalaMax,
                'escala'      => range(1, $escalaMax),
            ];
        }

        return $saida;
    }

    /**
     * Transicoes automaticas da situacao (BM.4-12, D-94). Valem so quando o RH
     * deixou a situacao como estava; se ele escolheu outra no select, a escolha manda.
     * - pedido de complemento novo (preenchido e diferente do gravado), vindo de recebido/em analise -> aguardando complemento
     * - resposta nova (preenchida e diferente da gravada), vindo de recebido/em analise/aguardando -> respondido
     * - encerrado so a mao, e nao sai dele sozinho
     */
    public static function proximaSituacao(string $anterior, string $escolhida, string $respostaAntes, string $respostaDepois, string $pedidoAntes, string $pedidoDepois): string
    {
        if ($escolhida !== $anterior || $anterior === 'encerrado') {
            return $escolhida;
        }
        if ($pedidoDepois !== '' && $pedidoDepois !== $pedidoAntes && in_array($anterior, ['recebido', 'em_analise'], true)) {
            return 'complemento';
        }
        if ($respostaDepois !== '' && $respostaDepois !== $respostaAntes && in_array($anterior, ['recebido', 'em_analise', 'complemento'], true)) {
            return 'respondido';
        }

        return $anterior;
    }

    /**
     * Resumo por situacao, para os chips.
     *
     * @return array<string, int>
     */
    public function resumoFila(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = array_fill_keys(array_keys(self::SITUACOES_RESPOSTA), 0);
        $saida['total'] = 0;
        foreach ($DB->request(['SELECT' => ['situacao'], 'FROM' => Resposta::getTable(), 'WHERE' => ['plugin_pessoasplus_formularios_id' => (int) $this->getID()]]) as $r) {
            $saida[$r['situacao']] = ($saida[$r['situacao']] ?? 0) + 1;
            $saida['total']++;
        }

        return $saida;
    }

    /** Trata uma resposta: situacao, resposta do RH e pedido de complemento. */
    public function tratar(int $respostaId, string $situacao, string $respostaRh, string $complemento): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!$this->podeLerFila() || !isset(self::SITUACOES_RESPOSTA[$situacao])) {
            return false;
        }
        $r = new Resposta();
        if (!$r->getFromDB($respostaId) || (int) $r->fields['plugin_pessoasplus_formularios_id'] !== (int) $this->getID()) {
            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $respostaRh  = mb_substr(trim($respostaRh), 0, 5000);
        $complemento = mb_substr(trim($complemento), 0, 2000);
        // BM.4-13 (D-95): o campo do tratamento e "nova resposta": vazio nao apaga a anterior; preenchido vira entrada datada da conversa.
        $anteriorRh = (string) ($r->fields['resposta_rh'] ?? '');
        if ($respostaRh === '') {
            $respostaRh = $anteriorRh;
        } elseif ($respostaRh !== $anteriorRh) {
            // Primeira resposta nova de um protocolo tratado antes do BM.4-13: a antiga (campo unico) vira entrada antes, para a conversa nao perder nada.
            if ($anteriorRh !== '' && array_filter(self::entradasDe($respostaId), static fn ($e) => $e['de'] === 'rh') === []) {
                $DB->insert(RespostaItem::getTable(), [
                    'plugin_pessoasplus_respostas_id' => $respostaId,
                    'plugin_pessoasplus_perguntas_id' => 0,
                    'valor' => json_encode(['de' => 'rh', 'quando' => (string) ($r->fields['data_resposta'] ?? $agora), 'texto' => $anteriorRh], JSON_UNESCAPED_UNICODE),
                ]);
            }
            $DB->insert(RespostaItem::getTable(), [
                'plugin_pessoasplus_respostas_id' => $respostaId,
                'plugin_pessoasplus_perguntas_id' => 0,
                'valor' => json_encode(['de' => 'rh', 'quando' => $agora, 'texto' => $respostaRh], JSON_UNESCAPED_UNICODE),
            ]);
        }
        $situacao    = self::proximaSituacao((string) $r->fields['situacao'], $situacao, $anteriorRh, $respostaRh, (string) ($r->fields['pedido_complemento'] ?? ''), $complemento);
        $DB->update(Resposta::getTable(), [
            'situacao'           => $situacao,
            'resposta_rh'        => $respostaRh,
            'pedido_complemento' => $complemento,
            'data_resposta'      => $agora,
            'users_id_rh'        => (int) (Session::getLoginUserID() ?: 0),
        ], ['id' => $respostaId]);
        Evento::registrar('pesquisas', 'ouvidoria_tratada', $this, 0, ['protocolo' => $r->fields['protocolo'], 'situacao' => $situacao]);

        return true;
    }

    /**
     * Quem ainda nao respondeu (publico de hoje menos participacoes), sem
     * dizer o que ninguem respondeu (D-31).
     *
     * @return array{total: int, responderam: int, faltam: string[], declarados: int}
     */
    public function cobranca(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $publico = Publico::resolverRegras($this->regrasDoPublico());
        $ja = [];
        foreach ($DB->request(['SELECT' => ['users_id'], 'FROM' => Participacao::getTable(), 'WHERE' => ['plugin_pessoasplus_formularios_id' => (int) $this->getID()]]) as $p) {
            $ja[(int) $p['users_id']] = true;
        }
        $faltam = [];
        foreach ($publico as $uid) {
            if (!isset($ja[$uid])) {
                $faltam[] = (string) getUserName($uid);
            }
        }
        sort($faltam);

        return ['total' => count($publico), 'responderam' => count($publico) - count($faltam), 'faltam' => $faltam, 'declarados' => Participacao::declarados((int) $this->getID())];
    }

    // ----------------------------------------------------------------- limites da pagina publica

    /** Conta um envio da pagina publica; devolve false quando estourou o limite do dia. */
    public function registrarEnvioPublico(string $ip): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        $dia   = date('Y-m-d');
        $chave = hash('sha256', $ip . '|' . $dia . '|' . (string) $this->fields['token']);
        $atual = 0;
        foreach ($DB->request(['FROM' => 'glpi_plugin_pessoasplus_limites', 'WHERE' => ['chave' => $chave]]) as $l) {
            $atual = (int) $l['total'];
        }
        if ($atual >= (int) $this->fields['limite_dia']) {
            return false;
        }
        if ($atual === 0) {
            $DB->insert('glpi_plugin_pessoasplus_limites', ['chave' => $chave, 'dia' => $dia, 'total' => 1]);
        } else {
            $DB->update('glpi_plugin_pessoasplus_limites', ['total' => $atual + 1], ['chave' => $chave]);
        }

        return true;
    }

    // ----------------------------------------------------------------- consultas de lista

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function lista(?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje ??= date('Y-m-d');
        $saida = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_deleted' => 0], 'ORDER' => ['tipo', 'date_mod DESC']]) as $f) {
            $form = new self();
            $form->fields = $f;
            if (!$form->podeLerFila()) {
                continue;
            }
            $saida[] = self::paraTela($f, $hoje) + ['resumo' => $form->resumoFila()];
        }

        return $saida;
    }

    /**
     * @param array<string, mixed> $f
     *
     * @return array<string, mixed>
     */
    public static function paraTela(array $f, string $hoje): array
    {
        $estado = self::estado($f, $hoje);
        $form = new self();
        $form->fields = $f;

        return [
            'id'            => (int) $f['id'],
            'titulo'        => (string) $f['titulo'],
            'descricao'     => (string) ($f['descricao'] ?? ''),
            'tipo'          => (string) $f['tipo'],
            'porta'         => (string) ($f['porta'] ?? ''),
            'porta_rotulo'  => self::PORTAS[$f['porta'] ?? ''][0] ?? '',
            'modo'          => (string) $f['modo'],
            'modo_rotulo'   => self::MODOS[$f['modo']] ?? $f['modo'],
            'estado'        => $estado,
            'estado_rotulo' => self::ESTADOS[$estado][0] ?? $estado,
            'estado_tom'    => self::ESTADOS[$estado][1] ?? 'neutro',
            'periodo'       => Publicacao::periodoLegivel($f['inicio'] ?? null, $f['fim'] ?? null),
            'inicio'        => (string) ($f['inicio'] ?? ''),
            'fim'           => (string) ($f['fim'] ?? ''),
            'publico_sem_login' => (int) ($f['publico_sem_login'] ?? 0) === 1,
            'limite_dia'    => (int) ($f['limite_dia'] ?? 20),
            'divulgar_qr'   => $form->divulgaQr(),
            'responde_fora' => $form->respondeFora(),
            'url_publica'   => $form->urlPublica(),
            // BM.4-17 (D-97): anonima com pagina publica -> bilhete.php emite o bilhete e leva para fora do GLPI.
            'url_responder' => $form->respondeFora()
                ? \Html::getPrefixedUrl('/plugins/pessoasplus/front/bilhete.php') . '?id=' . (int) $f['id']
                : \Html::getPrefixedUrl('/plugins/pessoasplus/front/responder.php') . '?id=' . (int) $f['id'],
            'url_declarar'  => \Html::getPrefixedUrl('/plugins/pessoasplus/front/bilhete.php'),
            'url_fila'      => \Html::getPrefixedUrl('/plugins/pessoasplus/front/fila.php') . '?id=' . (int) $f['id'],
            'url_editar'    => \Html::getPrefixedUrl('/plugins/pessoasplus/front/formulario_novo.php') . '?id=' . (int) $f['id'],
            'url_qr'        => \Html::getPrefixedUrl('/plugins/pessoasplus/front/qr.php') . '?id=' . (int) $f['id'],
        ];
    }

    /**
     * Pesquisas abertas que alcancam o usuario e que ele ainda nao respondeu
     * (Minha area).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function pendentesDe(int $uid, ?string $hoje = null): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $hoje ??= date('Y-m-d');
        $saida = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_deleted' => 0, 'tipo' => 'pesquisa', 'situacao' => 'aberto']]) as $f) {
            if (self::estado($f, $hoje) !== 'aberto') {
                continue;
            }
            $form = new self();
            $form->fields = $f;
            if (!$form->alcanca($uid) || $form->jaRespondeu($uid)) {
                continue;
            }
            $saida[] = self::paraTela($f, $hoje);
        }

        return $saida;
    }

    /** Formulario da porta da Ouvidoria, ou null. */
    public static function porta(string $porta): ?self
    {
        if (!isset(self::PORTAS[$porta])) {
            return null;
        }
        $f = new self();

        return $f->getFromDBByCrit(['tipo' => 'ouvidoria', 'porta' => $porta, 'is_deleted' => 0]) ? $f : null;
    }

    /** Cria as quatro portas da Ouvidoria se ainda nao existem (Install). */
    public static function semearOuvidoria(DBmysql $DB): void
    {
        $agora = date('Y-m-d H:i:s');
        foreach (self::PORTAS as $chave => $def) {
            $existe = false;
            foreach ($DB->request(['SELECT' => ['id'], 'FROM' => self::getTable(), 'WHERE' => ['tipo' => 'ouvidoria', 'porta' => $chave], 'LIMIT' => 1]) as $l) {
                $existe = true;
            }
            if ($existe) {
                continue;
            }
            $DB->insert(Publico::getTable(), ['nome' => 'Ouvidoria: ' . $def[0], 'itemtype' => self::class, 'items_id' => 0, 'is_modelo' => 0, 'date_creation' => $agora, 'date_mod' => $agora]);
            $publicoId = (int) $DB->insertId();
            $DB->insert(PublicoRegra::getTable(), ['plugin_pessoasplus_publicos_id' => $publicoId, 'tipo' => 'todos', 'valor_id' => 0, 'valor_texto' => '', 'incluir_filhos' => 0, 'is_exclusao' => 0]);
            $DB->insert(self::getTable(), [
                'entities_id' => 0, 'titulo' => $def[0], 'descricao' => $def[1], 'tipo' => 'ouvidoria', 'porta' => $chave,
                'modo' => $def[2], 'situacao' => 'aberto', 'inicio' => null, 'fim' => null, 'publico_sem_login' => $chave === 'conduta' ? 1 : 0, 'divulgar_qr' => $chave === 'conduta' ? 1 : 0,
                'token' => bin2hex(random_bytes(16)), 'limite_dia' => 20, 'plugin_pessoasplus_publicos_id' => $publicoId,
                'publico_modelo_id' => 0, 'users_id' => 0, 'is_deleted' => 0, 'date_creation' => $agora, 'date_mod' => $agora,
            ]);
            $formId = (int) $DB->insertId();
            $DB->update(Publico::getTable(), ['items_id' => $formId], ['id' => $publicoId]);
            $DB->insert(Pergunta::getTable(), ['plugin_pessoasplus_formularios_id' => $formId, 'ordem' => 0, 'tipo' => 'curto', 'enunciado' => 'Assunto', 'opcoes' => '[]', 'obrigatoria' => 1, 'escala_max' => 5]);
            $DB->insert(Pergunta::getTable(), ['plugin_pessoasplus_formularios_id' => $formId, 'ordem' => 1, 'tipo' => 'longo', 'enunciado' => $chave === 'conduta' ? 'Relate o que aconteceu (o que, quando, onde). Não é preciso se identificar.' : 'Descreva', 'opcoes' => '[]', 'obrigatoria' => 1, 'escala_max' => 5]);
        }
    }
}
