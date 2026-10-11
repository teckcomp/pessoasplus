<?php

/**
 * Pessoas+ - termo de cautela (Adendo 3, D-42; BM.6-1).
 *
 * PESSOASPLUS_BUILD_BM6
 *
 * Um termo liga um colaborador a uma lista de itens: ativos do GLPI (nativos
 * ou personalizados, pelo users_id, V-13) ou itens livres digitados pelo RH
 * (ferramenta, chave, uniforme; EPI fica fora). Ciclo: rascunho -> emitido
 * (lista congelada com selo SHA-256) -> assinado (ciencia do colaborador,
 * registro imutavel) | cancelado. Devolucao e um termo do tipo "devolucao".
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;
use Session;

final class Cautela extends CommonDBTM
{
    public static $rightname = 'pessoasplus_cautela';
    public $dohistory = false;

    public const TIPOS = [
        'entrega'   => ['Termo de entrega', 'ti ti-package'],
        'devolucao' => ['Termo de devolução', 'ti ti-package-import'],
    ];

    public const SITUACOES = [
        'rascunho'  => ['Rascunho', 'neutro'],
        'emitido'   => ['Aguardando ciência', 'alerta'],
        'assinado'  => ['Assinado', 'ok'],
        'cancelado' => ['Cancelado', 'neutro'],
    ];

    /** Ativos nativos do GLPI com dono (users_id): itemtype => [tabela, rotulo, icone, pagina]. V-13. */
    public const ATIVOS_NATIVOS = [
        'Computer'   => ['glpi_computers', 'Computador', 'ti ti-device-laptop', 'computer'],
        'Phone'      => ['glpi_phones', 'Telefone', 'ti ti-device-mobile', 'phone'],
        'Peripheral' => ['glpi_peripherals', 'Periférico', 'ti ti-device-usb', 'peripheral'],
        'Monitor'    => ['glpi_monitors', 'Monitor', 'ti ti-device-desktop', 'monitor'],
        'Printer'    => ['glpi_printers', 'Impressora', 'ti ti-printer', 'printer'],
    ];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_cautela_termos';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Termos de cautela' : 'Termo de cautela';
    }

    public static function getIcon()
    {
        return 'ti ti-tool';
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

    /** RH que le Cautela, o proprio colaborador e o gestor do grupo (D-36). */
    public function canViewItem(): bool
    {
        if (!Permissoes::usa()) {
            return false;
        }
        if (Permissoes::le('cautela')) {
            return true;
        }
        $eu = (int) Session::getLoginUserID();
        if ($eu > 0 && (int) $this->fields['users_id'] === $eu) {
            return true;
        }
        $col = new Colaborador();
        if ((int) $this->fields['plugin_pessoasplus_colaboradores_id'] > 0 && $col->getFromDB((int) $this->fields['plugin_pessoasplus_colaboradores_id'])) {
            return $col->canViewItem();
        }

        return false;
    }

    /** So rascunho e editavel; o resto e evidencia. */
    public function canUpdateItem(): bool
    {
        return Permissoes::gerencia('cautela') && ($this->fields['situacao'] ?? 'rascunho') === 'rascunho';
    }

    public function canDeleteItem(): bool
    {
        return $this->canUpdateItem();
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
        $input['date_creation'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $input['date_mod']      = $input['date_creation'];
        if ((int) ($input['plugin_pessoasplus_colaboradores_id'] ?? 0) <= 0) {
            Session::addMessageAfterRedirect('Escolha o colaborador.', false, ERROR);

            return false;
        }
        $col = new Colaborador();
        if ($col->getFromDB((int) $input['plugin_pessoasplus_colaboradores_id'])) {
            $input['users_id']    = (int) $col->fields['users_id'];
            $input['entities_id'] = (int) ($col->fields['entities_id'] ?? 0);
        }

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        if (($this->fields['situacao'] ?? '') !== 'rascunho') {
            // Evidencia: depois de emitido nada muda pelo formulario.
            return ['id' => $input['id'] ?? 0];
        }
        $input = $this->normalizar($input);
        unset($input['plugin_pessoasplus_colaboradores_id'], $input['users_id'], $input['situacao'], $input['selo']);
        $input['date_mod'] = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        return $input;
    }

    private function normalizar(array $input): array
    {
        if (array_key_exists('tipo', $input) && !isset(self::TIPOS[$input['tipo']])) {
            $input['tipo'] = 'entrega';
        }
        if (array_key_exists('titulo', $input)) {
            $input['titulo'] = mb_substr(trim((string) $input['titulo']), 0, 255);
        }
        if (array_key_exists('observacoes', $input)) {
            $input['observacoes'] = mb_substr(trim((string) $input['observacoes']), 0, 5000);
        }

        return $input;
    }

    public function post_addItem()
    {
        Evento::registrar('cautela', 'cautela_criada', $this, (int) $this->fields['plugin_pessoasplus_colaboradores_id'], ['tipo' => $this->fields['tipo']]);
    }

    public function cleanDBonPurge()
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->delete(CautelaItem::getTable(), ['plugin_pessoasplus_cautela_termos_id' => (int) $this->getID()]);
    }

    // ----------------------------------------------------------------- itens

    /**
     * Normaliza a lista de itens vinda do formulario (JSON).
     *
     * @return array<int, array{origem: string, itemtype: string, items_id: int, descricao: string, patrimonio: string, serie: string, quantidade: int, foto_obrigatoria: int}>
     */
    public static function normalizarItens(string $json): array
    {
        $lista = json_decode($json, true);
        if (!is_array($lista)) {
            return [];
        }
        $saida = [];
        $vistos = [];
        foreach ($lista as $i) {
            if (!is_array($i)) {
                continue;
            }
            $origem   = ($i['origem'] ?? '') === 'glpi' ? 'glpi' : 'livre';
            $itemtype = $origem === 'glpi' ? mb_substr(trim((string) ($i['itemtype'] ?? '')), 0, 100) : '';
            $itemsId  = $origem === 'glpi' ? (int) ($i['items_id'] ?? 0) : 0;
            $desc     = mb_substr(trim((string) ($i['descricao'] ?? '')), 0, 255);
            if ($desc === '') {
                continue;
            }
            if ($origem === 'glpi') {
                if ($itemtype === '' || $itemsId <= 0 || isset($vistos[$itemtype . '#' . $itemsId])) {
                    continue;
                }
                $vistos[$itemtype . '#' . $itemsId] = true;
            }
            $saida[] = [
                'origem'           => $origem,
                'itemtype'         => $itemtype,
                'items_id'         => $itemsId,
                'descricao'        => $desc,
                'patrimonio'       => mb_substr(trim((string) ($i['patrimonio'] ?? '')), 0, 100),
                'serie'            => mb_substr(trim((string) ($i['serie'] ?? '')), 0, 100),
                'quantidade'       => max(1, min(9999, (int) ($i['quantidade'] ?? 1))),
                'foto_obrigatoria' => (int) (bool) ($i['foto_obrigatoria'] ?? 0),
            ];
            if (count($saida) >= 200) {
                break;
            }
        }

        return $saida;
    }

    /** Regrava os itens de um rascunho. */
    public function definirItens(array $itens): void
    {
        /** @var DBmysql $DB */
        global $DB;

        if (($this->fields['situacao'] ?? '') !== 'rascunho') {
            return;
        }
        $DB->delete(CautelaItem::getTable(), ['plugin_pessoasplus_cautela_termos_id' => (int) $this->getID()]);
        foreach ($itens as $ordem => $i) {
            $DB->insert(CautelaItem::getTable(), $i + ['plugin_pessoasplus_cautela_termos_id' => (int) $this->getID(), 'ordem' => $ordem + 1]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itens(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        foreach ($DB->request(['FROM' => CautelaItem::getTable(), 'WHERE' => ['plugin_pessoasplus_cautela_termos_id' => (int) $this->getID()], 'ORDER' => 'ordem']) as $i) {
            $saida[] = [
                'id'               => (int) $i['id'],
                'origem'           => (string) $i['origem'],
                'itemtype'         => (string) $i['itemtype'],
                'items_id'         => (int) $i['items_id'],
                'descricao'        => (string) $i['descricao'],
                'patrimonio'       => (string) $i['patrimonio'],
                'serie'            => (string) $i['serie'],
                'quantidade'       => (int) $i['quantidade'],
                'foto_obrigatoria' => (int) $i['foto_obrigatoria'] === 1,
                'tipo_rotulo'      => self::rotuloItemtype((string) $i['itemtype']),
                'url'              => self::urlAtivo((string) $i['itemtype'], (int) $i['items_id']),
            ];
        }

        return $saida;
    }

    // ----------------------------------------------------------------- emissao e ciencia

    /** Selo do termo: sobre o conteudo congelado (colaborador, tipo, itens em ordem, observacoes, data). */
    public static function calcularSelo(array $f, array $itens, string $emitidoEm): string
    {
        $linhas = [(int) $f['users_id'], (int) $f['plugin_pessoasplus_colaboradores_id'], (string) $f['tipo'], (string) $f['titulo'], (string) $f['observacoes'], $emitidoEm];
        foreach ($itens as $i) {
            $linhas[] = implode('|', [$i['origem'], $i['itemtype'], (int) $i['items_id'], $i['descricao'], $i['patrimonio'], $i['serie'], (int) $i['quantidade']]);
        }

        return hash('sha256', implode("\n", $linhas));
    }

    /** Emite: congela os itens, calcula o selo e abre a pendencia de ciencia. */
    public function emitir(): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('cautela') || ($this->fields['situacao'] ?? '') !== 'rascunho') {
            return false;
        }
        $itens = $this->itens();
        if ($itens === []) {
            Session::addMessageAfterRedirect('O termo precisa de pelo menos um item.', false, ERROR);

            return false;
        }
        if ((int) $this->fields['users_id'] <= 0) {
            Session::addMessageAfterRedirect('O colaborador ainda não tem usuário do GLPI ligado à ficha: sem usuário não há quem dê ciência. Vincule na pasta e emita de novo.', false, ERROR);

            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $selo  = self::calcularSelo($this->fields, $itens, $agora);
        $DB->update(self::getTable(), ['situacao' => 'emitido', 'emitido_em' => $agora, 'users_id_rh' => (int) Session::getLoginUserID(), 'selo' => $selo, 'date_mod' => $agora], ['id' => (int) $this->getID()]);
        $this->fields['situacao']   = 'emitido';
        $this->fields['emitido_em'] = $agora;
        $this->fields['selo']       = $selo;
        Evento::registrar('cautela', 'cautela_emitida', $this, (int) $this->fields['plugin_pessoasplus_colaboradores_id'], ['tipo' => $this->fields['tipo'], 'itens' => count($itens), 'selo' => substr($selo, 0, 16)]);

        return true;
    }

    public function cancelar(string $motivo): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!Permissoes::gerencia('cautela') || !in_array($this->fields['situacao'] ?? '', ['rascunho', 'emitido'], true)) {
            return false;
        }
        $motivo = mb_substr(trim($motivo), 0, 500);
        if ($motivo === '') {
            Session::addMessageAfterRedirect('Informe o motivo do cancelamento.', false, ERROR);

            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->update(self::getTable(), ['situacao' => 'cancelado', 'motivo_cancelamento' => $motivo, 'date_mod' => $agora], ['id' => (int) $this->getID()]);
        $this->fields['situacao'] = 'cancelado';
        Evento::registrar('cautela', 'cautela_cancelada', $this, (int) $this->fields['plugin_pessoasplus_colaboradores_id'], ['motivo' => $motivo]);

        return true;
    }

    /** Selo da ciencia (mesmo padrao da ciencia de comunicado). */
    public static function seloCiencia(int $uid, int $termoId, string $seloTermo, string $data, string $interface): string
    {
        return hash('sha256', implode('|', [$uid, $termoId, $seloTermo, $data, $interface]));
    }

    /** Ciencia do colaborador: so o dono do termo, uma vez, registro imutavel. */
    public function registrarCiencia(int $uid, string $interface): bool
    {
        /** @var DBmysql $DB */
        global $DB;

        if (($this->fields['situacao'] ?? '') !== 'emitido' || $uid <= 0 || (int) $this->fields['users_id'] !== $uid) {
            return false;
        }
        $agora = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $interface = $interface === 'helpdesk' ? 'helpdesk' : 'central';
        $selo  = self::seloCiencia($uid, (int) $this->getID(), (string) $this->fields['selo'], $agora, $interface);
        $DB->update(self::getTable(), ['situacao' => 'assinado', 'ciencia_data' => $agora, 'ciencia_interface' => $interface, 'ciencia_selo' => $selo, 'date_mod' => $agora], ['id' => (int) $this->getID()]);
        $this->fields['situacao']          = 'assinado';
        $this->fields['ciencia_data']      = $agora;
        $this->fields['ciencia_interface'] = $interface;
        $this->fields['ciencia_selo']      = $selo;
        Evento::registrar('cautela', 'cautela_assinada', $this, (int) $this->fields['plugin_pessoasplus_colaboradores_id'], ['tipo' => $this->fields['tipo'], 'interface' => $interface]);

        return true;
    }

    /** Confere o selo do termo e o da ciencia contra o que esta gravado. */
    public function integridade(): array
    {
        $f = $this->fields;
        $termoOk = true;
        $cienciaOk = true;
        if (in_array($f['situacao'], ['emitido', 'assinado'], true)) {
            $termoOk = self::calcularSelo($f, $this->itens(), (string) $f['emitido_em']) === (string) $f['selo'];
        }
        if ($f['situacao'] === 'assinado') {
            $cienciaOk = self::seloCiencia((int) $f['users_id'], (int) $f['id'], (string) $f['selo'], (string) $f['ciencia_data'], (string) $f['ciencia_interface']) === (string) $f['ciencia_selo'];
        }

        return ['termo' => $termoOk, 'ciencia' => $cienciaOk];
    }

    // ----------------------------------------------------------------- ativos do GLPI (V-13)

    public static function rotuloItemtype(string $itemtype): string
    {
        if ($itemtype === '') {
            return 'Item livre';
        }
        if (isset(self::ATIVOS_NATIVOS[$itemtype])) {
            return self::ATIVOS_NATIVOS[$itemtype][1];
        }
        if (preg_match('#^Glpi\\\\CustomAsset\\\\(.+)Asset$#', $itemtype, $m)) {
            return self::rotuloDefinicao($m[1]);
        }

        return $itemtype;
    }

    private static function rotuloDefinicao(string $systemName): string
    {
        /** @var DBmysql $DB */
        global $DB;

        foreach ($DB->request(['SELECT' => ['label'], 'FROM' => 'glpi_assets_assetdefinitions', 'WHERE' => ['system_name' => $systemName], 'LIMIT' => 1]) as $d) {
            return (string) $d['label'];
        }

        return $systemName;
    }

    /** URL do formulario do ativo (nativo: /front/<x>.form.php; personalizado: /front/asset/asset.form.php?class=, CustomObjectTrait.php:60). */
    public static function urlAtivo(string $itemtype, int $id): string
    {
        if ($itemtype === '' || $id <= 0) {
            return '';
        }
        if (isset(self::ATIVOS_NATIVOS[$itemtype])) {
            return \Html::getPrefixedUrl('/front/' . self::ATIVOS_NATIVOS[$itemtype][3] . '.form.php') . '?id=' . $id;
        }
        if (preg_match('#^Glpi\\\\CustomAsset\\\\(.+)Asset$#', $itemtype, $m)) {
            return \Html::getPrefixedUrl('/front/asset/asset.form.php') . '?class=' . rawurlencode($m[1]) . '&id=' . $id;
        }

        return '';
    }

    /**
     * Ativos do GLPI cujo dono (users_id) e o usuario: nativos e personalizados
     * ativos, sem excluidos nem modelos. Conferido em glpi-empty.sql 11.0.6 (V-13).
     *
     * @return array<int, array{itemtype: string, items_id: int, tipo_rotulo: string, icone: string, nome: string, serie: string, patrimonio: string, estado: string, url: string, descricao: string}>
     */
    public static function ativosDoUsuario(int $uid): array
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($uid <= 0) {
            return [];
        }
        $saida = [];
        $estados = [];
        foreach ($DB->request(['SELECT' => ['id', 'name'], 'FROM' => 'glpi_states']) as $s) {
            $estados[(int) $s['id']] = (string) $s['name'];
        }
        foreach (self::ATIVOS_NATIVOS as $itemtype => [$tabela, $rotulo, $icone]) {
            if (!$DB->tableExists($tabela)) {
                continue;
            }
            $it = $DB->request(['SELECT' => ['id', 'name', 'serial', 'otherserial', 'states_id'], 'FROM' => $tabela, 'WHERE' => ['users_id' => $uid, 'is_deleted' => 0, 'is_template' => 0], 'ORDER' => 'name']);
            foreach ($it as $a) {
                $saida[] = self::ativoParaTela($itemtype, $rotulo, $icone, $a, $estados);
            }
        }
        if ($DB->tableExists('glpi_assets_assets') && $DB->tableExists('glpi_assets_assetdefinitions')) {
            $defs = [];
            foreach ($DB->request(['SELECT' => ['id', 'system_name', 'label', 'icon'], 'FROM' => 'glpi_assets_assetdefinitions', 'WHERE' => ['is_active' => 1]]) as $d) {
                $defs[(int) $d['id']] = $d;
            }
            if ($defs !== []) {
                $it = $DB->request(['SELECT' => ['id', 'name', 'serial', 'otherserial', 'states_id', 'assets_assetdefinitions_id'], 'FROM' => 'glpi_assets_assets', 'WHERE' => ['users_id' => $uid, 'is_deleted' => 0, 'is_template' => 0, 'assets_assetdefinitions_id' => array_keys($defs)], 'ORDER' => 'name']);
                foreach ($it as $a) {
                    $d = $defs[(int) $a['assets_assetdefinitions_id']];
                    $icone = (string) ($d['icon'] ?? '');
                    $saida[] = self::ativoParaTela('Glpi\\CustomAsset\\' . $d['system_name'] . 'Asset', (string) $d['label'], $icone !== '' ? $icone : 'ti ti-box', $a, $estados);
                }
            }
        }

        return $saida;
    }

    private static function ativoParaTela(string $itemtype, string $rotulo, string $icone, array $a, array $estados): array
    {
        $nome = (string) $a['name'] !== '' ? (string) $a['name'] : $rotulo . ' #' . (int) $a['id'];

        return [
            'itemtype'    => $itemtype,
            'items_id'    => (int) $a['id'],
            'tipo_rotulo' => $rotulo,
            'icone'       => $icone,
            'nome'        => $nome,
            'serie'       => (string) ($a['serial'] ?? ''),
            'patrimonio'  => (string) ($a['otherserial'] ?? ''),
            'estado'      => $estados[(int) ($a['states_id'] ?? 0)] ?? '',
            'url'         => self::urlAtivo($itemtype, (int) $a['id']),
            'descricao'   => $rotulo . ': ' . $nome,
        ];
    }

    // ----------------------------------------------------------------- listas

    /**
     * Linha pronta para as telas (toda chave existe, T-06).
     */
    public static function paraTela(array $f, array $itens = []): array
    {
        $tipo = self::TIPOS[$f['tipo']] ?? [$f['tipo'], 'ti ti-tool'];
        $sit  = self::SITUACOES[$f['situacao']] ?? [$f['situacao'], 'neutro'];
        $col  = new Colaborador();
        $nome = '';
        $setor = '';
        if ((int) $f['plugin_pessoasplus_colaboradores_id'] > 0 && $col->getFromDB((int) $f['plugin_pessoasplus_colaboradores_id'])) {
            $t = $col->paraTela();
            $nome = $t['nome'];
            $setor = $t['setor'];
        }
        $base = \Html::getPrefixedUrl('/plugins/pessoasplus/front/');

        return [
            'id'              => (int) $f['id'],
            'tipo'            => (string) $f['tipo'],
            'tipo_rotulo'     => $tipo[0],
            'icone'           => $tipo[1],
            'titulo'          => (string) $f['titulo'] !== '' ? (string) $f['titulo'] : $tipo[0],
            'situacao'        => (string) $f['situacao'],
            'situacao_rotulo' => $sit[0],
            'tom'             => $sit[1],
            'colaborador_id'  => (int) $f['plugin_pessoasplus_colaboradores_id'],
            'colaborador'     => $nome,
            'setor'           => $setor,
            'users_id'        => (int) $f['users_id'],
            'observacoes'     => (string) ($f['observacoes'] ?? ''),
            'emitido_em'      => (string) ($f['emitido_em'] ?? '') !== '' ? date('d/m/Y H:i', strtotime((string) $f['emitido_em'])) : '',
            'emitido_por'     => (int) ($f['users_id_rh'] ?? 0) > 0 ? (string) getUserName((int) $f['users_id_rh']) : '',
            'ciencia_data'    => (string) ($f['ciencia_data'] ?? '') !== '' ? date('d/m/Y H:i:s', strtotime((string) $f['ciencia_data'])) : '',
            'ciencia_interface' => ($f['ciencia_interface'] ?? '') === 'helpdesk' ? 'Simplificada' : 'Padrão',
            'selo'            => (string) ($f['selo'] ?? ''),
            'ciencia_selo'    => (string) ($f['ciencia_selo'] ?? ''),
            'motivo_cancelamento' => (string) ($f['motivo_cancelamento'] ?? ''),
            'itens'           => $itens,
            'total_itens'     => count($itens),
            'url'             => $base . 'cautela_termo.php?id=' . (int) $f['id'],
            'url_ciencia'     => $base . 'cautela_ciencia.php?id=' . (int) $f['id'],
            'url_comprovante' => $base . 'cautela_comprovante.php?id=' . (int) $f['id'],
            'url_pasta'       => (int) $f['plugin_pessoasplus_colaboradores_id'] > 0 ? $base . 'colaborador.form.php?id=' . (int) $f['plugin_pessoasplus_colaboradores_id'] . '&aba=cautela' : '',
        ];
    }

    /**
     * Termos de um colaborador (pasta) ou de um usuario (Minha area).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function lista(array $where, bool $comItens = false): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_deleted' => 0] + $where, 'ORDER' => ['date_creation DESC', 'id DESC']]) as $t) {
            $termo = new self();
            $termo->fields = $t;
            $saida[] = self::paraTela($t, $comItens ? $termo->itens() : self::contarItens((int) $t['id']));
        }

        return $saida;
    }

    /** @return array<int, mixed> lista vazia com a contagem no tamanho (so para total_itens) */
    private static function contarItens(int $termoId): array
    {
        return array_fill(0, countElementsInTable(CautelaItem::getTable(), ['plugin_pessoasplus_cautela_termos_id' => $termoId]), null);
    }

    /** Termos aguardando a ciencia do usuario (Minha area). */
    public static function pendentesDe(int $uid): array
    {
        return $uid > 0 ? self::lista(['users_id' => $uid, 'situacao' => 'emitido']) : [];
    }

    /** Itens em cautela com a pessoa agora: entregas assinadas menos devolucoes assinadas (por descricao+patrimonio+serie). */
    public static function emPosseDe(int $colaboradorId): array
    {
        $posse = [];
        foreach (array_reverse(self::lista(['plugin_pessoasplus_colaboradores_id' => $colaboradorId, 'situacao' => 'assinado'], true)) as $t) {
            foreach ($t['itens'] as $i) {
                $chave = $i['origem'] === 'glpi' ? $i['itemtype'] . '#' . $i['items_id'] : 'L|' . mb_strtolower($i['descricao']) . '|' . $i['patrimonio'] . '|' . $i['serie'];
                if ($t['tipo'] === 'entrega') {
                    $posse[$chave] = $i + ['termo_id' => $t['id'], 'desde' => $t['ciencia_data']];
                } else {
                    unset($posse[$chave]);
                }
            }
        }

        return array_values($posse);
    }
}
