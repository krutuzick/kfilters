��� �����������:
1. Backend.
  1.1) ���������� ����� kFilters � classes/modules/catalog (��� ��, ��� ��� ����� ���)
  1.2) ������� ������ catalog/kfilters:
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
  1.3) � ������� ����������� ������� (���� �� ������ ��� ���������) - ���� ����� �����, ��� ����������� ������� (������ ������ umiSelection) � �������� ���� ���: 
    if(!class_exists('kFilters')) require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'kfilters' . DIRECTORY_SEPARATOR . 'kFilters.php');
    kFilters::getInstance($category_id)->applyInFilters($sel);
  ��� $sel - ��� � ���� ������ umiSelection. ���� ��� ��������� ����������� ������� ��������� ���������� ��������� ����� get-������ (fields_filter)
  1.4) ���� �������� permission ��� ������� catalog::kfilters, ��������� ��� ���� - ��� ������, �� ������� ���������� ������ � front-end-�
  1.5) � customLogic ���� ������� ������ ����� � ������ ��������� ������
2. Front-end.
  2.1) ���������� ���������� � ����� js �� �����. ������ ���������� jquery (��� � ������ - ������ ���� �� �����), jqueryUI, jquery qTip
  2.2) � ������ ���� ���������� ������ div (������, � id=kFiltersBlock) � ���������� ������ � ���� head ��������� �������:
	
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
  ������ ����� ������������� ��� ��������� �����, ��� �� ������� front-end, ��� � �� ������� backend. 
  3.1) Front-end - ��� ������ ����� ������������ /js/kfilters/style.css . ����� ����, ������� ��� ������������� � � ������ ������ �������.
  ��������� ����� ����� ������� � �������� settings � ����� /js/kfilters/kfilters.js (��� � ������������� ��������� ���). ��� �� ���� 
  ���������� ��������� ��� ����������� ���������: kTips � kCountTips (��������, ���� ���, ������� �� ���� ��� �� ��������� � ����� � �������� ������)
  3.2) Back-end - ������������ � ��������� ������������� � ����� classes/modules/catalog/kFilters/kFiltersCustomLogic.php  . 
  ������ ����� ������ (����� __construct). 
  � ������� �������� �������� $this->catalogId (ID ��������� ��������, ��� ������� �������� �������) � $this->objectsTypeId (ID ���� �������� ��������, ������������� � ��������� (��� ������� �������� �������))
  ������� ���������  umi.cms.
 