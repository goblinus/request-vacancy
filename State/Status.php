<?php
namespace common\Integration\Gateway\InSales\State;

use common\Integration\Gateway\InSales\State\AbstractState;

/**
 * Class Status
 * @package common\Integration\Gateway\InSales\State
 *
 * Класса для хранения статуса InSales заказа и сравнения текущего статуса со статусом другого InSales заказа
 */
class Status extends AbstractState
{


  /** @inheritdoc */
  public function isEqualValue($value): bool
  {
    $value = is_string($value) ? trim($value) : '';
    return $this->stateData[$this->key] === $value;
  }


  ///////////////////////// ЗАЩИЩЕННЫЕ И ЗАКРЫТЫЕ МЕТОДЫ КЛАССА //////////////////////////


  /** @inheritdoc */
  protected function initState(array $data)
  {
    $this->key = 'fulfillment_status';
    $this->stateData[$this->key] = $data[$this->key] ?? '';
  }
}
