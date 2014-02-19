/**
 * Виды фильтров: boolean, number, option, select, relation
 */
//Creating a clousure
(function($) {
	
	/** PRIVATE **/
	
	var arFiltersData = [];
	var oFiltersConfig = {};
	var arPreExpanded = [];
	var opts = {};
	var hideCountTimer = null;
	var countTipApi = false;
	var initialWidth = false;
	
	function initFilters($this, opts) {
		opts.onBeforeInit($this);
		var sCurrentGetParams = document.location.href.substr(document.location.href.indexOf("?") + 1);
		$.ajax({
			url: '/udata' + opts.filtersMacro + 'GetFilters/' + opts.category_id + '/',
			dataType: 'json',
			type: 'POST',
			cache: false,
			data: sCurrentGetParams,
			error: function(jqXHR, textStatus, errorThrown) {
				debugLog('Failed to perform GetFilters request: ' + textStatus + ' :: ' + errorThrown, true);
				$this.find('.init_preloader').remove();
			},
			success: function(data) {
				if(!data.errors || data.errors.length == 0) {
					oFiltersConfig = data.config;
					arFiltersData = data.data;
					arPreExpanded = data.expanded;
					if(opts.drawOnInit) {
						$this.kFilters('show');
					}
					opts.onAfterInit($this);
				} else {
					debugLog(data.errors, true);
				}
				
				$this.find('.init_preloader').remove();
			}
		});
	}
	
	function enableTooltip(tip_link) {
		if(tip_link && tip_link.length) {
			tip_link.each(function() {
				var sContent = $(this).next('.tip_text').html();
				tip_link.qtip({
					content: sContent,
					position: {
						corner: opts.tipPosition
					},
					style: 'kTips'
				});
			});
		}
	}
	
	function getFiltersQuery($this) {
		var arData = [];
		var sOptionName = '';
		var sOptionValue = '';
		var sElementID = '';
		for(var i = 0; i < arFiltersData.length; i++) {
			sElementID = getElementId(arFiltersData[i]);
			if($('#' + sElementID).length) {
				$('#' + sElementID + ' input[type="text"]').each(function() {
					sOptionName = $(this).attr('name');
					sOptionValue = $(this).val();
					arData.push(sOptionName + '=' + sOptionValue);
				});
				
				$('#' + sElementID + ' input[type="checkbox"]:checked').each(function() {
					sOptionName = $(this).attr('name');
					sOptionValue = $(this).val();
					arData.push(sOptionName + '=' + sOptionValue);
				});
			}
		}
		
		return arData.join('&');
	}
	
	function getFieldFilters($this) {
		var arData = [];
		var sOptionName = '';
		var sOptionValue = '';
		var sElementID = '';
		for(var i = 0; i < arFiltersData.length; i++) {
			sElementID = getElementId(arFiltersData[i]);
			if($('#' + sElementID, $this).length) {
				if(fieldsFilterMethods['fields_filter_' + arFiltersData[i].type]) {
					var sFilterPart = fieldsFilterMethods['fields_filter_' + arFiltersData[i].type].apply($this, [$('#' + sElementID, $this), arFiltersData[i]]);
					if(sFilterPart.length) {
						arData.push(sFilterPart);
					}
				}
			}
		}
		
		return arData.join('&');
	}
	
	function drawCountTip(tipElement) {
		if(tipElement == undefined || !countTipApi) return;
		
		//создать/отобразить тултип для tipElement с прелоадером
		countTipApi.elements.target = tipElement.parents('.counter_target').eq(0);
		countTipApi.options.position.target = tipElement.parents('.counter_target').eq(0);
		debugLog(countTipApi);
		$('.submit_link', countTipApi.elements.content).unbind('click');
		countTipApi.updateContent(opts.countTipLoadingContent, false);
		if(initialWidth === false) {
			initialWidth = countTipApi.getDimensions().width;
		}
		countTipApi.updateWidth(initialWidth);
		countTipApi.updatePosition(function() {return true;}, false);
		countTipApi.show();

		debugLog('drawCountTip for ' + tipElement.attr('name'));
	}
	
	function updateCountTip(count, $this) {
		countTipApi.updateContent(opts.countTipLoadedContent.replace("{[count]}", count), false);
		countTipApi.updateWidth();
		countTipApi.updatePosition(function() {return true;}, false);
		
		$('.submit_link', countTipApi.elements.content).click(function() {
			$('form.kform', $this).submit();
			return false;
		});
		debugLog('updateCountTip to ' + count);
	}
	
	function attachGetCountEvent($this, element) {
		$('input', element).change(function() {
			var element = $(this);
			clearTimeout(hideCountTimer);
			hideCountTimer = null;
			methods.requestcount($this, element);
			
			return true;
		});
	}
	
	function submitForm($this) {
		var url = document.location.href.substr(0, document.location.href.indexOf("?"));
		url = url + '?' + getFieldFilters($this);
		debugLog(url);
		document.location.href = url;
		return false;
	}
	
	function updateSubmitText($this, count) {
		var sPostfix = 'т';
		var iMod = count % 10;
		switch(iMod) {
			case 0: 
			case 5: 
			case 6: 
			case 7: 
			case 8: 
			case 9: 
			{
				sPostfix = 'тов';
			} break;
			case 1: {
				sPostfix = 'т';
			} break;
			case 2: 
			case 3: 
			case 4: 
			{
				sPostfix = 'та';
			} break;
		}
		$('.kform .submit_btn').val('Показать ' + count + ' результа' + sPostfix);
	}
	
	function getElementId(dataElement) {
		return 'kItem-' + dataElement.id;
	}
	
	function debugLog(data, warn) {
		if(opts == {}) {
			console.log('Filters not initialized');
			return;
		}
		if(opts.debug) {
			if(warn == undefined || !warn) console.log(data);
			else console.warn(data);
		}
	}
	
	function setAllState($this, filtersData) {
		if(!oFiltersConfig.activeoncount) return;
		for(var i=0; i < filtersData.length; i++) {
			$('#' + getElementId(filtersData[i])).each(function() {
				setState($this, $(this), filtersData[i]);
			});
		}
	}
	
	function setState($this, element, controlData) {
		if(!oFiltersConfig.activeonload) return;
		
		if(stateMethods['set_' + controlData.type]) {
			stateMethods['set_' + controlData.type].apply($this, [element, controlData]);
		} else {
			debugLog('No setState method for type "' + controlData.type + '" for field "' + controlData.title + '"[' + controlData.name + ']', true);
		}
	}
	
	
	/**
	 * Методы установки активности элементов
	 */
	var stateMethods = {
		set_boolean: function(element, controlData) {
			if(!controlData.active) {
				$('.field_caption', element).addClass('noItems');
			} else {
				$('.field_caption', element).removeClass('noItems');
			}
		},
		set_number: function(element, controlData) {
			debugLog('No activity set for type "' + controlData.type + '"');
		},
		set_option: function(element, controlData) {
			$('.item_body ul li input', element).each(function() {
				var val = $(this).val();
				for(var i=0; i < controlData.values.length; i++) {
					if(val == controlData.values[i].id) {
						if(!controlData.values[i].active) {
							$(this).parent().find('.option_caption').addClass('noItems');
						} else {
							$(this).parent().find('.option_caption').removeClass('noItems');
						}
						break;
					}
				}
			})
		}
	}
	
	
	
	/**
	 * Методы форирования подстрок для фильтров
	 */
	var fieldsFilterMethods = {
		fields_filter_boolean: function(element, filterData) {
			if($('input[type="checkbox"]:checked', element).length) {
				var sFilterName = 'fields_filter[' + filterData.name + ']';
				var sFilterValue = $('input[type="checkbox"]:checked', element).eq(0).val();
				
				var sFilterString = sFilterName + '=' + sFilterValue;
				
				return sFilterString;
			} else {
				return "";
			}
		},
		fields_filter_number: function(element, filterData) {
			var sFilterNameMore = 'fields_filter[' + filterData.name + '][gt]';
			var sFilterNameLess = 'fields_filter[' + filterData.name + '][lt]';
			
			var sFilterValueMore = $('input#filter-' + filterData.id + '-0', element).val();
			var sFilterValueLess = $('input#filter-' + filterData.id + '-1', element).val();
			
			return sFilterNameMore + '=' + sFilterValueMore + '&' + sFilterNameLess + '=' + sFilterValueLess;
		},
		fields_filter_option: function(element, filterData) {
			var sFilterName = 'k_fields_filter[' + filterData.name + '][in]';
			var arOptions = [];
			var i = 0;
			$('input[type="checkbox"]:checked', element).each(function() {
				var value = $(this).val();
				arOptions.push(sFilterName + '[' + i + ']' + '=' + value);
				i++;
			});
			
			return arOptions.join('&');
		}
	}
	
	
	
	/**
	 * Методы "раскрытия/скрытия" контролов для полей
	 */
	var expandMethods = {
		expand_boolean: function(element) {
			return;
		},
		expand_number: function(element) {
			$('.item_body', element).toggle();
		},
		expand_option: function(element) {
			$('.item_body', element).toggle();
		}
	}
	
	
	/**
	 * Методы отрисовки контролов (draw_<название контрола>)
	 */
	var drawMethods = {
		draw_boolean: function($this, controlData) {
			var sItem = '\
			<li class="boolean" id="' + getElementId(controlData) + '">\
				<div class="counter_target">\
					<input type="checkbox" value="1" name="filter-'+ controlData.id + '" ' + ((controlData.selected) ? 'checked="checked"' : '') + '/>\
					<span class="field_caption">' + controlData.title + '</span>\
					' + ((controlData.tip != undefined && controlData.tip.length != 0) ? '<span class="tip_link" /><div class="tip_text">'+controlData.tip+'</div>' : '') + '\
				</div>\
			</li>';
			$('.kform > ul', $this).append(sItem);
			
			enableTooltip($('#' + getElementId(controlData) + ' .tip_link'));
			attachGetCountEvent($this, $('#' + getElementId(controlData)) );
			setState($this, $('#' + getElementId(controlData)), controlData);
			
			debugLog(controlData);
		},
		draw_select: function($this, controlData) {
			var sItem = '\
			<li class="select" id="' + getElementId(controlData) + '">\
				<span class="field_caption">' + controlData.title + '</span>\
				' + ((controlData.tip != undefined && controlData.tip.length != 0) ? '<span class="tip_link"></span><div class="tip_text">'+controlData.tip+'</div>' : '') + '\
				<div class="item_body">\
					<div class="counter_target">\
						<select>';
			
			for(var i = 0; i < controlData.values.length; i++) {
				sItem += '<option value="' + controlData.values[i].id + '" name="filter-'+ controlData.id + '"';
				if(controlData.selected == controlData.values[i].id) {
					sItem += 'selected="selected"';
				}
				sItem += '>' + controlData.values[i].value + '</option>';
			}
			sItem += '\
						</select>\
					</div>\
				</div>\
			</li>';
			
			$('.kform > ul', $this).append(sItem);
			
			enableTooltip($('#' + getElementId(controlData) + ' .tip_link'));
			attachGetCountEvent($this, $('#' + getElementId(controlData)) );
			setState($this, $('#' + getElementId(controlData)), controlData);
			
			$('#' + getElementId(controlData) + ' .field_caption', $this).click(function() {
				$('.item_body', $(this).parent()).toggle();
			});
			
			debugLog(controlData);
		},
		draw_number: function($this, controlData) {
			var sItem = '\
			<li class="number" id="' + getElementId(controlData) + '">\
				<span class="field_caption">' + controlData.title + '</span>\
				' + ((controlData.tip != undefined && controlData.tip.length != 0) ? '<span class="tip_link" /><div class="tip_text">'+controlData.tip+'</div>' : '') + '\
				<div class="item_body">\
					<div class="counter_target">\
						<span class="from_label">От</span>\
						<input type="text" name="filter-'+ controlData.id + '[gt]" id="filter-'+ controlData.id + '-0" value="' + controlData.selected.min + '" />\
						<span class="to_label">До</span>\
						<input type="text" name="filter-'+ controlData.id + '[lt]" id="filter-'+ controlData.id + '-1" value="' + controlData.selected.max + '" />\
					</div>\
					<div class="number-slider" id="slider-for-' + controlData.id + '" />\
				</div>\
			</li>';
			$('.kform > ul', $this).append(sItem);
			
			$('#' + getElementId(controlData) + ' .field_caption', $this).click(function() {
				$('.item_body', $(this).parent()).toggle();
			});
			
			enableTooltip($('#' + getElementId(controlData) + ' .tip_link'));
			attachGetCountEvent($this, $('#' + getElementId(controlData)) );
			setState($this, $('#' + getElementId(controlData)), controlData);
			
			$('#slider-for-' + controlData.id, $this).slider({
				range: true,
				min: controlData.values[0],
				max: controlData.values[1],
				values: [controlData.selected.min, controlData.selected.max],
				slide: function( event, ui ) {
					$('#filter-'+ controlData.id + '-0', $this).val( ui.values[0] );
					$('#filter-'+ controlData.id + '-1', $this).val( ui.values[1] );
				},
				stop: function( event, ui ) {
					$('#filter-'+ controlData.id + '-0', $this).val( ui.values[0] );
					$('#filter-'+ controlData.id + '-1', $this).val( ui.values[1] ).trigger('change');
				}
			});
			
			$('#filter-'+ controlData.id + '-0', $this).change(function() { 
				if($(this).val() <= $('#filter-'+ controlData.id + '-1', $this).val()) $('#slider-for-' + controlData.id, $this).slider("values", [$('#filter-'+ controlData.id + '-0', $this).val(), $('#filter-'+ controlData.id + '-1', $this).val()]);
				else return false;
			});
			$('#filter-'+ controlData.id + '-1', $this).change(function() { 
				if($(this).val() >= $('#filter-'+ controlData.id + '-0', $this).val()) $('#slider-for-' + controlData.id, $this).slider("values", [$('#filter-'+ controlData.id + '-0', $this).val(), $('#filter-'+ controlData.id + '-1', $this).val()]);
				else return false;
			});
			
			debugLog(controlData);
		},
		draw_option: function($this, controlData) {
			var sItem = '\
			<li class="option" id="' + getElementId(controlData) + '">\
				<span class="field_caption">' + controlData.title + '</span>\
				' + ((controlData.tip != undefined && controlData.tip.length != 0) ? '<span class="tip_link"></span><div class="tip_text">'+controlData.tip+'</div>' : '') + '\
				<div class="item_body">\
					<ul>';
			
			for(var i = 0; i < controlData.values.length; i++) {
				sItem += '<li>\
							<div class="counter_target">\
								<input type="checkbox" value="' + controlData.values[i].id + '" name="filter-'+ controlData.id + '[]"';
									for(var j = 0; j < controlData.selected.length; j++) {
										if(controlData.selected[j] == controlData.values[i].id) {
											sItem += ' checked="checked"';
										}
									}
						sItem += '/>\
								<span class="option_caption">' + controlData.values[i].value + '</span>\
							</div>\
						</li>';
			}
			sItem += '\
					</ul>\
				</div>\
			</li>';
			
			$('.kform > ul', $this).append(sItem);
			
			enableTooltip($('#' + getElementId(controlData) + ' .tip_link'));
			attachGetCountEvent($this, $('#' + getElementId(controlData)) );
			setState($this, $('#' + getElementId(controlData)), controlData);
			
			$('#' + getElementId(controlData) + ' .field_caption', $this).click(function() {
				$('.item_body', $(this).parent()).toggle();
			});
			
			debugLog(controlData);
		},
		draw_string: function($this, controlData) {
			debugLog(controlData);
			debugLog('Тип string надо преобразовывать в option');
		}
	}
	
	
	
	/**
	 * Методы для работы с плагином
	 */
	var methods = {
		/**
		 * Инициализация - формирование конфига, ajax-загрузка объекта с информацией о фильтрах
		 */
		init: function(options) {
			opts = $.extend({}, $.fn.kFilters.settings, options);
			if(opts.category_id == 0) {
				debugLog('No category_id specified', true);
				return this;
			}
			
			return this.each(function() {
				var $this = $(this);
				
				var widgetCss = {
					width: opts.width,
					'min-height': opts['min-height']
				}
				$this.addClass('theme-' + opts.theme);
				$this.addClass('kfilter');
				$this.css(widgetCss);
				
				$this.append('<div class="init_preloader"/>');
				
				initFilters($this, opts);
			});
		},
		/**
		 * Создание контролов
		 */
		show: function() {
			if(opts == {}) {
				debugLog('Filters not initialized', true);
				return this;
			}
			
			return this.each(function() {
				var $this = $(this);
				
				function drawControl(controlData) {
					opts.onBeforeDrawControl($this, controlData);
					if(drawMethods['draw_' + controlData.type]) {
						drawMethods['draw_' + controlData.type].apply($this, [$this, controlData]);
					} else {
						debugLog('Unknown control type "' + controlData.type + '" for field "' + controlData.title + '"[' + controlData.name + ']', true);
					}
					opts.onAfterDrawControl($this, controlData);
				};
				
				function preExpandControl(controlData) {
					for(var e=0; e < arPreExpanded.length; e++) {
						if(arPreExpanded[e] == controlData.name) {
							$('#' + getElementId(controlData), $this).each(function() {
								if(expandMethods['expand_' + controlData.type]) {
									expandMethods['expand_' + controlData.type].apply($this, [$(this)]);
								}
							});
							break;
						}
					}
				}
				
				$this.append('<form class="kform"><ul></ul></form>');
				
				for(var i = 0; i < arFiltersData.length; i++) {
					drawControl(arFiltersData[i]);
					preExpandControl(arFiltersData[i]);
				}
				
				$('.kform', $this).append('<input type="submit" value="Показать" class="submit_btn" />');
				$('.kform', $this).submit(function() {
					return submitForm($this);
				});
				$('.kform', $this).qtip({
					content: opts.countTipLoadingContent,
					position: {
						corner: opts.countTipPosition
					},
					style: 'kCountTips',
					show: false,
					hide: false
				});
				countTipApi = $('.kform', $this).qtip('api');
			});
		},
		/**
		 * Запрос для получение количества результатов, удовлетворяющих текущему состоянию фильтров
		 */
		requestcount: function($self, element) {
			if(opts == {}) {
				debugLog('Filters not initialized');
				return this;
			}
			
			if($self == undefined) $self = this;
			
			return $self.each(function() {
				var $this = $(this);
				
				var sQuery = getFiltersQuery($this);
				
				//залочить инпуты
				$('input, select', $this).attr('disabled', 'disabled');
				$('.number-slider', $this).slider('disable');
				
				//ajax-запрос и отображение результата в кнопке сабмита и, если указан, в тултипе
				drawCountTip(element);
				$.ajax({
					url: '/udata' + opts.filtersMacro + 'GetCount/' + opts.category_id + '/',
					data: sQuery,
					dataType: 'json',
					type: 'POST',
					cache: false,
					error: function(jqXHR, textStatus, errorThrown) {
						debugLog('Failed to perform GetCount request: ' + textStatus + ' :: ' + errorThrown, true);
						countTipApi.hide();
						
						//разлочить инпуты
						$('input, select', $this).removeAttr('disabled');
						$('.number-slider', $this).slider('enable');
					},
					success: function(data) {
						if(!data.errors || data.errors.length == 0) {
								updateCountTip(data.count, $this);
								//timer to remove count tip
								hideCountTimer = setTimeout(function() { countTipApi.hide(); }, opts.countTipFadeTimer);
								
								updateSubmitText($this, data.count);
								setAllState($this, data.filters);
								
								//разлочить инпуты
								$('input, select', $this).removeAttr('disabled');
								$('.number-slider', $this).slider('enable');
						} else {
							debugLog(data.errors, true);
							countTipApi.hide();
							
							//разлочить инпуты
							$('input, select', $this).removeAttr('disabled');
							$('.number-slider', $this).slider('enable');
						}
					}
				});
				
			});
		}
	};
	
	
	/** PUBLIC **/
	
	/**
	 * kFilters плагин
	 */
	$.fn.kFilters = function(method) {
		if(methods[method]) {
			//Call specified method with arguments
			return methods[method].apply(this, Array.prototype.slice.call( arguments, 1 ));
		} else if (typeof method === 'object' || ! method) {
			//If object instead of string or no method specified - call "init"
			return methods.init.apply(this, arguments);
		} else {
			//Otherwise rise error
			debugLog('Method ' +  method + ' does not exist on jQuery.kFilters', true);
		}
	};
	
	
	
	/**
	 * Настройки для фильтров по-умолччанию
	 */
	$.fn.kFilters.settings = {
		/**
		 * Ширина контрола фильтров
		 */
		width: '200px',
		/**
		 * Минимальная высота контрола фильтров
		 */
		'min-height': '200px',
		/**
		 * Название темы (задаёт класс для контрола - дальше css-ами можно кастомизировать)
		 */
		theme: 'default',
		/**
		 * ID категории, для которой строятся фильтры - обязательный параметр
		 */
		category_id: 0,
		/**
		 * Макрос для взаимодействия с бэкэндом - обязательный параметр
		 */
		filtersMacro: '/catalog/kfilters/',
		/**
		 * Флаг - отрисовывать контролы сразу по инициализации
		 */
		drawOnInit: true,
		/**
		 * Объект, определяющий расположение "облаков" с подсказками
		 */
		tipPosition: {	//Available values: center, topLeft, topMiddle, topRight, rightTop, rightMiddle, rightBottom, bottomRight, bottomMiddle, bottomLeft, leftBottom, leftMiddle, leftTop
			target: 'bottomRight',
			tooltip: 'topLeft'
		},
		/**
		 * Объект, определяющий расположение "облаков" с количеством найденных результатов
		 */
		countTipPosition: {	//Available values: center, topLeft, topMiddle, topRight, rightTop, rightMiddle, rightBottom, bottomRight, bottomMiddle, bottomLeft, leftBottom, leftMiddle, leftTop
			target: 'rightMiddle',
			tooltip: 'leftMiddle'
		},
		/**
		 * Задержка в мс подсказки с количеством найденных результатов
		 */
		countTipFadeTimer: 5000,
		/**
		 * Текст, отображающийся при загрузке количества найденных результатов
		 */
		countTipLoadingContent: '<span class="countTipText">Найдено результатов:</span><span class="tipLoader" /><div class="cleaner" />',
		/**
		 * Текст, отображающийся после загрузки количества найденных результатов; {[count]} будет заменено на число; submit_link - к этому классу привязывается событие для сабмита формы
		 */
		countTipLoadedContent: '<span class="countTipText">Найдено результатов:</span><a class="submit_link" href="">{[count]}</a><div class="cleaner" />',
		/**
		 * Режим отладки (логи в консоли)
		 */
		debug: false,
		/**
		 * Событие - до инициализации
		 */
		onBeforeInit: function(element) {},
		/**
		 * Событие - после инициализации
		 */
		onAfterInit: function(element) {},
		/**
		 * Событие - до отрисовки контрола
		 */
		onBeforeDrawControl: function(element, oControlData) {},
		/**
		 * Событие - после отрисовки контрола
		 */
		onAfterDrawControl: function(element, oControlData) {}
	};
	
	
	/**
	 * Настройки для tip-ов по-умолччанию
	 */
	$.fn.qtip.styles.kTips = {
		padding: 5,
		background: '#FEFFD6',
		color: '#000000',
		textAlign: 'left',
		border: {
			width: 1,
			radius: 0,
			color: '#FF9B00'
		},
		tip: 'topLeft',
		name: 'dark'
	}
	
	/**
	 * Настройки для tip-ов по-умолччанию
	 */
	$.fn.qtip.styles.kCountTips = {
		padding: 5,
		background: '#FEFFD6',
		color: '#000000',
		textAlign: 'left',
		border: {
			width: 1,
			radius: 0,
			color: '#FF9B00'
		},
		tip: 'leftMiddle',
		name: 'dark'
	}
	
})(jQuery);