<?php
namespace common\Integration\Gateway\InSales\State;

/**
 * Class AbstractState
 * @package common\Integration\Gateway\InSales\State
 *
 * Абстрактный класс-родителя для работы с состояниями различных параметров заказа. Параметры передаются в формате InSales
 */
abstract class AbstractState
{
  /** @var  array @stateData */
  protected $stateData;
  /** @var  string @key */
  protected $key;

  public function __construct(array $data)
  {
    $this->initState($data);
  }

  /**
   * Проверяет, является ли текущее значение пустым
   * @return bool
   */
  public function isEmpty()
  {
    return !array_key_exists($this->stateData[$this->key]) || !$this->stateData[$this->key];
  }

  /**
   * Возвращает значение состояние
   *
   * @return mix
   */
   public function getValue()
   {
     return $this->stateData[$this->key] ?? null;
   }

  /**
   * Сравнивает текущее состояние объекта класса AbstractState с переданным в параметре созначением
   *
   * @param mix $value
   * @return bool
   */
  abstract public function isEqualValue($value): bool;


  //////////////////////// ЗАЩИЩЕННЫЕ И ЗАКРЫТЫЕ МЕТОДЫ КЛАССА ///////////////////////////////


  /**
   * Инициализатор класса
   *
   * @abstract
   * @param array $data
   * @return void
   */
  abstract protected function initState(array $data);
}
