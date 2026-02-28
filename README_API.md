# Glamo API Reference (FlutterFlow)

## Base URL
- Production: `https://getglamo.com/api/v1`
- Local: `http://127.0.0.1:8000/api/v1`

## Response Format
Endpoints za `v1` zinatumia shape hii:

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

Kwenye error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

## Auth (Sanctum Bearer Token)
Baada ya `verify-otp` au `login`, utapata:
- `token`
- `token_type` = `Bearer`

Tumia kwenye header:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

## OTP Flow (Recommended)
1. `POST /auth/request-otp`
2. `POST /auth/verify-otp` (hapa ndipo user anahifadhi password na kupata token)

## Auth Endpoints
- `POST /auth/request-otp`
- `POST /auth/verify-otp`
- `POST /auth/login`
- `GET /auth/me` (auth)
- `POST /auth/logout` (auth)

### Example: Request OTP
`POST /auth/request-otp`

```json
{
  "intent": "register",
  "channel": "phone",
  "country_code": "255",
  "phone_local": "712345678",
  "role": "client"
}
```

### Example: Verify OTP + Create Account
`POST /auth/verify-otp`

```json
{
  "intent": "register",
  "channel": "phone",
  "country_code": "255",
  "phone_local": "712345678",
  "otp": "123456",
  "name": "Amina Musa",
  "password": "secret123",
  "password_confirmation": "secret123",
  "device_name": "flutterflow-client"
}
```

---

## Public Endpoints
- `GET /meta`
- `GET /app-links`
- `GET /categories`
- `GET /services?search=&category_slug=&category_id=&per_page=&random=1`
- `GET /services/{service}`
- `GET /services/{service}/providers?lat=&lng=&limit=`
- `POST /services/{service}/quote`
- `POST /coupons/preview`
- `GET /careers`
- `GET /careers/{careerJob:slug}`
- `POST /ambassador/apply`

### Example: Quote Before Order
`POST /services/{service}/quote`

```json
{
  "provider_id": 12,
  "service_ids": [5, 8],
  "lat": -6.7924,
  "lng": 39.2083,
  "include_hair_wash": true,
  "coupon_code": "KARIBU10"
}
```

---

## Authenticated Account Endpoints
- `PUT /me/location`
- `POST /me/phone-change/request-otp`
- `POST /me/phone-change/verify-otp`
- `GET /me/notifications`
- `POST /me/notifications/{id}/read`
- `POST /me/push-tokens`
- `DELETE /me/push-tokens`
- `GET /orders/{order}/messages?limit=&before_id=`
- `POST /orders/{order}/messages`

### Register Push Token
`POST /me/push-tokens`
```json
{
  "token": "fcm-device-token",
  "platform": "android",
  "app_variant": "client",
  "device_id": "optional-device-id"
}
```

### Revoke Push Token
`DELETE /me/push-tokens`
```json
{
  "token": "fcm-device-token"
}
```

### Change Phone Number via OTP
`POST /me/phone-change/request-otp`

```json
{
  "phone": "0712345678"
}
```

`POST /me/phone-change/verify-otp`

```json
{
  "phone": "0712345678",
  "otp": "123456"
}
```

### Order Private Chat
Chat ni private kwa client na provider wa order hiyo tu.

`GET /orders/{order}/messages`
- Query:
  - `limit` (optional, default 50, max 100)
  - `before_id` (optional, pagination ya messages za zamani)
- Response:
  - `order_id`, `conversation_id`, `can_send`, `messages[]`

`POST /orders/{order}/messages`
- Body:
```json
{
  "body": "Nipo njiani, nitafika ndani ya dakika 10."
}
```

Realtime event (private channel):
- Channel: `private-order.{orderId}`
- Event: `order.message.sent`
- Payload: `order_id`, `conversation_id`, `message`

---

## Client Endpoints
- `GET /client/orders`
- `GET /client/orders/active`
- `POST /client/orders`
- `GET /client/orders/{order}`
- `POST /client/orders/{order}/payment/mode`
- `POST /client/orders/{order}/payment/start`
- `POST /client/orders/{order}/payment/refresh`
- `POST /client/orders/{order}/cancel`
- `POST /client/orders/{order}/confirm-arrival`
- `POST /client/orders/{order}/review`
- `PUT /client/orders/{order}/services`

