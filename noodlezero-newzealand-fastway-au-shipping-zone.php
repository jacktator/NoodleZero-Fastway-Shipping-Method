<?php

if (!defined('WPINC')) {
	die;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	return false;
}

function fastway_nz_shipping_zone_method() {
	if (!class_exists('Fastway_NZ_Shipping_Zone_Method')) {
		class Fastway_NZ_Shipping_Zone_Method extends WC_Shipping_Method {
			var $api_key, $pickup_rfcode, $support_type, $custom_local_parcel_price;
			public function __construct($instance_id = 0) {
				$this->instance_id = absint($instance_id);
				$this->id = 'fastway_nz-zone';
				$this->method_title = __('Fastway NZ', 'sk8tech-fastwaynz');
				$this->method_description = __('Fastway Couriers currently operates across key metropolitan and regional locations across NewZealand, offering a low cost and fast courier delivery service. Franchise opportunities also available.<br/><strong style="color:red">Currency Of Shipping Price Is In New Zealand Dollar<s/trong><br/><strong style="color:black">Support URL: <a href="https://sk8.tech/" target="_blank">https://sk8.tech/</a></strong><br/><strong style="color:black">Plugin URL: <a href="https://github.com/SK8-PTY-LTD/NoodleZero-Australia-Fastway-Shipping-Method/" target="_blank">Github Repo</a></strong><br/><a href="http://nz.api.fastway.org/latest/docs/page/GetAPIKey.html" target="_blank" style="font-weight:bold;">Get Fastway API Key</a> ', 'sk8tech-fastwaynz');
				//$this->availability = 'including';

				$this->supports = array(
					'shipping-zones',
					'instance-settings',
					'instance-settings-modal');

				// $this->countries = array(
				// 	'NZ',
				// );
				$this->init();

				//$this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';

				$title = $this->get_option('title');

				$this->title = !empty($title) ? $title : __('Fastway NZ Shipping', 'sk8tech-fastwaynz');

				$this->api_key = $this->get_option('api_key');

				if (empty($this->api_key)) {
					$this->api_key = "b5056fe957ea82692b615808cfd881bc";
				}

				$this->pickup_rfcode = $this->get_option('pickup_rfcode');
				$this->support_type = $this->get_option('support_type');

				$this->custom_pink_zone_parcel_price = $this->settings['custom_pink_zone_parcel_price'];
				$this->custom_blue_zone_parcel_price = $this->settings['custom_blue_zone_parcel_price'];
				$this->custom_bluelt_zone_parcel_price = $this->settings['custom_bluelt_zone_parcel_price'];
				$this->custom_lime_zone_parcel_price = $this->settings['custom_lime_zone_parcel_price'];
				$this->custom_orange_zone_parcel_price = $this->settings['custom_orange_zone_parcel_price'];
				$this->custom_yellow_zone_parcel_price = $this->settings['custom_yellow_zone_parcel_price'];
				$this->custom_local_parcel_price = $this->get_option('custom_local_parcel_price');

			}

			function init() {
				$this->init_form_fields();
				$this->init_settings();
				add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
			}

			function init_form_fields() {

				$rfcode = array("" => "Please Select");
				$api_key = "";
				$formsetting = get_option("woocommerce_fastway_nz_" . $this->instance_id . "_settings");

				if (is_array($formsetting) && count($formsetting) > 0) {
					$api_key = $formsetting["api_key"];
				}

				if (empty($api_key)) {
					$api_key = "b5056fe957ea82692b615808cfd881bc";
				}

				$rfcode = get_option("rfcode_" . $this->instance_id);

				if (!empty($rfcode)) {

					$rfcode = unserialize($rfcode);

				} else {

					if (!empty($api_key)) {
						if (!is_callable('curl_init')) {
							add_action('admin_notices', 'fastway_nz_curl_error');
						}

						$url = "http://nz.api.fastway.org/latest/psc/listrfs?CountryCode=1&api_key=" . $api_key;

						$handle = curl_init($url);

						curl_setopt($handle, CURLOPT_VERBOSE, true);
						curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept: application/json'));

						$content = curl_exec($handle);
						$result = json_decode($content); // show target page
						$fastway_error = get_option("fastway_error_" . $this->instance_id);

						if (isset($result->error)) {
							if ($fastway_error !== false) {
								update_option("fastway_error_" . $this->instance_id, $result->error);
							} else {

								$deprecated = null;
								$autoload = 'no';
								add_option("fastway_error_" . $this->instance_id, $result->error, $deprecated, $autoload);
							}
						} else {
							if ($fastway_error !== false) {
								update_option("fastway_error_" . $this->instance_id, "");
							} else {

								$deprecated = null;
								$autoload = 'no';
								add_option("fastway_error_" . $this->instance_id, "", $deprecated, $autoload);
							}
						}

						if (is_array($result->result)) {
							if (count($result->result) > 0) {
								foreach ($result->result as $v) {
									$rfcode[$v->FranchiseCode] = $v->FranchiseName . "( " . $v->Add1 . " " . $v->Add2 . " " . $v->Add3 . " " . $v->Add4 . " )";
								}
							}
						}

					}

					add_option("rfcode_" . $this->instance_id, serialize($rfcode), null, 'no');
				}

				$this->instance_form_fields = array(
					'enabled' => array(
						'title' => __('Enable', 'sk8tech-fastwaynz'),
						'type' => 'checkbox',
						'description' => __('Enable this shipping.', 'sk8tech-fastwaynz'),
						'default' => 'yes',
					),
					'title' => array(
						'title' => __('Title', 'sk8tech-fastwaynz'),
						'type' => 'text',
						'description' => __('Title to be display on site', 'sk8tech-fastwaynz'),
						'default' => __('Fastway NZ Shipping', 'sk8tech-fastwaynz'),
					),
					'api_key' => array(
						'title' => __('API Key', 'sk8tech-fastwaynz'),
						'type' => 'password',
						'description' => __('<a href="http://nz.api.fastway.org/latest/docs/page/GetAPIKey.html" target="_blank" style="font-weight:bold;">Get Your Own Fastway API Key</a> or leave as empty', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),
					'pickup_rfcode' => array(
						'title' => __('Pickup Franchise', 'sk8tech-fastwaynz'),
						'type' => 'select',
						'description' => __('Options will be presented after API Key was filled and saved ', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
						'options' => $rfcode,
					),
					'support_type' => array(
						'title' => __('Service Type', 'sk8tech-fastwaynz'),
						'type' => 'select',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
						'options' => array("" => "All", "Parcel" => "Parcel"),
					),
					'custom_local_parcel_price' => array(
						'title' => __('Custom Local Parcel Price', 'sk8tech-fastwaynz'),
						'type' => 'decimal',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),
					'custom_pink_zone_parcel_price' => array(
						'title' => __('Custom Pink Parcel Price', 'sk8tech-fastwaynz'),
						'type' => 'decimal',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),
					'custom_blue_zone_parcel_price' => array(
						'title' => __('Custom Blue Parcel Price', 'sk8tech-fastwaynz'),
						'type' => 'decimal',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),
					'custom_bluelt_zone_parcel_price' => array(
						'title' => __('Custom Blue LT Parcel Price', 'sk8tech-fastwaynz'),
						'type' => 'decimal',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),
					'custom_lime_zone_parcel_price' => array(
						'title' => __('Custom Lime Parcel Price', 'sk8tech-fastwaynz'),
						'type' => 'decimal',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),
					'custom_orange_zone_parcel_price' => array(
						'title' => __('Custom Orange Zone Parcel Price', 'sk8tech-fastwaynz'),
						'type' => 'decimal',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),
					'custom_yellow_zone_parcel_price' => array(
						'title' => __('Custom Yellow Zone Parcel Price', 'sk8tech-fastwaynz'),
						'type' => 'decimal',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),
					'custom_parcel_excess_price' => array(
						'title' => __('Custom Parcel Excess Price', 'sk8tech-fastwaynz'),
						'type' => 'decimal',
						'description' => __('', 'sk8tech-fastwaynz'),
						'default' => __('', 'sk8tech-fastwaynz'),
					),

				);

			}

			public function calculate_shipping($package = array()) {

				$weight = 0;
				$quantity = 0;

				$country = $package["destination"]["country"];
				if ($country != "NZ") {
					return;
				}

				$quantity = WC()->cart->get_cart_contents_count();
				$weight = wc_get_weight($weight, 'kg');
				if ($weight > 25) {

					$message = sprintf(__('Sorry, %d kg exceeds the maximum weight of %d kg for %s', 'fastway_nz'), $weight, 25, $this->title);

					$messageType = "error";

					if (!wc_has_notice($message, $messageType)) {

						wc_add_notice($message, $messageType);

					}

					return;
				}

				$d_suburb = urlencode($package["destination"]["city"]);
				$d_suburb = rtrim($d_suburb, " ");
				$d_postcode = urlencode($package["destination"]["postcode"]);
				$d_postcode = rtrim($d_postcode, " ");
				$d_state = urlencode($package["destination"]["state"]);
				$d_country = urlencode($package["destination"]["country"]);

				if (empty($this->pickup_rfcode) || empty($this->api_key)) {
					return;
				}
				if (empty($d_suburb) || empty($d_postcode) || empty($d_state)) {
					return;
				}

				/**
				 * Auto decide FastWay branch from Address
				 * @author Jack
				 */
				$final_rfcode = $this->pickup_rfcode;
				if ($d_country == "New Zealand" || $d_country == "NZ") {
					// All packages destinationed to any state in New Zealand should be delivered from Auckland. Therefore calculate the delivery fee from AUK
					$final_rfcode = "AUK";
				} else {
					// All other places, no delivery offered
					return;
				}

				// Sample Reuqest: http://nz.api.fastway.org/v3/psc/lookup/SYD/Ultimo/2007/20?api_key=
				$handle = curl_init($url);
				$url = "http://nz.api.fastway.org/v3/psc/lookup/" . $final_rfcode . "/" . $d_suburb . "/" . $d_postcode . "/" . ($weight) . "?api_key=" . $this->api_key;
				$url = str_replace('+', '%20', $url);
				$content = file_get_contents($url);

				$result = json_decode($content); // show target page

				$fastway_error = get_option("fastway_error_" . $this->instance_id);

				if (isset($result->error)) {
					if ($fastway_error !== false) {
						update_option("fastway_error_" . $this->instance_id, $result->error);

					} else {

						$deprecated = null;
						$autoload = 'no';
						add_option("fastway_error_" . $this->instance_id, $result->error, $deprecated, $autoload);
					}
				}
				if (isset($result->result)) {

					if ($fastway_error !== false) {
						update_option("fastway_error_" . $this->instance_id, "");

					} else {

						$deprecated = null;
						$autoload = 'no';
						add_option("fastway_error_" . $this->instance_id, "", $deprecated, $autoload);
					}
					$parcel_price = 999999;
					$excess_package = 0;

					$item_count = WC()->cart->get_cart_contents_count();
					$cartTotal = floatval(preg_replace('#[^\d.,]#', '', WC()->cart->get_cart_total()));

					if (count($result->result->services) > 0) {

						foreach ($result->result->services as $k => $r) {

							if ($r->type == "Parcel" && $r->name != "Road (0-2kg)") {
								// Excluding "Road (0-2kg)" from New Zealand Fastway

								$tmp_price = "";
								// $exc_price = $this->custom_parcel_excess_price;

								$exc_price = $r->excess_label_price_frequent;

								if ($r->name == "Local") {
									if ($quantity >= 1 * $this->combo) {

										$rate = array(
											'id' => $this->id . "-parcel",
											'label' => "FREE! " . $this->title . " - Parcel (" . $result->result->delivery_timeframe_days . " Days) ",
											'cost' => 0,
											'taxes' => false,
										);

										$this->add_rate($rate);
										return;
									} else {
										$tmp_price = $this->custom_local_parcel_price;
									}
								} else {
									if ($r->labelcolour == "PINK") {
										if ($quantity >= 1 * $this->combo) {

											$rate = array(
												'id' => $this->id . "-parcel",
												'label' => "FREE! " . $this->title . " - Parcel (" . $result->result->delivery_timeframe_days . " Days) ",
												'cost' => 0,
												'taxes' => false,
											);

											$this->add_rate($rate);
											return;
										} else {
											$tmp_price = $this->custom_pink_zone_parcel_price;
										}
									} else if ($r->labelcolour == "BLUE") {
										if ($quantity >= 1 * $this->combo) {

											$rate = array(
												'id' => $this->id . "-parcel",
												'label' => "FREE! " . $this->title . " - Parcel (" . $result->result->delivery_timeframe_days . " Days) ",
												'cost' => 0,
												'taxes' => false,
											);

											$this->add_rate($rate);
											return;
										} else {
											$tmp_price = $this->custom_blue_zone_parcel_price;
										}
									} else if ($r->labelcolour == "LT BLUE") {
										if ($quantity >= 1 * $this->combo) {

											$rate = array(
												'id' => $this->id . "-parcel",
												'label' => "FREE! " . $this->title . " - Parcel (" . $result->result->delivery_timeframe_days . " Days) ",
												'cost' => 0,
												'taxes' => false,
											);

											$this->add_rate($rate);
											return;
										} else {
											$tmp_price = $this->custom_bluelt_zone_parcel_price;
										}
									} else if ($r->labelcolour == "LIME") {
										if ($quantity >= 1 * $this->combo) {

											$rate = array(
												'id' => $this->id . "-parcel",
												'label' => "FREE! " . $this->title . " - Parcel (" . $result->result->delivery_timeframe_days . " Days) ",
												'cost' => 0,
												'taxes' => false,
											);

											$this->add_rate($rate);
											return;
										} else {
											$tmp_price = $this->custom_lime_zone_parcel_price;
										}
									} else if ($r->labelcolour == "ORANGE") {
										if ($quantity >= 2 * $this->combo) {

											$rate = array(
												'id' => $this->id . "-parcel",
												'label' => "FREE! " . $this->title . " - Parcel (" . $result->result->delivery_timeframe_days . " Days) ",
												'cost' => 0,
												'taxes' => false,
											);

											$this->add_rate($rate);
											return;
										} else {
											$tmp_price = $this->custom_orange_zone_parcel_price;
										}
									} else if ($r->labelcolour == "YELLOW") {
										if ($quantity >= 2 * $this->combo) {

											$rate = array(
												'id' => $this->id . "-parcel",
												'label' => "FREE! " . $this->title . " - Parcel (" . $result->result->delivery_timeframe_days . " Days) ",
												'cost' => 0,
												'taxes' => false,
											);

											$this->add_rate($rate);
											return;
										} else {
											$tmp_price = $this->custom_yellow_zone_parcel_price;
										}
									}
								}

								if (is_numeric($tmp_price)) {
									$exc = $r->excess_labels_required;

									if ($exc > 0) {
										if (is_numeric($exc_price) && !empty($exc_price)) {
											$tmp_price = $tmp_price + ($exc_price * $exc);
										} else {
											$tmp_price = $tmp_price + $r->excess_label_price_normal;
										}
									}

									if ($parcel_price > $tmp_price) {
										$parcel_price = $tmp_price;
									}
								}

								if ($parcel_price > $r->totalprice_frequent && !is_numeric($tmp_price)) {
									$parcel_price = $r->totalprice_frequent;
								}
							}
						}

						if (empty($this->support_type) || $this->support_type == "Parcel") {
							if ($parcel_price != 999999) {

								/**
								 * Use Postcode/ZIP to determine if this method applies
								 * @author Jack
								 */
								$postcode = $package["destination"]["postcode"];

								$rate = array(
									'id' => $this->id . "-parcel",
									'label' => $this->title . " - Parcel (" . $result->result->delivery_timeframe_days . " Days) ",
									'cost' => $parcel_price,
									'taxes' => false,
								);

								$this->add_rate($rate);
							}
						}
					}
				}
			}
		}
	}
}

add_action('woocommerce_shipping_init', 'fastway_nz_shipping_zone_method');

function add_fastway_nz_shipping_zone_method($methods) {
	$methods['fastway_nz-zone'] = 'Fastway_NZ_Shipping_Zone_Method';
	return $methods;
}

add_filter('woocommerce_shipping_methods', 'add_fastway_nz_shipping_zone_method');