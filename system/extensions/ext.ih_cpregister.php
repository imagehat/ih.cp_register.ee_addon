<?php
/**
 * CP Register New Member Extension
 *
 * When adding a new member via the control panel this extension
 * adds the option of sending a welcome email to the new member
 * containing their login information (email template is customizable 
 * in the extension settings), as well as the ability to optionally 
 * enter custom profile field data while registering them right from 
 * the new member register form.
 *
 * @package     CpRegister
 * @version     1.0.0
 * @author      Mike Kroll <http://imagehat.com>
 * @copyright   Copyright (c) 2008-2009 Mike Kroll
 * @license     http://creativecommons.org/licenses/by-sa/3.0/ Creative Commons Attribution-Share Alike 3.0 Unported
 **/
  
if ( ! defined('EXT')) 
{
    exit('Invalid file request'); 
}


class Ih_cpregister {
	
	var $settings		= array();
	
	var $name			= 'CP Register Extended';
	var $version		= '1.0';
	var $description	= 'Adds options to the new member form in the control panel.';
	var $settings_exist	= 'y';
	var $docs_url		= '';

	
    /**
	 * Constructor
	 **/
	function Ih_cpregister($settings='')
	{
		$this->settings = $settings;
	}
	// END
	
	
	/**
	 * Modify Register Form
	 *
	 * @hook show_full_control_panel_end
	 **/
	function modify_register_form($out)
	{
		global $EXT, $IN, $DSP, $LANG, $DB;
		
		$LANG->fetch_language_file('ih_cpregister');
		
		// Play nice with others	
		if($EXT->last_call !== false)
		{
			$out = $EXT->last_call;
		}
		
		// Only modify register new member form
		if ($IN->GBL('P') != 'member_reg_form')
		{
		    return $out;
		} 
		
		$r = '';
        
				 
        /** -----------------------------------
		/**  Add custom member fields
		/** -----------------------------------*/
		
        if ($this->settings['show_custom_fields'] == 'yes')
        {
            $r .= $DSP->qdiv('tableHeading', $LANG->line('profile_heading'));
            $r .= $DSP->div('box');
    		
    		$sql = "SELECT * FROM exp_member_fields ORDER BY m_field_order";
		
            $query = $DB->query($sql);
        
            if ($query->num_rows > 0)
            {
    			foreach ($query->result as $row)
    			{
    				$field_data = ( ! isset( $result->row['m_field_id_'.$row['m_field_id']] )) ? '' : $result->row['m_field_id_'.$row['m_field_id']];
										 
    				$width = '300px';
																			  
    				$required  = ($row['m_field_required'] == 'n') ? '' : $DSP->required().NBS;     
			
    				// Textarea field types			
    				if ($row['m_field_type'] == 'textarea')
    				{               
    					$rows = ( ! isset($row['m_field_ta_rows'])) ? '10' : $row['m_field_ta_rows'];
				
    					$r .= $DSP->qdiv('itemWrapperTop', $DSP->qdiv('defaultBold', $required.$row['m_field_label']).$DSP->qdiv('default', $row['m_field_description']).$DSP->input_textarea('m_field_id_'.$row['m_field_id'], '', $rows, 'textarea', $width));
    				}
    				else
    				{        
    					// Text input fields					
    					if ($row['m_field_type'] == 'text')
    					{   
    						$r .= $DSP->qdiv('itemWrapperTop', $DSP->qdiv('defaultBold', $required.$row['m_field_label']).$DSP->qdiv('default', $row['m_field_description']).$DSP->input_text('m_field_id_'.$row['m_field_id'], '', '20', '100', 'input', $width));
    					}            
	
    					// Drop-down lists					
    					elseif ($row['m_field_type'] == 'select')
    					{                          
    						$d = $DSP->input_select_header('m_field_id_'.$row['m_field_id']);
										
    						foreach (explode("\n", trim($row['m_field_list_items'])) as $v)
    						{   
    							$v = trim($v);
    							$selected = '';
    							$d .= $DSP->input_select_option($v, $v, $selected);
    						}
						
    						$d .= $DSP->input_select_footer();
						
    						$r .= $DSP->qdiv('itemWrapperTop', $DSP->qdiv('defaultBold', $required.$row['m_field_label']).$DSP->qdiv('default', $row['m_field_description']).$DSP->qdiv('default', $d));
    					}
    				}
    			}        
    		}
    		
    		$r .= $DSP->div_c();
	    }
	    
	    /** -----------------------------------
		/**  Add notification email option
		/** -----------------------------------*/
        
        $r .= $DSP->qdiv('tableHeading', $LANG->line('notification_heading'));
        $r .= $DSP->div('box');
        
        $r .= $DSP->qdiv('itemWrapperTop', $DSP->qdiv('defaultBold', $LANG->line('send_notifications').NBS.$DSP->input_checkbox('ih_notify', 'y', 1)) );
        
        $r .= $DSP->div_c();
		
		 
		// Find end of form elements div and add new fields below existing div
        preg_match('/name=[\'"]group_id[\'"].*?<\/div>/si', $out, $form);
        $r = str_replace('</div>', '</div>'.$r, $form[0]);
		$out = str_replace($form[0], $r, $out);
		
		return $out;
				
	}
	// END
	
