<?php
define('IN_UICE','contact');
require 'config/config.php';

if(!is_logged())
	redirect('user.php?login','js',NULL,true);

if(is_posted('submit/cancel') && is_permitted(IN_UICE)){
	$_G['action']='misc_cancel';
	$_G['require_export']=false;
	
}elseif((got('add')||got('edit')) && is_permitted(IN_UICE)){
	$_G['action']=IN_UICE.'_add';

}elseif(is_permitted(IN_UICE)){
	$_G['action']=IN_UICE.'_list';

}else{
	exit('no permission');
}

require 'controller/export.php';
?>