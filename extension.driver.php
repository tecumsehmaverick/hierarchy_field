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
				'version'		=> '0.1',
				'release-date'	=> '2011-05-24',
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
					`show_association` enum('yes','no') NOT NULL default 'yes',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");
			
			return true;
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
			
			$context['options'] = $this->getBreadcrumbChildren(
				$entry_id, $field, $ignore_id, true
			);
		}
		
		/**
		 * Get a list of child entries.
		 * @param Field $field
		 * @param integer $left
		 *	The lefthand of the range in which to select children from.
		 * @param integer $right
		 *	The righthand of the range in which to select children from.
		 * @param boolean $recursive
		 *	Build a complete tree of children.
		 */
		public function getBreadcrumbChildren(Field $field, $left, $right, $recursive = false) {
			$db = Symphony::Database();
			$query = '
				SELECT
					child.entry_id
				FROM
					`tbl_entries_data_%1$d` AS `child`,
					`tbl_entries_data_%1$d` AS `parent`
				WHERE
					child.left BETWEEN %2$d and %3$d
					AND parent.left = %2$d
					AND parent.right = %3$d
					AND child.depth - parent.depth%4$s
			';
			$vars = array(
				$field->get('id'),
				$left, $right
			);
			
			// Add extra SQL for recursive queries:
			$vars[] = (
				$recursive === true
				? null : ' = 1'
			);
			
			$entry_ids = $db->fetchCol('entry_id', vsprintf($query, $vars));
			
			return $this->getBreadcrumbEntries($field, $entry_ids);
		}
		
		/**
		 * Get a list of all parent entries of a particular entry.
		 * @param Field $field
		 * @param integer $left
		 *	Lefthand value of the child.
		 * @param integer $right
		 *	Righthand value of the child.
		 */
		public function getBreadcrumbParents(Field $field, $left, $right) {
			$db = Symphony::Database();
			$entry_ids = $db->fetchCol('entry_id', sprintf('
				SELECT
					parent.entry_id
				FROM
					`tbl_entries_data_%1$d` AS `child`,
					`tbl_entries_data_%1$d` AS `parent`
				WHERE
					child.left BETWEEN parent.left and parent.right
					AND child.left = %2$d
					AND child.right = %3$d
					AND parent.left != %2$d
					AND parent.right != %3$d
				ORDER BY
					parent.left
				',
				$field->get('id'),
				$left, $right
			));
			
			return $this->getBreadcrumbEntries($field, $entry_ids);
		}
		
		/**
		 * Return an array of entries from an array of entry IDs
		 * maintaining the original sorting.
		 * @param Field $field
		 * @param array $entry_ids
		 */
		public function getBreadcrumbEntries(Field $field, array $entry_ids) {
			if (empty($entry_ids)) return array();
			
			$entries = array();
			
			foreach ($entry_ids as $index => $entry_id) {
				if (!isset(self::$entryCache[$entry_id])) continue;
				
				$entries[] = self::$entryCache[$entry_id];
				
				unset($entry_ids[$index]);
			}
			
			if (!empty($entry_ids)) {
				$em = new EntryManager(Symphony::Engine());
				$sm = new SectionManager(Symphony::Engine());
				$section = $sm->fetch($field->get('parent_section'));
				$extras = $em->fetch($entry_ids, $section->get('id'));
				
				foreach ($extras as $entry) {
					self::$entryCache[$entry->get('id')] = $entry;
				}
				
				$entries = array_merge($entries, $extras);
			}
			
			// Sort entries by ID so that that appear in the same
			// order as the $entry_ids variable:
			usort($entries, function($a, $b) use ($entry_ids) {
				return array_search($a->get('id'), $entry_ids)
					> array_search($b->get('id'), $entry_ids);
			});
			
			return $entries;
		}
		
		/**
		 * Get the existing handle of an entry, or generate one if
		 * no handle exists.
		 * @param Entry $entry
		 * @param Field $field
		 */
		public function getBreadcrumbEntryHandle(Entry $entry, Field $field) {
			$data = $entry->getData($field->get('id'));
			$span = new XMLElement('span');
			
			if (isset($data['handle'])) {
				return $data['handle'];
			}
			
			$field->prepareTableValue($data, $span, $entry->get('id'));
			
			return Lang::createHandle(strip_tags($span->generate()));
		}
		
		/**
		 * Get the title value of an entry.
		 * @param Entry $entry
		 * @param Field $field
		 */
		public function getBreadcrumbEntryTitle($entry, $field) {
			$data = $entry->getData($field->get('id'));
			$span = new XMLElement('span');
			
			$field->prepareTableValue($data, $span, $entry->get('id'));
			
			return General::sanitize(strip_tags($span->generate()));
		}
		
		/**
		 * Quick method for fetching the section a field
		 * belongs to.
		 * @param Field $field
		 */
		public function getBreadcrumbSection(Field $field) {
			$sm = new SectionManager(Symphony::Engine());
			
			return $sm->fetch($field->get('parent_section'));
		}
		
		/**
		 * Find the first visible field in a section that is
		 * not a breadcrumb field.
		 * @param Section $section
		 */
		public function getBreadcrumbTitle(Section $section) {
			$fields = $section->fetchVisibleColumns();
			
			if (empty($fields)) return null;
			
			foreach ($fields as $field) {
				if ($field instanceof FieldBreadcrumb) continue;
				
				return $field;
			}
		}
	}
	
?>