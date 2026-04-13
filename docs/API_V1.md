# UnlockMyRadio API v1

Base URL example:

`https://your-domain.com/api/v1`

## Serial Input Rule

Send the full serial from the radio label whenever possible (`min: 6` chars).  
Backend automatically applies fallback lookup for partial-key families (Becker, Chrysler, Continental).

## 1) Search serial

`POST /search`

Request:

```json
{
  "serial": "M123456"
}
```

Success (`200`, single match):

```json
{
  "success": true,
  "data": {
    "serial_input": "M123456",
    "serial_lookup": "M123456",
    "found": true,
    "requires_selection": false,
    "radio_code_id": 123,
    "brand": "Ford",
    "car_make": "Ford",
    "price_usd": "2.99"
  }
}
```

Success (`200`, multiple matches -> user must choose model):

```json
{
  "success": true,
  "data": {
    "serial_input": "BE1492 Y0010001",
    "serial_lookup": "0001",
    "found": true,
    "requires_selection": true,
    "price_usd": "2.99",
    "options": [
      {
        "radio_code_id": 50001,
        "brand": "Becker",
        "car_make": "Mercedes-Benz (4 buttons)",
        "lookup_serial": "0001",
        "hint": "Preset buttons: 1 2 3 4"
      },
      {
        "radio_code_id": 50002,
        "brand": "Becker",
        "car_make": "Mercedes-Benz (6 buttons)",
        "lookup_serial": "0001",
        "hint": "Preset buttons: 1 2 3 4 5 6"
      }
    ]
  }
}
```

Not found (`404`):

```json
{
  "success": false,
  "error": {
    "code": "SERIAL_NOT_FOUND",
    "message": "No code found for this serial number."
  }
}
```

## 2) Create Stripe checkout session

`POST /checkout`

Request:

```json
{
  "serial": "TQ1AA1151A3424",
  "email": "customer@example.com",
  "radio_code_id": 50001,
  "success_url": "unlockmyradio://payment/success?session_id={CHECKOUT_SESSION_ID}",
  "cancel_url": "unlockmyradio://payment/cancel"
}
```

`success_url` and `cancel_url` are optional. If omitted, server defaults to web URLs.

Success (`201`):

```json
{
  "success": true,
  "data": {
    "order_id": 123,
    "session_id": "cs_test_...",
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_test_...",
    "expires_at": 1770000000
  }
}
```

If multiple models match and `radio_code_id` is missing, API returns `409` (`MODEL_SELECTION_REQUIRED`) with `options`.
Each option includes a `hint` field for quick UI guidance.

## 3) Confirm payment and reveal code

`GET /payment/success?session_id=cs_test_...`

Success (`200`):

```json
{
  "success": true,
  "data": {
    "serial": "TQ1AA1151A3424",
    "serial_lookup": "13424",
    "brand": "Chrysler",
    "car_make": "Chrysler/Dodge/Jeep (5 buttons)",
    "code": "1234"
  }
}
```

If payment is not completed, API returns `402` with `PAYMENT_NOT_COMPLETED`.
