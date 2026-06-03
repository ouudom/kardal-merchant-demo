# kardal-merchant-demo (Nike Store)

Demo merchant app — Laravel 13 + Inertia + React — integrating the **Kardal**
payment gateway. Buyers register/log in (Breeze), build a server-side cart, and
pay via **Payment Link** (primary) or **KHQR (KESSKHQR)**, then see their order
history. Card (VISA_MASTER) is wired but commented out. Connects to the Kardal
**DEV** server.

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
# fill KARDAL_* in .env from your KUP credential package
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
Secrets (api_key, OAuth creds, RSA key) stay server-side.
