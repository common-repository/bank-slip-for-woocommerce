var actionselected, action, template, checked, order_ids;

jQuery( document.ready = function() {
    for (action in wc_order_bankslip.bulk_actions) {
        jQuery('<option>').val(action).html(wc_order_bankslip.bulk_actions[action]).appendTo("select[name='action'], select[name='action2']");
    }


    jQuery("#doaction, #doaction2").click(function (event) {
		actionselected = jQuery(this).attr("id").substr(2);
		action = jQuery('select[name="' + actionselected + '"]').val();

		if ( wc_order_bankslip.bulk_actions[action] !== undefined && action == 'wc-order-bankslip-pdf' ) {
			event.preventDefault();
			template = action;
			checked = [];
			jQuery('tbody th.check-column input[type="checkbox"]:checked').each(
				function() {
					checked.push(jQuery(this).val());
				}
			);

			if (!checked.length) {
				alert(wc_order_bankslip.messages.no_orders);
				return;
			}

			order_ids=checked.join(',');

            jQuery('#wc-order-bankslip-options').show(500);
		}
	});

    jQuery("#wc-order-bankslip-options-btn").click(function(){
        if (wp.ajax.settings.url.indexOf("?") != -1) {
            url = wp.ajax.settings.url+'&';
        } else {
            url = wp.ajax.settings.url+'?';
        }
        date = jQuery("#wc-order-bankslip-options-date").val();
        piece_no = jQuery("#wc-order-bankslip-options-piece_no").val();
        account_no = jQuery("#wc-order-bankslip-options-account_no").val();
        url+='action=wc_order_bankslip&document_type='+template+'&order_ids='+order_ids+'&_wpnonce=&date='+date+'&piece_no='+piece_no+'&account_no='+account_no;

        window.open(url,'_blank');
        jQuery('#wc-order-bankslip-options').hide(500);
    });
});
