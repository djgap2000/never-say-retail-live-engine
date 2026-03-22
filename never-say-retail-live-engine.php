<?php
/*
Plugin Name: Never Say Retail Live Engine
Description: Live sale system for Never Say Retail.
Version: 4.9.3
Update URI: https://github.com/djgap2000/never-say-retail-live-engine
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'nsr-pallets.php';
require_once plugin_dir_path(__FILE__) . 'nsr-smart-pricing.php';

add_action('admin_post_nsr_live_action', 'nsr_handle_live_action');
add_action('admin_post_nopriv_nsr_live_action', 'nsr_handle_live_action');
function nsr_handle_live_action() {

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $action = $_REQUEST['nsr_live_action'] ?? '';
$effect = sanitize_text_field($_REQUEST['effect'] ?? '');
    if ($action === 'show_mode_trigger') {

    $banner = '';

    if ($effect === 'flash') {
        $banner = 'FLASH DEAL LIVE';
    } elseif ($effect === 'sold') {
        $banner = 'SOLD ALERT';
    } elseif ($effect === 'mystery') {
        $banner = 'MYSTERY ITEM';
    } elseif ($effect === 'hype') {
        $banner = 'CLAIM IT NOW';
    }

    $state = nsr_live_state();
    $state['show_mode_effect'] = $effect;
    $state['show_mode_banner'] = $banner;
    $state['last_action'] = 'Show mode trigger: ' . $effect;

    nsr_live_save($state);
    wp_safe_redirect(admin_url('admin.php?page=nsr-live'));
    exit;
}

    wp_safe_redirect(admin_url('admin.php?page=nsr-live'));
    exit;
}

define('NSR_LIVE_OPT', 'nsr_live_state_v46');

function nsr_live_default_state() {
    return array(
        'is_live' => 0,
        'show_title' => 'Never Say Retail Live',
        'tagline' => 'Great Products at Great Prices',
        'follower_goal_current' => 775,
        'follower_goal_target' => 1000,
        'timer_seconds' => 45,
        'timer_end' => 0,
        'current_index' => 0,
        'claims_total' => 0,
        'total_savings' => 0,
        'next_item_no' => 101,
        'revealed_count' => 0,
        'recent' => array(),
        'queue' => array(),
        'last_action' => '',
        'scanner_draft' => array(),
        'barcode_lookup_api_key' => '',
        'upcdatabase_api_key' => '',
        'show_fx_enabled' => 1,
'show_music_enabled' => 0,
'show_mode_banner' => '',
'show_mode_effect' => '',
    );
}

function nsr_live_state() {
    $saved = get_option(NSR_LIVE_OPT, array());
    if (!is_array($saved)) $saved = array();
    return array_replace_recursive(nsr_live_default_state(), $saved);
}

function nsr_live_save($state) {
    update_option(NSR_LIVE_OPT, $state, false);
}

function nsr_live_format_money($n) {
    return '$' . number_format((float)$n, 2);
}

function nsr_live_discount_pct($retail, $live) {
    $retail = (float)$retail;
    $live = (float)$live;
    if ($retail <= 0 || $live < 0 || $live > $retail) return 0;
    return round((($retail - $live) / $retail) * 100);
}

function nsr_live_current_item($state = null) {
    if ($state === null) $state = nsr_live_state();
    $idx = (int)$state['current_index'];
    return isset($state['queue'][$idx]) ? $state['queue'][$idx] : null;
}

function nsr_live_upnext($state = null, $count = 3) {
    if ($state === null) $state = nsr_live_state();
    $idx = (int)$state['current_index'];
    $items = array();
    for ($i = $idx + 1; $i < count($state['queue']) && count($items) < $count; $i++) {
        $items[] = $state['queue'][$i];
    }
    return $items;
}

function nsr_live_recent($state = null, $count = 5) {
    if ($state === null) $state = nsr_live_state();
    return array_slice(array_reverse($state['recent']), 0, $count);
}

function nsr_live_stock_left($item) {
    return max(0, intval($item['qty']) - intval($item['claimed']));
}

function nsr_live_push_recent(&$state, $item, $status = '') {
    $state['recent'][] = array(
        'item_no' => $item['item_no'],
        'title'   => $item['title'],
        'source'  => $item['source'],
        'retail'  => $item['retail'],
        'live'    => $item['live'],
        'qty'     => $item['qty'],
        'claimed' => $item['claimed'],
        'barcode' => isset($item['barcode']) ? $item['barcode'] : '',
        'note'    => isset($item['note']) ? $item['note'] : '',
        'status'  => $status ? $status : (nsr_live_stock_left($item) <= 0 ? 'SOLD OUT' : nsr_live_stock_left($item) . ' LEFT'),
    );
}

function nsr_live_auto_item_number(&$state) {
    $num = intval($state['next_item_no']);
    if ($num < 1) $num = 101;
    $state['next_item_no'] = $num + 1;
    return (string)$num;
}

function nsr_live_maybe_expire_timer(&$state) {
    if (!empty($state['timer_end']) && time() >= intval($state['timer_end'])) {
        $state['timer_end'] = 0;
        $state['last_action'] = 'Timer ended — waiting for you to decide what to do next.';
        nsr_live_save($state);
    }
}

function nsr_live_notice($state) {
    if (!empty($state['last_action'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($state['last_action']) . '</p></div>';
    }
}

function nsr_live_styles() {
    static $done = false;
    if ($done) return;
    $done = true;
    ?>
    <style>
       .nsr-price-status{border-radius:16px;padding:14px 14px 12px;margin-bottom:12px;border:1px solid #dcdcde;box-shadow:0 2px 10px rgba(0,0,0,.04)}
.nsr-price-status.red{background:#fef2f2;border-color:#fecaca}
.nsr-price-status.yellow{background:#fffbeb;border-color:#fde68a}
.nsr-price-status.green{background:#ecfdf5;border-color:#a7f3d0}
.nsr-price-status strong{display:block;margin-bottom:8px;font-size:15px}
.nsr-smart-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.nsr-price-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 18px}
.nsr-price-note{margin-top:8px;font-style:italic;opacity:.85}
.nsr-smart-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
        .nsr-live-wrap{max-width:1200px}
        .nsr-admin-grid{display:grid;grid-template-columns:1fr 1.2fr .9fr;gap:18px;margin-top:16px}
        .nsr-admin-grid.second{grid-template-columns:1fr 1fr}
        .nsr-card,.nsr-card-list{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .nsr-row{display:flex;gap:10px;align-items:center}
        .wrap-buttons{flex-wrap:wrap;margin-top:12px}
        .nsr-current-no{font-weight:800;font-size:28px;letter-spacing:.04em}
        .nsr-form-grid{display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:14px}
        .nsr-form-grid label{display:flex;flex-direction:column;gap:6px;font-weight:600}
        .nsr-form-grid input,.nsr-form-grid select,.nsr-form-grid textarea{padding:8px}
        .nsr-list-row{padding:10px 0;border-top:1px solid #eee}
        .nsr-list-row:first-child{border-top:none}
        .nsr-small{font-size:12px;color:#666}
        .nsr-cue-box{background:#fff7ed;border:1px solid #fdba74;border-radius:12px;padding:12px;margin-top:12px}
        .nsr-cue-box h3{margin:0 0 8px 0}
        .nsr-cue-list{margin:0;padding-left:18px}
        .nsr-golive-btn{margin-top:8px}
        .nsr-scanner-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:16px}
        .nsr-scan-input{font-size:24px;font-weight:700;padding:12px}
        .nsr-draft-badge{display:inline-block;background:#111827;color:#fff;padding:6px 10px;border-radius:999px;font-weight:800;margin-bottom:10px}
        .nsr-highlight{background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:12px}
        .nsr-image-preview{margin-top:12px}
        .nsr-image-preview img{max-width:160px;height:auto;border-radius:12px;border:1px solid #e5e7eb;display:block}
        .nsr-api-help{background:#eff6ff;border:1px solid #93c5fd;border-radius:12px;padding:12px;margin-top:10px}

        .nsr-front-wrap{max-width:1100px;margin:0 auto;padding:20px}
        .nsr-hero{display:grid;grid-template-columns:1.15fr .85fr;gap:20px}
        .nsr-video-box,.nsr-product-card,.nsr-card-list{background:#fff;border:1px solid #e5e7eb;border-radius:16px}
        .nsr-video-box{min-height:360px;display:flex;align-items:center;justify-content:center}
        .nsr-video-placeholder{font-size:28px;font-weight:800;color:#666;text-align:center;padding:20px}
        .nsr-product-card{padding:20px}
        .nsr-show-title{font-size:14px;font-weight:800;color:#e11d48;text-transform:uppercase;letter-spacing:.12em}
        .nsr-tagline{color:#555;margin:4px 0 16px}
        .nsr-item-overlay{display:inline-block;background:#111827;color:#fff;padding:8px 14px;border-radius:999px;font-weight:900;font-size:22px;letter-spacing:.08em;margin-bottom:12px}
        .nsr-source-badge{display:inline-block;background:#111827;color:#fff;padding:6px 10px;border-radius:999px;font-weight:700;margin:6px 0}
        .nsr-price-drop{display:flex;flex-direction:column;gap:8px;margin:14px 0}
        .nsr-price-drop .retail{font-size:18px;color:#555}
        .nsr-price-drop .arrow{font-size:28px;font-weight:800;color:#888}
        .nsr-price-drop .live{font-size:34px;font-weight:900;color:#dc2626}
        .nsr-save-line{font-weight:800;margin:6px 0 12px}
        .nsr-meter{height:14px;background:#ececec;border-radius:999px;overflow:hidden;margin-bottom:14px}
        .nsr-meter span{display:block;height:100%;background:linear-gradient(90deg,#22c55e,#f59e0b,#dc2626)}
        .nsr-claim-hint{font-weight:800;font-size:18px;margin:10px 0}
        .nsr-timer{display:inline-block;padding:8px 12px;border-radius:999px;background:#111827;color:#fff;font-weight:800;margin:0 0 10px}
        .nsr-timer.off{background:#f3f4f6;color:#222}
        .nsr-stock{display:inline-block;padding:8px 12px;border-radius:999px;background:#f3f4f6;font-weight:800}
        .nsr-stock.low{background:#fff1f2;color:#be123c;animation:nsrPulse 1s infinite}
        .nsr-claim-btn{display:block;width:100%;margin:16px 0;padding:14px 18px;font-size:18px;font-weight:800;background:#111827;color:#fff;border:0;border-radius:12px}
        .nsr-auto-reminder{font-size:14px;color:#555;background:#f9fafb;border-radius:12px;padding:10px 12px}
        .nsr-stats-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:16px}
        .nsr-stat{background:#f9fafb;border-radius:12px;padding:12px}
        .nsr-stat strong{display:block;margin-bottom:6px}
        .nsr-lists{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px}
       .nsr-showmode-wrap{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:16px 0}
.nsr-showmode-card,.nsr-host-cues{background:#fff;border:1px solid #dcdcde;border-radius:18px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.04)}
.nsr-showmode-banner{border-radius:16px;padding:14px 16px;margin:12px 0 16px;font-weight:800;font-size:20px;letter-spacing:.2px;box-shadow:0 8px 24px rgba(0,0,0,.08)}
.nsr-showmode-banner.flash{background:#fff7ed;border:1px solid #fdba74}
.nsr-showmode-banner.sold{background:#ecfdf5;border:1px solid #86efac}
.nsr-showmode-banner.mystery{background:#eff6ff;border:1px solid #93c5fd}
.nsr-showmode-banner.hype{background:#faf5ff;border:1px solid #d8b4fe}
.nsr-showmode-banner.nsr-animate-pulse{animation:nsrPulse 1s ease-in-out 3}
.nsr-showmode-banner.nsr-animate-slide{animation:nsrSlideIn .45s ease-out 1}
.nsr-show-buttons{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.nsr-host-cues h3{margin-top:0}
.nsr-host-cues p{margin:.4em 0}
.nsr-pill{display:inline-block;padding:6px 10px;border-radius:999px;background:#111827;color:#fff;font-size:12px;font-weight:700}
.nsr-fx-on{background:#ecfdf5;border-color:#86efac}
.nsr-fx-off{background:#f8fafc;border-color:#cbd5e1}
@keyframes nsrPulse{0%{transform:scale(1)}50%{transform:scale(1.03)}100%{transform:scale(1)}}
@keyframes nsrSlideIn{0%{transform:translateY(-10px);opacity:0}100%{transform:translateY(0);opacity:1}}

        @keyframes nsrPulse{0%{transform:scale(1)}50%{transform:scale(1.02)}100%{transform:scale(1)}}
        @media (max-width:900px){
            .nsr-admin-grid,.nsr-admin-grid.second,.nsr-hero,.nsr-lists,.nsr-stats-grid,.nsr-form-grid,.nsr-scanner-grid{grid-template-columns:1fr}
            .nsr-video-box{min-height:220px}
            .nsr-price-drop .live{font-size:28px}
        }
    </style>
    <?php
}

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('nsr-live-js', plugins_url('nsr-scripts.js', __FILE__), array(), '4.9.3', true);
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('nsr-live-js', plugins_url('nsr-scripts.js', __FILE__), array(), '4.9.3', true);
});

add_action('admin_menu', function () {
    add_menu_page('NSR Live', 'NSR Live', 'manage_options', 'nsr-live', 'nsr_live_studio_page', 'dashicons-video-alt3', 56);
    add_submenu_page('nsr-live', 'Live Studio', 'Live Studio', 'manage_options', 'nsr-live', 'nsr_live_studio_page');
    add_submenu_page('nsr-live', 'Queue', 'Queue', 'manage_options', 'nsr-live-queue', 'nsr_live_queue_page');
    add_submenu_page('nsr-live', 'Scanner', 'Scanner', 'manage_options', 'nsr-live-scanner', 'nsr_live_scanner_page');
    add_submenu_page('nsr-live', 'Settings', 'Settings', 'manage_options', 'nsr-live-settings', 'nsr_live_settings_page');
});

function nsr_live_hidden_redirect() {
    echo '<input type="hidden" name="redirect_to" value="' . esc_attr((is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . '">';
}

function nsr_live_suggest_price($retail) {
    $retail = (float)$retail;
    if ($retail <= 0) return 5.00;
    if ($retail < 10) return 3.00;
    if ($retail < 20) return 5.00;
    if ($retail < 35) return 10.00;
    if ($retail < 50) return 15.00;
    if ($retail < 75) return 25.00;
    if ($retail < 100) return 35.00;
    return round($retail * 0.35);
}

function nsr_live_extract_best_price($product) {
    $prices = array();

    $possible_fields = array(
        'price',
        'lowest_recorded_price',
        'highest_recorded_price',
        'avg_price',
        'offer_price'
    );

    foreach ($possible_fields as $field) {
        if (!empty($product[$field]) && is_numeric($product[$field])) {
            $prices[] = (float)$product[$field];
        }
    }

    if (!empty($product['stores']) && is_array($product['stores'])) {
        foreach ($product['stores'] as $store) {
            if (isset($store['price']) && is_numeric($store['price'])) {
                $prices[] = (float)$store['price'];
            }
        }
    }

    $prices = array_filter($prices, function($p){ return $p > 0; });
    if (empty($prices)) return 0;

    return min($prices);
}

function nsr_live_real_lookup_barcodelookup($barcode, $api_key) {
    $barcode = preg_replace('/\D+/', '', (string)$barcode);
    if ($barcode === '' || $api_key === '') {
        return array('error' => 'Missing barcode or Barcode Lookup API key.');
    }

    $url = add_query_arg(array(
        'barcode' => $barcode,
        'formatted' => 'y',
        'key' => $api_key,
    ), 'https://api.barcodelookup.com/v3/products');

    $response = wp_remote_get($url, array(
        'timeout' => 20,
        'headers' => array('Accept' => 'application/json'),
    ));

    if (is_wp_error($response)) {
        return array('error' => 'Barcode Lookup request failed: ' . $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if ($code !== 200 || !is_array($json)) {
        return array('error' => 'Barcode Lookup failed. HTTP ' . $code);
    }

    if (empty($json['products'][0]) || !is_array($json['products'][0])) {
        return array('error' => 'Barcode Lookup: no product found.');
    }

    $p = $json['products'][0];
    $retail = nsr_live_extract_best_price($p);

    $title = '';
    if (!empty($p['title'])) $title = $p['title'];
    elseif (!empty($p['product_name'])) $title = $p['product_name'];
    else $title = 'Scanned Product #' . substr($barcode, -4);

    $image = '';
    if (!empty($p['images']) && is_array($p['images']) && !empty($p['images'][0])) {
        $image = esc_url_raw($p['images'][0]);
    }

    $brand = !empty($p['brand']) ? $p['brand'] : '';
    $category = !empty($p['category']) ? $p['category'] : '';
    $description = !empty($p['description']) ? wp_strip_all_tags($p['description']) : '';

    return array(
        'provider' => 'Barcode Lookup',
        'barcode' => $barcode,
        'title' => $title,
        'retail' => $retail > 0 ? $retail : 0,
        'live' => nsr_live_suggest_price($retail > 0 ? $retail : 0),
        'qty' => 1,
        'source' => 'Mixed',
        'note' => trim($brand . ($category ? ' | ' . $category : '')),
        'image' => $image,
        'brand' => $brand,
        'category' => $category,
        'description' => $description,
    );
}

function nsr_live_real_lookup_upcitemdb($barcode) {
    $barcode = preg_replace('/\D+/', '', (string)$barcode);
    if ($barcode === '') {
        return array('error' => 'Missing barcode.');
    }

    $url = 'https://api.upcitemdb.com/prod/trial/lookup?upc=' . rawurlencode($barcode);

    $response = wp_remote_get($url, array(
        'timeout' => 20,
        'headers' => array('Accept' => 'application/json'),
    ));

    if (is_wp_error($response)) {
        return array('error' => 'UPCitemdb request failed: ' . $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    if ($code !== 200 || !is_array($json)) {
        return array('error' => 'UPCitemdb failed. HTTP ' . $code);
    }

    if (empty($json['items'][0]) || !is_array($json['items'][0])) {
        return array('error' => 'UPCitemdb: no product found.');
    }

    $p = $json['items'][0];

    $title = !empty($p['title']) ? $p['title'] : 'Scanned Product #' . substr($barcode, -4);
    $brand = !empty($p['brand']) ? $p['brand'] : '';
    $description = !empty($p['description']) ? wp_strip_all_tags($p['description']) : '';
    $category = !empty($p['category']) ? $p['category'] : '';

    $retail = 0;
    if (!empty($p['offers']) && is_array($p['offers'])) {
        $offer_prices = array();
        foreach ($p['offers'] as $offer) {
            if (isset($offer['price']) && is_numeric($offer['price'])) {
                $offer_prices[] = (float)$offer['price'];
            }
        }
        if (!empty($offer_prices)) {
            $retail = min($offer_prices);
        }
    }

    $image = '';
    if (!empty($p['images']) && is_array($p['images']) && !empty($p['images'][0])) {
        $image = esc_url_raw($p['images'][0]);
    }

    return array(
        'provider' => 'UPCitemdb',
        'barcode' => $barcode,
        'title' => $title,
        'retail' => $retail > 0 ? $retail : 0,
        'live' => nsr_live_suggest_price($retail > 0 ? $retail : 0),
        'qty' => 1,
        'source' => 'Mixed',
        'note' => trim($brand . ($category ? ' | ' . $category : '')),
        'image' => $image,
        'brand' => $brand,
        'category' => $category,
        'description' => $description,
    );
}

function nsr_live_multi_lookup($barcode, $state) {
    $barcode_key = trim((string)$state['barcode_lookup_api_key']);

    if ($barcode_key !== '') {
        $primary = nsr_live_real_lookup_barcodelookup($barcode, $barcode_key);
        if (empty($primary['error']) && !empty($primary['title'])) {
            return $primary;
        }
    }

    $fallback = nsr_live_real_lookup_upcitemdb($barcode);
    if (empty($fallback['error']) && !empty($fallback['title'])) {
        return $fallback;
    }

    if (!empty($primary['error'])) {
        return array('error' => $primary['error'] . ' | ' . ($fallback['error'] ?? 'Fallback lookup failed.'));
    }

    return $fallback;
}

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_POST['nsr_live_action'])) return;

    check_admin_referer('nsr_live_action', 'nsr_live_nonce');

    $state = nsr_live_state();
    nsr_live_maybe_expire_timer($state);
    $action = sanitize_text_field($_POST['nsr_live_action']);
    $redirect_to = !empty($_POST['redirect_to']) ? esc_url_raw($_POST['redirect_to']) : wp_get_referer();

    if ($action === 'save_settings') {
        $state['show_title'] = sanitize_text_field($_POST['show_title'] ?? $state['show_title']);
        $state['tagline'] = sanitize_text_field($_POST['tagline'] ?? $state['tagline']);
        $state['follower_goal_current'] = max(0, intval($_POST['follower_goal_current'] ?? 0));
        $state['follower_goal_target'] = max(1, intval($_POST['follower_goal_target'] ?? 1000));
        $state['next_item_no'] = max(1, intval($_POST['next_item_no'] ?? $state['next_item_no']));
        $state['barcode_lookup_api_key'] = sanitize_text_field($_POST['barcode_lookup_api_key'] ?? $state['barcode_lookup_api_key']);
        $state['upcdatabase_api_key'] = sanitize_text_field($_POST['upcdatabase_api_key'] ?? $state['upcdatabase_api_key']);
        $state['last_action'] = 'Settings saved.';
    }

    if ($action === 'scanner_lookup') {
        $barcode = sanitize_text_field($_POST['scanner_barcode'] ?? '');
        $draft = nsr_live_multi_lookup($barcode, $state);

        if (!empty($draft['error'])) {
            $state['scanner_draft'] = array(
                'provider' => 'Manual',
                'barcode' => preg_replace('/\D+/', '', $barcode),
                'title' => '',
                'retail' => 0,
                'live' => 5,
                'qty' => 1,
                'source' => 'Mixed',
                'note' => 'Manual review needed',
                'image' => '',
                'brand' => '',
                'category' => '',
                'description' => '',
            );
            $state['last_action'] = $draft['error'];
        } else {
            $state['scanner_draft'] = $draft;
            $provider = !empty($draft['provider']) ? $draft['provider'] : 'lookup';
            $state['last_action'] = $provider . ' result loaded for barcode ' . $draft['barcode'] . '. Review and add to queue.';
        }
    }

    if ($action === 'scanner_clear') {
        $state['scanner_draft'] = array();
        $state['last_action'] = 'Scanner draft cleared.';
    }
if ($action === 'scanner_add_to_queue') {
    $draft = array(
        'item_no' => sanitize_text_field($_POST['item_no'] ?? ''),
        'title'   => sanitize_text_field($_POST['title'] ?? ''),
        'source'  => sanitize_text_field($_POST['source'] ?? 'Mixed'),
        'retail'  => (float)($_POST['retail'] ?? 0),
        'live'    => (float)($_POST['live'] ?? 0),
        'qty'     => max(1, intval($_POST['qty'] ?? 1)),
        'claimed' => 0,
        'barcode' => sanitize_text_field($_POST['barcode'] ?? ''),
        'note'    => sanitize_text_field($_POST['note'] ?? ''),
        'image'   => esc_url_raw($_POST['image'] ?? ''),
        'brand'   => sanitize_text_field($_POST['brand'] ?? ''),
        'category'=> sanitize_text_field($_POST['category'] ?? ''),
        'description' => sanitize_text_field($_POST['description'] ?? ''),
    );

    if ($draft['item_no'] === '') {
        $draft['item_no'] = nsr_live_auto_item_number($state);
    }

    if ($draft['title'] !== '') {
        $state['queue'][] = $draft;

        if (function_exists('nsr_attach_item_to_active_pallet')) {
            nsr_attach_item_to_active_pallet($draft);
        }

        $state['scanner_draft'] = array();
        $state['last_action'] = 'Scanned item ' . $draft['item_no'] . ' added to queue.';
    } else {
        $state['last_action'] = 'Title is required before adding scanner draft.';
    }
}
    if ($action === 'add_queue_item') {
        $provided_item_no = sanitize_text_field($_POST['item_no'] ?? '');
        $item_no = $provided_item_no !== '' ? $provided_item_no : nsr_live_auto_item_number($state);

        $item = array(
            'item_no' => $item_no,
            'title'   => sanitize_text_field($_POST['title'] ?? ''),
            'source'  => sanitize_text_field($_POST['source'] ?? 'Mixed'),
            'retail'  => (float)($_POST['retail'] ?? 0),
            'live'    => (float)($_POST['live'] ?? 0),
            'qty'     => max(1, intval($_POST['qty'] ?? 1)),
            'claimed' => 0,
            'barcode' => sanitize_text_field($_POST['barcode'] ?? ''),
            'note'    => sanitize_text_field($_POST['note'] ?? ''),
        );

        if ($item['title']) {
            $state['queue'][] = $item;
            $state['last_action'] = 'Added item ' . $item['item_no'] . ' to queue.';
        } else {
            $state['last_action'] = 'Title is required.';
        }
    }

    if ($action === 'delete_queue_item') {
        $idx = intval($_POST['idx'] ?? -1);
        if (isset($state['queue'][$idx])) {
            $state['last_action'] = 'Deleted item ' . $state['queue'][$idx]['item_no'] . ' from queue.';
            array_splice($state['queue'], $idx, 1);
            if ($state['current_index'] >= count($state['queue'])) {
                $state['current_index'] = max(0, count($state['queue']) - 1);
            }
        }
    }

    if ($action === 'start_show') {
        $state['is_live'] = 1;
        $state['timer_end'] = 0;
        $state['last_action'] = 'Show started.';
    }

    if ($action === 'end_show') {
        $state['is_live'] = 0;
        $state['timer_end'] = 0;
        $state['last_action'] = 'Show ended.';
    }

    if ($action === 'reveal_item') {
        $idx = intval($_POST['idx'] ?? $state['current_index']);
        if (isset($state['queue'][$idx])) {
            $state['current_index'] = $idx;
            $state['timer_end'] = 0;
            $state['revealed_count'] = max($state['revealed_count'], $idx + 1);
            $state['last_action'] = 'Revealed item ' . $state['queue'][$idx]['item_no'] . '. Timer is off until you start it.';
        }
    }

    if ($action === 'start_timer') {
        $seconds = max(10, intval($_POST['seconds'] ?? 45));
        $state['timer_seconds'] = $seconds;
        $state['timer_end'] = time() + $seconds;
        $current = nsr_live_current_item($state);
        $state['last_action'] = $current ? 'Started timer for item ' . $current['item_no'] . '.' : 'Started timer.';
    }

    if ($action === 'next_item') {
        $current = nsr_live_current_item($state);
        if ($current) {
            nsr_live_push_recent($state, $current);
        }
        $state['current_index'] = min(max(0, count($state['queue']) - 1), intval($state['current_index']) + 1);
        $state['timer_end'] = 0;
        $state['revealed_count'] = max($state['revealed_count'], $state['current_index'] + 1);
        $current = nsr_live_current_item($state);
        $state['last_action'] = $current ? 'Moved to next item ' . $current['item_no'] . '. Timer is off until you start it.' : 'Moved to next item.';
    }

    if ($action === 'simulate_claim') {
        $count = max(1, intval($_POST['claim_qty'] ?? 1));
        $idx = intval($_POST['idx'] ?? $state['current_index']);

        if (isset($state['queue'][$idx])) {
            $item = &$state['queue'][$idx];
            $take = min(nsr_live_stock_left($item), $count);

            if ($take > 0) {
                $item['claimed'] += $take;
                $state['claims_total'] += $take;
                $state['total_savings'] += (($item['retail'] - $item['live']) * $take);
                $left = nsr_live_stock_left($item);

                if ($left <= 0) {
                    nsr_live_push_recent($state, $item, 'SOLD OUT');
                    $state['last_action'] = 'SOLD OUT: item ' . $item['item_no'] . '. Auto-switched to next item. Timer is off until you start it.';
                    if ($idx < count($state['queue']) - 1) {
                        $state['current_index'] = $idx + 1;
                        $state['revealed_count'] = max($state['revealed_count'], $state['current_index'] + 1);
                    }
                    $state['timer_end'] = 0;
                } else {
                    $state['last_action'] = 'Claimed ' . $take . ' from item ' . $item['item_no'] . '. ' . $left . ' left.';
                }
            }
        }
    }

    if ($action === 'go_live_again') {
        $recent_idx = intval($_POST['recent_idx'] ?? -1);
        if (isset($state['recent'][$recent_idx])) {
            $r = $state['recent'][$recent_idx];
            $found_idx = -1;
            foreach ($state['queue'] as $idx => $item) {
                if ($item['item_no'] === $r['item_no']) {
                    $found_idx = $idx;
                    break;
                }
            }

            if ($found_idx >= 0) {
                $state['current_index'] = $found_idx;
            } else {
                $state['queue'][] = array(
                    'item_no' => $r['item_no'],
                    'title'   => $r['title'],
                    'source'  => isset($r['source']) ? $r['source'] : 'Mixed',
                    'retail'  => isset($r['retail']) ? (float)$r['retail'] : 0,
                    'live'    => isset($r['live']) ? (float)$r['live'] : 0,
                    'qty'     => isset($r['qty']) ? intval($r['qty']) : 1,
                    'claimed' => isset($r['claimed']) ? intval($r['claimed']) : 0,
                    'barcode' => isset($r['barcode']) ? $r['barcode'] : '',
                    'note'    => isset($r['note']) ? $r['note'] : '',
                );
                $state['current_index'] = count($state['queue']) - 1;
            }

            $state['timer_end'] = 0;
            $state['last_action'] = 'Brought item ' . $r['item_no'] . ' live again. Timer is off until you start it.';
        }
       if ($action === 'show_mode_trigger') {
       $effect = sanitize_text_field($_POST['effect'] ?? '');
    wp_die('SHOW MODE TRIGGER HIT: [' . esc_html($effect) . ']');
}
}

if ($action === 'show_mode_clear') {
    $state['show_mode_effect'] = '';
    $state['show_mode_banner'] = '';
    $state['last_action'] = 'Show mode banner cleared.';
}

if ($action === 'toggle_show_fx') {
    $state['show_fx_enabled'] = empty($state['show_fx_enabled']) ? 1 : 0;
    $state['last_action'] = $state['show_fx_enabled'] ? 'Show FX enabled.' : 'Show FX disabled.';
}

if ($action === 'toggle_show_music') {
    $state['show_music_enabled'] = empty($state['show_music_enabled']) ? 1 : 0;
    $state['last_action'] = $state['show_music_enabled'] ? 'Show music mode enabled.' : 'Show music mode disabled.';
}

    nsr_live_save($state);
    wp_safe_redirect($redirect_to ?: admin_url('admin.php?page=nsr-live'));
    exit;
});

add_action('wp_ajax_nsr_live_state', function() {
    $state = nsr_live_state();
    nsr_live_maybe_expire_timer($state);
    $current = nsr_live_current_item($state);

    wp_send_json_success(array(
        'is_live' => intval($state['is_live']),
        'timer_end' => intval($state['timer_end']),
        'current_item_no' => $current ? $current['item_no'] : '',
        'claims_total' => intval($state['claims_total']),
        'total_savings' => (float)$state['total_savings'],
        'signature' => md5(wp_json_encode(array(
            'is_live' => $state['is_live'],
            'timer_end' => $state['timer_end'],
            'current_index' => $state['current_index'],
            'claims_total' => $state['claims_total'],
            'total_savings' => $state['total_savings'],
            'queue' => $state['queue'],
            'recent' => $state['recent'],
        ))),
    ));
});

function nsr_live_cue_cards($state) {
    $cards = array();

    if (!$state['is_live']) {
        $cards[] = 'Start with: “Comment HELLO if you are here.”';
        $cards[] = 'Ask viewers to FOLLOW the page.';
        $cards[] = 'Ask viewers to SHARE the live.';
    } else {
        $cards[] = 'Remind viewers to type the ITEM NUMBER to claim.';
        $cards[] = 'Mention TOTAL SAVED TONIGHT.';
        $cards[] = 'Ask viewers to FOLLOW and SHARE.';
        if ($state['revealed_count'] > 0) {
            $cards[] = 'Tell viewers how many items have been revealed so far.';
        }
    }

    return $cards;
}

function nsr_live_studio_page() {
    $state = nsr_live_state();
    nsr_live_maybe_expire_timer($state);
    $current = nsr_live_current_item($state);
    $upnext = nsr_live_upnext($state);
    $timer_left = (!empty($state['timer_end']) ? max(0, intval($state['timer_end']) - time()) : 0);

   nsr_live_styles();
nsr_live_notice($state);
?>
<?php if (!empty($state['show_mode_banner'])) { ?>
    <div class="nsr-showmode-banner <?php echo !empty($state['show_mode_effect']) ? esc_attr($state['show_mode_effect']) : ''; ?>"
         data-effect="<?php echo !empty($state['show_mode_effect']) ? esc_attr($state['show_mode_effect']) : ''; ?>"
         data-fx="<?php echo !empty($state['show_fx_enabled']) ? '1' : '0'; ?>">
        <?php echo esc_html($state['show_mode_banner']); ?>
    </div>
<?php } ?>
<div class="nsr-showmode-wrap">
    <div class="nsr-showmode-card">
        <div class="nsr-pill">SHOW MODE</div>
        <h2>Live Energy Controls</h2>
        <p>Trigger quick audience moments during the show.</p>

      <a class="button button-primary" href="<?php echo admin_url('admin-post.php?action=nsr_live_action&nsr_live_action=show_mode_trigger&effect=flash'); ?>">
    Flash Deal TEST
</a>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="nsr-show-buttons">
    <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
    <input type="hidden" name="action" value="nsr_live_action">
    <input type="hidden" name="nsr_live_action" value="show_mode_trigger">
    <input type="hidden" name="effect" value="sold">
    <button class="button">Sold Alert</button>
</form>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="nsr-show-buttons">
    <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
    <input type="hidden" name="action" value="nsr_live_action">
    <input type="hidden" name="nsr_live_action" value="show_mode_trigger">
    <input type="hidden" name="effect" value="mystery">
    <button class="button">Mystery Item</button>
</form>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="nsr-show-buttons">
    <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
    <input type="hidden" name="action" value="nsr_live_action">
    <input type="hidden" name="nsr_live_action" value="show_mode_trigger">
    <input type="hidden" name="effect" value="hype">
    <button class="button">Claim Hype</button>
</form>

        <div class="nsr-show-buttons">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
    <input type="hidden" name="action" value="nsr_live_action">
    <input type="hidden" name="nsr_live_action" value="toggle_show_fx">
                <button class="button <?php echo !empty($state['show_fx_enabled']) ? 'button-primary nsr-fx-on' : 'nsr-fx-off'; ?>">
    <?php echo !empty($state['show_fx_enabled']) ? 'FX ON' : 'FX OFF'; ?>
</button>
            </form>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
    <input type="hidden" name="action" value="nsr_live_action">
    <input type="hidden" name="nsr_live_action" value="toggle_show_music">
                <button class="button <?php echo !empty($state['show_music_enabled']) ? 'button-primary nsr-fx-on' : 'nsr-fx-off'; ?>">
    <?php echo !empty($state['show_music_enabled']) ? 'Music Mode ON' : 'Music Mode OFF'; ?>
</button>
            </form>

            <form method="post">
                <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                <input type="hidden" name="nsr_live_action" value="show_mode_clear">
                <button class="button">Clear Banner</button>
            </form>
        </div>
    </div>

    <div class="nsr-host-cues">
        <div class="nsr-pill">HOST CUES</div>
        <h3>Energy Prompts</h3>
        <p>“Drop a comment if you’re here.”</p>
        <p>“Type the item number to claim.”</p>
        <p>“Follow the page and share the live.”</p>
        <p>“Only a few left — don’t wait.”</p>
        <p>“Who wants a flash deal?”</p>
        <p>“Claim train time — let’s go!”</p>
    </div>
</div>
    <div class="wrap nsr-live-wrap">
        <h1>Never Say Retail Live Studio TEST BUILD 4.9.3</h1>

        <div class="nsr-admin-grid">
            <div class="nsr-card">
                <h2>Show Controls</h2>
                <div class="nsr-row">
                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="start_show">
                        <button class="button button-primary">Start Show</button>
                    </form>
                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="end_show">
                        <button class="button">End Show</button>
                    </form>
                </div>
                <p><strong>Status:</strong> <?php echo $state['is_live'] ? 'LIVE' : 'OFFLINE'; ?></p>
                <p><strong>Follower Goal:</strong> <?php echo intval($state['follower_goal_current']); ?> / <?php echo intval($state['follower_goal_target']); ?></p>
                <p><strong>Next Auto Item #:</strong> <?php echo intval($state['next_item_no']); ?></p>
                <p><strong>Current Timer:</strong> <?php echo $timer_left > 0 ? gmdate('i:s', $timer_left) : 'OFF'; ?></p>
                <p><strong>Items Revealed:</strong> <?php echo intval($state['revealed_count']); ?> / <?php echo intval(count($state['queue'])); ?></p>

                <div class="nsr-cue-box">
                    <h3>🎤 Host Cue Cards</h3>
                    <ul class="nsr-cue-list">
                        <?php foreach (nsr_live_cue_cards($state) as $cue): ?>
                            <li><?php echo esc_html($cue); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="nsr-card">
                <h2>Current Item</h2>
                <?php if ($current): ?>
                    <div class="nsr-current-no">ITEM <?php echo esc_html($current['item_no']); ?></div>
                    <h3><?php echo esc_html($current['title']); ?></h3>
                    <p><strong><?php echo esc_html($current['source']); ?> Retail:</strong> <?php echo esc_html(nsr_live_format_money($current['retail'])); ?></p>
                    <p><strong>NSR Price:</strong> <?php echo esc_html(nsr_live_format_money($current['live'])); ?></p>
                    <p><strong>Save:</strong> <?php echo esc_html(nsr_live_format_money($current['retail'] - $current['live'])); ?> • <?php echo esc_html(nsr_live_discount_pct($current['retail'], $current['live'])); ?>% OFF</p>
                    <p><strong>Inventory:</strong> <?php echo nsr_live_stock_left($current); ?> left</p>
                    <p><strong>Keyboard Shortcuts:</strong> R reveal • T timer • N next</p>
                <?php else: ?>
                    <p>No queue items yet. Add them under Queue or Scanner.</p>
                <?php endif; ?>

                <div class="nsr-row wrap-buttons">
                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="reveal_item">
                        <input type="hidden" name="idx" value="<?php echo intval($state['current_index']); ?>">
                        <button class="button button-primary">Reveal Item</button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="start_timer">
                        <input type="hidden" name="seconds" value="45">
                        <button class="button">Start Timer</button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="next_item">
                        <button class="button">Next Item</button>
                    </form>
                </div>

                <div class="nsr-row wrap-buttons">
                    <button class="button">Flash Deal</button>
                    <button class="button">Spin Wheel</button>
                    <button class="button">Rapid Fire</button>
                    <button class="button">Golden Ticket</button>
                    <button class="button">Mystery Item</button>
                </div>

                <div class="nsr-row wrap-buttons">
                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="simulate_claim">
                        <input type="hidden" name="idx" value="<?php echo intval($state['current_index']); ?>">
                        <input type="hidden" name="claim_qty" value="1">
                        <button class="button">Sim Claim +1</button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="simulate_claim">
                        <input type="hidden" name="idx" value="<?php echo intval($state['current_index']); ?>">
                        <input type="hidden" name="claim_qty" value="2">
                        <button class="button">Sim Claim +2</button>
                    </form>
                </div>
            </div>

            <div class="nsr-card">
                <h2>Show Stats</h2>
                <p><strong>Total Claims:</strong> <?php echo intval($state['claims_total']); ?></p>
                <p><strong>Total Savings Tonight:</strong> <?php echo esc_html(nsr_live_format_money($state['total_savings'])); ?></p>
                <p><strong>Deal of the Night:</strong> Auto-tracked in next build</p>
                <p><strong>Top Savers / Buyers:</strong> Display foundation included</p>
            </div>
        </div>

        <div class="nsr-admin-grid second">
            <div class="nsr-card">
                <h2>Recent Deals</h2>
                <?php
                $full_recent = array_reverse($state['recent']);
                if (!$full_recent): ?><p>No recent deals yet.</p><?php endif; ?>
                <?php
                foreach ($full_recent as $display_idx => $r):
                    $real_idx = count($state['recent']) - 1 - $display_idx;
                ?>
                    <div class="nsr-list-row">
                        <div><?php echo esc_html($r['item_no'] . ' – ' . $r['title'] . ' – ' . $r['status']); ?></div>
                        <form method="post" class="nsr-golive-btn">
                            <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                            <input type="hidden" name="nsr_live_action" value="go_live_again">
                            <input type="hidden" name="recent_idx" value="<?php echo intval($real_idx); ?>">
                            <button class="button button-small">Go Live Again</button>
                        </form>
                    </div>
                    <?php if ($display_idx >= 4) break; ?>
                <?php endforeach; ?>
            </div>

            <div class="nsr-card">
                <h2>Up Next</h2>
                <?php if (!$upnext): ?><p>No upcoming items.</p><?php endif; ?>
                <?php foreach ($upnext as $u): ?>
                    <div class="nsr-list-row"><?php echo esc_html($u['item_no'] . ' – ' . $u['title']); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function nsr_live_queue_page() {
    $state = nsr_live_state();

    nsr_live_styles();
    nsr_live_notice($state);
    ?>
    <div class="wrap nsr-live-wrap">
        <h1>Queue Builder</h1>

        <div class="nsr-admin-grid">
            <div class="nsr-card">
                <h2>Add Queue Item</h2>
                <p><strong>Auto Item # Preview:</strong> <?php echo intval($state['next_item_no']); ?> (leave Item # blank to use this)</p>

                <form method="post" class="nsr-form-grid">
                    <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                    <input type="hidden" name="nsr_live_action" value="add_queue_item">

                    <label>Item # (optional)
                        <input type="text" name="item_no" placeholder="<?php echo esc_attr($state['next_item_no']); ?>">
                    </label>

                    <label>Title
                        <input type="text" name="title" required autofocus>
                    </label>

                    <label>Source
                        <select name="source">
                            <option>Target</option>
                            <option>Amazon</option>
                            <option>Sam's</option>
                            <option>DG</option>
                            <option>Mixed</option>
                        </select>
                    </label>

                    <label>Retail
                        <input type="number" step="0.01" name="retail" required>
                    </label>

                    <label>NSR Price
                        <input type="number" step="0.01" name="live" required>
                    </label>

                    <label>Qty
                        <input type="number" name="qty" value="1" min="1">
                    </label>

                    <label>Barcode (optional)
                        <input type="text" name="barcode">
                    </label>

                    <label>Quick note
                        <input type="text" name="note">
                    </label>

                    <div>
                        <button class="button button-primary">Add to Queue</button>
                    </div>
                </form>
            </div>

            <div class="nsr-card">
                <h2>Quick Add by Barcode</h2>
                <p>Use <strong>NSR Live → Scanner</strong> for the faster barcode workflow.</p>
                <p>The scanner now tries Barcode Lookup first, then UPCitemdb fallback.</p>
            </div>
        </div>

        <div class="nsr-card">
            <h2>Current Queue</h2>
            <?php if (empty($state['queue'])): ?>
                <p>No items in queue yet.</p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Item #</th>
                            <th>Title</th>
                            <th>Source</th>
                            <th>Retail</th>
                            <th>NSR</th>
                            <th>Qty</th>
                            <th>Claimed</th>
                            <th>Barcode</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($state['queue'] as $idx => $item): ?>
                            <tr>
                                <td><?php echo esc_html($item['item_no']); ?></td>
                                <td><?php echo esc_html($item['title']); ?></td>
                                <td><?php echo esc_html($item['source']); ?></td>
                                <td><?php echo esc_html(nsr_live_format_money($item['retail'])); ?></td>
                                <td><?php echo esc_html(nsr_live_format_money($item['live'])); ?></td>
                                <td><?php echo intval($item['qty']); ?></td>
                                <td><?php echo intval($item['claimed']); ?></td>
                                <td><?php echo esc_html($item['barcode']); ?></td>
                                <td>
                                    <form method="post">
                                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                                        <input type="hidden" name="nsr_live_action" value="delete_queue_item">
                                        <input type="hidden" name="idx" value="<?php echo intval($idx); ?>">
                                        <button class="button button-small">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="nsr-small">Drag-and-drop queue order is planned for the next upgrade.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function nsr_live_scanner_page() {
    $state = nsr_live_state();
    $draft = isset($state['scanner_draft']) && is_array($state['scanner_draft']) ? $state['scanner_draft'] : array();

    $pallets = function_exists('nsr_get_pallets') ? nsr_get_pallets() : array();
    $active_pallet_id = function_exists('nsr_get_active_pallet_id') ? nsr_get_active_pallet_id() : 0;
    $active_pallet = function_exists('nsr_get_active_pallet') ? nsr_get_active_pallet() : null;
    $pallet_totals = ($active_pallet && function_exists('nsr_calculate_pallet_totals')) ? nsr_calculate_pallet_totals($active_pallet) : null;

    nsr_live_styles();
    nsr_live_notice($state);
    ?>
    <div class="wrap nsr-live-wrap">
        <h1>Scanner</h1>

        <div class="nsr-scanner-grid">
            <div class="nsr-card">
                <h2>Scan Item</h2>
                <p>Use your USB, wireless, or keyboard-style scanner here. The barcode field stays ready.</p>

                <?php if (!empty($pallets) && function_exists('nsr_set_active_pallet_id')): ?>
                    <form method="post" style="margin-bottom:16px;">
                        <?php wp_nonce_field('nsr_pallet_action', 'nsr_pallet_nonce'); ?>
                        <input type="hidden" name="nsr_action" value="set_active_pallet">
                        <label for="active_pallet_id"><strong>Active Pallet</strong></label><br>
                        <select name="active_pallet_id" id="active_pallet_id">
                            <option value="0">— No Active Pallet —</option>
                            <?php foreach ($pallets as $idx => $pallet): ?>
                                <option value="<?php echo intval($idx); ?>" <?php selected($active_pallet_id, $idx); ?>>
                                    <?php echo esc_html($pallet['name'] . ' ($' . number_format(floatval($pallet['cost']), 2) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button" style="margin-left:8px;">Set Active</button>
                    </form>
                <?php endif; ?>

                <?php if ($active_pallet && $pallet_totals): ?>
                    <div class="nsr-api-help" style="margin-bottom:16px;">
                        <strong>Active Pallet: <?php echo esc_html($active_pallet['name']); ?></strong>
                        <p class="nsr-small" style="margin:8px 0 0 0;">
                            Cost: <?php echo esc_html(nsr_live_format_money($pallet_totals['cost'])); ?><br>
                            Items: <?php echo intval($pallet_totals['items']); ?><br>
                            Retail Value: <?php echo esc_html(nsr_live_format_money($pallet_totals['retail'])); ?><br>
                            Est Sale Value: <?php echo esc_html(nsr_live_format_money($pallet_totals['sales'])); ?><br>
                            Profit: <?php echo esc_html(nsr_live_format_money($pallet_totals['profit'])); ?><br>
                            Break-even Remaining: <?php echo esc_html(nsr_live_format_money($pallet_totals['break_even_remaining'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                    <input type="hidden" name="nsr_live_action" value="scanner_lookup">
                    <label for="scanner_barcode"><strong>Barcode</strong></label>
                    <input
                        id="scanner_barcode"
                        class="nsr-scan-input"
                        type="text"
                        name="scanner_barcode"
                        autocomplete="off"
                        autofocus
                        placeholder="Scan barcode here"
                    >
                    <p style="margin-top:12px">
                        <button class="button button-primary">Lookup Barcode</button>
                    </p>
                </form>

                <div class="nsr-highlight">
                    <strong>Fast workflow</strong>
                    <p class="nsr-small" style="margin:8px 0 0 0">Scan → review result → set qty → add to queue → cursor returns to barcode.</p>
                </div>

                <div class="nsr-api-help">
                    <strong>Lookup order</strong>
                    <p class="nsr-small" style="margin:6px 0 0 0">1. Barcode Lookup if key is filled<br>2. UPCitemdb fallback<br>3. Manual review if needed</p>
                </div>
            </div>

            <div class="nsr-card">
                <h2>Scanner Draft</h2>

                <?php if (!empty($draft)): ?>
                    <div class="nsr-draft-badge">READY TO REVIEW<?php echo !empty($draft['provider']) ? ' • ' . esc_html($draft['provider']) : ''; ?></div>

                    <?php if (!empty($draft['image'])): ?>
                        <div class="nsr-image-preview">
                            <img src="<?php echo esc_url($draft['image']); ?>" alt="">
                        </div>
                    <?php endif; ?>

<?php
$smart_price = '';
$pricing_context = array();

if (function_exists('nsr_calculate_hybrid_pricing_context') && $active_pallet && $pallet_totals) {
    $pricing_context = nsr_calculate_hybrid_pricing_context(
        floatval($draft['retail'] ?? 0),
        floatval($pallet_totals['cost']),
        floatval($pallet_totals['sales'])
    );
    $smart_price = $pricing_context['hybrid_price'];
}
?>

<?php if ($smart_price !== '' && !empty($pricing_context)) { ?>
    <div class="nsr-price-status <?php echo esc_attr($pricing_context['status']); ?>">
        <strong><?php echo esc_html($pricing_context['status_icon'] . ' Status: ' . $pricing_context['status_label']); ?></strong>

        <div class="nsr-price-grid nsr-small">
            <div>Retail:</div>
            <div><?php echo esc_html(nsr_live_format_money(floatval($draft['retail'] ?? 0))); ?></div>

            <div>Base Suggested Price:</div>
            <div><?php echo esc_html(nsr_live_format_money($pricing_context['base_price'])); ?></div>

            <div>Hybrid Suggested Price:</div>
            <div><?php echo esc_html(nsr_live_format_money($pricing_context['hybrid_price'])); ?></div>

            <div>Max Profit Price:</div>
            <div><?php echo esc_html(nsr_live_format_money($pricing_context['max_profit_price'])); ?></div>

            <div>Estimated Profit If Sold:</div>
            <div><?php echo esc_html(nsr_live_format_money($pricing_context['estimated_profit'])); ?></div>

            <?php if ($pricing_context['margin_percent'] !== null) { ?>
                <div>Margin:</div>
                <div><?php echo esc_html($pricing_context['margin_percent']); ?>%</div>
            <?php } ?>

            <div>Break-even Remaining Now:</div>
            <div><?php echo esc_html(nsr_live_format_money($pricing_context['remaining_now'])); ?></div>

            <div>Break-even Remaining After Sale:</div>
            <div><?php echo esc_html(nsr_live_format_money($pricing_context['remaining_after_sale'])); ?></div>
        </div>

        <?php if (!empty($pricing_context['needs_retail'])) { ?>
            <div class="nsr-price-note nsr-small">Enter retail price for more accurate margin and profit guidance.</div>
        <?php } ?>

        <div class="nsr-smart-actions">
            <button type="button" class="button" onclick="document.querySelector('.nsr-live-input').value='<?php echo esc_attr($pricing_context['hybrid_price']); ?>'">
                Use Smart Price
            </button>
            <button type="button" class="button" onclick="document.querySelector('.nsr-live-input').value='<?php echo esc_attr($pricing_context['max_profit_price']); ?>'">
                Use Max Profit Price
            </button>
        </div>
    </div>
<?php } ?>

                    <form method="post" class="nsr-form-grid">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="scanner_add_to_queue">

                        <input type="hidden" name="image" value="<?php echo esc_attr($draft['image'] ?? ''); ?>">
                        <input type="hidden" name="brand" value="<?php echo esc_attr($draft['brand'] ?? ''); ?>">
                        <input type="hidden" name="category" value="<?php echo esc_attr($draft['category'] ?? ''); ?>">
                        <input type="hidden" name="description" value="<?php echo esc_attr($draft['description'] ?? ''); ?>">

                        <label>Item # (optional)
                            <input type="text" name="item_no" placeholder="<?php echo esc_attr($state['next_item_no']); ?>">
                        </label>

                        <label>Barcode
                            <input type="text" name="barcode" value="<?php echo esc_attr($draft['barcode']); ?>">
                        </label>

                        <label>Title
                            <input type="text" name="title" value="<?php echo esc_attr($draft['title']); ?>" required>
                        </label>

                        <label>Source
                            <select name="source">
                                <?php
                                $sources = array('Target', 'Amazon', "Sam's", 'DG', 'Mixed');
                                foreach ($sources as $source) {
                                    echo '<option value="' . esc_attr($source) . '"' . selected($draft['source'], $source, false) . '>' . esc_html($source) . '</option>';
                                }
                                ?>
                            </select>
                        </label>

                        <label>Retail
                            <input class="nsr-retail-input" type="number" step="0.01" name="retail" value="<?php echo esc_attr($draft['retail']); ?>" required>
                        </label>

                        <label>Suggested NSR Price
                            <input class="nsr-live-input" type="number" step="0.01" name="live" value="<?php echo esc_attr($smart_price !== '' ? $smart_price : ($draft['live'] ?? '')); ?>" required>
                        </label>

                        <label>Qty
                            <input type="number" name="qty" value="<?php echo esc_attr($draft['qty']); ?>" min="1">
                        </label>

                        <label>Quick note
                            <input type="text" name="note" value="<?php echo esc_attr($draft['note']); ?>">
                        </label>

                        <div>
                            <button class="button button-primary">Add to Queue</button>
                        </div>
                    </form>

                    <form method="post" style="margin-top:10px">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                        <input type="hidden" name="nsr_live_action" value="scanner_clear">
                        <button class="button">Clear Draft</button>
                    </form>
                <?php else: ?>
                    <p>No scanner draft yet.</p>
                    <p class="nsr-small">Once you scan a barcode, the review form will appear here.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function nsr_live_settings_page() {
    $state = nsr_live_state();

    nsr_live_styles();
    nsr_live_notice($state);
    ?>
    <div class="wrap nsr-live-wrap">
        <h1>NSR Live Settings</h1>
        <div class="nsr-card">
            <form method="post" class="nsr-form-grid">
                <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); nsr_live_hidden_redirect(); ?>
                <input type="hidden" name="nsr_live_action" value="save_settings">

                <label>Show Title
                    <input type="text" name="show_title" value="<?php echo esc_attr($state['show_title']); ?>">
                </label>

                <label>Tagline
                    <input type="text" name="tagline" value="<?php echo esc_attr($state['tagline']); ?>">
                </label>

                <label>Follower Goal Current
                    <input type="number" name="follower_goal_current" value="<?php echo intval($state['follower_goal_current']); ?>">
                </label>

                <label>Follower Goal Target
                    <input type="number" name="follower_goal_target" value="<?php echo intval($state['follower_goal_target']); ?>">
                </label>

                <label>Next Auto Item #
                    <input type="number" name="next_item_no" value="<?php echo intval($state['next_item_no']); ?>">
                </label>

                <label>Barcode Lookup API Key (optional)
                    <input type="text" name="barcode_lookup_api_key" value="<?php echo esc_attr($state['barcode_lookup_api_key']); ?>" autocomplete="off">
                </label>

                <label>UPCDatabase Token (optional for future use)
                    <input type="text" name="upcdatabase_api_key" value="<?php echo esc_attr($state['upcdatabase_api_key']); ?>" autocomplete="off">
                </label>

                <div>
                    <button class="button button-primary">Save Settings</button>
                </div>
            </form>

            <div class="nsr-api-help">
                <strong>Scanner provider order</strong>
                <p class="nsr-small" style="margin:6px 0 0 0">If Barcode Lookup key is filled, the scanner tries that first. If it fails or is blank, it uses UPCitemdb fallback.</p>
            </div>

            <p>Shortcode: <code>[nsr_live_page]</code></p>
        </div>
    </div>
    <?php
}

function nsr_live_page_shortcode() {
    $state = nsr_live_state();
    nsr_live_maybe_expire_timer($state);

    $current = nsr_live_current_item($state);
    $recent  = nsr_live_recent($state);
    $upnext  = nsr_live_upnext($state, 3);
    $timer_left = (!empty($state['timer_end']) ? max(0, intval($state['timer_end']) - time()) : 0);

    ob_start();
    nsr_live_styles();
    $signature = md5(wp_json_encode(array(
        'is_live' => $state['is_live'],
        'timer_end' => $state['timer_end'],
        'current_index' => $state['current_index'],
        'claims_total' => $state['claims_total'],
        'total_savings' => $state['total_savings'],
        'queue' => $state['queue'],
        'recent' => $state['recent'],
    )));
    ?>
    <div
        class="nsr-front-wrap"
        data-mode="<?php echo $state['is_live'] ? 'live' : 'showcase'; ?>"
        data-signature="<?php echo esc_attr($signature); ?>"
        data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-timer-end="<?php echo intval($state['timer_end']); ?>"
    >
        <div class="nsr-hero">
            <div class="nsr-video-box">
                <div class="nsr-video-placeholder"><?php echo $state['is_live'] ? 'LIVE VIDEO AREA' : 'AUTO SHOWCASE MODE'; ?></div>
            </div>

            <div class="nsr-product-card">
                <div class="nsr-show-title"><?php echo esc_html($state['show_title']); ?></div>
                <div class="nsr-tagline"><?php echo esc_html($state['tagline']); ?></div>

                <?php if ($current):
                    $available = nsr_live_stock_left($current);
                    $pct  = nsr_live_discount_pct($current['retail'], $current['live']);
                    $save = (float)$current['retail'] - (float)$current['live'];
                ?>
                    <div class="nsr-item-overlay">ITEM <?php echo esc_html($current['item_no']); ?></div>
                    <h2><?php echo esc_html($current['title']); ?></h2>
                    <div class="nsr-source-badge"><?php echo esc_html($current['source']); ?></div>

                    <div class="nsr-price-drop">
                        <span class="retail"><?php echo esc_html($current['source']); ?> Retail <?php echo esc_html(nsr_live_format_money($current['retail'])); ?></span>
                        <span class="arrow">↓</span>
                        <span class="live">🔥 <?php echo esc_html(nsr_live_format_money($current['live'])); ?> 🔥</span>
                    </div>

                    <div class="nsr-save-line">SAVE <?php echo esc_html(nsr_live_format_money($save)); ?> • <?php echo esc_html($pct); ?>% OFF</div>
                    <div class="nsr-meter"><span style="width: <?php echo min(100, intval($pct)); ?>%"></span></div>
                    <div class="nsr-claim-hint">Type <?php echo esc_html($current['item_no']); ?> to claim</div>

                    <?php if ($timer_left > 0): ?>
                        <div class="nsr-timer" id="nsr-live-timer">⏱ <?php echo esc_html(gmdate('i:s', $timer_left)); ?></div>
                    <?php else: ?>
                        <div class="nsr-timer off" id="nsr-live-timer">Timer off until host starts it</div>
                    <?php endif; ?>

                    <div class="nsr-stock <?php echo $available <= 2 ? 'low' : ''; ?>">
                        <?php
                        if ($available <= 0) echo '🔥 SOLD OUT 🔥';
                        elseif ($available == 1) echo '⚠ LAST ITEM ⚠';
                        elseif ($available == 2) echo '⚠ ONLY 2 LEFT ⚠';
                        else echo $available . ' available';
                        ?>
                    </div>

                    <button class="nsr-claim-btn">CLAIM ITEM</button>
                    <div class="nsr-auto-reminder">Type the item number to claim • Example: <?php echo esc_html($current['item_no']); ?></div>
                <?php else: ?>
                    <p>No live item yet. Add queue items in NSR Live → Queue or Scanner.</p>
                <?php endif; ?>

                <div class="nsr-stats-grid">
                    <div class="nsr-stat"><strong>Top Savers</strong><span>Live board in next upgrade</span></div>
                    <div class="nsr-stat"><strong>Total Savings</strong><span><?php echo esc_html(nsr_live_format_money($state['total_savings'])); ?></span></div>
                    <div class="nsr-stat"><strong>Items Revealed</strong><span><?php echo intval($state['revealed_count']); ?> / <?php echo intval(count($state['queue'])); ?></span></div>
                    <div class="nsr-stat"><strong>Follower Goal</strong><span><?php echo intval($state['follower_goal_current']); ?> / <?php echo intval($state['follower_goal_target']); ?></span></div>
                </div>
            </div>
        </div>

        <div class="nsr-lists">
            <div class="nsr-card-list">
                <h3>Recent Deals</h3>
                <?php if (!$recent): ?><div class="nsr-list-row">No recent deals yet.</div><?php endif; ?>
                <?php foreach ($recent as $r): ?>
                    <div class="nsr-list-row"><?php echo esc_html($r['item_no'] . ' ' . $r['title'] . ' – ' . $r['status']); ?></div>
                <?php endforeach; ?>
            </div>

            <div class="nsr-card-list">
                <h3>Up Next</h3>
                <?php if (!$upnext): ?><div class="nsr-list-row">No upcoming items yet.</div><?php endif; ?>
                <?php foreach ($upnext as $u): ?>
                    <div class="nsr-list-row"><?php echo esc_html($u['item_no'] . ' ' . $u['title']); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('nsr_live_page', 'nsr_live_page_shortcode');

