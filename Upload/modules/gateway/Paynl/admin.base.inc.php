<?php

$smarty->assign('merchantId', $merchantId); 
$smarty->assign('merchantName', $merchantName);

// define the image shown to the customer
$merchantImage = str_ireplace($_SERVER['DOCUMENT_ROOT'],'', dirname($modulePath)) .'/logo.png';
if(substr($merchantImage, 0, 1) !== '/') $merchantImage = '/'. $merchantImage;
$smarty->assign('merchantImage', $merchantImage);

/* Gateway settings will be updated after this script, so make sure we grab  
 * the latest settings from $_POST. */
$arrModuleSettings = $config->get($_GET['module']);
if(isset($_POST['module'])) $arrModuleSettings = array_merge($arrModuleSettings, $_POST['module']);

//create list of orderstatusses in the right language
$arrOrderStatusses = array(
  '1' => $lang['order_state']['name_1'], // ORDER_PENDING
  '2' => $lang['order_state']['name_2'], // ORDER_PROCESS
  '3' => $lang['order_state']['name_3'], // ORDER_COMPLETE
  '4' => $lang['order_state']['name_4'], // ORDER_DECLINED
  '5' => $lang['order_state']['name_5'], // ORDER_FAILED
  '6' => $lang['order_state']['name_6']  // ORDER_CANCELLED
);
$smarty->assign('orderOptions', $arrOrderStatusses);

//defaults 
$paidOrderstatus    = isset($arrModuleSettings['paidOrderstatus'])    ? $arrModuleSettings['paidOrderstatus']    : '2';
$pendingOrderstatus = isset($arrModuleSettings['pendingOrderstatus']) ? $arrModuleSettings['pendingOrderstatus'] : '1';
$cancelOrderstatus  = isset($arrModuleSettings['cancelOrderstatus'])  ? $arrModuleSettings['cancelOrderstatus']  : '4';
$failedOrderstatus  = isset($arrModuleSettings['failedOrderstatus'])  ? $arrModuleSettings['failedOrderstatus']  : '5';

// throw these to smarty
$smarty->assign('paidOrderstatus',    $paidOrderstatus);
$smarty->assign('pendingOrderstatus', $pendingOrderstatus);
$smarty->assign('cancelOrderstatus',  $cancelOrderstatus);
$smarty->assign('failedOrderstatus',  $failedOrderstatus);

// tell the user if the api crendentials are correct
require_once dirname($modulePath) . '/../gateway.class.php';
$objGateway = new Gateway($arrModuleSettings);
$connectionSuccesful = $objGateway->checkConnection( 
  $arrModuleSettings['apitoken'],
  $arrModuleSettings['service_id']);
$smarty->assign('noConnection', ! $connectionSuccesful);

// create the module and show the admin form
$module	      = new Module($modulePath, $_GET['module'], realpath(__DIR__ . '/../Paynl/skin/admin/index.tpl'), true);
$page_content = $module->display();

