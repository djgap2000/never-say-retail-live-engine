<?php
/*
Plugin Name: Never Say Retail Live Engine
Description: Live sale system for Never Say Retail.
Version: 4.2
Update URI: https://github.com/djgap2000/never-say-retail-live-engine
*/

if (!defined('ABSPATH')) exit;

define('NSR_LIVE_OPT', 'nsr_live_state_v42');

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
        'recent' => array(),
        'queue' => array(),
        'last_action' => '',
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

function nsr_live_recent($state = null, $count = 4) {
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
        .nsr-live-wrap{max-width:1200px}
        .nsr-admin-grid{display:grid;grid-template-columns:1fr 1.2fr .9fr;gap:18px;margin-top:16px}
        .nsr-admin-grid.second{grid-template-columns:1fr 1fr}
        .nsr-card,.nsr-card-list{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .nsr-row{display:flex;gap:10px;align-items:center}
        .wrap-buttons{flex-wrap:wrap;margin-top:12px}
        .nsr-current-no{font-weight:800;font-size:28px;letter-spacing:.04em}
        .nsr-form-grid{display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:14px}
        .nsr-form-grid label{display:flex;flex-direction:column;gap:6px;font-weight:600}
        .nsr-form-grid input,.nsr-form-grid select{padding:8px}
        .nsr-list-row{padding:10px 0;border-top:1px solid #eee}
        .nsr-list-row:first-child{border-top:none}

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
        @keyframes nsrPulse{0%{transform:scale(1)}50%{transform:scale(1.02)}100%{transform:scale(1)}}
        @media (max-width: 900px){
            .nsr-admin-grid,.nsr-admin-grid.second,.nsr-hero,.nsr-lists,.nsr-stats-grid,.nsr-form-grid{grid-template-columns:1fr}
            .nsr-video-box{min-height:220px}
            .nsr-price-drop .live{font-size:28px}
        }
    </style>
    <?php
}

add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script('nsr-live-js', plugins_url('nsr-scripts.js', __FILE__), array(), '4.2', true);
});

add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('nsr-live-js', plugins_url('nsr-scripts.js', __FILE__), array(), '4.2', true);
});

add_action('admin_menu', function () {
    add_menu_page('NSR Live', 'NSR Live', 'manage_options', 'nsr-live', 'nsr_live_studio_page', 'dashicons-video-alt3', 56);
    add_submenu_page('nsr-live', 'Live Studio', 'Live Studio', 'manage_options', 'nsr-live', 'nsr_live_studio_page');
    add_submenu_page('nsr-live', 'Queue', 'Queue', 'manage_options', 'nsr-live-queue', 'nsr_live_queue_page');
    add_submenu_page('nsr-live', 'Settings', 'Settings', 'manage_options', 'nsr-live-settings', 'nsr_live_settings_page');
});

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (empty($_POST['nsr_live_action'])) return;

    check_admin_referer('nsr_live_action', 'nsr_live_nonce');

    $state = nsr_live_state();
    nsr_live_maybe_expire_timer($state);
    $action = sanitize_text_field($_POST['nsr_live_action']);

    if ($action === 'save_settings') {
        $state['show_title'] = sanitize_text_field($_POST['show_title'] ?? $state['show_title']);
        $state['tagline'] = sanitize_text_field($_POST['tagline'] ?? $state['tagline']);
        $state['follower_goal_current'] = max(0, intval($_POST['follower_goal_current'] ?? 0));
        $state['follower_goal_target'] = max(1, intval($_POST['follower_goal_target'] ?? 1000));
        $state['next_item_no'] = max(1, intval($_POST['next_item_no'] ?? $state['next_item_no']));
        $state['last_action'] = 'Settings saved.';
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
                    }
                    $state['timer_end'] = 0;
                } else {
                    $state['last_action'] = 'Claimed ' . $take . ' from item ' . $item['item_no'] . '. ' . $left . ' left.';
                }
            }
        }
    }

    nsr_live_save($state);
    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=nsr-live'));
    exit;
});