	/**
	 * Process additional options when form is submitted
     *
	 * @hook cp_members_member_create
	 **/
	function process_member_creation($member_id, $data)
	{
		global $EXT, $IN, $PREFS, $FNS, $REGX, $DB;
		
		// Play nice with others	
		if($EXT->last_call !== false)
		{
			$data = $EXT->last_call;
		}
        
        /** -----------------------------------
		/**  Process notification email
		/** -----------------------------------*/
		
        if ($IN->GBL('ih_notify', 'POST') == 'y')
        {
            if ( ! class_exists('EEmail'))
    		{
    			require PATH_CORE.'core.email'.EXT;
    		}

    		$email = new EEmail;

            // Swap variables
		    $swap = array(
			    'screen_name' => $data['screen_name'],
		        'username'    => $data['username'],
			    'email'       => $data['email'],
			    'password'    => stripslashes($_POST['password']),
			    'site_name'	  => stripslashes($PREFS->ini('site_name')),
				'site_url'	  => $PREFS->ini('site_url'),
				'site_index'  => $FNS->fetch_site_index(),
				'site_name'   => stripslashes($PREFS->ini('site_name'))
			);

			//  Send email
			$email->initialize();
			$email->wordwrap = true;
			$email->from($PREFS->ini('webmaster_email'), $PREFS->ini('webmaster_name'));	
			$email->to($data['email']); 
			$email->subject($REGX->entities_to_ascii($FNS->var_swap($this->settings['notification_subject'], $swap)));	
			$email->message($REGX->entities_to_ascii($FNS->var_swap($this->settings['notification_template'], $swap)));		
			$email->Send();

        }
        
        /** -----------------------------------
		/**  Process custom member fields
		/** -----------------------------------*/
        
        // Ignore non-custom member fields
        $fields = array('username', 'password', 'password_confirm', 'screen_name', 'email', 'group_id', 'ih_notify');
        
        foreach ($fields as $val)
        {
        	unset($_POST[$val]);
        }
        
        // Update
        if (count($_POST) > 0)
        {        
            $DB->query($DB->update_string('exp_member_data', $_POST, "member_id = '".$DB->escape_str($member_id)."'"));
        }
        
		return $data;
		
	}
	// END	
	
	   
	/**
     * Activate Extension
     **/
	function activate_extension()
	{
		global $DB;
		
		$default_settings = $this->default_settings();
		
		$DB->query(
			$DB->insert_string('exp_extensions',
				array('extension_id' => '',
					  'class'        => get_class($this),
					  'method'       => 'process_member_creation',
					  'hook'         => 'cp_members_member_create',
					  'settings'     => serialize($default_settings),
					  'priority'     => 10,
					  'version'      => $this->version,
					  'enabled'      => 'y'
				)
			)
		);
		
		$DB->query(
		    $DB->insert_string('exp_extensions', 
    		    array('extension_id' =>'', 
        		    'class'          => get_class($this), 
        		    'method'         => 'modify_register_form', 
        		    'hook'           => 'show_full_control_panel_end', 
        		    'settings'       => serialize($default_settings), 
        		    'priority'       => 10, 
        		    'version'        => $this->version, 
        		    'enabled'        =>'y'
    		    )
		    )
		);
		
	}
	// END
	
	
    /**
     * Update Extension
     **/
	function update_extension($current='')
	{
	    global $DB;

	    if ($current == '' OR $current == $this->version)
	    {
	        return FALSE;
	    }

	    if ($current < '1.0.0')
	    {
	        // Update to next version
	    }

        // Update version
	    $SQL = "UPDATE exp_extensions SET version = '".$DB->escape_str($this->version)."' WHERE class = '".get_class($this)."'";
	    
	    // Run update queries
	    foreach ($sql as $query)
		{
			$DB->query($query);
		}
	}
	// END
	
	
    /**
     * Disable Extension
     **/
	function disable_extension()
	{
	    global $DB;

	    $DB->query("DELETE FROM exp_extensions WHERE class = '".get_class($this)."'");
	}
	// END
	
    /**
     * Extension settings
     * @return array
     **/
    function settings()
    {
    	global $PREFS;

    	  $settings = array();

          $settings['show_custom_fields']   = array('r', array('yes' => "yes", 'no' => "no"), 'no');
          $settings['notification_subject'] = '';	
    	  $settings['notification_template'] = array('t', '');

    	return $settings;
    }
    // END


    	/**
     * Default Extension settings
     * @return array
     **/
    function default_settings()
    {
    	global $DB, $PREFS;

    	$default_settings = array();

    	$default_settings['show_custom_fields']   = 'no';
    	$default_settings['notification_subject'] = 'Welcome to '.$PREFS->ini('site_name');	
    	$default_settings['notification_template'] = <<<EOF
Dear {screen_name},

Welcome to {site_name}! Below is your new account information:

username: {username}
password: {password}

Thank You!

{site_name}
{site_url}

EOF;

    	return $default_settings;
    }
    // END   


}
// END Class

/* End of file ext.ih_simple_commerce_purchases.php */
/* Location: ./system/extensions/ext.ih_simple_commerce_purchases.php */
