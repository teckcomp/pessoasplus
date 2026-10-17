<?php

/**
 * Pessoas+ - tipos de documento de admissao (BM.8-1a, D-89).
 *
 * PESSOASPLUS_BUILD_BM8_1
 *
 * Cada tipo diz a quais vinculos se aplica (lista separada por virgula ou '*' para todos),
 * se e obrigatorio e a ordem. Configurado pelo RH (gerencia Fichario). Nunca um tipo de saude (D-66).
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;

final class AdmissaoTipo extends CommonDBTM
{
    public static $rightname = 'pessoasplus_fichario';

    public $dohistory = false;

    /** Semente: nome, descricao, vinculos, obrigatorio. */
    public const SEMENTE = [
        ['Documento de identidade (RG ou CNH)', 'Frente e verso, legível.', '*', 1],
        ['CPF', 'Pode ser o próprio RG/CNH quando constar o número.', '*', 1],
        ['Comprovante de residência', 'Conta de água, luz ou telefone dos últimos 90 dias.', '*', 1],
        ['Foto 3×4', 'Fundo claro, recente.', '*', 0],
        ['Carteira de trabalho digital', 'Print ou PDF do aplicativo CTPS Digital.', 'clt,aprendiz,temporario', 1],
        ['Título de eleitor', '', 'clt,aprendiz,temporario', 0],
        ['Certificado de reservista', 'Quando aplicável.', 'clt,aprendiz,temporario', 0],
        ['Termo de compromisso de estágio', 'Assinado pela instituição de ensino.', 'estagio', 1],
        ['Comprovante de matrícula', 'Da instituição de ensino, do semestre atual.', 'estagio,aprendiz', 1],
        ['Contrato de prestação de serviços', 'Assinado.', 'pj', 1],
        ['Cartão CNPJ', 'Comprovante de inscrição.', 'pj', 1],
    ];

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_pessoasplus_admissao_tipos';
    }

    public static function getTypeName($nb = 0)
    {
        return $nb > 1 ? 'Tipos de documento de admissão' : 'Tipo de documento de admissão';
    }

    public static function canView(): bool
    {
        return Permissoes::le('fichario');
    }

    public static function canCreate(): bool
    {
        return Permissoes::gerencia('fichario');
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
        return Permissoes::gerencia('fichario');
    }

    private function normalizar(array $input): array|false
    {
        if (array_key_exists('nome', $input)) {
            $input['nome'] = mb_substr(trim((string) $input['nome']), 0, 150);
            if ($input['nome'] === '') {
                \Session::addMessageAfterRedirect('Informe o nome do documento.', false, ERROR);

                return false;
            }
        }
        if (array_key_exists('descricao', $input)) {
            $input['descricao'] = mb_substr(trim((string) $input['descricao']), 0, 500);
        }
        if (array_key_exists('vinculos', $input)) {
            $input['vinculos'] = self::normalizarVinculos($input['vinculos']);
        }
        foreach (['obrigatorio', 'is_active'] as $c) {
            if (array_key_exists($c, $input)) {
                $input[$c] = (int) (bool) $input[$c];
            }
        }
        if (array_key_exists('ordem', $input)) {
            $input['ordem'] = max(0, (int) $input['ordem']);
        }

        return $input;
    }

    /** Aceita array de chaves ou string "a,b"; '*' quando vazio ou contem '*'. */
    public static function normalizarVinculos(mixed $v): string
    {
        $lista = is_array($v) ? $v : explode(',', (string) $v);
        $ok = [];
        foreach ($lista as $x) {
            $x = trim((string) $x);
            if ($x === '*') {
                return '*';
            }
            if (isset(Colaborador::VINCULOS[$x])) {
                $ok[$x] = $x;
            }
        }

        return $ok === [] ? '*' : implode(',', $ok);
    }

    public function prepareInputForAdd($input)
    {
        $input = $this->normalizar($input);
        if ($input === false) {
            return false;
        }
        $input['nome']        = $input['nome'] ?? '';
        $input['vinculos']    = $input['vinculos'] ?? '*';
        $input['obrigatorio'] = $input['obrigatorio'] ?? 1;
        $input['is_active']   = $input['is_active'] ?? 1;
        $input['ordem']       = $input['ordem'] ?? 0;

        return $input;
    }

    public function prepareInputForUpdate($input)
    {
        return $this->normalizar($input);
    }

    /** Todos os tipos, ativos primeiro, na ordem. */
    public static function todos(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        foreach ($DB->request(['FROM' => self::getTable(), 'ORDER' => ['is_active DESC', 'ordem', 'nome']]) as $t) {
            $saida[] = self::paraTela($t);
        }

        return $saida;
    }

    /** Tipos ativos que se aplicam ao vinculo. */
    public static function paraVinculo(string $vinculo): array
    {
        $saida = [];
        foreach (self::todos() as $t) {
            if ($t['ativo'] && self::aplica($t['vinculos'], $vinculo)) {
                $saida[] = $t;
            }
        }

        return $saida;
    }

    public static function aplica(string $vinculos, string $vinculo): bool
    {
        if ($vinculos === '*' || $vinculos === '') {
            return true;
        }

        return in_array($vinculo, array_map('trim', explode(',', $vinculos)), true);
    }

    public static function paraTela(array $t): array
    {
        $vinc = (string) ($t['vinculos'] ?? '*');
        $rotulos = [];
        if ($vinc !== '*') {
            foreach (explode(',', $vinc) as $v) {
                $rotulos[] = Colaborador::VINCULOS[trim($v)] ?? trim($v);
            }
        }

        return [
            'id'          => (int) $t['id'],
            'nome'        => (string) $t['nome'],
            'descricao'   => (string) ($t['descricao'] ?? ''),
            'vinculos'    => $vinc,
            'vinculos_lista' => $vinc === '*' ? [] : array_map('trim', explode(',', $vinc)),
            'vinculos_rotulo' => $vinc === '*' ? 'Todos os vínculos' : implode(', ', $rotulos),
            'obrigatorio' => (int) ($t['obrigatorio'] ?? 1) === 1,
            'ativo'       => (int) ($t['is_active'] ?? 1) === 1,
            'ordem'       => (int) ($t['ordem'] ?? 0),
        ];
    }

    /** Semente idempotente: so grava quando a tabela esta vazia. */
    public static function semear(): void
    {
        /** @var DBmysql $DB */
        global $DB;

        if (countElementsInTable(self::getTable()) > 0) {
            return;
        }
        $ordem = 0;
        foreach (self::SEMENTE as $s) {
            $DB->insert(self::getTable(), ['nome' => $s[0], 'descricao' => $s[1], 'vinculos' => $s[2], 'obrigatorio' => $s[3], 'is_active' => 1, 'ordem' => ++$ordem]);
        }
    }
}
