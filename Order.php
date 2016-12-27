<?php
namespace common\Integration\Gateway\InSales;


use common\models\EntityInterface\OrderSuitable;
use common\Utils\Phones\PhonesParser;
use common\Utils\RenderHelper;
use orders\models\Goods;
use orders\requests\Order\OrderCreateRequest;
use yii\base\UnknownPropertyException;
use orders\models\Order as UmzilaOrder;

/**
 * Гейтвей заказа. Переводит набор свойств внешней структуры заказа в структуру данных заказа, используемую в системе
 *
 * @property string $status
 * @property integer $shop_id
 * @property integer $shop_order_id
 * @property String $customer_name
 * @property String $customer_phone
 * @property String $customer_flat
 * @property String $customer_address
 * @property String $customer_external_id
 * @property String $customer_address_country
 * @property bool $delivery_enabled
 * @property String $delivery_date
 * @property String $delivery_date_type
 * @property String $delivery_price
 * @porperty integer $status_manual_processing
 * @property integer $status_technical_processing
 * @property integer $archived
 * @property integer $integrated_system_shop_order_number
 * @property array $goods
 *
 * @package common\Integration\RawOrder\InSales
 */
class Order implements OrderSuitable
{
  const INSALES_ORDER_STATUS_NEW        = 'new';
  const INSALES_ORDER_STATUS_ACCEPTED   = 'accepted';
  const INSALES_ORDER_STATUS_APPROVED   = 'approved';
  const INSALES_ORDER_STATUS_DISPATCH   = 'dispatched';
  const INSALES_ORDER_STATUS_DELIVERED  = 'delivered';
  const INSALES_ORDER_STATUS_RETURNED   = 'returned';
  const INSALES_ORDER_STATUS_DECLINED   = 'declined';

  const INSALES_PAID_STATUS     = 'paid';
  const INSALES_PENDING_STATUS  = 'pending';


  /** @var  array $rawData */
  protected $rawData;
  /** @var  array $data */
  protected $data;
  /** @var  bool $isNew */

  public function __construct(array $data)
  {
    //Структура заказа для передачи на сохранение в веб-сервис
    $this->data = [
      'shop_id'         => null,
      'status'          => null,
      'shop_order_id'   => null,
      'customer_name'   => null,
      'customer_phone'  => null,
      'customer_flat'   => null,
      'customer_address'          => null,
      'customer_external_id'      => null,
      'customer_address_country'  => null,
      'delivery_enabled'          => null,
      'delivery_date'             => null,
      'delivery_date_type'        => null,
      'delivery_price'            => null,
      'status_manual_processing'  => null,
      'archived'                  => null,
      'status_technical_processing' => null,
      'integrated_system_shop_order_number' => null,
      'goods' => [],
    ];

    //Инициализируем структуру заказа и сохраняем исходные данные
    $this->convert($data);
  }


  /**
   * Создаем и возвращает объект Order из переданных в массиве значений
   *
   * @param array $data
   * @return Order
   */
  static public function createFromArray(array $data): Order
  {
    return new Order($data);
  }


  /**
   * Проверяет, подходит ли заказ для обработки в системе Umzila
   *
   * @return bool
   */
  public function isRelevant(): bool
  {
    $ignoredStatuses = [
      static::INSALES_ORDER_STATUS_NEW,        //Новый еще не обработанный заказ
      static::INSALES_ORDER_STATUS_ACCEPTED,   //Заказ утвержден, но без даты доставки
      static::INSALES_ORDER_STATUS_APPROVED,
    ];

    if (!in_array($this->getOriginalStatus(), $ignoredStatuses)) {
        return true;
    }

    return false;
  }


