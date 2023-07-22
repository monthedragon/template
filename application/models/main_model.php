<?php
class Main_model extends CI_Model {
	var $retVal = array();
	
	//this will control the number of chunk to be processed at a time
	//by this way we can avoid MEMORY issue and performance problem
	public $chunk_limit = 2000; 

	public function __construct()
	{
		ini_set('memory_limit','2048M');
		$this->load->database();
		$this->setMaxTouch();
	}
	
	//Set max touch for each record
	//start of 2017-07-22 [max_touch]
	//anything related to $this->max_touch
	public function setMaxTouch(){
		$this->max_touch = 10; //by default
		
		$this->db->select('*')->from('lookup')
					->where('lu_cat','max_touch')
					->where('is_active',1);
		
		$max_touch = $this->db->order_by('order_by')->get()->result_array();
		
		if($max_touch){
			$this->max_touch = $max_touch[0]['lu_code'];
		}
		
	}
	
	public function change_conn($cd){
		$config['hostname'] = $cd['hostname'];
		$config['username'] = $cd['username'];
		$config['password'] = $cd['password'];
		$config['database'] = $cd['campaign_db'];
		$config['dbdriver'] = "mysql";
		$config['dbprefix'] = "";
		$config['pconnect'] = FALSE;
		$config['db_debug'] = TRUE;

		return $this->load->database($config,true);
	}
	
	//get random record
	public function get_rnd_record()
	{
		$id = 0;
		
		//2023-06-24, prioritize CB records over Virgin
		$id = $this->getPastCBRecord();
		
		if(!$id)
			$id = $this->get_virgin_record();

        if($id == 0)
			$id = $this->get_recon();
		
		if($id != 0 && $this->is_with_locked($id))
			return 'A'; //with locked record ;errMsg
		elseif($id != 0 && $this->is_locked($id))
			return 'B'; //if record picked is already locked to other agent ;errMsg
		elseif($id == 0)
			return 'C'; //no more records, please check helpers/site_helper.php for err msg reference
		else
			return $id; 
			
	}

    function get_fixed_where($leadIdentity){
        $userType = $this->session->userdata('user_type');
        $userID = $this->session->userdata('username');

        //if not admin then get all records assign to user
        if($userType != ADMIN_CODE){
            $this->db->where('assigned_agent',$userID);

            $li = array();
            if(count($leadIdentity) > 0){
                foreach($leadIdentity as $key=>$val){
                    $li[] = $key;
                }
                $this->db->where_in('lead_identity',$li);
            }else{
                $this->db->where('lead_identity',"'1'"); //no lead identity assigned
            }
        }
    }
	
	//2023-06-24 get past CB records
	function getPastCBRecord(){
		$this->getPastCBQuery();
        $result = $this->db->order_by('calldate','DESC')
            ->limit('1')
            ->get()
			->row();
			
		// echo ($result) ? $result->id : 0;
		// echo $this->db->last_query();
		// exit();

		if($result)
			return $result->id;
		else
			return 0;
		
	}
	
	//prepare past callback query
	function getPastCBQuery($selectField = 'id'){
		
        $userID = $this->session->userdata('username');
        $leadIdentity = $this->get_lead_identity_assigned($userID);
		$todayDateTime = date('Y-m-d H:i:s');
		$todayDateTimeStart = date('Y-m-d 00:00:00');
		
        $this->db->select($selectField)
            ->from('contact_list')
            ->where('callresult',CB_TAG)
			->where('agent',$userID)
			->where('is_active',1)
			->where("CONCAT(`callbackdate`, ' ', `callbacktime`) >=", $todayDateTimeStart)
			->where("CONCAT(`callbackdate`, ' ', `callbacktime`) <=", $todayDateTime);

        $this->get_fixed_where($leadIdentity);
	}
	
	function getPastCBCTR(){
		$this->getPastCBQuery("count(*) AS CTR");
		$result = $this->db->get()->row();
			
		// echo $this->db->last_query();
		// echo($result->CTR);
		// exit();
		
		return $result->CTR;
	}

	function get_virgin_record()
	{
        $userID = $this->session->userdata('username');
        $leadIdentity = $this->get_lead_identity_assigned($userID);
        $this->db->select('id')
            ->from('contact_list')
            ->where('is_active',1)
            ->where('is_locked',0)
            ->where('calldate is null');

        $this->get_fixed_where($leadIdentity);

        $result = $this->db->order_by('calldate','DESC')
            ->limit('1')
            ->get()
            ->result_array();

		if(count($result) > 0)
			return $result[0]['id'];
		else
			return 0;
	}
	
	//bs and NA
	function get_recon()
	{
        $userID = $this->session->userdata('username');
        $leadIdentity = $this->get_lead_identity_assigned($userID);
		$this->db->select('contact_list.id,COUNT(*) as attempts')
				->from('contact_list')
				->join('trans_log','contact_id = contact_list.id ')
				->where('is_active',1)
				->where('is_locked',0)
				->where_in('contact_list.callresult',array('BS','NA',''))
                ->where('calldate IS NOT NULL');

        $this->get_fixed_where($leadIdentity);
		
        $result = $this->db
				->group_by('contact_list.id')
				->having("attempts < {$this->max_touch}")
				->order_by('calldate,attempts','DESC')
				->limit('1')
				->get()
				->result_array();


		if(count($result) > 0)
			return $result[0]['id'];
		else
			return 0;
	}
	  
	  
	public  function get_locked_record(){
		$username = $this->session->userdata('username');
		return $this->db->select('*')->from('contact_list')
						->where('is_locked',1)
						->where('is_active',1)
						->where('agent',$username)
						->get()
						->result_array();
	}

