<?php
/*
Plugin Name: WP SHORTSCORE
Description: Present your SHORTSCORES in a review box at the end of your posts.
Plugin URI:  http://shortscore.org
Version:     3.0
Text Domain: wp-shortscore
Domain Path: /language
Author:      MarcDK, lephilde
Author URI:  http://marc.tv
License URI: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/**
 * Class WpShortscore
 */
class WpShortscore {
	private $version = '3.0';

	/**
	 * WpShortscore constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'wan_load_textdomain' ) );

		$this->frontendInit();

		if ( is_admin() ) {
			$this->frontendAdminInit();
			add_action( 'save_post', array( $this, 'saveUserInput' ) );
			add_action( 'add_meta_boxes', array( $this, 'shortscore_custom_meta' ) );
			//add_action( 'admin_notices', array( $this, 'wp_shortscore_message' ) );
		}
	}

	/**
	 * Load textdomain
	 */
	public function wan_load_textdomain() {
		load_plugin_textdomain( 'wp-shortscore', false, dirname( plugin_basename( __FILE__ ) ) . '/language/' );
	}

	/**
	 * Initialise frontend methods
	 */
	public function frontendInit() {
		add_action( 'wp_print_styles', array( $this, 'enqueScripts' ) );
		add_filter( 'the_content', array( $this, 'appendShortscore' ),99 );
	}

