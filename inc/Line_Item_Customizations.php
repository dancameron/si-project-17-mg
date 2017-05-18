<?php


class Line_Item_Customizations extends SI_Controller {

	public static function init() {
		add_filter( 'si_line_item_types', array( __CLASS__, 'change_type_names' ), 20, 1 );
		add_filter( 'si_set_line_items', array( __CLASS__, 'change_type_default' ), 20, 2 );

		add_filter( 'si_line_item_columns', array( __CLASS__, 'modify_column_names' ), 11 );
	}

	public static function change_type_names( $types = array() ) {
		$types = array(
				'product' => __( 'Product', 'sprout-invoices' ),
				'task' => __( 'Task', 'sprout-invoices' ),
				'service' => __( 'Service', 'sprout-invoices' ),
			);
		return $types;
	}

	public static function change_type_default( $line_items = array(), $doc ) {

		// only invoices
		if ( ! is_a( $doc, 'SI_Invoice' ) ) {
			return $line_items;
		}

		// set default if not already
		if ( ! empty( $line_items ) ) {
			foreach ( $line_items as $position => $data ) {
				if ( ! isset( $data['type'] ) || '' === $data['type'] ) {
					$line_items[ $position ]['type'] = 'product';
				}
			}
		}
		return $line_items;
	}

	public static function modify_column_names( $columns = array() ) {
		$columns['tax']['label'] = '&#37;&nbsp;Disc&nbsp;<span class="helptip" title="A percentage adjustment per line item, i.e. tax or discount"></span>';
		$columns['tax_vat']['label'] = 'VAT&nbsp;<span class="helptip" title="VAT Rate: 0, 13.5, or 23."></span>';
		return $columns;
	}
}

