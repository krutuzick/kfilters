<?php

/**
 * Класс действия GetCount
 * Получение кол-ва объектов, удовлетворяющих условию в json по нотации:
 * {
 *     filters: [{...}], //фильтры с активностью или пустой массив
 *     count: начение
 * }
 */
class kFiltersAction_GetCount extends kFiltersAction {
	
	
	public function run() {
		return json_encode(array(
			'filters' => $this->getFilters(),
			'count' => $this->getCount()
		));
	}
	
	protected function getCount() {
		$sel = kFilters::getInstance($this->catalogId)->getSelectionObject();
		
		//add selected filters 
		kFilters::getInstance($this->catalogId)->applySelectedCountFilters($sel);
		
		$sqls = umiSelectionsParser::parseSelection($sel);
		$query_key = trim(str_replace(array("\n", "\r", "\t", " "), "", $sqls['count']));
		$total = 0;
		if($query_key) {
			$oCache = kFilters::getInstance($this->catalogId)->getCache();
			$total = $oCache->getQuery($query_key, $this->catalogId);
			if(is_null($total)) {
				$total = umiSelectionsParser::runSelectionCounts($sel);
				$oCache->saveQuery($total, $query_key, $this->catalogId);
			}
		}
		
		return $total;
	}
	
	protected function getFilters() {
		if(!kFilters::getInstance($this->catalogId)->customLogic->showStateOnCount()) return array();
		
		$oCache = kFilters::getInstance($this->catalogId)->getCache();
		$arCustomFilters = kFilters::getInstance($this->catalogId)->customLogic->filters();
		$sCustomFilters = (empty($arCustomFilters)) ? 0 : serialize($arCustomFilters);
		$arHiddenFields = kFilters::getInstance($this->catalogId)->customLogic->hiddenFields();
		$sHiddenFields = (empty($arHiddenFields)) ? 0 : serialize($arHiddenFields);
		
		$arFiltersData = $oCache->getCategory($this->catalogId, "{$sCustomFilters}{$sHiddenFields}");
		if(is_null($arFiltersData)) return array();
		
		foreach($arFiltersData as $j => $arFilter) {
			$this->setState($arFiltersData[$j]);
		}
		
		return $arFiltersData;
	}
	
	protected function setState(&$arField) {
		$iFieldId = $arField['id'];
		$paramName = "filter-{$iFieldId}";
		
		$bNeedRestoreFields = false;
		$fields_filter = (is_array($_REQUEST)) ? $_REQUEST : array();
		
		if(isset($fields_filter[$paramName])) {
			$bNeedRestoreFields = $fields_filter[$paramName];
			unset($_REQUEST[$paramName]);
		}
		
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
						if(is_null($count) || true) {
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
			$_REQUEST[$paramName] = $bNeedRestoreFields;
		}
		
	}
	
	protected function getCurrentCountSelection() {
		$hierarchy_type_id = umiHierarchyTypesCollection::getInstance()->getTypeByName("catalog", "object")->getId();
		$hierarchy_type = umiHierarchyTypesCollection::getInstance()->getType($hierarchy_type_id);
		$type_id = umiObjectTypesCollection::getInstance()->getBaseType($hierarchy_type->getName(), $hierarchy_type->getExt());
		
		$sel = kFilters::getInstance($this->catalogId)->getSelectionObject();
		
		kFilters::getInstance($this->catalogId)->applySelectedCountFilters($sel);
		
		return $sel;
	}
}

?>