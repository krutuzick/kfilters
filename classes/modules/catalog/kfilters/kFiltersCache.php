<?php

/**
 * Клас для работы с кэшем фильтров kFilters
 * Кэш представляет из себя массив, в котором ключ - это id категории, значение - это массив, у которого
 *   ключ - это сериализованный массив с кастомными фильтрами, значение - массив, содержащий информацию для фильтров
 * Кроме того, кэш умеет хранить результаты sql-запросов в файле $_sQueryCacheFile . Для этого используются методы getQuery и saveQuery
 */
class kFiltersCache {
	
	/**
	 * Путь до папки с кэшем
	 * @var string
	 */
	protected $_sCacheRoot;
	
	/**
	 * Хранилище кэша
	 * @var array
	 */
	protected $_arCache = array();
	
	/**
	 * Хранилище кэша SQL-запросов
	 * @var array
	 */
	protected $_arQueryCache = array();
	
	/**
	 * Имя файла, в котором находится кэш SQL-запросов
	 * @var string
	 */
	protected $_sQueryCacheFile = "queries";
	
	
	public function __construct() {
		$this->_sCacheRoot = dirname(__FILE__) . DIRECTORY_SEPARATOR . "kCache";
		
		if(!is_dir($this->_sCacheRoot)) {
			@mkdir($this->_sCacheRoot);
		}
	}
	
	/**
	 * Сбросить все фильтры
	 */
	public function flushFilters() {
		$this->_arCache = array();
		$arFiles = glob($this->_sCacheRoot . DIRECTORY_SEPARATOR . "*");
		foreach($arFiles as $sPathToFile) {
			unlink($sPathToFile);
		}
	}
	
	/**
	 * Получить массив фильтров для категории
	 *
	 * @param int $iCategoryId ID категории
	 * @param mixed $sCustom Сериализованный массив кастомных ограничений (или 0 если их нет)
	 * @return mixed Array или null
	 */
	public function getCategory($iCategoryId, $sCustom = 0) {
		if(!isset($this->_arCache[$iCategoryId])) {
			$arBuf = $this->getCacheFileContent($iCategoryId);
			if(empty($arBuf)) {
				unset($arBuf);
				return null;
			} else {
				$this->_arCache[$iCategoryId] = $arBuf;
				unset($arBuf);
			}
		}
		
		if(!isset($this->_arCache[$iCategoryId][$sCustom])) {
			return null;
		} else {
			return $this->_arCache[$iCategoryId][$sCustom];
		}
	}
	
	/**
	 * Сохранить кэш для категории
	 *
	 * @param mixed $arData Массив, который нужно сохранить в кэш
	 * @param mixed $iCategoryId ID категории, для которой надо сохранить фильтры
	 * @param mixed $sCustom Сериализованный массив кастомных ограничений (или 0 если их нет)
	 */
	public function setCategory($arData, $iCategoryId, $sCustom = 0) {
		//Сохраняем в памяти
		if(!isset($this->_arCache[$iCategoryId])) {
			$this->_arCache[$iCategoryId] = array();
		}
		$this->_arCache[$iCategoryId][$sCustom] = $arData;
		
		//Сохраняем в файле
		$arBuf = $this->getCacheFileContent($iCategoryId);
		$arBuf[$sCustom] = $arData;
		$this->setCacheFileContent($arBuf, $iCategoryId);
		unset($arBuf);
	}
	
	/**
	 * Удалить кэш для категории
	 *
	 * @param mixed $iCategoryId ID категории, для которой надо удалить кэш
	 * @param mixed $sCustom Сериализованный массив кастомных ограничений (если false - удалится кэш для всей категории)
	 */
	public function unsetCategory($iCategoryId, $sCustom = false) {
		//Удаляем из памяти
		if(isset($this->_arCache[$iCategoryId])) {
			if($sCustom !== false) {
				if(isset($this->_arCache[$iCategoryId][$sCustom])) {
					unset($this->_arCache[$iCategoryId][$sCustom]);
				}
			} else {
				unset($this->_arCache[$iCategoryId]);
			}
		}
		
		//Удаляем из файла
		if($sCustom === false) {
			$this->setCacheFileContent(array(), $iCategoryId);
		} else {
			$arBuf = $this->getCacheFileContent($iCategoryId);
			if(isset($arBuf[$sCustom])) {
				unset($arBuf[$sCustom]);
				$this->setCacheFileContent($arBuf, $iCategoryId);
			}
			unset($arBuf);
		}
	}
	
	/**
	 * Получить закэшированный результат выполнения SQL запроса
	 *
	 * @param mixed $sQuery SQL-запрос
	 * @param mixed $iCategoryId ID категории (для разбиения по файлам)
	 */
	public function getQuery($sQuery, $iCategoryId = "") {
		if(!isset($this->_arQueryCache[$sQuery])) {
			$arBuf = $this->getCacheFileContent($this->_sQueryCacheFile . $iCategoryId);
			if(empty($arBuf)) {
				unset($arBuf);
				return null;
			} else {
				$this->_arQueryCache = $arBuf;
				unset($arBuf);
			}
		}
		
		if(!isset($this->_arQueryCache[$sQuery])) {
			return null;
		} else {
			return $this->_arQueryCache[$sQuery];
		}
	}
	
	/**
	 * Сохранить результат выполнения SQL-запроса в кэше
	 *
	 * @param mixed $arData Данные для сохранения
	 * @param mixed $sQuery SQL-запрос
	 * @param mixed $iCategoryId ID категории (для разбиения по файлам)
	 */
	public function saveQuery($arData, $sQuery, $iCategoryId = "") {
		//Сохраняем в памяти
		$this->_arQueryCache[$sQuery] = $arData;
		
		//Сохраняем в файле
		$arBuf = $this->getCacheFileContent($this->_sQueryCacheFile . $iCategoryId);
		$arBuf[$sQuery] = $arData;
		$this->setCacheFileContent($arBuf, $this->_sQueryCacheFile . $iCategoryId);
		unset($arBuf);
	}
	
	/**
	 * Получает содержимое файла кэша (+ создаёт его, если такого файла не существует)
	 *
	 * @param mixed $iCategoryId ID категории
	 */
	protected function getCacheFileContent($iCategoryId) {
		$sFile = $this->_sCacheRoot . DIRECTORY_SEPARATOR . $iCategoryId . ".dat";
		if(!file_exists($sFile)) {
			$this->setCacheFileContent(array(), $iCategoryId);
		}
		
		return unserialize(file_get_contents($sFile));
	}
	
	/**
	 * Сохранить данные в файле кэша
	 *
	 * @param mixed $arData Данные
	 * @param mixed $iCategoryId ID категории
	 */
	protected function setCacheFileContent($arData, $iCategoryId) {
		$sFile = $this->_sCacheRoot . DIRECTORY_SEPARATOR . $iCategoryId . ".dat";
		file_put_contents($sFile, serialize($arData));
	}

};

?>