### Checkout Payment Flow (App)
1. Tengeneza order kwa `POST /client/orders` na `payment_method`:
   - `cash` = mteja atalipa baada ya huduma.
   - `prepay` = mteja analipa online sasa (`payment_channel=mobile|card`).
2. Ukiunda `prepay`, response itarudisha `payment_action`:
   - `payment_url` ikiwa ipo, mpeleke mteja huko (hasa card checkout).
   - kama ni mobile na hakuna URL, mteja athibitishe malipo kwenye simu.
3. Unaweza kurudia kuanzisha malipo kwa:
   - `POST /client/orders/{order}/payment/start`
4. Refresh status ya malipo mpaka `held`:
   - `POST /client/orders/{order}/payment/refresh`
5. Order ya `prepay` inaweza kuendelea kikamilifu baada ya malipo kuthibitishwa (`payment.status = held`).

### Example: Create Order (Cash)
`POST /client/orders`

```json
{
  "provider_id": 12,
  "primary_service_id": 5,
  "service_ids": [5, 8],
  "lat": -6.7924,
  "lng": 39.2083,
  "address_text": "Sinza Mori, karibu na kituo",
  "include_hair_wash": true,
  "coupon_code": "KARIBU10",
  "payment_method": "cash"
}
```

### Example: Create Order (Online - Mobile Money)
`POST /client/orders`

```json
{
  "provider_id": 12,
  "primary_service_id": 5,
  "service_ids": [5, 8],
  "lat": -6.7924,
  "lng": 39.2083,
  "address_text": "Sinza Mori, karibu na kituo",
  "payment_method": "prepay",
  "payment_channel": "mobile",
  "phone_number": "0712345678"
}
```

### Example: Create Order (Online - Card)
`POST /client/orders`

```json
{
  "provider_id": 12,
  "primary_service_id": 5,
  "service_ids": [5, 8],
  "lat": -6.7924,
  "lng": 39.2083,
  "address_text": "Sinza Mori, karibu na kituo",
  "payment_method": "prepay",
  "payment_channel": "card"
}
```

### Example: Change Payment Mode
`POST /client/orders/{order}/payment/mode`

```json
{
  "payment_method": "prepay",
  "payment_channel": "mobile"
}
```

### Example: Start Payment (retry/manual)
`POST /client/orders/{order}/payment/start`

```json
{
  "payment_channel": "mobile",
  "phone_number": "0712345678"
}
```

### Example: Refresh Payment Status
`POST /client/orders/{order}/payment/refresh`
- Body haihitajiki.
- Response `data.payment.order_payment_status` inaweza kuwa: `pending`, `held`, `failed`, `released`, `refunded`.

### Example: Cancel Order (reason required)
`POST /client/orders/{order}/cancel`

```json
{
  "reason": "Nimeahirisha safari ya leo, naomba nisitishe oda."
}
```

### Refund Notes (kwa app UI)
- Kwa order ya `prepay` yenye `payment.status=held`, cancellation itaweka `payment.status=refund_pending`.
- Refund itafuatiliwa na webhook; status inaweza kuwa `refunded` au `refund_failed`.
- Hifadhi/onyesha fields hizi kutoka order payload:
  - `payment.method`, `payment.channel`, `payment.status`
  - `payment.reference`, `payment.refund_reference`, `payment.refund_reason`
  - `cancellation_reason`

### SMS zinazotumwa moja kwa moja
- Mteja: SMS ya risiti malipo yakithibitishwa.
- Mtoa huduma: SMS ya muamala kupokelewa kwa order husika.

---

## Provider Onboarding Endpoints
- `GET /provider/onboarding/status`
- `POST /provider/onboarding/submit` (multipart/form-data)

### Onboarding File Fields
- `profile_image` (optional image: jpg/jpeg/png/webp, max 5MB, recommended 1080x1080+)
- `profile_image_mode` (optional: `auto_remove` | `original`, default `auto_remove`)
- `id_document` au (`id_document_front` + `id_document_back`) kutegemea `id_type`
- `certificate_file` (ikiwa trained)
- `qualification_files[]` (optional nyingi)

---