function nsr_live_studio_page() {
    $state = nsr_live_state();
    nsr_live_maybe_expire_timer($state);
    $current = nsr_live_current_item($state);
    $upnext = nsr_live_upnext($state);
    $timer_left = (!empty($state['timer_end']) ? max(0, intval($state['timer_end']) - time()) : 0);

    nsr_live_styles();
    nsr_live_notice($state);
    ?>
    <div class="wrap nsr-live-wrap">
        <h1>Never Say Retail Live Studio</h1>

        <div class="nsr-admin-grid">
            <div class="nsr-card">
                <h2>Show Controls</h2>
                <div class="nsr-row">
                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
                        <input type="hidden" name="nsr_live_action" value="start_show">
                        <button class="button button-primary">Start Show</button>
                    </form>
                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
                        <input type="hidden" name="nsr_live_action" value="end_show">
                        <button class="button">End Show</button>
                    </form>
                </div>
                <p><strong>Status:</strong> <?php echo $state['is_live'] ? 'LIVE' : 'OFFLINE'; ?></p>
                <p><strong>Follower Goal:</strong> <?php echo intval($state['follower_goal_current']); ?> / <?php echo intval($state['follower_goal_target']); ?></p>
                <p><strong>Next Auto Item #:</strong> <?php echo intval($state['next_item_no']); ?></p>
                <p><strong>Current Timer:</strong> <?php echo $timer_left > 0 ? gmdate('i:s', $timer_left) : 'OFF'; ?></p>
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
                    <p>No queue items yet. Add them under Queue.</p>
                <?php endif; ?>

                <div class="nsr-row wrap-buttons">
                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
                        <input type="hidden" name="nsr_live_action" value="reveal_item">
                        <input type="hidden" name="idx" value="<?php echo intval($state['current_index']); ?>">
                        <button class="button button-primary">Reveal Item</button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
                        <input type="hidden" name="nsr_live_action" value="start_timer">
                        <input type="hidden" name="seconds" value="45">
                        <button class="button">Start Timer</button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
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
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
                        <input type="hidden" name="nsr_live_action" value="simulate_claim">
                        <input type="hidden" name="idx" value="<?php echo intval($state['current_index']); ?>">
                        <input type="hidden" name="claim_qty" value="1">
                        <button class="button">Sim Claim +1</button>
                    </form>

                    <form method="post">
                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
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
                <?php $recent = nsr_live_recent($state); ?>
                <?php if (!$recent): ?><p>No recent deals yet.</p><?php endif; ?>
                <?php foreach ($recent as $r): ?>
                    <div class="nsr-list-row"><?php echo esc_html($r['item_no'] . ' – ' . $r['title'] . ' – ' . $r['status']); ?></div>
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
                    <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
                    <input type="hidden" name="nsr_live_action" value="add_queue_item">

                    <label>Item # (optional)
                        <input type="text" name="item_no" placeholder="<?php echo esc_attr($state['next_item_no']); ?>">
                    </label>

                    <label>Title
                        <input type="text" name="title" required>
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
                <p>Scan into the barcode field, fill title + prices, then add to queue.</p>
                <p>This build stores the barcode. Automatic UPC lookup and stock photos come in the next upgrade.</p>
                <p><strong>Live recommendation:</strong> use short item numbers like 101, 102, 103 for customers. Keep the barcode in the background.</p>
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
                                        <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
                                        <input type="hidden" name="nsr_live_action" value="delete_queue_item">
                                        <input type="hidden" name="idx" value="<?php echo intval($idx); ?>">
                                        <button class="button button-small">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
                <?php wp_nonce_field('nsr_live_action', 'nsr_live_nonce'); ?>
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

                <div>
                    <button class="button button-primary">Save Settings</button>
                </div>
            </form>

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
    ?>
    <div class="nsr-front-wrap" data-mode="<?php echo $state['is_live'] ? 'live' : 'showcase'; ?>">
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
                        <div class="nsr-timer">⏱ <?php echo esc_html(gmdate('i:s', $timer_left)); ?></div>
                    <?php else: ?>
                        <div class="nsr-timer off">Timer off until host starts it</div>
                    <?php endif; ?>

                    <div class="nsr-stock <?php echo $available <= 2 ? 'low' : ''; ?>">
                        <?php
                        if ($available <= 0) echo '🔥 SOLD OUT 🔥';
                        elseif ($available == 1) echo '⚠ LAST ITEM ⚠';
                        elseif ($available == 2) echo '⚠ ONLY 2 LEFT ⚠';
                        else echo $available . ' left';
                        ?>
                    </div>

                    <button class="nsr-claim-btn">CLAIM ITEM</button>
                    <div class="nsr-auto-reminder">Type the item number to claim • Example: <?php echo esc_html($current['item_no']); ?></div>
                <?php else: ?>
                    <p>No live item yet. Add queue items in NSR Live → Queue.</p>
                <?php endif; ?>

                <div class="nsr-stats-grid">
                    <div class="nsr-stat"><strong>Top Savers</strong><span>Live board in next upgrade</span></div>
                    <div class="nsr-stat"><strong>Total Savings</strong><span><?php echo esc_html(nsr_live_format_money($state['total_savings'])); ?></span></div>
                    <div class="nsr-stat"><strong>Deal of Night</strong><span>Enabled next upgrade</span></div>
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
