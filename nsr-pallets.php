<?php

if (!defined('ABSPATH')) exit;

define('NSR_PALLETS_OPT', 'nsr_pallets_data');
define('NSR_ACTIVE_PALLET_OPT', 'nsr_active_pallet_id');

function nsr_get_pallets() {
    $data = get_option(NSR_PALLETS_OPT, []);
    if (!is_array($data)) $data = [];
    return $data;
}

function nsr_save_pallets($data) {
    update_option(NSR_PALLETS_OPT, $data, false);
}

function nsr_get_active_pallet_id() {
    return intval(get_option(NSR_ACTIVE_PALLET_OPT, 0));
}

function nsr_set_active_pallet_id($id) {
    update_option(NSR_ACTIVE_PALLET_OPT, intval($id), false);
}

function nsr_get_active_pallet() {
    $pallets = nsr_get_pallets();
    $active_id = nsr_get_active_pallet_id();
    return isset($pallets[$active_id]) ? $pallets[$active_id] : null;
}

function nsr_calculate_pallet_totals($pallet) {
    $retail = 0;
    $sales = 0;
    $count = 0;

    if (!empty($pallet['items']) && is_array($pallet['items'])) {
        foreach ($pallet['items'] as $item) {
            $qty = max(1, intval($item['qty'] ?? 1));
            $retail += floatval($item['retail'] ?? 0) * $qty;
            $sales += floatval($item['live'] ?? 0) * $qty;
            $count += $qty;
        }
    }

    $cost = floatval($pallet['cost'] ?? 0);
    $profit = $sales - $cost;
    $break_even_remaining = max(0, $cost - $sales);

    return [
        'cost' => $cost,
        'retail' => $retail,
        'sales' => $sales,
        'profit' => $profit,
        'break_even_remaining' => $break_even_remaining,
        'items' => $count,
    ];
}

function nsr_attach_item_to_active_pallet($item) {
    $pallets = nsr_get_pallets();
    $active_id = nsr_get_active_pallet_id();

    if (!isset($pallets[$active_id])) return false;
    if (!isset($pallets[$active_id]['items']) || !is_array($pallets[$active_id]['items'])) {
        $pallets[$active_id]['items'] = [];
    }

    $pallets[$active_id]['items'][] = $item;
    nsr_save_pallets($pallets);
    return true;
}

add_action('admin_menu', function () {
    add_submenu_page(
        'nsr-live',
        'Pallets',
        'Pallets',
        'manage_options',
        'nsr-live-pallets',
        'nsr_pallets_page'
    );
}, 20);

function nsr_pallets_page() {
    $pallets = nsr_get_pallets();
    $active_id = nsr_get_active_pallet_id();
    ?>
    <div class="wrap">
        <h1>Pallet Profit Dashboard</h1>

        <h2>Create Pallet</h2>
        <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field('nsr_pallet_action', 'nsr_pallet_nonce'); ?>
            <input type="hidden" name="nsr_action" value="create_pallet">
            <input type="text" name="name" placeholder="Pallet name" required>
            <input type="number" step="0.01" name="cost" placeholder="Cost" required>
            <input type="text" name="supplier" placeholder="Supplier">
            <button class="button button-primary">Create Pallet</button>
        </form>

        <hr>

        <h2>Set Active Pallet</h2>
        <form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <?php wp_nonce_field('nsr_pallet_action', 'nsr_pallet_nonce'); ?>
            <input type="hidden" name="nsr_action" value="set_active_pallet">
            <select name="active_pallet_id">
                <option value="0">— No Active Pallet —</option>
                <?php foreach ($pallets as $idx => $pallet): ?>
                    <option value="<?php echo intval($idx); ?>" <?php selected($active_id, $idx); ?>>
                        <?php echo esc_html($pallet['name'] . ' ($' . number_format(floatval($pallet['cost']), 2) . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button">Set Active Pallet</button>
        </form>

        <hr>

        <h2>Existing Pallets</h2>
        <table class="widefat striped">
            <tr>
                <th>Name</th>
                <th>Supplier</th>
                <th>Cost</th>
                <th>Items</th>
                <th>Retail Value</th>
                <th>Est Sale Value</th>
                <th>Profit</th>
                <th>Status</th>
            </tr>

            <?php foreach ($pallets as $idx => $p): ?>
                <?php $totals = nsr_calculate_pallet_totals($p); ?>
                <tr>
                    <td><?php echo esc_html($p['name']); ?></td>
                    <td><?php echo esc_html($p['supplier']); ?></td>
                    <td><?php echo '$' . number_format($totals['cost'], 2); ?></td>
                    <td><?php echo intval($totals['items']); ?></td>
                    <td><?php echo '$' . number_format($totals['retail'], 2); ?></td>
                    <td><?php echo '$' . number_format($totals['sales'], 2); ?></td>
                    <td><?php echo '$' . number_format($totals['profit'], 2); ?></td>
                    <td><?php echo $idx === $active_id ? '<strong>ACTIVE</strong>' : ''; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
}

add_action('admin_init', function () {
    if (empty($_POST['nsr_action'])) return;
    if (!current_user_can('manage_options')) return;
    if (empty($_POST['nsr_pallet_nonce']) || !wp_verify_nonce($_POST['nsr_pallet_nonce'], 'nsr_pallet_action')) return;

    $pallets = nsr_get_pallets();
    $action = sanitize_text_field($_POST['nsr_action']);

    if ($action === 'create_pallet') {
        $pallets[] = [
            'name' => sanitize_text_field($_POST['name']),
            'supplier' => sanitize_text_field($_POST['supplier']),
            'cost' => floatval($_POST['cost']),
            'items' => [],
        ];
        nsr_save_pallets($pallets);
    }

    if ($action === 'set_active_pallet') {
        nsr_set_active_pallet_id(intval($_POST['active_pallet_id'] ?? 0));
    }
});
