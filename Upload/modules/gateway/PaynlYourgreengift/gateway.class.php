<?php

require_once realpath(__DIR__ . '/../Paynl/PaynlBase.php');

class Gateway extends PaynlBase
{
  protected $merchantId = 2925;
  protected $moduleName = 'PaynlYourgreengift';
}