	public  function get_popout_records(){
		$username = $this->session->userdata('username');
		return $this->db->select('*')->from('contact_list')
						->where('forcedpop',1)
						->where('is_active',1)
						->where('agent',$username)
						->get()
						->result_array();
	}
	
	public  function get_callback_record(){
		$username = $this->session->userdata('username');
        $leadIdentity = $this->get_lead_identity_assigned($username);
		$this->db->select('*')->from('contact_list')
						->where('callresult',CB_TAG)
						->where('agent',$username)
						->where('is_active',1);

        $this->get_fixed_where($leadIdentity);
				
				$result = $this->db
											->order_by('callbackdate,callbacktime')
											->get()
											->result_array();
						
        return $result;
	}	
	
	public function search_contact_list($all){
		$retVal = array(); 
        $userType = $this->session->userdata('user_type');
        $userID = $this->session->userdata('username');
        $leadIdentity = $this->get_lead_identity_assigned($userID);
        $selectFields = 'contact_list.id,
                        contact_list.firstname,
                        contact_list.lastname,
                        contact_list.pd_name,
                        contact_list.calldate,
                        contact_list.callresult,
                        contact_list.sub_callresult,
                        contact_list.is_active,
                        contact_list.forcedpop,
                        contact_list.ag_type,
                        contact_list.lead_identity,
						GROUP_CONCAT(" ",CONCAT(supplementary.firstname," ",supplementary.lastname)) AS supple_fullname' ;

        $this->db->select($selectFields,FALSE)->from('contact_list')
				->join('supplementary','contact_list.id = supplementary.baserecid','left');	
		
		if($all){
            $this->db->where('is_active',1); 
		}else{
		
			$pdata = $this->input->post();

			if($pdata['firstname'] != '')
				$this->db->like('contact_list.firstname', $pdata['firstname']);

			if($pdata['lastname'] != '')
				$this->db->like('contact_list.lastname', $pdata['lastname']);

            if($pdata['pd_name'] != '')
                $this->db->like('contact_list.pd_name', $pdata['pd_name']);

            if(!isset($pdata['show_all']))//show all record even not active for admin use only
				$this->db->where('is_active',1);
				
			//Supple fields are being added on the search screen as of 06/11/2016
			if(isset($pdata['supple_firstname']) && !empty($pdata['supple_firstname'])){
				$this->db->like('supplementary.firstname',$pdata['supple_firstname']);
			}
			
			//Supple fields are being added on the search screen as of 06/11/2016
			if(isset($pdata['supple_lastname']) && !empty($pdata['supple_lastname'])){
				$this->db->like('supplementary.lastname',$pdata['supple_lastname']);
				
			}
			
			//2018-10-27
			//ADDED contact number fields on search, once it available it will search to these 3 fields telno,mobileno,officeno
			//it is only available to super admin
			if(isset($pdata['contact_number']) && !empty($pdata['contact_number'])){
				//telno,mobileno,officeno
				$contact_number = $pdata['contact_number'];

				$this->db->where( ' ( 	contact_list.telno like "%'.$contact_number.'%" OR 
										contact_list.mobileno like "%'.$contact_number.'%" OR 
										contact_list.officeno like "%'.$contact_number.'%" ) ', NULL, FALSE ); 
			}
		}
		
		
		
		if($userType == ADMIN_CODE || isset($this->data[199])){
			//user allowed to search ALL records regardless of the tagging (06/11/2016)
			//set NOTHING
		}elseif(isset($this->data[200])){
			//user allowed to search CB records  (06/11/2016)
			$this->db->where('callresult','CB');	
		}else{
			//DEFAULT USER  wont show any records on available leads
			$this->db->where('callresult','no_records_to_show');
		}

        $this->get_fixed_where($leadIdentity);

		// need to GROUP BY contact_list.id because it might get many records on supplementary table (06/11/2016)
		$retVal = $this->db->group_by('contact_list.id')
						->order_by('calldate is null','DESC')
						->order_by('callresult = ""','DESC')
						->order_by('callresult = "BS"','DESC')
						->order_by('callresult = "NA"','DESC')
						->order_by('callresult = "CB"','DESC')
						->order_by('calldate')
						->limit(LIMIT)
						->get()
						->result_array();
						
		//echo $this->db->last_query();
		return $retVal; 
	}

    function get_assign_lead_identity($userid){

    }
	
	//$action = 0 edit; 1 = view onely ;
	function get_details_byID($id,$action){
		if($this->is_with_locked($id) && $action==0)
			return 0; //with locked record ;errMsg
		elseif($this->is_locked($id) && $action==0)
			return 1; //if record picked is already locked to other agent ;errMsg
		else{
			$username = $this->session->userdata('username');

			if($action == 0){ //set locked to record if action = edit
				$update = array('is_locked'=>1,'agent'=>$username);
				$this->db->where('id',$id)->update('contact_list',$update);
			}
			
			return $this->get_record_by_id($id);
		}
	}
	
	function get_record_by_id($id)
	{
		return $this->db->get_where('contact_list',array('id'=>$id))->result_array();
	}
	
	public function dopop($id,$action)
	{
        $pdata = $this->input->post();
        $this->db->where('id',$id)->update('contact_list',array('forcedpop'=>$action,'agent'=>$pdata['pop_to']));
	}
	
