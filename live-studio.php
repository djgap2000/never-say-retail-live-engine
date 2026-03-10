<?php

function nsr_live_admin_menu() {

add_menu_page(
'NSR Live Studio',
'NSR Live',
'manage_options',
'nsr-live',
'nsr_live_admin_page'
);

}

add_action('admin_menu','nsr_live_admin_menu');

function nsr_live_admin_page() {

?>

<div class="wrap">

<h1>Never Say Retail Live Studio</h1>

<button>Reveal Item</button>
<button>Start Timer</button>
<button>Next Item</button>

<hr>

<button>Flash Deal</button>
<button>Spin Wheel</button>
<button>Rapid Fire</button>
<button>Golden Ticket</button>
<button>Mystery Item</button>

<hr>

<button style="background:red;color:white;">End Sale</button>

</div>

<?php

}