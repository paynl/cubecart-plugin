<?php

require_once realpath(__DIR__ . '/../Paynl/PaynlBase.php');

class Gateway extends PaynlBase
{
  protected $merchantId = 816;
  protected $moduleName = 'PaynlPodiumcadeaukaart';
}