  /**
   * Геттер свойств объекта
   *
   * @param String $property
   * @return mixed|null
   * @throws UnknownPropertyException
   */
  public function __get($property)
  {
    if (in_array($property, array_keys($this->data))) {
      return $this->data[$property];
    } else {
      throw new UnknownPropertyException;
    }
    return null;
  }

  
  /**
   * Сеттер свойств объекта
   *
   * @param String $property
   * @param mix $value
   * @return null
   * @throws UnknownPropertyException
   */
  public function __set($property, $value)
  {
    if (in_array($property, array_keys($this->data))) {
      $this->data[$property] = $value;
    } else {
      throw new UnknownPropertyException;
    }
    return null;
  }


  /**
   * Возвращает массив значений для сохранения в БД
   *
   * @return array
   */
  public function buildRequest(int $shop_id = null): array
  {
    return [
      'shop_id'               => $shop_id,
      'shop_order_id'         => $this->shop_order_id,
      'delivery_enabled'      => $this->delivery_enabled,
      'customer_flat'         => $this->customer_flat,
      'customer_name'         => $this->customer_name,
      'customer_phone'        => $this->customer_phone,
      'customer_external_id'  => $this->customer_external_id,
      'delivery_date'         => $this->delivery_date,
      'delivery_date_type'    => $this->delivery_date_type,
      'delivery_price'        => $this->delivery_price,
      'archived'              => $this->archived,
      'customer_address'      => call_user_func(function($address) {      //Извлекаем часть адреса
        $result = $address;
        if (false !== mb_strstr($address, '[')) {
          $buffer = explode('[', $result);
          $result = $buffer[0] ?? $result;
          $result = trim($result);
        }
        return $result;
      }, $this->customer_address),
      'customer_address_comment'    => call_user_func(function($address) { //Извлекаем комментарии
        $result = '';
        if (false !== mb_strstr($address, '[')) {
          $buffer = explode('[', $address);
          if (is_array($buffer) && count($buffer)) {
            array_shift($buffer); //Убираем первый элемент массива
            $result = implode('; ', $buffer);
            $result = str_replace(['[', ']'], '', $result);
            $result = trim($result);
          }
        }
        return $result;
      }, $this->customer_address),
      'customer_address_country'    => $this->customer_address_country,
      'status_manual_processing'    => $this->status_manual_processing,
      'status_technical_processing' => $this->status_technical_processing,
      'integrated_system_shop_order_number' => $this->integrated_system_shop_order_number,
      'goods' => $this->goods,
    ];
  }


  /**
   * Возвращает символьное обозначение оригинального (InSales) статуса заказа
   *
   * @return mixed
   */
  public function getOriginalStatus()
  {
    return $this->rawData['fulfillment_status'];
  }


  /**
   *
   * @return bool
   */
  public function isPaid()
  {
    return $this->rawData['financial_status'] == 'paid';
  }


  /**
   * Возвращает исходное состояние объекта в виде массива
   *
   * @return array
   */
  public function getRawData()
  {
    return $this->rawData;
  }


  /**
   * Возвращает полную стоимость заказа
   *
   * @return int
   */
  public function getTotalSum()
  {
    $items  = $this->goods;
    $result = $this->delivery_price;
    array_walk($items, function($item) use (&$result) {
      $price = !empty($item['price'])    ? (int)$item['price']    : null;
      $count = !empty($item['quantity']) ? (int)$item['quantity'] : null;
      if ($price && $count) {
        $result = $price * $count;
      }
    });

    return $result;
  }


  /**
   * Возвращает статус заказа для сохранения в системе InSales
   *
   * @return string
   */
  public function getInsalesOrderStatus()
  {
    $result = $this->data['status'];
    switch($result) {
      case UmzilaOrder::STATUS_ACTIVE:
        $result = static::INSALES_ORDER_STATUS_DISPATCH;
        break;
      case UmzilaOrder::STATUS_COMPLETED:
        $result = static::INSALES_ORDER_STATUS_DELIVERED;
        break;
      case UmzilaOrder::STATUS_CANCELED:
        $result = static::INSALES_ORDER_STATUS_DECLINED;
        break;
    }

    return $result;
  }


