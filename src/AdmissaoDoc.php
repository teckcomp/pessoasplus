<?php

/**
 * Pessoas+ - documento de admissao de uma ficha (BM.8-1a, D-89, D-90, D-99).
 *
 * PESSOASPLUS_BUILD_BM8_1
 *
 * Uma linha por pendencia. Situacoes: pendente (sem arquivo) -> enviado (arquivo aguardando o RH)
 * -> aceito | recusado (com motivo; a linha e o arquivo FICAM, D-90, e nasce uma nova pendencia
 * para reenvio) | dispensado (RH decidiu que nao precisa). Arquivo em pasta propria do plugin,
 * fora do webroot (files/_plugins/pessoasplus/admissao/<ficha>/), servido so por
 * front/admissao_arquivo.php para quem gerencia o Fichario ou para o proprio colaborador (D-99).
 * SHA-256 gravado para conferencia de integridade.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;
use Session;

final class AdmissaoDoc extends CommonDBTM
{
    public static $rightname = 'pessoasplus_fichario';

    public $dohistory = false;

    public const PASTA = 'pessoasplus/admissao';

    public const SITUACOES = [
        'pendente'   => ['Pendente', 'alerta', 'ti ti-file-upload'],
        'enviado'    => ['Enviado · aguardando o RH', 'info', 'ti ti-file-search'],
        'aceito'     => ['Aceito', 'ok', 'ti ti-file-check'],
        'recusado'   => ['Recusado', 'atraso', 'ti ti-file-x'],
        'dispensado' => ['Dispensado', 'neutro', 'ti ti-file-off'],
    ];

    public const EXTENSOES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    public const LIMITE_BYTES = 10 * 1024 * 1024;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_admissao_docs';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Documentos de admissão' : 'Documento de admissão';
    }

    // ----------------------------------------------------------------- pendencias

    /**
     * Cria as pendencias que faltam para a ficha (uma por tipo ativo do vinculo que ainda nao
     * tem linha). Idempotente: chamar de novo nao duplica. Devolve quantas nasceram.
     */
    public static function gerarPendencias(Colaborador $c): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $id = (int) $c->getID();
        if ($id <= 0) {
            return 0;
        }
        $existentes = [];
        foreach ($DB->request(['SELECT' => ['plugin_pessoasplus_admissao_tipos_id'], 'FROM' => self::getTable(), 'WHERE' => ['plugin_pessoasplus_colaboradores_id' => $id]]) as $d) {
            $existentes[(int) $d['plugin_pessoasplus_admissao_tipos_id']] = true;
        }
        $agora = date('Y-m-d H:i:s');
        $novas = 0;
        foreach (AdmissaoTipo::paraVinculo((string) $c->fields['vinculo']) as $t) {
            if (isset($existentes[$t['id']])) {
                continue;
            }
            $DB->insert(self::getTable(), [
                'plugin_pessoasplus_colaboradores_id' => $id,
                'plugin_pessoasplus_admissao_tipos_id' => $t['id'], 'titulo' => '',
                'situacao' => 'pendente', 'arquivo' => '', 'nome_original' => '', 'sha256' => '', 'tamanho' => 0,
                'enviado_em' => null, 'users_id_envio' => 0, 'avaliado_em' => null, 'users_id_avaliacao' => 0, 'motivo' => '',
                'date_creation' => $agora, 'date_mod' => $agora,
            ]);
            $novas++;
        }
        if ($novas > 0) {
            Evento::registrar('fichario', 'admissao_pendencias', $c, $id, ['novas' => $novas, 'vinculo' => (string) $c->fields['vinculo']]);
        }

        return $novas;
    }

    /** D-100: pendencia avulsa com titulo livre (tipo 0). Devolve '' ou erro. */
    public static function criarAvulsa(Colaborador $c, string $titulo): string
    {
        /** @var DBmysql $DB */
        global $DB;

        $titulo = mb_substr(trim(preg_replace('/\s+/u', ' ', $titulo) ?? ''), 0, 150);
        if ($titulo === '') {
            return 'Informe o título do documento.';
        }
        $agora = date('Y-m-d H:i:s');
        $DB->insert(self::getTable(), [
            'plugin_pessoasplus_colaboradores_id' => (int) $c->getID(),
            'plugin_pessoasplus_admissao_tipos_id' => 0, 'titulo' => $titulo,
            'situacao' => 'pendente', 'arquivo' => '', 'nome_original' => '', 'sha256' => '', 'tamanho' => 0,
            'enviado_em' => null, 'users_id_envio' => 0, 'avaliado_em' => null, 'users_id_avaliacao' => 0, 'motivo' => '',
            'date_creation' => $agora, 'date_mod' => $agora,
        ]);
        Evento::registrar('fichario', 'admissao_pendencias', $c, (int) $c->getID(), ['novas' => 1, 'avulso' => $titulo]);

        return '';
    }

    // ----------------------------------------------------------------- envio

    /**
     * Recebe um arquivo ($_FILES[...]) para a pendencia. Devolve '' ou a mensagem de erro.
     * Valida tamanho, extensao e conteudo (imagem decodificavel / cabecalho %PDF). Guarda fora
     * do webroot com nome proprio, SHA-256 e situacao 'enviado'.
     */
    public function enviar(array $arquivo, int $uid): string
    {
        /** @var DBmysql $DB */
        global $DB;

        if (!in_array((string) $this->fields['situacao'], ['pendente'], true)) {
            return 'Esta pendência não aceita envio agora.';
        }
        $erro = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro === UPLOAD_ERR_NO_FILE) {
            return 'Escolha um arquivo.';
        }
        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            return 'Arquivo maior que o limite do servidor.';
        }
        if ($erro !== UPLOAD_ERR_OK) {
            return 'Falha no envio. Tente de novo.';
        }
        $tamanho = (int) ($arquivo['size'] ?? 0);
        if ($tamanho <= 0 || $tamanho > self::LIMITE_BYTES) {
            return 'O arquivo precisa ter até ' . (self::LIMITE_BYTES / 1024 / 1024) . ' MB.';
        }
        $nome = (string) ($arquivo['name'] ?? '');
        $ext  = strtolower((string) pathinfo($nome, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, self::EXTENSOES, true)) {
            return 'Formato não aceito. Envie PDF, JPG, PNG ou WEBP.';
        }
        $tmp = (string) $arquivo['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return 'Falha no envio. Tente de novo.';
        }
        if ($ext === 'pdf') {
            $cab = (string) file_get_contents($tmp, false, null, 0, 5);
            if ($cab !== '%PDF-') {
                return 'O arquivo não é um PDF válido.';
            }
        } elseif (function_exists('getimagesize') && @getimagesize($tmp) === false) {
            return 'A imagem não pôde ser lida.';
        }

        $fichaId = (int) $this->fields['plugin_pessoasplus_colaboradores_id'];
        $pasta   = GLPI_PLUGIN_DOC_DIR . '/' . self::PASTA . '/' . $fichaId;
        if (!is_dir($pasta) && !@mkdir($pasta, 0750, true)) {
            return 'Não foi possível preparar a pasta de documentos.';
        }
        $relativo = self::PASTA . '/' . $fichaId . '/' . (int) $this->getID() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $destino  = GLPI_PLUGIN_DOC_DIR . '/' . $relativo;
        if (!@move_uploaded_file($tmp, $destino)) {
            return 'Não foi possível guardar o arquivo.';
        }
        @chmod($destino, 0640);
        $agora = date('Y-m-d H:i:s');
        $DB->update(self::getTable(), [
            'situacao' => 'enviado', 'arquivo' => $relativo, 'nome_original' => mb_substr($nome, 0, 255),
            'sha256' => hash_file('sha256', $destino), 'tamanho' => $tamanho, 'enviado_em' => $agora, 'users_id_envio' => $uid,
            'avaliado_em' => null, 'users_id_avaliacao' => 0, 'motivo' => '', 'date_mod' => $agora,
        ], ['id' => (int) $this->getID()]);
        $this->getFromDB((int) $this->getID());
        $c = new Colaborador();
        if ($c->getFromDB($fichaId)) {
            Evento::registrar('fichario', 'admissao_enviado', $c, $fichaId, ['tipo' => $this->tipoNome(), 'por' => $uid === (int) $c->fields['users_id'] ? 'colaborador' : 'rh']);
        }

        return '';
    }

    // ----------------------------------------------------------------- avaliacao (RH)

    /** aceitar | recusar (motivo obrigatorio; nasce nova pendencia) | dispensar | reabrir (dispensado -> pendente). */
    public function avaliar(string $acao, int $uid, string $motivo = ''): string
    {
        /** @var DBmysql $DB */
        global $DB;

        $sit   = (string) $this->fields['situacao'];
        $agora = date('Y-m-d H:i:s');
        $motivo = mb_substr(trim($motivo), 0, 500);
        $campos = ['avaliado_em' => $agora, 'users_id_avaliacao' => $uid, 'date_mod' => $agora];
        switch ($acao) {
            case 'aceitar':
                if ($sit !== 'enviado') {
                    return 'Só um documento enviado pode ser aceito.';
                }
                $campos['situacao'] = 'aceito';
                $campos['motivo']   = $motivo;
                break;
            case 'recusar':
                if ($sit !== 'enviado') {
                    return 'Só um documento enviado pode ser recusado.';
                }
                if ($motivo === '') {
                    return 'Informe o motivo da recusa: o colaborador vai lê-lo.';
                }
                $campos['situacao'] = 'recusado';
                $campos['motivo']   = $motivo;
                break;
            case 'dispensar':
                if (!in_array($sit, ['pendente', 'enviado', 'recusado'], true)) {
                    return 'Esta linha não pode ser dispensada.';
                }
                $campos['situacao'] = 'dispensado';
                $campos['motivo']   = $motivo;
                break;
            case 'reabrir':
                if ($sit !== 'dispensado') {
                    return 'Só um documento dispensado pode voltar a pendente.';
                }
                $campos['situacao'] = 'pendente';
                $campos['motivo']   = '';
                $campos['avaliado_em'] = null;
                $campos['users_id_avaliacao'] = 0;
                break;
            default:
                return 'Ação desconhecida.';
        }
        $DB->update(self::getTable(), $campos, ['id' => (int) $this->getID()]);
        $fichaId = (int) $this->fields['plugin_pessoasplus_colaboradores_id'];
        if ($acao === 'recusar') {
            // D-90: o recusado fica; o reenvio e uma pendencia nova do mesmo tipo.
            $DB->insert(self::getTable(), [
                'plugin_pessoasplus_colaboradores_id' => $fichaId,
                'plugin_pessoasplus_admissao_tipos_id' => (int) $this->fields['plugin_pessoasplus_admissao_tipos_id'],
                'titulo' => (string) ($this->fields['titulo'] ?? ''),
                'situacao' => 'pendente', 'arquivo' => '', 'nome_original' => '', 'sha256' => '', 'tamanho' => 0,
                'enviado_em' => null, 'users_id_envio' => 0, 'avaliado_em' => null, 'users_id_avaliacao' => 0, 'motivo' => '',
                'date_creation' => $agora, 'date_mod' => $agora,
            ]);
        }
        $c = new Colaborador();
        if ($c->getFromDB($fichaId)) {
            Evento::registrar('fichario', 'admissao_' . $campos['situacao'], $c, $fichaId, ['tipo' => $this->tipoNome(), 'motivo' => $motivo]);
        }
        $this->getFromDB((int) $this->getID());

        return '';
    }

    // ----------------------------------------------------------------- consultas

    public function tipoNome(): string
    {
        if ((string) ($this->fields['titulo'] ?? '') !== '') {
            return (string) $this->fields['titulo'];
        }
        $t = new AdmissaoTipo();

        return $t->getFromDB((int) $this->fields['plugin_pessoasplus_admissao_tipos_id']) ? (string) $t->fields['nome'] : 'Documento';
    }

    /** Linhas da ficha, prontas para a tela (ordem: tipo, depois cronologia). */
    public static function doColaborador(int $fichaId): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $tipos = [];
        foreach (AdmissaoTipo::todos() as $t) {
            $tipos[$t['id']] = $t;
        }
        $saida = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['plugin_pessoasplus_colaboradores_id' => $fichaId], 'ORDER' => ['plugin_pessoasplus_admissao_tipos_id', 'id']]) as $d) {
            $saida[] = self::paraTela($d, $tipos[(int) $d['plugin_pessoasplus_admissao_tipos_id']] ?? null);
        }
        usort($saida, static fn ($a, $b) => ($a['ordem'] <=> $b['ordem']) ?: strcmp($a['tipo'], $b['tipo']) ?: ($a['id'] <=> $b['id']));

        return $saida;
    }

    /** Pendencias (pendente/recusado-com-reenvio/enviado) do usuario logado, pela ficha ligada. */
    public static function doUsuario(int $uid): array
    {
        $c = Colaborador::porUsuario($uid);

        return $c === null ? [] : self::doColaborador((int) $c->getID());
    }

    /** Resumo por ficha: total, aceitos, pendentes (pendente), aguardando (enviado), recusados. */
    public static function resumo(int $fichaId): array
    {
        $r = ['total' => 0, 'aceitos' => 0, 'pendentes' => 0, 'aguardando' => 0, 'recusados' => 0, 'dispensados' => 0, 'obrigatorios_abertos' => 0];
        foreach (self::doColaborador($fichaId) as $d) {
            $r['total']++;
            switch ($d['situacao']) {
                case 'aceito':     $r['aceitos']++; break;
                case 'pendente':   $r['pendentes']++; if ($d['obrigatorio']) { $r['obrigatorios_abertos']++; } break;
                case 'enviado':    $r['aguardando']++; break;
                case 'recusado':   $r['recusados']++; break;
                case 'dispensado': $r['dispensados']++; break;
            }
        }

        return $r;
    }

    /** Fichas com documento enviado aguardando conferencia (Painel do RH). */
    public static function aguardandoConferencia(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $porFicha = [];
        foreach ($DB->request(['SELECT' => ['plugin_pessoasplus_colaboradores_id', 'enviado_em'], 'FROM' => self::getTable(), 'WHERE' => ['situacao' => 'enviado']]) as $d) {
            $f = (int) $d['plugin_pessoasplus_colaboradores_id'];
            $porFicha[$f] ??= ['ficha' => $f, 'total' => 0, 'desde' => (string) $d['enviado_em']];
            $porFicha[$f]['total']++;
            if ((string) $d['enviado_em'] < $porFicha[$f]['desde']) {
                $porFicha[$f]['desde'] = (string) $d['enviado_em'];
            }
        }
        $saida = [];
        foreach ($porFicha as $p) {
            $c = new Colaborador();
            if ($c->getFromDB($p['ficha']) && (int) $c->fields['is_deleted'] === 0) {
                $saida[] = $p + ['nome' => (string) $c->fields['nome']];
            }
        }

        return $saida;
    }

    public static function paraTela(array $d, ?array $tipo): array
    {
        $sit = (string) $d['situacao'];
        $def = self::SITUACOES[$sit] ?? [$sit, 'neutro', 'ti ti-file'];

        return [
            'id'            => (int) $d['id'],
            'ficha_id'      => (int) $d['plugin_pessoasplus_colaboradores_id'],
            'tipo_id'       => (int) $d['plugin_pessoasplus_admissao_tipos_id'],
            'tipo'          => (string) ($d['titulo'] ?? '') !== '' ? (string) $d['titulo'] : ($tipo['nome'] ?? 'Documento'),
            'descricao'     => (string) ($d['titulo'] ?? '') !== '' ? '' : ($tipo['descricao'] ?? ''),
            'obrigatorio'   => (string) ($d['titulo'] ?? '') !== '' ? false : ($tipo['obrigatorio'] ?? true),
            'avulso'        => (string) ($d['titulo'] ?? '') !== '',
            'ordem'         => (string) ($d['titulo'] ?? '') !== '' ? 9999 : ($tipo['ordem'] ?? 0),
            'situacao'      => $sit,
            'situacao_rotulo' => $def[0],
            'tom'           => $def[1],
            'icone'         => $def[2],
            'tem_arquivo'   => (string) ($d['arquivo'] ?? '') !== '',
            'nome_original' => (string) ($d['nome_original'] ?? ''),
            'tamanho'       => (int) ($d['tamanho'] ?? 0),
            'tamanho_legivel' => self::tamanho((int) ($d['tamanho'] ?? 0)),
            'sha256'        => (string) ($d['sha256'] ?? ''),
            'enviado_em'    => $d['enviado_em'] ? date('d/m/Y H:i', strtotime((string) $d['enviado_em'])) : '',
            'avaliado_em'   => $d['avaliado_em'] ? date('d/m/Y H:i', strtotime((string) $d['avaliado_em'])) : '',
            'avaliado_por'  => (int) ($d['users_id_avaliacao'] ?? 0) > 0 ? (string) getUserName((int) $d['users_id_avaliacao']) : '',
            'motivo'        => (string) ($d['motivo'] ?? ''),
            'aceita_envio'  => $sit === 'pendente',
            'url_arquivo'   => (string) ($d['arquivo'] ?? '') !== '' ? \Html::getPrefixedUrl('/plugins/pessoasplus/front/admissao_arquivo.php') . '?id=' . (int) $d['id'] : '',
        ];
    }

    public static function tamanho(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }
        if ($bytes < 1024 * 1024) {
            return (string) (int) round($bytes / 1024) . ' KB';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', '') . ' MB';
    }

    /** Apaga os arquivos da ficha (purga, BM.8-1c). Devolve quantos arquivos sairam. */
    public static function purgarDaFicha(int $fichaId): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $n = 0;
        foreach ($DB->request(['SELECT' => ['arquivo'], 'FROM' => self::getTable(), 'WHERE' => ['plugin_pessoasplus_colaboradores_id' => $fichaId]]) as $d) {
            $a = (string) $d['arquivo'];
            if ($a !== '' && !str_contains($a, '..') && is_file(GLPI_PLUGIN_DOC_DIR . '/' . $a)) {
                @unlink(GLPI_PLUGIN_DOC_DIR . '/' . $a);
                $n++;
            }
        }
        $DB->delete(self::getTable(), ['plugin_pessoasplus_colaboradores_id' => $fichaId]);
        $pasta = GLPI_PLUGIN_DOC_DIR . '/' . self::PASTA . '/' . $fichaId;
        if (is_dir($pasta)) {
            @rmdir($pasta);
        }

        return $n;
    }

    /** Quem pode ver o arquivo: quem gerencia o Fichario, ou o proprio colaborador da ficha. */
    public function podeVer(int $uid): bool
    {
        if (Permissoes::gerencia('fichario')) {
            return true;
        }
        $c = new Colaborador();

        return $c->getFromDB((int) $this->fields['plugin_pessoasplus_colaboradores_id']) && (int) $c->fields['users_id'] === $uid && $uid > 0;
    }
}
