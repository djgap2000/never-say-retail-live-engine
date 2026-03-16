<?php

if (!defined('ABSPATH')) exit;

define('NSR_PALLETS_OPT','nsr_pallets_data');

function nsr_get_pallets(){
    $data = get_option(NSR_PALLETS_OPT,[]);
    if(!is_array($data)) $data=[];
    return $data;
}

function nsr_save_pallets($data){
    update_option(NSR_PALLETS_OPT,$data,false);
}

add_action('admin_menu',function(){

add_submenu_page(
'nsr_live',
'Pallets',
'Pallets',
'manage_options',
'nsr-live-pallets',
'nsr_pallets_page'
);

});

function nsr_pallets_page(){

$pallets = nsr_get_pallets();

?>

<div class="wrap">

<h1>Pallet Profit Dashboard</h1>

<h2>Create Pallet</h2>

<form method="post">

<input type="hidden" name="nsr_action" value="create_pallet">

<input type="text" name="name" placeholder="Pallet name" required>

<input type="number" step="0.01" name="cost" placeholder="Cost" required>

<input type="text" name="supplier" placeholder="Supplier">

<button class="button button-primary">Create Pallet</button>

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
</tr>

<?php

foreach($pallets as $p){

$retail=0;
$sales=0;

if(!empty($p['items'])){

foreach($p['items'] as $item){

$retail += $item['retail'] * $item['qty'];
$sales += $item['live'] * $item['qty'];

}

}

$profit = $sales - $p['cost'];

echo "<tr>

<td>{$p['name']}</td>
<td>{$p['supplier']}</td>
<td>\${$p['cost']}</td>
<td>".count($p['items'])."</td>
<td>\$".number_format($retail,2)."</td>
<td>\$".number_format($sales,2)."</td>
<td>\$".number_format($profit,2)."</td>

</tr>";

}

?>

</table>

</div>

<?php

}

add_action('admin_init',function(){

if(empty($_POST['nsr_action'])) return;

$pallets = nsr_get_pallets();

if($_POST['nsr_action']=="create_pallet"){

$pallets[]=[
"name"=>sanitize_text_field($_POST['name']),
"supplier"=>sanitize_text_field($_POST['supplier']),
"cost"=>floatval($_POST['cost']),
"items"=>[]
];

}

nsr_save_pallets($pallets);

});
