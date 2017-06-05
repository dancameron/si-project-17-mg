<?php


class Line_Item_Customizations extends SI_Controller {

	public static function init() {
		add_filter( 'si_line_item_types', array( __CLASS__, 'change_type_names' ), 20, 1 );
		add_filter( 'si_set_line_items', array( __CLASS__, 'change_type_default' ), 20, 2 );
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
}
