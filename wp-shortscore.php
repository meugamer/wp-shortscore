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
        if (wp_is_post_revision($post_id))
            return;


        if (function_exists('get_post_meta') && get_post_meta($post_id, 'shortscore_id', true) != '') {

            $shortscore_id = get_post_meta($post_id, 'shortscore_id', true);

            $json = file_get_contents($this->shortscore_baseurl . $this->shortscore_endpoint . $shortscore_id);
            $result = json_decode($json);


            if (!isset($result->game->id)) {
                return;
            }

            if (!isset($result->shortscore->userscore)) {
                return;
            }

            if (!isset($result->shortscore->userscore)) {
                return;
            }

            if (!isset($result->shortscore->summary)) {
                return;
            }

            if (!isset($result->game->title)) {
                return;
            }

            if (!isset($result->shortscore->date)) {
                return;
            }

            if (!isset($result->game->count)) {
                return;
            }


            $this->savePostMeta($post_id, 'shortscore', $result->shortscore->userscore);
            $this->savePostMeta($post_id, 'shortscore_url', $result->game->url);
            $this->savePostMeta($post_id, 'shortscore_summary', $result->shortscore->summary);
            $this->savePostMeta($post_id, 'shortscore_title', $result->game->title);
            $this->savePostMeta($post_id, 'shortscore_author', $result->shortscore->author);
            $this->savePostMeta($post_id, 'shortscore_date', $result->shortscore->date);
            $this->savePostMeta($post_id, 'shortscore_count', $result->game->count);

        }

        return;
    }

    private function displayShortscore()
    {
        $shortscore = '';
        $post_id = get_the_ID();

        if (
            function_exists('get_post_meta') &&
            get_post_meta($post_id, 'shortscore', true) != '' &&
            get_post_meta($post_id, 'shortscore_url', true) != '' &&
            get_post_meta($post_id, 'shortscore_summary', true) != '' &&
            get_post_meta($post_id, 'shortscore_author', true) != '' &&
            get_post_meta($post_id, 'shortscore_date', true) != '' &&
            get_post_meta($post_id, 'shortscore_count', true) != '' &&
            get_post_meta($post_id, 'shortscore_title', true) != ''
        ) {
            $shortscore_url = get_post_meta($post_id, 'shortscore_url', true);
            $shortscore = round(get_post_meta($post_id, 'shortscore', true));
            $shortscore_summary = get_post_meta($post_id, 'shortscore_summary', true);
            $shortscore_author = get_post_meta($post_id, 'shortscore_author', true);
            $shortscore_title = get_post_meta($post_id, 'shortscore_title', true);
            $shortscore_date = get_post_meta($post_id, 'shortscore_date', true);
            $shortscore_count = get_post_meta($post_id, 'shortscore_count', true);
            
            $shortscore_html = '<div class="type-game">';
            $shortscore_html .= '<div class="hreview shortscore-hreview">';
            $shortscore_html .= '<div class="rating">';
            $shortscore_html .= '<div class="shortscore shortscore-' . $shortscore . '">' . $shortscore . '</div>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '<div class="text">';
            $shortscore_html .= '<span class="item"> <a class="score" href="' . $shortscore_url . '"><strong class="fn">' . $shortscore_title . '</strong></a>: </span>';
            $shortscore_html .= '<span class="summary">' . $shortscore_summary . '</span><span class="reviewer vcard"> – <span class="fn">' . $shortscore_author . '</span></span>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '<div class="link"><a href="' . $shortscore_url . '">' . sprintf(__('%s', 'wp-shortscore'),'<span class="votes">' . sprintf(_n('one user review', '%s user reviews', $shortscore_count, 'wp-shortscore'),$shortscore_count ) . '</span> ') . __('on','wp-shortscore') . ' SHORTSCORE.org</a> ' . __('to','wp-shortscore') . ' '. $shortscore_title . '</div>';
            $shortscore_html .= '<span class="dtreviewed">' . $shortscore_date . '</span> ';
            $shortscore_html .= '<span class="outof">' . sprintf(__('out of %s.','wp-shortscore'),'<span class="best">10</span>').'</span>';
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
}

new WP_SHORTSCORE();