	function get_history($id){
		$user_role = $this->session->userdata('user_type');
		//mon as of May 14 2016
		//This is to show again the history to all users but ONLY CB log will be available
		//ONly ADMIN user can show every logs
		$this->db->select("trans_log.*")
				->from('trans_log')
				->where('contact_id',$id);
				
		if($user_role != ADMIN_CODE){
			$this->db->where('callresult','CB');
		}
		
		return $this->db->order_by('time_stamp','DESC')
					->get()
					->result_array();
	}
	
	
	function is_with_locked($id){
		$username = $this->session->userdata('username');
		
		//check if with locked record and the ID is not equal to picked record
		$result = $this->db->select('id')
						->from('contact_list')
						->where('is_locked',1)
						->where('is_active',1)
						->where('agent',$username)
						->where('id !=',$id)
						->get();
		
		//with locked record
		if($result->num_rows() > 0)
			return 1;
		else
			return 0;
		
	}
	
	function is_locked($id){  
		$username = $this->session->userdata('username');
		$result = $this->db->select('id')
						->from('contact_list')
						->where('is_locked',1)
						->where('is_active',1)
						->where('id',$id)
						->where('agent !=',$username)
						->get();
		
		//locked record to other agent
		if($result->num_rows() > 0)
			return 1;
		else
			return 0;
		
	}
	
	function save($id){
		$pdata = $this->input->post();   
		if($id != 0){
			$pdata['calldate'] = date('Y-m-d H:i:s');
			
			//this field is only for trans log 
			unset($pdata['remarks']);
			
			//unlocked the record,even the pop to 0
			$pdata  = array_merge($pdata,array('is_locked'=>0,'forcedpop'=>0)); 
			$this->db->where("id",$id)->update('contact_list',$pdata);
			
			
			//add to trans logs
			$this->update_trans_logs($id);
		}else
			$this->db->insert('contact_list',$pdata);
		
	}
	
	function update_trans_logs($id,$callresult=''){
		$pdata = $this->input->post(); 
		
		if($callresult == '')
			$callresult = $pdata['callresult'];
		
		//IF CALLBACK, then set the callback date and time set by the user in remarks
		if($pdata['callresult'] == CB_TAG)
			$pdata['remarks'] .= "<br><br> Callback date and time : {$pdata['callbackdate']}  {$pdata['callbacktime']}";

        $subCallresult= (isset($pdata['sub_callresult']) ? $pdata['sub_callresult'] : '') ;
        $agType= (isset($pdata['ag_type']) ? $pdata['ag_type'] : '') ;
		
		$username = $this->session->userdata('username');
		$transLog = array('contact_id'=>$id,
						  'callresult'=>$callresult, 
						  'sub_callresult'=>$subCallresult,
                          'ag_type'=>$agType,
						  'dob'=>$pdata['c_dob'], 
						  'gender'=>$pdata['c_gender'],   
						  'user_id'=>$username,
						  'remarks'=>$pdata['remarks']);
						  
		$this->db->insert('trans_log',$transLog);
	}
	
	public function set_uploading_folder(){
		$rootFolder = './uploads/';
		return $rootFolder;
	}
	
	function is_restricted_field($col){
		//add restricted field here
		$restrictedFields  = array('id','lead_identity');
		 
		if(in_array($col,$restrictedFields))
			return 1;
		else
			return 0;
	}
	
	function check_column_alter($col){
		if(!$this->is_restricted_field($col)){
		
			$fields = mysql_list_fields(MAIN_DB, CONTACT_LIST);
			$columns = mysql_num_fields($fields);
			for ($i = 0; $i < $columns; $i++) {$field_array[] = mysql_field_name($fields, $i);}

			if (!in_array($col, $field_array)){
				
				$sql = 'ALTER TABLE '.CONTACT_LIST.
						' ADD `' . $col . '` CHAR(60) NOT NULL';
				$this->db->query($sql);
			}
			
		}
	}
		  
	function structure_column($col){
		$col = str_replace("'","",$col);
		$col = str_replace(' ','_',$col);
		$col = preg_replace('/[^A-Za-z0-9\_-]/', '', $col);
		return strtolower(trim($col));
	}
	
	function do_batch_disable(){
		$udata = $this->upload->data();  
		$pdata = $this->input->post(); 
		$doDelete = (isset($pdata['do_delete']) ? 1 : 0);
		
		$allowedTypes = array('application/msword','application/vnd.ms-excel');
		 
		$validValues = array('id','is_active');
		
		if(!in_array($udata['file_type'],$allowedTypes))
			return 2;
		else{
		
			//read the db xls
			$dataXls = new Spreadsheet_Excel_Reader();
			$dataXls->read($udata['full_path']); 
			
			for ($j = 1; $j < $dataXls->sheets[0]['numRows']; $j++){
				//var to hold data of valid record
				$updateData = array();
				$whereArr = array();
				
				for($a=1;$a<=$dataXls->sheets[0]['numCols'];$a++){
					
					$xlsCurCol =  $this->structure_column($dataXls->sheets[0]['cells'][1][$a]);
					
					if(in_array($xlsCurCol,$validValues)){
						$val = isset($dataXls->sheets[0]['cells'][$j+1][$a]) ? $dataXls->sheets[0]['cells'][$j+1][$a] : '';
							
						if($val != '')
							$val = trim(str_replace("'","''",$val));
						
						if($xlsCurCol == 'id')
							$whereArr['id'] = $val;
						
						if($xlsCurCol == 'is_active')
							$updateData['is_active'] = $val;
							
					}else{
						return 3;
					}					
				} 
				if($doDelete == 1){
					$this->db->delete('contact_list',$whereArr);
				}else{
					$this->db->update('contact_list',$updateData,$whereArr);
				}
				
			}
			
			return 1;
		}
	
	}
	
