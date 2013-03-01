var TABLE_SALES_NUM_TABLES = 42;
var TABLE_SALES_PER_SEAT = 100;
var TABLE_SALES_PER_TABLE = 1000;
var tables = null;

jQuery(document).ready(
    function() {
        jQuery(".table-sales-table-select").change(
            function() { return table_sales_update_row(this); });
        });

function table_sales_get_table(number) {
    jQuery.getJSON(table_sales_script.ajaxurl, function(data) {
        return data[0];
    });
}

function table_sales_add_row() {
    jQuery("#table-sales-table tr:last").before(table_sales_new_row());
}

function table_sales_new_row() {
    return jQuery("<tr>").append(
        jQuery("<td>").append(table_sales_dropdown()),
        jQuery("<td>").text("-"),
        jQuery("<td>").text("-"),
        jQuery("<td>").text("-"),
        jQuery("<td>").text("-"),
        jQuery("<td>").text("-")
    );
}

function table_sales_dropdown() {
    var ret = jQuery("<select>").addClass("table-sales-table-select").append(jQuery("<option>").attr("value", "0").text("-- Select Table --"));
    ret.change(function() { return table_sales_update_row(this); });
    for (var i=1; i<=42; i++) {
        ret.append(jQuery("<option>").attr("value", i).text("Table " + i));
    }
    return ret;
}

function table_sales_update_row(select) {
    var tablenum = select.value;
    if (tablenum < 0 || tablenum > TABLE_SALES_NUM_TABLES) {
        return;
    }
    if (tablenum == 0) {
        var col = jQuery(select).closest("td");
        // Total seats
        col = col.next();
        col.text("-");
        // Available seats
        col = col.next();
        col.text("-");
        // Seats sold individually
        col = col.next();
        col.text("-");
        // Cost
        col = col.next();
        col.text("-");
        // Quantity
        col = col.next();
        col.text("-");
        return;
    }
    jQuery.getJSON(table_sales_script.ajaxurl, {action: 'table_sales_tables'}, function(data, st) {
        // var table = table_sales_get_table(tablenum);
        var table = data[tablenum - 1]; // Hackish...should reallly search for table['number'] == tablenum.
        var available = parseInt(table['available']);
        var total = parseInt(table['total']);
        var individual = parseInt(table['individual']);
        var col = jQuery(select).closest("td");
        // Total seats
        col = col.next();
        col.text(total);
        // Available seats
        col = col.next();
        col.text(available);
        // Seats sold individually
        col = col.next();
        col.text(individual ? "Yes" : "No");
        // Cost
        col = col.next();
        col.text(individual ? "$100 per Seat" : "$1000 per Table");
        // Quantity
        col = col.next();
        var max = individual ? available : 1;
        col.html(jQuery("<input>").attr("type", "number").attr("min", "0").attr("max", max).attr("value", "0"));
    });
}

function table_sales_checkout() {
    var order = {};
    var orderingsomething = false;
    var name = jQuery('#table-sales-name').val();
    var email = jQuery('#table-sales-email').val();
    var phone = jQuery('#table-sales-phone').val();
    if (!name) {
        jQuery("#table-sales-name-label").css("color", "red");
        alert("Please enter your name.");
        return false;
    }
    if (!email && !phone) {
        jQuery("#table-sales-email-label").css("color", "red");
        jQuery("#table-sales-phone-label").css("color", "red");
        alert("Please enter your email address and/or phone number.");
        return false;
    }
    var buyer = {};
    buyer["name"] = name;
    buyer["phone"] = phone;
    buyer["email"] = email;
    // Just collect the quantity from the form in this loop because we
    // can't break all the way out and return failure.
    jQuery.each(
        jQuery(".table-sales-table-select"),
        function(i, select) {
            var tablenum = select.value;
            var qinput = jQuery(select).closest("tr").find("input");
            var quantity = parseInt(qinput.val());
            if (tablenum in order) {
                quantity += order[tablenum]["quantity"];
            } else {
                order[tablenum] = {};
            }
            order[tablenum]["quantity"] = quantity;
            if (quantity > 0) {
                orderingsomething = true;
            }
        });
    if (!orderingsomething) {
        alert("Your reservation is empty! Please select a table and increase the quantity of seats/table to place an order.");
        return false;
    }
    // Check for errors and collect the data we need from the table DB.
    for (var tablenum in order) {
        // Table out of range.
        if (tablenum < 0 || tablenum > TABLE_SALES_NUM_TABLES) {
            alert("Error: unknown table number " + tablenum + ". Please try again.");
            location.reload();
            return false;
        }
        var quantity = order[tablenum]['quantity'];
        // Nonsensical quantity.
        if (quantity < 0) {
            alert("Error: don't know what to do with negative number of seats on table " + tablenum + ". Please try again.");
            location.reload();
            return false;
        }
        // If zero quantity, just remove it.
        if (quantity == 0) {
            delete order[tablenum];
            continue;
        }
        var soldindividually = tablenum > 20; // HACK! Cheating...should check in DB.
        // [mboldt:20130228] Move this check to *right* before we add reservation to DB.
        // If quantity more than available seats, error out.
        // if (quantity > available) {
        //     if (soldindividually) {
        //         alert("Sorry, Table " + tablenum + " only has " + available + " seats left so you may not buy " + q + ".");
        //     } else {
        //         alert("Sorry, Table " + tablenum + " is already sold.");
        //     }
        //     return false;
        // }
        // If we made it this far, we actually have an order!
        order[tablenum]["sold-individually"] = soldindividually;
        if (soldindividually) {
            order[tablenum]["cost"] = TABLE_SALES_PER_SEAT * quantity;
        } else {
            order[tablenum]["cost"] = TABLE_SALES_PER_TABLE;
        }
        order[tablenum]["lineitem"] = "\tTable " + tablenum + " (" + quantity + (soldindividually ? " seats" : " table") + ")\t$" + order[tablenum]["cost"] + "\n";
    }
    // Print out the line items, and add up the total, and confirm with user.
    var total = 0;
    var msg = "Your order:\n";
    for (var tablenum in order) {
        msg += order[tablenum]["lineitem"];
        total += order[tablenum]["cost"];
    }
    msg += "Total: $" + total + "\n\nBy Click OK to place your reservation and proceed to PayJunction for checkout.";
    if (!confirm(msg)) {
        return false;
    }
    // Get paypal crap ready.
}
