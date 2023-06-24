<span class='redspanLink'>DNC format via accnt_nbr:</span><br>
<img src='../assets/images/dnc_acct_nbr_sample.JPG' height=120px>

<br>
<form id='frm-do-upload' method="post" action="<?=base_url()?>main/do_up_dnc_acct_nbr/" enctype="multipart/form-data" target='if-test'>
	<table> 
		<tr>
			<td>
				<label for='userfile'>CSV file for DNC via accnt_nbr</label>
				<input type="file" name="userfile" size="20" class='required' > 
			</td>
		</tr> 
		<tr>
			<td>
				<input type="submit" id='btn-upload' value="upload" />
			</td>
		</tr> 
	</table>
</form>

<hr>
<br>

<span class='redspanLink'>DNC format via NUMBERS:</span><br>
<img src='../assets/images/dnc_number_sample.JPG' height=120px>

<br>
<form id='frm-do-upload-2' method="post" action="<?=base_url()?>main/do_up_dnc_number/" enctype="multipart/form-data" target='if-test'>
	<table> 
		<tr>
			<td>
				<label for='userfile'> CSV file for DNC via Number</label>
				<input type="file" name="userfile" size="20" class='required' > 
			</td>
		</tr> 
		<tr>
			<td>
				<input type="submit" id='btn-upload' value="upload" />
			</td>
		</tr> 
	</table>
</form>



<script>
	$(document).ready(function(){
		
		$("form").submit(function(){
			var frm_id = $(this).attr('id');
			if(!$(this).valid()){
				alert('Please check all required fields');
				return false;
			}else{
			 
				var form = document.getElementById(frm_id);
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
				return false; // To avoid actual submission of the form
				
		});
		
			
	})
</script>