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
					KEY `relation_id` (`relation_id`),
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
			$section = $this->driver->getBreadcrumbSection($this);
			$title = $this->driver->getBreadcrumbTitle($section);
			
			if ($mode == 'children' || $mode == 'parents' || $mode == 'siblings') {
				if ($mode == 'children') {
					$entries = $this->driver->getBreadcrumbChildren(
						$entry_id, $this
					);
				}
				
				else if ($mode == 'parents') {
					$entries = $this->driver->getBreadcrumbParents(
						$data['relation_id'], $this
					);
				}
				
				else if ($mode == 'siblings') {
					$entries = $this->driver->getBreadcrumbChildren(
						$data['relation_id'], $this, $entry_id
					);
				}
				
				foreach ($entries as $entry) {
					$handle = $this->driver->getBreadcrumbEntryHandle($entry, $title);
					$value = $this->driver->getBreadcrumbEntryTitle($entry, $title);
					
					$item = new XMLElement('item');
					$item->setAttribute('id', $entry->get('id'));
					$item->setAttribute('handle', $handle);
					$item->setValue($value);
					$element->appendChild($item);
				}
				
				$element->setAttribute('mode', $mode);
			}
			
			else {
				$entries = $this->driver->getBreadcrumbParents($data['relation_id'], $this);
				$path = array();
				
				foreach ($entries as $entry) {
					$path[] = $this->driver->getBreadcrumbEntryHandle($entry, $title);
				}
				
				$element->setAttribute('mode', 'path');
				$element->setValue(implode('/', $path));
			}
			
			$wrapper->appendChild($element);
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
			
			$this->driver->getBreadcrumbChildren(11, $this, 24);
			
			$label = Widget::Label($this->get('label'));
			$breadcrumb = new BreadcrumbUI($name);
			$breadcrumb->setData('type', $this->get('type'));
			$breadcrumb->setData('field', $this->get('id'));
			$breadcrumb->setData('entry', $entry_id);
			
			if (isset($data['relation_id'])) {
				$items = $this->driver->getBreadcrumbParents($data['relation_id'], $this, true);
			}
			
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
				"{$name}: path",
				"{$name}: children",
				"{$name}: parents",
				"{$name}: siblings"
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
			
			$items = $this->driver->getBreadcrumbParents($entry_id, $this, true);
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
				array_pop($links);
			}
			
			if ($link instanceof XMLElement) {
				return implode(' ◂ ', array_reverse($links));
			}
			
			else {
				return implode(' ▸ ', $links);
			}
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