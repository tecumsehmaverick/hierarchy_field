<?php

	/**
	 * @package breadcrumb_field
	 */
	
	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	require_once EXTENSIONS . '/breadcrumb_ui/lib/class.breadcrumb_ui.php';

	class FieldBreadcrumb extends Field {
		public $driver = null;

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
					PRIMARY KEY	(`id`),
					KEY `entry_id` (`entry_id`),
					KEY `relation_id` (`relation_id`)
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
			return true;
		}

		public function canPrePopulate() {
			return false;
		}

		public function isSortable() {
			return false;
		}
		
		/**
		 * Append the formatted xml output of this field as utilized as a data source.
		 *
		 * @param XMLElement $wrapper
		 *	the xml element to append the xml representation of this to.
		 * @param array $data
		 *	the current set of values for this field. the values are structured as
		 *	for displayPublishPanel.
		 * @param boolean $encode (optional)
		 *	flag as to whether this should be html encoded prior to output. this
		 *	defaults to false.
		 * @param string $mode
		 *	 A field can provide ways to output this field's data. For instance a mode
		 *  could be 'items' or 'full' and then the function would display the data
		 *  in a different way depending on what was selected in the datasource
		 *  included elements.
		 * @param integer $entry_id (optional)
		 *	the identifier of this field entry instance. defaults to null.
		 */
		public function appendFormattedElement(XMLElement $wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			if ($entry_id == null) return;
			
			$element = new XMLElement($this->get('element_name'));
			$section = $this->driver->getSectionByField($this);
			$title = $this->driver->getFieldBySectionId($section->get('id'));
			
			if (
				$mode == 'children'
				|| $mode == 'parents'
				|| $mode == 'siblings'
				|| $mode == 'tree'
			) {
				if ($mode == 'children' || $mode == 'tree') {
					$items = $this->driver->getBreadcrumbChildren(
						$this, $entry_id
					);
				}
				
				else if ($mode == 'parents') {
					$items = $this->driver->getBreadcrumbParents(
						$this, $data['relation_id']
					);
				}
				
				else if ($mode == 'siblings') {
					$items = $this->driver->getBreadcrumbChildren(
						$this, $data['relation_id'], $entry_id
					);
				}
				
				$this->buildFormattedItem($element, $items);
				
				$element->setAttribute('mode', $mode);
				$wrapper->appendChild($element);
			}
			
			else if ($mode == 'parent') {
				$items = $this->driver->getBreadcrumbParents($this, $data['relation_id']);
				$path = array();
				
				foreach ($items as $item) {
					$path[] = $item->handle;
				}
				
				if (isset($item)) {
					$child = new XMLElement('item');
					$child->setAttribute('id', $item->entry);
					$child->setAttribute('path', implode('/', $path));
					$child->setAttribute('handle', $item->handle);
					$child->setAttribute('value', $item->value);
					$element->appendChild($child);
					$element->setAttribute('mode', 'parent');
					$wrapper->appendChild($element);
				}
			}
			
			else {
				$items = $this->driver->getBreadcrumbParents($this, $data['relation_id']);
				$item = $items[] = $this->driver->getBreadcrumbItem($title, $entry_id);
				$path = array();
				
				foreach ($items as $item) {
					$path[] = $item->handle;
				}
				
				$child = new XMLElement('item');
				$child->setAttribute('id', $item->entry);
				$child->setAttribute('path', implode('/', $path));
				$child->setAttribute('handle', $item->handle);
				$child->setAttribute('value', $item->value);
				$element->appendChild($child);
				$element->setAttribute('mode', 'current');
				$wrapper->appendChild($element);
			}
		}
		
		public function buildFormattedItem($element, $items, $title, $mode) {
			foreach ($items as $item) {
				$path_items = $this->driver->getBreadcrumbParents($this, $item->entry);
				$path = array();
				
				foreach ($path_items as $path_item) {
					$path[] = $path_item->handle;
				}
				
				$path = array_reverse($path);
				
				$child = new XMLElement('item');
				$child->setAttribute('id', $item->entry);
				$child->setAttribute('path', implode('/', $path));
				$child->setAttribute('handle', $item->handle);
				$child->setAttribute('value', $item->value);
				
				if ($mode == 'tree') {
					$items = $field->driver->getBreadcrumbChildren(
						$field, $item->entry
					);
					
					$this->buildFormattedItem($child, $items, $title, $mode);
				}
				
				$element->appendChild($child);
			}
		}
		
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation = false) {
			$parents = $this->driver->getBreadcrumbChildren($this);
			$items = array(); $entry_id = null;
			$field_id = $this->get('id');
			
			if (preg_match('/^children-of:\s*(.*)/', $data[0], $matches)) {
				$mode = 'children-of';
				$paths = preg_split('%/%', $matches[1], 0, PREG_SPLIT_NO_EMPTY);
			}
			
			else {
				$mode = 'item';
				$paths = preg_split('%/%', $data[0], 0, PREG_SPLIT_NO_EMPTY);
			}
			
			// Find the item specified in the path:
			foreach ($paths as $path) {
				foreach ($parents as $parent) {
					if ($parent->handle != $path) continue;
					
					$items[] = $entry_id = $parent->entry;
					$parents = $this->driver->getBreadcrumbChildren($this, $parent->entry);
					
					break;
				}
			}
			
			// Path not found:
			if (count($items) != count($paths)) return false;
			
			if ($mode == 'children-of') {
				$this->_key++;
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				
				if (count($items)) {
					$where .= "
						AND (
							t{$field_id}_{$this->_key}.relation_id = '{$entry_id}'
						)
					";
				}
				
				else {
					$where .= "
						AND (
							t{$field_id}_{$this->_key}.relation_id IS NULL
						)
					";
				}
			}
			
			if ($mode == 'item') {
				$where .= sprintf(
					"AND (e.id = %d)",
					$entry_id
				);
			}
			
			return true;
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
		
		public function displayDatasourceFilterPanel($wrapper, $data = null, $errors = null, $prefix = null, $suffix = null) {
			parent::displayDatasourceFilterPanel($wrapper, $data, $errors, $prefix, $suffix);
			
			$help = new XMLElement('p');
			$help->setAttribute('class', 'help');
			$help->setValue(__('Enter the path to the item you want to select, optionally prepend <code>children-of:</code> to get the children of that item.'));

			$wrapper->appendChild($help);
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
		 * @param mixed $error (optional)
		 *	flag with error defaults to null.
		 * @param string $prefix (optional)
		 *	the string to be prepended to the display of the name of this field.
		 *	this defaults to null.
		 * @param string $suffix (optional)
		 *	the string to be appended to the display of the name of this field.
		 *	this defaults to null.
		 * @param integer $entry_id (optional)
		 *	the entry id of this field. this defaults to null.
		 */
		public function displayPublishPanel($wrapper, $data = null, $error = null, $prefix = null, $suffix = null, $entry_id = null) {
			$items = array();
			$name = sprintf(
				'fields%s[%s]%s',
				$prefix,
				$this->get('element_name'),
				$suffix
			);
			
			$this->driver->getBreadcrumbChildren($this, 11, 24);
			
			$label = Widget::Label($this->get('label'));
			$breadcrumb = new BreadcrumbUI($name);
			$breadcrumb->setData('type', $this->get('type'));
			$breadcrumb->setData('field', $this->get('id'));
			$breadcrumb->setData('entry', $entry_id);
			
			if (isset($data['relation_id'])) {
				$items = $this->driver->getBreadcrumbParents($this, $data['relation_id']);
			}
			
			foreach ($items as $item) {
				$breadcrumb->appendItem($item->entry, $item->value);
			}
			
			if ($error != null) {
				$breadcrumb = Widget::wrapFormElementWithError($group, $error);
			}

			$wrapper->appendChild($label);
			$wrapper->appendChild($breadcrumb);
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
		 * Default accessor for the includable elements of this field. This array
		 * will populate the Datasource included elements. Fields that have
		 * different modes will override this and add new items to the array.
		 * The Symphony convention is element_name : mode. Modes allow Fields to
		 * output different XML in datasources.
		 *
		 * @return array
		 *	the array of includable elements from this field.
		 */
		public function fetchIncludableElements() {
			$name = $this->get('element_name');
			$label = $this->get('label');
			
			return array(
				"{$name}: children",
				"{$name}: parent",
				"{$name}: parents",
				"{$name}: current",
				"{$name}: siblings",
				"{$name}: tree"
			);
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
		
		/**
		 * Format this field value for display in the publish index tables. By default,
		 * Symphony will truncate the value to the configuration setting `cell_truncation_length`.
		 * This function will attempt to use PHP's `mbstring` functions if they are available.
		 *
		 * @param array $data
		 *	an associative array of data for this string. At minimum this requires a
		 *  key of 'value'.
		 * @param XMLElement $link (optional)
		 *	an xml link structure to append the content of this to provided it is not
		 *	null. it defaults to null.
		 * @return string
		 *	the formatted string summary of the values of this field instance.
		 */
		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			if ($entry_id == null) return parent::prepareTableValue(null, $link);
			
			$items = $this->driver->getBreadcrumbParents($this, $entry_id, true);
			$sm = new SectionManager(Symphony::Engine());
			$section = $sm->fetch($this->get('parent_section'));
			$links = array();
			
			foreach ($items as $item) {
				$element = new XMLElement('a');
				$element->setAttribute('href', sprintf(
					'%s/publish/%s/edit/%d',
					SYMPHONY_URL,
					$section->get('handle'),
					$item->entry
				));
				$element->setValue($item->value);
				
				$links[] = $element->generate();
			}
			
			if (!$link instanceof XMLElement) {
				array_pop($links);
			}
			
			if ($link instanceof XMLElement) {
				return implode(' ◂ ', array_reverse($links));
			}
			
			else {
				return implode(' ▸ ', $links);
			}
		}
		
		public function preparePlainTextValue($data, $entry_id = null) {
			if ($entry_id == null) return null;
			
			$items = $this->driver->getBreadcrumbParents($this, $entry_id, true);
			$sm = new SectionManager(Symphony::Engine());
			$section = $sm->fetch($this->get('parent_section'));
			$bits = array();
			
			foreach ($items as $item) {
				$bits[] = $item->value;
			}
			
			return implode(' ▸ ', $bits);
		}

		/**
		 * Process the raw field data.
		 *
		 * @param mixed $data
		 *	post data from the entry form
		 * @param integer $status
		 *	the status code resultant from processing the data.
		 * @param boolean $simulate (optional)
		 *	true if this will tell the CF's to simulate data creation, false
		 *	otherwise. this defaults to false. this is important if clients
		 *	will be deleting or adding data outside of the main entry object
		 *	commit function.
		 * @param mixed $entry_id (optional)
		 *	the current entry. defaults to null.
		 * @return array
		 *	the processed field data.
		 */
		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = null) {
			$result = array(
				'relation_id'	=> $data
			);
			
			return $result;
		}
	}

?>