<?php
class Security_model extends CI_Model {
	public function __construct()
	{
		$this->load->database(); 		
	}
	
	function login(){

		$pdata = $this->input->post();
		
		$query = $this->db->select('*')
				->from('users')
				->where('user_name',$pdata['user_name'])
				->where("user_password","md5('{$pdata['user_password']}')",false)
				->where('is_active',1)
				->get();
		
		
		if($query->num_rows() > 0){
			
			//HEALTH DEC RELATED
			if(!$this->checkIsAllowDueToHealthDec($pdata['user_name'])){
				return 99; //FOR HEALTH DEC CHECKLIST
			}
			
			//set user privs
			$this->set_user_privileges($pdata['user_name']);
			
			$newdata = array(
						'username'  => $pdata['user_name'], 
						'logged_in' => TRUE
					);
			$this->session->set_userdata($newdata);
			
			$result = $query->result_array();
			
			$this->session->set_userdata(array('full_name'=>$result[0]['firstname'] . ' ' . $result[0]['lastname'],
												'user_mobile'=>$result[0]['mobile_no'],
                                                'user_type'=>$result[0]['user_type']
                                        ));
			
			return 1;
		}else
			return 0;
	}
	
	//HEALTH DEC RELATED
	function checkIsAllowDueToHealthDec($userID){
		
		$config['hostname'] = 'localhost';
		$config['username'] = 'root';
		$config['password'] = '';
		$config['database'] = 'covid_health_check';
		$config['dbdriver'] = "mysql";
		$config['dbprefix'] = "";
		$config['pconnect'] = FALSE;
		$config['db_debug'] = TRUE;

        $newconn = $this->load->database($config,true);
		
		//2020-12-12 added users.alt_user_name to be used in identifying health_dec
		//This is to handle misspelled or double user name from the healthDec and to other system
		$row = $newconn->select('is_allow')
						->from('contact_list')
						->join('users','users.user_name = contact_list.created_by')
						->where("DATE(date_entered) = DATE(NOW())")
						->where("created_by = '{$userID}' OR users.alt_user_name = '{$userID}'")
						->get()
						->result_array();
		
		if($row){
			if($row[0]['is_allow']){
				return true;
			}
		}
		
		//SHOULD take health declaration
		return false;
		
	}
	
	function set_user_privileges($username)
	{
		$result = $this->db->select('*')
					->from('privilege')
					->where('user_id',$username)
					->where('is_active',1)
					->get()
					->result_array();
		
		$privs = array();	
		foreach($result as $r)
			$privs[$r['right_id']]= 1;
		
		$this->session->set_userdata(array('privs'=>$privs));
	}
	
	//$process = 1 timein ; 0 timeout
	public function do_time_proc($process){
		if(!$this->session->userdata('logged_in'))
			redirect('security/login');
		else{
			$this->load->database();
			$username = $this->session->userdata('username');
			$this->db->where("user_name",$username)->update('users',array('log_status'=>$process));
			
			
			$insertData = array('user_id'=>$username,
								'log_status'=>$process,
								'ip_address'=>$this->session->userdata('ip_address')
								);
								
			//log every time process
			$this->db->insert('login_trans_log',$insertData);
		}
	}

	function clock_details(){
		$username = $this->session->userdata('username');
		
		$result = $this->db->select('*')->from('login_trans_log')
				->where('user_id',$username)
				->order_by('time_stamp')
				->get()
				->result_array();
		
		return $result;
	}
	
	public function cp_save()
	{
		$username = $this->session->userdata('username');
		$pdata = $this->input->post();
		$oldPw = md5(trim($pdata['old_password']));
		$newPw = (trim($pdata['password']));
		$repPw = (trim($pdata['rep_password'])); //repeat pw
		
		$result = $this->db->select('user_password')->from('users')
				->where('user_name',$username)
				->get()->result_array();
				
		
		if($result[0]['user_password'] != $oldPw)
			return 3;
		elseif($newPw != $repPw)
			return 4;
		elseif(strlen($newPw) <5)
			return 5;
		else{
			$this->db->where('user_name',$username)->update('users',array('user_password'=>md5($newPw)));
			return 1;
		}
	}
}
?>
