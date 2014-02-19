<?php

/**
 * Класс действия GetFilters
 * Получение списка фильтров в виде массива json-объектов по нотации:
 * {
 *     config: {...},
 *     data: {type: "boolean|number|option|string", name: "fieldname", title: "Заголовок поля", tip: "Подсказка дял поля", values: [значение, значение, ...], selected: <текущие выбранные значения - формат зависит от типа поля>, id: <id поля данных>}, //Кроме того, в зависимости от конфига и типа поля может присутствовать информация об активности элемента
 *     expanded: [] //список полей, которые должны быть раскрыты
 */
class kFiltersAction_GetFilters extends kFiltersAction {
	
	protected $sContentTable = 'cms3_object_content';
	
	public function run() {
		$oCache = kFilters::getInstance($this->catalogId)->getCache();
		$arCustomFilters = kFilters::getInstance($this->catalogId)->customLogic->filters();
		$sCustomFilters = (empty($arCustomFilters)) ? 0 : serialize($arCustomFilters);
		$arHiddenFields = kFilters::getInstance($this->catalogId)->customLogic->hiddenFields();
		$sHiddenFields = (empty($arHiddenFields)) ? 0 : serialize($arHiddenFields);
		
		$arFiltersData = $oCache->getCategory($this->catalogId, "{$sCustomFilters}{$sHiddenFields}");
		if(is_null($arFiltersData)) {
			$this->sContentTable = kFilters::getInstance($this->catalogId)->sContentTable;
			$arFiltersData = $this->getFiltersArray();
			$oCache->setCategory($arFiltersData, $this->catalogId, "{$sCustomFilters}{$sHiddenFields}");
		}
		
		$arHiddenFields = kFilters::getInstance($this->catalogId)->customLogic->hiddenFields();
		$arFiltered = array();
		foreach($arFiltersData as $i => $arFilter) {
			$bOk = true;
			foreach($arHiddenFields as $sHidden) {
				if(trim($arFilter['name']) == trim($sHidden)) {
					$bOk = false;
				}
			}
			if($bOk) {
				$arFiltered[] = $arFilter;
			}
		}
		$arFiltersData = $arFiltered;
		
		foreach($arFiltersData as $i => $arFilter) {
			$this->setSelected($arFiltersData[$i]);
		}
		
		if(kFilters::getInstance($this->catalogId)->customLogic->showStateOnLoad()) {
			//setting state
			foreach($arFiltersData as $j => $arFilter) {
				$this->setState($arFiltersData[$j]);
			}
		}
		
		$config = array(
			'activeonload' => kFilters::getInstance($this->catalogId)->customLogic->showStateOnLoad(),
			'activeoncount' => kFilters::getInstance($this->catalogId)->customLogic->showStateOnCount(),
		);
		
		$arExpanded = kFilters::getInstance($this->catalogId)->customLogic->expanded();
		
		return json_encode(array('config' => $config, 'data' => $arFiltersData, 'expanded' => $arExpanded));
	}
	
	/**
	 * Установка фильтров в выбранное на текущий момент состояние
	 */
	protected function setSelected(&$arField) {
		$fields_filter = (isset($_REQUEST['fields_filter'])) ? $_REQUEST['fields_filter'] : array();
		$k_fields_filter = (isset($_REQUEST['k_fields_filter'])) ? $_REQUEST['k_fields_filter'] : array();
		$field_name = $arField['name'];
		switch($arField['type']) {
			case "number": {
				$iSelectedMin = $arField['values'][0];
				$iSelectedMax = $arField['values'][1];
				if(isset($fields_filter[$field_name])) {
					if(isset($fields_filter[$field_name]['lt'])) {
						$iSelectedMax = $fields_filter[$field_name]['lt'];
					}
					if(isset($fields_filter[$field_name]['gt'])) {
						$iSelectedMin = $fields_filter[$field_name]['gt'];
					}
				}
				
				$arField['selected'] = array('min' => $iSelectedMin, 'max' => $iSelectedMax);
			} break;
			
			case "boolean": {
				$bSelected = false;
				if(isset($fields_filter[$field_name])) {
					$bSelected = true;
				}
				$arField['selected'] = $bSelected;
			} break;
			
			case "option": {
				$arSelected = array();

				if(isset($k_fields_filter[$field_name])) {
					if(isset($k_fields_filter[$field_name]['in'])) {
						if(is_array($k_fields_filter[$field_name]['in'])) {
							$arSelected = $k_fields_filter[$field_name]['in'];
						} else {
							$arSelected[] = $k_fields_filter[$field_name]['in'];
						}
					} else {
						if(is_array($k_fields_filter[$field_name])) {
							$arSelected = $k_fields_filter[$field_name];
						} else {
							$arSelected[] = $k_fields_filter[$field_name];
						}
					}
				}
				$arField['selected'] = $arSelected;
			} break;
		}
	}
	
