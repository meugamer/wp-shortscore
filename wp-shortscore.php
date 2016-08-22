<?php
/*
Plugin Name: WP SHORTSCORE
Description: Displays a SHORTSCORE review box at the bottom of the post.

Plugin URI:  http://shortscore.org
Version:     1.0
Author:      MarcDK, le-phil.de
Author URI:  http://marc.tv
License URI: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

/**
 * Class WpShortscore
 */
class WpShortscore
{
    const SHORTSCORE_ENDPOINT = '/?get_shortscore=';
    const SHORTSCORE_URL      = 'https://shortscore.org';
    private $version = '1.0';

    /**
     * WpShortscore constructor.
     */
    public function __construct()
    {
        load_plugin_textdomain('wp-shortscore', false, dirname(plugin_basename(__FILE__)) . '/language/');

        $this->frontendInit();

        if (is_admin()) {

            add_action('save_post', array($this, 'getShortscore'));
            add_action('add_meta_boxes', array($this, 'shortscore_custom_meta'));
            add_action('admin_notices', array($this, 'wp_shortscore_message'));
        }
    }

    /*
     * Initialise frontend methods
     */
    public function frontendInit()
    {
        add_action('wp_print_styles', array($this, 'enqueScripts'));
        add_filter('the_content', array($this, 'appendShortscore'));
    }

    /*
     * helper method to save meta data to a post.
     * */
    public function savePostMeta($post_ID, $meta_name, $meta_value)
    {
        add_post_meta($post_ID, $meta_name, $meta_value, true) || update_post_meta($post_ID, $meta_name, $meta_value);
    }

    /**
     * Pull the Shortscore data by using the shortscore id and save it to the post.
     * @param $post_id
     */
    public function getShortscore($post_id)
    {
        // Checks save status
        $is_autosave = wp_is_post_autosave($post_id);
        $is_revision = wp_is_post_revision($post_id);
        $is_valid_nonce = (isset($_POST['shortscore_nonce']) && wp_verify_nonce($_POST['shortscore_nonce'], basename(__FILE__))) ? 'true' : 'false';

        // Exits script depending on save status
        if ($is_autosave || $is_revision || !$is_valid_nonce) {
            return;
        }

        // Checks for input and sanitizes/saves if needed
        if (isset($_POST['_shortscore_id'])) {
            update_post_meta($post_id, '_shortscore_id', sanitize_text_field($_POST['_shortscore_id']));
        }

        if (function_exists('get_post_meta') && get_post_meta($post_id, '_shortscore_id', true) != '') {

            $shortscore_id = get_post_meta($post_id, '_shortscore_id', true);

            $json = file_get_contents(WpShortscore::SHORTSCORE_URL . WpShortscore::SHORTSCORE_ENDPOINT . $shortscore_id);

            // validate JSON structure
            try {

                if (null === ($result = json_decode($json))) {
                    throw new \Exception('result-null');
                }

                // JSON structure (defined as array)
                $structure = [
                    'game'       => ['id', 'url', 'title', 'count'],
                    'shortscore' => ['userscore', 'url', 'author','summary','date', 'id']
                ];

                foreach ($structure as $property => $subProperties) {
                    if(!($result->$property)) {
                        throw new \Exception($property);
                    }
                    foreach ($subProperties as $subProperty) {
                        if (!isset($result->{$property}->$subProperty)) {
                            throw new \Exception($property . '-' . $subProperty);
                        }
                        // third level or SubSubProperties

                    }
                }

            } catch (\Exception $exception) {
                add_filter('redirect_post_location', function ($location) use ($exception) {
                    return add_query_arg('wp-shortscore-error', $exception->getMessage(), $location);
                });
                return;
            }

            $this->savePostMeta($post_id, '_shortscore_result', $result);

            $msg_code = 'success';

            add_filter('redirect_post_location', function ($location) use ($msg_code) {
                return add_query_arg('wp-shortscore-msg', $msg_code, $location);
            });


        }

        return;
    }


    /**
     * Display Shortscore review box below a post
     */

    /**
     * @param string $content
     * @return string
     */
    public function appendShortscore(/* string */ $content)
    {
        if (is_single()) {
            // Add SHORTSCORE to the end of the post.
            $content = $content . $this->displayShortscore();
        }

        // Returns the content.
        return $content;

    }

    /**
     * Load CSS in the theme for the SHORTSCORE styling.
     */
    public function enqueScripts()
    {
        wp_enqueue_style(
            "shortscore-styles", plugins_url('shortscore-base.css', __FILE__), $this->version);

        wp_enqueue_style(
            "shortscore-base", plugins_url('shortscore.css', __FILE__), true, $this->version);
    }

    /**
     * Adds a meta box to the post editing screen
     */
    public function shortscore_custom_meta()
    {
        add_meta_box('shortscore_meta', __(__('Add SHORTSCORE', 'wp-shortscore'), 'prfx-textdomain'), array($this, 'shortscore_meta_callback'), 'post');
    }

