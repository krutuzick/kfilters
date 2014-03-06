<?php

/**
 * Класс для кастомной конфигурации поведения фильтров
 */
class kFiltersCustomLogic {
	
	/**
	 * ID категории каталога, для которой строятся фильтры
	 * @var integer
	 */
	protected $catalogId = null;
	
	/**
	 * ID типа объектов каталога, преобладающих в категории (для которых строятся фильтры)
	 * @var integer
	 */
	protected $objectsTypeId = null;
	
	
	public function __construct($catalogId, $objectsTypeId) {
		$this->catalogId = $catalogId;
		$this->objectsTypeId = $objectsTypeId;
	}
	
	

	
	
	
	
	/**
	 * Названия групп полей в шаблоне данных, на основе которых формируются фильтры
	 * @return array Список названий групп полей
	 */
	public function groupNames() {
		return array("cenovye_svojstva", "catalog_filter_prop");
	}
	
	
	/**
	 * Кастомная (дополнительная) логика для формирования списка фильтров - реализована в виде массива, описывающего условия ограничений на выборку при проверке наличия страниц для полей.
	 * Возможные операторы: eq, noteq, null, notnull, gt, lt, like
	 * Возможные значения: number, string, array
	 * Формат элемента: array('field_name', 'operator', 'value')
	 * Элементы массива соединяются по типу "И".
	 * @return array
	 */
	public function filters() {
		$arCustomFilters = array();
		
		//$arCustomFilters[] = array('field_name', 'operator', 'value')
		
		return $arCustomFilters;
	}
	
	
	/**
	 * Кастомная (дополнительная) логика для списика полей в фильтрах - реализована в виде массива названий полей, которые надо скрыть (тут можно, например, ограничивать видимость полей по куке)
	 * @return array
	 */
	public function hiddenFields() {
		$arHiddenFields = array();
		
		//$arHiddenFields[] = 'price_msk';
		//$arHiddenFields[] = 'old_price';
		//$arHiddenFields[] = 'extra_goods';
		$arHiddenFields[] = 'napryazhenie_v';
		
		return $arHiddenFields;
	}

	/**
	 * Текст для значений полей контролов, равным NULL (значение не установлено). Если false, то такое поле не будет выводиться
	 * @param $oField
	 * @return boolean|string
	 */
	public function relationNullValue($oField) {
		//return "Значение не установлено";
		return false;
	}

	/**
	 * Не отображать фильтр, если он бесполезен (например, только один вариант значения, или вообще вариантов нет)
	 * @param $oField
	 * @return boolean
	 */
	public function omitNoChoice($oField) {
		return false;
	}
	
	/**
	 * Отображать "неактивность" при загрузке фильтров
	 * @return boolean
	 */
	public function showStateOnLoad() {
		return true;
	}
	
	/**
	 * Отображать "неактивность" при каждом изменении состояния фильтров (запросе на кол-во)
	 * @return boolean
	 */
	public function showStateOnCount() {
		return true;
	}

	/**
	 * Сохранять ли результирующие json-ы запросов в папке kJsonCache
	 * @return boolean
	 */
	public function saveJsonCache() {
		return true;
	}

	/**
	 * Список названий полей, которые должны быть раскрыты при закрузке виджета
	 * @return array
	 */
	public function expanded() {
		return array("price");
		//return array();
	}
};

?>