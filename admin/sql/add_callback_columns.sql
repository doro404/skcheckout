-- Adiciona colunas na tabela merchants
ALTER TABLE merchants
ADD COLUMN webhook_url VARCHAR(255) DEFAULT NULL COMMENT 'URL padrão de callback do comerciante',
ADD COLUMN use_dynamic_callback TINYINT(1) DEFAULT 0 COMMENT 'Se deve usar callback dinâmico baseado no Referer';

-- Adiciona colunas na tabela products
ALTER TABLE products
ADD COLUMN product_callback_url VARCHAR(255) DEFAULT NULL COMMENT 'URL de callback específica do produto',
ADD COLUMN use_custom_callback TINYINT(1) DEFAULT 0 COMMENT 'Se deve usar callback próprio do produto';

-- Adiciona índices para melhorar performance
ALTER TABLE merchants
ADD INDEX idx_callback_config (use_dynamic_callback, webhook_url);

ALTER TABLE products
ADD INDEX idx_product_callback (use_custom_callback, product_callback_url);

-- Remove trigger se já existir
DROP TRIGGER IF EXISTS before_merchant_update;

-- Cria trigger para limpar webhook_url quando use_dynamic_callback for ativado
DELIMITER //
CREATE TRIGGER before_merchant_update 
BEFORE UPDATE ON merchants
FOR EACH ROW
BEGIN
    IF NEW.use_dynamic_callback = 1 THEN
        SET NEW.webhook_url = NULL;
    END IF;
END//
DELIMITER ;

-- Limpa webhook_url em registros existentes onde use_dynamic_callback está ativo
UPDATE merchants SET webhook_url = NULL WHERE use_dynamic_callback = 1; 