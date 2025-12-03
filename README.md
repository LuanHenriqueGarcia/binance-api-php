## Requisitos
- PHP 8.1+ com cURL habilitado
- Composer (opcional, só para atualizar o autoload e ferramentas de dev)

## Configuração
1. Clone ou baixe o projeto.
2. Crie um arquivo `.env` na raiz (ou edite o existente). Para endpoints públicos, pode deixar as chaves em branco.
3. (Opcional) Rode `composer install` para gerar `vendor/autoload.php` e instalar ferramentas de dev.

## Como rodar localmente
- Usando o servidor embutido do PHP:
  ```
  php -S localhost:8000 -t .
  ```
  A aplicação responde em `http://localhost:8000`.
- Em Apache, habilite `mod_rewrite` e use o `.htaccess` já incluso.

## Como usar no Insomnia
1) Abra o Insomnia → Application → Import/Export → Import Data → From File.  
2) Selecione `insomnia.json` na raiz do projeto (já contém todas as rotas).  
3) No ambiente do Insomnia:
   - `base_url`: ex. http://localhost:8000
   - `api_key` / `secret_key`: suas chaves da Binance (ou deixe vazio para endpoints públicos).
   - `BINANCE_BASE_URL` (opcional): override da base (ex.: https://testnet.binance.vision). Ou use `BINANCE_TESTNET=true`.
   - `BINANCE_SSL_VERIFY` (opcional): `false` para ignorar certificado em ambiente local (não use em produção).
4) Rode o servidor local: `php -S localhost:8000 -t .`  
5) Teste as rotas (General, Market, Account, Trading). Endpoints de conta/trading exigem chaves.

## Endpoints rápidos
- Ping: `GET /api/general/ping`
- Hora do servidor: `GET /api/general/time`
- Info de câmbio: `GET /api/general/exchange-info?symbol=BTCUSDT`
- Preço atual: `GET /api/market/ticker?symbol=BTCUSDT`
- Order book: `GET /api/market/order-book?symbol=BTCUSDT&limit=100`
- Trades: `GET /api/market/trades?symbol=BTCUSDT&limit=500`

Endpoints autenticados (usam chaves do `.env` ou params `api_key`/`secret_key`):
- Info da conta: `GET /api/account/info`
- Ordens abertas: `GET /api/account/open-orders?symbol=BTCUSDT`
- Histórico de ordens: `GET /api/account/order-history?symbol=BTCUSDT&limit=500`
- Saldo de um ativo: `GET /api/account/balance?asset=USDT`

Trading:
```bash
# Criar ordem LIMIT
curl -X POST http://localhost:8000/api/trading/create-order ^
-H "Content-Type: application/json" ^
-d "{\"api_key\":\"...\",\"secret_key\":\"...\",\"symbol\":\"BTCUSDT\",\"side\":\"BUY\",\"type\":\"LIMIT\",\"quantity\":\"0.001\",\"price\":\"42000\",\"timeInForce\":\"GTC\"}"

# Criar ordem MARKET com quoteOrderQty
curl -X POST http://localhost:8000/api/trading/create-order ^
-H "Content-Type: application/json" ^
-d "{\"api_key\":\"...\",\"secret_key\":\"...\",\"symbol\":\"BTCUSDT\",\"side\":\"BUY\",\"type\":\"MARKET\",\"quoteOrderQty\":\"50\"}"

# Cancelar ordem
curl -X DELETE http://localhost:8000/api/trading/cancel-order ^
-H "Content-Type: application/json" ^
-d "{\"api_key\":\"...\",\"secret_key\":\"...\",\"symbol\":\"BTCUSDT\",\"orderId\":\"123456\"}"
```

## Campos extras em ordens
- MARKET: envie `quantity` **ou** `quoteOrderQty`.
- LIMIT/STOP_LIMIT/TAKE_PROFIT_LIMIT/LIMIT_MAKER: inclua `price` e `timeInForce` (default GTC).
- STOP/TAKE (com ou sem LIMIT): inclua `stopPrice`.

## Testes e qualidade
- Rodar testes (PHPUnit): `vendor/bin/phpunit` (controllers e router)
- Análise estática (PHPStan nível 6): `vendor/bin/phpstan analyse`
