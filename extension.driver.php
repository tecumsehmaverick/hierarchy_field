<?php
	
	/**
	 * @package breadcrumb_field
	 */
	
	/**
	 * A new link field, used to build hierarchical content.
	 */
	class Extension_Breadcrumb_Field extends Extension {
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
		
		public function getTitleField($section) {
			$fields = $section->fetchVisibleColumns();
			
			if (empty($fields)) return null;
			
			foreach ($fields as $field) {
				if ($field instanceof FieldBreadcrumb) continue;
				
				return $field;
			}
		}
		
		public function getEntryTitle($entry, $field) {
			$data = $entry->getData($field->get('id'));
			$span = new XMLElement('span');
			
			$field->prepareTableValue($data, $span, $entry->get('id'));
			
			return strip_tags($span->generate());
		}
		
		public function getParentEntries($field_id, $entry_id) {
			$db = Symphony::Database();
			$em = new EntryManager(Symphony::Engine());
			$sm = new SectionManager(Symphony::Engine());
			$entries = $em->fetch($entry_id);
			
			if (empty($entries)) return array();
			
			$entry = current($entries);
			$section = $sm->fetch($entry->get('section_id'));
			$title = $this->getTitleField($section);
			
			// Do nothing if there are no visible fields:
			if ($title === null) return array();
			
			// Find parent entries:
			$entry_ids = array($entry_id);
			
			while (true) {
				$current = $db->fetchVar('relation_id', 0, sprintf('
					SELECT
						d.relation_id
					FROM
						`tbl_entries_data_%d` AS d
					WHERE
						d.entry_id = %d
					',
					$field_id, $entry_id
				));
				
				if (empty($current)) break;
				
				$entry_ids[] = $entry_id = $current;
			}
			
			$entry_ids = array_reverse($entry_ids);
			$entries = $em->fetch($entry_ids, $section->get('id'));
			
			// Find parent entry titles:
			$titles = array();
			
			foreach ($entries as $entry) {
				$titles[$entry->get('id')] = $this->getEntryTitle($entry, $title);
			}
			
			return $titles;
		}
		
		public function getChildEntries($field_id, $entry_id = null, $ignore_id = null) {
			$db = Symphony::Database();
			$em = new EntryManager(Symphony::Engine());
			$fm = new FieldManager(Symphony::Engine());
			$sm = new SectionManager(Symphony::Engine());
			
			$field = $fm->fetch($field_id);
			$section = $sm->fetch($field->get('parent_section'));
			$title = $this->getTitleField($section);
			$parent = $em->fetch($entry_id);
			
			// Do nothing if there are no visible fields:
			if ($title === null) return array();
			
			if ($parent === false) {
				/**
				 * @todo This query is probably very slow.
				 */
				$entry_ids = $db->fetchCol('id', sprintf('
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
						)
					',
					$section->get('id'),
					$field_id
				));
			}
			
			else {
				$parent = current($parent);
				$entry_ids = $db->fetchCol('entry_id', sprintf('
					SELECT
						d.entry_id
					FROM
						`tbl_entries_data_%d` AS d
					WHERE
						d.relation_id = %d
					',
					$field_id, $entry_id
				));
			}
			
			if (empty($entry_ids)) return array();
			
			$entries = $em->fetch($entry_ids, $section->get('id'));
			
			// Find parent entry titles:
			$titles = array();
			
			foreach ($entries as $entry) {
				if ($entry->get('id') == $ignore_id) continue;
				
				$titles[$entry->get('id')] = $this->getEntryTitle($entry, $title);
			}
			
			return $titles;
		}
		
		public function appendBreadcrumbOptions($context) {
			if ($context['data']['type'] != 'breadcrumb') return;
			
			$context['options'] = $this->getChildEntries(
				$context['data']['field'],
				$context['data']['location'],
				$context['data']['entry']
			);
		}
	}
	
?>