	function do_batch_upload(){
		
		$udata = $this->upload->data();  
		$pdata = $this->input->post();
		
		$allowedTypes = array('application/msword','application/vnd.ms-excel');

        /*for reference only*/
		$validValues = array('firstname',
                            'middlename',
                            'lastname',
                            'telno',
                            'mobileno',
                            'officeno',
                            'credit_limit',
                            'ava_cred_limit',
                            'bill_cycle',
                            'double_cl');
		
		
		
		if(!in_array($udata['file_type'],$allowedTypes))
			return 2;
		else{
		
			//read the db xls
			$dataXls = new Spreadsheet_Excel_Reader();
			$dataXls->read($udata['full_path']); 
			
			$this->add_non_existing_column($dataXls);
			
			for ($j = 1; $j < $dataXls->sheets[0]['numRows']; $j++){
				//var to hold data of valid record
				$insertData = array();
				$insertData['lead_identity'] = $pdata['lead_identity'];
				for($a=1;$a<=$dataXls->sheets[0]['numCols'];$a++){
					
					$xlsCurCol =  $this->structure_column($dataXls->sheets[0]['cells'][1][$a]);
					
					#$this->check_column_alter($xlsCurCol);
					
					#if(in_array($xlsCurCol,$validValues)){
					$val = isset($dataXls->sheets[0]['cells'][$j+1][$a]) ? $dataXls->sheets[0]['cells'][$j+1][$a] : '';
						
					if($val != '')
						$val = trim(str_replace("'","''",$val));
					
					$insertData[$xlsCurCol] = $val;
						
					#} 
				}  
				$this->db->insert('contact_list',$insertData);
			}
			
			$this->db->insert('leads_details',array(
														'lead_identity'=>$pdata['lead_identity'],
														'assign_month'=>$pdata['assign_month'])
													); //insert lead_identity in the list
			
			$this->doDedupping($pdata['lead_identity']);		
			return 1;
		}
	
	}
	
	//2019-10-05 (mon)
	//Main entry on the update done for 2019-10-05
	//2020-12-12 (mon) change pd_name to acct_br as per requested by Sir Vince
	public function doDedupping($lead_identity){
		$this->CreateLogFile();
		$pdata = $this->input->post();
		// echo '<pre>';
		$this->target_li = $lead_identity;
		
		//TODO: as of May 8, 2021 need to create an output file base on this variable
		//This will hold the information of the dedupped records
		$this->dup_rec_arr = array();
		
		$this->remove2MosAG1MoCB();
		$this->removeIrate();
		
		//2021-01-10 added dedupping control
		if(isset($pdata['dedup_prev_month'])){
			$this->inactiveDupRecFrPrevMon();
		}
		
		//2021-07-10 added dedupping control
		if(isset($pdata['dedup_via_dnc_acct_nbr_db'])){
			$this->removeDncViaAcctNbr();
		}
		
		if(isset($pdata['dedup_via_dnc_nos_db'])){
			$this->remoteDncViaNumbers();
		}
		// exit(); 
	}
	
	//From the targeted CSV check all the records just uploaded if there is same acct_nbr
	//Then remove it from the list
	function removeDncViaAcctNbr(){
		$target_path ='uploads/dnc/via_acct_nbr.csv';
		$file = fopen($target_path, 'r');

		$acct_nbr_arr = array();
		while (($line = fgets($file)) !== false) {
			$acct_nbr_arr[] = $line;
		}
		// echo '<pre>';
		// print_r($acct_nbr_arr);
		// exit();
		fclose($file);
		
		$trg_rec_arr_chunk = array_chunk($acct_nbr_arr,$this->chunk_limit);
		
		foreach($trg_rec_arr_chunk as $trg_data_arr){
			
			//IMPLODE to be added on WHERE QUERY 
			$trg_rec_str = "('".implode("','",$trg_data_arr) . "')";
			$where_condition = " is_active = 1 
								  AND lead_identity = '{$this->target_li}'
								  AND acct_nbr IN {$trg_rec_str}";
			
			$this->getDeduppedRecords($where_condition,'removeDncViaAcctNbr');
								  
			$query = "DELETE FROM contact_list 
					  WHERE {$where_condition}";
			
			// echo $query . '<br>';
			$this->db->query($query);
			
		}
		
		// exit();
	}
	
	//From the targeted CSV check all the records just uploaded if there is same telno, mobileno, or officeno
	//Then remove it from the list
	function remoteDncViaNumbers(){
		$target_path ='uploads/dnc/via_numbers.csv';
		$file = fopen($target_path, 'r');
		
		$number_arr = array();
		while (($line = fgets($file)) !== false) {
			$number_arr[] = $line;
		}
		
		// print_r($number_arr);
		fclose($file);
		
		$trg_rec_arr_chunk = array_chunk($number_arr,$this->chunk_limit);
		
		foreach($trg_rec_arr_chunk as $trg_data_arr){
			
			//IMPLODE to be added on WHERE QUERY 
			$trg_rec_str = "('".implode("','",$trg_data_arr) . "')";
			$where_condition = " is_active = 1 
								  AND lead_identity = '{$this->target_li}'
								  AND 
									( 
										telno IN {$trg_rec_str} OR
										mobileno IN {$trg_rec_str} OR
										officeno IN {$trg_rec_str} 
									)";
			
			$this->getDeduppedRecords($where_condition,'remoteDncViaNumbers');
								  
			$query = "DELETE FROM contact_list 
					  WHERE {$where_condition}";
			
			// echo $query . '<br>';
			$this->db->query($query);
			
		}
		
		// exit();
	}
	
	
	
