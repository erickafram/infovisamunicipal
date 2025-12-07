# Visualização de Documentos Organizados por Pastas - Visão da Empresa

## Resumo
Implementação da funcionalidade de visualização de documentos organizados por pastas na página `detalhes_processo_empresa.php`. Esta funcionalidade permite que as empresas vejam seus documentos organizados conforme as pastas criadas pela administração, mas **sem permissão para editar ou excluir** as pastas.

## Arquivos Modificados

### 1. `views/Processo/detalhes_processo_empresa.php`
- **Adicionado**: Inclusão do modelo `PastaDocumento`
- **Modificado**: Carregamento dos documentos com informações de pasta
- **Adicionado**: Sistema de abas para navegação entre pastas
- **Adicionado**: Filtros para exibir documentos por pasta
- **Adicionado**: Interface visual com abas estilizadas

## Funcionalidades Implementadas

### 1. **Abas de Navegação**
- **Documentos não organizados**: Aba azul que mostra documentos que não estão em nenhuma pasta
- **Pastas específicas**: Abas roxas que mostram documentos organizados em cada pasta
- **Contadores**: Cada aba mostra o número de documentos que contém
- **Navegação**: Clique nas abas para alternar entre diferentes visualizações

### 2. **Visualização Organizada**
- Os documentos são filtrados automaticamente com base na pasta ativa
- Documentos não organizados aparecem na aba "Documentos não organizados"
- Documentos organizados aparecem nas respectivas abas de pasta
- Descrição da pasta é exibida quando disponível

### 3. **Interface Responsiva**
- Abas com scroll horizontal em dispositivos móveis
- Layout adaptativo para diferentes tamanhos de tela
- Estilos consistentes com o resto da aplicação

### 4. **Permissões Restritas**
- **Somente visualização**: Empresas podem apenas ver a organização
- **Sem edição**: Não há botões para criar, editar ou excluir pastas
- **Sem movimentação**: Não há opções para mover documentos entre pastas

## Detalhes Técnicos

### Estrutura do Código
```php
// Carregamento das pastas
$pastas = $pastaDocumento->getPastasByProcesso($processoId);

// Adição de informação de pasta a cada item
foreach ($itens as &$item) {
    $pasta_item = $pastaDocumento->getItemPasta($item['tipo'], $item['id']);
    $item['pasta'] = $pasta_item;
}

// Filtragem baseada na pasta ativa
if ($pasta_ativa === 'geral') {
    $itens_exibir = array_filter($itens, function($item) {
        return !$item['pasta'];
    });
} else {
    $itens_exibir = $pastaDocumento->getItensByPasta($pasta_ativa);
}
```

### Navegação por URL
- `detalhes_processo_empresa.php?id=123&pasta=geral` - Documentos não organizados
- `detalhes_processo_empresa.php?id=123&pasta=5` - Documentos da pasta ID 5

### Estilos CSS
- `.pasta-tabs` - Container das abas com scroll horizontal
- Cores diferenciadas: azul para documentos não organizados, roxo para pastas
- Responsividade para dispositivos móveis

## Benefícios

### Para as Empresas
1. **Melhor organização**: Visualização clara dos documentos organizados por categoria
2. **Navegação intuitiva**: Sistema de abas familiar e fácil de usar
3. **Informações contextuais**: Descrições das pastas quando disponíveis
4. **Contadores visuais**: Fácil identificação de quantos documentos há em cada pasta

### Para o Sistema
1. **Consistência**: Mesma estrutura de dados da área administrativa
2. **Performance**: Filtragem eficiente dos documentos
3. **Manutenibilidade**: Código organizado e bem documentado
4. **Escalabilidade**: Suporte a qualquer número de pastas

## Comportamento

### Quando não há pastas
- Mostra apenas a aba "Documentos não organizados"
- Todos os documentos aparecem nesta aba única

### Quando há pastas vazias
- Pastas vazias aparecem nas abas com contador 0
- Mensagem informativa quando uma pasta vazia é selecionada

### Quando há documentos não organizados
- Aba "Documentos não organizados" sempre aparece
- Mostra documentos que não foram movidos para nenhuma pasta

## Integração com Sistema Existente

### Compatibilidade
- Mantém toda funcionalidade existente de visualização de documentos
- Não interfere com upload, exclusão ou correção de documentos
- Preserva todas as permissões e validações existentes

### Dependências
- Requer tabelas `pastas_documentos` e `documentos_pastas`
- Utiliza modelo `PastaDocumento` existente
- Compatível com estrutura atual de documentos e arquivos

## Navegação Sem Recarregamento (AJAX)

### Funcionalidade Implementada
- **Troca de pastas dinâmica**: Navegação entre pastas sem recarregar a página
- **Atualização em tempo real**: Contadores e conteúdo atualizados instantaneamente
- **Indicador de carregamento**: Feedback visual durante o carregamento dos documentos
- **Atualização da URL**: Histórico do navegador mantido para navegação com botões voltar/avançar

### Arquivos Adicionais
- `views/Processo/carregar_documentos_pasta.php` - Endpoint AJAX para carregar documentos por pasta

### Benefícios da Navegação AJAX
1. **Experiência do usuário**: Navegação mais fluida e responsiva
2. **Performance**: Carregamento apenas dos dados necessários
3. **Economia de recursos**: Menos requisições completas ao servidor
4. **Manutenção do estado**: Preserva posição de scroll e outros elementos da página

### Funcionalidades JavaScript
- `trocarPasta(pastaId)` - Função principal para trocar pastas
- `atualizarAbasAtivas(pastaId)` - Atualiza visual das abas
- `atualizarContadores(contadores)` - Atualiza números dos contadores
- `atualizarDescricaoPasta(pastaInfo)` - Atualiza descrição da pasta
- `atualizarListaDocumentos(itens)` - Atualiza lista de documentos
- `gerarHTMLItem(item)` - Gera HTML para cada documento

### Tratamento de Erros
- Validação de autenticação no endpoint AJAX
- Verificação de permissões de acesso ao processo
- Tratamento de erros de rede com mensagens amigáveis
- Fallback para mensagens de erro visuais

## Conclusão

A implementação permite que as empresas visualizem seus documentos de forma organizada, seguindo a estrutura de pastas definida pela administração, mantendo a simplicidade e clareza da interface enquanto oferece uma experiência mais organizada e profissional. A navegação AJAX adiciona fluidez e modernidade à interface, eliminando recarregamentos desnecessários da página. 