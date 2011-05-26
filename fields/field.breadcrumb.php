<?php

	/**
	 * @package breadcrumb_field
	 */
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	require_once EXTENSIONS . '/breadcrumb_ui/lib/class.breadcrumb_ui.php';

	class FieldBreadcrumb extends Field {
		protected $driver = null;

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct($parent) {
			parent::__construct($parent);
			
			$this->driver = $this->_engine->ExtensionManager->create('breadcrumb_field');
			
			$this->_name = 'Breadcrumb';
			$this->_required = true;
			$this->_showassociation = true;
			
			// Set defaults:
			$this->set('show_column', 'no');
			$this->set('show_association', 'yes');
			$this->set('required', 'yes');
			$this->set('related_field_id', null);
		}

		public function createTable() {
			$field_id = $this->get('id');

			return $this->_engine->Database->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$field_id}` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`relation_id` int(11) unsigned DEFAULT NULL,
					`value` text DEFAULT NULL,
					`depth` int(11) unsigned NOT NULL,
					PRIMARY KEY	(`id`),
					KEY `entry_id` (`entry_id`),
					KEY `relation_id` (`relation_id`)
					KEY `depth` (`depth`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;
			");
		}

		public function allowDatasourceOutputGrouping() {
			return false;
		}

		public function allowDatasourceParamOutput() {
			return false;
		}

		public function canFilter() {
			return false;
		}

		public function canPrePopulate() {
			return false;
		}

		public function isSortable() {
			return false;
		}

		/**
		 * Commit the settings of this field from the section editor to
		 * create an instance of this field in a section.
		 *
		 * @return boolean
		 *	true if the commit was successful, false otherwise.
		 */
		public function commit() {
			if (!parent::commit()) return false;
			
			$id = $this->get('id');
			$handle = $this->handle();
			
			if ($id === false) return false;
			
			$fields = array(
				'field_id'			=> $id,
				'show_association'	=> $this->get('show_association')
			);
			
			$this->Database->query("
				DELETE FROM
					`tbl_fields_{$handle}`
				WHERE
					`field_id` = '{$id}'
				LIMIT 1
			");
			
			return $this->Database->insert($fields, "tbl_fields_{$handle}");
		}
		
		/**
		 * Display the default settings panel, calls the `buildSummaryBlock`
		 * function after basic field settings are added to the wrapper.
		 *
		 * @see buildSummaryBlock()
		 * @param XMLElement $wrapper
		 *	the input XMLElement to which the display of this will be appended.
		 * @param mixed errors (optional)
		 *	the input error collection. this defaults to null.
		 */
		public function displaySettingsPanel($wrapper, $errors = null) {
			//$this->driver->addSettingsHeaders($this->_engine->Page);

			parent::displaySettingsPanel($wrapper, $errors);

			$order = $this->get('sortorder');
			
			// Options:
			$list = new XMLElement('ul');
			$list->setAttribute('class', 'options-list');

			$item = new XMLElement('li');
			$this->appendShowAssociationCheckbox($item);
			$list->appendChild($item);

			$item = new XMLElement('li');
			$this->appendShowColumnCheckbox($item);
			$list->appendChild($item);
			
			$wrapper->appendChild($list);
			$wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' field-textbox');
		}
		
		/**
		 * Display the publish panel for this field. The display panel is the
		 * interface shown to Authors that allow them to input data into this 
		 * field for an Entry.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the html defined user interface to this
		 *	field.
		 * @param array $data (optional)
		 *	any existing data that has been supplied for this field instance.
		 *	this is encoded as an array of columns, each column maps to an
		 *	array of row indexes to the contents of that column. this defaults
		 *	to null.
		 * @param mixed $flagWithError (optional)
		 *	flag with error defaults to null.
		 * @param string $fieldnamePrefix (optional)
		 *	the string to be prepended to the display of the name of this field.
		 *	this defaults to null.
		 * @param string $fieldnameSuffix (optional)
		 *	the string to be appended to the display of the name of this field.
		 *	this defaults to null.
		 * @param integer $entry_id (optional)
		 *	the entry id of this field. this defaults to null.
		 */
		public function displayPublishPanel($wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$items = array();
			$name = sprintf(
				'fields%s[%s]%s',
				$prefix,
				$this->get('element_name'),
				$postfix
			);
			
			$label = Widget::Label($this->get('label'));
			$breadcrumb = new BreadcrumbUI($name);
			$breadcrumb->setData('type', $this->get('type'));
			$breadcrumb->setData('field', $this->get('id'));
			$breadcrumb->setData('entry', $entry_id);
			
			if (isset($data['relation_id'])) {
				$items = $this->driver->getParentEntries(
					$this->get('id'),
					$data['relation_id']
				);
			}
			
			$this->driver->getChildEntries($this->get('id'), $entry_id);
			
			foreach ($items as $id => $title) {
				$breadcrumb->appendItem($id, $title);
			}
			
			if ($error != null) {
				$breadcrumb = Widget::wrapFormElementWithError($group, $error);
			}

			$wrapper->appendChild($label);
			$wrapper->appendChild($breadcrumb);
		}
		
		/**
		 * Allows a field to set default settings.
		 *
		 * @param array $settings
		 *	the array of settings to populate with their defaults.
		 */
		public function findDefaults(&$fields) {
			if (!isset($fields['show_association'])) {
				$fields['show_association'] = 'yes';
			}
		}
		
		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			if ($entry_id == null) return parent::prepareTableValue(null, $link);
			
			$items = $this->driver->getParentEntries($this->get('id'), $entry_id);
			$sm = new SectionManager(Symphony::Engine());
			$section = $sm->fetch($this->get('parent_section'));
			$links = array();
			
			foreach ($items as $entry_id => $item) {
				$element = new XMLElement('a');
				$element->setAttribute('href', sprintf(
					'%s/publish/%s/edit/%d',
					SYMPHONY_URL,
					$section->get('handle'),
					$entry_id
				));
				$element->setValue($item);
				
				$links[] = $element->generate();
			}
			
			if (!$link instanceof XMLElement) {
				array_shift($links);
			}
			
			if ($link instanceof XMLElement) {
				return implode(' ◂ ', array_reverse($links));
			}
			
			else {
				return implode(' ▸ ', $links);
			}
		}

		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			if (empty($data)) return null;
			
			$result = array(
				'relation_id'	=> $data
			);
			
			return $result;
		}
	}

?>