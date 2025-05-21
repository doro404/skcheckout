-- Adiciona novos campos na tabela merchants
ALTER TABLE merchants
ADD COLUMN default_callback_url VARCHAR(255) DEFAULT NULL,
ADD COLUMN use_dynamic_callback TINYINT(1) DEFAULT 0;

-- Atualiza registros existentes para usar callback padrão
UPDATE merchants
SET default_callback_url = webhook_url
WHERE default_callback_url IS NULL AND webhook_url IS NOT NULL; 