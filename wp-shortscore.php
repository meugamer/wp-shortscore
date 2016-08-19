<?php
/**
 * Plugin Name: WP SHORTSCORE
 * Description: Displays your SHORTSCORE at the bottom of the post. Uses custom fields: 'shortscore' and 'shortscore_slug'
 * Plugin URI:  http://shortscore.org
 * Version:     0.0.1
 */

class WP_SHORTSCORE
{

    private $version = '0.0.1';
    private $shortscore_baseurl = 'https://shortscore.org';

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

    public function appendShortscore($content)
    {
        if ( is_single() )
            // Add SHORTSCORE to the end of the post.

            $shortscore_slug = 'empyrion-galactic-survival';

            $shortscore_html  = '<div class="rating">';
            $shortscore_html .= '<a class="score" href="http://shortscore.local/game/' . $shortscore_slug . '/">';
            $shortscore_html .= '<div class="average shortscore shortscore-0">?</div>';
            $shortscore_html .= '</a>';
            $shortscore_html .= '</div>';

            
            $content = $content . $shortscore_html; 

        // Returns the content.
        return $content;

    }

    public function enqueScripts()
    {
        wp_enqueue_style(
            "jquery.marctv-galleria-style", WP_PLUGIN_URL . "/marctv-galleria/galleria/themes/classic/galleria.classic.css", false, $this->version);

    }
}

new WP_SHORTSCORE();


