<?php

class Woo_Commerce_Int_Mods extends SI_Controller {

	public static function init() {
		add_filter( 'si_woo_process_payment_invoice_args', array( __CLASS__, 'add_vat' ), 20, 2 );
	}

	public static function add_vat( $invoice_args = array(), $order_id = 0 ) {
		$order = wc_get_order( $order_id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return $invoice_args;
		}
		$invoice_args['line_items'] = array();
		foreach ( $order->get_items() as $key => $item ) {

			$_product = $item->get_product();
			if ( $_product && ! $_product->is_visible() ) {
				$desc = apply_filters( 'woocommerce_order_item_name', $item['name'], $item );
			} else {
				$desc = apply_filters( 'woocommerce_order_item_name', sprintf( '<a href="%s">%s</a>', get_permalink( $item['product_id'] ), $item['name'] ), $item );
			}

			// hard coded hack becuase I can't think of a calculation at this time...
			$vat = 0;
			if ( $item->get_total_tax() > 0 ) {
				if ( round( $item->get_total() * .23, 2 ) === (float) $item->get_total_tax() ) {
					$vat = 23;
				} elseif ( round( $item->get_total() * .135, 2 ) === (float) $item->get_total_tax() ) {
					$vat = 13.5;
				}
			}

			$invoice_args['line_items'][] = array(
				'rate' => $item->get_total() / $item->get_quantity(),
				'qty' => $item->get_quantity(),
				'desc' => $desc,
				'total' => $item->get_total(),
				'tax' => 0,
				'tax_vat' => $vat,
				);
		}

		return $invoice_args;
	}
}