	/**
	 * Установка активности поля в зависимости от набранных фильтров
	 */
	protected function setState(&$arField) {
		$bNeedRestoreFields = $bNeedRestoreKFields = false;
		$fields_filter = (isset($_REQUEST['fields_filter'])) ? $_REQUEST['fields_filter'] : array();
		$k_fields_filter = (isset($_REQUEST['k_fields_filter'])) ? $_REQUEST['k_fields_filter'] : array();
		$field_name = $arField['name'];
		
		if(isset($fields_filter[$field_name])) {
			$bNeedRestoreFields = $fields_filter[$field_name];
			unset($_REQUEST['fields_filter'][$field_name]);
		}
		if(isset($k_fields_filter[$field_name])) {
			$bNeedRestoreKFields = $k_fields_filter[$field_name];
			unset($_REQUEST['k_fields_filter'][$field_name]);
		}
		
		$iFieldId = $arField['id'];
		
		switch($arField['type']) {
			case "boolean": {
				$sel = $this->getCurrentCountSelection();
				$sel->addPropertyFilterEqual($iFieldId, 1);
				$sqls = umiSelectionsParser::parseSelection($sel);
				$query_key = trim(str_replace(array("\n", "\r", "\t", " "), "", $sqls['count']));
				$count = 0;//Count of goods with this value, with selected filters but without filering on this field
				if($query_key) {
					$oCache = kFilters::getInstance($this->catalogId)->getCache();
					//try load from cache
					$count = $oCache->getQuery($query_key, $this->catalogId);
					if(is_null($count)) {
						$count = umiSelectionsParser::runSelectionCounts($sel);
						//save to cache
						$oCache->saveQuery($count, $query_key, $this->catalogId);
					}
				}
				
				if($count == 0) {
					$arField['active'] = false;
				} else {
					$arField['active'] = true;
				}
			} break;
			case "option": {
				$arValues = $arField['values'];
				foreach($arValues as $i => $arValue) {
					$sel = $this->getCurrentCountSelection();
					$sel->addPropertyFilterEqual($iFieldId, $arValue['id']);
					$sqls = umiSelectionsParser::parseSelection($sel);
					$query_key = trim(str_replace(array("\n", "\r", "\t", " "), "", $sqls['count']));
					$count = 0;//Count of goods with this value, with selected filters but without filering on this field
					if($query_key) {
						$oCache = kFilters::getInstance($this->catalogId)->getCache();
						//try load from cache
						$count = $oCache->getQuery($query_key, $this->catalogId);
						if(is_null($count)) {
							$count = umiSelectionsParser::runSelectionCounts($sel);
							//save to cache
							$oCache->saveQuery($count, $query_key, $this->catalogId);
						}
					}
					if($count == 0) {
						$arField['values'][$i]['active'] = false;
					} else {
						$arField['values'][$i]['active'] = true;
					}
				}
			} break;
		}
		
		if($bNeedRestoreFields !== false) {
			$_REQUEST['fields_filter'][$field_name] = $bNeedRestoreFields;
		}
		if($bNeedRestoreKFields !== false) {
			$_REQUEST['k_fields_filter'][$field_name] = $bNeedRestoreKFields;
		}
	}
	
	/**
	 * Получение объекта umiSelection, настроенного на выборку с учётом текущих фильтров
	 */
	protected function getCurrentCountSelection() {
		$hierarchy_type_id = umiHierarchyTypesCollection::getInstance()->getTypeByName("catalog", "object")->getId();
		$hierarchy_type = umiHierarchyTypesCollection::getInstance()->getType($hierarchy_type_id);
		$type_id = umiObjectTypesCollection::getInstance()->getBaseType($hierarchy_type->getName(), $hierarchy_type->getExt());
		
		$sel = kFilters::getInstance($this->catalogId)->getSelectionObject();
		
		$oCatalogModule = cmsController::getInstance()->getModule('catalog');
		if($oCatalogModule) {
			$oCatalogModule->autoDetectFilters($sel, $type_id);
		}
		kFilters::getInstance($this->catalogId)->applyInFilters($sel);
		
		return $sel;
	}
	
