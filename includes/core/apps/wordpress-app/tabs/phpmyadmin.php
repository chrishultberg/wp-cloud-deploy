<?php
/**
 * PHPMYADMIN Database tab.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPCD_WORDPRESS_TABS_PHPMYADMIN.
 */
class WPCD_WORDPRESS_TABS_PHPMYADMIN extends WPCD_WORDPRESS_TABS {

	/**
	 * WPCD_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
		/* add_filter( 'wpcd_is_ssh_successful', array( $this, 'was_ssh_successful' ), 10, 5 ); */

		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: wpcd_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the server cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed( $id, $name ) {

		if ( get_post_type( $id ) !== 'wpcd_app' ) {
			return;
		}

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905.
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run.
		// [1] => cf1110.wpvix.com.
		// [2] => 911.
		$command_array = explode( '---', $name );

		// if the command is to install phpmyadmin then we need to update some postmeta items in the app with the database user id, password and phpmyadmin status.
		if ( 'install_phpmyadmin' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'manage_phpmyadmin.txt' );

			if ( true == $success ) {

				// We need to parse the log files to find.
				// 1. user name.
				// 2. password.
				// 3. url string.

				// split the logs into an array.
				$logs_array = explode( "\n", $logs );

				// 1. user name.
				$searchword   = 'User:';
				$matches_user = array_filter(
					$logs_array,
					function( $var ) use ( $searchword ) {
						return strpos( $var, $searchword ) !== false;
					}
				);
				// 2. password.
				$searchword       = 'Password:';
				$matches_password = array_filter(
					$logs_array,
					function( $var ) use ( $searchword ) {
						return strpos( $var, $searchword ) !== false;
					}
				);

				if ( ! empty( $matches_user ) && count( $matches_user ) == 1 ) {

					update_post_meta( $id, 'wpapp_phpmyadmin_status', 'on' );
					update_post_meta( $id, 'wpapp_phpmyadmin_user_id', array_values( $matches_user )[0] );
					update_post_meta( $id, 'wpapp_phpmyadmin_user_password', $this::encrypt( array_values( $matches_password )[0] ) );

				}
			}
		}

		// if the command is to remove phpmyadmin then we need to remove some postmeta items and update phpmyadmin status.
		if ( 'remove_phpmyadmin' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'manage_phpmyadmin.txt' );

