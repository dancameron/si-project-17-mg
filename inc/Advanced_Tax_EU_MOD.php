<?php

/**
 * Controller
 * Adds meta boxes to client admin.
 */
class SI_Hearts_EU_Mod extends SI_Controller {

	const BUSINESS_VAT_OPTION = 'si_eu_vat';
	const BUSINESS_INFO_OPTION = 'si_eu_additional_info';
	const INVOICE_PREFIX = 'si_eu_sequential_invoice_prefix';
	const INVOICE_NUMBERING_START = 'si_eu_sequential_invoice_start';
	private static $vat_number;
	private static $additional_info;
	private static $invoice_prefix;
	private static $invoice_start;

	public static function init() {

		self::$vat_number = get_option( self::BUSINESS_VAT_OPTION, '' );
		self::$additional_info = get_option( self::BUSINESS_INFO_OPTION, '' );
		self::$invoice_prefix = get_option( self::INVOICE_PREFIX, '' );
		self::$invoice_start = get_option( self::INVOICE_NUMBERING_START, '1' );

		// Register Settings
		self::register_settings();
		add_action( 'si_document_details_pre', array( __CLASS__, 'add_vat_number_to_doc' ) );

		add_filter( 'si_client_adv_form_fields', array( __CLASS__, 'add_vat_option' ) );
		add_action( 'SI_Clients::save_meta_box_client_adv_information', array( __CLASS__, 'save_client_options' ) );

		add_action( 'si_document_client_addy', array( __CLASS__, 'maybe_add_vat' ) );

		add_action( 'si_document_details', array( __CLASS__, 'add_additional_info_to_doc' ) );

		// Add GST Column
		add_filter( 'si_line_item_columns', array( __CLASS__, 'add_tax_columns' ), 10, 3 );
		add_filter( 'si_format_front_end_line_item_value', array( __CLASS__, 'format_front_end_taxes' ), 10, 3 );

		add_filter( 'si_line_item_total', array( __CLASS__, 'adjust_subtotal' ), 10, 2 );

		// Line items footer
		add_filter( 'si_line_item_totals', array( __CLASS__, 'modify_line_item_totals' ), 1000 );

		//// Enqueue
		//add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register_resources' ) );
		//add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ) );

		// Sequential Ordering
		if ( ! class_exists( 'SI_Advanced_Id_Generation' ) ) {
			add_filter( 'load_view_args_admin/meta-boxes/invoices/information.php', array( __CLASS__, 'change_invoice_id_in_meta_args' ) );
		}

		add_filter( 'si_paypal_ec_payment_request_line_items', '__return_empty_array' );
		add_filter( 'si_paypal_pro_payment_request_line_items', '__return_empty_array' );
	}

	//////////////////
	// Business VAT //
	//////////////////

	/**
	 * Hooked on init add the settings page and options.
	 *
	 */
	public static function register_settings() {
		// Settings
		$settings = array(
			'si_ca_site_settings' => array(
				'title' => __( 'Additional Company Info', 'sprout-invoices' ),
				'weight' => 201,
				'tab' => 'settings',
				'callback' => array( __CLASS__, 'display_general_section' ),
				'settings' => array(
					self::BUSINESS_VAT_OPTION => array(
						'label' => __( 'VAT Number', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$vat_number,
							),
						),
					self::BUSINESS_INFO_OPTION => array(
						'label' => __( 'Additional Info for Invoices/Estimates', 'sprout-invoices' ),
						'option' => array(
							'type' => 'wysiwyg',
							'default' => self::$additional_info,
							'description' => __( 'Additional info displayed on Invoices and Estimates. Example information: TVA identification, Banking info.', 'sprout-invoices' ),
							),
						),
					self::INVOICE_PREFIX => array(
						'label' => __( 'Invoice Numbering: Prefix', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$invoice_prefix,
							),
						),
					self::INVOICE_NUMBERING_START => array(
						'label' => __( 'Invoice Numbering: Start', 'sprout-invoices' ),
						'option' => array(
							'type' => 'text',
							'default' => self::$invoice_start,
							),
						),
					),
				),
			);
		if ( class_exists( 'SI_Advanced_Id_Generation' ) ) {
			unset( $settings['si_ca_site_settings']['settings'][ self::INVOICE_PREFIX ] );
			unset( $settings['si_ca_site_settings']['settings'][ self::INVOICE_NUMBERING_START ] );
		}
		do_action( 'sprout_settings', $settings, SI_Controller::SETTINGS_PAGE );
	}

