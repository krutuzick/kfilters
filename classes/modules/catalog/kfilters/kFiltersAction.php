<?php

/**
 * Абстракнтый базовый класс действия для взаимодействий с интерфейсом
 */
abstract class kFiltersAction {
	
	/**
	 * ID категории
	 * @var int
	 */
	protected $catalogId;
	
	/**
	 * Конструктор
	 */
	public function __construct($iCatalogId) {
		$this->catalogId = $iCatalogId;
	}
	
	/**
	 * Интерфейсный метод запуска действия
	 * @return string json-ответ для интерфейса
	 */
	abstract public function run();
};

?>