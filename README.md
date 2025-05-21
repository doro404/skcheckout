# Sistema de Pagamento PIX com Mercado Pago

Este é um sistema de pagamento que integra o PIX do Mercado Pago, permitindo receber pagamentos instantâneos de forma segura e eficiente.

## Principais Recursos

- Pagamentos instantâneos via PIX
- Integração com Mercado Pago
- Verificação automática do status do pagamento
- Interface amigável e responsiva
- Segurança robusta
- Experiência do usuário otimizada

## Requisitos

- PHP 7.4 ou superior
- Composer
- Conta no Mercado Pago
- Access Token do Mercado Pago

## Instalação

1. Clone este repositório:
```bash
git clone https://seu-repositorio/pix-payment-system.git
cd pix-payment-system
```

2. Instale as dependências via Composer:
```bash
composer install
```

3. Configure o Access Token do Mercado Pago:
   - Abra os arquivos `process_payment.php` e `check_payment.php`
   - Substitua `YOUR_ACCESS_TOKEN` pelo seu Access Token do Mercado Pago

## Configuração

1. Acesse sua conta do Mercado Pago
2. Vá até as configurações de desenvolvedor
3. Gere um Access Token de produção
4. Configure o webhook para receber notificações de pagamento (opcional)

## Uso

1. Acesse o sistema através do navegador
2. Preencha os dados do pagamento
3. Gere o QR Code PIX
4. Realize o pagamento usando qualquer aplicativo de banco
5. Aguarde a confirmação automática

## Segurança

- Todas as transações são processadas pelo Mercado Pago
- Os dados sensíveis não são armazenados localmente
- As comunicações são realizadas via HTTPS
- O sistema segue as melhores práticas de segurança

## Suporte

Para dúvidas ou problemas, abra uma issue no repositório ou entre em contato através do email: seu-email@exemplo.com 