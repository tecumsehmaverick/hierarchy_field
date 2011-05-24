(function ($) {
	$('div[data-breadcrumb]')
		.live('initialize', function() {
			var $self = $(this);
			var $list = $self.find('ol');
			var $insert = $('<li />')
				.addClass('insert')
				.text('+');
			
			$insert.appendTo($list);
		})
		
		.live('open', function() {
			var $self = $(this);
			var $list = $self
				.children('ol');
			var $form = $('<div />')
				.addClass('selection');
			var $options = $('<ol />')
				.addClass('options');
			var $item = $list
				.children('li.item.selected');
			
			$options.appendTo($form);
			$form.appendTo($self);
			
			/**
			 * @todo Perform an ajax request with $self.data('edit-id')
			 */
			
			// Dummy children:
			$options
				.append('<li data-id="0">News</li>')
				.append('<li data-id="1">Politics</li>')
				.append('<li data-id="2">Three</li>')
				.append('<li data-id="3">Four</li>');
			
			// Select current child:
			$options
				.find('li')
				.each(function() {
					var $option = $(this);
					
					if ($option.attr('data-id') == $item.attr('data-id')) {
						$option.addClass('selected');
						
						return false;
					}
				});
		})
		
		.live('close', function() {
			var $self = $(this);
			
			$self
				.find('ol.breadcrumb li.item.selected')
				.removeClass('selected');
			
			$self
				.children('div.selection')
				.remove();
		});
	
	/**
	 * Select an item.
	 */
	$('div[data-breadcrumb] ol.breadcrumb li.item:not(.selected)')
		.live('click', function() {
			var $item = $(this);
			var $self = $item.closest('div');
			
			$self.trigger('close');
			$item.addClass('selected');
			$self.trigger('open');
		});
	
	/**
	 * Deselect an item.
	 */
	$('div[data-breadcrumb] ol.breadcrumb li.item.selected')
		.live('click', function() {
			$(this).closest('div').trigger('close');
		});
	
	/**
	 * Insert a new item.
	 */
	$('div[data-breadcrumb] ol.breadcrumb li.insert')
		.live('click', function() {
			var $insert = $(this);
			var $self = $insert
				.closest('div');
			var $list = $self
				.children('ol');
			var $item = $('<li />')
				.addClass('item')
				.text('None');
			
			$self.trigger('close');
			
			// Empty item already exists:
			if ($list.find('li.item:not([data-id])').length) {
				$item = $list.find('li.item:not([data-id])');
			}
			
			else {
				$item.insertBefore($insert);
			}
			
			$item.addClass('selected');
			$self.trigger('open');
		});
	
	/**
	 * Initialize any select elements.
	 */
	$(document)
		.ready(function() {
			$('div[data-breadcrumb]')
				.trigger('initialize')
				//.trigger('open');
		});
})(jQuery);