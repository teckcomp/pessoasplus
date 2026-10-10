<?php

/**
 * Pessoas+ - ficha do colaborador (F1), base do Fichario (D-34).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use CommonGLPI;
use DBmysql;
use Group;
use Session;
use User;

/**
 * Uma ficha por pessoa. Ligada a um usuario do GLPI (users_id > 0) ou em
 * pre-cadastro (users_id = 0). Setor e sempre um grupo do GLPI (D-35, D-69);
 * empregador, cargo e local sao listas livres do plugin. Nenhum dado de
 * saude entra aqui (regra do projeto).
 *
 * Quem ve o que (D-36): RH com Fichario Ler ve tudo; gestor ve as fichas
 * dos grupos em que e is_manager; o colaborador ve a propria. So quem tem
 * Fichario Gerenciar cria e altera.
 */
final class Colaborador extends CommonDBTM
{
    public static $rightname = 'pessoasplus_fichario';

    /** O historico fica na trilha do plugin (Evento), nao em glpi_logs. */
    public $dohistory = false;

    public const VINCULOS = [
        'clt'          => 'CLT',
        'pj'           => 'Prestador PJ',
        'estagio'      => 'Estágio',
        'aprendiz'     => 'Jovem aprendiz',
        'temporario'   => 'Temporário',
        'terceirizado' => 'Terceirizado',
        'outro'        => 'Outro',
    ];