	/**
	 * Получение списка фильтруемых полей из базы данных
	 */
	protected function getFiltersArray() {
		set_time_limit(0);
		$arFilters = array();
		$iTypeId = umiHierarchy::getInstance()->getDominantTypeId($this->catalogId);
		$oType = umiObjectTypesCollection::getInstance()->getType($iTypeId);
		if(!$oType) return $arFilters;
		
		
		//получить отсортированный список полей из групп kFilters::getInstance($this->catalogId)->customLogic->groupNames(); и обработать его
		$arGroupNames = kFilters::getInstance($this->catalogId)->customLogic->groupNames();
		foreach($arGroupNames as $sGroupName) {
			$oGroup = $oType->getFieldsGroupByName($sGroupName);
			$arFields = $oGroup->getFields();
			//обработать каждое поле - есть ли видимые товары с учётом кастомных фильтров
			//обработать каждое поле - установить текущее значение
			foreach($arFields as $oField) {
				$arFilterField = $this->getFilterField($oField);
				if(!is_null($arFilterField)) {
					$arFilters[] = $arFilterField;
				}
				
			}
		}
		
		return $arFilters;
	}
	
	/**
	 * Фабричный метод для получения массива фильтра для поля по его типу (вызывается метод по шаблону "getFilterField_типполя")
	 */
	protected function getFilterField($oField) {
		$arField = null;
		$oFieldType = $oField->getFieldType();
		$sPrepareMethod = "getFilterField_{$oFieldType->getDataType()}";
		if(method_exists($this, $sPrepareMethod)) {
			$arField = $this->$sPrepareMethod($oField);
		}
		
		return $arField;
	}
	
	/**
	 * Фильтр для поля типа int
	 */
	protected function getFilterField_int($oField) {
		$arField = array();
		
		$arField['id'] = $oField->getId();
		$arField['type'] = 'number';
		$arField['name'] = $oField->getName();
		$arField['title'] = $oField->getTitle();
		$arField['tip'] = $oField->getTip();
		
		//отфильтровать по имеющимся значениям и кастомным фильтрам
		$iValueMin = $this->getNumberExtremum($oField, "MIN");
		$iValueMax = $this->getNumberExtremum($oField, "MAX");
		
		//Если в товарах только одно значение поля - то нет смысла отображать этот фильтр
		$omitNoChoice = kFilters::getInstance($this->catalogId)->customLogic->omitNoChoice($oField);
		if($iValueMin == $iValueMax && ($omitNoChoice || $iValueMax == 0)) return null;
		
		$arField['values'] = array($iValueMin, $iValueMax);
		
		$arField['selected'] = array('min' => $iValueMin, 'max' => $iValueMax);
		
		return $arField;
	}
	
	/**
	 * Фильтр для поля типа float
	 */
	protected function getFilterField_float($oField) {
		return $this->getFilterField_int($oField);
	}
	
	/**
	 * Фильтр для поля типа price
	 */
	protected function getFilterField_price($oField) {
		return $this->getFilterField_float($oField);
	}
	
	/**
	 * Фильтр для поля типа symlink
	 */
	protected function getFilterField_symlink($oField) {
		return $this->getFilterField_relation($oField);
	}
	
	/**
	 * Фильтр для поля типа boolean
	 */
	protected function getFilterField_boolean($oField) {
		$arField = array();
		
		$arField['id'] = $oField->getId();
		$arField['type'] = 'boolean';
		$arField['name'] = $oField->getName();
		$arField['title'] = $oField->getTitle();
		$arField['tip'] = $oField->getTip();
		
		//отфильтровать по имеющимся значениям и кастомным фильтрам
		$values = $this->getBooleanValues($oField);
		
		//Если все объекты в категории имеют это поле в одном и том же значении - нет смысла отображать этот фильтр
		$omitNoChoice = kFilters::getInstance($this->catalogId)->customLogic->omitNoChoice($oField);
		if(count($values) <= 1 && $omitNoChoice) return null;
		
		$arField['values'] = $values;
		
		$arField['selected'] = false;
		
		return $arField;
	}
	
	/**
	 * Фильтр для поля типа relation
	 */
	protected function getFilterField_relation($oField) {
		$arField = array();
		
		$arField['id'] = $oField->getId();
		$arField['type'] = 'option';
		$arField['name'] = $oField->getName();
		$arField['title'] = $oField->getTitle();
		$arField['tip'] = $oField->getTip();
		
		//отфильтровать по имеющимся значениям и кастомным фильтрам
		$values = $this->getRelationValues($oField);
		
		//Если для этого поля нет вариантов, то отображать этот фильтр не имеет смысла
		$omitNoChoice = kFilters::getInstance($this->catalogId)->customLogic->omitNoChoice($oField);
		if(count($values) <= 1 && $omitNoChoice) return null;
		
		$arField['values'] = $values;
		
		$arField['selected'] = array();
		
		return $arField;
	}


