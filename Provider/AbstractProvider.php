<?php
namespace common\Integration\Provider;

use yii\di\Container;
use common\models\IntegratedMerchant;

/**
 * Class AbstractProvider
 * @package common\Integration\Provider
 */
abstract class AbstractProvider
{
  /** @const int TIMEOUT_LIMIT время ожидания задержки при запросе к стороннему сервису */
  const TIMEOUT_LIMIT = 2;
  /** @const int PERMITTED_REDIRECT_QUANTITY максимально возможное кол-во редиректов при http-запросе */
  const PERMITTED_REDIRECT_QUANTITY = 3;


  /** @var  String $name Строковый идентификатор партнерского сервиса */
  protected $name;
  /** @var IntegratedMerchant $merchant */
  protected $merchant;
  /** @var  array $params */
  protected $params;
  /** @var resource $connection curl-соединение */
  protected $connection;
  /** @var array $responseHeaders  */
  protected $responseHeaders;
  /** @var  String $responseContent */
  protected $responseContent;


  public function __construct(IntegratedMerchant $merchant = null)
  {
    $this->merchant = $merchant;
    $this->responseHeaders = [];
    $this->responseContent = '';

    $this->connection = curl_init();

    $this->setRequestOptions();
  }

  /**
   * Отправляет запрос к стороннему сервису интеграции
   * 
   * @return bool
   */
  //abstract public function request(Strint ): bool;

  /**
   * Возвращает результат запроса к стороннему сервису интеграции
   *
   * @param String $url
   * @return mixed
   */
  abstract public function getResponse();


  ////////////////////////// ЗАЩИЩЕННЫЕ И ЗАКРЫТЫЕ МЕТОДЫ /////////////////////////////////


  /**
   * Устанавливает дополнительные атрибуты соединения с партнерских сервисом
   *
   * @return void
   */
  protected function setRequestOptions()
  {
    curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->connection, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($this->connection, CURLOPT_CONNECTTIMEOUT, static::TIMEOUT_LIMIT);
    curl_setopt($this->connection, CURLOPT_MAXREDIRS, static::PERMITTED_REDIRECT_QUANTITY);
  }
}
