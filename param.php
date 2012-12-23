<?php
defined('_JEXEC') or die('Restricted access');
/**
* Param: Virtuemart 2 customfield plugin
* Version: 1.0.0 (2012.04.23)
* Author: Usov Dima
* Copyright: Copyright (C) 2012 usovdm
* License GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
* http://myext.eu
**/

if (!class_exists('vmCustomPlugin')) require(JPATH_VM_PLUGINS.DS.'vmcustomplugin.php');

class plgVmCustomParam extends vmCustomPlugin {

	public static $_this = false;

	function __construct(& $subject, $config) {
		parent::__construct($subject, $config);

		$this->_tablepkey = 'id';
		$this->tableFields = array_keys($this->getTableSQLFields());
		$varsToPush = array(
			'n'=> array('', 'char'), // name
			's'=> array('', 'string'), // searchable?
			'l'=> array('', 'string'), // list?
			'ft'=> array('', 'string'), // field type
			't'=> array('', 'string'), // view type
			'm'=> array('', 'string'), // search method (AND/OR)
			'vd'=> array('', 'string'), // value default
		);
		$this->setConfigParameterable('custom_params',$varsToPush);
	}
	
	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Product Param Table');
	}

	function getTableSQLFields() {

		$SQLfields = array(
	    'id' => 'int(11) unsigned NOT NULL AUTO_INCREMENT',
	    'virtuemart_product_id' => 'int(11) UNSIGNED DEFAULT NULL',
	    'virtuemart_custom_id' => 'int(11) UNSIGNED DEFAULT NULL',
	    'param' => 'varchar(128) NOT NULL DEFAULT \'\' ',
	    'value' => 'varchar(1024) NOT NULL DEFAULT \'\' ',
	    'intvalue' => 'float(11) UNSIGNED DEFAULT \'-13692\'',
		'ordering' => 'int(11) UNSIGNED DEFAULT NULL',
		'published' => 'int(11) UNSIGNED DEFAULT NULL'
		);

		return $SQLfields;
	}

	public function plgVmSelectSearchableCustom(&$selectList,&$searchCustomValues,$virtuemart_custom_id)
	{
		return true;
	}
	
	
	public function plgVmAddToSearch(&$where,&$PluginJoinTables,$custom_id)
	{
		$custom_parent_ids = JRequest::getVar('cpi', array());
		$manufacturers = JRequest::getVar('mids',null);
		$categories = JRequest::getVar('cids',null);
		$price_left = JRequest::getVar('pl',null);
		$price_right = JRequest::getVar('pr',null);
		$stock = JRequest::getVar('s',false); // instock
		
		if($price_right != null || $price_left != null || $categories != null || $manufacturers != null || count($custom_parent_ids)>0){
			$go_search = true;
		}else{
			$go_search = false;
		}
		
		if ($go_search) {
			// $profiler = new JProfiler;
			$db = & JFactory::getDBO();
			$q_where = $q_join = $q_where_customfields = array();
			$q_having = '';
			/* ===== + Categories Table===== */
			if($categories != null){
				$categories = implode('","',$categories);
				if(!empty($categories)){
					$q_where[] = 'pc.`virtuemart_category_id` IN ("'.$categories.'")';
					$q_join[] = array('#__virtuemart_product_categories','pc');
					$q_where[] = 'c.`published` = "1"';
					$q_join[] = array('#__virtuemart_categories','c','pc.`virtuemart_category_id` = c.`virtuemart_category_id`'); // category publish
				}
			}
			/* ===== - Categories Table ===== */
			/* ===== + Manufacturers Table ===== */
			if($manufacturers!=null){
				$manufacturers = implode('","',$manufacturers);
				if(!empty($manufacturers)){
					$q_where[] = 'pm.`virtuemart_manufacturer_id` IN ("'.$manufacturers.'")';
				}
				$q_join[] = array('#__virtuemart_product_manufacturers','pm');
			}
			/* ===== - Manufacturers Table ===== */
			/* ===== + Price Table ===== */
			$discount = JRequest::getVar('d',false); // discount
			$price_where = false;
			if($price_left!=null || $price_right!=null){
				if($price_left!=null){
					if(!$discount){
						$q_where[] = 'pp.`product_price` >= "'.$price_left.'"';
					}else{
						$price_where = true;
					}
				}
				if($price_right!=null){
					if(!$discount){
						$q_where[] = 'pp.`product_price` <= "'.$price_right.'"';
					}else{
						$price_where = true;
					}
				}
				$q_join[] = array('#__virtuemart_product_prices','pp');
				if($discount){
					$q_join[] = array('#__virtuemart_calcs','pd','pd.`virtuemart_calc_id` = pp.`product_discount_id`'); // product discount
				}
			}
			/* ===== - Price Table ===== */
			/* ===== + Customfields plg table ===== */
			if(count($custom_parent_ids) > 0){
				foreach($custom_parent_ids as $v){
					if ($this->_name != $this->GetNameByCustomId($v)) return;
					$real_custom_id = $v;
					$where_values = $where_values_or = $where_values_and = array();
					$q = 'SELECT `custom_params` FROM `#__virtuemart_customs` WHERE `virtuemart_custom_id` = "'.$v.'"';
					$db->setQuery($q);
					$field = $db->loadObject();
					$this->parseCustomParams($field);
					if($custom_value = JRequest::getVar('cv'.$v, '')) {
						foreach($custom_value as $k=>$v){
							if(empty($v)){
								continue;
							}
							if($field->ft == 'int'){
								if($k === 'gt'){
									$where_values_and[] = 'param.`intvalue` >= "'.$db->getEscaped($v,true).'"';
								}elseif($k === 'lt'){
									$where_values_and[] = 'param.`intvalue` <= "'.$db->getEscaped($v,true).'"';
								}else{
									$where_values[] = 'param.`intvalue` = "'.$db->getEscaped($v,true).'"';
								}
							}else{
								$where_values[] = 'param.`value` LIKE "%|'.$db->getEscaped($v,true).'|%"';
							}
						}
						if(count($where_values_and) > 0){
							$where_values[] = implode(' AND ',$where_values_and);
						}
						if(count($where_values) == 0)
							continue;
						$q_where_customfields[] = '(param.`virtuemart_custom_id` = "'.$real_custom_id.'" AND ('.implode(' '.$field->m.' ',$where_values).'))';
					}
				}
			}
			if(count($q_where_customfields) > 0){
				$q_having = ' HAVING COUNT(param.`virtuemart_custom_id`) = '.count($q_where_customfields);
				$q_where[] = implode(' OR ',$q_where_customfields);
			}
			/* ===== - Customfields plg table ===== */
			if(count($q_where) < 1 && !$price_where)
				return true;
			
			/* ===== + Select =====*/
			$q  = 'SELECT p.`virtuemart_product_id`';
			if($discount && ($price_left!=null || $price_right!=null)){
				$q .= ',CASE pd.`calc_value_mathop`
							WHEN "+%" THEN pp.`product_price` + pp.`product_price` * pd.`calc_value` / 100
							WHEN "-%" THEN pp.`product_price` - pp.`product_price` * pd.`calc_value` / 100
							WHEN "+" THEN pp.`product_price` + pd.`calc_value`
							WHEN "-" THEN pp.`product_price` - pd.`calc_value`
							ELSE pp.`product_price`
						END as price';
			}
			$q .= ' FROM `#__virtuemart_products` as p';
			if(count($q_where_customfields) > 0){
				$q .= ' LEFT JOIN `#__virtuemart_product_custom_plg_'.$this->_name.'` as param USING(`virtuemart_product_id`)';
			}
			foreach($q_join as $k=>$v){
				$q .= ' LEFT JOIN `'.$v[0].'` as '.$v[1].' ON ';
				if(isset($v[2]))
					$q .= $v[2];
				else
					$q .= 'p.`virtuemart_product_id` = '.$v[1].'.`virtuemart_product_id`';
			}
			/* ----- + In stock ----- */
			if($stock){
				$q_where[] = 'p.`product_in_stock` > 0';
			}
			/* ----- - In stock ----- */
			if(count($q_where) > 0)
				$q .= ' WHERE '.implode(' AND ',$q_where);
			$q .= ' GROUP BY p.`virtuemart_product_id`';
			$q .= $q_having;
			$db->setQuery($q);
			$ids_list = $db->loadObjectList();
			// echo $q;
			$ids = array();
			foreach($ids_list as $v){
				if($discount && ($price_left!=null || $price_right!=null)){
					if($price_left!=null && $price_left > $v->price)
						continue;
					if($price_right!=null && $price_right < $v->price)
						continue;
				}
				$ids[] = $v->virtuemart_product_id;
			}
			/* ===== - Select ===== */
			$where[] = 'p.`virtuemart_product_id` IN ("'.implode('","',$ids).'")';
			// echo $profiler->mark('no cashing');
		}
		return true;
	}

	function plgVmOnProductEdit($field, $product_id, &$row,&$retValue) {
		if ($field->custom_element != $this->_name) return '';
		$this->parseCustomParams($field);
		$this->getPluginProductDataCustom($field, $product_id);

		$html = '';
		$html .='<strong>'.$field->n.':</strong>&nbsp;&nbsp;&nbsp;';
		
		$params_html = '';
		if($field->l == '0'){
			if($field->ft == 'int'){
				$value = isset($field->intvalue) && $field->intvalue != '-13692' ? $field->intvalue : ''; // -1396.2 reserved as default
				$params_html .= '<input type="text" name="plugin_param['.$row.']['.$this->_name.'][intvalue]" value="'.$value.'" />';
				$params_html .= '<input type="hidden" name="plugin_param['.$row.']['.$this->_name.'][value]" value="" />';
			}else{
				$value = empty($field->value) ? '' : substr($field->value,1,-1);
				$params_html .= '<input type="hidden" name="plugin_param['.$row.']['.$this->_name.'][intvalue]" value="" />';
				$params_html .= '<input type="text" name="plugin_param['.$row.']['.$this->_name.'][value]" value="'.$value.'" />';
			}
		}else{
			$params = explode(';',$field->vd);
			$params_html .= '<input type="hidden" name="plugin_param['.$row.']['.$this->_name.'][value]" value="" />';
			$params_html .= '<input type="hidden" name="plugin_param['.$row.']['.$this->_name.'][intvalue]" value="" />';
			if($field->ft == 'int'){
				$values = isset($field->intvalue) ? array($field->intvalue) : array();
				$multiple = '';
				$params_html .= '<select name="plugin_param['.$row.']['.$this->_name.'][intvalue][]".'.$multiple.' style="width:350px;" >';
			}else{
				$values = empty($field->value) ? '' : substr($field->value,1,-1);
				$values = explode('|',$values);
				$multiple = ' multiple';
				$params_html .= '<select name="plugin_param['.$row.']['.$this->_name.'][value][]".'.$multiple.' style="width:350px;" >';
			}
			foreach($params as $k=>$v){
				$selected = in_array($v,$values) ? ' selected="selected"' : '';
				$params_html .= '<option value="'.$v.'"'.$selected.'>'.$v.'</option>';
			}
			$params_html .= '</select>';
		}
		$params_html .= '<a href="/administrator/index.php?option=com_virtuemart&view=custom&task=edit&virtuemart_custom_id[]='.$field->virtuemart_custom_id.'" target="_blank">Управление параметрами</a>';
		$html .= $params_html;
		$html .='<input type="hidden" value="'.$field->virtuemart_custom_id.'" name="plugin_param['.$row.']['.$this->_name.'][virtuemart_custom_id]">';
		// 		$field->display =
		$retValue .= $html  ;
		$row++;
		return true;
	}

	function plgVmOnDisplayProductFE($product,&$idx,&$group) {
		if ($group->custom_element != $this->_name) return '';
		$this->_tableChecked = true;
		$this->parseCustomParams($group);
		$this->getPluginProductDataCustom($group, $product->virtuemart_product_id);
		$html = $this->renderByLayout('default', $group);
		$group->display = $html;
		return true;
	}

	function plgVmOnStoreProduct($data,$plugin_param){
		if(is_array($plugin_param['param']['value'])){
			$plugin_param['param']['value'] = implode('|',$plugin_param['param']['value']);
		}
		$plugin_param['param']['value'] = '|'.str_replace(';','|',$plugin_param['param']['value']).'|';
		if(empty($plugin_param['param']['intvalue'])){
			$plugin_param['param']['intvalue'] = -13692;
		}
		return $this->OnStoreProduct($data,$plugin_param);
	}
	
	
	function plgVmOnCloneProduct($data,$plugin_param){ // not work! need to edit VM2 core
		return $this->OnStoreProduct($data,$plugin_param);
	}

	function plgVmSetOnTablePluginParamsCustom($name, $id, &$table){
		return $this->setOnTablePluginParams($name, $id, $table);
	}
	
	function plgVmOnStoreInstallPluginTable($psType,$data) {
		/* Fix. v1.0 to v1.1. Add new column */
		$db = & JFactory::getDBO();
		$q = 'ALTER TABLE `#__virtuemart_product_custom_plg_'.$this->_name.'` ADD `intvalue` float(11) UNSIGNED DEFAULT \'-13692\' AFTER `value`';
		$db->setQuery($q);
		if($db->query()){}
		/* Fix. v1.0 to v1.1 */
		return $this->onStoreInstallPluginTable($psType);
	}

	function plgVmOnDeleteProduct($id, $ok){
		return true;
	}
	
	function plgVmDeclarePluginParamsCustom($psType,$name,$id, &$data){
		return $this->declarePluginParams($psType, $name, $id, $data);
	}

	function plgVmOnDisplayEdit($virtuemart_custom_id,&$customPlugin){
		
		$j =	"
				(function($){
					function dv_declare(){
						$('#paramsvd').val('');
						var dv_val = new Array();
						$('#dv_box ul li .paramsvd').each(function(){
							dv_val.push($(this).val());
						})
						$('#paramsvd').val(dv_val.join(';'));
					}
					function new_vd(dv_box,def_values,vd){
						dv_box.append('<li><input class=\"paramsvd\" type=\"text\" size=\"32\" value=\"'+def_values[vd]+'\"\"><a class=\"dv_sort\" href=\"#\"></a><a class=\"dv_delete\" href=\"#\"></a></li>');
					}
					$(document).ready(function(){
						if($('#paramsvd').length > 0){
							$($('#paramsvd')).before('<div style=\"color:#f00;\">".JText::_('PLG_VMCUSTOM_PARAM_ONLY_LIST')."</div><div id=\"dv_box\"><ul></ul></div>')
													.before('<a id=\"new_dv\" href=\"#\">".JText::_('PLG_VMCUSTOM_PARAM_ADD_VALUE')."</a><br><br><span><strong>Alternative input</strong> (paste your string and save customfield)</span>');
							var dv_box = $('#dv_box ul');
							var def_values = $('#paramsvd').val();
							def_values = def_values.split(';');
							for(var vd in def_values){
								if (!def_values.hasOwnProperty(vd)) continue
								new_vd(dv_box,def_values,vd);
							}
						}
						$(\"a#new_dv\").click(function(){
							new_vd(dv_box,new Array(''),0);
							return false;
						});
						$(\".paramlist_value\").delegate(\"a.dv_delete\",\"click\", function(){
							$(this).parent().remove();
							dv_declare();
							return false;
						});
						$(dv_box).sortable({
							placeholder: \"ui-state-highlight\",
							handle: \"a.dv_sort\",
							items: \"li\",
							opacity: 0.5,
							stop: dv_declare
						});
						$(\"#dv_box\").delegate(\".paramsvd\",\"change\", dv_declare);
					});
				})(jQuery)
				
				";
		$document = JFactory::getDocument();
		$document->addScriptDeclaration($j);
		$pluginName = 'param';
		$document->addStyleSheet(JURI::root().DS.'plugins'.DS.'vmcustom'.DS.'param'.DS.'param'.DS.'assets'.DS.'style.css');
		return $this->onDisplayEditBECustom($virtuemart_custom_id,$customPlugin);
	}
	
	/* redeclare parent functions */
	function getPluginProductDataCustom(&$field,$product_id){

		$id = $this->getIdForCustomIdProduct( $product_id,$field->virtuemart_custom_id) ;

	 	if($id){ // VM2 fix
			$datas = $this->getPluginInternalData($id);
			if($datas){
				foreach($datas as $k=>$v){
					if (!is_string($v) ) continue ;// Only get real Table variable
					if (isset($field->$k) && $v===0) continue ;
					$field->$k = $v;
				}
			}
		}
	}

}