	////////////////
	// Line items //
	////////////////


	public static function add_tax_columns( $columns = array(), $type = '', $item_data = array() ) {

		$columns['total']['label'] = sprintf( '%s&nbsp;<span class="helptip" title="%s"></span>', __( 'Total', 'sprout-invoices' ), __( 'Inclusive of Taxes', 'sprout-invoices' ) );
		if ( is_admin() ) {
			$columns['total']['label'] = sprintf( '%s&nbsp;<span class="helptip" title="%s"></span>', __( 'Subtotal', 'sprout-invoices' ), __( 'Exclusive of Taxes', 'sprout-invoices' ) );
		}

		$columns['tax_vat'] = array(
				'label' => sprintf( '%s', __( 'VAT', 'sprout-invoices' ) ),
				'type' => 'small-input',
				'calc' => true,
				'hide_if_parent' => true,
				'weight' => 21,
			);
		return $columns;
	}

	public static function modify_line_item_totals( $totals = array(), $doc_id = 0 ) {
		if ( ! $doc_id ) {
			$doc_id = get_the_id();
		}

		unset( $totals['balance'] );
		$totals['total']['label'] = __( 'Balance', 'sprout-invoices' );
		$totals['subtotal']['label'] = __( 'Total', 'sprout-invoices' );

		$taxes = (float) self::invoice_has_tax( $doc_id, true );
		if ( 0.01 > $taxes ) { // don't change the admin
			return $totals;
		}

		$exclusive_taxes_total = $totals['subtotal']['value'] - $taxes;
		$inclusive_taxes_total = $totals['subtotal']['value'];
		$totals['exclusive_taxes'] = array(
						'label' => sprintf( '%s&nbsp;<span class="helptip" title="%s"></span>', __( 'Subotal', 'sprout-invoices' ), __( 'Exclusive of Taxes', 'sprout-invoices' ) ),
						'value' => $exclusive_taxes_total,
						'formatted' => sa_get_formatted_money( $exclusive_taxes_total, $doc_id, '<span class="money_amount">%s</span>' ),
						'hide' => false,
						'admin_hide' => false,
						'weight' => 0,
					);

		$different_tax_rates = self::get_tax_totals( $doc_id );
		foreach ( $different_tax_rates as $rate => $total ) {
			$totals[ 'adv_taxes_' . $rate ] = array(
						'label' => sprintf( __( 'Tax: %s&#37;', 'sprout-invoices' ), si_get_number_format( $rate ) ),
						'value' => $total,
						'formatted' => sa_get_formatted_money( $total, $doc_id, '<span class="money_amount">%s</span>' ),
						'hide' => false,
						'admin_hide' => false,
						'weight' => 5 + ( $rate / 100 ),
					);
		}

		if ( 1 < count( $different_tax_rates ) ) {
			$totals['taxes_total'] = array(
						'label' => __( 'VAT', 'sprout-invoices' ),
						'value' => $taxes,
						'formatted' => sa_get_formatted_money( $taxes, $doc_id, '<span class="money_amount">%s</span>' ),
						'hide' => false,
						'admin_hide' => false,
						'weight' => 6,
					);
		}

		uasort( $totals, array( __CLASS__, 'sort_by_weight' ) );
		return $totals;
	}