	//2019-10-05 (mon)
	//Get AG after 2 months until current date
	//Also get the CB for CONFIRMATION for previous month to current date
	//And remove from the uploaded lead_identity
	public function remove2MosAG1MoCB(){
		$today_date = date('Y-m-01');
		$two_month_ago = date('Y-m-d 00:00:00',strtotime($today_date. '-2 months'));
		$one_month_ago = date('Y-m-d 00:00:00',strtotime($today_date. '-1 month'));
		$pdata = $this->input->post();
		
		$target_condition = '';
		
		//2021-01-10 added dedupping control
		if(isset($pdata['dedup_AG'])){
			$target_condition = "(callresult = 'AG' AND calldate >= '{$two_month_ago}')";
		}
		
		//2021-01-10 added dedupping control
		if(isset($pdata['dedup_CB'])){
			$target_condition .= ($target_condition != '') ? ' OR ' : ''; 
			$target_condition .= " (callresult = 'CB' AND sub_callresult IN ('forconfirmation','ConfirmationSSSTIN') AND calldate >= '{$one_month_ago}') ";
		}
		
		if($target_condition == ''){
			return false; //do nothing if there is no dedup being set for AB and CB
		}
		
		$query = "	SELECT acct_nbr FROM contact_list 
					WHERE 
						(
						{$target_condition}
						)	
					
					AND is_active = 1
					
					";
		//TODO: this query should be removed 
		// $query .= " AND lead_identity IN ('2nd card 2021-Jan-REAL Bt 1','2nd card 2021-Jan-REAL Bt 2')  ";		


		//get the raw data
		$trg_rec_arr_raw = $this->db->query($query)->result_array();
		
		
		//re-construct array 
		$trg_rec_arr = array();
		foreach($trg_rec_arr_raw as $details){
			$acct_nbr = trim($details['acct_nbr']);
			$this->dup_rec_arr['ag_and_cb'][$acct_nbr] = $acct_nbr;
			$trg_rec_arr[] = $acct_nbr;
		}
		
		//chunk the target data to be deleted to avoid memory issue when there are too many 
		$trg_rec_arr_chunk = array_chunk($trg_rec_arr,$this->chunk_limit);
		
		
		foreach($trg_rec_arr_chunk as $trg_data_arr){
			
			//IMPLODE to be added on WHERE QUERY 
			$trg_rec_str = "('".implode("','",$trg_data_arr) . "')";
			$where_condition = "is_active = 1 
								  AND lead_identity = '{$this->target_li}'
								  AND acct_nbr IN {$trg_rec_str}";
			
			$this->getDeduppedRecords($where_condition,'remove2MosAG_1MoCB');
								  
			$query = "DELETE FROM contact_list 
					  WHERE {$where_condition}";
			
			// echo $query . '<br>';
			
			$this->db->query($query);
			
		}
			  
	}
	
	//2019-10-05 (mon)
	//Get all DO NOT CALL records from the beginning and remove it from the current lead_identity
	public function removeIrate(){
		$query = "SELECT acct_nbr FROM contact_list 
				  WHERE callresult = 'DONOTCALL' 
				  AND is_active = 1";

		//get the raw data
		$trg_rec_arr_raw = $this->db->query($query)->result_array();
		
		//re-construct array 
		$trg_rec_arr = array();
		foreach($trg_rec_arr_raw as $details){
			$acct_nbr = trim($details['acct_nbr']);
			$this->dup_rec_arr['irate'][$acct_nbr] = $acct_nbr;
			$trg_rec_arr[] = $acct_nbr;
		}
		
		//chunk the target data to be deleted to avoid memory issue when there are too many 
		$trg_rec_arr_chunk = array_chunk($trg_rec_arr,$this->chunk_limit);
		
		foreach($trg_rec_arr_chunk as $trg_data_arr){
			
			//IMPLODE to be added on WHERE QUERY 
			$trg_rec_str = "('".implode("','",$trg_data_arr) . "')";
			$where_condition = " is_active = 1 
								  AND lead_identity = '{$this->target_li}'
								  AND acct_nbr IN {$trg_rec_str}";
			
			$this->getDeduppedRecords($where_condition,'remove_irate');
								  
			$query = "DELETE FROM contact_list 
					  WHERE {$where_condition}";
			
			// echo $query . '<br>';
			$this->db->query($query);
			
		}
		
	}

