<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'gform_tooltips', array( 'GFHANNANSMS_Pro_Settings', 'tooltips' ) );

class GFHANNANSMS_Pro_Settings {

	protected static function check_access( $required_permission ) {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		return GFCommon::current_user_can_any( $required_permission );
	}

	public static function tooltips( $tooltips ) {

		$tooltips["admin_default"] = __( "You can set several numbers. Separate with commas(,). for example : +16175551212,+16175551213. this numbers is default and you can change them later.", "GF_SMS" );
		$tooltips["show_credit"]   = __( "Activating this section is not recommended; since you must connect to the webservice provider every time you wish to check your credit’s status and this might cause the wordpress admin to reduce in speed.", "GF_SMS" );
		$tooltips["country_code"]  = __( "Your Mobile Country code. like : +1", "GF_SMS" );
		$tooltips["gf_sms_sender"] = __( "Separate with commas (,)", "GF_SMS" );
		$tooltips["show_adminbar"] = __( "Show Ready Studio SMS menu in adminbar?", "GF_SMS" ); // Rebranded
		$tooltips["sidebar_ajax"]  = __( "Activate this option to replace merge tags value in SMS Sidebar via ajax (Entry details)", "GF_SMS" );

		return $tooltips;
	}


	public static function settings() {
		
		// We don't need chosen.js anymore since the gateway select is removed
		// wp_enqueue_script('GF_SMS_Chosen', GF_SMS_URL . '/assets/chosen_v1.8.5/chosen.jquery.min.js', array(), true);
		// wp_enqueue_style('GF_SMS_Chosen', GF_SMS_URL . '/assets/chosen_v1.8.5/chosen.min.css');

		$settings = GFHANNANSMS_Pro::get_option();

		// Hard-code the gateway to msgway
		$G_code = 'msgway';

		// Get options for msgway
		$gateway_options = get_option( "gf_hannansms_msgway" );

		if ( ! rgempty( "uninstall" ) ) {

			check_admin_referer( "uninstall", "gf_hannansms_uninstall" );

			if ( ! self::check_access( "gravityforms_hannansms_uninstall" ) ) {
				die( __( "You don't have adequate permission to uninstall Ready Studio SMS.", "GF_SMS" ) ); // Rebranded
			} else {

				GFHANNANSMS_Pro_SQL::drop_table();

				delete_option( "gf_sms_settings" );
				delete_option( "gf_sms_version" );
				delete_option( "gf_sms_installed" );
				delete_option( "gf_sms_last_sender" );

				// Only delete the gateways we know about (or just msgway)
				delete_option( "gf_hannansms_msgway" );
				delete_option( "gf_hannansms_mellipayamak" ); // Also delete old one just in case

				$plugin = GF_SMS_DIR . "/gravity_sms_pro.php";

				update_option( 'recently_activated', array( $plugin => time() ) + (array) get_option( 'recently_activated' ) );

				deactivate_plugins( $plugin );
				?>

                <div class="updated fade" style="padding:20px;">
					<?php
					// Rebranded
					echo sprintf( __( "Ready Studio SMS have been successfully uninstalled. It can be re-activated from the %splugins page%s.", "GF_SMS" ), "<a href='plugins.php'>", "</a>" )
					?>
                </div>

				<?php
			}

			return false;
		} else if ( ! rgempty( "gf_hannansms_submit" ) ) {

			check_admin_referer( "update", "gf_hannansms_update" );

			// Save settings
			$settings = array(
				"user_name"    => rgpost( "gf_hannansms_user_name" ), // This and password seem unused by msgway, but we keep them just in case
				"password"     => rgpost( "gf_hannansms_password" ),
				"from"         => rgpost( "gf_hannansms_from" ),
				"code"         => rgpost( "gf_hannansms_code" ),
				"to"           => rgpost( "gf_hannansms_to" ),
				"ws"           => 'msgway', // Hard-coded gateway
				"cr"           => rgpost( "gf_hannansms_showcr" ),
				"menu"         => rgpost( "gf_hannansms_menu" ),
				"sidebar_ajax" => rgpost( "gf_hannansms_sidebar_ajax" )
			);

			update_option( "gf_sms_settings", array_map( 'sanitize_text_field', $settings ) );

			// Save gateway options for msgway
			$Saved_Gateway = 'GFHANNANSMS_Pro_MSGWAY'; // Hard-coded

			if ( class_exists( $Saved_Gateway ) && method_exists( $Saved_Gateway, 'options' ) ) {

				$gateway_options = array();

				foreach ( (array) $Saved_Gateway::options() as $option => $name ) {
					$gateway_options[ $option ] = sanitize_text_field( rgpost( "gf_hannansms_msgway_" . $option ) );
				}

				update_option( "gf_hannansms_msgway", $gateway_options ); // Hard-coded
			}


			if ( ! headers_sent() ) {
				wp_redirect( admin_url( 'admin.php?page=gf_settings&subview=gf_sms_pro&updated=true' ) );
				exit;
			}
		}

		if ( rgget( 'updated' ) == 'true' ) {
			echo '<div class="updated fade" style="padding:6px">' . __( "Settings updated.", "GF_SMS" ) . '</div>';
		}
		?>

        <form method="post" action="">

			<?php wp_nonce_field( "update", "gf_hannansms_update" ) ?>

            <h3><span><i
                            class="fa fa fa-mobile"></i><?php echo '   ' . __( "تنظیمات پیامک Ready Studio", "GF_SMS" ) . '   '; // Rebranded ?></span>
            </h3>

			<?php
			// Ensure gateways class is loaded before calling credit
			if ( ! class_exists( 'GFHANNANSMS_Pro_WebServices' ) ) {
				require_once( GF_SMS_DIR . 'includes/gateways.php' );
			}
			
			// Show credit if settings are saved
			if ( $G_code == strtolower( $settings["ws"] ) && $credit = GFHANNANSMS_Pro::credit( true ) ) {

				preg_match( '/([\d]+)/', $credit, $match );
				$credit_int = isset( $match[0] ) ? $match[0] : $credit;

				$range = GFHANNANSMS_Pro::range();

				$max = isset( $range["max"] ) ? $range["max"] : 500;
				$min = isset( $range["min"] ) ? $range["min"] : 2;

				if ( intval( $credit_int ) >= $max ) {
					$color = '#008000';
				} else if ( intval( $credit_int ) < $max && intval( $credit_int ) >= $min ) {
					$color = '#FFC600';
				} else {
					$color = '#FF1454';
				}
				?>

                <h5><?php _e( "Your SMS Credit : ", "GF_SMS" ) ?><span
                            style="color:<?php echo $color; ?> !important;"><?php echo $credit; ?></span></h5>

				<?php
			}
			?>

            <hr/>

            <table class="form-table">
                
                <!-- Gateway selection removed - hard-coded to msgway -->
                <input type="hidden" id="gf_hannansms_ws" name="gf_hannansms_ws" value="msgway" />

				<?php

				// Load MsgWay options directly
				$Gateway = 'GFHANNANSMS_Pro_MSGWAY';

				if ( class_exists( $Gateway ) && method_exists( $Gateway, 'options' ) ) {

					$flag = true; // Flag to show credit toggle

					foreach ( (array) $Gateway::options() as $option => $name ) { ?>
                        <tr>
                            <th scope="row"><label
                                        for="gf_hannansms_<?php echo $G_code . '_' . $option; ?>"><?php echo $name; ?></label>
                            </th>
                            <td width="340">
                                <input type="text" id="gf_hannansms_<?php echo $G_code . '_' . $option; ?>"
                                       name="gf_hannansms_<?php echo $G_code . '_' . $option; ?>"
                                       value="<?php echo isset( $gateway_options[ $option ] ) ? esc_attr( $gateway_options[ $option ] ) : ''; ?>" size="50"
                                       style="padding: 5px; direction:ltr !important;text-align:left;"/>
                            </td>
                            <td rowspan="2" valign="middle">
                            </td>
                        </tr>
					<?php }
				}
				?>

                <tr>
                    <th scope="row">
                        <label for="gf_hannansms_from">
							<?php _e( "Sender (From)", "GF_SMS" ); ?>
							<?php gform_tooltip( 'gf_sms_sender' ) ?>
                        </label>

                    </th>
                    <td width="340">

                        <input type="text" id="gf_hannansms_from" name="gf_hannansms_from"
                               value="<?php echo isset( $settings["from"] ) ? esc_attr( $settings["from"] ) : ''; ?>" size="50"
                               style="padding: 5px; direction:ltr !important;text-align:left;"/><br/>
                    </td>
                </tr>


                <tr>
                    <th scope="row">
                        <label for="gf_hannansms_code">
							<?php _e( "Your Default Country Code", "GF_SMS" ); ?>
							<?php gform_tooltip( 'country_code' ) ?>
                        </label>
                    </th>
                    <td width="340">

                        <input type="text" id="gf_hannansms_code" name="gf_hannansms_code"
                               value="<?php echo isset( $settings["code"] ) ? esc_attr( $settings["code"] ) : ''; ?>" size="50"
                               style="padding: 5px; direction:ltr !important;text-align:left;"/><br/>

                    </td>
                </tr>


               <!-- <tr>
                    <th scope="row">
                        <label for="gf_hannansms_to">
							<?php /*_e( "Admin Default Numbers", "GF_SMS" ); */?>
							<?php /*gform_tooltip( 'admin_default' ) */?>
                        </label>
                    </th>
                    <td width="340">

                        <input type="text" id="gf_hannansms_to" name="gf_hannansms_to"
                               value="<?php /*echo esc_attr( $settings["to"] ) */?>" size="50"
                               style="padding: 5px; direction:ltr !important;text-align:left;"/><br/>

                    </td>
                </tr>-->

				<?php if ( ! empty( $flag ) && ! empty( $Gateway ) && $Gateway::credit() ) { ?>

                    <tr>
                        <th scope="row">
                            <label for="gf_hannansms_showcr">
								<?php _e( "Show Credit/Balance", "GF_SMS" ); ?>
								<?php gform_tooltip( 'show_credit' ) ?>
                            </label>
                        </th>
                        <td width="340">


                            <input type="radio" name="gf_hannansms_showcr" id="gf_hannansms_showcr_show"
                                   value="Show" <?php echo ( isset( $settings["cr"] ) && esc_attr( $settings["cr"] ) == "Show" ) ? "checked='checked'" : "" ?>/>
                            <label class="inline"
                                   for="gf_hannansms_showcr_show"><?php _e( "Yes", "GF_SMS" ); ?></label>&nbsp;&nbsp;&nbsp;

                            <input type="radio" name="gf_hannansms_showcr" id="gf_hannansms_showcr_no"
                                   value="No" <?php echo ( ! isset( $settings["cr"] ) || esc_attr( $settings["cr"] ) != "Show" ) ? "checked='checked'" : "" ?>/>
                            <label class="inline"
                                   for="gf_hannansms_showcr_no"><?php _e( "No ( Recommended )", "GF_SMS" ); ?></label>

                            <br/>

                        </td>
                    </tr>

					<?php
				} ?>

                <tr>
                    <th scope="row">
                        <label for="gf_hannansms_menu">
							<?php _e( "Admin Bar Menu", "GF_SMS" ); ?>
							<?php gform_tooltip( 'show_adminbar' ) ?>
                        </label>
                    </th>
                    <td width="340">


                        <input type="radio" name="gf_hannansms_menu" id="gf_hannansms_menu_show"
                               value="Show" <?php echo ( isset( $settings["menu"] ) && esc_attr( $settings["menu"] ) == "Show" ) ? "checked='checked'" : "" ?>/>
                        <label class="inline" for="gf_hannansms_menu_show"><?php _e( "Yes", "GF_SMS" ); ?></label>&nbsp;&nbsp;&nbsp;


                        <input type="radio" name="gf_hannansms_menu" id="gf_hannansms_menu_no"
                               value="No" <?php echo ( ! isset( $settings["menu"] ) || esc_attr( $settings["menu"] ) != "Show" ) ? "checked='checked'" : "" ?>/>
                        <label class="inline" for="gf_hannansms_menu_no"><?php _e( "No", "GF_SMS" ); ?></label>

                        <br/>

                    </td>
                </tr>


                <tr>
                    <th scope="row">
                        <label for="gf_hannansms_sidebar_ajax">
							<?php _e( "Replace merge tags value in SMS Sidebar", "GF_SMS" ); ?>
							<?php gform_tooltip( 'sidebar_ajax' ) ?>
                        </label>
                    </th>
                    <td width="340">


                        <input type="radio" name="gf_hannansms_sidebar_ajax" id="gf_hannansms_sidebar_ajax_Yes"
                               value="Yes" <?php echo ( empty( $settings["sidebar_ajax"] ) || esc_attr( $settings["sidebar_ajax"] ) != "No" ) ? "checked='checked'" : "" ?>/>
                        <label class="inline"
                               for="gf_hannansms_sidebar_ajax_Yes"><?php _e( "Yes", "GF_SMS" ); ?></label>&nbsp;&nbsp;&nbsp;

                        <input type="radio" name="gf_hannansms_sidebar_ajax" id="gf_hannansms_sidebar_ajax_no"
                               value="No" <?php echo ( ! empty( $settings["sidebar_ajax"] ) && esc_attr( $settings["sidebar_ajax"] ) == "No" ) ? "checked='checked'" : "" ?>/>
                        <label class="inline" for="gf_hannansms_sidebar_ajax_no"><?php _e( "No", "GF_SMS" ); ?></label>

                        <br/>

                    </td>
                </tr>


                <tr>
                    <th scope="row">
                        <input type="submit" name="gf_hannansms_submit" class="button-primary"
                               value="<?php _e( "Save Settings", "GF_SMS" ) ?>"/>
                    </th>
                </tr>

            </table>


        </form>
        <form action="" method="post">
			<?php wp_nonce_field( "uninstall", "gf_hannansms_uninstall" ) ?>
			<?php if ( self::check_access( "gravityforms_hannansms_uninstall" ) ) { ?>

                <div class="hr-divider"></div>
                <div class="delete-alert alert_red">
                    <h3><?php _e( "Uninstall Ready Studio SMS", "GF_SMS" ) // Rebranded ?></h3>
                    <div
                            class="gf_delete_notice"><?php _e( "<strong>Warning!</strong> This operation deletes ALL Ready Studio SMS Informations.", "GF_SMS" ) // Rebranded ?></div>
                    <input type="submit" name="uninstall"
                           value="<?php _e( "Uninstall Ready Studio SMS", "GF_SMS" ) // Rebranded ?>" class="button"
                           onclick="return confirm('<?php _e( "Warning! ALL Ready Studio SMS informations will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "GF_SMS" ) // Rebranded ?>'); "/>
                </div>
			<?php } ?>
        </form>


