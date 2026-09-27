<?php
/**
 * Table reservations: [restaurant_reservation_form] + storage as a private post type.
 *
 * The form POSTs to admin-post.php, which is never cached. Instead of a nonce (which would
 * go stale inside cached HTML) it uses a honeypot field; Nginx rate-limits the endpoint.
 */

defined( 'ABSPATH' ) || exit;

const RC_RES_FIELDS = array( 'name', 'email', 'phone', 'date', 'time', 'party', 'notes' );

add_action( 'init', function () {
	register_post_type( 'rc_reservation', array(
		'labels'          => array(
			'name'          => 'Reservations',
			'singular_name' => 'Reservation',
			'edit_item'     => 'Reservation',
		),
		'public'          => false,
		'show_ui'         => true,
		'menu_icon'       => 'dashicons-calendar-alt',
		'menu_position'   => 6,
		'supports'        => array( 'title', 'custom-fields' ),
		'capability_type' => 'post',
		'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'    => true,
	) );
} );

add_action( 'admin_post_nopriv_rc_reservation', 'rc_handle_reservation' );
add_action( 'admin_post_rc_reservation', 'rc_handle_reservation' );

function rc_handle_reservation() {
	$back = wp_get_referer() ?: home_url( '/reservations/' );
	$back = remove_query_arg( 'reservation', $back );

	// Honeypot: humans never see or fill this field.
	if ( ! empty( $_POST['website'] ) ) {
		wp_safe_redirect( add_query_arg( 'reservation', 'ok', $back ) . '#reservation' );
		exit;
	}

	$data = array();
	foreach ( RC_RES_FIELDS as $field ) {
		$value          = wp_unslash( $_POST[ $field ] ?? '' );
		$data[ $field ] = 'notes' === $field ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}
	$data['email'] = sanitize_email( $data['email'] );
	$data['party'] = (int) $data['party'];

	$valid = $data['name'] && is_email( $data['email'] )
		&& preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data['date'] ) && $data['date'] >= wp_date( 'Y-m-d' )
		&& preg_match( '/^\d{2}:\d{2}$/', $data['time'] )
		&& $data['party'] >= 1 && $data['party'] <= 20;

	if ( ! $valid ) {
		wp_safe_redirect( add_query_arg( 'reservation', 'invalid', $back ) . '#reservation' );
		exit;
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'rc_reservation',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s — %s %s (%d)', $data['name'], $data['date'], $data['time'], $data['party'] ),
	) );
	foreach ( $data as $field => $value ) {
		update_post_meta( $post_id, 'rc_' . $field, $value );
	}

	wp_mail(
		get_option( 'admin_email' ),
		'New reservation: ' . get_the_title( $post_id ),
		implode( "\n", array_map( fn( $k, $v ) => ucfirst( $k ) . ': ' . $v, array_keys( $data ), $data ) )
	);

	wp_safe_redirect( add_query_arg( 'reservation', 'ok', $back ) . '#reservation' );
	exit;
}

add_shortcode( 'restaurant_reservation_form', function () {
	$status  = sanitize_key( $_GET['reservation'] ?? '' );
	$notice  = '';
	if ( 'ok' === $status ) {
		$notice = '<p class="rc-notice rc-notice--ok" role="status">Thank you! We received your request and will confirm by email or phone.</p>';
	} elseif ( 'invalid' === $status ) {
		$notice = '<p class="rc-notice rc-notice--error" role="alert">Please check the form: name, a valid email, a future date, a time, and party size are required.</p>';
	}

	ob_start();
	?>
	<form class="rc-form" id="reservation" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php echo $notice; // phpcs:ignore -- static markup ?>
		<input type="hidden" name="action" value="rc_reservation">
		<div class="rc-form__grid">
			<label>Name <input name="name" required autocomplete="name"></label>
			<label>Email <input type="email" name="email" required autocomplete="email"></label>
			<label>Phone <input type="tel" name="phone" autocomplete="tel"></label>
			<label>Guests
				<select name="party" required>
					<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
						<option value="<?php echo $i; ?>" <?php selected( $i, 2 ); ?>><?php echo $i; ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label>Date <input type="date" name="date" required></label>
			<label>Time <input type="time" name="time" required step="900"></label>
		</div>
		<label>Notes <textarea name="notes" rows="3" placeholder="Allergies, occasion, seating preference…"></textarea></label>
		<label class="rc-hp" aria-hidden="true">Website <input name="website" tabindex="-1" autocomplete="off"></label>
		<button type="submit" class="wp-element-button">Request a table</button>
	</form>
	<?php
	return ob_get_clean();
} );

/* Admin list: show the details at a glance. */
add_filter( 'manage_rc_reservation_posts_columns', function ( $cols ) {
	return array(
		'cb'       => $cols['cb'],
		'title'    => 'Guest',
		'rc_email' => 'Email',
		'rc_phone' => 'Phone',
		'rc_notes' => 'Notes',
		'date'     => 'Received',
	);
} );

add_action( 'manage_rc_reservation_posts_custom_column', function ( $col, $post_id ) {
	echo esc_html( get_post_meta( $post_id, $col, true ) );
}, 10, 2 );