    public const SITUACOES = [
        'pre_cadastro'    => ['Pré-cadastro', 'info'],
        'ativo'           => ['Ativo', 'ok'],
        'afastado'        => ['Afastado', 'alerta'],
        'em_desligamento' => ['Em desligamento', 'alerta'],
        'desligado'       => ['Desligado', 'neutro'],
    ];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_colaboradores';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Colaboradores' : 'Colaborador';
    }

    public static function getIcon()
    {
        return 'ti ti-id-badge-2';
    }

    // ----------------------------------------------------------------- direitos

    public static function canCreate(): bool
    {
        return Permissoes::gerencia('fichario');
    }

    public static function canView(): bool
    {
        // A grade e as pastas sao filtradas por canViewItem; o direito
        // global e "usa o Pessoas+", porque o colaborador ve a propria pasta.
        return Permissoes::usa();
    }

    public static function canUpdate(): bool
    {
        return Permissoes::gerencia('fichario');
    }

    public static function canDelete(): bool
    {
        return Permissoes::gerencia('fichario');
    }

    public static function canPurge(): bool
    {
        return false;
    }

    public function canCreateItem(): bool
    {
        return Permissoes::gerencia('fichario');
    }

    public function canUpdateItem(): bool
    {
        return Permissoes::gerencia('fichario');
    }

    public function canDeleteItem(): bool
    {
        return Permissoes::gerencia('fichario');
    }

    public function canPurgeItem(): bool
    {
        return false;
    }

    public function canViewItem(): bool
    {
        if (!Permissoes::usa()) {
            return false;
        }
        if (Permissoes::le('fichario')) {
            return true;
        }
        $eu = (int) Session::getLoginUserID();
        if ($eu > 0 && (int) $this->fields['users_id'] === $eu) {
            return true;
        }
        if (Permissoes::gestor() && (int) $this->fields['groups_id'] > 0) {
            return in_array((int) $this->fields['groups_id'], self::gruposGeridos($eu), true);
        }

        return false;
    }

    /**
     * Grupos em que o usuario e gestor (glpi_groups_users.is_manager), P-05.
     *
     * @return int[]
     */
    public static function gruposGeridos(int $usersId): array
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($usersId <= 0) {
            return [];
        }
        $saida = [];
        $it = $DB->request([
            'SELECT' => ['groups_id'],
            'FROM'   => 'glpi_groups_users',
            'WHERE'  => ['users_id' => $usersId, 'is_manager' => 1],
        ]);
        foreach ($it as $g) {
            $saida[] = (int) $g['groups_id'];
        }

        return $saida;
    }

    // ----------------------------------------------------------------- entrada

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>|false
     */
    private function normalizar(array $input, bool $novo)
    {
        if (array_key_exists('nome', $input)) {
            $input['nome'] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $input['nome']) ?? ''), 0, 255);
            if ($input['nome'] === '') {
                Session::addMessageAfterRedirect('Informe o nome do colaborador.', false, ERROR);

                return false;
            }
        } elseif ($novo) {
            Session::addMessageAfterRedirect('Informe o nome do colaborador.', false, ERROR);

            return false;
        }

        if (array_key_exists('email', $input)) {
            $input['email'] = mb_substr(trim((string) $input['email']), 0, 255);
            if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                Session::addMessageAfterRedirect('O e-mail informado não é válido.', false, ERROR);

                return false;
            }
        }
        foreach (['telefone' => 50, 'matricula' => 50] as $campo => $tam) {
            if (array_key_exists($campo, $input)) {
                $input[$campo] = mb_substr(trim((string) $input[$campo]), 0, $tam);
            }
        }
        if (array_key_exists('vinculo', $input) && !isset(self::VINCULOS[$input['vinculo']])) {
            $input['vinculo'] = 'outro';
        }
        if (array_key_exists('situacao', $input) && !isset(self::SITUACOES[$input['situacao']])) {
            $input['situacao'] = 'ativo';
        }
        foreach (['data_inicio', 'data_fim'] as $campo) {
            if (array_key_exists($campo, $input)) {
                $valor = trim((string) $input[$campo]);
                if ($valor === '') {
                    $input[$campo] = 'NULL';
                } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) || !checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))) {
                    Session::addMessageAfterRedirect('Data inválida em "' . ($campo === 'data_inicio' ? 'Início' : 'Fim') . '".', false, ERROR);

                    return false;
                }
            }
        }
        foreach (['users_id', 'groups_id', 'users_id_gestor', 'plugin_pessoasplus_empregadores_id', 'plugin_pessoasplus_cargos_id', 'plugin_pessoasplus_locais_id', 'entities_id'] as $campo) {
            if (array_key_exists($campo, $input)) {
                $input[$campo] = max(0, (int) $input[$campo]);
            }
        }

        // Um usuario do GLPI tem no maximo uma ficha.
        if (!empty($input['users_id'])) {
            $outra = self::porUsuario((int) $input['users_id']);
            $meuId = $novo ? 0 : (int) $this->getID();
            if ($outra !== null && (int) $outra->getID() !== $meuId) {
                Session::addMessageAfterRedirect('Este usuário já tem uma ficha: ' . $outra->fields['nome'] . '.', false, ERROR);

                return false;
            }
            // Pre-cadastro ligado a usuario deixa de ser pre-cadastro.
            $situacao = $input['situacao'] ?? ($novo ? 'pre_cadastro' : $this->fields['situacao']);
            if ($situacao === 'pre_cadastro') {
                $input['situacao'] = 'ativo';
            }
        }

        if (array_key_exists('observacoes', $input)) {
            $input['observacoes'] = mb_substr((string) $input['observacoes'], 0, 5000);
        }

        return $input;
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->normalizar($input, true);
        if ($input === false) {
            return false;
        }
        $input['situacao'] = $input['situacao'] ?? (empty($input['users_id']) ? 'pre_cadastro' : 'ativo');
        $input['vinculo']  = $input['vinculo'] ?? 'clt';
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
        Evento::registrar('fichario', 'ficha_criada', $this, (int) $this->getID(), [
            'nome'     => $this->fields['nome'],
            'situacao' => $this->fields['situacao'],
            'users_id' => (int) $this->fields['users_id'],
        ]);
    }

    public function post_updateItem($history = true)
    {
        $mudancas = [];
        foreach ($this->updates as $campo) {
            if (in_array($campo, ['date_mod'], true)) {
                continue;
            }
            $mudancas[$campo] = [
                'de'   => $this->oldvalues[$campo] ?? null,
                'para' => $this->fields[$campo] ?? null,
            ];
        }
        if ($mudancas === []) {
            return;
        }
        $acao = (isset($mudancas['users_id']) && (int) ($mudancas['users_id']['de'] ?? 0) === 0 && (int) $this->fields['users_id'] > 0)
            ? 'ficha_vinculada'
            : 'ficha_alterada';
        Evento::registrar('fichario', $acao, $this, (int) $this->getID(), ['campos' => $mudancas]);
    }

    public function post_deleteItem()
    {
        Evento::registrar('fichario', 'ficha_excluida', $this, (int) $this->getID(), ['nome' => $this->fields['nome']]);
    }

    // ----------------------------------------------------------------- consultas

    /** Ficha ligada ao usuario, ou null. */
    public static function porUsuario(int $usersId): ?self
    {
        if ($usersId <= 0) {
            return null;
        }
        $c = new self();
        if ($c->getFromDBByCrit(['users_id' => $usersId, 'is_deleted' => 0])) {
            return $c;
        }

        return null;
    }

    /**
     * Dados da ficha resolvidos (nomes, rotulos, foto) para os templates.
     * Toda chave existe sempre (T-06).
     *
     * @return array<string, mixed>
     */
    public function paraTela(): array
    {
        $f        = $this->fields;
        $usuario  = (int) $f['users_id'] > 0 ? new User() : null;
        $temUser  = $usuario !== null && $usuario->getFromDB((int) $f['users_id']);
        $gestor   = (int) $f['users_id_gestor'] > 0 ? (string) getUserName((int) $f['users_id_gestor']) : '';
        $setor    = '';
        if ((int) $f['groups_id'] > 0) {
            $g = new Group();
            if ($g->getFromDB((int) $f['groups_id'])) {
                $setor = (string) $g->fields['completename'];
            }
        }
        $sit = self::SITUACOES[$f['situacao']] ?? [$f['situacao'], 'neutro'];

        return [
            'id'             => (int) $f['id'],
            'nome'           => (string) $f['nome'],
            'iniciais'       => self::iniciais((string) $f['nome']),
            'email'          => (string) $f['email'],
            'telefone'       => (string) $f['telefone'],
            'matricula'      => (string) $f['matricula'],
            'vinculo'        => (string) $f['vinculo'],
            'vinculo_rotulo' => self::VINCULOS[$f['vinculo']] ?? (string) $f['vinculo'],
            'situacao'       => (string) $f['situacao'],
            'situacao_rotulo' => $sit[0],
            'situacao_tom'   => $sit[1],
            'empregador_id'  => (int) $f['plugin_pessoasplus_empregadores_id'],
            'empregador'     => Empregador::nome((int) $f['plugin_pessoasplus_empregadores_id']),
            'cargo_id'       => (int) $f['plugin_pessoasplus_cargos_id'],
            'cargo'          => Cargo::nome((int) $f['plugin_pessoasplus_cargos_id']),
            'local_id'       => (int) $f['plugin_pessoasplus_locais_id'],
            'local'          => Local::nome((int) $f['plugin_pessoasplus_locais_id']),
            'setor_id'       => (int) $f['groups_id'],
            'setor'          => $setor,
            'gestor_id'      => (int) $f['users_id_gestor'],
            'gestor'         => $gestor,
            'data_inicio'    => (string) ($f['data_inicio'] ?? ''),
            'data_fim'       => (string) ($f['data_fim'] ?? ''),
            'observacoes'    => (string) ($f['observacoes'] ?? ''),
            'users_id'       => (int) $f['users_id'],
            'usuario_login'  => $temUser ? (string) $usuario->fields['name'] : '',
            'usuario_nome'   => $temUser ? (string) getUserName((int) $f['users_id']) : '',
            'usuario_ativo'  => $temUser ? (bool) $usuario->fields['is_active'] : false,
            'foto'           => $temUser ? (string) User::getThumbnailURLForPicture($usuario->fields['picture'] ?? null) : '',
            'url'            => self::getFormURLWithID((int) $f['id']),
            'url_usuario'    => $temUser ? User::getFormURLWithID((int) $f['users_id']) : '',
            'data_criacao'   => (string) ($f['date_creation'] ?? ''),
            'data_alteracao' => (string) ($f['date_mod'] ?? ''),
        ];
    }

    public static function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $partes = array_values(array_filter($partes, static fn ($p) => $p !== '' && mb_strlen($p) > 2));
        if ($partes === []) {
            return mb_strtoupper(mb_substr($nome, 0, 2));
        }
        $primeira = mb_substr($partes[0], 0, 1);
        $ultima   = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';

        return mb_strtoupper($primeira . $ultima);
    }

    // ----------------------------------------------------------------- aba na ficha do usuario

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof User || !Permissoes::usa()) {
            return '';
        }
        $ficha = self::porUsuario((int) $item->getID());
        if ($ficha !== null && $ficha->canViewItem()) {
            return self::createTabEntry('Pessoas+', 0, $item::class, self::getIcon());
        }
        if ($ficha === null && Permissoes::gerencia('fichario')) {
            return self::createTabEntry('Pessoas+', 0, $item::class, self::getIcon());
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof User || !Permissoes::usa()) {
            return false;
        }
        $ficha = self::porUsuario((int) $item->getID());
        if ($ficha !== null && $ficha->canViewItem()) {
            $d = $ficha->paraTela();
            echo '<div class="card"><div class="card-body">';
            echo '<p class="mb-1"><strong>' . htmlescape($d['nome']) . '</strong>';
            echo ' <span class="badge bg-secondary">' . htmlescape($d['situacao_rotulo']) . '</span></p>';
            echo '<p class="text-muted mb-2">' . htmlescape(trim($d['cargo'] . ' · ' . $d['setor'] . ' · ' . $d['vinculo_rotulo'], ' ·')) . '</p>';
            echo '<a class="btn btn-primary btn-sm" href="' . htmlescape($d['url']) . '">Abrir a pasta no Fichário</a>';
            echo '</div></div>';

            return true;
        }
        if ($ficha === null && Permissoes::gerencia('fichario')) {
            $url = \Html::getPrefixedUrl(self::getFormURL(false)) . '?users_id=' . (int) $item->getID();
            echo '<div class="card"><div class="card-body">';
            echo '<p class="mb-2">Este usuário ainda não tem ficha no Pessoas+.</p>';
            echo '<a class="btn btn-primary btn-sm" href="' . htmlescape($url) . '">Criar a ficha</a>';
            echo '</div></div>';

            return true;
        }

        return false;
    }
}
