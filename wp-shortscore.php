<?php

/*
Plugin Name: WP SHORTSCORE
Description: Displays your SHORTSCORE at the bottom of the post. Add a custom field `shortscore_id` with
a shortscore.org ID (e.g. 374)

Plugin URI:  http://shortscore.org
Version:     1.0
Author:      MarcDK, le-phil.de
Author URI:  http://marc.tv
License URI: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

class WP_SHORTSCORE
{

    private $version = '1.0';
    public $shortscore_baseurl = 'https://shortscore.org';
    private $shortscore_css_path = '/wp-content/themes/twentyfifteen-child/shortscore.css';
    public $shortscore_endpoint = '/?get_shortscore=';

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

    public function frontendInit()
    {
        add_action('wp_print_styles', array($this, 'enqueScripts'));
        add_filter('the_content', array($this, 'appendShortscore'));
    }

    public function savePostMeta($post_ID, $meta_name, $meta_value)
    {
        add_post_meta($post_ID, $meta_name, $meta_value, true) || update_post_meta($post_ID, $meta_name, $meta_value);
    }


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

            $json = file_get_contents($this->shortscore_baseurl . $this->shortscore_endpoint . $shortscore_id);
            $result = json_decode($json);

            $error_code = false;

            if (!isset($result->game->id)) {
                $error_code = 'no-id';
            }

            if (!isset($result->game->url)) {
                $error_code = 'url';
            }

            if (!isset($result->shortscore->userscore)) {
                $error_code = 'userscore';
            }

            if (!isset($result->shortscore->author)) {
                $error_code = 'author';
            }

            if (!isset($result->shortscore->summary)) {
                $error_code = 'summary';
            }

            if (!isset($result->game->title)) {
                $error_code = 'title';
            }

            if (!isset($result->shortscore->date)) {
                $error_code = 'date';
            }

            if (!isset($result->game->count)) {
                $error_code = 'count';
            }

            if (!isset($result->shortscore->id)) {
                $error_code = 'no-shortscore';
            }

            if($error_code){
                add_filter('redirect_post_location', function ($location) use ($error_code) {
                    return add_query_arg('wp-shortscore-error', $error_code, $location);
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


    private function displayShortscore()
    {
        $shortscore = '';
        $post_id = get_the_ID();

        if (
            function_exists('get_post_meta') && get_post_meta($post_id, '_shortscore_result', true) != ''
        ) {

            $result = get_post_meta($post_id, '_shortscore_result', true);

            $shortscore_url = $result->game->url;
            $shortscore = round($result->shortscore->userscore);
            $shortscore_summary = $result->shortscore->summary;
            $shortscore_author = $result->shortscore->author;
            $shortscore_title = $result->game->title;
            $shortscore_date = $result->shortscore->date;
            $shortscore_count = $result->game->count;

            $shortscore_html = '<div class="type-game">';
            $shortscore_html .= '<div class="hreview shortscore-hreview">';
            $shortscore_html .= '<div class="type-game-title">';
            $shortscore_html .= '<h3 class="type-game-title-h3">Rating on shortscore.org:</h3>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '<div class="rating">';
            $shortscore_html .= '<div class="shortscore shortscore-' . $shortscore . '">' . $shortscore . '</div>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '<div class="text">';
            $shortscore_html .= '<span class="item"> <a class="score" href="' . $shortscore_url . '"><strong class="fn">' . $shortscore_title . '</strong></a>: </span>';
            $shortscore_html .= '<span class="summary">' . $shortscore_summary . '</span><span class="reviewer vcard"> – <span class="fn">' . $shortscore_author . '</span></span>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '<div class="link"><a href="' . $shortscore_url . '">' . sprintf(__('%s', 'wp-shortscore'), '<span class="votes">' . sprintf(_n('one user review', '%s user reviews', $shortscore_count, 'wp-shortscore'), $shortscore_count) . '</span> ') . __('on', 'wp-shortscore') . ' SHORTSCORE.org</a> ' . __('to', 'wp-shortscore') . ' ' . $shortscore_title . '</div>';
            $shortscore_html .= '<span class="dtreviewed">' . $shortscore_date . '</span> ';
            $shortscore_html .= '<span class="outof">' . sprintf(__('out of %s.', 'wp-shortscore'), '<span class="best">10</span>') . '</span>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '</div>';

            $shortscore = $shortscore_html;
        }

        return $shortscore;
    }


    public function appendShortscore($content)
    {
        if (is_single()) {
            // Add SHORTSCORE to the end of the post.
            $content = $content . $this->displayShortscore();
        }

        // Returns the content.
        return $content;

    }

    public function enqueScripts()
    {
        wp_enqueue_style(
            "external-shortscore-styles", $this->shortscore_baseurl . $this->shortscore_css_path, true, $this->version);

        wp_enqueue_style(
            "shortscore-base", WP_PLUGIN_URL . '/wp-shortscore/shortscore-base.css', true, $this->version);
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
        ?>
        <p>
            <label for="_shortscore_id"
                   class="prfx-row-title"><?php _e('Please input SHORTSCORE ID', 'wp-shortscore') ?></label>
            <input type="text" name="_shortscore_id" id="_shortscore_id"
                   value="<?php if (isset ($shortscore_stored_meta['_shortscore_id'])) echo $shortscore_stored_meta['_shortscore_id'][0]; ?>"/>
        </p>
        <p><?php _e('You can find the SHORTSCORE ID next to your submitted SHORTSCORE on', 'wp-shortscore') ?> <a
                href="http://shortscore.org">SHORTSCORE.org</a></p>
        <?php
    }


    /**
     * Admin notices
     **/
    public function wp_shortscore_message()
    {
        if (array_key_exists('wp-shortscore-error', $_GET)) { ?>
            <div class="error">
            <p>
                <?php
                switch ($_GET['wp-shortscore-error']) {
                    case 'no-shortscore':
                        _e('This SHORTSCORE ID does not exist','wp-shortscore');
                        break;
                    default:
                        _e('An error ocurred when saving the SHORTSCORE.');
                        break;
                }
                ?>
            </p>
            </div><?php

        }

        if (array_key_exists('wp-shortscore-msg', $_GET)) { ?>
            <div class="notice notice-success">
            <p>
                <?php
                switch ($_GET['wp-shortscore-msg']) {
                    case 'success':
                        _e('Valid SHORTSCORE ID found and saved to post successfully','wp-shortscore');
                        break;
                    default:
                        _e('SHORTSCORE ID saved','wp-shortscore');
                        break;
                }
                ?>
            </p>
            </div><?php

        }
    }
}

new WP_SHORTSCORE();
