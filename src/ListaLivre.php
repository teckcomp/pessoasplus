<?php

/**
 * Pessoas+ - base das listas livres do plugin (D-69).
 *
 * PESSOASPLUS_BUILD_BM1
 *
 * @copyright 2026 Teckcomp
 * @license   GPL-2.0-or-later
 */

namespace GlpiPlugin\Pessoasplus;

use CommonDBTM;
use DBmysql;

/**
 * Empregador, cargo e local de trabalho sao listas simples (id, name),
 * alimentadas inline pelo "+" das telas (ajax/lista.php). Quem gerencia o
 * Fichario pode incluir; ninguem apaga pela tela (so desativa).
 */
abstract class ListaLivre extends CommonDBTM
{
    public static $rightname = 'pessoasplus_fichario';

    /** Chave usada no ajax e nos templates: empregador, cargo, local. */
    abstract public static function chave(): string;

    /** Rotulo singular para mensagens. */
    abstract public static function rotulo(): string;

    /**
     * Itens ativos, ordenados por nome.
     *
     * @return array<int, array{id: int, nome: string}>
     */
    public static function ativos(): array
    {
        /** @var DBmysql $DB */
        global $DB;

        $saida = [];
        $it = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => static::getTable(),
            'WHERE'  => ['is_active' => 1],
            'ORDER'  => 'name',
        ]);
        foreach ($it as $linha) {
            $saida[] = ['id' => (int) $linha['id'], 'nome' => (string) $linha['name']];
        }

        return $saida;
    }

    /**
     * Devolve o id do item com esse nome (sem diferenciar maiusculas),
     * criando-o se nao existir. Nome vazio devolve 0.
     */
    public static function obterOuCriar(string $nome): int
    {
        /** @var DBmysql $DB */
        global $DB;

        $nome = trim(preg_replace('/\s+/', ' ', $nome) ?? '');
        if ($nome === '') {
            return 0;
        }
        $nome = mb_substr($nome, 0, 80);

        $it = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => static::getTable(),
            'WHERE'  => ['name' => $nome],
            'LIMIT'  => 1,
        ]);
        foreach ($it as $linha) {
            return (int) $linha['id'];
        }

        $item = new static();
        $id   = $item->add(['name' => $nome, 'is_active' => 1]);

        return $id === false ? 0 : (int) $id;
    }

    /** Nome pelo id, ou '' quando 0 ou inexistente. */
    public static function nome(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $item = new static();

        return $item->getFromDB($id) ? (string) $item->fields['name'] : '';
    }

    /**
     * @param string $chave empregador|cargo|local
     *
     * @return class-string<ListaLivre>|null
     */
    public static function classePorChave(string $chave): ?string
    {
        return match ($chave) {
            'empregador' => Empregador::class,
            'cargo'      => Cargo::class,
            'local'      => Local::class,
            default      => null,
        };
    }
}