	//2019-10-05 (mon)
	//Inactive (is_active = 0) those records that will be found as DUPLICATE from the PREVIOUS LEAD IDENTITY
	//DONT mind the AG, CB for Confirmation and DONT call IRATE
	//Because they are already deleted at this point from target lead_identity
	public function inactiveDupRecFrPrevMon(){
		$target_prev_li = $this->getPrevLi();
		
		if($target_prev_li){
			//get the newly saved acct_nbr using $this->target_li
			// and all the names to be gathered here
			//UPDATE its duplicate record from previous LIs
			$query = "SELECT acct_nbr FROM contact_list 
					  WHERE lead_identity = '{$this->target_li}'";
			$result = $this->db->query($query)->result_array();
			
			$trg_rec_arr = array();
			foreach($result as $details){
				$acct_nbr = trim($details['acct_nbr']);
				$this->dup_rec_arr['inactive_dup_fr_prev_month'][$acct_nbr] = $acct_nbr;
				$trg_rec_arr[] = $acct_nbr;
			}
			
			//chunk the target data to be deleted to avoid memory issue when there are too many 
			$trg_rec_arr_chunk = array_chunk($trg_rec_arr,$this->chunk_limit);
			
			$prev_li_str = "('".implode("','",$target_prev_li) . "')";
				
			//TODO: this query should be removed 
			// $prev_li_str = "('2nd card 2021-Jan-REAL Bt 1','2nd card 2021-Jan-REAL Bt 2') ";
			
			foreach($trg_rec_arr_chunk as $trg_data_arr){
			
				//IMPLODE to be added on WHERE QUERY 
				$trg_rec_str = "('".implode("','",$trg_data_arr) . "')";
				
				$where_condition = "is_active = 1 
									AND lead_identity != '{$this->target_li}'
									AND lead_identity IN {$prev_li_str}
									AND acct_nbr IN {$trg_rec_str}";
										
				
				$this->getDeduppedRecords($where_condition,'inactiveDupRecFrPrevMon');
									
				$query = "UPDATE contact_list 
						  SET 
							is_active = 0
						  WHERE {$where_condition}";
				
				// echo $query . '<br>';
				$this->db->query($query);
				
			}
			
		}
	}	
	
	public function getDeduppedRecords($where_condition,$dedup_from){
		
		// $this->AppendToLog("TARGET PROCESS: {$target_process}");
		$query = "SELECT
					cust_nbr
					acct_nbr,
					MASKED_CARD_NBR,
					pd_name,
					lead_identity,
					'$dedup_from'
				FROM contact_list WHERE  {$where_condition}";
		// $this->AppendToLog($query);
		
		$result = $this->db->query($query)->result_array();
		
		foreach($result as $details){
			
			if(!isset($this->append_cols)){
				$this->append_cols = true;
				$this->AppendToLog(implode(",",array_keys($details)),1);
			}
			
			$this->AppendToLog(implode(",",$details),1);
		}
	}
	
	
    /**
     * Create log file
     */
    public function CreateLogFile() {
		$this->log_folder = '/var/www/html/template/uploads/dedup';
        @mkdir($this->log_folder, 0777, true);
        $ext=".txt";
        $filename= "dedup_result_".date("YmdHis");
        $logFilename=$this->log_folder."/".$filename.$ext;
		$this->log_filename = $filename;
        $this->fh = @fopen($logFilename, "a+" );

    }

    /**
     * Write to log file
     * @param $msg
     */
    public function AppendToLog($msg,$is_plain = 0) {
        if (!$this->fh) return;
		
		if($is_plain ){
			$msg=$msg."\r\n";
		}else{
			$msg="\n@".date("Y-m-d H:i:s")." [memory] ".number_format(memory_get_usage())."->\r\n".$msg."\r\n";
		}
        fwrite($this->fh, $msg);
    }

	
	//2019-10-05 (mon)
	//get previous LI using leads_details.assign_month
	public function getPrevLi(){
		$cur_date = date('Y-m-d');
		$prev_month = date('Y-m-d',strtotime($cur_date.' -1 month'));
		
		//TODO: SHOULD be enable on Monday Janyary 11 2021 and disable after use his is for second task (TARGET: 2nd Card 2020 - January 2021 - Real (AKA. JAN LEADS))
		//as of 2021-01-11 code commented
		//$prev_month = $cur_date; //SET TEMPORARILY willk only be use on Monday January 11 2021 (should be removed after that)
		 
		
		list($trgt_year,$trgt_mon,$trgt_day) = explode('-',$prev_month);
		
		$data = $this->db->select('*')
					->from('leads_details')
					->where('YEAR(assign_month)',$trgt_year)
					->where('MONTH(assign_month)',$trgt_mon)
					->get()->result_array();
		
		$target_li = array();
		foreach($data as $details){
			$target_li[] = $details['lead_identity'];
		}
		
		return $target_li;
	}
		
	public function add_non_existing_column($dataXls){ 
		for ($j = 1; $j <= 1; $j++){
					//var to hold data of valid record 
					for($a=1;$a<=$dataXls->sheets[0]['numCols'];$a++){ 
						$xlsCurCol =  $this->structure_column($dataXls->sheets[0]['cells'][1][$a]); 
						$this->check_column_alter($xlsCurCol);
					}
		}
	}
	
	public function get_card_details($id)
	{
		return $this->db->select('*')->from('card_details')->where('baserecid',$id)->get()->result_array();
	}
	
	public function get_card_details_by_ID($cardID)
	{ 
		return $this->db->select('*')->from('card_details')->where('id',$cardID)->get()->result_array();
	}
	
	public function get_supple($id)
	{
		return $this->db->select('*')->from('supplementary')->where('baserecid',$id)->get()->result_array();
	} 
	
	public function get_supple_details_by_ID($suppleID)
	{
		return $this->db->select('*')->from('supplementary')->where('id',$suppleID)->get()->result_array();
	}
	
	public function save_supple($id,$suppleID)
	{
		$pdata = $this->input->post();
		
		if($suppleID == 0) //insert
			$this->db->insert('supplementary',array_merge(array('baserecid'=>$id),$pdata));
		else //update
			$this->db->where('id',$suppleID)->update('supplementary',$pdata);
	}
	
