<?php 
$path = BASEPATH .'../application/third_party/php/pear';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

$_SERVER['DOCUMENT_ROOT'] .= '/template';

include_once($_SERVER['DOCUMENT_ROOT']. '/application/third_party/Spreadsheet/Excel/Writer.php');
include_once($_SERVER['DOCUMENT_ROOT']. '/application/third_party/Spreadsheet/Excel/Reader/reader.php');
//include_once('Spreadsheet/Excel/Writer.php');
//include_once('Spreadsheet/Excel/Reader/reader.php');
Class Main extends Auth_Controller  { 
	var $data = array();
	//use `Auth_Controller` if you want the page to be validated if the user is logged in or not, if you want to disable this then use `CI_Controller` instead
	public  function __construct(){
		parent::__construct();
		$this->load->model('main_model');
		$this->load->helper('url');
		$this->load->library('session');  
		$this->load->helper(array('form', 'url'));  
		$this->has_permission(178);
		$this->data = $this->get_privs();
		/**load your own library
			or add in application/config/autoload/ under libraries
		**/
		//$this->load->library('MY_auth_lib');
	} 
	
	public function index(){
		$this->load->helper('form');
		
		//$this->main_model->email(array('tests'),'atuyay@teleperformance.ph');
		
		// call fx to all pages add code in application/config/autoload.php, 
		// then add a file under application/helpers/xxxx_helper.php 
		//echo testHelper("d'dasd\das'dasf"); 
		
		//Calling your own library
		//echo $this->my_auth_lib->callMeNow2() 
		$this->set_header_data(PROJECT_NAME);
			
		$dataIndex['session'] = $this->session->all_userdata();
		$dataIndex['privs'] = $this->data;
		
		$this->load->view('main/index',$dataIndex);
		
		$this->load->view('templates/footer'); 	
	}
	 
	public function search_contact($all=1){ 
		$data['privs']= $this->data;
		
		$user_type = $this->session->userdata('user_type');
		$data['user_type'] = $user_type;
		if(ENABLE_AVAILABLE_LEADS || $user_type == ADMIN_CODE){
			//May 21,2015 disable the AVAILABLE LEADS  only LOCK,POPUP and CALLBACK will be availbale and also the get new random record button
			$data['contacts'] = $this->main_model->search_contact_list($all);  
		}else{
			//Else set empty array
			$data['contacts'] = array();
		}
		
		$data['restricted'] = $this->getLookup('restrict_tag',1);
		$data['cr'] = $this->getLookup('callresult',1);
		$data['subCr'] = ($this->getLookup('NI',1)+$this->getLookup('CB',1));
		$data['withSubCR'] = array('NI','CB');
        $data['ag_type'] = $this->getLookup('AG_TYPE',1);
		$this->load->view('main/contacts',$data); 
	}
	
	public function locked(){
		//get user locked record
		$data['privs'] = $this->data;
		$data['lockedRec'] = $this->main_model->get_locked_record();
		$this->load->view('main/locked',$data);  
	}
	
	public function popout()
	{ 
		$data['privs'] = $this->data;
		$data['popoutRec'] = $this->main_model->get_popout_records();
		$data['cr'] = $this->getLookup('callresult',1,0);
		$data['subCr'] = ($this->getLookup('NI',1)+$this->getLookup('CB',1));
		$data['withSubCR'] = array('NI','CB');
		$this->load->view('main/popout',$data); 
	}
	
	public function callback(){
		//get user callback record 
		$data['privs'] = $this->data;
		$data['callback'] = $this->main_model->get_callback_record();
		//2023-06-24 get the past CB counter
		$data['pastCBCtr'] = $this->main_model->getPastCBCTR();
		
		$this->load->view('main/callback',$data);  
	}
	
	public function pop($id)
	{
		$data['details'] = $this->main_model->get_record_by_id($id);
		$data['users'] = $this->get_users_grp_by_id(1);
		$data['id'] = $id;
		$this->load->view('main/pop',$data);
	}
	
	//action =1 do pop, 0 then cancel pop
	public function dopop($id,$action=1)
	{
		$this->main_model->dopop($id,$action);
	}
	
	public function get_new_record()
	{
		$id = $this->main_model->get_rnd_record();
		$result = 0;
		$action = 0; // set to edit
		if(!is_numeric($id) || $id == 'C'){
			$data['errMsg'] = get_err_msgs($id);
			$data['detail'] = $result;
			$data['action'] = $action; 
			$this->load->view('main/edit',$data); 
		}else{ 
			//as of May 23, 2016 re-load the edit function 
			$this->edit($id);
		}
		
	}
	
	//$action = 0 edit; 1 = view onely ;
	public function edit($id,$action=0){
		$this->load->helper('form');
		
		$result = $this->main_model->get_details_byID($id,$action);
		
		if(!is_array($result))
			$data['errMsg'] = get_err_msgs($result);
		 
//		$data['script'] = $this->get_script();
		$data['callresult'] = $this->getLookup('callresult',1,0);
		$data['ni'] = $this->getLookup('NI',1);
		$data['gender'] = $this->getLookup('gender',1);
		$data['cardtypes'] = $this->getLookup('card_types',1);
		$data['homeOwnership'] = $this->getLookup('ownership',1); 
		$data['educAttain'] = $this->getLookup('education_attainment',1); 
		$data['yesno'] = $this->getLookup('yesno',1); 
		$data['locations'] = $this->getLookup('locations',1); 
		$data['carOwnership'] = $this->getLookup('car_ownership',1); 
		$data['spEmployment'] = $this->getLookup('employment',1);
        $data['billingaddress'] = $this->getLookup('billingaddress',1);
		$data['nationality'] = $this->getLookup('nationality',1);
		$data['card_request'] = $this->getLookup('CardRequest',1);
        $data['ag_type'] = $this->getLookup('AG_TYPE',1);
        $data['displayFields'] = $this->get_displayed_fields();
		$data['sof'] = $this->getLookup('sof',1); 

        $data['detail'] = $result;
		$data['action'] = $action;
		$data['user_type'] = $this->session->userdata('user_type');
        $this->load->view('script/init');
        $this->load->view('main/edit',$data);
	}
	
	public function get_card_details($id,$action)
	{
		$data['cardDetails'] = $this->main_model->get_card_details($id);
		$data['id'] = $id;
		$data['action'] = $action;
		$this->load->view('main/carddetails',$data);
	}
	
	public function add_card($id,$cardID=0)
	{
		$data['id'] = $id;
		$data['cardID'] = $cardID; 
		$data['cardDetails'] = $this->main_model->get_card_details_by_ID($cardID);
		$this->load->view('main/add_card',$data); 
	}

    public function delete_card($cardID){
        $this->db->delete('card_details',array('id'=>$cardID));
    }
	
	public function save_card($id,$cardID=0)
	{
		$this->main_model->save_card($id,$cardID);
	}
	
	public function get_supple($id,$action)
	{
		$data['suppleDetails'] = $this->main_model->get_supple($id);
		$data['id'] = $id;
		$data['action'] = $action;
		$this->load->view('main/supplementary',$data);
	}
	
	public function add_supple($id,$suppleID=0)
	{
		
		$data['relationship'] = $this->getLookup('relationship',1);
		$data['employment'] = $this->getLookup('employment',1);
		$data['gender'] = $this->getLookup('gender',1);
		$data['id'] = $id;
		$data['suppleID'] = $suppleID;  
		$data['detail'] = $this->main_model->get_supple_details_by_ID($suppleID);
		$this->load->view('main/add_supple',$data); 
	}

    public function delete_supple($suppleID){
        $this->db->delete('supplementary',array('id'=>$suppleID));
    }
	
	public function save_supple($id,$suppleID=0)
	{
		$this->main_model->save_supple($id,$suppleID);
	}
	
	public function get_history($id,$is_rehashed = 0){
		$data['user_type'] = $this->session->userdata('user_type'); //05072016
		$data['users'] = $this->get_users_grp_by_id();
		
		//as of 2021-07-28 if the records is tagged as rehashed then dont displayed the history logs
		if($is_rehashed){
			$history_logs = array();
		}else{
			$history_logs = $this->main_model->get_history($id);
		}
		
		$data['history'] = $history_logs;
		$data['cr'] = $this->getLookup('callresult',1);
		$data['subCr'] = ($this->getLookup('NI',1)+$this->getLookup('CB',1));
		$data['withSubCR'] = array('NI','CB');
        $data['ag_type'] = $this->getLookup('AG_TYPE',1);
		$this->load->view('main/history',$data);
	}
	
	public function get_sub_callresult($lu_cat){
		echo json_encode($this->getLookup($lu_cat,1,0));
	}
	
	public function add(){
		$data['users'] = $this->get_users_grp_by_id();
		$data['gender'] = $this->getLookup('gender',1);
		$data['leadDetails'] = $this->get_leads_details(); //get available lead identity
		$this->load->view('main/add',$data);
	}
	
	public function save($id=0){
		$this->main_model->save($id);
	}
	
	public function check_lead_identity(){
		$this->main_model->check_lead_identity();
	}	
	
	public function do_upload($contactID){
		$config['upload_path'] = './uploads/'; 
		$config['allowed_types'] = '*'; //all types are allowed
		$this->load->library('upload', $config);
		
		if(!$this->upload->do_upload()){
			$data['error'] = $this->upload->display_errors();  
		}
			
		
		
		if(isset($data['error']))
			echo $data['error'];
		else{
			$this->main_model->save_attachments($contactID);
		}
		
		echo "Success";		
	}
	 
	
	
	public function batch_upload(){
		$pdata = $this->input->post();
		if(!isset($pdata['assign_month'])){
			$pdata['assign_month']  = date('Y-m-d');
		}
		$this->set_header_data('','Batch Upload');
		$this->load->view('main/batch_upload',$pdata);
		$this->load->view('templates/footer');
	}
	
	public function batch_lead_activator(){
		$this->set_header_data('','Lead Activator');
		$this->load->view('main/batch_lead_activator');
		$this->load->view('templates/footer');
	}
	
	public function do_batch_upload($type=1){
		$data = array();
		$config['upload_path'] = './uploads/database/'; 
		$config['allowed_types'] = '*'; //'*' = all types are allowed
		$this->load->library('upload', $config);
		
		if(!$this->upload->do_upload()){
			$data['error'] = $this->upload->display_errors();  
		}
		
		if(isset($data['error']))
			echo $data['error'];
		else{
			
			if($type == 1) //batch upload in db
				$return = $this->main_model->do_batch_upload();
			elseif($type == 2) //batch disable to db
				$return = $this->main_model->do_batch_disable();
				
			//invalid file type
			if($return == 2)
				echo "Invalid File TYPE!";
			if($return == 3)
				echo "Invalid column name! <br> Follow the correct column format.";
			elseif($return == 1){
				echo "Success!";
				echo '###'.$this->main_model->log_filename;
			}
			
		}
		
	} 
	
	public function leads()
	{  
		$this->set_header_data('','Lead Allocation'); 
		$data['users'] = $this->get_users();
		$this->load->view('main/leads',$data);
		$this->load->view('templates/footer');
	}
	
	public function manage($userid=null,$target_li='')
	{
		$this->set_header_data('','Lead Allocation'); 
		
		if($userid == null)
			$userid = $this->session->userdata('agentID');
			
		$this->session->set_userdata('agentID', $userid);
		$data['cr_lookup'] = $this->getLookup('callresult',1);
		$data['restricted'] = $this->getLookup('restrict_tag',1);
		$data['leadDetails'] = $this->get_leads_details(); //get available lead identity
		$this->main_model->leadsDetails = $data['leadDetails'];
		$data['allocLeads'] = $this->main_model->get_allocated_leads($userid); //get assigned leads
		$data['unAllocLeads'] = $this->main_model->get_unallocated_leads(); //get unassigned leads
		$data['unAllocVirginLeads'] = $this->main_model->get_unallocated_virgin_leads(); //get unassigned virgin leads
		$data['agent'] = $this->get_user_grp_by_id($userid);
		$data['leads_assigned'] = $this->main_model->get_lead_identity_assigned($userid);
		$data['userid'] = $userid;
		$data['target_li'] = $target_li;
		
		//start of 2017-07-22 [max_touch]
		$max_touch = $this->getLookup('max_touch',0); 
		$data['max_touch'] =  $max_touch[0]['lu_code'];
		//end of 2017-07-22 [max_touch]
		
		$this->load->view('main/manage_leads',$data);
		$this->load->view('templates/footer');
	}
	
	public function allocate_leads(){
		$this->main_model->allocate_leads();
		#$this->manage();
	}

    public function lead_iden_activator(){
        $this->main_model->lead_iden_activator();
    }
	
	public function manage_lead_identity($all=0){
		$this->set_header_data('','Manage Lead Identity'); 
		$data['leadDetails'] = $this->get_leads_details($all); //get available lead identity
		$data['isAll'] = $all;
		$this->load->view('main/manage_lead_identity',$data);
		$this->load->view('templates/footer');
	}
	
	public function li_activator(){
		$this->main_model->li_activator();
	}
	
	public function single_activator($recordID,$is_active){
		echo $recordID . ' -- ' .$is_active;
		$this->db->update('contact_list',array('is_active'=>$is_active),array('id'=>$recordID));
	}
	
	
	public function dnc_acct_nbr(){
		$this->set_header_data('','Upload Do Not Call List');
		$this->load->view('main/dnc');
		$this->load->view('templates/footer');
	}
	
	public function do_up_dnc_acct_nbr(){
		$this->do_dnc_upload('via_acct_nbr.csv');
	}
	
	
	public function do_up_dnc_number(){
		$this->do_dnc_upload('via_numbers.csv');
	}
	
	public function do_dnc_upload($filename = 'via_acct_nbr.csv'){
		
		$data = array();
		$config['file_name'] = $filename;
		$config['overwrite'] = true;
		$config['upload_path'] = './uploads/dnc/'; 
		$config['allowed_types'] = 'csv'; //'*' = all types are allowed
		$this->load->library('upload', $config);
		
		if(!$this->upload->do_upload('userfile')){
			$data['error'] = $this->upload->display_errors();  
		}
		
		if(isset($data['error']))
			echo $data['error'];
		else{
			echo "Success!";
		}
	}
	
	public function dl_dnc($filename){
		$abs_path = ('uploads/dnc/'.$filename); // Read the file's contents
		$this->doDownload($abs_path, $filename);
	}
	
	public function dl_log($filename){
		$abs_path = ('uploads/dedup/'.$filename.'.txt'); // Read the file's contents
		$mock_filename = $filename.'.txt'; //FOR NOW download it as CSV, and later change it to XLSX
		$this->doDownload($abs_path, $mock_filename);

	}
	
	public function doDownload($abs_path, $filename){
		
		// Build the headers to push out the file properly.
		header('Pragma: public');     // required
		header('Expires: 0');         // no cache
		header('Cache-Control: private',false);
		header('Content-Disposition: attachment; filename="'.basename($filename).'"');  // Add the file name
		header('Content-Length: '.filesize($abs_path)); // provide file size
		header('Connection: close');
		readfile($abs_path); // push it out
		exit();
	}
	
}
?>