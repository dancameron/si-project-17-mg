<?php


class Invoice_Customizations extends SI_Controller {

	public static function init() {
		add_filter( 'gettext', array( __CLASS__, 'change_tva' ), 100, 3 );
		add_filter( 'si_line_item_totals', array( __CLASS__, 'modify_line_item_totals_text' ), 100 );
	}

	public static function modify_line_item_totals_text( $totals ) {
		foreach ( $totals as $key => $total ) {
			$totals[ $key ]['label'] = str_replace( 'Tax:', 'VAT:', $total['label'] );
		}
		return $totals;
	}

	public static function change_tva( $translations, $text, $domain ) {
		if ( 'sprout-invoices' === $domain ) {
			// Change "Description"
			if ( 'TVA' === $text ) {
				return 'VAT';
			}
			// add more conditions, changing as many strings as you'd like
		}
		return $translations;
	}
}
