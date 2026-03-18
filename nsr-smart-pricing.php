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

    // Hybrid model:
    // Far from break-even => lean higher on margin
    // Near break-even => balanced
    // After break-even => loosen a little for sell-through
    if ($break_even_remaining > ($pallet_cost * 0.50)) {
        $suggested = $base_price * 1.15;
    } elseif ($break_even_remaining > 0) {
        $suggested = $base_price * 1.05;
    } else {
        $suggested = $base_price * 0.95;
    }

    // Never exceed retail
    if ($retail_price > 0) {
        $suggested = min($suggested, $retail_price);
    }

    // Keep a small floor
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

    return array(
        'base_price' => round($base, 2),
        'hybrid_price' => round($hybrid, 2),
        'remaining_now' => round($remaining_now, 2),
        'remaining_after_sale' => round($remaining_after_sale, 2),
    );
}
