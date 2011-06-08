<?php
	
	/**
	 * @package breadcrumb_field
	 */
	
	require_once TOOLKIT . '/class.entrymanager.php';
	require_once TOOLKIT . '/class.fieldmanager.php';
	require_once TOOLKIT . '/class.sectionmanager.php';
	
	/**
	 * A new link field, used to build hierarchical content.
	 */
	class Extension_Breadcrumb_Field extends Extension {
		static protected $entryCache = array();
		
		/**
		 * Extension information.
		 */
		public function about() {
			return array(
				'name'			=> 'Field: Breadcrumb',
				'version'		=> '0.2',
				'release-date'	=> '2011-06-01',
				'author'		=> array(
					'name'			=> 'Rowan Lewis',
					'website'		=> 'http://rowanlewis.com/',
					'email'			=> 'me@rowanlewis.com'
				)
			);
		}
		
		/**
		 * Listen for these delegates.
		 */
		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'InitaliseAdminPageHead',
					'callback' => 'appendScriptToHead'
				),
				array(
					'page' => '/extension/breadcrumb_ui/',
					'delegate' => 'AppendBreadcrumbOptions',
					'callback' => 'appendBreadcrumbOptions'
				)
			);
		}
		
		/**
		 * Cleanup installation.
		 */
		public function uninstall() {
			Symphony::Database()->query("DROP TABLE `tbl_fields_breadcrumb`");
		}
		
		/**
		 * Create tables and configuration.
		 */
		public function install() {
			Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_fields_breadcrumb` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`show_tree` enum('yes','no') NOT NULL default 'yes',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");
			
			return true;
		}
		
		/**
		 * Add tree view scripts to head.
		 */
		public function appendScriptToHead($context) {
			if ($this->isShowTreeEnabled() === false) return;
			
			$page = Symphony::Engine()->Page;
			
			// Tempoarily set the pagination size to a huge number:
			Symphony::Configuration()->set('pagination_maximum_rows', 99999, 'symphony');
			
			// Include our styles and scripts:
			$page->addStylesheetToHead(URL . '/extensions/breadcrumb_field/assets/publish.css');
			$page->addScriptToHead(URL . '/extensions/breadcrumb_field/assets/publish.js');
		}
		
		/**
		 * Determines weather the current page is to be modified with
		 * the 'show_tree' script.
		 */
		public function isShowTreeEnabled() {
			$db = Symphony::Database();
			$page = Symphony::Engine()->Page;
			$context = $page->_context;
			
			// Make sure it's the correct page:
			if (
				!$page instanceof ContentPublish
				|| !isset($context['section_handle'])
				|| $context['page'] != 'index'
			) return false;
			
			// Is show tree enabled?
			return (boolean)$db->fetchVar(
				'enabled', 0,
				sprintf("
					SELECT
						d.show_tree = 'yes' AS `enabled`
					FROM
						`tbl_fields_breadcrumb` AS d,
						`tbl_fields` AS f,
						`tbl_sections` AS s
					WHERE
						d.show_tree = 'yes'
						AND d.field_id = f.id
						AND f.parent_section = s.id
						AND s.handle = '%s'
					",
					$context['section_handle']
				)
			);
		}
		
		/**
		 * Respond to AJAX query asking for options.
		 */
		public function appendBreadcrumbOptions($context) {
			if ($context['data']['type'] != 'breadcrumb') return;
			
			$fm = new FieldManager(Symphony::Engine());
			$entry_id = $context['data']['item'];
			$field = $fm->fetch($context['data']['field']);
			$ignore_id = $context['data']['entry'];
			
			$items = $this->getBreadcrumbChildren(
				$field, $entry_id, $ignore_id
			);
			
			foreach ($items as $item) {
				$context['options'][$item->entry] = $item->value;
			}
		}
		
		/**
		 * Get a list of child entries.
		 * @param Field $breadcrumb
		 * @param integer $entry_id
		 * @param integer $ignore_id
		 *	Don't output entries with this ID.
		 */
		public function getBreadcrumbChildren(Field $breadcrumb, $entry_id = null, $ignore_id = null) {
			$em = new EntryManager(Symphony::Engine());
			$parent = $em->fetch($entry_id);
			
			// Loat root level entries:
			if ($parent === false) {
				$entry_ids = Symphony::Database()->fetchCol('id', sprintf('
					SELECT DISTINCT
						e.id
					FROM
						`tbl_entries` AS e
					WHERE
						e.section_id = %d
						AND e.id NOT IN (
							SELECT
								d.entry_id
							FROM
								`tbl_entries_data_%d` AS d
							WHERE
								d.relation_id IS NOT NULL
						)
					',
					$breadcrumb->get('parent_section'),
					$breadcrumb->get('id')
				));
			}
			
			// Load child entries:
			else {
				$parent = current($parent);
				$entry_ids = Symphony::Database()->fetchCol('entry_id', sprintf('
					SELECT DISTINCT
						d.entry_id
					FROM
						`tbl_entries_data_%d` AS d
					WHERE
						d.relation_id = %d
					',
					$breadcrumb->get('id'),
					$entry_id
				));
			}
			
			if (empty($entry_ids)) return array();
			
			$items = $this->getBreadcrumbItems(
				$breadcrumb->get('parent_section'), $entry_ids
			);
			
			// Remove ignored entry:
			$items = array_filter($items, function($item) use ($ignore_id) {
				return $item->entry != $ignore_id;
			});
			
			return $items;
		}
		
		/**
		 * Get a list of all parent entries of a particular entry.
		 * @param Field $breadcrumb
		 * @param integer $entry_id
		 */
		public function getBreadcrumbParents(Field $breadcrumb, $entry_id) {
			$em = new EntryManager(Symphony::Engine());
			$entries = $em->fetch($entry_id);
			
			if (empty($entries)) return array();
			
			$entry = current($entries);
			
			// Find parent entries:
			$entry_ids = array($entry_id);
			
			while (true) {
				$current = Symphony::Database()->fetchVar('relation_id', 0, sprintf('
					SELECT DISTINCT
						d.relation_id
					FROM
						`tbl_entries_data_%d` AS d
					WHERE
						d.entry_id = %d
					',
					$breadcrumb->get('id'),
					$entry_id
				));
				
				if (empty($current)) break;
				
				$entry_ids[] = $entry_id = $current;
			}
			
			if (empty($entry_ids)) return array();
			
			$entry_ids = array_reverse($entry_ids);
			
			return $this->getBreadcrumbItems(
				$breadcrumb->get('parent_section'), $entry_ids
			);
		}
		
		/**
		 * Return an array of entries from an array of entry IDs
		 * maintaining the original sorting.
		 * @param integer $section_id
		 * @param array $entry_ids
		 */
		public function getBreadcrumbItems($section_id, array $entry_ids) {
			if (empty($entry_ids)) return array();
			
			$items = array(); $sort_ids = $entry_ids;
			
			foreach ($entry_ids as $index => $entry_id) {
				if (!isset(self::$entryCache[$entry_id])) continue;
				
				$items[] = self::$entryCache[$entry_id];
				
				unset($entry_ids[$index]);
			}
			
			if (!empty($entry_ids)) {
				$title = $this->getFieldBySectionId($section_id);
				
				foreach ($entry_ids as $entry_id) {
					$item = $this->getBreadcrumbItem($title, $entry_id);
					
					$items[] = self::$entryCache[$item->entry] = $item;
				}
			}
			
			// Sort entries by ID so that that appear in the same
			// order as the $entry_ids variable:
			usort($items, function($a, $b) use ($sort_ids) {
				return array_search($a->entry, $sort_ids)
					> array_search($b->entry, $sort_ids);
			});
			
			return $items;
		}
		
		/**
		 * Fetch a breadcrumb item.
		 * @param Field $title
		 * @param integer $entry_id
		 */
		public function getBreadcrumbItem(Field $title, $entry_id) {
			$result = (object)array(
				'entry'		=> $entry_id,
				'handle'	=> null,
				'value'		=> null
			);
			$data = Symphony::Database()->fetchRow(0, sprintf('
				SELECT DISTINCT
					d.*
				FROM
					`tbl_entries_data_%d` AS d
				WHERE
					d.entry_id = %d
				',
				$title->get('id'),
				$entry_id
			));
			
			if (isset($data['value'])) {
				$result->value = General::sanitize($data['value']);
			}
			
			else {
				$span = new XMLElement('span');
				$title->prepareTableValue($data, $span, $entry_id);
				$result->value = strip_tags($span->generate());
			}
			
			if (isset($data['handle'])) {
				$result->handle = $data['handle'];
			}
			
			else {
				$handle = Lang::createHandle($result->value);
			}
			
			return $result;
		}
		
		/**
		 * Quick method for fetching the section a field
		 * belongs to.
		 * @param Field $field
		 */
		public function getSectionByField(Field $field) {
			$sm = new SectionManager(Symphony::Engine());
			
			return $sm->fetch($field->get('parent_section'));
		}
		
		/**
		 * Find the first visible field in a section that is
		 * not a breadcrumb field.
		 * @param integer $section_id
		 */
		public function getFieldBySectionId($section_id) {
			$sm = new SectionManager(Symphony::Engine());
			$section = $sm->fetch($section_id);
			$fields = $section->fetchVisibleColumns();
			
			if (empty($fields)) return null;
			
			foreach ($fields as $field) {
				if ($field instanceof FieldBreadcrumb) continue;
				
				return $field;
			}
		}
	}
	
?>