	public static function format_front_end_taxes( $value = '', $column_slug = '', $item_data = array() ) {
		switch ( $column_slug ) {
			case 'tax_vat':
				$tax = self::calculate_tax( $item_data, 'tax_vat' );
				if ( isset( $item_data['tax_vat'] ) && 0.00 < $tax ) {
					$value = si_get_number_format( $item_data['tax_vat'] ) . '%';
				}
				break;
			case 'total':
				$value = self::calculate_inclusive_vat_tax_total( $item_data );
				break;
			default:
				break;
		}
		return $value;
	}

	public static function register_resources() {
		// admin js
		wp_register_script( 'si_live_eu_tax_calculations', SA_ADDON_MGPROJECT_URL . '/resources/eu_tax.js', array( 'jquery', 'si_admin_est_and_invoices' ), 1 );
	}

	public static function admin_enqueue() {
		$screen = get_current_screen();
		$screen_post_type = str_replace( 'edit-', '', $screen->id );
		if ( in_array( $screen_post_type, array( SI_Estimate::POST_TYPE, SI_Invoice::POST_TYPE ) ) ) {
			// wp_enqueue_script( 'si_live_eu_tax_calculations' );
		}
	}

	public static function get_tax_totals( $invoice_id = 0 ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_id();
		}
		$taxes = array();
		$line_items = si_get_doc_line_items( $invoice_id );
		if ( ! empty( $line_items ) ) {
			foreach ( $line_items as $position => $data ) {
				if ( isset( $data['tax_vat'] ) && $data['tax_vat'] ) {
					if ( ! isset( $taxes[ $data['tax_vat'] ] ) ) {
						$taxes[ $data['tax_vat'] ] = 0;
					}
					$taxes[ $data['tax_vat'] ] += self::calculate_tax( $data, 'tax_vat' );
				}
			}
		}
		return $taxes;
	}

	public static function invoice_has_tax( $invoice_id = 0, $return_total = false ) {
		if ( ! $invoice_id ) {
			$invoice_id = get_the_id();
		}
		$tax = 0;
		$has_new_tax = false;
		$line_items = si_get_doc_line_items( $invoice_id );
		if ( ! empty( $line_items ) ) {
			foreach ( $line_items as $position => $data ) {
				if ( isset( $data['tax_vat'] ) && $data['tax_vat'] ) {
					if ( $return_total ) {
						$tax += self::calculate_tax( $data, 'tax_vat' );
					} else {
						$has_new_tax = true;
						break;
					}
				}
			}
		}
		if ( $return_total ) {
			return $tax;
		}
		return $has_new_tax;
	}

	public static function calculate_tax( $data = array(), $tax = 'tax_vat' ) {
		$subtotal = ( (int)$data['rate'] * (int)$data['qty'] );
		return ( isset( $data[ $tax ] ) && $data[ $tax ] ) ? $subtotal * ( $data[ $tax ] / 100 ) : 0 ;
	}

	public static function calculate_inclusive_vat_tax_total( $data = array() ) {
		$subtotal = ( (int)$data['rate'] * (int)$data['qty'] );
		$tax = self::calculate_tax( $data );
		return $subtotal + $tax;
	}

	/**
	 * Adjust the line item total from the subtotal calculations
	 * @see invoice->get_subtotal & estimate->get_subtotal
	 * @param  integer $calculated_total
	 * @param  array   $data
	 * @return
	 */
	public static function adjust_subtotal( $subtotal = 0, $data = array() ) {
		$tax = ( isset( $data['tax'] ) ) ? (float) $data['tax'] : 0 ;
		$hst = ( isset( $data['tax_hst'] ) ) ? (float) $data['tax_hst'] : 0 ;
		$pst = ( isset( $data['tax_vat'] ) ) ? (float) $data['tax_vat'] : 0 ;
		if ( $hst + $pst > 0 ) {
			// Pre hst and/or pst
			$subtotal = ( $data['rate'] * $data['qty'] ) * ( ( 100 - $tax ) / 100 );
			$hst_subtotal = 0;
			// add hst
			if ( $hst ) {
				$hst_subtotal = $subtotal * ( $hst / 100 );
			}
			$pst_subtotal = 0;
			// add pst
			if ( $pst ) {
				$pst_subtotal = $subtotal * ( $pst / 100 );
			}
			$subtotal = $subtotal + $pst_subtotal + $hst_subtotal;
		}
		return $subtotal;
	}

	/////////////////
	// Invoice VAT //
	/////////////////

	public static function add_vat_number_to_doc() {
		if ( '' !== self::$vat_number ) {
			printf( '<dl class="doc_vat"><dt><span class="dt_heading">%1$s</span></dt><dd>%2$s</dd></dl>', __( 'VAT', 'sprout-invoices' ), self::$vat_number );
		}
	}

	public static function add_additional_info_to_doc() {
		if ( '' !== self::$additional_info ) {
			printf( '</div></section><section id="header_additional_info" class="clearfix"><div class="additional_doc_information">%1$s', wpautop( self::$additional_info ) );
		}
	}

	////////////////
	// Client VAT //
	////////////////


	/**
	 * Filters si_client_adv_form_fields to add VAT options
	 * @param array $fields
	 */
	public static function add_vat_option( $fields = array(), $client = 0 ) {

		if ( ! $client ) {
			$id = get_the_ID();
			if ( isset( $_GET['post'] ) && $id !== $_GET['post'] ) {
				$id = $_GET['post'];
			}
			$client = SI_Client::get_instance( $id );
		}

		$fields['vat'] = array(
			'weight' => 10,
			'label' => __( 'VAT', 'sprout-invoices' ),
			'type' => 'text',
			'default' => ( $client ) ? self::get_vat( $client ) : '',
			'placeholder' => '123321',
			'attributes' => array( 'size' => '8' ),
			'description' => __( 'A value added tax identification number or VAT identification number (VATIN) is used in many countries, for value added tax purposes...but you knew already.', 'sprout-invoices' ),
		);

		return $fields;
	}

	/**
	 * Save client options on advanced meta box save action
	 * @param integer $post_id
	 * @return
	 */
	public static function save_client_options( $post_id = 0 ) {
		$vat = ( isset( $_POST['sa_metabox_vat'] ) ) ? $_POST['sa_metabox_vat'] : '' ;
		$client = SI_Client::get_instance( $post_id );
		if ( $vat ) {
			self::set_vat( $client, $vat );
		}
	}

	public static function get_vat( SI_Client $client ) {
		return $client->get_post_meta( '_vat' );
	}

	public static function set_vat( SI_Client $client, $vat = '' ) {
		return $client->save_post_meta( array( '_vat' => $vat ) );
	}

	public static function maybe_add_vat() {
		$client_id = 0;
		$doc_id = get_the_id();
		if ( SI_Invoice::POST_TYPE === get_post_type( $doc_id ) ) {
			$client_id = si_get_invoice_client_id();
		}
		if ( SI_Estimate::POST_TYPE === get_post_type( $doc_id ) ) {
			$client_id = si_get_estimate_client_id();
		}
		if ( $client_id ) {
			$client = SI_Client::get_instance( $client_id );
			$vat = self::get_vat( $client );
			if ( '' !== $vat ) {
				printf( __( '<em>VAT: %s</em>', 'sprout-invoices' ), $vat );
			}
		}
	}

	//////////////////////////
	// Sequential Numbering //
	//////////////////////////

	public static function change_invoice_id_in_meta_args( $args ) {
		if ( 'auto-draft' === $args['post']->post_status ) { // only adjust drafts
			global $wpdb;

			if ( apply_filters( 'si_eu_addon_invoice_id_reset_per_year', false ) ) {
				$select = $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s AND YEAR( post_date ) = %s", SI_Invoice::POST_TYPE, 'auto-draft', date( 'Y' ) );
			} else {
				$select = $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s", SI_Invoice::POST_TYPE, 'auto-draft' );
			}
			$number_of_invoices = $wpdb->get_var( $select );
			$args['invoice_id'] = self::$invoice_prefix . sprintf( '%02d', $number_of_invoices + ( self::$invoice_start ) );
		}
		return $args;
	}
}
