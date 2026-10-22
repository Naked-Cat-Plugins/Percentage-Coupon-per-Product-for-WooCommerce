# Tests

Both suites run against a real local WordPress + WooCommerce install with this plugin active. They create their own products, coupons, users and orders (prefixed `PPCW Test` / `PPCW E2E` / `ppcw-`) and delete them when they finish.

Development only: `.distignore` keeps the `tests` folder out of the WordPress.org release.

## Integration

Runs every discount scenario through WooCommerce's real cart and order code:

```bash
wp --skip-themes eval-file wp-content/plugins/product-percentage-coupon-woo/tests/integration/test-discounts.php
```

`--skip-themes` keeps a dev theme's own notices out of the output.

## End-to-end (Playwright)

Block cart, block checkout with an order placed by bank transfer (BACS), and the product edit screen:

```bash
cd tests/e2e
npm install
npx playwright install chromium
npx playwright test
```

Environment variables, all optional: `BASE_URL` (default `https://wordpress.local`), `CART_URL` (default `/carrinho/`), `CHECKOUT_URL` (default `/checkout-blocks-2/`), `WP_PATH` (default: the install this plugin lives in), `PPCW_KEEP_FIXTURES=1` to leave the test data in place after a run.

The checkout test needs the bank transfer (BACS) gateway enabled. To cover both order storage modes, run the suites once with HPOS on and once with it off (`wp wc hpos enable` / `wp wc hpos disable`).