			if ( true == $success ) {

					update_post_meta( $id, 'wpapp_phpmyadmin_status', 'off' );
					delete_post_meta( $id, 'wpapp_phpmyadmin_user_id' );
					delete_post_meta( $id, 'wpapp_phpmyadmin_user_password' );

			}
		}

		// if the command is to switch remote database then we need to update postmeta items and update remote database status.
		if ( 'switch_remote' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'manage_database_operation.txt' );

			if ( true == $success ) {

				// Indicate that we are connecting to a remote database.
				$this->enable_remote_db_flag( $id );

				update_post_meta( $id, 'wpapp_db_host', get_post_meta( $id, 'wpapp_temp_db_host', true ) );
				update_post_meta( $id, 'wpapp_db_port', get_post_meta( $id, 'wpapp_temp_db_port', true ) );
				update_post_meta( $id, 'wpapp_db_name', get_post_meta( $id, 'wpapp_temp_db_name', true ) );
				update_post_meta( $id, 'wpapp_db_user', get_post_meta( $id, 'wpapp_temp_db_user', true ) );
				update_post_meta( $id, 'wpapp_db_pass', get_post_meta( $id, 'wpapp_temp_db_pass', true ) );

			}
		}

		// if the command is to switch local database then we need to update postmeta items and update remote database status.
		if ( 'switch_local' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = $this->is_ssh_successful( $logs, 'manage_database_operation.txt' );

			if ( true === $success ) {

				$this->disable_remote_db_flag( $id );

				update_post_meta( $id, 'wpapp_db_host', get_post_meta( $id, 'wpapp_temp_db_host', true ) );
				update_post_meta( $id, 'wpapp_db_port', get_post_meta( $id, 'wpapp_temp_db_port', true ) );
				update_post_meta( $id, 'wpapp_db_name', get_post_meta( $id, 'wpapp_temp_db_name', true ) );
				update_post_meta( $id, 'wpapp_db_user', get_post_meta( $id, 'wpapp_temp_db_user', true ) );
				update_post_meta( $id, 'wpapp_db_pass', get_post_meta( $id, 'wpapp_temp_db_pass', true ) );

			}
		}

		// Copy database from local to remote.
		if ( 'copy_to_remote' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$this->is_ssh_successful( $logs, 'manage_database_operation.txt' );

		}

		// Copy database from remote to local.
		if ( 'copy_to_local' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$this->is_ssh_successful( $logs, 'manage_database_operation.txt' );

		}

		// Remove some metas that may be present.
		delete_post_meta( $id, 'wpapp_temp_db_host' );
		delete_post_meta( $id, 'wpapp_temp_db_port' );
		delete_post_meta( $id, 'wpapp_temp_db_name' );
		delete_post_meta( $id, 'wpapp_temp_db_user' );
		delete_post_meta( $id, 'wpapp_temp_db_pass' );

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'database';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_phpmyadmin_tab';
	}
	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 * @param int   $id   The post ID of the server.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs, $id ) {
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Database', 'wpcd' ),
				'icon'  => 'fad fa-database',
			);
		}
		return $tabs;
	}

	/**
	 * Checks whether or not the user can view the current tab.
	 *
	 * @param int $id The post ID of the site.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		// If admin has an admin lock in place and the user is not admin they cannot view the tab or perform actions on them.
		if ( $this->get_admin_lock_status( $id ) && ! wpcd_is_admin() ) {
			return false;
		}
		// If we got here then check team and other permissions.
		return ( true === $this->wpcd_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->wpcd_can_author_view_site_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Called when an action needs to be performed on the tab.
	 *
	 * @param mixed  $result The default value of the result.
	 * @param string $action The action to be performed.
	 * @param int    $id The post ID of the app.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->wpcd_user_can_view_wp_app( $id ) ) {
			return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array( 'install-phpmyadmin', 'update-phpmyadmin', 'remove-phpmyadmin', 'remote-database', 'local-database', 'copy-database-from-local-to-remote', 'copy-database-from-remote-to-local' );
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'wpcd' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'install-phpmyadmin':
					$result = $this->manage_phpmyadmin( 'install_phpmyadmin', $id );
					break;
				case 'update-phpmyadmin':
					$result = $this->manage_phpmyadmin( 'update_phpmyadmin', $id );
					break;
				case 'remove-phpmyadmin':
					$result = $this->manage_phpmyadmin( 'remove_phpmyadmin', $id );
					break;
				case 'remote-database':
					$result = $this->manage_remote_db( 'switch_remote', $id );
					break;
				case 'local-database':
					$result = $this->manage_remote_db( 'switch_local', $id );
					break;
				case 'copy-database-from-local-to-remote':
					$result = $this->manage_remote_db( 'copy_to_remote', $id );
					break;
				case 'copy-database-from-remote-to-local':
					$result = $this->manage_remote_db( 'copy_to_local', $id );
					break;

			}
		}

		return $result;

	}

	/**
	 * Manage phpmyadmin - add, remove, update/upgrade
	 *
	 * @param string $action The action key to send to the bash script.  This is actually the key of the drop-down select.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function manage_phpmyadmin( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'manage_phpmyadmin.txt',
			array(
				'command' => $command,
				'action'  => $action,
				'domain'  => $domain,
			)
		);
		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			return new \WP_Error( sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action ) );
		}

		/**
		 * Run the constructed commmand
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;

	}

	/**
	 * Manage remote database - Switch and copy local/remote database.
	 *
	 * @param string $action The action key to send to the bash script.  This is actually the key of the drop-down select.
	 * @param int    $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function manage_remote_db( $action, $id ) {

		// Get the instance details.
		$instance = $this->get_app_instance_details( $id );

		// Bail if error.
		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		// Get params of fields.
		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Get the domain we're working on.
		$domain = $this->get_domain_name( $id );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// construct the run command.
		switch ( $action ) {

			case 'switch_local':
				$local_dbname = $args['dbname_for_local_database'];
				$local_dbuser = $args['dbuser_for_local_database'];
				$local_dbpass = $args['dbpass_for_local_database'];

				// Double-check just in case of errors.
				if ( empty( $local_dbname ) || empty( $local_dbuser ) || empty( $local_dbpass ) ) {
					return new \WP_Error( __( 'Database name, username & password required for the switch to the local database', 'wpcd' ) );
				}

				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_database_operation.txt',
					array(
						'command'      => $command,
						'action'       => $action,
						'domain'       => $domain,
						'local_dbname' => $local_dbname,
						'local_dbuser' => $local_dbuser,
						'local_dbpass' => $local_dbpass,
					)
				);

				// Update some temporary metas that we'll use later if the command succeeds.
				update_post_meta( $id, 'wpapp_temp_db_host', 'localhost' );
				update_post_meta( $id, 'wpapp_temp_db_port', '3301' );
				update_post_meta( $id, 'wpapp_temp_db_name', $local_dbname );
				update_post_meta( $id, 'wpapp_temp_db_user', $local_dbuser );
				update_post_meta( $id, 'wpapp_temp_db_pass', self::encrypt( $local_dbpass ) );

				break;
			case 'switch_remote':
				$remote_dbhost = $args['dbhost_for_remote_database'];
				$remote_dbport = $args['dbport_for_remote_database'];
				$remote_dbname = $args['dbname_for_remote_database'];
				$remote_dbuser = $args['dbuser_for_remote_database'];
				$remote_dbpass = $args['dbpass_for_remote_database'];

				// Double-check just in case of errors.
				if ( empty( $remote_dbhost ) || empty( $remote_dbname ) || empty( $remote_dbuser ) || empty( $remote_dbpass ) ) {
					return new \WP_Error( __( 'Database name, host, username & password required for the switch to the remote database', 'wpcd' ) );
				}

				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_database_operation.txt',
					array(
						'command'       => $command,
						'action'        => $action,
						'domain'        => $domain,
						'remote_dbhost' => $remote_dbhost,
						'remote_dbport' => $remote_dbport,
						'remote_dbname' => $remote_dbname,
						'remote_dbuser' => $remote_dbuser,
						'remote_dbpass' => $remote_dbpass,
					)
				);

				// Update some temporary metas that we'll use later if the command succeeds.
				update_post_meta( $id, 'wpapp_temp_db_host', $remote_dbhost );
				update_post_meta( $id, 'wpapp_temp_db_port', $remote_dbport );
				update_post_meta( $id, 'wpapp_temp_db_name', $remote_dbname );
				update_post_meta( $id, 'wpapp_temp_db_user', $remote_dbuser );
				update_post_meta( $id, 'wpapp_temp_db_pass', self::encrypt( $remote_dbpass ) );

				break;
			case 'copy_to_remote':
				$remote_dbhost_for_copy = $args['remote_dbhost_for_copy'];
				$remote_dbport_for_copy = $args['remote_dbport_for_copy'];
				$remote_dbname_for_copy = $args['remote_dbname_for_copy'];
				$remote_dbuser_for_copy = $args['remote_dbuser_for_copy'];
				$remote_dbpass_for_copy = $args['remote_dbpass_for_copy'];

				// Double-check just in case of errors.
				if ( empty( $remote_dbhost_for_copy ) || empty( $remote_dbname_for_copy ) || empty( $remote_dbuser_for_copy ) || empty( $remote_dbpass_for_copy ) ) {
					return new \WP_Error( __( 'Database name, host, username & password required for the copy database from local to remote', 'wpcd' ) );
				}

				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_database_operation.txt',
					array(
						'command'       => $command,
						'action'        => $action,
						'domain'        => $domain,
						'remote_dbhost' => $remote_dbhost_for_copy,
						'remote_dbport' => $remote_dbport_for_copy,
						'remote_dbname' => $remote_dbname_for_copy,
						'remote_dbuser' => $remote_dbuser_for_copy,
						'remote_dbpass' => $remote_dbpass_for_copy,
					)
				);
				break;
			case 'copy_to_local':
				$local_dbname_for_copy = $args['local_dbname_for_copy'];
				$local_dbuser_for_copy = $args['local_dbuser_for_copy'];
				$local_dbpass_for_copy = $args['local_dbpass_for_copy'];

				// Double-check just in case of errors.
				if ( empty( $local_dbname_for_copy ) || empty( $local_dbuser_for_copy ) || empty( $local_dbpass_for_copy ) ) {
					return new \WP_Error( __( 'Database name, username & password required for the copy database from remote to local', 'wpcd' ) );
				}

				$run_cmd = $this->turn_script_into_command(
					$instance,
					'manage_database_operation.txt',
					array(
						'command'      => $command,
						'action'       => $action,
						'domain'       => $domain,
						'local_dbname' => $local_dbname_for_copy,
						'local_dbuser' => $local_dbuser_for_copy,
						'local_dbpass' => $local_dbpass_for_copy,
					)
				);
				break;
		}

		// double-check just in case of errors.
		if ( empty( $run_cmd ) || is_wp_error( $run_cmd ) ) {
			return new \WP_Error( sprintf( __( 'Something went wrong - we are unable to construct a proper command for this action - %s', 'wpcd' ), $action ) );
		}

		/**
		 * Run the constructed commmand
		 * Check out the write up about the different aysnc methods we use
		 * here: https://wpclouddeploy.com/documentation/wpcloud-deploy-dev-notes/ssh-execution-models/
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;

	}

	/**
	 * Toggle local status for phpmyadmin
	 *
	 * @param int $id the id of the app post being handled.
	 *
	 * @return boolean|object Can return wp_error, true/false
	 */
	public function toggle_local_status_phpmyadmin( $id ) {

		// get current local memcached status.
		$pa_status = get_post_meta( $id, 'wpapp_phpmyadmin_status', true );
		if ( empty( $pa_status ) ) {
			$pa_status = 'off';
		}

		// whats the new status going to be?
		if ( 'on' === $pa_status ) {
			$new_pa_status = 'off';
		} else {
			$new_pa_status = 'on';
		}

		// update it.
		update_post_meta( $id, 'wpapp_phpmyadmin_status', $new_pa_status );

		// Force refresh?
		if ( ! is_wp_error( $result ) ) {
			$result = array(
				'msg'     => __( 'The local PHPMyAdmin status has been toggled.', 'wpcd' ),
				'refresh' => 'yes',
			);
		} else {
			$result = false;
		}

		return $result;

	}

	/**
	 * Gets the fields to be shown.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields( array $fields, $id ) {

		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( 'database' ) );
		}

		$fields = $this->get_php_myadmin_fields( $fields, $id );

		if ( wpcd_is_admin() ) {
			$fields = $this->get_local_remote_db_fields( $fields, $id );
		}

		return $fields;

	}

	/**
	 * Gets the fields to be shown for the local-remote db switch management section.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_local_remote_db_fields( array $fields, $id ) {

		// Remote Database Options.
		$is_remote_database = $this->is_remote_db( $id );

		// Current database settings.
		$current_db_host           = get_post_meta( $id, 'wpapp_db_host', true );
		$current_db_port           = get_post_meta( $id, 'wpapp_db_port', true );
		$current_db_name           = get_post_meta( $id, 'wpapp_db_name', true );
		$current_db_user           = get_post_meta( $id, 'wpapp_db_user', true );
		$current_db_pass           = get_post_meta( $id, 'wpapp_db_pass', true );
		$current_db_pass_decrypted = self::decrypt( $current_db_pass );

		// Set some text for the header depending on whether we have a local or remote database in use.
		if ( 'yes' === $is_remote_database ) {
			$section_heading              = __( 'Remote Database - Switch To Local Database [Beta]', 'wpcd' );
			$running_database_server_name = __( 'This site is currently using a remote database. To switch to a local database please enter the local database information and click the SWITCH button.', 'wpcd' );
		} else {
			$section_heading              = __( 'Local Database - Switch To Remote Database [Beta]', 'wpcd' );
			$running_database_server_name = __( 'This site is currently using a local database.  To switch to a remote database please enter the remote database information and click the SWITCH button.', 'wpcd' );
		}
		$running_database_server_name .= '<br />' . __( 'Please note that that switching databases does NOT automatically copy the database between local and remote or vice-versa.', 'wpcd' );
		$running_database_server_name .= '<br />' . __( 'You can use the options in the COPY DATABASE section below to copy the data BEFORE switching the database.', 'wpcd' );

		// Show the current database info if we have it.
		if ( ! empty( $current_db_host ) || ! empty( $current_db_port ) || ! empty( $current_db_name ) || ! empty( $current_db_user ) || ! empty( $current_db_pass_decrypted ) ) {
			$fields[] = array(
				'name' => __( 'Current Database Connection Information', 'wpcd' ),
				'tab'  => 'database',
				'type' => 'heading',
				'desc' => __( 'The following is the information we have saved from prior database operations. If you have directly updated the wp-config.php file then this information is likely not correct.', 'wpcd' ),
			);

			$db_info  = sprintf( __( 'Host: %s', 'wpcd' ), $current_db_host );
			$db_info .= '<br />';
			$db_info .= sprintf( __( 'Port: %s', 'wpcd' ), $current_db_port );
			$db_info .= '<br />';
			$db_info .= sprintf( __( 'DB Name: %s', 'wpcd' ), $current_db_name );
			$db_info .= '<br />';
			$db_info .= sprintf( __( 'DB User: %s', 'wpcd' ), $current_db_user );
			$db_info .= '<br />';
			$db_info .= sprintf( __( 'DB Pass: %s', 'wpcd' ), $current_db_pass_decrypted );

			$fields[] = array(
				'tab'  => 'database',
				'type' => 'custom_html',
				'std'  => $db_info,
			);

		}

		// Section for switching between remote and local databases.
		$fields[] = array(
			'name' => $section_heading,
			'tab'  => 'database',
			'type' => 'heading',
			'desc' => $running_database_server_name,
		);

		// Switch To Remote Database.
		if ( $is_remote_database === 'yes' ) {

			// Local database name.
			$fields[] = array(
				'id'         => 'local-dbname',
				'name'       => __( 'DB Name:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'local-dbname',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'dbname_for_local_database',
				),
			);

			// Local database username.
			$fields[] = array(
				'id'         => 'local-dbuser',
				'name'       => __( 'DB Username:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'local-dbuser',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'dbuser_for_local_database',
					'spellcheck'       => 'false',
				),
			);

			// Local database password.
			$fields[] = array(
				'id'         => 'local-dbpass',
				'name'       => __( 'DB Password:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'password',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'local-dbpass',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'dbpass_for_local_database',
					'spellcheck'       => 'false',
				),
			);

			// Switch to local database.
			$fields[] = array(
				'id'         => 'local-database',
				'name'       => '',
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Switch To Local Database', 'wpcd' ),
				'attributes' => array(
					// Get User Name & Password.
					'data-wpcd-fields'              => json_encode( array( '#local-dbname', '#local-dbuser', '#local-dbpass' ) ),
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'local-database',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like switch to local database?', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing switch to local database.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			// Copy database remote to local.
			$fields[] = array(
				'name' => __( 'Copy Database - Remote to local [Beta]', 'wpcd' ),
				'tab'  => 'database',
				'type' => 'heading',
				'desc' => __( 'Enter connection information for the local database.', 'wpcd' ),
			);

			// Local database name.
			$fields[] = array(
				'id'         => 'local-dbname-for-copy',
				'name'       => __( 'DB Name:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'local-dbname-for-copy',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'local_dbname_for_copy',
				),
			);

			// Local database username.
			$fields[] = array(
				'id'         => 'local-dbuser-for-copy',
				'name'       => __( 'DB Username:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'local-dbuser-for-copy',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'local_dbuser_for_copy',
					'spellcheck'       => 'false',
				),
			);

			// Local database password.
			$fields[] = array(
				'id'         => 'local-dbpass-for-copy',
				'name'       => __( 'DB Password:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'password',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'local-dbpass-for-copy',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'local_dbpass_for_copy',
					'spellcheck'       => 'false',
				),
			);

			// Copy database from remote to local.
			$fields[] = array(
				'id'         => 'copy-database-from-remote-to-local',
				'name'       => '',
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Copy Database From Remote To Local', 'wpcd' ),
				'attributes' => array(
					// Get User Name & Password.
					'data-wpcd-fields'              => json_encode( array( '#local-dbname-for-copy', '#local-dbuser-for-copy', '#local-dbpass-for-copy' ) ),
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'copy-database-from-remote-to-local',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like copy database from remote to local?', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to copy your database from remote to local.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

		} else {

			// Remote database host.
			$fields[] = array(
				'id'         => 'remote-dbhost',
				'name'       => __( 'DB Host:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					'data-wpcd-action' => 'remote-dbhost',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'dbhost_for_remote_database',
				),
			);

			// Remote database port.
			$fields[] = array(
				'id'         => 'remote-dbport',
				'name'       => __( 'DB Port:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'remote-dbport',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'dbport_for_remote_database',
				),
			);

			// Remote database name.
			$fields[] = array(
				'id'         => 'remote-dbname',
				'name'       => __( 'DB Name:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'remote-dbname',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'dbname_for_remote_database',
				),
			);

			// Remote database username.
			$fields[] = array(
				'id'         => 'remote-dbuser',
				'name'       => __( 'DB Username:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'remote-dbuser',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'dbuser_for_remote_database',
					'spellcheck'       => 'false',
				),
			);

			// Remote database password.
			$fields[] = array(
				'id'         => 'remote-dbpass',
				'name'       => __( 'DB Password:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'password',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'remote-dbpass',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'dbpass_for_remote_database',
					'spellcheck'       => 'false',
				),
			);

			// Switch to remote database.
			$fields[] = array(
				'id'         => 'remote-database',
				'name'       => '',
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Switch To Remote Database', 'wpcd' ),
				'attributes' => array(
					// Get User Name & Password.
					'data-wpcd-fields'              => json_encode( array( '#remote-dbhost', '#remote-dbport', '#remote-dbname', '#remote-dbuser', '#remote-dbpass' ) ),
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'remote-database',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like switch to remote database?', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to switch to remote database.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			// Copy database local to remote.
			$fields[] = array(
				'name' => __( 'Copy Database - Local to remote [Beta]', 'wpcd' ),
				'tab'  => 'database',
				'type' => 'heading',
				'desc' => __( 'To copy your data to a remote database please enter the connection information for the remote database and then click the COPY DATABASE button.', 'wpcd' ),
			);

			// Remote database host.
			$fields[] = array(
				'id'         => 'remote-dbhost-for-copy',
				'name'       => __( 'DB Host:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					'data-wpcd-action' => 'remote-dbhost-for-copy',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'remote_dbhost_for_copy',
				),
			);

			// Remote database port.
			$fields[] = array(
				'id'         => 'remote-dbport-for-copy',
				'name'       => __( 'DB Port:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'remote-dbport-for-coy',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'remote_dbport_for_copy',
				),
			);

			// Remote database name.
			$fields[] = array(
				'id'         => 'remote-dbname-for-copy',
				'name'       => __( 'DB Name:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'remote-dbname-for-copy',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'remote_dbname_for_copy',
				),
			);

			// Remote database username.
			$fields[] = array(
				'id'         => 'remote-dbuser-for-copy',
				'name'       => __( 'DB Username:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'text',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'remote-dbuser-for-copy',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'remote_dbuser_for_copy',
					'spellcheck'       => 'false',
				),
			);

			// Remote database password.
			$fields[] = array(
				'id'         => 'remote-dbpass-for-copy',
				'name'       => __( 'DB Password:', 'wpcd' ),
				'tab'        => 'database',
				'type'       => 'password',
				'attributes' => array(
					'desc'             => '',
					// the _action that will be called in ajax.
					'data-wpcd-action' => 'remote-dbpass-for-copy',
					'std'              => '',
					// the key of the field (the key goes in the request).
					'data-wpcd-name'   => 'remote_dbpass_for_copy',
					'spellcheck'       => 'false',
				),
			);

			// Copy database from local to remote.
			$fields[] = array(
				'id'         => 'copy-database-from-local-to-remote',
				'name'       => '',
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Copy Database From Local To Remote', 'wpcd' ),
				'attributes' => array(
					// Get User Name & Password.
					'data-wpcd-fields'              => json_encode( array( '#remote-dbhost-for-copy', '#remote-dbport-for-copy', '#remote-dbname-for-copy', '#remote-dbuser-for-copy', '#remote-dbpass-for-copy' ) ),
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'copy-database-from-local-to-remote',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like copy database from local to remote?', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to copy database from local to remote.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);
		}

		return $fields;

	}

	/**
	 * Gets the fields to be shown for the phpmyadmin section.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_php_myadmin_fields( array $fields, $id ) {

		// Bail if certain 6G or 7G firewall items are enabled.
		$fw_6g = get_post_meta( $id, 'wpapp_6g_status', true );
		$fw_7g = get_post_meta( $id, 'wpapp_7g_status', true );
		if ( ! empty( $fw_6g ) && ! empty( $fw_6g['6g_query_string'] ) && 'on' === $fw_6g['6g_query_string'] ) {
			$fields[] = array(
				'name' => __( 'Database Management With PHPMyAdmin [Disabled]', 'wpcd' ),
				'tab'  => 'database',
				'type' => 'heading',
				'desc' => __( 'You must disable the 6G firewall QUERY STRING rules before PHPMyAdmin can be used.', 'wpcd' ),
			);
			return $fields;
		}
		if ( ! empty( $fw_7g ) && ! empty( $fw_7g['7g_query_string'] ) && 'on' === $fw_7g['7g_query_string'] ) {
			$fields[] = array(
				'name' => __( 'Database Management With PHPMyAdmin [Disabled]', 'wpcd' ),
				'tab'  => 'database',
				'type' => 'heading',
				'desc' => __( 'You must disable the 7G firewall QUERY STRING and REQUEST STRING rules before PHPMyAdmin can be used.', 'wpcd' ),
			);
			return $fields;
		}
		if ( ! empty( $fw_7g ) && ! empty( $fw_7g['7g_query_string'] ) && 'on' === $fw_7g['7g_request_string'] ) {
			$fields[] = array(
				'name' => __( 'Database Management With PHPMyAdmin [Disabled]', 'wpcd' ),
				'tab'  => 'database',
				'type' => 'heading',
				'desc' => __( 'You must disable the 7G firewall QUERY STRING and REQUEST STRING rules before PHPMyAdmin can be used.', 'wpcd' ),
			);
			return $fields;
		}
		// End Bail if certain 6G or 7G firewall items are enabled.

		$desc  = __( 'Use PHPMyAdmin to access and manage the data in your WordPress database.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'This is a very powerful tool with which you can easily corrupt your database beyond repair.', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'Before performing any actions with it, we urge you to backup your site!', 'wpcd' );
		$desc .= '<br />';
		$desc .= __( 'Finally, because this tool is accessible from the internet we suggest that you remove it when you are done using it.  Or, at least, restrict access to it by your IP address.', 'wpcd' );

		$fields[] = array(
			'name' => __( 'Database', 'wpcd' ),
			'tab'  => 'database',
			'type' => 'heading',
			'desc' => $desc,
		);

		// What is the status of PHPMyAdmin?
		$pa_status = get_post_meta( $id, 'wpapp_phpmyadmin_status', true );
		if ( empty( $pa_status ) ) {
			$pa_status = 'off';
		}

		if ( 'off' == $pa_status ) {
			// PHPMyAdmin is not installed on this site, so show button to install it.
			$fields[] = array(
				'id'         => 'install-phpmyadmin',
				'name'       => '',
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Install PHPMyAdmin', 'wpcd' ),
				'desc'       => '',
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'install-phpmyadmin',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to install the PHPMyAdmin tool?', 'wpcd' ),
					// show log console?
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to install PHPMyAdmin.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);
		} else {

			// use custom html to show a launch link.
			$phpmyadmin_user_id  = get_post_meta( $id, 'wpapp_phpmyadmin_user_id', true );
			$phpmyadmin_password = $this::decrypt( get_post_meta( $id, 'wpapp_phpmyadmin_user_password', true ) );

			// Remove any "user:" and "Password:" phrases that might be embedded inside the user id and password strings.
			$phpmyadmin_user_id  = str_replace( 'User:', '', $phpmyadmin_user_id );
			$phpmyadmin_password = str_replace( 'Password:', '', $phpmyadmin_password );

			if ( true === $this->get_site_local_ssl_status( $id ) ) {
				$phpmyadmin_url = 'https://' . $this->get_domain_name( $id ) . '/' . 'phpMyAdmin';
			} else {
				$phpmyadmin_url = 'http://' . $this->get_domain_name( $id ) . '/' . 'phpMyAdmin';
			}

			$launch              = sprintf( '<a href="%s" target="_blank">', $phpmyadmin_url ) . __( 'Launch PHPMyAdmin', 'wpcd' ) . '</a>';
			$phpmyadmin_details  = '<div class="wpcd_tool_details">';
			$phpmyadmin_details .= __( 'User Id: ', 'wpcd' ) . wpcd_wrap_clipboard_copy( $phpmyadmin_user_id );
			$phpmyadmin_details .= '</div>';

			$phpmyadmin_details .= '<div class="wpcd_tool_details">';
			$phpmyadmin_details .= __( 'Password: ', 'wpcd' ) . wpcd_wrap_clipboard_copy( $phpmyadmin_password );
			$phpmyadmin_details .= '</div>';

			if ( true === wpcd_is_admin() ) {
				// We'll be showing the options to switch local and remote databases to admins.
				// So need to warn them that phpMyAdmin needs to be reinstalled if they do so.
				$phpmyadmin_details .= '<br />';
				$phpmyadmin_details .= '<hr />' . __( 'Admin Note: If you have switched between local and remote databases AFTER installing PHPMyAdmin, it is critical that you uninstall and reinstall it so that the new database information is entered into the PHPMyAdmin configuration files.  Otherwise you will end up connecting to the old database! ', 'wpcd' ) . '<hr />';
			}

			$fields[] = array(
				'tab'   => 'database',
				'type'  => 'custom_html',
				'std'   => $launch,
				'class' => 'button',
			);
			$fields[] = array(
				'tab'  => 'database',
				'type' => 'custom_html',
				'std'  => $phpmyadmin_details,
			);

			// new fields section for update and remove of phpmyadmin.
			$fields[] = array(
				'name' => __( 'Database Tools - Update and Remove', 'wpcd' ),
				'tab'  => 'database',
				'type' => 'heading',
				'desc' => __( 'Update and/or remove PHPMyAdmin', 'wpcd' ),
			);

			// update php my admin.
			$fields[] = array(
				'id'         => 'update-phpmyadmin',
				'name'       => '',
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Update PHPMyAdmin', 'wpcd' ),
				'desc'       => __( 'Update the PHPMyAdmin tool to the latest version.', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'update-phpmyadmin',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to update the PHPMyAdmin tool? This is a risky operation and should only be done if there are security issues that need to be addressed!', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to update PHPMyAdmin.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);

			// remove phpmyadmin.
			$fields[] = array(
				'id'         => 'remove-phpmyadmin',
				'name'       => '',
				'tab'        => 'database',
				'type'       => 'button',
				'std'        => __( 'Remove PHPMyAdmin', 'wpcd' ),
				'desc'       => __( 'Remove the PHPMyAdmin tool from this site.', 'wpcd' ),
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-wpcd-action'              => 'remove-phpmyadmin',
					// the id.
					'data-wpcd-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-wpcd-confirmation-prompt' => __( 'Are you sure you would like to remove the PHPAdmin tool from this site?', 'wpcd' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to remove PHPMyAdmin.<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
				),
				'class'      => 'wpcd_app_action',
				'save_field' => false,
			);
		}

		return $fields;
	}

}

new WPCD_WORDPRESS_TABS_PHPMYADMIN();
