$(function() {
	$('#btn_transfer_domain').on('click', function (e){
		e.preventDefault();
		$('#transfer_domain_msg').hide();
					
		if($('#transfer_domain_id').val() < 1){
			$('#transfer_domain_msg').html(form_msgs['no_domain']);
			$('#transfer_domain_msg').show();
			return false;
		}
		
		if($('#transfer_uid').val() < 1){
			$('#transfer_domain_msg').html(form_msgs['no_uid']);
			$('#transfer_domain_msg').show();
			return false;
		}
		
		//Confirm Domain Transfer
		Sentora.dialog.confirm({
			title: form_msgs['transfer_domain_dialog_title'],
			message: form_msgs['transfer_domain_confirm_msg'],
			width: 300,
			cancelButton: {
			    text: form_msgs['transfer_domain_cancel_btn_label'],
			    show: true,
			    class: 'btn-default'
			},
			okButton: {
			    text: form_msgs['transfer_domain_ok_btn_label'],
			    show: true,
			    class: 'btn-primary'
			},
			cancelCallback: function() { return false; },
			okCallback: function() { Sentora.loader.showLoader(); $('#frm_transfer_domain').submit(); }
		});
		//return false;
	});

});
