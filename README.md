# kardal-merchant-demo (Nike Store)

Demo merchant app — Laravel 13 + Inertia + React — integrating the **Kardal**
payment gateway. Buyers register/log in (Breeze), build a server-side cart, and
pay via **Payment Link** (primary) or **KHQR (KESSKHQR)**, then see their order
history. Card (VISA_MASTER) is wired but commented out. Both KHQR and Payment
Link now use Gateway ecommerce + Core Java.

## Flow

- Browse the catalog (hardcoded — no products table) at `/`, publicly.
- **Register / log in** to add to cart. Cart + orders are keyed by `user_id`.
- Cart lives in the DB (`cart_items`, with qty). Checkout reads it server-side;
  order amount is computed from the cart, never trusted from the client.
- Pay via Payment Link or KHQR. On a paid status the cart is cleared.
- `/orders` lists the buyer's order history; each links to its result page
  (success / failed / pending).

## Quick start

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate   # if no .env yet
# fill KARDAL_GATEWAY_*, KARDAL_API_KEY, and KARDAL_MERCHANT_KEY for Gateway ecommerce
# keep legacy KARDAL_* only if you still test old card/queryOrder paths
php artisan migrate
npm run dev
php artisan serve
```

Open `/`, register, add products, checkout via Payment Link or KHQR.

## Docs

Full merchant onboarding (KUP create/approve/activate, credentials) **and** the
API integration: **[docs/ONBOARDING-AND-INTEGRATION.md](docs/ONBOARDING-AND-INTEGRATION.md)**.

## Kardal config

All gateway settings live in `config/kardal.php`, driven by `KARDAL_*` env vars.
Secrets stay server-side.

For local Gateway ecommerce:

```env
KARDAL_GATEWAY_BASE_URL=http://localhost:8080
KARDAL_OAUTH_CLIENT_ID=kardal-merchant-demo
KARDAL_OAUTH_CLIENT_SECRET=merchant-demo-secret
KARDAL_OAUTH_SCOPE=merchant.ecommerce.payment:create
KARDAL_API_KEY=...
KARDAL_MERCHANT_KEY=...
```