	public function save_card($id,$cardID)
	{
		$pdata = $this->input->post();
		
		if($cardID == 0) //insert
			$this->db->insert('card_details',array_merge(array('baserecid'=>$id),$pdata));
		else //update
			$this->db->where('id',$cardID)->update('card_details',$pdata);
	} 
	
	public function get_allocated_leads($userid)
	{ 
		$retVal = array();
		$result = $this->db->select('lead_identity,
								count(case when calldate is null then 1 else null end ) as VIRGIN')
				->from('contact_list')
				->where('is_active',1)
				->where('is_locked',0)
				->where('forcedpop',0)
				->where('assigned_agent',$userid)
				->group_by('lead_identity')
				->get()
				->result_array();
				
		foreach($result as $r){
			$this->retVal[$r['lead_identity']]['V']['LESS_9']  = $r['VIRGIN'];
		}
			
		$this->get_allocated_leads_touched($userid);
		
		
		return $this->retVal;
	}
	
	/**
	* Get allocated touched leads
	*/
	function get_allocated_leads_touched($userid){
		$retVal  = array();
		
		/*
		$query = "SELECT 
					lead_identity,callresult,count(*) as CTR
				FROM contact_list 
				WHERE is_active = 1 
				AND calldate IS NOT NULL
				AND assigned_agent = '{$userid}'
				AND forcedpop = 0 
				AND is_locked = 0
				GROUP BY lead_identity,callresult";
		*/
		
		//mon as of June 25 2016
		//Changes the way how the leads dispalyed on the screen, separate records that is less than 9 and greater or equal to 9 
		//For management purposes
		$query = "SELECT 
					lead_identity,
					callresult,
					COUNT(CASE WHEN no_of_touches < {$this->max_touch} THEN 1 ELSE NULL END) AS LESS_9 ,
					COUNT(CASE WHEN no_of_touches >= {$this->max_touch} THEN 1 ELSE NULL END) AS OVER_USED   
				FROM (
					SELECT 
						lead_identity,
						contact_list.callresult,
						COUNT(DISTINCT contact_list.id) AS actual_record,
						COUNT(*) AS no_of_touches 
					FROM contact_list 
					LEFT JOIN trans_log ON contact_id = contact_list.id
					WHERE contact_list.is_active = 1 
					AND contact_list.calldate IS NOT NULL
					AND contact_list.assigned_agent = '{$userid}'
					AND contact_list.forcedpop = 0 
					AND contact_list.is_locked = 0
					GROUP BY contact_list.id 
				) AS new_tbl 
				GROUP BY lead_identity,callresult";
		
		$result =  $this->db->query($query)->result_array();
		
		foreach($result as $r){
			$this->retVal[$r['lead_identity']][$r['callresult']]['LESS_9'] 	= $r['LESS_9'];
			$this->retVal[$r['lead_identity']][$r['callresult']]['OVER_USED'] = $r['OVER_USED'];
		}
	
		return $this->retVal;
	}
	 
	/**
	* Get un assigned leads to be allocated
	*/
	public function get_unallocated_leads()
	{
		$retVal =array();
		/*
		$sql = "Select 
					lead_identity,callresult,count(*) as CTR 
				from contact_list 
				where is_active = 1 
				and assigned_agent = '' 
				and calldate is not null
				group by callresult,lead_identity";
		*/
		
		//mon as of June 25 2016
		//Changes the way how the leads dispalyed on the screen, separate records that is less than 9 and greater or equal to 9 
		//For management purposes
		$liWhere = $this->addWhereActiveLI(); //added 2023-06-24 for performance
		$query = "SELECT 
					lead_identity,
					callresult,
					COUNT(CASE WHEN no_of_touches < {$this->max_touch} THEN 1 ELSE NULL END) AS LESS_9 ,
					COUNT(CASE WHEN no_of_touches >= {$this->max_touch} THEN 1 ELSE NULL END) AS OVER_USED   
				FROM (
					SELECT 
						lead_identity,
						contact_list.callresult,
						COUNT(DISTINCT contact_list.id) AS actual_record,
						COUNT(*) AS no_of_touches 
					FROM contact_list 
					LEFT JOIN trans_log ON contact_id = contact_list.id
					WHERE contact_list.is_active = 1 
					AND contact_list.calldate IS NOT NULL
					AND contact_list.assigned_agent = ''
					AND contact_list.forcedpop = 0 
					AND contact_list.is_locked = 0
					{$liWhere}
					GROUP BY contact_list.id 
				) AS new_tbl 
				GROUP BY lead_identity,callresult";
				
		$result =  $this->db->query($query)->result_array();
		
		foreach($result as $r){			
			$retVal[$r['lead_identity']][$r['callresult']]['LESS_9'] 	= $r['LESS_9'];
			$retVal[$r['lead_identity']][$r['callresult']]['OVER_USED'] = $r['OVER_USED'];
		}
		
		// echo $this->db->last_query();
		// exit();
		// var_dump($this->leadsDetails);
		return $retVal;
	}
	
	//Added 2023-06-25 to get active lead_identity, and use in any WHERE condition to limit the target record
	function addWhereActiveLI($isActiveRecord = false){

		if($this->leadsDetails){
			$li = array();
			foreach($this->leadsDetails as $details){
				$li[] = $details['lead_identity'];
			}
			
			if($li){
				if($isActiveRecord){
					$this->db->where_in('lead_identity',$li);
				}else{
					$addWhere = " AND contact_list.lead_identity IN ('". implode("','", $li) ."') ";
					
					return $addWhere;
				}
			}
		}
	}

	
	/**
	* Get un assigned virgin leads to be allocated
	*/
	public function get_unallocated_virgin_leads()
	{
		$retVal =array();
		$sql = "Select lead_identity,count(*) as CTR 
				from contact_list 
				where calldate is null  
				and is_active = 1 
				and assigned_agent = '' 
				group by lead_identity";
		
		$result =  $this->db->query($sql)->result_array();
		 
		foreach($result as $r)
			$retVal[$r['lead_identity']] = $r['CTR'];
		
		
		return $retVal;	
	}
	
	/**
	* Do the actuall ALLOCATING/REMOVING of leads
	*/
	public function allocate_leads(){
		$post = $this->input->post();
		$agentID = $this->session->userdata('agentID');
		
		//Allocation for removing leads
		if($post['allocType'] == 1){ //un allocate leads
			$sql 		= " UPDATE contact_list
							SET 
								assigned_agent = '',
								callresult = '' ";
						
			$where_sql 	=" WHERE contact_list.is_active = 1
							AND contact_list.assigned_agent = '{$agentID}'";
			
			if($post['callresult'] == VIRGIN_CODE)//if de-allocation for virgin
				$where_sql .= " AND contact_list.calldate IS NULL ";
			else
				$where_sql .= " AND contact_list.callresult = '{$post['callresult']}' AND contact_list.calldate IS NOT NULL ";
			
		}else{ //allocate leads
			
			$sql 		= " UPDATE contact_list
							SET assigned_agent = '{$agentID}' ";
							
			$where_sql 	= " WHERE contact_list.is_active = 1 
							AND contact_list.assigned_agent = '' ";
			
			if($post['callresult'] == VIRGIN_CODE)//if allocation for virgin
				$where_sql .= " AND contact_list.calldate IS NULL ";
			else
				$where_sql .= " AND contact_list.callresult = '{$post['callresult']}' AND contact_list.calldate IS NOT NULL ";
		}
		
		$where_sql .= " AND contact_list.lead_identity = \"{$post['li']}\"
						AND contact_list.is_locked = 0
						AND contact_list.forcedpop = 0 ";
		
		$limit_sql = " LIMIT {$post['value']} ";
		
		$target_ids = '';
		if($post['callresult'] != VIRGIN_CODE){ //no applicable on VIRGIN recors
			//get targted leads to be allocated or removed
			$target_ids = $this->get_target_contact_list($where_sql,$limit_sql);
		}
		
		if(!empty($target_ids) || $post['callresult'] == VIRGIN_CODE){
			//2019-10-05
			//allocation and deallocation should be in order based on the EXCEL uploaded through batch upload
			$order_by = ' ORDER BY contact_list.id '; 
			
			if($post['callresult'] != VIRGIN_CODE){
				$where_sql .= " AND contact_list.id IN {$target_ids}";
			}
			
			//concatenate all the queries
			$query = $sql.$where_sql.$order_by.$limit_sql;
			//echo $query;
			$this->db->query($query); //do the actually allocation or removing records
			
		}else{
			//IF there is no records to be targetted then do nothing!
		}
	}
	
	/**
	* Mon as of June 25, 2016
	* Function to get TARGETTED records in contact_list table to be ALLOCATED or REMOVED
	*/
	function get_target_contact_list($where_sql,$limit_sql){
		$query = "SELECT 
						contact_list.id,COUNT(*) 
					FROM contact_list
					INNER JOIN trans_log ON contact_id = contact_list.id
					{$where_sql}
					GROUP BY contact_list.id
					HAVING COUNT(*) < {$this->max_touch}
					ORDER BY COUNT(*)
					{$limit_sql}";
		
		$result =  $this->db->query($query)->result_array();
		$where_arr = array();
		foreach($result as $details){
			$where_arr[$details['id']] = true;
		}
		
		$return_where = '';
		if($where_arr){
			$return_where =  "('".implode("','",array_keys($where_arr)) ."')";
		}
		
		//return the WHERE condition that contains contact_list.id in IN condition
		return $return_where;
		
	}

    //get lead identity in users_lead_identity
    public function get_lead_identity_assigned($userID){
        $retVal = array();

        $result = $this->db->select('users_lead_identity.*')
                        ->from('users_lead_identity')
						->join('leads_details','users_lead_identity.lead_identity = leads_details.lead_identity ')
                        ->where('leads_details.is_active',1)
                        ->where('users_lead_identity.is_assign',1)
                        ->where('users_lead_identity.user_id',$userID)
                        ->get()
                        ->result_array();

        foreach($result as $data){
            $retVal[$data['lead_identity']] = $data['lead_identity'];
        }

        return $retVal;
    }

    public function lead_iden_activator(){
        $agentID = $this->session->userdata('agentID');
        $post = array_merge($this->input->post(),array('user_id'=>$agentID));
        $this->db->on_duplicate('users_lead_identity',$post);
    }
	
	public function check_lead_identity(){
		$post = $this->input->post();
		
		$result = $this->db->select('count(*) as ctr')
				->from('leads_details')
				->where('lead_identity',$post['li'])
				->get()
				->result_array();
		
		if($result[0]['ctr'] > 0)
			echo  1; //existing then alert user this is not allowed!
		else
			echo  0;
	}
	
	public function li_activator(){
		$post = $this->input->post();
		
		$this->db->update('leads_details',array('is_active'=>$post['is_active']),array('id'=>$post['id']));
	}
}