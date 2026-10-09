# Pessoas+

Plugin de RH para o GLPI 11.

Cobre o ciclo de vida do colaborador dentro do GLPI: comunicados e normativas com
ciência registrada, mural, ficha do colaborador, chegada e transferência,
checklists, férias e afastamentos, desligamento com nada consta, questionários e
registro funcional.

Nenhuma tabela nativa do GLPI é alterada. Tudo o que é do plugin vive em tabelas
com o prefixo `glpi_plugin_pessoasplus_`.

## Requisitos

- GLPI 11.0.6 ou superior, abaixo do 12.0
- PHP 8.2 ou superior

## Instalação

```bash
cd /var/www/html/glpi/plugins
unzip -o pessoasplus-<versao>.zip
chown -R www-data:www-data pessoasplus
sudo -u www-data php /var/www/html/glpi/bin/console plugin:install pessoasplus --allow-superuser
sudo -u www-data php /var/www/html/glpi/bin/console plugin:activate pessoasplus --allow-superuser
```

## Direitos

| Direito | Para que serve |
|---|---|
| `pessoasplus` | Acessar a área do Pessoas+ |
| `pessoasplus_config` | Configurar o plugin |

Os direitos nascem concedidos apenas aos perfis que podem ler e alterar a
configuração do GLPI. Nos demais perfis eles são criados em zero, de propósito:
cada perfil precisa ser liberado conscientemente.

## Estado

Em desenvolvimento. Este pacote corresponde ao bloco B0.1 (esqueleto
instalável), sem módulos de negócio.

## Licença

GPL-2.0-or-later. Veja o arquivo `LICENSE`.
