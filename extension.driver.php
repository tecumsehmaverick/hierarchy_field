<?php
	
	/**
	 * @package breadcrumb_field
	 */
	
	/**
	 * Email creation and sending API.
	 */
	class Extension_Breadcrumb_Field extends Extension {
		protected $addedPublishHeaders = false;
		
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
		 * Add stylesheets and scripts to page header.
		 */
		public function addPublishHeaders() {
			if (!$this->addedPublishHeaders && isset(Symphony::Engine()->Page)) {
				$page = Symphony::Engine()->Page;
				$page->addStylesheetToHead(URL . '/extensions/breadcrumb_field/assets/publish.css');
				$page->addScriptToHead(URL . '/extensions/breadcrumb_field/assets/symphony.breadcrumb.js');
				$page->addScriptToHead(URL . '/extensions/breadcrumb_field/assets/publish.js');
			}
		}
	}
	
?>