  /////////////////////////////// Защищенные и закрытые методы ////////////////////////////////////


  /**
   * Инициализирует структуру заказа и сохраняет исходные данные
   *
   * @param array $data
   */
  protected function convert(array $data)
  {
    $this->rawData = $data;
    $this->setAttributesObject($data);
    $this->detectOrderStatus($data);
    $this->detectShopOrderId($data);
    $this->detectOrderCustomer($data);
    $this->detectOrderDelivery($data);
    $this->detectOrderItems($data);
    $this->detectDiscounts($data);
  }

  /**
   * Возвращает внешний уникальный идентификатор заказа
   *
   * @param array $data
   * @return bool
   */
  protected function detectShopOrderId(array $data): bool
  {
    $this->shop_id = !empty($data['shop_id']) ? (int)$data['shop_id'] : $this->shop_id;
    
    $this->shop_order_id = null;
    if (!empty($data['id'])) {
      $this->shop_order_id = (int)$data['id'];
    }

    return (bool)$this->shop_order_id;
  }


  /**
   * Возвращает массив товаров в заказе
   *
   * @param array $data
   * @return bool
   */
  protected function detectOrderItems(array $data): bool
  {
    $this->goods = call_user_func(function($data) {
      $goods = [];
      if ($orderLines = !empty($data['order_lines']) ? $data['order_lines'] : []) {
        foreach($orderLines as $line) {
          $article = [];
          if (!empty($line['product_id'])) { $article[] = $line['product_id']; }
          if (!empty($line['variant_id'])) { $article[] = $line['variant_id']; }
          $goods[] = [
            'article'     => implode('_', $article),
            'title'       => !empty($line['title'])     ? $line['title']    : null,
            'description' => !empty($line['comment'])   ? $line['comment']  : null,
            'weight'      => !empty($line['weight'])    ? $line['weight']   : null,
            'quantity'    => !empty($line['quantity'])  ? (int)$line['quantity']      : null,
            'price'       => !empty($line['sale_price']) ? $line['sale_price'] * 100  : null,
            'type'        => Goods::TYPE_GOOD,
          ];
        }
      }

      return $goods;
    }, $data);

    return count($this->goods) ? true : false;
  }


  /**
   * Возвращает данные о клиенте
   *
   * @param array $data
   * @return bool
   */
  protected function detectOrderCustomer(array $data): bool
  {
    $result = [];
    $client  = !empty($data['client']) ? $data['client'] : [];
    $address = !empty($data['shipping_address']) ? $data['shipping_address'] : [];

    $this->customer_name  = !empty($client['name']) ? $client['name'] : null;
    $this->customer_flat  = null;
    $this->customer_phone = PhonesParser::normalizePhone($client['phone']);
    $this->customer_address         = null;
    $this->customer_external_id     = !empty($client['id']) ? (int)$client['id'] : null;
    $this->customer_address_country = 'Россия'; //@TODO нужно понять, как определить страну, если адрес в виде строки

    //$this->email        = !empty($client['email'])    ? $client['email'] : null;
    //$this->surname      = !empty($client['surname'])  ? $client['surname'] : null;
    //$this->patronymic   = !empty($client['middlename']) ? $client['middlename'] : null;
    //$this->created_at   = (new \DateTime($client['created_at']))->getTimestamp();
    //$this->updated_at   = (new \DateTime($client['updated_at']))->getTimestamp();

    //Собираем адрес доставки
    if (!empty($address['address']) || !empty($address['full_delivery_address'])) {
      $buffer = [];
      if (!empty($address['zip']))      { $buffer[] = $address['zip']; }
      if (!empty($address['country']))  { $buffer[] = $address['country']; }
      if (!empty($address['city']))     { $buffer[] = $address['city']; }
      if (!empty($address['street']))   { $buffer[] = $address['street']; }
      if (!empty($address['house']))    { $buffer[] = $address['house']; }
      if (!empty($address['flat']))     { $buffer[] = $address['flat']; }

      $this->customer_address  = implode(', ', $buffer);
      if (!empty($address['full_delivery_address'])) {
        $this->customer_address  .= " {$address['full_delivery_address']}";
      } else if (!empty($address['address'])) {
        $this->customer_address  = " {$address['address']}";
      }
    }

    return (!empty($client) || !empty($address));
  }


