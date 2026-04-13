# Reseller Credit API (Test Mode)

Last update: 2026-04-13

## Test Credit Rule

New reseller accounts receive `50` credits by default (configurable):

`RESELLER_TEST_DEFAULT_CREDITS=50`

## Create Reseller + API Key

```bash
php artisan reseller:create "Demo Partner" --email=demo@example.com
```

Optional:

```bash
php artisan reseller:create "Demo Partner" --credits=50
php artisan reseller:create "Demo Partner" --inactive
```

The command prints one plain API key once. Save it immediately.

## Adjust Credits Manually

```bash
php artisan reseller:credit 1 100
php artisan reseller:credit 1 -10 --reason=manual_fix
```

## Auth

Use either:

- `Authorization: Bearer <API_KEY>`
- `X-Api-Key: <API_KEY>`

## Endpoints

Base: `/api/v1/reseller`

### 1) Balance

`GET /balance`

Response:

```json
{
  "success": true,
  "data": {
    "reseller_id": 1,
    "name": "Demo Partner",
    "credits": 50,
    "key_prefix": "AbC123..."
  }
}
```

### 2) Decode (consumes 1 credit on success)

`POST /decode`

Request:

```json
{
  "serial": "TQ1AA1151A3424",
  "radio_code_id": 12345
}
```

`radio_code_id` optional when lookup resolves to a single result.

Success:

```json
{
  "success": true,
  "data": {
    "serial_input": "TQ1AA1151A3424",
    "serial_lookup": "13424",
    "brand": "Chrysler",
    "car_make": "Chrysler/Dodge/Jeep (5 buttons)",
    "code": "1234",
    "remaining_credits": 49,
    "charged_credits": 1
  }
}
```

If multiple matches exist:

- returns `409 MODEL_SELECTION_REQUIRED`
- includes option list with `radio_code_id` + `hint`

If credits are empty:

- returns `402 CREDITS_EXHAUSTED`