	/**
	 * Сформировать массив со значениями, необходимыми для построения sql-запросов для работы с полями
	 * 
	 * @return array Массив: array($sColumn, $iFieldId, $sActivePagesSubquery, $sCustomFiltersSubquery)
	 */
	protected function getPreparedSQLValues($oField) {
		$sColumn = umiFieldType::getDataTypeDB($oField->getFieldType()->getDataType());
		
		$iFieldId = $oField->getId();
		
		$sActivePagesSubquery = kFilters::getInstance($this->catalogId)->getSQL_activePages();
		$sActivePagesSubquery = ($sActivePagesSubquery == "") ? "" : "AND `obj_id` IN ({$sActivePagesSubquery})";
		
		$sCustomFiltersSubquery = kFilters::getInstance($this->catalogId)->getSQL_customFilters();
		$sCustomFiltersSubquery = ($sCustomFiltersSubquery == "") ? "" : "AND `obj_id` IN ({$sCustomFiltersSubquery})";
		
		return array($sColumn, $iFieldId, $sActivePagesSubquery, $sCustomFiltersSubquery);
	}
	
	/**
	 * Получить экстремум для числового значения поля у страниц в категории
	 */
	protected function getNumberExtremum($oField, $sqlFunc = "MIN") {
		list($sColumn, $iFieldId, $sActivePagesSubquery, $sCustomFiltersSubquery) = $this->getPreparedSQLValues($oField);
		if(!$sColumn) return 0;
		
		$sql = <<<SQL
			SELECT {$sqlFunc}({$sColumn}) AS extremumVal FROM `{$this->sContentTable}`
			WHERE `field_id` = {$iFieldId} {$sActivePagesSubquery} {$sCustomFiltersSubquery} 
SQL;
		$result = l_mysql_query($sql);
		list($extremum) = mysql_fetch_row($result);
		$extremum = (int) $extremum;
		
		return $extremum;
	}
	
	/**
	 * Получить значения для булева поля у страниц в категории
	 */ 
	protected function getBooleanValues($oField) {
		list($sColumn, $iFieldId, $sActivePagesSubquery, $sCustomFiltersSubquery) = $this->getPreparedSQLValues($oField);
		if(!$sColumn) return array(false);
		
		$sql = <<<SQL
			SELECT DISTINCT {$sColumn} FROM `{$this->sContentTable}` 
			WHERE `field_id` = {$iFieldId} {$sActivePagesSubquery} {$sCustomFiltersSubquery}
SQL;
		$result = l_mysql_query($sql);
		$arResult = array();
		while(list($bool_value) = mysql_fetch_row($result)) {
			if(is_null($bool_value) || $bool_value == 0) {
				$arResult[] = false;
			} else {
				$arResult[] = true;
			}
		}
		$arResult = array_unique($arResult);
		
		
		return $arResult;
	}
	
	/**
	 * Получить значения для ссылочного поля у страниц в категории
	 */ 
	protected function getRelationValues($oField) {
		list($sColumn, $iFieldId, $sActivePagesSubquery, $sCustomFiltersSubquery) = $this->getPreparedSQLValues($oField);
		if(!$sColumn) return array();
		
		$sql = <<<SQL
			SELECT DISTINCT {$sColumn} FROM `{$this->sContentTable}`
			WHERE `field_id` = {$iFieldId} {$sActivePagesSubquery} {$sCustomFiltersSubquery}
SQL;
		$result = l_mysql_query($sql);
		$arResult = array();
		while(list($rel_value) = mysql_fetch_row($result)) {
			if(is_null($rel_value)) {
				if(kFilters::getInstance($this->catalogId)->customLogic->relationNullValue($oField) !== false) {
					$arResult[0] = array("id" => '', "value" => kFilters::getInstance($this->catalogId)->customLogic->relationNullValue($oField));
				}
			} else {
				$obj = false;
				if($sColumn == 'rel_val') {
					$obj = umiObjectsCollection::getInstance()->getObject($rel_value);
				} elseif($sColumn == 'tree_val') {
					$obj = umiHierarchy::getInstance()->getElement($rel_value);
				}
				if(!$obj) continue;
				
				$arResult[$obj->getName()] = array("id" => $rel_value, "value" => $obj->getName());
			}
		}
		$arKeys = array_keys($arResult);
		sort($arKeys);
		$arSorted = array();
		foreach($arKeys as $arResultKey) {
			$arSorted[] = $arResult[$arResultKey];
		}
		
		return $arSorted;
	}
}

?>