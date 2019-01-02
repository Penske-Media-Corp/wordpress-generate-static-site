<?php
// Include this in your site's functions.php

/**
 * VIP 'protected-iframe' shortcode.
 * [protected-iframe id="#" info="#" width="100%" height="200"]
 *
 * @param $atts
 *
 * @return string
 */
function protected_iframe_shortcode_clone( $atts ) {

	$atts = shortcode_atts(
		array(
			'id'     => '#',
			'info'   => '#',
			'width'  => '100%',
			'height' => '200',
			'scrolling' => 'no',
		),
		$atts
	);

	return sprintf(
		'<iframe id="%s" src="%s" width=%s height=%s scrolling="%s" ></iframe>',
		esc_html( $atts['id'] ),
		esc_url( $atts['info'] ),
		esc_attr( $atts['width'] ),
		esc_attr( $atts['height'] ),
		esc_html( $atts['scrolling'] )
	);
}
add_shortcode( 'protected-iframe', 'protected_iframe_shortcode_clone' );

/**
 * Import/Migrate users from a CSV file and set new id to existing posts.
 *
 * If the user already exists (matching the email address or login), then
 * the user is updated unless the `--skip-update` flag is used.
 *
 * ## OPTIONS
 *
 * <file>
 * : The local or remote CSV file of users to import. If '-', then reads from STDIN.
 *
 * ## EXAMPLES
 *
 *     # Import users from local CSV file
 *     $ wp pmc-users-import /path/to/users.csv
 *     Success: bobjones created
 *     Success: newuser1 created
 *     Success: existinguser created
 *
 *     # Import users from remote CSV file
 *     $ wp user import-csv http://example.com/users.csv
 *
 *     Sample users.csv file:
 *
 *     user_login,user_email,display_name,role,ID
 *     bobjones,bobjones@example.com,Bob Jones,contributor,1
 *     newuser1,newuser1@example.com,New User,author,2
 *     existinguser,existinguser@example.com,Existing User,administrator,3
 */
function migrate_from_csv( $args, $assoc_args ) {

	$filename = $args[0];

	if ( ! file_exists( $filename ) ) {
		WP_CLI::error( sprintf( "Missing file: %s", $filename ) );
	}

	// Don't send core's emails during the creation / update process
	add_filter( 'send_password_change_email', '__return_false' );
	add_filter( 'send_email_change_email', '__return_false' );

	$csv_data = new \WP_CLI\Iterators\CSV( $filename, '	' );

	// Update old id with new id for posts_author.
	global $wpdb;

	foreach ( $csv_data as $i => $new_user ) {
		$defaults = array(
			'role' => get_option('default_role'),
			'user_pass' => wp_generate_password(),
			'user_registered' => strftime( "%F %T", time() ),
			'display_name' => false,
		);
		$new_user = array_merge( $defaults, $new_user );

		$secondary_roles = array();
		if ( ! empty( $new_user['roles'] ) ) {
			$roles = array_map( 'trim', explode( ',', $new_user['roles'] ) );
			$invalid_role = false;
			foreach( $roles as $role ) {
				if ( is_null( get_role( $role ) ) ) {
					WP_CLI::warning( "{$new_user['user_login']} has an invalid role." );
					$invalid_role = true;
					break;
				}
			}
			if ( $invalid_role ) {
				continue;
			}
			$new_user['role'] = array_shift( $roles );
			$secondary_roles = $roles;
		} else if ( 'none' === $new_user['role'] ) {
			$new_user['role'] = false;
		} elseif ( is_null( get_role( $new_user['role'] ) ) ) {
			WP_CLI::warning( "{$new_user['user_login']} has an invalid role." );
			continue;
		}

		// User already exists and we just need to add them to the site if they aren't already there
		$existing_user = get_user_by( 'email', $new_user['user_email'] );

		if ( ! empty( $existing_user ) ) {
			WP_CLI::log( "{$existing_user->user_login} exists and has been skipped." );
			continue;
		} else {
			$old_id = $new_user['ID'];
			unset( $new_user['ID'] ); // Unset else it will just return the ID

			if ( is_multisite() ) {
				$ret = wpmu_validate_user_signup( $new_user['user_login'], $new_user['user_email'] );
				if ( is_wp_error( $ret['errors'] ) && ! empty( $ret['errors']->errors ) ) {
					WP_CLI::warning( $ret['errors'] );
					continue;
				}
				$user_id = wpmu_create_user( $new_user['user_login'], $new_user['user_pass'], $new_user['user_email'] );
				if ( ! $user_id ) {
					WP_CLI::warning( "Unknown error creating new user." );
					continue;
				}
				$new_user['ID'] = $user_id;
				$user_id = wp_update_user( $new_user );
				if ( is_wp_error( $user_id ) ) {
					WP_CLI::warning( $user_id );
					continue;
				}
			} else {
				$user_id = wp_insert_user( $new_user );
			}

			if ( is_wp_error( $user_id ) ) {
				WP_CLI::warning( $user_id );
				continue;

			} else if ( $new_user['role'] === false ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}

			update_user_meta( $user_id, 'pmc_old_user_id', $old_id );

			$updated_posts = $wpdb->update(
				$wpdb->posts,
				array(
					'post_author' => $user_id
				),
				array(
					'post_author' => $old_id,
				)
			);

			if ( empty( $updated_posts ) ) {
				WP_CLI::success( "There is no post found to migrate for user: " . $new_user['user_login'] );
			} else {
				WP_CLI::success( "(" . $updated_posts . ") Post old id updated for user: " . $new_user['user_login'] );
			}

			$user = get_user_by( 'id', $user_id );
			foreach( $secondary_roles as $secondary_role ) {
				$user->add_role( $secondary_role );
			}

			if ( !empty( $existing_user ) ) {
				WP_CLI::success( $new_user['user_login'] . " updated." );
			} else {
				WP_CLI::success( $new_user['user_login'] . " created." );
			}
		}
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'pmc-users-import', 'migrate_from_csv' );
}

// EOF
