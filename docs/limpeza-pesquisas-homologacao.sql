-- Pessoas+ — limpeza do MOVIMENTO das pesquisas (tipo = 'pesquisa') na HOMOLOGAÇÃO.
-- Só uso em homologação. Não mexe na Ouvidoria (tipo = 'ouvidoria'), nos formulários,
-- nas perguntas, no mural, nos comunicados nem na configuração.
--
-- Antes de rodar, faça a cópia de segurança (comando no roteiro).
-- Rode com:  sudo mysql glpidb < limpeza-pesquisas-homologacao.sql

START TRANSACTION;

-- Quantos vão sair (só informativo)
SELECT 'respostas de pesquisa' AS o_que, COUNT(*) AS qtd
  FROM glpi_plugin_pessoasplus_respostas r
  JOIN glpi_plugin_pessoasplus_formularios f ON f.id = r.plugin_pessoasplus_formularios_id
 WHERE f.tipo = 'pesquisa'
UNION ALL
SELECT 'participacoes de pesquisa', COUNT(*)
  FROM glpi_plugin_pessoasplus_participacoes p
  JOIN glpi_plugin_pessoasplus_formularios f ON f.id = p.plugin_pessoasplus_formularios_id
 WHERE f.tipo = 'pesquisa'
UNION ALL
SELECT 'respostas da Ouvidoria (ficam)', COUNT(*)
  FROM glpi_plugin_pessoasplus_respostas r
  JOIN glpi_plugin_pessoasplus_formularios f ON f.id = r.plugin_pessoasplus_formularios_id
 WHERE f.tipo = 'ouvidoria';

-- 1. Itens das respostas de pesquisa
DELETE i FROM glpi_plugin_pessoasplus_respostas_itens i
  JOIN glpi_plugin_pessoasplus_respostas r ON r.id = i.plugin_pessoasplus_respostas_id
  JOIN glpi_plugin_pessoasplus_formularios f ON f.id = r.plugin_pessoasplus_formularios_id
 WHERE f.tipo = 'pesquisa';

-- 2. Respostas de pesquisa
DELETE r FROM glpi_plugin_pessoasplus_respostas r
  JOIN glpi_plugin_pessoasplus_formularios f ON f.id = r.plugin_pessoasplus_formularios_id
 WHERE f.tipo = 'pesquisa';

-- 3. Participações (quem respondeu) de pesquisa
DELETE p FROM glpi_plugin_pessoasplus_participacoes p
  JOIN glpi_plugin_pessoasplus_formularios f ON f.id = p.plugin_pessoasplus_formularios_id
 WHERE f.tipo = 'pesquisa';

-- 4. Contadores diários da página pública (hash sem IP; não dá para separar por
--    formulário, então zera tudo — só reinicia o limite do dia, inclusive da Ouvidoria)
DELETE FROM glpi_plugin_pessoasplus_limites;

-- 5. Itens órfãos, se sobrou algum de testes antigos
DELETE i FROM glpi_plugin_pessoasplus_respostas_itens i
  LEFT JOIN glpi_plugin_pessoasplus_respostas r ON r.id = i.plugin_pessoasplus_respostas_id
 WHERE r.id IS NULL;

COMMIT;

-- Conferência: as duas primeiras linhas devem dar 0
SELECT 'respostas de pesquisa' AS o_que, COUNT(*) AS qtd
  FROM glpi_plugin_pessoasplus_respostas r
  JOIN glpi_plugin_pessoasplus_formularios f ON f.id = r.plugin_pessoasplus_formularios_id
 WHERE f.tipo = 'pesquisa'
UNION ALL
SELECT 'participacoes de pesquisa', COUNT(*)
  FROM glpi_plugin_pessoasplus_participacoes p
  JOIN glpi_plugin_pessoasplus_formularios f ON f.id = p.plugin_pessoasplus_formularios_id
 WHERE f.tipo = 'pesquisa'
UNION ALL
SELECT 'formularios de pesquisa (mantidos)', COUNT(*) FROM glpi_plugin_pessoasplus_formularios WHERE tipo = 'pesquisa';

-- OPCIONAL — só se quiser apagar também os FORMULÁRIOS de pesquisa de teste
-- (não a Ouvidoria). Descomente, rode de novo, e refaça a pesquisa real do zero.
-- DELETE q FROM glpi_plugin_pessoasplus_perguntas q
--   JOIN glpi_plugin_pessoasplus_formularios f ON f.id = q.plugin_pessoasplus_formularios_id
--  WHERE f.tipo = 'pesquisa';
-- DELETE FROM glpi_plugin_pessoasplus_formularios WHERE tipo = 'pesquisa';
