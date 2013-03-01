<?php
/*
  Plugin Name: Tables Sales
  Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
  Description: Sell reserved seats at tables.
  Version: 0.1
  Author: New Logic Software, LLC
  Author URI: http://www.newlogicsoftware.com/
  License: GPL2
*/

/*  Copyright 2012 New Logic Software, LLC

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action("wp_enqueue_scripts", "table_sales_enqueue_scripts");
function table_sales_enqueue_scripts() {
  wp_enqueue_script("table_sales", plugins_url('table-sales.js', __FILE__), array('jquery'));
  // For AJAX:
  wp_localize_script("table_sales", 'table_sales_script', array('ajaxurl' => admin_url('admin-ajax.php')));
}

/** Setup **/

register_activation_hook(__FILE__, "table_sales_install");
function table_sales_install () {
  global $wpdb;

  $res_table = $wpdb->prefix . "table_sales_res";
  $tables_table = $wpdb->prefix . "table_sales_tables";
  $item_table = $wpdb->prefix . "table_sales_item";
  
  $sql = "CREATE TABLE $res_table (
  id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
  time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  name varchar(200) NOT NULL,
  phone varchar(100),
  email varchar(100),
  paid BOOLEAN DEFAULT FALSE,
  UNIQUE KEY (id)
    );
CREATE TABLE $tables_table (
  number TINYINT(4) NOT NULL,
  available TINYINT(4) NOT NULL,
  total TINYINT(4) NOT NULL,
  individual BOOLEAN DEFAULT 0,
  UNIQUE KEY (number)
    );
CREATE TABLE $item_table (
  id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
  res_id MEDIUMINT(9) NOT NULL,
  table_id MEDIUMINT(9) NOT NULL,
  quantity TINYINT(4) NOT NULL,
  UNIQUE KEY (id)
    );";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}

register_activation_hook(__FILE__, "table_sales_init_data");
function table_sales_init_data() {
  global $wpdb;
  $table_name = $wpdb->prefix . "table_sales_tables";
  $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name LIMIT 1");
  if ($count > 0) {
    return;
  }
  include_once dirname( __FILE__ ) . '/init-tables.php';
  $tables = init_tables();
  
  foreach ($tables as $number => $table) {
    $rows_affected = $wpdb->insert($table_name, array('number' => $number,
                                                      'available' => $table['available-seats'],
                                                      'total' => $table['total-seats'],
                                                      'individual' => $table['sold-individually']));
  }
}

/** AJAX **/

add_action('wp_ajax_table_sales_tables', 'table_sales_tables');
add_action('wp_ajax_nopriv_table_sales_tables', 'table_sales_tables');
function table_sales_tables() {
  global $wpdb;
  $table_name = $wpdb->prefix . "table_sales_tables";
  $tables = $wpdb->get_results("SELECT * FROM  $table_name ORDER BY number");
  print json_encode($tables);
  exit;
}

add_action('wp_ajax_table_sales_reserve', 'table_sales_reserve');
add_action('wp_ajax_nopriv_table_sales_reserve', 'table_sales_reserve');
function table_sales_reserve() {
  global $wpdb;
  if (!isset($_POST['buyer']) || !isset($_POST['order'])) {
    print json_encode(array("errormessage" => "Internal error. Please try again."));
    exit;
  }
  $order = $_POST['order'];
  $buyer = $_POST['buyer'];

  $table_tables = $wpdb->prefix . "table_sales_tables";
  $table_res = $wpdb->prefix . "table_sales_res";
  $table_item = $wpdb->prefix . "table_sales_item";

  /* print json_encode(array("order" => print_r($order, true), "buyer" => print_r($buyer, true))); */
  /* exit; */

  // TODO: acquire lock.
  $tables = $wpdb->get_results("SELECT * FROM  $table_tables ORDER BY number");
  // Verify availability.
  foreach($order as $tablenum => $tableorder) {
    if ($tables[$tablenum-1]->available < $tableorder['quantity']) {
      print json_encode(array("errormessage" => "Sorry, Table $tablenum ran out of seats. Please try again."));
      exit;
    }
  }

  // Everything available. Start committing.
  // First, make a reservation entry.
  $success = $wpdb->insert($table_res,
                           array("name" => sprintf("%s %s", $buyer['firstname'], $buyer['lastname']),
                                 "phone" => $buyer['phone'],
                                 "email" => $buyer['email']));
  $resid = $wpdb->insert_id;
  if (!$success) {
    print json_encode(array("errormessage" => "There was an error recording your reservation. Please try again."));
    exit;
  }
  // Make a row for each line item, and update table availability.
  foreach($order as $tablenum => $tableorder) {
    // Line item insert.
    $success = $wpdb->insert($table_item,
                             array('res_id' => $resid,
                                   'table_id' => $tablenum,
                                   'quantity'=> $tableorder['quantity']));
    if (!$success) {
      print json_encode(array("errormessage" => "There was an error recording your reservation. Please try again."));
      exit;
    }
    // Table availability update.
    $seatsleft = $tables[$tablenum-1]->individual ?
      $tables[$tablenum-1]->available - $tableorder['quantity']
      : 0;
    $success = $wpdb->update($table_tables,
                             array('available' => $seatsleft),
                             array('number' => $tablenum),
                             "%d",
                             "%d");
    if ($success != 1) {
      print json_encode(array("errormessage" => "There was an error recording your reservation. Please try again."));
      exit;
    }
  }
  // TODO: release lock.
  print json_encode($_POST);
  exit;
}

