# Kardal Merchant Onboarding & Integration Guide

End-to-end guide for onboarding a **new merchant ("Nike Store")** onto the Kardal
payment platform and integrating **KHQR (KESSKHQR)** and **Card (VISA_MASTER)**
payments. Written against the Kardal DEV environment.

Two halves:

- **Part 1 — Onboarding** (business/ops): create the merchant in **KUP**, get it
  approved, and download its API credentials.
- **Part 2 — Integration** (engineering): how this `kardal-merchant-demo` Laravel +
  React app talks to the gateway.

> Source references point at the Kardal monorepo (`kup/`, `core/`, `webpay/`,
> `kardal-api-docs/`) and the web-demo reference (`web-demo/`).

---

## Part 1 — Onboard the Nike Store merchant on KUP

The merchant is created and approved in the **Kardal Unified Portal (KUP)**. Only
after the approval workflow reaches **ACTIVATED** does KUP provision the merchant in
WebPay/Core and produce the API credential package.

### 1.1 Organisation model

KUP is multi-tenant. Hierarchy (`kup/app/Enums/OrganizationTypes.php`):

```
BANK_ACQUIRING  →  PAY_FAC  →  MERCHANT  →  SUB_MERCHANT
```

The Nike Store is a **MERCHANT** under a PayFac/acquiring parent.

### 1.2 Create the merchant (application / KYB)

Self-service onboarding track (routes in `kup/routes/web.php:73-89`):

| Step | Route | Purpose |
|------|-------|---------|
| Eligibility | `GET /merchants/eligibility` | Pre-check |
| Email verify | `GET /merchants/email/verification` | Verify business email |
| Onboarding form | `GET /merchants/onboarding` | KYB data capture |
| Terms/Privacy | `GET /merchants/terms-privacy` | Declaration agreement |
| Create user+merchant | `POST /create-merchant-user` | Creates `merchants` + `users` + `organizations` rows |
| Form config / lookups | `GET /merchants/onboarding/{config,countries,locations}` | Dropdown data |

Required data captured on the `merchants` table (see
`kup/database/seeders/DemoMerchantWorkflowSeeder.php:117-198` for the full field
set). For "Nike Store" you supply:

- **Business identity**: `name`, `bus_name`, `bus_type` (e.g. `sole_proprietorship`
  / company), `bus_category` (Retail), `bus_registration_no`, `bus_pat_tax_no`,
  `bus_tax_id`, `bus_mcc_code` (e.g. `5651` apparel), `bus_industry`.
- **Contact**: `bus_email`, `bus_phone_number`, `bus_website`, `bus_con_person`.
- **Business location** (`bl_*`): country, city, province, district, commune,
  village, address lines, zip, geo lat/long, operation hours.
- **Business profile** (`bp_*`): monthly sales volume, monthly txn count, avg
  ticket size, refund policy, risk category.
- **Store photos** (`bpho_*`): exterior/interior.
- **Settlement** (`set_*`): primary settlement currency (USD), frequency.
- **Declaration**: `declaration_agreement` (terms + privacy), `fee_acceptance`.
- **Product/Service selection** (`product_service`): which channels + payment
  types are enabled — this is where **eCommerce** and **KHQR / Card** are chosen
  (see seeder `defaultProductServiceSelection()` lines 739-776).

### 1.3 Supporting records (required before approval)

| Record | Table | Notes |
|--------|-------|-------|
| Merchant admin user | `users` | logs into KUP; linked via `merchant_id`; gets role `merchant` (`role_user`) |
| Organization | `organizations` | `type = merchant` |
| KYB documents | `files` | id_passport, patent_certificate, certificate_incorporation, rental_agreement, business_license, premise_photos |
| Document type | `document_types` | e.g. `sole_proprietorship` |
| UBO owner(s) | `ubo_owners` | beneficial owner: NID upload, proof of address |
| Authorized representative | `auth_representatives` | name, position, contact |
| Settlement account | `settlement_accounts` | bank, account no/name, currency, bank statement |

### 1.4 Compliance verification

Before sign-off, compliance checks run and must pass
(`compliance_verifications` + `compliance_verification_checks`, seeder lines
435-504). Check types:

- `ekyc` — eKYC verification
- `kyb_registry` — KYB registry check
- `aml_pep` — AML / PEP screening
- `visa_vmss` — Visa VMSS
- `mastercard_match` — Mastercard MATCH

Verification `status` must be `passed` (overall_progress 100) with each check
`passed` / `low_risk`.

### 1.5 Approval workflow (5 steps)

Driven by the `HasApprovalWorkflow` trait +
`kup/app/Models/Modules/Merchant/MerchantApprovalWorkflowOperations.php`.
API routes (`kup/routes/web.php:257-301`, prefix `back-process/approval`):

| Step | `approval_items.status` | Action endpoint |
|------|------------------------|-----------------|
| 1 | `submitted` | `POST back-process/merchant/{id}/submit-approval` |
| 2 | `reviewed` | `POST back-process/approval/{itemId}/review` |
| 3 | `approved` | `POST back-process/approval/{itemId}/approve` |
| 4 | `final_approved` | `POST back-process/approval/{itemId}/final-approve` |
| 5 | `activated` | `POST back-process/approval/{itemId}/activate` |

(Reject / resubmit also exist.) The submission row lives in
`approval_submissions` (`status`, `current_step`); per-step audit in
`approval_item_details`. Track status via
`GET back-process/merchant/{id}/approval-status`.

Each step is performed by an authorised reviewer (admin/PayFac role) — the
**merchant cannot self-approve**. KUP web auth stack is
`['auth', TwoFA, CheckPermissionWeb, ForcePasswordReset]`, so reviewers need the
relevant **permission** on the approval menu and 2FA enabled.

**On activation** (step 5) KUP dispatches `CreateKardalMerchantJob`
(`MerchantApprovalWorkflowOperations.php:19`), which:

1. Creates/syncs the merchant in **Core** (`merchants.sync_status = synced`).
2. Calls **WebPay merchant setup** via
   `kup/app/Services/WebPayOAuth/MerchantSetupService.php` →
   `setupMerchantAfterCoreSynced()`. This returns the gateway MID + credentials
   and **generates the credential package**.

### 1.6 Provisioning output: seller_code, credentials, keys

`MerchantSetupService::persistWebPaySetupData()` (line 168) stores:

- **`merchants.seller_code`** — unique per merchant
  (migration `kup/database/migrations/2026_04_08_000002_add_seller_code_to_merchants_table.php`).
  This is the `seller_code` used in every gateway transaction.
- **`merchants.gateway_mid`** — WebPay merchant id.
- **`merchants.api_secret_key`** — encrypted at rest (`kardalEncrypt`); this is the
  **`api_key`** used for request signing.
- **Credential package** (ZIP, stored on S3/local, downloadable) built by
  `buildCredentialPackageFiles()` (line 847). Contents:
  - `credential.txt` — `Host`, `username`, `password`, `client_id`,
    `client_secret`, `seller_code`, `api_secret_key`.
  - `<type>-public.key` — **RSA public key (PEM)** used to encrypt card +
    customer data for Direct Pay.
  - `postman.json` — ready-to-use Postman collection.

Gateway/payment-method metadata is cached via `GatewayMerchantCacheService`
(seeder lines 397-433): `payment_methods` (e.g. `['KHQR','Visa','Mastercard']`),
`services` (`['Payments','Refunds','Settlement']`), `whitelist_ips`, KHQR
merchant info, WeChat/Alipay MIDs.

### 1.7 Download credentials

Merchant admin downloads the package from KUP:

```
GET back-process/.../merchant-setup/{merchant_id}/credentials
```
(`MerchantSetupController@downloadCredentials`, `kup/routes/web.php:117`).
Payment options / services available:
`.../merchant-setup/payment-options`, `.../merchant-setup/services`.

### 1.8 Enable payment methods + IP whitelist

- **KHQR / Card** are enabled through the product/service selection (1.2) and the
  cached `payment_methods` (1.6). KESSKHQR = the KHQR service code; VISA_MASTER =
  card. Full service-code list in `kardal-api-docs/content/api-gateway/native-pay.mdx:90-93`.