    /**
     * Outputs the content of the meta box
     */
    public function shortscore_meta_callback($post)
    {
        wp_nonce_field(basename(__FILE__), 'shortscore_nonce');
        $shortscore_stored_meta = get_post_meta($post->ID);

        if (isset ($shortscore_stored_meta['_shortscore_result'])) {
            $result = get_post_meta($post->ID, '_shortscore_result', true);
            echo '<p><ul style="background-color: #108D4F; color: #fff; padding: 0.3em;">';
            echo '<li><a style="color: white" href="' . $result->shortscore->url . '">' . __('Show on SHORTSCORE.org', 'wp-shortscore') . '</a></li>';
            echo '<li>SHORTSCORER: <br><strong>' . $result->shortscore->author . '</strong></li>';
            echo '<li>' . __('Game title', 'wp-shortscore') . ' <br><strong>' . $result->game->title . '</strong></li>';
            echo '<li>' . __('Userscore', 'wp-shortscore') . ' <br><strong>' . $result->shortscore->userscore . '</strong></li>';
            echo '</ul></p>';
        }

        ?>

        <p>
            <label for="_shortscore_id"
                   class="prfx-row-title"><?php _e('Please input SHORTSCORE ID', 'wp-shortscore') ?></label>
            <input type="text" name="_shortscore_id" id="_shortscore_id"
                   value="<?php if (isset ($shortscore_stored_meta['_shortscore_id'])) echo $shortscore_stored_meta['_shortscore_id'][0]; ?>"/>
        </p>

        <p><?php _e('You can find the SHORTSCORE ID next to your submitted SHORTSCORE on', 'wp-shortscore') ?> <a
                href="http://shortscore.org">SHORTSCORE.org</a>
        </p>
        <?php
    }

    /**
     * Admin notices
     **/
    public function wp_shortscore_message()
    {
        if (array_key_exists('wp-shortscore-error', $_GET)) { ?>
            <div class="error ">
            <p>
                <?php
                switch ($_GET['wp-shortscore-error']) {
                    case 'shortscore-id':
                    case 'result-null':
                        _e('This SHORTSCORE ID does not exist', 'wp-shortscore');
                        break;
                    default:
                        echo __('An error ocurred when saving the SHORTSCORE.') .' [hint: '.$_GET['wp-shortscore-error'].']';
                        break;
                }
                ?>
            </p>
            </div><?php

        }

        if (array_key_exists('wp-shortscore-msg', $_GET)) { ?>
            <div class="update notice notice-success is-dismissible">
            <p>
                <?php
                switch ($_GET['wp-shortscore-msg']) {
                    case 'success':
                        _e('Valid SHORTSCORE ID found and data saved to post successfully', 'wp-shortscore');
                        break;
                    default:
                        _e('SHORTSCORE ID saved', 'wp-shortscore');
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
    private function displayShortscore()
    {
        $shortscore = '';
        $post_id = get_the_ID();

        if (
            function_exists('get_post_meta') && get_post_meta($post_id, '_shortscore_result', true) != ''
        ) {

            $result = get_post_meta($post_id, '_shortscore_result', true);

            $shortscore_url = $result->game->url;
            $shortscore_comment_url = $result->shortscore->url;
            $shortscore = round($result->shortscore->userscore);
            $shortscore_summary = nl2br($result->shortscore->summary);
            $shortscore_author = $result->shortscore->author;
            $shortscore_title = $result->game->title;
            $shortscore_date = $result->shortscore->date;
            $shortscore_count = $result->game->count;

            $shortscore_html = '<div class="type-game">';
            $shortscore_html .= '<h3 class="shortscore-title">' . __('Rating on SHORTSCORE.org', 'wp-shortscore') . '</h3>';
            $shortscore_html .= '<div class="hreview shortscore-hreview">';

            $shortscore_html .= '<div class="text">';
            $shortscore_html .= '<span class="item"> <a class="score" href="' . $shortscore_url . '"><strong class="fn">' . $shortscore_title . '</strong></a>: </span>';
            $shortscore_html .= '<span class="summary">' . $shortscore_summary . '</span><span class="reviewer vcard"> – <span class="fn">' . $shortscore_author . '</span></span>';
            $shortscore_html .= '</div>';

            $shortscore_html .= '<div class="rating">';
            $shortscore_html .= '<a href="' . $shortscore_comment_url . '" class="shortscore shortscore-' . $shortscore . '">' . $shortscore . '</a>';
            $shortscore_html .= '</div>';

            $shortscore_html .= '<div class="link"><a href="' . $shortscore_url . '">' . sprintf(__('%s', 'wp-shortscore'), '<span class="votes">' . sprintf(_n('one user review', '%s user reviews', $shortscore_count, 'wp-shortscore'), $shortscore_count) . '</span> ') . __('on', 'wp-shortscore') . ' SHORTSCORE.org ' . __('to', 'wp-shortscore') . ' ' . $shortscore_title . '</a></div>';
            $shortscore_html .= '<span class="dtreviewed">' . $shortscore_date . '</span> ';
            $shortscore_html .= '<span class="outof">' . sprintf(__('out of %s.', 'wp-shortscore'), '<span class="best">10</span>') . '</span>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '</div>';

            $shortscore = $shortscore_html;
        }

        return $shortscore;
    }

}

new WpShortscore();