  /**
   * Возвращает данные о доставке
   *
   * @param array $data
   * @return bool
   */
  protected function detectOrderDelivery(array $data): bool
  {
    $deliveryDate = $data['delivery_date'] ?? 0;
    if ($deliveryDate = trim($deliveryDate)) {
      $deliveryDate = (\DateTime::createFromFormat(
        'Y-m-d', $deliveryDate
      ))->format('Ymd');
    }

    $deliveryPrice = $data['delivery_price'] ?? null;
    $deliveryPrice = $deliveryPrice ? $deliveryPrice * 100 : $deliveryPrice;

    $this->delivery_date    = $deliveryDate;
    $this->delivery_price   = $deliveryPrice;
    $this->delivery_enabled   = true;
    $this->delivery_date_type = OrderCreateRequest::DELIVERY_DATE_TYPE_CUSTOM;

    return true;
  }

  /**
   * Устанавливаем статус заказа
   *
   * @param array $data
   * @return void
   */
  protected function detectOrderStatus(array $data)
  {
    $result = false;

    if (!empty($data['fulfillment_status'])) {
      switch($data['fulfillment_status']) {
        case static::INSALES_ORDER_STATUS_NEW:
          $this->status = static::ORDER_STATUS_ACTIVE;
          $this->status_manual_processing = static::ORDER_STATUS_MANUAL_PROCESSING_REQUIRE;
          break;
        case static::INSALES_ORDER_STATUS_ACCEPTED:
        case static::INSALES_ORDER_STATUS_APPROVED:
        case static::INSALES_ORDER_STATUS_DISPATCH:
          $this->status = static::ORDER_STATUS_ACTIVE;
          $this->status_manual_processing = static::ORDER_STATUS_MANUAL_PROCESSING_NOT_REQUIRE;
          $this->status_technical_processing = static::ORDER_STATUS_TECHNICAL_PROCESSING_COMPLETED;
          break;
        case static::INSALES_ORDER_STATUS_DELIVERED:
          $this->status   = static::ORDER_STATUS_COMPLETED;
          break;
        case static::INSALES_ORDER_STATUS_RETURNED:
          $this->archived = true;
          break;
        case static::INSALES_ORDER_STATUS_DECLINED:
          $this->status = static::ORDER_STATUS_CANCELED;
          break;
      }
    }

    return $result;
  }

  /**
   * Проверяет наличие скидок в заказе и добавляет их в массив товаров
   *
   * @param array $data
   * @return void
   */
  protected function detectDiscounts(array $data)
  {
    $discounts = $data['discounts'] ?? [];
    if (count($this->goods) && $discounts) {
      array_walk($discounts, function($discount) {
        //Формируем название и описание по умолчанию
        $sum   = array_key_exists('amount', $discount) ? $discount['amount'] * 100  : 0;
        $title = sprintf('Скидка (%s р.)', number_format(($sum/100), 2, ',', ' '));
        if((bool)$discount['percent']) {
            $title = "Скидка {$discount['percent']}%";
        }

        $this->data['goods'][] =[
          'article'     => $discount['id'] ?? uniquid(),
          'title'       => $title,
          'description' => null,
          'weight'      => null,
          'quantity'    => 1,
          'price'       => -$sum,
          'type'        => Goods::TYPE_DISCOUNT,
        ];
      });
    }
  }


  /**
   * Устанавливает параметры самого обьекта
   *
   * @param $data
   */
  private function setAttributesObject($data)
  {
    $this->integrated_system_shop_order_number = !empty($data['number']) ? (int)$data['number'] : null;
  }
}
