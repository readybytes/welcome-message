<?php
defined('_JEXEC') or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );
jimport( 'joomla.html.parameter' );
define('ONSCREENMESSAGE',1);
define('PERSONALMESSAGE',1);
define('EMAIL',1);
$version = new JVersion();
//define('WLCM_JOOMLA_15',($version->RELEASE === '1.5'));

if(!defined('DS')){
    define('DS',DIRECTORY_SEPARATOR);
}
class plgUserWelcomeMessage extends JPlugin
{
	
	function _construct(& $subject, $config) 
	{
		parent::__construct($subject, $config);
	}
	
	function onLoginUser($user,$option)
	{	
		$this->afterLogin($user, $option);
	}
	
	//in J1.7 this event is called
	function onUserLogin($user,$option)
	{	
		$this->afterLogin($user, $option);
	}
	
	function editMessage($userid,$message,$customurl)
	{
		$mainframe = JFactory::getApplication();
		$name 	   = $lastvisitdate = JFactory::getUser($userid)->name;
		$username  = $lastvisitdate = JFactory::getUser($userid)->username;
		$siteurl   = JURI::base();
		$sitename  = $mainframe->getCfg( 'sitename' );
		
		$pattern = array("/\[\[NAME\]\]/","/\[\[USERNAME\]\]/","/\[\[SITEURL\]\]/","/\[\[SITENAME\]\]/","/\[\[CUSTOMURL\]\]/");
		$replace = array($name,$username,$siteurl,$sitename,$customurl);
		$message = preg_replace($pattern,$replace,$message);
		return $message;
	}
	
	function sendMessage($userid,$subject,$message,$emessage,$permessage,$email)
	{
		$mainframe = JFactory::getApplication();
		if($emessage == ONSCREENMESSAGE){
			$mainframe->enqueueMessage($message);
		}
		if($permessage == PERSONALMESSAGE){
			
			$jspath = JPATH_ROOT.DS.'components'.DS.'com_community';
			include_once($jspath.DS.'libraries'.DS.'core.php');
			
			$inboxModel = CFactory::getModel( 'inbox' );
			
			$postvars = array();
			$postvars['to'] 	 = $userid;
			$postvars['subject'] = $subject;
			$postvars['body']	 = $message;
			
			$msgid = $this->sendJomSocialMessage ($postvars);
			
			CFactory::load( 'libraries' , 'userpoints' );	
            //reload user to clean cache
            CFactory::loadUsers(array("$userid"));	
			CUserPoints::assignPoint('inbox.message.send');
		}
		if($email == EMAIL){
			$mailer	= JFactory::getMailer();
			$email  = JFactory::getUser($userid)->email;
			$mailer->addRecipient($email);
			$mailer->setSubject($subject);
			$mailer->setBody($message);					
			$mailer->send();
		}
			
	}

	function sendJomsocialMessage($vars)
	{	    
		$db    = JFactory::getDBO();
		$my	   = JFactory::getUser();
		$admin = $this->getAdmin();
		$date  = JFactory::getDate(); //get the time without any offset!
		$cDate = $date->toSQL(); 

		$obj   = new stdClass();
		$obj->id        = null;
		$obj->from 		= $admin;
		$obj->posted_on = $date->toSQL();
		$obj->from_name	= JFactory::getUser($admin)->name;
		$obj->subject	= $vars['subject'];
		$obj->body		= $vars['body'];
		
		$db->insertObject('#__community_msg', $obj, 'id');
		
		// Update the parent
		$obj->parent = $obj->id;
		$db->updateObject('#__community_msg', $obj, 'id');
		
		$this->addReceipient($obj, $vars['to']);    
		
		return $obj->id;
	}
	
	function addReceipient($msgObj, $recepientId)
	{
		$db = JFactory::getDBO();
			        
		$recepient = new stdClass();
		$recepient->msg_id     = $msgObj->id;
		$recepient->msg_parent = $msgObj->parent;
		$recepient->msg_from   = $msgObj->from;
		$recepient->to		   = $recepientId;		
		$db->insertObject('#__community_msg_recepient', $recepient);
		
		if($db->getErrorNum()) {
		     JError::raiseError( 500, $db->stderr());
	    }
	}
	
