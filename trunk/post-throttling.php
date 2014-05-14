<?php

/*
Plugin: Pro-sites Post Throttling Module
Plugin URI: http://wpsoft.com.br
Author: diegpl, pkelbert
Author URI: http://wpsoft.com.br/
Description:
Protect your network against spam or sell more posts permission for premium users of pro-sites
Proteja sua rede contra spam ou venda mais permissões de postagem para usuários premium do pro-sites
Version: 1.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: pro-sites,post throttling,pro-sites spam, pro-sites plugin, wpmudev
Text Domain: twentythirteen
*/


class ProSites_Module_Throttling {

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'psts_page_after_modules', array( &$this, 'plug_network_page' ) );
		add_action( 'admin_notices', array( &$this, 'message' ) );
		add_filter( 'user_has_cap', array( &$this, 'write_filter' ), 10, 3 );
	}

	/**
	 * Returns last post publication time or zero if not found.
	 *
	 * @return int
	 */
	private function last_publish_time() {
		$args = array(
			'numberposts'       => 1,
			'orderby'           => 'post_date',
			'order'             => 'desc',
			'post_type'         => 'post',
			'post_status'       => 'publish',
			'suppress_filters'  => true,
		);
		
		$last_posts = wp_get_recent_posts( $args, OBJECT );
		
		if ( count( $last_posts ) == 0 )
			return 0;
		
		$result = get_post_time( 'U', false, $last_posts[0] );
		
		return $result;
	}

	/**
	 * Hook for action "psts_page_after_modules".
	 *
	 * Inserts a submenu for plugin's configurations into "Pro Sites" menu.
	 *
	 * @return void
	 */
	public function plug_network_page() {
		add_submenu_page( 'psts', __( 'Pro Sites Post Throttling', 'psts' ), __( 'Post Throttling', 'psts' ), 'manage_network_options', 'psts-throttling', array( &$this, 'admin_page' ) );
	}

	/**
	 * Handler for plugin's configurations page.
	 *
	 * @return void
	 */
	public function admin_page() {
		global $psts;

		$timeframes = array();
		for ( $hour = 1; $hour <= 23; $hour++ ) {
			$timeframes[] = array(
				'value' => $hour * 3600,
				'label' => $hour . ' hour' . ( $hour > 1) ? 's' : '',
			);
		}
		for ( $day = 1; $day <= 30; $day++ ) {
			$timeframes[] = array(
				'value' => $day * 86400,
				'label' => $day . ' day' . ( $day > 1 ) ? 's' : '',
			);
		}
		
		if ( isset( $_POST['post_throttling'] ) && is_array( $_POST['timeframes'] ) ) {
			$psts_timeframes = $_POST['timeframes'];
			$psts->update_setting( 'throttling_timeframes', $psts_timeframes );
			echo '<div id="message" class="updated fade"><p>' . __( 'Settings Saved!', 'psts' ) . '</p></div>';
		} else {
			$psts_timeframes = (array) $psts->get_setting( 'throttling_timeframes' );
		}
?>
	<div class="wrap">
		<div class="icon32" id="icon-plugins"></div>
		<h2><?php _e( 'Post Throttling', 'psts' ); ?></h2>
		<p><?php _e( 'Select minimum timeframe between posts per Pro Site level.', 'psts' ); ?></p>
		<form method="post" action="">
			<table class="widefat post-throttling">
				<thead>
					<tr>
						<th style="width:5%;"><?php _e( 'Level', 'psts' ) ?></th>
						<th style="width:20%;"><?php _e( 'Name', 'psts' ); ?></th>
						<th><?php _e( 'Minimum timeframe', 'psts' ) ?></th>
					</tr>
				</thead>
				<tbody id="timeframes">
<?php
				$levels = (array) get_site_option( 'psts_levels' );
				foreach ( $levels as $level=>$info ) {
?>
					<tr>
						<td style="text-align:center;"><strong><?php echo $level; ?></strong></td>
						<td><?php echo esc_attr( $info['name'] ); ?></td>
						<td>
							<select name="timeframes[<?php echo $level; ?>]">
								<option value="0"<?php selected( @$psts_timeframes[$level], '0' ); ?>><?php _e( 'None', 'psts' ) ?></option>
<?php							foreach ( $timeframes as $timeframe ) { ?>
								<option value="<?php echo $timeframe['value']; ?>"<?php selected( @$psts_timeframes[$level], $timeframe['value'] ); ?>><?php _e( $timeframe['label'], 'psts' ); ?></option>
<?php							} ?>
							</select>
						</td>
					</tr>
<?php 			} ?>
				</tbody>
			</table>
			<p class="submit">
				<input type="submit" name="post_throttling" class="button-primary" value="<?php _e( 'Save Changes', 'psts' ) ?>" />
			</p>
		</form>
	</div>
<?php
	}

	/**
	 * Hook for action "admin_notices".
	 *
	 * Displays a notification about post publishing limitation.
	 *
	 * @return void
	 */
	public function message() {
		global $psts, $current_screen, $post_type, $blog_id;

		if ( is_pro_site( false, $psts->get_setting( 'pq_level', 1 ) ) )
			return;

		if ( in_array( $current_screen->id, array( 'edit-post', 'post' ) ) ) {
			$settings = $psts->get_setting( "throttling_timeframes" );
			$level = $psts->get_level();
			if ( is_array( $settings ) && $settings[$level] > 0 ) {
				$timeframe = $settings[$level];
				$last_publish_time = $this->last_publish_time();
				$elapsed_time = time() - $last_publish_time;
				if ( $last_publish_time > 0 && $elapsed_time < $timeframe ) {
					$date = date_i18n( __( 'M j, Y @ G:i' ), $last_publish_time + $timeframe );
					$notice = sprintf( __( 'You have to wait until %s to publish your next post.', 'psts' ), $date );
					echo '<div class="updated"><p>' . $notice . '</p></div>';
				}
			}
		}
	}

	/**
	 * Hook for filter "user_has_cap".
	 *
	 * Removes post publishing capability if configured timeframe since last post publication has not been elapsed.
	 *
	 * @return void
	 */
	public function write_filter( $allcaps, $caps, $args ) {
		global $psts;

		$level = $psts->get_level();
		if ( $level > 0 ) {
			$settings = $psts->get_setting( "throttling_timeframes" );
			if ( is_array( $settings ) && $settings[$level] > 0 ) {
				$timeframe = $settings[$level];
				$last_publish_time = $this->last_publish_time();
				$elapsed_time = time() - $last_publish_time;
				if ( $last_publish_time > 0 && $elapsed_time < $timeframe ) {
					$post_type = get_post_type_object( 'post' );
					unset( $allcaps[$post_type->cap->publish_posts] );
				}
			}
		}
		
		return $allcaps;
	}

}

/** Register the module. */
psts_register_module( 'ProSites_Module_Throttling', __( 'Post Throttling', 'psts' ), __( 'Allows you to limit number of post types per days or hours and Pro Site levels.', 'psts' ) );
?>
