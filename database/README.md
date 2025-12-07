# Atualização de Funções do Banco de Dados

Este diretório contém scripts SQL para criação ou atualização de funções do banco de dados.

## Função normalizarCnae

A função `normalizarCnae` foi atualizada para tratar corretamente valores NULL e vazios. Isso é essencial para evitar erros ao processar CNAEs para diferentes tipos de estabelecimentos (pessoa física e jurídica).

### Como aplicar a atualização

1. Conecte-se ao seu banco de dados MySQL ou MariaDB usando o comando `mysql` ou através do phpMyAdmin
2. Selecione o banco de dados correto com `USE nome_do_banco;`
3. Execute o conteúdo do arquivo `functions.sql` para atualizar a função:

```sql
SOURCE /caminho/para/functions.sql;
```

Ou copie e cole o conteúdo do arquivo diretamente no console SQL.

### Possíveis problemas

Se encontrar erros relacionados a permissões ao criar a função, verifique se o usuário do banco de dados tem o privilégio `CREATE ROUTINE`:

```sql
GRANT CREATE ROUTINE ON nome_do_banco.* TO 'seu_usuario'@'localhost';
FLUSH PRIVILEGES;
``` 