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
						var $parent = $table
							.find(
								'tbody tr:has(span[data-breadcrumb-entry = '
								+ parent_entry
								+ '])'
							);
						
						// Move the child after its parent:
						$current
							.addClass('breadcrumb-child')
							.insertAfter($parent);
						
						// Associate child with parent:
						$current.data().parent = $parent;
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
				.hide()
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
		});
	
	$('form > table tr.breadcrumb-parent')
		.live('toggle-tree', function() {
			var $current = $(this);
			
			if ($current.next().is(':visible')) {
				$current.trigger('collapse-tree');
			}
			
			else {
				$current.trigger('expand-tree');
			}
		})
		
		.live('collapse-tree', function() {
			$(this)
				.data()
				.children
				.trigger('collapse-tree')
				.hide();
		})
		
		.live('expand-tree', function() {
			$(this)
				.data()
				.children
				.show();
		});
	
	$(document)
		.ready(function() {
			$('form > table')
				.trigger('initialize');
		});
})(jQuery);