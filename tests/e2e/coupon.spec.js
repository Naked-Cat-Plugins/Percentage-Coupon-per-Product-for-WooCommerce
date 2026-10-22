// End-to-end tests for Percentage Coupon per Product for WooCommerce, against the block cart and checkout.
const { test, expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );
const { wp } = require( './wp' );

const fixtures = () => JSON.parse( fs.readFileSync( path.join( __dirname, '.fixtures.json' ), 'utf8' ) );

const CART_URL = process.env.CART_URL || '/carrinho/';
const CHECKOUT_URL = process.env.CHECKOUT_URL || '/checkout-blocks-2/';

// "-105,00 €" or "-€105.00" to a number, whatever the store's price format.
function money( text ) {
	const digits = text.replace( /[^\d.,]/g, '' );
	const decimal = digits.match( /[.,](\d{2})$/ );
	const whole = decimal ? digits.slice( 0, -3 ) : digits;
	return parseFloat( whole.replace( /[.,]/g, '' ) + ( decimal ? '.' + decimal[ 1 ] : '' ) );
}

async function login( page, user ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', user.login );
	await page.fill( '#user_pass', user.password );
	await page.click( '#wp-submit' );
	await page.waitForURL( ( url ) => ! url.pathname.includes( 'wp-login.php' ) );
}

async function fillCart( page, productIds ) {
	wp( 'reset-cart' );
	await login( page, fixtures().customer );
	for ( const id of productIds ) {
		const response = await page.goto( `/?add-to-cart=${ id }` );
		expect( response.status() ).toBe( 200 );
	}
	await page.goto( CART_URL );
	await expect( page.locator( '.wc-block-cart-items__row' ) ).toHaveCount( productIds.length );
}

async function applyCoupon( page, code ) {
	await page.getByRole( 'button', { name: 'Add coupons' } ).click();
	const form = page.locator( '.wc-block-components-totals-coupon' );
	await form.locator( 'input' ).fill( code );
	await form.getByRole( 'button', { name: 'Apply' } ).click();
}

const discountRow = ( page ) => page.locator( '.wc-block-components-totals-discount .wc-block-components-totals-item__value' );

