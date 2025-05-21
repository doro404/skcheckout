# Documentação do Sistema de Emails

## Fluxo de Envio de Emails

```mermaid
graph TD
    A[Início] --> B{Evento}
    B -->|Pagamento Aprovado| C[Webhook]
    B -->|Reenvio Manual| D[Resend Notification]
    
    C --> E[Busca Configurações SMTP]
    D --> E
    
    E --> F{Configurações OK?}
    F -->|Não| G[Erro: Configurações Incompletas]
    F -->|Sim| H[Configura PHPMailer]
    
    H --> I[Prepara Email Cliente]
    I --> J[Envia Email Cliente]
    J --> K[Prepara Email Comerciante]
    K --> L[Envia Email Comerciante]
    
    L --> M{Sucesso?}
    M -->|Sim| N[Resposta JSON Sucesso]
    M -->|Não| O[Resposta JSON Erro]
```

## Estrutura dos Emails

```mermaid
graph TD
    A[Email] --> B[Headers]
    A --> C[Corpo]
    
    B --> D[From]
    B --> E[To]
    B --> F[Reply-To]
    B --> G[Subject]
    B --> H[Anti-Spam]
    
    C --> I[HTML]
    I --> J[Estilo]
    I --> K[Conteúdo]
    
    K --> L[Detalhes da Compra]
    K --> M[Informações de Entrega]
    K --> N[Botão de Ação]
```

## Configurações SMTP

```mermaid
graph TD
    A[Configurações SMTP] --> B[Host]
    A --> C[Porta]
    A --> D[Usuário]
    A --> E[Senha]
    A --> F[From]
    
    G[Segurança] --> H[SSL/TLS]
    G --> I[Autenticação]
    G --> J[Verificação SSL]
```

## Tratamento de Erros

```mermaid
graph TD
    A[Erro] --> B{Tipo}
    B -->|Configuração| C[SMTP Incompleto]
    B -->|Envio| D[Falha no Envio]
    B -->|Dados| E[Dados Inválidos]
    
    C --> F[Log Error]
    D --> F
    E --> F
    
    F --> G[Resposta JSON]
    G --> H[Status 500]
```

## Headers Anti-Spam

```mermaid
graph TD
    A[Headers] --> B[X-MSMail-Priority]
    A --> C[X-Mailer]
    A --> D[X-Auto-Response-Suppress]
    A --> E[List-Unsubscribe]
    A --> F[Precedence]
    
    G[Meta Tags] --> H[x-spam-status]
    G --> I[x-spam-score]
```

## Formato da Resposta JSON

```mermaid
graph TD
    A[Resposta] --> B{Sucesso}
    B -->|Sim| C[Status 200]
    B -->|Não| D[Status 400/401/500]
    
    C --> E[success: true]
    C --> F[message: string]
    
    D --> G[success: false]
    D --> H[message: string]
```

## Logs do Sistema

```mermaid
graph TD
    A[Logs] --> B[Webhook]
    A --> C[Resend Notification]
    
    B --> D[Recebimento]
    B --> E[Processamento]
    B --> F[Envio]
    
    C --> G[Validação]
    C --> H[Envio]
    
    D --> I[error_log]
    E --> I
    F --> I
    G --> I
    H --> I
``` 