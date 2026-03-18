<?php
if (!defined('ABSPATH')) exit;

function nsr_base_suggested_price($retail_price) {
    $retail_price = floatval($retail_price);
    if ($retail_price <= 0) return 5.00;
    if ($retail_price < 10) return 3.00;
    if ($retail_price < 20) return 5.00;
    if ($retail_price < 35) return 10.00;
    if ($retail_price < 50) return 15.00;
    if ($retail_price < 75) return 25.00;
    if ($retail_price < 100) return 35.00;
    return round($retail_price * 0.35, 2);
}

function nsr_calculate_smart_price($retail_price, $pallet_cost, $pallet_revenue) {
    $retail_price = floatval($retail_price);
    $pallet_cost = floatval($pallet_cost);
    $pallet_revenue = floatval($pallet_revenue);

    $base_price = nsr_base_suggested_price($retail_price);
    $break_even_remaining = max(0, $pallet_cost - $pallet_revenue);

    if ($break_even_remaining > ($pallet_cost * 0.50)) {
        $suggested = $base_price * 1.15;
    } elseif ($break_even_remaining > 0) {
        $suggested = $base_price * 1.05;
    } else {
        $suggested = $base_price * 0.95;
    }

    if ($retail_price > 0) {
        $suggested = min($suggested, $retail_price);
    }

    $suggested = max($suggested, 3.00);

    return round($suggested, 2);
}

function nsr_calculate_hybrid_pricing_context($retail_price, $pallet_cost, $pallet_revenue) {
    $retail_price = floatval($retail_price);
    $pallet_cost = floatval($pallet_cost);
    $pallet_revenue = floatval($pallet_revenue);

    $base = nsr_base_suggested_price($retail_price);
    $hybrid = nsr_calculate_smart_price($retail_price, $pallet_cost, $pallet_revenue);

    $remaining_now = max(0, $pallet_cost - $pallet_revenue);
    $remaining_after_sale = max(0, $pallet_cost - ($pallet_revenue + $hybrid));

    $profit_if_sold_now = round($hybrid, 2);

    $estimated_item_cost = 0;
    if ($retail_price > 0) {
        $estimated_item_cost = round($retail_price * 0.20, 2);
    }
    $margin_percent = $hybrid > 0
        ? round((($hybrid - $estimated_item_cost) / $hybrid) * 100)
        : 0;

    $status = 'green';
    $status_label = 'Profitable';
    if ($pallet_cost > 0 && $remaining_now > ($pallet_cost * 0.50)) {
        $status = 'red';
        $status_label = 'Building Margin';
    } elseif ($remaining_now > 0) {
        $status = 'yellow';
        $status_label = 'Near Break-even';
    }

    $max_profit_price = $retail_price > 0
        ? min($retail_price, round(max($base, $hybrid) * 1.10, 2))
        : $hybrid;

    return array(
        'base_price' => round($base, 2),
        'hybrid_price' => round($hybrid, 2),
        'remaining_now' => round($remaining_now, 2),
        'remaining_after_sale' => round($remaining_after_sale, 2),
        'profit_if_sold_now' => round($profit_if_sold_now, 2),
        'margin_percent' => intval($margin_percent),
        'status' => $status,
        'status_label' => $status_label,
        'max_profit_price' => round($max_profit_price, 2),
    );
}