- **Whitelist your server IPs** (`whitelist_ips`) — the gateway rejects calls from
  non-whitelisted IPs in production. Add your app server's egress IP.
- **Set `notify_url`** — the server-to-server callback URL the gateway posts
  payment results to (per-transaction or merchant default).

### 1.9 Onboarding checklist

- [ ] Merchant + admin user created, role `merchant` assigned
- [ ] KYB fields complete; documents uploaded; UBO + rep + settlement account added
- [ ] Compliance verification `passed`
- [ ] Approval workflow reached **ACTIVATED**
- [ ] Core synced (`sync_status = synced`), `gateway_mid` set
- [ ] Credential package downloaded (`credential.txt` + `*-public.key`)
- [ ] `seller_code`, `client_id/secret`, `username/password`, `api_key` recorded
- [ ] KHQR + Card enabled; server IP whitelisted; `notify_url` registered

---

## Part 2 — API integration

Current demo payment path uses **Gateway ecommerce** in front of **Core Java**.
Legacy WebPay password-grant/card routes stay in code only for reference.

| Operation | Route / `service` | Notes |
|-----------|-------------------|-------|
| OAuth token | `POST {base}/gateway/oauth2/token` | `client_credentials` + Basic auth |
| Generate payment link (hosted) | `service=createPaymentLink` | `POST {base}/api/gateway/v1/ecommerce` |
| **KHQR** | `service=nativePay` | `POST {base}/api/gateway/v1/ecommerce` |
| Checkout status | `GET {base}/api/gateway/v1/ecommerce/checkout/{orderKey}/status` | signed by `orderKey` |
| **Card** | legacy `webpay.acquire.directPay` | disabled in this demo |

Merchant create routes require bearer OAuth + raw-body `request-signature` +
`Idempotency-Key`.

### 2.1 Authenticate

```
POST {base}/gateway/oauth2/token
Authorization: Basic {base64(client_id:client_secret)}
grant_type=client_credentials&scope=merchant.ecommerce.payment:create
```
Gateway returns Kardal envelope data with `accessToken` / `expiresIn`. Cache and
reuse the bearer token server-side.

### 2.2 Sign every request

Merchant create calls:

1. Build exact raw JSON body with camelCase fields.
2. HMAC-SHA256 sign the exact body using `KARDAL_API_KEY`.
3. Send header `request-signature: {hex}`.
4. Send `Idempotency-Key: {outTradeNo}`.

Checkout status calls:

1. HMAC-SHA256 sign the plain `orderKey` using `KARDAL_API_KEY`.
2. Send that value as `request-signature`.

Implementation: `app/Services/Kardal/KardalClient.php` (`ecommerceGateway()`,
`ecommerceCheckoutStatus()`).

### 2.3 KHQR (`service=nativePay`)

Request body:

```json
{
  "merchantKey": "...",
  "outTradeNo": "nike-...",
  "service": "nativePay",
  "totalAmount": 12.34,
  "currency": "USD",
  "body": "Nike Store Order",
  "notifyUrl": "https://merchant.example/payment/notify",
  "redirectUrl": "https://merchant.example/order/nike-..."
}
```

Response includes `orderKey`, `status`, and `result.qrContent`/`qrcode`. Render
the QR, then poll checkout status by that `orderKey` until terminal.

### 2.4 Card (VISA_MASTER, Direct Pay)

`service=webpay.acquire.directPay`, `service_code=VISA_MASTER`. **Card + customer
are RSA-encrypted with the merchant public key, then hex-encoded** — done
server-side, never in the browser.

- `card` = hex(RSA(`{number, securityCode, expiry:{month, year}}`))
- `customer` = hex(RSA(`{first_name, last_name, email, phone_number, phone_code}`))
- also send `ip_address` (client IP) and `holder_name`.

Response `data` may contain:

```json
{ "required_3ds": true, "pre_card_input": true,
  "html_confirm_payment": "<html>…auto-submitting 3DS form…</html>",
  "order_info": { "out_trade_no": "...", "status": "WAITING" } }
```

