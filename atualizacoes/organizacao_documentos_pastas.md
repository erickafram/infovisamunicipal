# Sistema de Organização de Documentos em Pastas

## Visão Geral

Este documento descreve a implementação do sistema de organização de documentos e arquivos em pastas dentro do módulo de processos do sistema Visa Municipal.

## Funcionalidades Implementadas

### 1. Estrutura de Pastas
- **Pastas como Abas**: As pastas funcionam como abas dentro da seção "Lista de Documentos/Arquivos"
- **Aba "Documentos não organizados"**: Mostra todos os documentos que não estão em nenhuma pasta
- **Navegação por Abas**: Cada pasta é uma aba clicável que filtra os documentos

### 2. Gerenciamento de Pastas

#### Criação de Pastas
- **Localização**: Botão "Nova Pasta" no menu lateral junto com "Ações do Processo"
- **Campos**: Nome da pasta (obrigatório) e descrição (opcional)
- **Validação**: Nome da pasta é obrigatório e tem limite de 255 caracteres

#### Edição de Pastas
- **Acesso**: Ícone de edição que aparece ao passar o mouse sobre a aba da pasta
- **Funcionalidade**: Permite alterar nome e descrição da pasta
- **Interface**: Modal com formulário de edição

#### Exclusão de Pastas
- **Acesso**: Ícone de exclusão que aparece ao passar o mouse sobre a aba da pasta
- **Comportamento**: Quando uma pasta é excluída, os documentos voltam para "Documentos não organizados"
- **Confirmação**: Modal de confirmação antes da exclusão

### 3. Movimentação de Documentos

#### Mover para Pasta
- **Interface**: Dropdown "Mover" nos botões de ação de cada documento/arquivo
- **Opções**: Lista todas as pastas disponíveis do processo
- **Funcionalidade**: Move o item da localização atual para a pasta selecionada

#### Remover da Pasta
- **Interface**: Opção "Remover da pasta" no dropdown "Mover" (aparece apenas se o item estiver em uma pasta)
- **Funcionalidade**: Remove o item da pasta atual e move para "Documentos não organizados"

### 4. Indicadores Visuais

#### Contadores
- **Aba Geral**: Mostra a quantidade de documentos não organizados
- **Abas de Pastas**: Mostra a quantidade de itens em cada pasta
- **Atualização Automática**: Os contadores são atualizados automaticamente

#### Cores e Ícones
- **Aba Geral**: Cor azul com ícone de documento
- **Pastas**: Cor roxa com ícone de pasta
- **Estados**: Diferentes cores para aba ativa vs inativa

## Estrutura Técnica

### Banco de Dados

#### Tabela `pastas_documentos`
```sql
CREATE TABLE `pastas_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `processo_id` int NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text,
  `data_criacao` datetime NOT NULL,
  `criado_por` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pasta_processo` (`processo_id`),
  KEY `idx_pasta_usuario` (`criado_por`),
  CONSTRAINT `fk_pasta_processo` FOREIGN KEY (`processo_id`) REFERENCES `processos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Tabela `documentos_pastas`
```sql
CREATE TABLE `documentos_pastas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pasta_id` int NOT NULL,
  `tipo_item` enum('documento','arquivo') NOT NULL,
  `item_id` int NOT NULL,
  `data_adicionado` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_docpasta_pasta` (`pasta_id`),
  CONSTRAINT `fk_docpasta_pasta` FOREIGN KEY (`pasta_id`) REFERENCES `pastas_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Arquivos Modificados

#### `models/PastaDocumento.php`
Novo modelo com métodos para:
- `createPasta()`: Criar nova pasta
- `updatePasta()`: Editar pasta existente
- `deletePasta()`: Excluir pasta
- `getPastasByProcesso()`: Obter pastas de um processo
- `addItemToPasta()`: Adicionar item a uma pasta
- `removeItemFromPasta()`: Remover item de uma pasta
- `getItensByPasta()`: Obter itens de uma pasta
- `getItemPasta()`: Verificar se item está em alguma pasta

#### `views/Processo/documentos.php`
Principais modificações:
- Adicionado include do modelo `PastaDocumento`
- Implementado processamento de ações das pastas
- Adicionada lógica de filtragem por pasta ativa
- Implementadas abas de navegação
- Adicionados botões "Mover para Pasta" nos itens
- Adicionados modais para gerenciamento de pastas

## Fluxo de Funcionamento

### 1. Carregamento da Página
1. Sistema carrega todas as pastas do processo
2. Determina qual aba está ativa (padrão: "geral")
3. Filtra documentos/arquivos conforme aba ativa
4. Exibe contadores atualizados

### 2. Criação de Pasta
1. Usuário clica em "Nova Pasta" no menu lateral
2. Preenche formulário no modal
3. Sistema valida dados e cria pasta
4. Página recarrega com nova pasta nas abas

### 3. Movimentação de Documentos
1. Usuário clica no dropdown "Mover" de um item
2. Seleciona pasta de destino
3. Sistema move item e atualiza relacionamentos
4. Página recarrega com item na nova localização

### 4. Navegação por Abas
1. Usuário clica em uma aba (pasta ou "geral")
2. URL é atualizada com parâmetro `pasta`
3. Sistema filtra e exibe apenas itens da aba selecionada

## Benefícios

### Para os Usuários
- **Organização Melhorada**: Documentos podem ser agrupados logicamente
- **Navegação Facilitada**: Interface intuitiva com abas
- **Flexibilidade**: Fácil movimentação entre pastas
- **Visibilidade**: Contadores mostram quantidade de itens

### Para o Sistema
- **Escalabilidade**: Suporte a qualquer quantidade de pastas
- **Integridade**: Exclusão de pastas preserva documentos
- **Performance**: Consultas otimizadas com índices adequados
- **Manutenibilidade**: Código organizado e documentado

## Considerações Técnicas

### Compatibilidade
- **Funcionalidade Existente**: Todas as funcionalidades anteriores mantidas
- **Dados Existentes**: Documentos sem pasta aparecem em "Documentos não organizados"
- **Permissões**: Respeita níveis de acesso existentes

### Performance
- **Consultas Otimizadas**: Uso de JOINs eficientes
- **Índices**: Chaves estrangeiras com índices apropriados
- **Cache**: Contadores calculados em tempo real

### Segurança
- **Validação**: Dados validados no servidor
- **Autorização**: Apenas usuários autorizados podem gerenciar pastas
- **Integridade**: Constraints garantem consistência dos dados

## Instalação

### 1. Executar Script SQL
```bash
mysql -u usuario -p nome_banco < database/pastas_documentos.sql
```

### 2. Verificar Arquivos
- `models/PastaDocumento.php` (novo)
- `views/Processo/documentos.php` (modificado)
- `database/pastas_documentos.sql` (novo)

### 3. Testar Funcionalidades
1. Acessar página de documentos de um processo
2. Criar nova pasta pelo menu lateral
3. Mover documentos entre pastas
4. Verificar navegação por abas

## Versão
- **Data**: Janeiro 2025
- **Versão**: 1.0
- **Autor**: Sistema Visa Municipal
- **Status**: Implementado e Testado 