	public function frontendAdminInit() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueAdminScripts' ) );
	}

	/*
	 * helper method to save meta data to a post.
	 * */
	public function savePostMeta( $post_ID, $meta_name, $meta_value ) {
		add_post_meta( $post_ID, $meta_name, $meta_value, true ) || update_post_meta( $post_ID, $meta_name, $meta_value );
	}

	/**
	 * Pull Shortscore data by using the shortscore id and save it to the post.
	 *
	 * @param $post_id
	 *
	 */
	public function saveUserInput( $post_id ) {
		// Checks save status
		$is_autosave    = wp_is_post_autosave( $post_id );
		$is_revision    = wp_is_post_revision( $post_id );
		$is_valid_nonce = ( isset( $_POST['shortscore_nonce'] ) && wp_verify_nonce( $_POST['shortscore_nonce'], basename( __FILE__ ) ) ) ? 'true' : 'false';

		// Exits script depending on save status
		if ( $is_autosave || $is_revision || ! $is_valid_nonce ) {
			return;
		}

		// Get the author's nickname
		$post_author_id  = get_post_field( 'post_author', $post_id );
		$author_nickname = get_the_author_meta( 'nickname', $post_author_id );

		// Checks for input and sanitizes/saves if needed
		$title                 = $this->getPostData( '_shortscore_game_title' );
		$shortscore_userrating = $this->getPostData( '_shortscore_user_rating' );
		$shortscore_summary = $this->getPostData( '_shortscore_summary' );


		if ( function_exists( 'get_post_meta' ) ) {

			// JSON structure (defined as array)
			$result = [
				'game' => [
					'id'    => - 1,
					'url'   => get_permalink(),
					'title' => $title,
					'count' => 0
				],

				'shortscore' => [
					'userscore' => $shortscore_userrating,
					'url'       => get_permalink(),
					'author'    => $author_nickname,
					'summary'   => $shortscore_summary,
					'date'      => get_the_date( DateTime::ISO8601 ),
					'id'        => - 1
				],
			];

			if ( $title != '' OR $shortscore_userrating != '' ) {
				$this->savePostMeta( $post_id, '_shortscore_result', $result );
				$this->savePostMeta( $post_id, '_shortscore_user_rating', $shortscore_userrating );
			}

			if ( isset($_POST['delete_shortscore']) ) {
				delete_post_meta( $post_id, '_shortscore_result' );
			}

			$msg_code = 'success';

			add_filter( 'redirect_post_location', function ( $location ) use ( $msg_code ) {
				return add_query_arg( 'wp-shortscore-msg', $msg_code, $location );
			} );
		}

		return;
	}

	/**
	 * Display Shortscore review box below a post
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function appendShortscore( $content ) {
		if ( is_single() ) {
			$post_id = get_the_ID();
			if ( metadata_exists( 'post', $post_id, '_shortscore_result' ) ) {
				$content = $content . $this->getShortscoreHTML();
			}
		}

		// Returns the content.
		return $content;

	}

	/**
	 * Load CSS in the theme for the SHORTSCORE styling.
	 */
	public function enqueScripts() {
		if ( is_single() && get_post_meta( get_the_ID(), '_shortscore_result', true ) != '' ) {
			wp_enqueue_style(
				"shortscore-base", plugins_url( 'shortscore-base.css', __FILE__ ), $this->version );

			wp_enqueue_style(
				"shortscore-rating", plugins_url( 'shortscore-rating.css', __FILE__ ), true, $this->version );
		}
	}

	/**
	 * Load CSS in the admin backend for the SHORTSCORE styling.
	 */
	public function enqueAdminScripts() {

		wp_enqueue_style(
			"shortscore-base", plugins_url( 'shortscore-base.css', __FILE__ ), $this->version );

		wp_enqueue_style(
			"shortscore-rangeslider", plugins_url( 'rangeslider/rangeslider.css', __FILE__ ), $this->version );

		wp_enqueue_style(
			"shortscore-rating", plugins_url( 'shortscore-rating.css', __FILE__ ), array(), $this->version );

		wp_enqueue_script(
			'shortscore-rangeslider', plugins_url( 'rangeslider/rangeslider.js', __FILE__ ), array( "jquery" ), $this->version );

		wp_enqueue_script(
			'shortscore-rangeslider-init', plugins_url( 'rangeslider/rangeslider.init.js', __FILE__ ), array(
			"jquery",
			"shortscore-rangeslider"
		), $this->version );


	}

	/**
	 * Adds a meta box to the post editing screen
	 */
	public function shortscore_custom_meta() {
		add_meta_box( 'shortscore_meta', __( 'Add SHORTSCORE', 'wp-shortscore' ), array(
			$this,
			'shortscore_meta_callback'
		), 'post', 'advanced', 'high' );
	}

	public function object_to_array( $d ) {
		if ( is_object( $d ) ) {
			$d = get_object_vars( $d );
		}

		return is_array( $d ) ? array_map( __METHOD__, $d ) : $d;
	}


	/**
	 * Outputs the content of the meta box
	 */
	public function shortscore_meta_callback( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'shortscore_nonce' );
		$shortscore_stored_meta = get_post_meta( $post->ID );

		$shortscore = '';

		if ( isset ( $shortscore_stored_meta['_shortscore_result'] ) ) {
			$result = $this->object_to_array( get_post_meta( $post->ID, '_shortscore_result', true ) );

			if ( isset( $result['shortscore'] ) AND isset( $result['shortscore']['userscore'] ) ) {
				$shortscore = $result['shortscore']['userscore'];
			}

			if ( isset( $result['shortscore'] ) AND isset( $result['shortscore']['summary'] ) ) {
				$shortscore_summary = $result['shortscore']['summary'];
			}

			if ( array_key_exists( 'game', $result ) AND array_key_exists( 'title', $result['game'] ) ) {
				$title = $result['game']['title'];
			}
		}

		if ( $shortscore == '' ) {
			$shortscore = 0;
		}

		$html = '';


		$slug = '_shortscore_game_title';
		$html .= '<p class="rangeslider-box">
                    <label for="' . $slug . '" class="prfx-row-title"> ' . __( 'Game title', 'wp-shortscore' ) . '</label><br>
                    <input type="text" name="' . $slug . '" id="' . $slug . '" value="' . $title . '"/>
                </p>';

		$slug = '_shortscore_user_rating';
		$html .= '<p>
                    <label for="' . $slug . '" class="prfx-row-title"> ' . __( 'Shortscore (1 to 10)', 'wp-shortscore' ) . '</label><br>
                    <input type="range" min="0" max="10" step ="0.5" name="' . $slug . '" id="' . $slug . '" value="' . $shortscore . '"/>
                </p>';

		$slug = '_shortscore_summary';
		$html .= '<p>
                    <label for="' . $slug . '" class="prfx-row-title"> ' . __( 'Summary', 'wp-shortscore' ) . '</label><br>
                    <textarea name="' . $slug . '" id="' . $slug . '" class="widefat" cols="50" rows="5">' . $shortscore_summary . '</textarea> 
                </p>';



		echo $html;

		echo $this->getShortscoreHTML();

	}

	public function getPostData( $slug ) {

		if ( isset( $_POST[ $slug ] ) ) {
			$data = sanitize_text_field( $_POST[ $slug ] );

			return $data;
		} else {
			return false;
		}
	}


	public function renderInputField( $post, $slug, $label, $type = '' ) {

		$shortscore_stored_meta = get_post_meta( $post->ID );

		if ( isset ( $shortscore_stored_meta[ $slug ] ) ) {
			$value = $shortscore_stored_meta[ $slug ][0];
		}
		$html = '<p>
                    <label for="' . $slug . '" class="prfx-row-title"> ' . __( $label, "wp-shortscore" ) . '</label>
                    <input type="text" name="' . $slug . '" id="' . $slug . '" value="' . $value . '"/>
                </p>';

		return $html;

	}

	/**
	 * Admin notices
	 **/
	public function wp_shortscore_message() {
		if ( array_key_exists( 'wp-shortscore-error', $_GET ) ) { ?>
            <div class="error ">
            <p>
				<?php
				switch ( $_GET['wp-shortscore-error'] ) {
					case 'shortscore-id':
					case 'result-null':
						_e( 'This SHORTSCORE ID does not exist', 'wp-shortscore' );
						break;
					default:
						echo __( 'An error occurred when saving the SHORTSCORE:' ) . ' [' . $_GET['wp-shortscore-error'] . ']';
						break;
				}
				?>
            </p>
            </div><?php

		}

		if ( array_key_exists( 'wp-shortscore-msg', $_GET ) ) { ?>
            <div class="update notice notice-success is-dismissible">
            <p>
				<?php
				switch ( $_GET['wp-shortscore-msg'] ) {
					case 'success':
						_e( 'Valid SHORTSCORE ID found and data saved to post successfully', 'wp-shortscore' );
						break;
					default:
						_e( 'SHORTSCORE ID saved', 'wp-shortscore' );
						break;
				}
				?>
            </p>
            </div><?php

		}
	}

	/**
	 * @return float|string
	 */
	private function getShortscoreHTML() {
		$shortscore = '';
		$post_id    = get_the_ID();

		if ( get_post_meta( $post_id, '_shortscore_result', true ) != '' ) {

			$result = $this->object_to_array( get_post_meta( $post_id, '_shortscore_result', true ) );

			$shortscore_url = $result['game']['url'];
			//$shortscore_comment_url = $result['shortscore']['url'];
			$shortscore = ( $result['shortscore']['userscore'] );

			$shortscore_summary = nl2br( $result['shortscore']['summary'] );

			$shortscore_author = $result['shortscore']['author'];
			$shortscore_title  = $result['game']['title'];
			$shortscore_date   = $result['shortscore']['date'];
			//$shortscore_count       = $result['game']['count'];

		}

		if ( $shortscore == '' OR $shortscore < 1 ) {
			$shortscore_class = 0;
		} else {
			$shortscore_class = $shortscore;
		}

		$notice = '';

		if ( is_admin()  ) {

		    $notice = '';
			$notice_inner = '';

			if ( $shortscore == '' OR $shortscore < 1 ) {

				$notice_inner     .= '<li>' . __( 'The SHORTSCORE needs to be greater than zero.', 'wp-shortscore' ) . '</li>';
				$shortscore = 0;
			}

			if ( $shortscore_summary == '' ) {
				$notice_inner .= '<li>' . __( 'Summary field is empty.', 'wp-shortscore' ) . '</li>';
			}

			if ( $shortscore_title == '' ) {
				$notice_inner .= '<li>' . __( 'Game title field is emtpy.', 'wp-shortscore' ) . '</li>';
			}

			if($notice_inner != ''){
				$notice .= '<div class="shortscore-notice">';
				$notice .= '<p><strong>' . __( 'Attention:', 'wp-shortscore' ) . '</strong></p>';
				$notice .= '<ul>';
				$notice .= $notice_inner;
				$notice .= '</ul></div>';

			}

			echo '<h2>'.  __('Preview','wp-shortscore') . '</h2>';
		}


		/* HTML */
		$shortscore_html = '<div class="type-game">';
		// $shortscore_html .= '<h3 class="shortscore-title"><a class="score" href="' . $shortscore_url . '">' . __( 'Rating on SHORTSCORE.org', 'wp-shortscore' ) . '</a></h3>';
		$shortscore_html .= '<div class="hreview shortscore-hreview">';

		if ( $shortscore_summary != '' ) {
			$shortscore_html .= '<div class="text">';
			$shortscore_html .= '<span class="item"> <a class="score" href="' . $shortscore_url . '"><strong class="fn">' . $shortscore_title . '</strong></a>: </span>';
			$shortscore_html .= '<span class="summary">' . $shortscore_summary . '</span><span class="reviewer vcard"> – <span class="fn">' . $shortscore_author . '</span></span>';
			$shortscore_html .= '</div>';
		}

		$shortscore_html .= '<div class="rating">';
		$shortscore_html .= '<div id="shortscore_value" class="shortscore shortscore-' . $shortscore_class . '"><span class="value">' . $shortscore . '</span></div>';
		$shortscore_html .= '<div class="outof">' . sprintf( __( 'out of %s.', 'wp-shortscore' ), '<span class="best">10</span>' ) . '</div>';
		$shortscore_html .= '<span class="dtreviewed">' . $shortscore_date . '</span> ';
		$shortscore_html .= '</div>';

		//$shortscore_html .= '<div class="link"><a href="' . $shortscore_url . '">' . sprintf( __( '%s', 'wp-shortscore' ), '<span class="votes">' . sprintf( _n( 'one user review', '%s user reviews', $shortscore_count, 'wp-shortscore' ), $shortscore_count ) . '</span> ' ) . __( 'on', 'wp-shortscore' ) . ' SHORTSCORE.org ' . __( 'to', 'wp-shortscore' ) . ' ' . $shortscore_title . '</a></div>';

		$shortscore_html .= '</div>';
		$shortscore_html .= '</div>';

		if ( is_admin() ) {
			$buttons = '<div style="overflow: hidden; margin-top: 1em;">' . get_submit_button( __('Delete SHORTSCORE','wp-shortscore'), 'delete small', 'delete_shortscore') . '</div>';

			$shortscore_html = '<div class="shortscore-preview">'. $shortscore_html . '</div>' .  $notice . $buttons;

		}

		if ( is_admin() OR ( $shortscore_url != '' AND $shortscore_title != '' AND $shortscore_author != '' AND $shortscore_date != '' AND $shortscore != '' AND $shortscore_summary != '' ) ) {
			return $shortscore_html;
		} else {
			return false;
		}

	}

}

new WpShortscore();
