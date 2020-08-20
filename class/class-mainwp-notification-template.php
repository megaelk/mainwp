<?php
/**
 * MainWP notification template
 *
 * @package     MainWP/Dashboard
 */

namespace MainWP\Dashboard;

/**
 * Manage notification templates.
 */
class MainWP_Notification_Template {

	/**
	 * Private static variable to hold the single instance of the class.
	 *
	 * @static
	 *
	 * @var mixed Default null.
	 */
	private static $instance = null;


	/**
	 * Template directory.
	 *
	 * @var string Default empty.
	 */
	private $template_path = '';

	/**
	 * Custom template directory.
	 *
	 * @var string Default empty.
	 */
	private $template_custom_path = '';

	/**
	 * Method get_class_name()
	 *
	 * Get Class Name.
	 *
	 * @return string Class name.
	 */
	public static function get_class_name() {
		return __CLASS__;
	}

	/**
	 * Create a new Self Instance.
	 *
	 * @return mixed self::$instance
	 */
	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->template_path        = MAINWP_PLUGIN_DIR . 'templates/';
		$this->template_custom_path = MainWP_System_Utility::get_mainwp_sub_dir( 'templates' );
		// create custom template folders if does not existed.
		MainWP_System_Utility::get_mainwp_sub_dir( 'templates/emails' );
	}

	/**
	 * Get default templates folder.
	 *
	 * @return string folder.
	 */
	public function get_default_templates_dir() {
		return $this->template_path;
	}

	/**
	 * Get custom templates folder.
	 *
	 * @return string folder.
	 */
	public function get_custom_templates_dir() {
		return $this->template_custom_path;
	}

	/**
	 * Get template HTML.
	 *
	 * Credits.
	 *
	 * Plugin-Name: WooCommerce.
	 * Plugin URI: https://woocommerce.com/.
	 * Author: Automattic.
	 * Author URI: https://woocommerce.com.
	 * License: GPLv3 or later.
	 *
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments. (default: array).
	 *
	 * @return string
	 */
	public function get_template_html( $template_name, $args = array() ) {
		return $this->get_template( $template_name, $args );
	}

	/**
	 * Get templates.
	 *
	 * Credits.
	 *
	 * Plugin-Name: WooCommerce.
	 * Plugin URI: https://woocommerce.com/.
	 * Author: Automattic.
	 * Author URI: https://woocommerce.com.
	 * License: GPLv3 or later.
	 *
	 * @param string $template_name Template name.
	 * @param array  $args          Arguments. (default: array).
	 */
	public function get_template( $template_name, $args = array() ) {

		$template = $this->locate_template( $template_name );

		/**
		 * Filter: mainwp_get_template
		 *
		 * Filters available templates and adds support for 3rd party templates.
		 *
		 * @param string $template_name Template name.
		 * @param array  $args          Args.
		 *
		 * @since 4.1
		 */
		$filter_template = apply_filters( 'mainwp_get_template', $template, $template_name, $args );

		if ( $filter_template !== $template ) {
			if ( ! file_exists( $filter_template ) ) {
				return;
			}
			$template = $filter_template;
		}

		$located = $template;

		extract( $args ); // @codingStandardsIgnoreLine

		ob_start();

		/**
		 * Action: mainwp_before_template_part
		 *
		 * Fires before the email template is loaded.
		 *
		 * @param string   $template_name Template name.
		 * @param resource $located Template file.
		 * @param array    $args    Args.
		 *
		 * @since 4.1
		 */
		do_action( 'mainwp_before_template_part', $template_name, $located, $args );

		include $located;

		/**
		 * Action: mainwp_after_template_part
		 *
		 * Fires after the email template is loaded.
		 *
		 * @param string   $template_name Template name.
		 * @param resource $located Template file.
		 * @param array    $args    Args.
		 *
		 * @since 4.1
		 */
		do_action( 'mainwp_after_template_part', $template_name, $located, $args );

		$content = ob_get_clean();

		if ( isset( $current_email_site ) && is_object( $current_email_site ) ) {

			$content = MainWP_Notification_Settings::replace_tokens_for_content( $content, $current_email_site );

			if ( isset( $child_site_tokens ) && ! empty( $child_site_tokens ) ) {

				if ( ! isset( $timestamp_from_date ) || empty( $timestamp_from_date ) || ! isset( $timestamp_to_date ) || empty( $timestamp_to_date ) ) {
					$now_timestamp       = time();
					$now_timestamp       = MainWP_Utility::get_timestamp( $now_timestamp );
					$timestamp_from_date = $now_timestamp - DAY_IN_SECONDS;
					$timestamp_to_date   = $now_timestamp;
				}

				if ( preg_match( '/\[[^\]]+\]/is', $content, $matches ) ) {

					/**
					 * Filter: mainwp_pro_reports_generate_content
					 *
					 * Filters the Pro Reports available content.
					 *
					 * @since 4.1
					 */
					$content = apply_filters( 'mainwp_pro_reports_generate_content', $content, $current_email_site->id, $timestamp_from_date, $timestamp_to_date );

					/**
					 * Filter: mainwp_client_report_generate_content
					 *
					 * Filters the Client Reports available content.
					 *
					 * @since 4.1
					 */
					$content = apply_filters( 'mainwp_client_report_generate_content', $content, $current_email_site->id, $timestamp_from_date, $timestamp_to_date );
				}
			}
		}

		return $content;
	}

	/**
	 * Locate a template and return the path for inclusion.
	 *
	 * Credits.
	 *
	 * Plugin-Name: WooCommerce.
	 * Plugin URI: https://woocommerce.com/.
	 * Author: Automattic.
	 * Author URI: https://woocommerce.com.
	 * License: GPLv3 or later.
	 *
	 * @param string $template_name Template name.
	 * @return string
	 */
	private function locate_template( $template_name ) {

		$template = '';

		// Look within custom path - this is priority.
		$template_path = $this->template_custom_path;
		if ( file_exists( $template_path . $template_name ) ) {
			$template = $template_path . $template_name;
		}

		// Get default template.
		if ( ! $template ) {
			$template_path = $this->template_path;
			$template      = $template_path . $template_name;
		}

		/**
		 * Filer: mainwp_locate_template
		 *
		 * Filters the template location.
		 *
		 * @param $string $template_name Template name.
		 * @param $string $template_path Template path.
		 *
		 * @since 4.1
		 */
		return apply_filters( 'mainwp_locate_template', $template, $template_name, $template_path );
	}

	/**
	 * Check if it is overrided template.
	 *
	 * @param string $type Email type.
	 *
	 * @return bool True|False
	 */
	public function is_overrided_template( $type ) {
		$templ = self::get_template_name_by_notification_type( $type );
		if ( file_exists( $this->template_custom_path . $templ ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get default template name by email/notification type.
	 *
	 * @param string $type email/notification type.
	 *
	 * @return string|null Template name.
	 */
	public static function get_template_name_by_notification_type( $type = '' ) {
		$types = array(
			'daily_digest' => 'emails/mainwp-daily-digest-email.php',
			'uptime'       => 'emails/mainwp-uptime-monitoring-email.php',
			'site_health'  => 'emails/mainwp-site-health-monitoring-email.php',
			'http_check'   => 'emails/mainwp-after-update-http-check-email.php',
		);
		return isset( $types[ $type ] ) ? $types[ $type ] : null;
	}


	/**
	 * Method handle_template_file_action()
	 *
	 * Handle template file action.
	 *
	 * @return bool $done handle result.
	 */
	public function handle_template_file_action() {
		$updated_templ = false;

		$hasWPFileSystem = MainWP_System_Utility::get_wp_file_system();
		global $wp_filesystem;

		$type = isset( $_GET['edit-email'] ) ? $_GET['edit-email'] : '';

		if ( ! empty( $type ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete-email-template' ) ) {
			if ( $hasWPFileSystem ) {
				$dir     = $this->template_custom_path;
				$templ   = self::get_template_name_by_notification_type( $type );
				$deleted = $wp_filesystem->delete( $dir . $templ );
				if ( $deleted ) {
					$updated_templ = 1;
				}
			}
		}

		if ( ! empty( $type ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'copy-email-template' ) ) {
			if ( $hasWPFileSystem ) {
				$source_dir = $this->template_path;
				$dest_dir   = $this->template_custom_path;
				$templ      = self::get_template_name_by_notification_type( $type );
				$copied     = $wp_filesystem->copy( $source_dir . $templ, $dest_dir . $templ );
				if ( $copied ) {
					$updated_templ = 2;
				}
			}
		}

		if ( ! empty( $type ) && isset( $_POST['wp_nonce'] ) && wp_verify_nonce( $_POST['wp_nonce'], 'save-email-template' ) ) {
			$template_name = self::get_template_name_by_notification_type( $type );
			$template_code = isset( $_POST[ 'edit_' . $type . '_code' ] ) ? $_POST[ 'edit_' . $type . '_code' ] : '';
			$updated       = $this->save_template( $template_code, $template_name );
			if ( $updated ) {
				$updated_templ = 3;
			}
		}

		return $updated_templ;
	}

	/**
	 * Save the email templates.
	 *
	 * Credits.
	 *
	 * Plugin-Name: WooCommerce.
	 * Plugin URI: https://woocommerce.com/.
	 * Author: Automattic.
	 * Author URI: https://woocommerce.com.
	 * License: GPLv3 or later.
	 *
	 * @since 4.1
	 * @param string $template_code Template code.
	 * @param string $template Template.
	 */
	public function save_template( $template_code, $template ) {
		if ( current_user_can( 'edit_themes' ) && ! empty( $template_code ) && ! empty( $template ) ) {
			$saved = false;
			$file  = $this->template_custom_path . $template;
			$code  = wp_unslash( $template_code );

			if ( is_writeable( $file ) ) { // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_is_writeable
				$f = fopen( $file, 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

				if ( false !== $f ) {
					fwrite( $f, $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
					fclose( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
					$saved = true;
				}
			}

			if ( $saved ) {
				return true;
			}
		}
		return false;
	}

}