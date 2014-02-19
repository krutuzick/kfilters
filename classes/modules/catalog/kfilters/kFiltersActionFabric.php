<?php

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'kFiltersAction.php');

/**
 * Абстрактная фабрика действий для взамодействия с интерфейсом.
 * Каждое действие представлено классом-потомком абстрактного базового класса kFiltersAction
 * Название класса действия формируется по шаблону kFiltersAction_<название действия>, например, kFiltersAction_GetFilters
 */
class kFiltersActionFabric {
	/**
	 * Получение экземпляра объекта действия
	 *
	 * @param string $actionName Название действия
	 * @param int|string $iCatalogId ID каталога
	 * @return kFiltersAction Объект действия
	 */
	public static function getAction($actionName, $iCatalogId = 0) {
		$oAction = null;
		$sClassActionName = "kFiltersAction_{$actionName}";
		if(!class_exists($sClassActionName) && file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $sClassActionName . '.php')) require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . $sClassActionName . '.php');
		if(class_exists($sClassActionName)) $oAction = new $sClassActionName($iCatalogId);
		
		return $oAction;
	}
}

?>