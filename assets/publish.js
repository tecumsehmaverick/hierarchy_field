(function($) {
	$('form > table')
		.live('initialize', function() {
			var $table = $(this);
			
			// Hide columns:
			$table
				.find('th.field-breadcrumb, td.field-breadcrumb')
				.hide();
			
			// Sort table rows:
			$table
				.find('tbody tr span[data-breadcrumb-entry]')
				.each(function() {
					var parent_entry = $(this)
						.attr('data-breadcrumb-parent');
					var current_depth = $(this)
						.attr('data-breadcrumb-depth');
					var current_entry = $(this)
						.attr('data-breadcrumb-entry');
					var $current = $(this)
						.closest('tr');
					var $children = $table.has(
						'span[data-breadcrumb-parent = '
						+ current_entry
						+ ']'
					);
					
					// Store data:
					$current
						.data({
							'entry':	current_entry,
							'depth':	current_depth
						});
					
					// Has a parent:
					if (parent_entry) {
						$current
							.addClass('breadcrumb-child')
							.data().parent = $table
								.find(
									'tbody tr:has(span[data-breadcrumb-entry = '
									+ parent_entry
									+ '])'
								);
					}
					
					// Has children:
					if ($children.length) {
						$current
							.addClass('breadcrumb-parent');
					}
				});
			
			// Prepare parent items:
			$table
				.find('tbody tr.breadcrumb-parent')
				.each(function() {
					var $current = $(this);
					
					// Associate parent with children:
					$current.data().children = $table
						.find(
							'tbody tr:has(span[data-breadcrumb-parent = '
							+ $current.data().entry
							+ '])'
						);
					
					// Insert toggle controls:
					$('<a />')
						.addClass('breadcrumb-toggle')
						.text('►')
						.prependTo(
							$current.find('td:first')
						)
						.bind('click', function() {
							$current.trigger('toggle-tree');
							
							return false;
						})
						.bind('mousedown', function() {
							return false;
						});
				});
			
			// Prepare child items:
			$table
				.find('tbody tr.breadcrumb-child')
				//.hide()
				.each(function() {
					var $current = $(this);
					var depth = $current.data().depth;
					
					while (depth-- > 0) {
						$('<span />')
							.addClass('breadcrumb-spacer')
							.prependTo(
								$current.find('td:first')
							);
					}
				});
			
			// Sort tree:
			$table
				.find('tbody tr.breadcrumb-parent:not(.breadcrumb-child)')
				.trigger('sort-tree');
		});
	
	$('form > table tr.breadcrumb-parent')
		.live('sort-tree', function() {
			var $parent = $(this);
			var $children = $parent.data().children;
			
			$children
				.sort(function(a, b) {
					return $(a).data().depth > $(b).data().depth;
				})
				.each(function() {
					$(this)
						.insertAfter($parent);
				});
			
			$children
				.filter('.breadcrumb-parent')
				.trigger('sort-tree');
		})
		
		.live('click.selectable', function() {
			var $current = $(this);
			var $children = $current.data().children;
			
			if ($current.is('.selected')) {
				$current
					.trigger('expand-tree')
					.trigger('select-tree');
			}
			
			else {
				$children
					.removeClass('selected')
					.find('input[type = "checkbox"]')
					.attr('checked', false);
			}
		})
		
		.live('toggle-tree', function() {
			var $current = $(this);
			
			if ($current.next().is(':visible')) {
				$current.trigger('collapse-tree');
			}
			
			else {
				$current.trigger('expand-tree');
			}
		})
		
		.live('select-tree', function() {
			var $current = $(this);
			var $children = $current.data().children
			
			$current
				.addClass('selected')
				.find('input[type = "checkbox"]')
				.attr('checked', true);
			$children
				.trigger('expand-tree')
				.trigger('select-tree');
		})
		
		.live('deselect-tree', function() {
			var $current = $(this);
			var $children = $current.data().children
			
			$current
				.removeClass('selected')
				.find('input[type = "checkbox"]')
				.attr('checked', false);
			$children
				.trigger('deselect-tree');
		})
		
		.live('collapse-tree', function() {
			var $current = $(this);
			var $children = $current.data().children
				.trigger('collapse-tree')
				.hide();
			
			if ($current.is('.selected')) {
				$current
					.trigger('deselect-tree');
			}
		})
		
		.live('expand-tree', function() {
			var $current = $(this);
			var $children = $current.data().children
				.show();
			
			//if ($current.is('.selected')) {
			//	$children
			//		.addClass('selected')
			//		.find('input[type = "checkbox"]')
			//		.attr('checked', true);
			//}
		});
	
	$('form > table tr.breadcrumb-child')
		.live('select-tree', function() {
			var $current = $(this);
			
			$current
				.addClass('selected')
				.find('input[type = "checkbox"]')
				.attr('checked', true);
		})
		
		.live('deselect-tree', function() {
			var $current = $(this);
			
			$current
				.removeClass('selected')
				.find('input[type = "checkbox"]')
				.attr('checked', false);
		});
	
	$(document)
		.ready(function() {
			$('form > table')
				.trigger('initialize');
		});
})(jQuery);