## Provider Endpoints
- `GET /provider/dashboard`
- `GET /provider/nearby-customers?radius_km=&limit=&lat=&lng=`
- `POST /provider/location`
- `PUT /provider/profile`
- `GET /provider/services/catalog`
- `PUT /provider/services`
- `POST /provider/withdraw`
- `POST /provider/debt/pay`
- `GET /provider/debt/payments?status=&per_page=`
- `GET /provider/debt/payments/{providerPayment}`
- `POST /provider/debt/payments/{providerPayment}/refresh`
- `GET /provider/reviews?per_page=`
- `GET /provider/reviews/summary`
- `GET /provider/client-feedback?per_page=`
- `GET /provider/orders`
- `GET /provider/orders/{order}`
- `POST /provider/orders/{order}/accept`
- `POST /provider/orders/{order}/reject`
- `POST /provider/orders/{order}/on-the-way`
- `POST /provider/orders/{order}/arrived`
- `POST /provider/orders/{order}/suspend`
- `POST /provider/orders/{order}/complete`
- `POST /provider/orders/{order}/client-feedback`

### Provider Wallet / Commission Rules
- Commission inasomwa kwenye config: `GLAMO_COMMISSION_PERCENT` (default `10`).
- Kwa oda ya online (`payment_method=prepay`):
  - malipo yakithibitishwa yanaingia hali ya escrow (`payment_status=held`).
  - baada ya provider kubonyeza `complete`, escrow release hufanyika na `payment_status` huwa `released`.
  - kiasi kinachoingia wallet ni payout baada ya commission (kawaida ~90%).
- Provider anaweza kuwithdraw kiasi kilichopo `wallet_balance` pekee.

### Provider Dashboard muhimu kwa homepage
`GET /provider/dashboard`
- Data kuu:
  - `wallet_balance`
  - `pending_escrow`
  - `debt_balance`
  - `orders_count`
  - `orders_stats`
  - `debt_ledgers[]`
  - `debt_payments[]`
  - `reviews_summary`
  - `recent_reviews[]`
  - `wallet_terms.commission_percent`
  - `wallet_terms.release_rule`
  - `wallet_terms.withdraw_rule`

### Example: Withdraw kutoka wallet
`POST /provider/withdraw`

```json
{
  "amount": 25000,
  "method": "mobile_money",
  "destination": "0712345678"
}
```

### Example: Provider Complete Order (escrow release trigger)
`POST /provider/orders/{order}/complete`

```json
{
  "note": "Kazi imekamilika vizuri."
}
```

### Example: Provider Accept Order (now/later)
`POST /provider/orders/{order}/accept`

```json
{
  "approve_mode": "later",
  "scheduled_for": "2026-02-18 14:30:00"
}
```

- Kwa oda ya `prepay`, accept inaruhusiwa tu baada ya `payment_status=held`.

### Example: Provider Reject Order (reason required)
`POST /provider/orders/{order}/reject`

```json
{
  "reason": "Nipo mbali sana na location ya mteja kwa sasa."
}
```

- `reason` sasa ni required.
- Order ikikubaliwa/kukataliwa, mteja anatumiwa SMS ya kawaida moja kwa moja.

### Order payload (provider side)
`GET /provider/orders` na `GET /provider/orders/{order}` sasa zinarudisha pia:
- `map.client_lat`, `map.client_lng`, `map.provider_lat`, `map.provider_lng`, `map.distance_km`
- `client.name`, `client.profile_image_url`, `client.location_text`, `client.distance_km`
- `service.name`, `service.image_url`
- `booked_services[]` (huduma alizobook)
- `price.total`
- `created_at` / `order_received_at` (muda order ilipoingia)
- `review` (ikiwa mteja tayari ametoa rating/comment)
- `payment.channel`, `payment.refund_reference`, `payment.refund_reason`
- `cancellation_reason`

### Chat (provider <-> client)
- `GET /orders/{order}/messages`
- `POST /orders/{order}/messages`
- `messages[].sender.profile_image_url` ipo pia.

### Nearby Customers kwa ramani
`GET /provider/nearby-customers?radius_km=5&limit=100`
- Response ina:
  - `center.lat`, `center.lng`
  - `customers[]` kila mteja ana `lat`, `lng`, `distance_km`, `orders_count`

