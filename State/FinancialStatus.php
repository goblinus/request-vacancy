<?php
namespace common\Integration\Gateway\InSales\State;

use common\Integration\Gateway\InSales\State\AbstractState;

/**
 * Class FinancialStatus
 * @package common\Integration\Gateway\InSales\State
 *
 *
 */
class FinancialStatus extends AbstractState
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
    $this->key = 'financial_status';
    $this->stateData[$this->key] = $data[$this->key] ?? '';
  }
}
