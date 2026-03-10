<?php
/*
Plugin Name: Never Say Retail Live Engine
Description: Live sale system for Never Say Retail.
Version: 4.0
Update URI: https://github.com/djgap2000/never-say-retail-live-engine
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'live-studio.php';
require_once plugin_dir_path(__FILE__) . 'auto-showcase.php';

function nsr_live_page_shortcode() {

ob_start();
?>

<div id="nsr-live-container">

<h2>🔥 Never Say Retail Live 🔥</h2>

<div id="nsr-current-item">
<h3>Live Item Will Appear Here</h3>
</div>

<button id="nsr-claim-btn">CLAIM ITEM</button>

<div id="nsr-up-next">
<h4>Up Next</h4>
<div class="nsr-preview-items"></div>
</div>

</div>

<?php
return ob_get_clean();

}

add_shortcode('nsr_live_page', 'nsr_live_page_shortcode');
