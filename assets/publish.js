(function($) {
	var $table = $('form > table')
		.live('initialize', function() {
			var $parents = $table
				.find('tr:has(span[data-breadcrumb-entry]):not(:has(span[data-breadcrumb-root]))')
				.addClass('breadcrumb-parent');
			var $children = $table
				.find('tr:has(span[data-breadcrumb-root])')
				.addClass('breadcrumb-child')
				.hide();
			
			$parents
				.each(function() {
					var $parent = $(this);
					var parent_entry = $(this)
						.find('span[data-breadcrumb-entry]')
						.attr('data-breadcrumb-entry');
					
					// Insert toggle control:
					$('<a />')
						.addClass('breadcrumb-toggle')
						.text('►')
						.prependTo(
							$parent.find('td:first')
						)
						.bind('click', function() {
							var $self = $(this);
							var $items = $children
								.filter(
									':has(span[data-breadcrumb-root = '
									+ parent_entry
									+ '])'
								);
							
							if ($items.is(':visible')) {
								$items.hide();
								$self.text('►');
							}
							
							else {
								$items.show();
								$self.text('▼');
							}
							
							return false;
						})
						.bind('mousedown', function() {
							return false;
						});
					
					$children
						.filter(
							':has(span[data-breadcrumb-root = '
							+ parent_entry
							+ '])'
						)
						
						// Insert spacer:
						.each(function() {
							var $child = $(this);
							
							$('<span />')
								.addClass('breadcrumb-spacer')
								.prependTo(
									$child.find('td:first')
								);
						})
						
						// Move children after their parent:
						.insertAfter($parent);
				});
		});
	
	$(document)
		.ready(function() {
			$table = $('form > table');
			$table.trigger('initialize');
		});
})(jQuery);