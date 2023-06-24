Sample Format:
<img src='../assets/images/upload_file_sample.jpg' >

<br>
<form id='frm-do-upload' method="post" action="<?=base_url()?>main/do_batch_upload/" enctype="multipart/form-data" target='if-test'>
	<table> 
		<tr>
			<td>
				<label for='lead_identity'>Lead Identity</label>
				<input type='input' name='lead_identity' id='lead_identity' class='required'>
			</td>
		</tr>
		<tr>
			<td>
				<label for='assign_month'>Assign Month (YYYY-MM-DD)</label>
				<input type='input' name='assign_month' id='assign_month' class='required' value = '<?=$assign_month?>'>
			</td>
		</tr>
		<tr>
			<td>
				<label for='userfile'>File</label>
				<input type="file" name="userfile" size="20" class='required' > 
			</td>
		</tr> 
		<tr>
			<td>
				<br>
				<label for='userfile'><b>Dedupping processes</b></label>
				<input type='checkbox' name='dedup_AG' checked=checked> Dedup AG from 2 months ago <br>
				<input type='checkbox' name='dedup_CB' checked=checked> Dedup CB from 1 month ago<br>
				<input type='checkbox' name='dedup_prev_month' checked=checked> Dedup previous month to the current month (will set inactive records from PREVIOUS month based on the value set on "Assign Month")<br>
				
				<input type='checkbox' name='dedup_via_dnc_acct_nbr_db' checked=checked> DNC via acct_nbr field  
					<input type='button' value='Download DNC via accnt_nbr' id='btn_dnc_acct_nbr'>
					<input type='button' class='dnc_upload red-button' value='Upload new DNC file'>
				<br>
				<input type='checkbox' name='dedup_via_dnc_nos_db' checked=checked> DNC via numbers (telno, mobileno, officeno)
					<input type='button' value='Download DNC via Numbers' id='btn_dnc_number'>
					<input type='button' class='dnc_upload red-button' value='Upload new DNC file'>
				<br>
				<br><br>
			</td>
		</tr> 
		<tr>
			<td>
				<input type="submit" id='btn-upload' value="upload" />
			</td>
		</tr> 
	</table>
</form>
<iframe style='display:none' id='if-test'></iframe>



<script>
	function check_lead_identity(){
		var li = $("#lead_identity").val();
		var data = {};
		data['li'] = li;
		var data = $.ajax({
			url:'<?=base_url()?>main/check_lead_identity',
			type:'post',
			data:data,
			success:function(data){
				return data; //returns 1(existing and not allowed to proceed) and 0 is allowed!
			}
		})
		 
	}
	$(document).ready(function(){
		
		$("#frm-do-upload").submit(function(){
			
			
			if(!$(this).valid()){
				alert('Please check all required fields');
				return false;
			}else{
			
				var li = $("#lead_identity").val();
				var data = {};
				data['li'] = li;
				var data = $.ajax({
					url:'<?=base_url()?>main/check_lead_identity',
					type:'post',
					data:data,
					beforeSend:function(){
						$("#btn-upload").val('please wait').prop('disabled',true);
					},
					success:function(data){
						if( data == 1){
							alert('Transaction canceled!. Make it sure Lead identity is UNIQUE!'); 
							$("#btn-upload").val('upload').removeProp('disabled');
						}else{ 
							var form = document.getElementById('frm-do-upload');
							var file  ='';

							var formData = new FormData(form); 
							formData.append('file', file);

							var xhr = new XMLHttpRequest();
							
							xhr.onreadystatechange = function()
							{
								if(xhr.readyState == 4)
								{
								  var resp = xhr.responseText;	 
								  
								  if(resp.indexOf('Success') == 0){
									alert('Done!'); 
									$("#div-messages").html('SUCCESS!');
									var res_arr = resp.split('###');
									window.open('<?=base_url()?>main/dl_log/'+res_arr[1]);
								  }else{
									alert('Error!');
									$("#div-messages").html(resp); 
								 } 
								
									$("#btn-upload").val('upload').removeProp('disabled');
								}
							}
							
							xhr.open('POST', form.getAttribute('action'), true);
							xhr.send(formData);

						}
					}
				}) 
				
				
			}  
				return false; // To avoid actual submission of the form
				
		});
		
		$('#btn_dnc_acct_nbr').click(function(){
			window.open('<?=base_url()?>main/dl_dnc/via_acct_nbr.csv');
		})
		
		$('#btn_dnc_number').click(function(){
			window.open('<?=base_url()?>main/dl_dnc/via_numbers.csv');
		})
		
		$('.dnc_upload').click(function(){
			window.location = '<?=base_url()?>main/dnc_acct_nbr';
		})
			
	})
</script>