add_action('wp_ajax_table_sales_mark_paid', 'table_sales_mark_paid');
add_action('wp_ajax_nopriv_table_sales_mark_paid', 'table_sales_mark_paid');
function table_sales_mark_paid() {
  if (!isset($_POST['res']) || !isset($_POST['val'])) {
    print json_encode(array("errormessage" => "Internal error. Please try again."));
    exit;
  }
  $res = $_POST['res'];
  $val = $_POST['val'];

  global $wpdb;
  $table_name = $wpdb->prefix . "table_sales_res";
  $success = $wpdb->update($table_name,
                           array('paid' => $val),
                           array('id' => $res),
                           "%d",
                           "%d");
  if ($success < 1) {
    print json_encode(array("errormessage" => "There was an error updating paid status. Please double-check it and try again if needed."));
    exit;
  } else if ($success > 1) {
    print json_encode(array("errormessage" => "Multiple reservations updated. Call Mikey!"));
    exit;
  }
  print json_encode(array("res" => $res, "val" => $val));
  exit;
}

add_action('wp_ajax_table_sales_cancel', 'table_sales_cancel');
add_action('wp_ajax_nopriv_table_sales_cancel', 'table_sales_cancel');
function table_sales_cancel() {
  if (!isset($_POST['res'])) {
    print json_encode(array("errormessage" => "Internal error. Please try again."));
    exit;
  }
  $res = $_POST['res'];

  global $wpdb;
  $table_res = $wpdb->prefix . "table_sales_res";
  $table_tables = $wpdb->prefix . "table_sales_tables";
  $table_item = $wpdb->prefix . "table_sales_item";

  // Delete the items and add back availability.
  $items = $wpdb->get_results("SELECT * FROM $table_item WHERE res_id = $res");
  
  foreach ($items as $item) {
    $table = $wpdb->get_row("SELECT * FROM $table_tables WHERE number = $item->table_id");
    $available = $item->quantity + $table->available;
    if (!$table->individual) {
      $available = $table->total;
    }
    // Add back availability.
    $wpdb->update($table_tables,
                  array('available' => $available),
                  array('number' => $table->number),
                  "%d",
                  "%d");
  }
  // Delete the items.
  $wpdb->query($wpdb->prepare("DELETE FROM $table_item WHERE res_id = %d",
                              $res));
  // Delete the res.
  $wpdb->query($wpdb->prepare("DELETE FROM $table_res WHERE id = %d",
                              $res));
  print json_encode(array("res" => $res));
  exit;
}


/** Front-end **/

function table_sales_dropdown($n) {
  $ret = '<select class="table-sales-table-select"><option value="0">-- Select Table -- </option>';
  foreach (range(1, 42) as $number) {
    $ret .= sprintf('<option value="%d">Table %d</option>', $number, $number);
  }
  $ret .= "</select>";
  return $ret;
}

// Useful for debugging plugin install errors.
/* add_action('activated_plugin','save_error'); */
/* function save_error(){ */
/*     update_option('plugin_error',  ob_get_contents()); */
/* } */

