<?php
namespace common\Integration\Provider;
/**
 * Class ProviderFactory
 * @package common\Integration\Provider
 */
use common\Integration\ {
  Provider\InSales\Application as InSalesApp,
  Provider\RetailCRM\Application as RetailCrmApp,
  Exception\ProviderNotExisted as ProviderNotExistedException
};

use common\models\IntegratedMerchant;

class ProviderFactory
{
  const INSALES_PROVIDER    = 'in-sales';
  const RETAIL_CRM_PROVIDER = 'retail-crm';

  /**
   * @param String $providerType
   * @return AbstractProvider
   * @throws ProviderNotExistedException
   */
  public function create(String $providerType, IntegratedMerchant $merchant, array $params = [])
  {
    $providers = $this->getProviders();
    if (!array_key_exists($providerType, $providers)) {
      throw new \Exception(
        'Exception: cannot create integration provider: '. $providerType
      );
    }
    
    $provider = null;
    switch($providerType) {
      case static::INSALES_PROVIDER:
        $provider = new InSalesApp($merchant, $params);
        break;
      case static::RETAIL_CRM_PROVIDER:
        $provider = new RetailCrmApp($merchant);
        break;
    }
    
    return $provider;
  }


  /**
   * Возвращает массив достпустимых провайдеров интеграции
   * 
   * @return array
   */
  protected function getProviders()
  {
    return [
      static::INSALES_PROVIDER    => InSalesApp::class,
      static::RETAIL_CRM_PROVIDER => RetailCrmApp::class,
    ];
  }
}
