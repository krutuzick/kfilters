<?php

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'kFiltersActionFabric.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'kFiltersCache.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'kFiltersCustomLogic.php');

/**
 * Класс-мультитон для работы с фильтрами и виджетом kFilters
 * Для работы фильтров по выпадающим спискам (IN-значения), нужно в макрос выборки объектов добавить kFilters::applyInFilters($sel, $type_id);
 */
class kFilters {
	/**
	 * Экземпляр синглтона
	 * @var kFilters
	 */
	protected static $instances = array();
	
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
	
	/**
	 * Объект для работы с кэшем
	 * @var kFiltersCache
	 */
	protected $cache = null;
	
	/**
	 * Объект с описанием кастомной логики
	 * @var kFiltersCustomLogic
	 */
	public $customLogic = null;
	
	/**
	 * Название таблицы, в которой находится контент для данной категории
	 * @var string
	 */
	public $sContentTable = false;
	
	/**
	 * Защищённый конструктор
	 * @param int $catalogId ID категории каталога
	 */
	protected function __construct($catalogId) {
		$this->catalogId = $catalogId;
		$this->cache = new kFiltersCache();
		$this->objectsTypeId = umiHierarchy::getInstance()->getDominantTypeId($this->catalogId);
		$this->sContentTable = umiBranch::getBranchedTableByTypeId($this->objectsTypeId);
		$this->customLogic = new kFiltersCustomLogic($this->catalogId, $this->objectsTypeId);
	}
	
	/**
	 * Перекрытие клонирования для обеспечения шаблона singleton
	 */
	public function __clone() {
		throw new coreException('Singletone clonning is not permitted. Just becase it\'s non-sense.');
	}

	/**
	 * Получение экземпляра синглтона
	 * @param int $catalogId ID элемента, для которого строится объект
	 * @return kFilters
	 */
	public static function getInstance($catalogId) {
		if(!isset(self::$instances[$catalogId])) {
			self::$instances[$catalogId] = new self($catalogId);
		}
		
		return self::$instances[$catalogId];
	}
	
	/**
	 * Реакция на интерфейсное действие для виджета
	 * @param string $actionName Название действия
	 * @return string json-ответ
	 */
	public function runAction($actionName) {
		$oAction = kFiltersActionFabric::getAction($actionName, $this->catalogId);
		if($oAction instanceof kFiltersAction) {
			return $oAction->run();
		} else {
			return json_encode(array('errors' => "Action {$actionName} does not exists"));
		}
	}
	
	/**
	 * Получение объекта кэша
	 * @return kFiltersCache
	 */
	public function getCache() {
		return $this->cache;
	}

	/**
	 * SQL-запрос, выбирающий только активные подстраницы для текущей категории
	 *
	 * @return string
	 */
	public function getSQL_activePages() {
		$sSQL = <<<SQL
			SELECT `h`.`obj_id` 
			FROM `cms3_hierarchy` AS `h`
			WHERE `h`.`rel` = {$this->catalogId} AND `h`.`is_active` = 1 AND `h`.`is_deleted` = 0
SQL;
		return $sSQL;
	}
	
	/**
	 * SQL-запрос, соответствующий кастомной логике для фильтров
	 *
	 * @return string
	 */
	public function getSQL_customFilters() {
		$sSQL = <<<SQL
			SELECT `coc`.`obj_id`
			FROM `{$this->sContentTable}` AS `coc`
			WHERE 1
SQL;
		
		$arCustomFilters = $this->customLogic->filters();
		if(empty($arCustomFilters)) return "";
		
		$oType = umiObjectTypesCollection::getInstance()->getType($this->objectsTypeId);
		if(!$oType) return "";
		
		foreach($arCustomFilters as $i => $arCustomFilter) {
			$iFieldId = $oType->getFieldId($arCustomFilter[0]);
			
			$sCustomOperator = $arCustomFilter[1];
			
			$sOperator = "";
			$sValue = $arCustomFilter[2];
			
			switch($sCustomOperator) {
				case "eq": {
					$sOperator = "IN";
					$sValue = (is_array($sValue)) ? join("','", $sValue) : $sValue;
					$sValue = "('" . $sValue . "')";
				} break;
				case "noteq": {
					$sOperator = "NOT IN";
					$sValue = (is_array($sValue)) ? join("','", $sValue) : $sValue;
					$sValue = "('" . $sValue . "')";
				} break;
				case "null": {
					$sOperator = "IS";
					$sValue = "NULL";
				} break;
				case "notnull": {
					$sOperator = "IS NOT";
					$sValue = "NULL";
				} break;
				case "gt": {
					$sOperator = ">=";
					$sValue = trim($sValue);
				} break;
				case "lt": {
					$sOperator = "<=";
					$sValue = trim($sValue);
				} break;
				case "like": {
					$sOperator = "LIKE";
				} break;
			}
			
			
			
			$oField = umiFieldsCollection::getField($iFieldId);
			if(!$oField) continue;
			$sColumn = umiFieldType::getDataTypeDB($oField->getFieldType()->getDataType());
			if(!$sColumn) continue;
			
			$sCustomExpression = "{$sColumn} {$sOperator} {$sValue}";
			
			if(in_array($sColumn, array("int_val", "float_val", "rel_val", "tree_val")) && $sValue == 0) {
				$sCustomExpression = "({$sCustomExpression} OR {$sColumn} IS NULL)";
			}
			
			$sSQL .= " AND `coc`.`obj_id` IN (SELECT `coc{$i}`.`obj_id` FROM `{$this->sContentTable}` AS `coc{$i}` WHERE `coc{$i}`.`field_id` = {$iFieldId}  AND {$sCustomExpression})";
		}
		
		return $sSQL;
	}
	
	
	/**
	 * Применение выбранных фильтров для подсчёта кол-ва
	 *
	 * @param umiSelection $selection Объект umiSelection
	 */
	public function applySelectedCountFilters(&$selection) {
		foreach($_REQUEST as $key => $value) {
			if(preg_match("/^filter\-[\d]+$/i", $key)) {
				$iFieldId = substr($key, strpos($key, "-") + 1);
				
				$value = (is_array($value)) ? $value : array($value);
				
				$arEquals = array();
				foreach($value as $type => $val) {
					if($type === 'gt') {
						$selection->addPropertyFilterMore($iFieldId, $val);
					} elseif($type === 'lt') {
						$selection->addPropertyFilterLess($iFieldId, $val);
					} elseif(is_numeric($type)) {
						$arEquals[] = $val;
					}
				}
				
				$selection->addPropertyFilterEqual($iFieldId, $arEquals);
			}
		}
	}
	