add_shortcode('table-sales', 'table_sales');
function table_sales() {
  $ret = '';

  // For debugging. See function save_error() above.
  /* $ret .= get_option('plugin_error'); */

  $ret .= '<style type="text/css"> #table-1 { fill:red; }</style>';
  $layout = plugins_url('/img/tables.svg', __FILE__);
  /* $layout = plugins_url('/img/embed.svg', __FILE__); */
  /* $ret .= '<script type="text/javascript">jQuery(function() { jQuery("#table-1").click(function() { alert("Clicked it!"); })});</script>'; */
  /* $ret .= '<script type="text/javascript">function tableclick(evt) { alert(\"hi\"); }</script>'; */
  /* $ret .= "<a href=\"$layout\"><img src=\"$layout\" width=\"500px\" /></a>"; */
  /* $ret .= file_get_contents($layout); */

  $ret .= "<img src=\"$layout\" width=\"700px\" />";
  $ret .= '
<div id="table-sales">
<form name="table-sales-cart" id="table-sales-cart" action="https://www.payjunctionlabs.com/trinity/quickshop/add_to_cart.action" method="post">
  <input type="hidden" name="store" value="pj-qs-01" />
  <input type="hidden" name="need_to_ship" value="No" />
  <input type="hidden" name="need_to_tax" value="No" />
  <table id="table-sales-table">
  <thead>
  <tr><th>Table #</th>
      <th>Total<br />Seats</th>
      <th>Available<br />Seats</th>
      <th>Seats Sold<br />Individually</th>
      <th>Cost</th>
      <th>Quantity</th></tr>
  </thead>';
  
  $ret .= sprintf('
  <tbody>
  <tr><td>%s</td>
      <td>-</td>
      <td>-</td>
      <td>-</td>
      <td>-</td>
      <td>-</td></tr>
  </tbody>',
                  table_sales_dropdown(42)
                  );

  $ret .= '<tr><td><a href="javascript:table_sales_add_row();">Add anoter table</a></td></tr>';
  $ret .= '</table>';
  $ret .= '<table>';
  $ret .= '<tr>';
  $ret .= '<td><label id="table-sales-firstname-label" for="table-sales-firstname">First name: </label><input type="text" length="100" id="table-sales-firstname" /></td>';
  $ret .= '<td><label id="table-sales-lastname-label" for="table-sales-lastname">Last name: </label><input type="text" length="100" id="table-sales-lastname" /></td>';
  $ret .= '<td><label id="table-sales-email-label"for="table-sales-email">Email: </label><input type="text" length="100" id="table-sales-email" /></td>';
  $ret .= '<td><label id="table-sales-phone-label"for="table-sales-phone">Phone: </label><input type="text" length="100" id="table-sales-phone" /></td>';
  $ret .= '</tr>';
  $ret .= '<tr><td colspan="4" style="text-align:right"><input type="button" onclick="table_sales_precheckout()" value="Checkout via PayJunction"></td></tr>';
  $ret .= '</table></form></div>';
  return $ret;
}

add_shortcode('table-sales-manage', 'table_sales_manage');
function table_sales_manage() {
  $ret = '';
  global $wpdb;
  $table_res = $wpdb->prefix . "table_sales_res";
  $table_item = $wpdb->prefix . "table_sales_item";

  $reservations = $wpdb->get_results("SELECT * FROM  $table_res ORDER BY time");
  $ret .= "<table>";
  $ret .= "<tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Order</th><th>Paid?</th><th>Mark Paid</th><th>Cancel</th></tr>";
  foreach ($reservations as $res) {
    $items = $wpdb->get_results("SELECT * FROM $table_item WHERE res_id = $res->id");
    $order = '';
    foreach($items as $item) {
      $order .= sprintf('%d at Table %d<br />', $item->quantity, $item->table_id);
    }
    $paidattrs = sprintf('id="table-sales-%d-paid" style="color: %s"',
                         $res->id, $res->paid ? "green" : "red");
    $paid = $res->paid ? 'Paid' : 'Unpaid';
    $marktext = $res->paid ? "Mark Unpaid" : "Mark Paid";
    $mark = sprintf('<input id="table-sales-%d-paid-button" type="button" onclick="table_sales_mark_paid(%s, %d)" value="%s" />',
                    $res->id,
                    $res->id,
                    $res->paid ? 0 : 1,
                    $res->paid ? "Mark Unpaid" : "Mark Paid");
    $cancel = sprintf('<input type="button" onclick="table_sales_cancel(%d)" value="Cancel" />',
                      $res->id);
    $trid = sprintf('id="table-sales-res-%d"', $res->id);
    $ret .= "<tr $trid><td>$res->id</td><td>$res->name</td><td>$res->email</td><td>$res->phone</td><td>$order</td><td $paidattrs>$paid</td><td>$mark</td><td>$cancel</td></tr>";
  }
  $ret .= "</table>";
  return $ret;
}
