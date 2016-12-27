<?php
namespace common\Integration\Provider\InSales;

use common\Integration\Provider\AbstractProvider;
use common\models\IntegratedMerchant;
use yii\base\InvalidParamException;

/**
 * Class Application
 * @package common\Integration\Provider\InSales
 */
class Application extends AbstractProvider
{
  /** @const String REQUEST_TYPE_ACCOUNT_GET Строковый идентификатор массива параметров для генерации запроса к insales.ru для получения данных аккаунта */
  const REQUEST_TYPE_ACCOUNT_GET = 'account';
  /** @const String REQUEST_TYPE_GET_ORDERS Строковый иденитификатор массива параметров для генерации запроса к insales.ru для получения данных о заказах */
  const REQUEST_TYPE_GET_ORDERS  = 'orders';
  /** @const String REQUEST_TYPE_GET_ORDER Строковый идентификатор массива параметра для генерации запроса к insales.ru для получения данных отдельного заказа по его номеру*/
  const REQUEST_TYPE_GET_ORDER = 'order';
  /** @const String REQUEST_TYPE_ORDER_GOODS_GET Строковый идентификатор массива парметров для генерации запроса к insales.ru для получения данных о товарах каталога */
  const REQUEST_TYPE_ORDER_GOODS_GET = 'goods';
  /** @const string REQUEST_TYPE_LIST_WEB_HOOKS_GET Строковый идентификатор массива парметров для генерации запроса к insales.ru для получения списка активных веб-хуков */
  const REQUEST_TYPE_LIST_WEB_HOOKS_GET = 'list/webhook';
  /** @const string REQUEST_TYPE_WEB_HOOKS_DELETE Строковый идентификатор массива парметров для генерации запроса к insales.ru на удаление конкретного веб-хука */
  const REQUEST_TYPE_WEB_HOOKS_DELETE = 'destroy/webhook';
  /** @const string REQUEST_TYPE_WEB_HOOKS_POST Строковый идентификатор массива парметров для генерации запроса к insales.ru на создание нового веб-хука */
  const REQUEST_TYPE_CREATE_WEB_HOOKS_POST = 'create/webhook';
  /** @const string REQUEST_TYPE_UPDATE_WEB_HOOKS_POST Строковый идентификатор массива парметров для генерации запроса к insales.ru на создание нового веб-хука */
  const REQUEST_TYPE_UPDATE_WEB_HOOKS_POST = 'update/webhook';
  /** @const string REQUEST_TYPE_ORDERS_UPDATE_PUT Строковый идентификатор массива парметров для генерации запроса к insales.ru на изменение состояния заказа */
  const REQUEST_TYPE_ORDERS_UPDATE_PUT = 'orders/update';

  /** @var  String $apiKey */
  protected $apiKey;
  /** @var  String $apiLogin */
  protected $apiLogin;
  /** @var array $requestSettings */
  protected $requestSettings;
  /** @var  string $pathReplacer */
  protected $pathReplacer;


  public function __construct(IntegratedMerchant $merchant, array $params = [])
  {
    parent::__construct($merchant);

    if (!empty($params['api_key'])) {
      $this->apiKey = $params['api_key'];
    }

    if (!empty($params['api_login'])) {
      $this->apiLogin = $params['api_login'];
    }

    if (!empty($params['request_settings'])) {
      $this->requestSettings = $params['request_settings'];
    }
  }

  
  /** @inheritdoc */
  public function request(string $request_type, array $params = [])
  {
    $result = false;
    $this->responseContent = null;

    //Получаем набор данных для генерации запроса: адреса запросов, параметры, дополнительные заголовки
    if ($this->requestSettings[$request_type]) {
      $data = $this->requestSettings[$request_type];
      $this->prepareRequest($data, $params);
      if ($this->responseContent = curl_exec($this->connection)) {
        switch($request_type) {
          case static::REQUEST_TYPE_ACCOUNT_GET:
          case static::REQUEST_TYPE_GET_ORDERS:
          case static::REQUEST_TYPE_LIST_WEB_HOOKS_GET:
          case static::REQUEST_TYPE_WEB_HOOKS_DELETE:
          case static::REQUEST_TYPE_CREATE_WEB_HOOKS_POST:
          case static::REQUEST_TYPE_UPDATE_WEB_HOOKS_POST:
          case static::REQUEST_TYPE_ORDERS_UPDATE_PUT:
            $this->responseContent = json_decode($this->responseContent, true);
          break;
        }
        $result = true;
      }
    }

    //Обнуляем замещенный путь к запрашиваемому ресурсу после каждого запроса
    $this->pathReplacer = $this->pathReplacer ? null : $this->pathReplacer;
    return $result;
  }
  
  /** @inheritdoc */
  public function getResponse()
  {
    return $this->responseContent;
  }


  /**
   * Заменяет базовый адрес для запроса
   *
   * @param string $url
   * @return void
   */
  public function modifyUrlPath(string $path)
  {
    $this->pathReplacer = $path;
  }


  ////////////////////////////// ЗАЩИЩЕННЫЕ И ЗАКРЫТЫЕ МЕОДЫ КЛАССА ///////////////////////////////


  /** @inheritdoc */
  protected function setRequestOptions()
  {
    parent::setRequestOptions();
    curl_setopt($this->connection, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  }


  /**
   * @param array $data
   * @params
   * @return void
   */
  protected function prepareRequest(array $data, array $params = [])
  {
    if (!$data) {
      throw new InvalidParamException(
        'Cannot create request to in-sales service: ' . __METHOD__
      );
    }

    //1. Генерим базовый (без дополнительных параметров) URL запроса
    $urlPath = $this->pathReplacer ?? $data['URL'];
    $requestUrl = sprintf("http://%s:%s@%s/%s",
      $this->apiLogin,
      $this->merchant->password,
      $this->merchant->external_shop_url,
      $urlPath
    );

    //2. Уставнивалем: 1) URL запроса, метод запроса и дополнительные данные, если есть
    $method = $data['METHOD'];
    switch($method) {
      case 'GET':
        curl_setopt($this->connection, CURLOPT_HTTPGET, true);
        if ($params) {  //Параметры передаются в виде массива элементов ключ=значение
          $requestUrl .= "?" . implode('&', $params);
        }
        break;
      case 'POST':
        curl_setopt($this->connection, CURLOPT_POST, true);
        if ($params) {  //Параметры передаются в виде массива, который потом приводится к json-строке
          curl_setopt($this->connection, CURLOPT_POSTFIELDS, json_encode($params));
        }
        break;
      case 'PUT':
        curl_setopt($this->connection, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($params) {
          curl_setopt($this->connection, CURLOPT_POSTFIELDS, json_encode($params));
        }
        break;
      case 'DELETE':
        curl_setopt($this->connection, CURLOPT_CUSTOMREQUEST, 'DELETE');
        break;
    }

    //Устанавливаем адрес запроса
    curl_setopt($this->connection, CURLOPT_URL, $requestUrl);

    //3. Устанавливаем дополнительные заголовки, которые требуются для данного запроса
    if ($data['HEADERS']) {
        curl_setopt($this->connection, CURLOPT_HTTPHEADER, $data['HEADERS']);
    }
  }

  /**
   * Конвертирует все поля типа stdClass в array
   *
   * @param $object
   * @return array
   */
  public function obj2array($object): array
  {
    $result = $object;
    if ($result instanceof \stdClass) {
      $result = (array)$result;
    } else if (is_array($object)) {
      foreach($result as $field => $value) {
        if ($value instanceof \stdClass) {
          $result[$field] = $this->obj2array($value);
        }
      }
    }

    return $result;
  }
}
