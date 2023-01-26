<?php

require_once realpath(__DIR__ . '/../Paynl/PaynlBase.php');

class Gateway extends PaynlBase
{
  protected $merchantId = 2670;
  protected $moduleName = 'PaynlDinerbon';
}
