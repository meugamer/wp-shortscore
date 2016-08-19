<?php
/*

Plugin Name: WP SHORTSCORE
Description: Displays your SHORTSCORE at the bottom of the post. Uses custom fields: 'shortscore' and 'shortscore_slug'
Plugin URI:  http://shortscore.org
Version:     0.0.1
Author:     MarcDK, le-phil-de
Author      URI: http://www.marc.tv
License:    GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
your option) any later version.

 */

class WP_SHORTSCORE
{

    private $version = '0.0.1';
    private $shortscore_baseurl = 'https://shortscore.org';
    private $shortscore_css_path = '/wp-content/themes/twentyfifteen-child/shortscore.css';

    public function __construct()
    {
        load_plugin_textdomain('wp-shortscore', false, dirname(plugin_basename(__FILE__)) . '/language/');

        $this->frontendInit();
    }

    public function frontendInit()
    {
        add_action('wp_print_styles', array($this, 'enqueScripts'));
        add_filter('the_content', array($this, 'appendShortscore'));
    }

    private function generateShortscore()
    {
        $shortscore = '';
        $pid = get_the_ID();

        if (function_exists('get_post_meta') && get_post_meta($pid, 'shortscore', true) != '' && get_post_meta($pid, 'shortscore_slug', true) != '') {
            $shortscore_slug = get_post_meta($pid, 'shortscore_slug', true);
            $shortscore      = round(get_post_meta($pid, 'shortscore', true));

            $shortscore_html  = '<div class="type-game">';
            $shortscore_html .= '<div class="hreview">';
            $shortscore_html .= '<div class="rating">';
            $shortscore_html .= '<a class="score" href="http://shortscore.local/game/' . $shortscore_slug . '/">';
            $shortscore_html .= '<div class="average shortscore shortscore-' . $shortscore . '">'. $shortscore . '</div>';
            $shortscore_html .= '</a>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '</div>';
            $shortscore_html .= '</div>';

            $shortscore = $shortscore_html;
        }

        return $shortscore;
    }

    public function appendShortscore($content)
    {
        if ( is_single() )
            // Add SHORTSCORE to the end of the post.

        $content = $content . $this->generateShortscore();

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
