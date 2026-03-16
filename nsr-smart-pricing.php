<?php
if (!defined('ABSPATH')) exit;

function nsr_calculate_smart_price($retail_price, $pallet_cost, $pallet_revenue){

    if(!$retail_price) return 0;

    $base_price = $retail_price * 0.35;

    $break_even_remaining = $pallet_cost - $pallet_revenue;

    if($break_even_remaining > 0){
        $suggested = $base_price * 1.15;
    }
    else{
        $suggested = $base_price * 0.95;
    }

    return round($suggested,2);
}