If `html_confirm_payment` is non-empty, **render that HTML** in the browser to run
the 3DS / ACS step; the bank redirects back via the `TermUrl` inside it. Then
confirm final status via `queryOrder` / notify.
(`kardal-api-docs/content/api-gateway/direct-pay.mdx`.) RSA reference:
`web-demo/backend/index.php:763`.

### 2.5 Checkout status

`GET /api/gateway/v1/ecommerce/checkout/{orderKey}/status` returns public order
state such as `status` and `paidAt`. Current demo stores `orderKey` in
`orders.token` and polls through Laravel.

### 2.6 Notify webhook

The gateway POSTs transaction results to your `notify_url` server-to-server.
**Verify the `sign`** on the callback before trusting it, then update your order.
(Demo handler logs + updates: `app/Http/Controllers/PaymentController.php::notify`.)

### 2.7 Error codes

`400 VALIDATION_ERROR`, `401 UNAUTHENTICATED`, `403 FORBIDDEN`, `404 NOT_FOUND`,
`409 DUPLICATED`, `419 EXPIRED`, `422 PROCESS_FAILED`, `500 SYSTEM_ERROR`,
`503 FEATURE_UNDER_MAINTENANCE`, `504 GATEWAY_TIMEOUT`.

---

## Part 3 — This app (`kardal-merchant-demo`)

Laravel 13 + Inertia + React (Breeze scaffold), SQLite. Nike-store front end +
thin server-side proxy to Kardal.

### 3.1 Layout

| File | Role |
|------|------|
| `config/kardal.php` | Gateway ecommerce config, legacy card/query fallback config, callback URLs |
| `app/Services/Kardal/KardalClient.php` | OAuth token cache, raw-body request signing, checkout status signing, legacy helpers, RSA encrypt |
| `app/Services/Kardal/KardalPaymentService.php` | `createKhqr()`, `createPaymentLink()`, `queryOrder()` |
| `app/Http/Controllers/StoreController.php` | Inertia pages: store, checkout, result |
| `app/Http/Controllers/PaymentController.php` | `POST /payment/khqr`, `/payment/link`, `GET /payment/{id}/status`, `POST /payment/notify` |
| `app/Models/Order.php` + `orders` migration | Local order tracking |
| `resources/js/Pages/Store/{Index,Checkout,Result}.jsx` | Storefront, KHQR + payment-link checkout, result |

Secrets stay server-side; the browser only sees `qrcode`, `html_confirm_payment`,
and order status.

### 3.2 Configure (`.env`)

Fill Gateway ecommerce values:

```
KARDAL_GATEWAY_BASE_URL=http://localhost:8080
KARDAL_OAUTH_CLIENT_ID=...
KARDAL_OAUTH_CLIENT_SECRET=...
KARDAL_OAUTH_SCOPE=merchant.ecommerce.payment:create
KARDAL_API_KEY=...
KARDAL_MERCHANT_KEY=...
KARDAL_NOTIFY_URL=${APP_URL}/payment/notify
KARDAL_REDIRECT_URL=${APP_URL}
```

Legacy `KARDAL_BASE_URL`, password-grant, seller-code, and RSA public key vars
only matter if you re-enable old WebPay card/queryOrder paths.

Place the RSA public key at `storage/app/kardal/merchant-public.key`
(the `*-public.key` from the package), or set `KARDAL_PUBLIC_KEY` inline.

> The notify webhook must be reachable by the DEV gateway. For local dev expose it
> with a tunnel (e.g. ngrok) and register that URL as `notify_url` / whitelist the IP.

### 3.3 Run

```bash
composer install
npm install
php artisan migrate
npm run dev      # or: npm run build
php artisan serve
```

Open `/` → add Nike products → **Checkout** → pick **KHQR** (scan, auto-polls) or
**Payment Link** (redirect to hosted checkout) → redirected to the order result.

### 3.4 Flow recap

```text
Browser → /payment/khqr|link → PaymentController → KardalPaymentService
  → KardalClient → POST /gateway/oauth2/token
  → KardalClient → POST {base}/api/gateway/v1/ecommerce
Gateway/Core Java → notify_url
Browser → /payment/{id}/status → Laravel → GET /api/gateway/v1/ecommerce/checkout/{orderKey}/status
```
