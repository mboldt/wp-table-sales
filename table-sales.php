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

/* include_once dirname( __FILE__ ) . '/tables.php'; */

add_action("wp_enqueue_scripts", "table_sales_enqueue_scripts");
function table_sales_enqueue_scripts() {
  wp_enqueue_script("table_sales", plugins_url('table-sales.js', __FILE__), array('jquery'));
}

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
  PRIMARY KEY  id (id)
    );
CREATE TABLE $tables_table (
  number TINYINT(4) NOT NULL,
  available TINYINT(4) NOT NULL,
  individual BOOLEAN DEFAULT 0,
  UNIQUE KEY number (number)
    );
CREATE TABLE $item_table (
  id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
  res_id MEDIUMINT(9) NOT NULL,
  table_id MEDIUMINT(9) NOT NULL,
  quantity TINYINT(4) NOT NULL,
  PRIMARY KEY  id (id)
    );";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}

register_activation_hook(__FILE__, "table_sales_init_data");
function table_sales_init_data() {
}

function table_sales_dropdown($n) {
  $ret = '<select class="table-sales-table-select"><option value="0">-- Select Table -- </option>';
  foreach (range(1, 42) as $number) {
    $ret .= sprintf('<option value="%d">Table %d</option>', $number, $number);
  }
  $ret .= "</select>";
  return $ret;
}

function table_sales() {
  /* $tables = get_tables(); */
  $ret = '';
  /* $layout = plugins_url('/img/tables.svg', __FILE__); */
  $ret .= '<style type="text/css"> #table-1 { fill:red; }</style>';
  $layout = plugins_url('/img/byhand.svg', __FILE__);
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

  /* Code to display all tables */
  /* foreach ($tables as $number => $table) { */
  /*   $ret .= sprintf(' */
  /* <tr><td>%d</td> */
  /*     <td>%d</td> */
  /*     <td>%d</td> */
  /*     <td>%s</td> */
  /*     <td>$%d per %s</td> */
  /*     <td><input type="number" name="popular-quantity" id="table-1-quantity" min="0" max="%d" value="0"></td></tr>', */
  /*                   $number, */
  /*                   $table['total-seats'], */
  /*                   $table['available-seats'], */
  /*                   $table['sold-individually'] ? 'Yes' : 'No', */
  /*                   $table['sold-individually'] ? 100 : 1000, */
  /*                   $table['sold-individually'] ? 'Seat' : 'Table', */
  /*                   $table['sold-individually'] ? $table['available-seats'] : 1 */
  /*                   ); */
  /* } */

  $ret .= '<tr><td><a href="javascript:table_sales_add_row();">Add anoter table</a></td><td colspan="5" style="text-align:right"><input type="submit" value="Reserve now"></td></tr>';
  $ret .= '</table></form></div>';
  return $ret;
}
add_shortcode('table-sales', 'table_sales');
