<?php
/**
 * Restaurant info: settings page (Settings → Restaurant) + helper functions.
 */

defined( 'ABSPATH' ) || exit;

const RC_DAYS = array(
	'mon' => 'Monday',
	'tue' => 'Tuesday',
	'wed' => 'Wednesday',
	'thu' => 'Thursday',
	'fri' => 'Friday',
	'sat' => 'Saturday',
	'sun' => 'Sunday',
);

function rc_info_defaults() {
	$hours = array();
	foreach ( array_keys( RC_DAYS ) as $day ) {
		$hours[ $day ] = array( 'open' => '', 'close' => '' );
	}
	return array(
		'phone'       => '',
		'email'       => '',
		'street'      => '',
		'city'        => '',
		'region'      => '',
		'postal'      => '',
		'country'     => '',
		'cuisine'     => '',
		'price_range' => '$$',
		'currency'    => '$',
		'map_query'   => '',
		'hours'       => $hours,
	);
}

/** Get one field, or the whole info array when $key is null. */
function rc_info( $key = null ) {
	$info = wp_parse_args( get_option( 'restaurant_info', array() ), rc_info_defaults() );
	return null === $key ? $info : ( $info[ $key ] ?? '' );
}

function rc_address_line() {
	$info  = rc_info();
	$parts = array_filter( array( $info['street'], $info['city'], trim( $info['region'] . ' ' . $info['postal'] ), $info['country'] ) );
	return implode( ', ', $parts );
}

/**
 * Group consecutive days with identical hours: "Monday – Friday: 11:00 – 22:00".
 *
 * @return array<int, array{label:string, hours:string}>
 */
function rc_hours_rows() {
	$hours = rc_info( 'hours' );
	$rows  = array();
	$keys  = array_keys( RC_DAYS );

	$start = null;
	$prev  = null;
	foreach ( $keys as $i => $day ) {
		$open  = $hours[ $day ]['open'] ?? '';
		$close = $hours[ $day ]['close'] ?? '';
		$value = ( $open && $close ) ? "$open – $close" : __( 'Closed', 'restaurant-core' );

		if ( null === $start ) {
			$start = $day;
		} elseif ( $value !== $prev ) {
			$rows[] = rc_hours_row( $start, $keys[ $i - 1 ], $prev );
			$start  = $day;
		}
		$prev = $value;
	}
	$rows[] = rc_hours_row( $start, end( $keys ), $prev );
	return $rows;
}

function rc_hours_row( $from, $to, $value ) {
	$label = $from === $to ? RC_DAYS[ $from ] : RC_DAYS[ $from ] . ' – ' . RC_DAYS[ $to ];
	return array( 'label' => $label, 'hours' => $value );
}

/* ---------------------------------------------------------------------------
 * Settings page
 * ------------------------------------------------------------------------- */

add_action( 'admin_init', function () {
	register_setting( 'restaurant_info', 'restaurant_info', array(
		'type'              => 'array',
		'sanitize_callback' => 'rc_sanitize_info',
		'default'           => rc_info_defaults(),
	) );
} );

function rc_sanitize_info( $input ) {
	$clean = rc_info_defaults();
	foreach ( $clean as $key => $default ) {
		if ( 'hours' === $key || ! isset( $input[ $key ] ) ) {
			continue;
		}
		$clean[ $key ] = 'email' === $key ? sanitize_email( $input[ $key ] ) : sanitize_text_field( $input[ $key ] );
	}
	foreach ( array_keys( RC_DAYS ) as $day ) {
		foreach ( array( 'open', 'close' ) as $edge ) {
			$time = $input['hours'][ $day ][ $edge ] ?? '';
			$clean['hours'][ $day ][ $edge ] = preg_match( '/^\d{2}:\d{2}$/', $time ) ? $time : '';
		}
	}
	return $clean;
}

add_action( 'admin_menu', function () {
	add_options_page( 'Restaurant', 'Restaurant', 'manage_options', 'restaurant-info', 'rc_render_settings_page' );
} );

function rc_render_settings_page() {
	$info   = rc_info();
	$fields = array(
		'phone'       => 'Phone',
		'email'       => 'Email',
		'street'      => 'Street address',
		'city'        => 'City',
		'region'      => 'State / Region',
		'postal'      => 'Postal code',
		'country'     => 'Country',
		'cuisine'     => 'Cuisine (e.g. Vietnamese)',
		'price_range' => 'Price range (e.g. $$)',
		'currency'    => 'Currency symbol',
		'map_query'   => 'Map search (defaults to address)',
	);
	?>
	<div class="wrap">
		<h1>Restaurant</h1>
		<p>Name and tagline come from <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">Settings → General</a>.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'restaurant_info' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $label ) : ?>
					<tr>
						<th scope="row"><label for="rc-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input class="regular-text" id="rc-<?php echo esc_attr( $key ); ?>" name="restaurant_info[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $info[ $key ] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row">Opening hours<br><small>Leave empty for closed</small></th>
					<td>
						<?php foreach ( RC_DAYS as $day => $label ) : ?>
							<p>
								<span style="display:inline-block;width:7em"><?php echo esc_html( $label ); ?></span>
								<input type="time" name="restaurant_info[hours][<?php echo esc_attr( $day ); ?>][open]" value="<?php echo esc_attr( $info['hours'][ $day ]['open'] ); ?>">
								–
								<input type="time" name="restaurant_info[hours][<?php echo esc_attr( $day ); ?>][close]" value="<?php echo esc_attr( $info['hours'][ $day ]['close'] ); ?>">
							</p>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ---------------------------------------------------------------------------
 * Shortcodes: [restaurant_hours] [restaurant_contact] [restaurant_map]
 * ------------------------------------------------------------------------- */

add_shortcode( 'restaurant_hours', function () {
	$out = '<dl class="rc-hours">';
	foreach ( rc_hours_rows() as $row ) {
		$out .= sprintf( '<div class="rc-hours__row"><dt>%s</dt><dd>%s</dd></div>', esc_html( $row['label'] ), esc_html( $row['hours'] ) );
	}
	return $out . '</dl>';
} );

add_shortcode( 'restaurant_contact', function () {
	$info = rc_info();
	$out  = '<address class="rc-contact">';
	if ( $address = rc_address_line() ) {
		$out .= '<p class="rc-contact__address">' . esc_html( $address ) . '</p>';
	}
	if ( $info['phone'] ) {
		$tel  = preg_replace( '/[^\d+]/', '', $info['phone'] );
		$out .= '<p><a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $info['phone'] ) . '</a></p>';
	}
	if ( $info['email'] ) {
		$out .= '<p><a href="mailto:' . esc_attr( $info['email'] ) . '">' . esc_html( $info['email'] ) . '</a></p>';
	}
	return $out . '</address>';
} );

add_shortcode( 'restaurant_map', function () {
	$query = rc_info( 'map_query' ) ?: rc_address_line();
	if ( ! $query ) {
		return '';
	}
	return sprintf(
		'<div class="rc-map"><iframe title="%s" src="%s" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>',
		esc_attr__( 'Map showing our location', 'restaurant-core' ),
		esc_url( 'https://www.google.com/maps?output=embed&q=' . rawurlencode( $query ) )
	);
} );
