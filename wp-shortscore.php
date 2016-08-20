<?php

/*

Plugin Name: WP SHORTSCORE
Description: Displays your SHORTSCORE at the bottom of the post. Uses custom fields: 'shortscore' and 'shortscore_slug'
Plugin URI:  http://shortscore.org
Version:     0.0.2
Author:     MarcDK, le-phil.de
URI:        http://marc.tv
License:    GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
your option) any later version.

 */

class WP_SHORTSCORE
{

    private $version = '0.0.2';
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

            $this->savePostMeta($post_id, 'shortscore', $result->shortscore->userscore);
            $this->savePostMeta($post_id, 'shortscore_url', $result->game->url);
            $this->savePostMeta($post_id, 'shortscore_summary', $result->shortscore->summary);
            $this->savePostMeta($post_id, 'shortscore_title', $result->game->title);
            $this->savePostMeta($post_id, 'shortscore_author', $result->shortscore->author);
            $this->savePostMeta($post_id, 'shortscore_date', $result->shortscore->date);

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
            get_post_meta($post_id, 'shortscore_title', true) != ''
        ) {
            $shortscore_url = get_post_meta($post_id, 'shortscore_url', true);
            $shortscore = round(get_post_meta($post_id, 'shortscore', true));
            $shortscore_summary = get_post_meta($post_id, 'shortscore_summary', true);
            $shortscore_author = get_post_meta($post_id, 'shortscore_author', true);
            $shortscore_title = get_post_meta($post_id, 'shortscore_title', true);
            $shortscore_date = get_post_meta($post_id, 'shortscore_date', true);

            $shortscore_html = '<div class="type-game">';
            $shortscore_html .= '<p class="hreview">';
            $shortscore_html .= '<span class="text"><span class="item"> <strong class="fn">' . $shortscore_title . '</strong>: </span>';
            $shortscore_html .= '<span class="summary">' . $shortscore_summary.  '</span><span class="reviewer vcard"> – <span class="fn">' . $shortscore_author . '</span></span>';
            $shortscore_html .= '<div class="rating">';
            $shortscore_html .= '<a class="score" href="' . $shortscore_url . '">';
            $shortscore_html .= '<div class="average shortscore shortscore-' . $shortscore . '">' . $shortscore . '</div>';
            $shortscore_html .= '</a>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '<span class="dtreviewed">' . $shortscore_date . '</span>';
            $shortscore_html .= '</p>';
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
    }
}

new WP_SHORTSCORE();
