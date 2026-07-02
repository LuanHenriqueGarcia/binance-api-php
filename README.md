# Traders API

API REST em PHP para integracao com Binance e Coinbase.

## Objetivo deste repositorio

- Facilitar a compra/venda de cripto moedas
- Monitoramento de mercado e analise de investimento

## Requisitos locais

- PHP 8.2+
- Composer
- Extensoes PHP: curl, openssl, json, bcmath, zip

## Setup rapido

```bash
cd api
composer install
composer test
```

Status esperado dos testes: GREEN.

## Subir API local

```bash
cd api
composer serve
```

API em http://localhost:8080/api

## Variaveis de ambiente

Use api/.env.example como base para api/.env.

Principais chaves:
- APP_ENV, APP_DEBUG
- BASIC_AUTH_USER, BASIC_AUTH_PASSWORD (obrigatorio em producao; senao a API retorna 503, salvo ALLOW_UNAUTHENTICATED=true)
- ALLOW_UNAUTHENTICATED (libera acesso sem Basic Auth em producao - use apenas atras de um gateway de auth)
- BINANCE_API_KEY, BINANCE_SECRET_KEY
- BINANCE_SSL_VERIFY, BINANCE_CA_BUNDLE
- COINBASE_API_KEY, COINBASE_API_SECRET, COINBASE_KEY_FILE
- COINBASE_SSL_VERIFY, COINBASE_CA_BUNDLE

## test manual

Com API no ar:

```bash
curl -i http://localhost:8080/api/health
curl -i http://localhost:8080/api/general/ping
curl -i "http://localhost:8080/api/market/ticker?symbol=BTCUSDT"
curl -i http://localhost:8080/api/coinbase/general/time
curl -i "http://localhost:8080/api/coinbase/market/product?product_id=BTC-USD"
```

## Nota sobre SSL em ambiente local

Em algumas maquinas Windows, a validacao de certificado pode falhar para chamadas externas.

Para desenvolvimento local, ajuste no api/.env:

```env
BINANCE_SSL_VERIFY=false
COINBASE_SSL_VERIFY=false
```

Em producao, mantenha SSL habilitado e use CA bundle confiavel.

## Estrutura relevante

- api/index.php
- api/src/Router.php
- api/src/BinanceClient.php
- api/src/CoinbaseClient.php
- api/tests/
