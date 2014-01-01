<?php
// jimport( 'joomla.html.parameter' );
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

jimport( 'joomla.filesystem.folder');

if(!defined('DS')){
    define('DS',DIRECTORY_SEPARATOR);
}

class JFormFieldPtypesubject extends JFormField
{
	public $type = 'Ptypesubject';
	
	protected function getInput()
	{
		$path = JPATH_ROOT. DS.'components'.DS.'com_xipt';
		if(JFolder::exists($path)){
			//Check JSPT unhook
			$isjspt=$this->_isPluginInstalledAndEnabled();
			if($isjspt){
					$db = JFactory::getDBO();
					$db->setQuery("SELECT `params` FROM `#__extensions` WHERE `folder`='user' AND `element`='welcomemessage' ");
					$params = $db->loadResult();
					
					$pSubObject =  new JRegistry($params);
			
					$fieldsHtml = $this->getFieldsHtml($pSubObject,$this->fieldname, $this->value, $this->element, $this->name);
			}
			else
				$fieldsHtml = "<label>". JText::_('Profiletype is not installed.')."</label>";
		}
		else
			$fieldsHtml = "<label>". JText::_('Profiletype is not installed.')."</label>";
		
		return $fieldsHtml;
	}
	
	function _isPluginInstalledAndEnabled()
	{
		$db = JFactory::getDBO();
		$query = "SELECT * FROM `#__extensions` WHERE `element` = 'xipt_community' ";
			
		$db->setQuery($query);
	    $communityPlugin = $db->loadObject();
			if(!$communityPlugin || $communityPlugin->enabled == 0)
			return false;
				
		$query = "SELECT * FROM `#__extensions` WHERE `element` = 'xipt_system' ";
			
		$db->setQuery($query);
	    $systemPlugin = $db->loadObject();
		if(!$systemPlugin || $systemPlugin->enabled == 0)
			return false;
			
		return true;
	}
	
	function getJomsocialProfileTypes($filter = '',$join='AND')
	{
		$allField=null;
		
		if($allField == null)
			$db	=& JFactory::getDBO();
				
		$sql = "SELECT * FROM " . $db->quoteName('#__xipt_profiletypes');
		$db->setQuery($sql);
		$fields = $db->loadObjectList();
		return $fields;
	}
	
	function getFieldsHtml($values,$name, $value, &$node, $control_name)
	{
		$fields = self::getJomsocialProfileTypes();
		$html = '';
		if(empty($fields)) {
			$html = "<label>".JText::_('There are no parameters for this item')."</label>";
			return $html;
		}
				
		foreach($fields as $f) {
			    $html .= "<div class='paramlist admintable'>";
			    $html .= "<label>".$f->name;
			    $html .= "</label>";
			    if(!$value)
			    	$html .= "<textarea rows='1' cols='40' id='".$control_name."[".$f->id."]' name='".$control_name."[".$f->id."]'>{$value}</textarea></div>";
			    else
					$html .= "<textarea rows='1' cols='40' id='".$control_name."[".$f->id."]' name='".$control_name."[".$f->id."]'>{$value[$f->id]}</textarea></div>";
		}
		
		return $html;
	}
}