	function getAdmin()
	{
		$mainframe = JFactory::getApplication();
		$db = JFactory::getDBO();
		$query = 'SELECT id FROM #__users' . ' '
						. 'WHERE `email`="' .$mainframe->getCfg( 'mailfrom' ).'"';

		$db->setQuery( $query );
		if($db->getErrorNum()) {
			JError::raiseError( 500, $db->stderr());
		}
		$result = $db->loadResult();
		return $result;
	}
	
	function _isPluginInstalledAndEnabled()
	{
		$db = JFactory::getDBO();
		if(WLCM_JOOMLA_15){
			$query = "SELECT * FROM `#__plugins` WHERE `element` = 'xipt_community' ";
			$db->setQuery($query);
	    	$communityPlugin = $db->loadObject();
			if(!$communityPlugin || $communityPlugin->published == 0)
				return false;
		}
		else{
			$query = "SELECT * FROM `#__extensions` WHERE `element` = 'xipt_community' ";
			$db->setQuery($query);
	    	$communityPlugin = $db->loadObject();
			if(!$communityPlugin || $communityPlugin->enabled == 0)
			return false;
		}	
		
				
		if(WLCM_JOOMLA_15){	
			$query= "SELECT * FROM `#__plugins` WHERE `element` = 'xipt_system' ";
			$db->setQuery($query);
		    $systemPlugin = $db->loadObject();
			if(!$systemPlugin || $systemPlugin->published == 0)
				return false;
		}
		else{
			$query = "SELECT * FROM `#__extensions` WHERE `element` = 'xipt_system' ";
			$db->setQuery($query);
		    $systemPlugin = $db->loadObject();
			if(!$systemPlugin || $systemPlugin->enabled == 0)
				return false;
		}
		return true;
	}
	
	function afterLogin($user, $option)
	{	
		$username  = JRequest::getVar('username');
		
		$mainframe = JFactory::getApplication();
		$userid    = JUserHelper::getUserId($username);
		
		// For Guest, do nothing and just return, let the joomla handle it
		if(!$userid) 
			return;
			
		$plugin 	   = JPluginHelper::getPlugin('user', 'welcomemessage');
		
 		$params 	   = json_decode($plugin->params);
 	
 		$lastvisitdate = JFactory::getUser($userid)->lastvisitDate;
		$block		   = JFactory::getUser($userid)->block;
		
 		//Check for first login
		if($lastvisitdate == "0000-00-00 00:00:00" && $block==0){
			$path = JPATH_ROOT. DS.'components'.DS.'com_xipt';
			if(JFolder::exists($path)){
				//Check is XiPT unhook or not
				$result = $this->_isPluginInstalledAndEnabled();
			  	if($result){
					require_once (JPATH_ROOT. DS.'components'.DS.'com_xipt'.DS.'api.xipt.php');
					$pID = XiptAPI::getUserInfo($userid,'PROFILETYPE');	
					
						$ptsubject = $params->ptypesubject ? $params->ptypesubject: '';
						$subject	= $ptsubject->$pID;
						$ptmessage  = $params->ptypemessage ? $params->ptypemessage: '';
						$message	= $ptmessage->$pID;
					
	          	}
	          	else{
	          		$subject  = $params->get('subject',' ');
	          		$message  = $params->get('message',' ');
	          	}
			}
			else{
				$subject  = $params->subject? $params->subject: '';	
				$message  = $params->message? $params->message: '';
			}
				
			//$message.= $this->getMessage($userid);
			$emessage   = $params->EnqueueMessage;
			$permessage = $params->PersonalMessage;
			$email      = $params->Email;
			$customurl  = $params->customurl;
			$subject    = $this->editMessage($userid,$subject,$customurl);
			$message    = $this->editMessage($userid,$message,$customurl);
 			$this->sendMessage($userid,$subject,$message,$emessage,$permessage,$email);
		}	
	}
}

