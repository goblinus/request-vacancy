<?php
namespace common\Integration\Provider\RetailCRM;

use common\Integration\Provider\AbstractProvider;

/**
 * Class Application
 * @package common\Integration\Provider\RetailCRM
 */
class Application extends AbstractProvider
{
  public function __construct(IntegratedMerchant $merchant)
  {
    parent::__construct($merchant);
  }

  /** @inheritdoc */
  public function getResponse()
  {
    // TODO: Implement getResponse() method.
  }

  public function request(String $type): bool
  {
    return true;
  }
}