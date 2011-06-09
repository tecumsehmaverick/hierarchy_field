(function($) {
	var Cookie = {
		set: function(name, value, seconds) {
			var expires = "";

			if (seconds) {
				var date = new Date();
				date.setTime(date.getTime() + seconds);
				expires = "; expires=" + date.toGMTString();
			}

			document.cookie = name + "=" + value + expires + "; path=/";
		},
		get: function(name) {
			var nameEQ = name + "=";
			var ca = document.cookie.split(';');

			for (var i=0;i < ca.length;i++) {
				var c = ca[i];
				while (c.charAt(0)==' ') c = c.substring(1,c.length);
				if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
			}

			return null;
		}
	};
	
	$('form > table')
		.live('initialize', function() {
			var $table = $(this);
			
			$table
				.find('th.field-breadcrumb, td.field-breadcrumb')
				.not(':first-child')
				.hide();
			
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
					var $children = $table.find(
						'tbody tr:has(span[data-breadcrumb-parent = '
						+ current_entry
						+ '])'
					);
					var temp_depth = current_depth;
					var cookie = 'breadcrumb-tree.'
						+ parent_entry
						+ '.'
						+ current_entry;
					
					// Store data:
					$current
						.data({
							'children':		$children,
							'parent_id':	parent_entry,
							'entry_id':		current_entry,
							'depth':		current_depth,
							'cookie':		cookie
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
					
					// Is a parent:
					if ($children.length) {
						$current
							.addClass('breadcrumb-parent');
						
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
					}
				});
			
			$table
				.find('tbody tr.breadcrumb-parent:not(.breadcrumb-child)')
				.trigger('sort-tree');
			
			$table
				.find('tbody tr')
				.each(function() {
					var $current = $(this);
					var current_depth = $current.data().depth;
					var temp_depth = current_depth;
					
					// Remember collapsed states:
					if ($current.is('.breadcrumb-parent')) {
						if (Cookie.get($current.data().cookie) != 'expanded') {
							$current.trigger('collapse-tree');
						}
					}
					
					if (!(current_depth % 2)) {
						$current.addClass('alternating-depth');
					}
					
					// Insert item spacers:
					while (temp_depth-- > 0) {
						var $spacer = $('<span />')
							.addClass('breadcrumb-spacer')
							.prependTo(
								$current.find('td:first')
							);
					}
					
					if ($current.is(':last-child')) {
						$current
							.find('span.breadcrumb-spacer')
							.addClass('ignored')
							.filter(':last')
							.removeClass('ignored')
							.addClass('terminated');
					}
					
					else {
						var next_depth = $current.next().data().depth;
						var depth_diff = current_depth - next_depth;
						
						if (depth_diff > 0) {
							var $spacers = $current
								.find('span.breadcrumb-spacer');
							
							$spacers
								.each(function(index) {
									if (index > next_depth - 1) {
										if (index == $spacers.length - 1) {
											$(this).addClass('terminated');
										}
										
										else {
											$(this).addClass('ignored');
										}
									}
									
									else {
										$(this).addClass('ignored');
									}
								});
						}
						
						else if (depth_diff == -1) {
							$current
								.find('span.breadcrumb-spacer')
								.addClass('ignored')
								.filter(':last')
								.removeClass('ignored')
								.addClass('terminated');
						}
						
						else {
							$current
								.find('span.breadcrumb-spacer')
								.addClass('ignored')
								.filter(':last')
								.removeClass('ignored')
								.addClass('continued');
						}
					}
				});
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
			
			$current.data().children
				.trigger('collapse-tree')
				.hide();
			
			if ($current.is('.selected')) {
				$current
					.trigger('deselect-tree');
			}
			
			Cookie.set($current.data().cookie, 'collapsed');
		})
		
		.live('expand-tree', function() {
			var $current = $(this);
			
			$current.data().children
				.show();
			
			$current
				.find('a.breadcrumb-toggle')
			
			Cookie.set($current.data().cookie, 'expanded');
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