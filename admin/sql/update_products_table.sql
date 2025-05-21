-- Adiciona campos de callback na tabela products
ALTER TABLE products
ADD COLUMN product_callback_url VARCHAR(255) DEFAULT NULL,
ADD COLUMN use_custom_callback TINYINT(1) DEFAULT 0;

-- Adiciona índice para melhorar performance
ALTER TABLE products
ADD INDEX idx_callback (use_custom_callback, product_callback_url); 