test.describe( 'Block cart and checkout', () => {
	test( 'one coupon, different percentage per product, sale price included', async ( { page } ) => {
		const { products, coupons } = fixtures();
		// 10 (coupon default) + 25 (product) + 40 (50% of the 80 sale price) + 0 (disabled) + 30 (variation) = 105.
		await fillCart( page, [ products.default, products.override, products.sale, products.disabled, products.variation_M ] );
		await applyCoupon( page, coupons.main.code );
		await expect( discountRow( page ) ).toBeVisible();
		expect( money( await discountRow( page ).innerText() ) ).toBe( 105 );
	} );

	test( 'order placed through the block checkout keeps the per product discounts', async ( { page } ) => {
		const { products, coupons, hpos } = fixtures();
		await fillCart( page, [ products.default, products.override, products.sale, products.disabled, products.variation_M ] );
		await applyCoupon( page, coupons.main.code );
		await expect( discountRow( page ) ).toBeVisible();

		await page.goto( CHECKOUT_URL );
		await expect( page.locator( '.wc-block-components-checkout-place-order-button' ) ).toBeVisible();
		await expect( discountRow( page ) ).toBeVisible();
		expect( money( await discountRow( page ).innerText() ) ).toBe( 105 );

		// Required fields added by other plugins on a dev install.
		for ( const input of await page.locator( '.wc-block-checkout input[required][type="text"], .wc-block-checkout input[required]:not([type])' ).all() ) {
			if ( ( await input.isVisible() ) && ! ( await input.inputValue() ) ) {
				await input.fill( 'E2E' );
			}
		}
		await page.locator( 'input[name="radio-control-wc-payment-method-options"][value="bacs"]' ).check( { force: true } );
		await page.locator( '.wc-block-components-checkout-place-order-button' ).click();
		await page.waitForURL( /order-received\/\d+/, { timeout: 60000 } );

		const orderId = page.url().match( /order-received\/(\d+)/ )[ 1 ];
		const order = wp( 'order', orderId );
		expect( order.coupons ).toEqual( [ coupons.main.code ] );
		expect( order.discount ).toBe( 105 );
		expect( order.data_store ).toBe( hpos ? 'Automattic\\WooCommerce\\Internal\\DataStores\\Orders\\OrdersTableDataStore' : 'WC_Order_Data_Store_CPT' );
		const byName = Object.fromEntries( order.items.map( ( item ) => [ item.name, item.discount ] ) );
		expect( byName ).toEqual( {
			'PPCW E2E default': 10,
			'PPCW E2E override 25': 25,
			'PPCW E2E sale 80 override 50': 40,
			'PPCW E2E disabled': 0,
			'PPCW E2E variable override 20 - M': 30,
		} );
	} );

	test( 'coupon with "Exclude sale items" is refused on a cart of sale items only', async ( { page } ) => {
		const { products, coupons } = fixtures();
		await fillCart( page, [ products.sale ] );
		await applyCoupon( page, coupons.nosale.code );
		await expect( page.locator( '.wc-block-components-totals-coupon' ) ).toContainText( /sale/i );
		await expect( discountRow( page ) ).toHaveCount( 0 );
	} );

	test( 'coupon is refused on a cart of products set to "No" only', async ( { page } ) => {
		const { products, coupons } = fixtures();
		await fillCart( page, [ products.disabled ] );
		await applyCoupon( page, coupons.main.code );
		await expect( page.locator( '.wc-block-components-totals-coupon' ) ).toContainText( /not applicable/i );
		await expect( discountRow( page ) ).toHaveCount( 0 );
	} );

	test( 'coupon with "Exclude sale items" skips the sale item in a mixed cart', async ( { page } ) => {
		const { products, coupons } = fixtures();
		await fillCart( page, [ products.sale, products.default ] );
		await applyCoupon( page, coupons.nosale.code );
		await expect( discountRow( page ) ).toBeVisible();
		expect( money( await discountRow( page ).innerText() ) ).toBe( 10 );
	} );
} );

test.describe( 'Product settings', () => {
	test( 'percentage with decimals set on the product edit screen is saved and used in the cart', async ( { page } ) => {
		const { products, coupons, admin, decimal } = fixtures();
		await login( page, admin );
		await page.goto( `/wp-admin/post.php?post=${ products.edit }&action=edit` );
		await page.locator( '.product_percentage_coupon_options a, li.product_percentage_coupon_tab a' ).first().click();
		const panel = page.locator( '#product_percentage_coupon' );
		await expect( panel ).toBeVisible();
		const active = panel.locator( `#_product_percentage_coupon_woo_active_${ coupons.main.id }` );
		const amount = panel.locator( `#_product_percentage_coupon_woo_amount_${ coupons.main.id }` );
		// The product screen script hides the percentage while the coupon is set to "No".
		await active.selectOption( 'no' );
		await expect( amount ).toBeHidden();
		await active.selectOption( 'yes' );
		await expect( amount ).toBeVisible();
		// Typed with the store's own decimal separator, as the field asks for.
		await amount.fill( `12${ decimal }5` );
		await page.locator( '#publish' ).click();
		await expect( page.locator( '#message.updated, .notice-success' ).first() ).toBeVisible();
		// "Yes" is the default, so only the percentage is stored, always with a dot.
		expect( wp( 'product-meta', products.edit, coupons.main.id ) ).toEqual( { active: '', amount: '12.5' } );

		await page.context().clearCookies();
		await fillCart( page, [ products.edit ] );
		await applyCoupon( page, coupons.main.code );
		await expect( discountRow( page ) ).toBeVisible();
		expect( money( await discountRow( page ).innerText() ) ).toBe( 12.5 );
	} );
} );
