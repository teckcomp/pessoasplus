<?php

/**
 * Pessoas+ - bilhete de uso unico para responder pesquisa anonima pela pagina publica (BM.4-17, D-97).
 *
 * PESSOASPLUS_BUILD_BM4_17
 *
 * Regra de anonimato (auditavel no codigo):
 *  - o bilhete em claro so existe na URL (fragmento #b=..., que o navegador nao envia ao servidor)
 *    e no corpo do POST; no banco fica so o hash SHA-256, ligado a (formulario, usuario);
 *  - ao enviar, a MESMA transacao grava a resposta (users_id = 0, so o dia), a participacao
 *    (usuario, so o dia, sem chave para a resposta) e apaga o bilhete: nada sobra que ligue um ao outro;
 *  - bilhete nao usado expira em 24 h e e purgado na proxima emissao;
 *  - um bilhete por (formulario, usuario): emitir de novo substitui o anterior.
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use DBmysql;

final class Bilhete
{
    public const TABELA = 'glpi_plugin_pessoasplus_bilhetes';

    /** Validade de um bilhete nao usado, em segundos. */
    public const VALIDADE = 86400;

    /**
     * Emite um bilhete para o usuario responder o formulario pela pagina publica.
     * Devolve o bilhete em claro (32 hex), que NAO e gravado.
     */
    public static function emitir(int $formId, int $uid): string
    {
        /** @var DBmysql $DB */
        global $DB;

        self::purgarExpirados();
        $claro = bin2hex(random_bytes(16));
        $hash  = self::hash($claro);
        $agora = date('Y-m-d H:i:s');
        $DB->delete(self::TABELA, ['plugin_pessoasplus_formularios_id' => $formId, 'users_id' => $uid]);
        $DB->insert(self::TABELA, ['plugin_pessoasplus_formularios_id' => $formId, 'users_id' => $uid, 'hash' => $hash, 'criado_em' => $agora]);

        return $claro;
    }

    /**
     * Localiza um bilhete valido para o formulario. Devolve o users_id dono, ou 0 quando
     * nao existe, nao bate com o formulario ou expirou. NAO apaga: quem apaga e consumir().
     */
    public static function dono(string $claro, int $formId): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $claro = preg_replace('/[^a-f0-9]/', '', $claro);
        if (strlen($claro) !== 32) {
            return 0;
        }
        $limite = date('Y-m-d H:i:s', time() - self::VALIDADE);
        foreach ($DB->request(['FROM' => self::TABELA, 'WHERE' => ['hash' => self::hash($claro), 'plugin_pessoasplus_formularios_id' => $formId]]) as $b) {
            if ((string) $b['criado_em'] < $limite) {
                return 0;
            }

            return (int) $b['users_id'];
        }

        return 0;
    }

    /** Apaga o bilhete (chamar dentro da transacao do envio). */
    public static function consumir(int $formId, int $uid): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->delete(self::TABELA, ['plugin_pessoasplus_formularios_id' => $formId, 'users_id' => $uid]);
    }

    public static function purgarExpirados(): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $DB->delete(self::TABELA, ['criado_em' => ['<', date('Y-m-d H:i:s', time() - self::VALIDADE)]]);
    }

    public static function hash(string $claro): string
    {
        return hash('sha256', $claro);
    }

    /** Fragmento que vai na URL publica: a parte apos # nunca chega ao servidor nem ao access.log. */
    public static function fragmento(string $claro): string
    {
        return '#b=' . $claro;
    }
}
