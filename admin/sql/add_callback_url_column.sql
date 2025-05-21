-- Adiciona a coluna callback_url na tabela transactions
ALTER TABLE transactions
ADD COLUMN callback_url VARCHAR(255) NULL DEFAULT NULL COMMENT 'URL de retorno após o pagamento';

-- Atualiza transações existentes para usar o callback padrão do comerciante
UPDATE transactions t
JOIN merchants m ON t.merchant_id = m.id
SET t.callback_url = m.default_callback_url
WHERE t.callback_url IS NULL; 