        <script type="text/javascript">
            // This function is no longer needed as the dropdown is removed.
            /*
            function GF_SwitchGateway(code) {
                new_query = "gateway=" + code;
                document.location = document.location + "&" + new_query;
            }
            */

            jQuery(document).ready(function () {
                // This is no longer needed
                // jQuery(".select-gateway").chosen();
            });
        </script>
		<?php
	}
}

if ( defined( 'GF_SMS_GATEWAY' ) ) {

	$files = scandir( GF_SMS_GATEWAY );

	if ( $files ) {

		foreach ( (array) $files as $file ) {

			$path_parts = pathinfo( GF_SMS_GATEWAY . $file );

			if ( strpos( $file, '.php' ) ) {

				// Only load our desired gateway
				if ( $path_parts['filename'] == 'msgway' ) {

					include 'gateways/' . $path_parts['filename'] . '.php';

					$Gateway = 'GFHANNANSMS_Pro_' . strtoupper( $path_parts['filename'] );

					if ( class_exists( $Gateway ) ) {
						if ( method_exists( $Gateway, 'options' ) && method_exists( $Gateway, 'process' ) && method_exists( $Gateway, 'name' ) ) {
							add_filter( 'gf_sms_gateways', array( $Gateway, 'name' ) );
						}
					}
				}
				// Do not load mellipayamak or any other gateway
			}
		}
	}
}