	/**
	 * Применение кастомной логики к объекту umiSelection
	 *
	 * @param umiSelection $selection Объект umiSelection
	 */
	public function applyCustomCountFilters(&$selection) {
		$arCustomFilters = $this->customLogic->filters();
		$oType = umiObjectTypesCollection::getInstance()->getType($this->objectsTypeId);
		if(!$oType) return;
		
		foreach($arCustomFilters as $arCustomFilter) {
			$iFieldId = $oType->getFieldId($arCustomFilter[0]);
			
			$sOperator = $arCustomFilter[1];
			
			$value = $arCustomFilter[2];
			
			switch($sOperator) {
				case "eq": {
					$selection->addPropertyFilterEqual($iFieldId, $value);
				} break;
				case "noteq": {
					$selection->addPropertyFilterNotEqual($iFieldId, $value);
				} break;
				case "null": {
					$selection->addPropertyFilterIsNull($iFieldId);
				} break;
				case "notnull": {
					$selection->addPropertyFilterIsNotNull($iFieldId);
				} break;
				case "gt": {
					$selection->addPropertyFilterMore($iFieldId, $value);
				} break;
				case "lt": {
					$selection->addPropertyFilterLess($iFieldId, $value);
				} break;
				case "like": {
					$selection->addPropertyFilterLike($iFieldId, $value);
				} break; 
			}
		}
	}
	
	/**
	 * Парсинг ключа [in] в параметрах запроса k_fields_filter
	 *
	 * @param umiSelection &$selection Объект umiSelection
	 */
	public function applyInFilters(&$selection) {
		$type = umiObjectTypesCollection::getInstance()->getType($this->objectsTypeId);
		
		if(!isset($_REQUEST['k_fields_filter'])) return;
		foreach($_REQUEST['k_fields_filter'] as $field_name => $arFilter) {
			if(!is_array($arFilter)) continue;
			foreach($arFilter as $sType => $values) {
				if($sType != 'in') continue;
				$field_id = $type->getFieldId($field_name);
				if(!$field_id) continue;
				$selection->addPropertyFilterEqual($field_id, $values);
			}
		}
	}
	
	public function getSelectionObject() {
		$hierarchy_type_id = umiHierarchyTypesCollection::getInstance()->getTypeByName("catalog", "object")->getId();
		
		$sel = new umiSelection;
		$sel->setElementTypeFilter();
		$sel->addElementType($hierarchy_type_id);
		
		$sel->setHierarchyFilter();
		$sel->addHierarchyFilter($this->catalogId, 1);
		
		$sel->setPermissionsFilter();
		$sel->addPermissions();
		
		//add custom filters
		kFilters::getInstance($this->catalogId)->applyCustomCountFilters($sel);
		
		return $sel;
	}

	/**
	 * Создать папку рекурсивно со всеми подпапками, с выставлением прав 0777
	 * @param string $target_dir Папка, в которой создаётся новая папка
	 * @param string $dir Создаваемая папка
	 */
	protected function createDirRecurent($target_dir, $dir) {
		$dir = str_replace('\\', '/', $dir);
		$dir = trim($dir, '/ ');
		if($dir == '') return;
		$arDir = explode('/', $dir);
		clearstatcache();
		$subdir = array_shift($arDir);
		if(!is_dir($target_dir . "/" . $subdir)) {
			mkdir($target_dir . "/" . $subdir);
			@chmod($target_dir . "/" . $subdir, 0777);
		}
		$this->createDirRecurent($target_dir . "/" . $subdir, join('/', $arDir));
	}

	/**
	 * Сохранить json-ответ на действие в файл в папке /js/kfilters/kJsonCache
	 * @param string $json
	 */
	public function saveJsonCache($json) {
		$jsonCacheRoot = CURRENT_WORKING_DIR . "/js/kfilters/kJsonCache";
		
		if(!is_dir($jsonCacheRoot)) {
			mkdir($jsonCacheRoot);
			@chmod($jsonCacheRoot, 0777);
		}
		
		$jsonCacheCatalogActionFilterRoot = str_replace('?', '', trim(getServer('REQUEST_URI'), '/ '));
		$this->createDirRecurent($jsonCacheRoot, $jsonCacheCatalogActionFilterRoot);
		
		file_put_contents($jsonCacheRoot . '/' . $jsonCacheCatalogActionFilterRoot . "/index.html", $json);
		@chmod($jsonCacheRoot . '/' . $jsonCacheCatalogActionFilterRoot . "/index.html", 0666);
	}
};

?>