### Services Catalog (selected + unselected)
`GET /provider/services/catalog`
- Response ina:
  - `allowed_services[]`
  - `active_service_ids[]`
  - `selected_service_ids[]`
  - `unselected_service_ids[]`
  - `selected_services[]`
  - `unselected_services[]`

### Debt Payment Actions
`POST /provider/debt/pay`

```json
{
  "debt_amount": 5000,
  "payment_channel": "mpesa",
  "phone_number": "0712345678"
}
```

`GET /provider/debt/payments?status=pending&per_page=20`
- status options: `pending`, `paid`, `failed`

`POST /provider/debt/payments/{providerPayment}/refresh`
- Hutumia reference ku-refresh status kutoka gateway na kusasisha record.

### Reviews / Feedback kwa mtoa huduma
`GET /provider/reviews?per_page=20`
- Inarudisha:
  - `summary` (total reviews, avg rating, breakdown ya 1-5)
  - `reviews[]` kila review ina `rating`, `comment`, `client`, `order`, `service`.

`GET /provider/reviews/summary`
- Inarudisha summary ya ratings pekee.

### Provider kutoa feedback kwa mteja (rate client)
`POST /provider/orders/{order}/client-feedback`

```json
{
  "rating": 5,
  "comment": "Mteja alikuwa tayari kwa muda na maelekezo yalikuwa wazi."
}
```

`GET /provider/client-feedback?per_page=20`
- List ya feedback ambazo provider amewahi kutoa kwa wateja.

---

## Team / About / Careers (Auth Actions)
- `GET /about/team-status`
- `POST /about/join-team`
- `POST /careers/{careerJob:slug}/apply` (multipart/form-data)

Career apply file fields:
- `cv_file` (pdf/doc/docx)
- `application_letter_file` (pdf/doc/docx)
- `cover_letter` (optional text)

---

## Legacy Non-Versioned API (Still Available)
Zipo kwa backward compatibility:
- `/api/auth/request-otp`
- `/api/auth/verify-otp`
- `/api/client/orders` n.k.

Kwa app mpya tumia `v1` endpoints.

---

## FlutterFlow Setup Notes
1. Weka `API Base URL` = `https://getglamo.com/api/v1`
2. Login/verify response JSON path ya token:
   - `$.data.token`
3. Hifadhi token kwenye App State (String).
4. Kwenye authenticated calls tumia header:
   - `Authorization: Bearer [token]`
5. Kwenye file upload endpoints tumia `multipart/form-data`.

---

## Hosting & Deployment Checklist (getglamo.com)
1. **Environment**
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://getglamo.com`
   - `FILESYSTEM_DISK=public`
   - `CORS_ALLOWED_ORIGINS=https://getglamo.com,https://www.getglamo.com,https://app.flutterflow.io`
   - `GLAMO_COMMISSION_PERCENT=10`
   - `SNIPPE_BASE_URL=https://api.snippe.sh`
   - `SNIPPE_API_KEY=...`
   - Set `SNIPPE_WEBHOOK_URL=https://getglamo.com/webhooks/snippe`
   - `SNIPPE_WEBHOOK_SECRET=...` (kama umepewa na Snippe)
   - `SNIPPE_TIMEOUT=30`
   - `BEEM_API_KEY=...`
   - `BEEM_SECRET_KEY=...`
   - `BEEM_SENDER_ID=Glamo`
   - `BEEM_SMS_URL=https://apisms.beem.africa/v1/send`
   - Set DB + MAIL values za production.
2. **Install**
   - `composer install --no-dev --optimize-autoloader`
3. **Migrate**
   - `php artisan migrate --force`
4. **Storage Link**
   - `php artisan storage:link`
5. **Optimize**
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:cache`
6. **Queue Worker**
   - Endesha worker wa queue (Supervisor/systemd) kwa notifications/webhooks.
7. **Scheduler**
   - Cron: `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
   - Inahitajika kwa `orders:process-suspended`.
8. **HTTPS**
   - Hakikisha SSL certificate iko active.
9. **Web Server**
   - `public/` ndio document root.

---

## Quick Health Test
Baada ya deploy:

```bash
curl https://getglamo.com/api/v1/meta
```

Ikiwa response ina `success: true`, API iko live.
