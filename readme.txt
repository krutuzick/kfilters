Для подключения:
1. Backend.
  1.1) Разместить папку kFilters в classes/modules/catalog (так же, как она лежит тут)
  1.2) Создать кастом catalog/kfilters:
	/**
	 * Proxy for kFilters widget
	 */
	public function kfilters($sAction = false, $sCategoryId = false) {
		$sAction = ($sAction) ? $sAction : getRequest('param0');
		$sCategoryId = ($sCategoryId) ? $sCategoryId : getRequest('param1');
		
		if(!class_exists('kFilters')) require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'kfilters' . DIRECTORY_SEPARATOR . 'kFilters.php');
		
		$filtersResult = kFilters::getInstance($sCategoryId)->runAction($sAction);
		
		return def_module::flush($filtersResult, "text/javascript");
	}
  1.3) В макросе отображения товаров (будь то кастом или системный) - надо найти место, где формируется выборка (объект класса umiSelection) и добавить туда код: 
    if(!class_exists('kFilters')) require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'kfilters' . DIRECTORY_SEPARATOR . 'kFilters.php');
    kFilters::getInstance($category_id)->applyInFilters($sel);
  где $sel - это и есть объект umiSelection. Этот код позволяет реализовать условие равенства нескольким критериям через get-запрос (fields_filter)
  1.4) Надо добавить permission для кастома catalog::kfilters, доступный для всех - это макрос, на который обращается виджет с front-end-а
  1.5) В customLogic надо указать группы полей и прочие настройки данных
2. Front-end.
  2.1) Необходимо разместить в папку js на сайте. Проект использует jquery (нет в архиве - должен быть на сайте), jqueryUI, jquery qTip
  2.2) В вёрстке надо разместить пустой div (скажем, с id=kFiltersBlock) и подключить виджет в тэге head следующим образом:
	
	<!-- kFilters includes - start -->
	<link rel="stylesheet" href="/js/jqueryui/css/ui-lightness/jquery-ui-1.8.16.custom.css" type="text/css" />
	<link rel="stylesheet" href="/js/kfilters/style.css" type="text/css" />

	<script type="text/javascript" src="/js/jqueryui/js/jquery-ui-1.8.16.custom.min.js" charset="utf-8"></script>
	<script type="text/javascript" src="/js/jqueryqtip/jquery.qtip-1.0.0-rc3.min.js" charset="utf-8"></script>
	<script type="text/javascript" src="/js/kfilters/kfilters.js" charset="utf-8"></script>

	<script type='text/javascript'>
	jQuery(document).ready(function($) {
		$.fn.qtip.styles.kTips.tip = "topRight";
		$.fn.qtip.styles.kCountTips.tip = "rightMiddle";
		$('#kFiltersBlock').kFilters({
			width: '280px',
			'min-height': '80px',
			category_id: 30318,
			tipPosition: {
				tooltip: 'topRight',
				target: 'bottomLeft'
			},
			countTipPosition: {
				target: 'leftMiddle',
				tooltip: 'rightMiddle'
			}
			//,debug: true
		});
	});
	</script>
	<!-- kFilters includes - end -->
	
3. Settings
  Виджет гибко настраивается под различные нужды, как со стороны front-end, так и со стороны backend. 
  3.1) Front-end - для стилей можно использовать /js/kfilters/style.css . Кроме того, внешний вид настраивается и в момент вызова виджета.
  Доступные опции можно увидеть в свойстве settings в файле /js/kfilters/kfilters.js (там с комментариями расписаны они). Там же есть 
  гловальные настройки для всплывающих подсказок: kTips и kCountTips (посказки, если что, берутся из базы юми из подсказок к полям в шаблонах данных)
  3.2) Back-end - кастомизация и настройки разполагаются в файле classes/modules/catalog/kFilters/kFiltersCustomLogic.php  . 
  Менять можно методы (кроме __construct). 
  В объекте доступны свойства $this->catalogId (ID категории каталога, для которой строятся фильтры) и $this->objectsTypeId (ID типа объектов каталога, преобладающих в категории (для которых строятся фильтры))
  Достпно окружение  umi.cms.
 