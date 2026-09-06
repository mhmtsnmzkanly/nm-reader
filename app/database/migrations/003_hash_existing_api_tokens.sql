-- Preserve existing client tokens while removing their plaintext form at rest.
UPDATE users
SET api_token = SHA2(api_token, 256)
WHERE api_token IS NOT NULL AND api_token <> '';
