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
  name varchar(100) NOT NULL,
  phone varchar(20),
  email varchar(50),
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
function table_sales_reserve($order) {
  global $wpdb;
  $table_tables = $wpdb->prefix . "table_sales_tables";
  $table_res = $wpdb->prefix . "table_sales_res";
  $table_item = $wpdb->prefix . "table_sales_item";
  $tables = $wpdb->get_results("SELECT * FROM  $table_tables ORDER BY number");
  print json_encode($order);
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

// FIXME: Take this out when error gone.
add_action('activated_plugin','save_error');
function save_error(){
    update_option('plugin_error',  ob_get_contents());
}

add_shortcode('table-sales', 'table_sales');
function table_sales() {
  $ret = '';

  // FIXME: Take this out when error gone.
  $ret .= get_option('plugin_error');

  $ret .= '<style type="text/css"> #table-1 { fill:red; }</style>';
  $layout = plugins_url('/img/tables.svg', __FILE__);
  /* $layout = plugins_url('/img/embed.svg', __FILE__); */
  /* $ret .= '<script type="text/javascript">jQuery(function() { jQuery("#table-1").click(function() { alert("Clicked it!"); })});</script>'; */
  /* $ret .= '<script type="text/javascript">function tableclick(evt) { alert(\"hi\"); }</script>'; */
  /* $ret .= "<a href=\"$layout\"><img src=\"$layout\" width=\"500px\" /></a>"; */
  /* $ret .= file_get_contents($layout); */
  $ret .= "<img src=\"$layout\" width=\"700px\" />";
  // TODO: re-enable paypal.
  /* <form name="table-sales-cart" id="table-sales-cart" action="https://www.paypal.com/cgi-bin/webscr" method="post" onsubmit="return table_sales_checkout()" target="paypal"> */

  $ret .= '
<div id="table-sales">
<form name="table-sales-cart" id="table-sales-cart" action="#" method="post" onsubmit="return table_sales_checkout()" target="paypal">
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
  $ret .= '<td><label id="table-sales-name-label" for="table-sales-name">Name: </label><input type="text" length="100" id="table-sales-name" /></td>';
  $ret .= '<td><label id="table-sales-email-label"for="table-sales-email">Email: </label><input type="text" length="100" id="table-sales-email" /></td>';
  $ret .= '<td><label id="table-sales-phone-label"for="table-sales-phone">Phone: </label><input type="text" length="100" id="table-sales-phone" /></td>';
  $ret .= '</tr>';
  $ret .= '<tr><td colspan="3" style="text-align:right"><input type="submit" value="Checkout via PayJunction"></td></tr>';
  $ret .= '</table></form></div>';
  return $ret;
}
