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

$html = '<div class="product-field-'.$viewData->virtuemart_custom_id.'">';
// $html .='<div class="product-fields-title">'.$group->custom_title.'</div>';
$values = $viewData->ft == 'int' ? array($viewData->intvalue) : explode('|',substr($viewData->value,1,-1));
$html .='<div class="product-fields-value">';
if(count($values) > 0)
	$html .= '<ul><li>'.implode('</li><li>',$values).'</li></ul>';
$html .='</div></div>';
echo $html;