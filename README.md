# Traders API

API REST em PHP para integracao com Binance e Coinbase.

## Objetivo deste repositorio

- Rodar a API localmente
- Executar testes unitarios

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

## Credenciais de exchange (importante)

Ordem de preferencia para fornecer as chaves:

1. **Servidor (.env)** — recomendado. Configure BINANCE_API_KEY/BINANCE_SECRET_KEY
   (e equivalentes Coinbase) e nao envie segredos por requisicao.
2. **Cabecalhos HTTP** — `X-API-Key` e `X-API-Secret`. Cabecalhos nao caem em
   access logs, historico de navegador ou Referer.
3. **Body JSON** (apenas em POST/DELETE).

NUNCA envie `api_key`/`secret_key` na **query string** (ex: `?api_key=...&secret_key=...`):
a URL completa costuma ser registrada em logs de servidor/proxy e no historico do
navegador, expondo a chave secreta da sua conta.

## Endpoints publicos vs protegidos

- `/api/health` e publico (sem Basic Auth), para load balancers sondarem a aplicacao.
- Todas as demais rotas exigem Basic Auth quando BASIC_AUTH_USER/PASSWORD estao
  configurados (obrigatorio em producao).

## Smoke test manual

Com API no ar:

```bash
curl -i http://localhost:8080/api/health
curl -i http://localhost:8080/api/general/ping
curl -i "http://localhost:8080/api/market/ticker?symbol=BTCUSDT"
curl -i http://localhost:8080/api/coinbase/general/time
curl -i "http://localhost:8080/api/coinbase/market/product?product_id=BTC-USD"
```

## Nota sobre SSL em ambiente local

A verificacao de SSL vem **habilitada por padrao** (BINANCE_SSL_VERIFY=true,
COINBASE_SSL_VERIFY=true).

Em algumas maquinas Windows a validacao de certificado pode falhar para chamadas
externas. **Somente em desenvolvimento local**, voce pode ajustar no api/.env:

```env
BINANCE_SSL_VERIFY=false
COINBASE_SSL_VERIFY=false
```

Em producao, mantenha SSL habilitado e use um CA bundle confiavel (BINANCE_CA_BUNDLE).

## Estrutura relevante

- api/index.php
- api/src/Router.php
- api/src/BinanceClient.php
- api/src/CoinbaseClient.php
- api/tests/
