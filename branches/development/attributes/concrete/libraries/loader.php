<?

defined('C5_EXECUTE') or die(_("Access Denied."));

/**
 * @package Core
 * @category Concrete
 * @author Andrew Embler <andrew@concrete5.org>
 * @copyright  Copyright (c) 2003-2008 Concrete5. (http://www.concrete5.org)
 * @license    http://www.concrete5.org/license/     MIT License
 *
 */

/**
 * A wrapper for loading core files, libraries, applications and models. Whenever possible the loader class should be used because it will always know where to look for the proper files, in the proper order.
 * @package Core
 * @author Andrew Embler <andrew@concrete5.org>
 * @category Concrete
 * @copyright  Copyright (c) 2003-2008 Concrete5. (http://www.concrete5.org)
 * @license    http://www.concrete5.org/license/     MIT License
 */
 
 class Loader {
		
		/** 
		 * Loads a library file, either from the site's files or from Concrete's
		 */
		public function library($lib, $pkgHandle = null) {
			if ($pkgHandle) {
				$dir = (is_dir(DIR_PACKAGES . '/' . $pkgHandle)) ? DIR_PACKAGES : DIR_PACKAGES_CORE;
				require_once($dir . '/' . $pkgHandle . '/' . DIRNAME_LIBRARIES . '/' . $lib . '.php');
			} else if (file_exists(DIR_LIBRARIES . '/' . $lib . '.php')) {
				require_once(DIR_LIBRARIES . '/' . $lib . '.php');
			} else {
				require_once(DIR_LIBRARIES_CORE . '/' . $lib . '.php');
			}
		}

		/** 
		 * Loads a model from either an application, the site, or the core Concrete directory
		 */
		public function model($mod, $pkgHandle = null) {
			
			$ret = Loader::legacyModel($mod);
			if ($ret) {
				return;
			}
			
			if ($pkgHandle) {
				$dir = (is_dir(DIR_PACKAGES . '/' . $pkgHandle)) ? DIR_PACKAGES : DIR_PACKAGES_CORE;
				require_once($dir . '/' . $pkgHandle . '/' . DIRNAME_MODELS . '/' . $mod . '.php');
			} else if (file_exists(DIR_MODELS . '/' . $mod . '.php')) {
				require_once(DIR_MODELS . '/' . $mod . '.php');
			} else {
				require_once(DIR_MODELS_CORE . '/' . $mod . '.php');
			}
		}
		
		protected function legacyModel($model) {
			switch($model) {
				case 'collection_attributes':
					Loader::model('attribute/categories/collection');
					return true;
					break;
				case 'user_attributes':
					Loader::model('attribute/categories/user');
					return true;
					break;
				case 'file_attributes':
					Loader::model('attribute/categories/file');
					return true;
					break;
				default:
					return false;
					break;
			}
		}
		
		
		/** 
		 * @access private
		 */
		public function packageElement($file, $pkgHandle, $args = null) {
			if (is_array($args)) {
				extract($args);
			}
			$dir = (is_dir(DIR_PACKAGES . '/' . $pkgHandle)) ? DIR_PACKAGES : DIR_PACKAGES_CORE;
			include($dir . '/' . $pkgHandle . '/' . DIRNAME_ELEMENTS . '/' . $file . '.php');
		}

		/** 
		 * Loads an element from C5 or the site
		 */
		public function element($file, $args = null) {
			if (is_array($args)) {
				extract($args);
			}
			if (file_exists(DIR_FILES_ELEMENTS . '/' . $file . '.php')) {
				include(DIR_FILES_ELEMENTS . '/' . $file . '.php');
			} else if (file_exists(DIR_FILES_ELEMENTS_CORE . '/' . $file . '.php')) {
				include(DIR_FILES_ELEMENTS_CORE . '/' . $file . '.php');
			}
		}

		/** 
		 * Loads a block's controller/class into memory. 
		 * <code>
		 * <?php Loader::block('autonav'); ?>
		 * </code>
		 */
		public function block($bl) {
			if (file_exists(DIR_FILES_BLOCK_TYPES . '/' . $bl . '/' . FILENAME_BLOCK_CONTROLLER)) {
				require_once(DIR_FILES_BLOCK_TYPES . '/' . $bl . '/' . FILENAME_BLOCK_CONTROLLER);
			} else if (file_exists(DIR_FILES_BLOCK_TYPES_CORE . '/' . $bl . '/' . FILENAME_BLOCK_CONTROLLER)) {
				require_once(DIR_FILES_BLOCK_TYPES_CORE . '/' . $bl . '/' . FILENAME_BLOCK_CONTROLLER);
			} else {
				// we haven't found it anywhere so we need to try applications
				// this is last because it's kind of a performance drain to run all the time
				// but that will be less of a problem when we cache the block types request
				$bt = BlockType::getByHandle($bl);
				if (is_object($bt)) { 
					$pkg = $bt->getPackageHandle();
					
					if (file_exists(DIR_PACKAGES . '/' . $pkg . '/' . DIRNAME_BLOCKS . '/' . $bl . '/' . FILENAME_BLOCK_CONTROLLER)) {
						require_once(DIR_PACKAGES . '/' . $pkg . '/' . DIRNAME_BLOCKS . '/' . $bl . '/' . FILENAME_BLOCK_CONTROLLER);		
					} else if (file_exists(DIR_PACKAGES . '/' . $pkg . '/' . DIRNAME_BLOCKS . '/' . $bl . '/' . FILENAME_BLOCK_CONTROLLER)) {
						require_once(DIR_PACKAGES_CORE . '/' . $pkg . '/' . DIRNAME_BLOCKS . '/' . $bl . '/' . FILENAME_BLOCK_CONTROLLER);
					}
				}
			}
		}
		
		/** 
		 * Loads the various files for the database abstraction layer. We would bundle these in with the db() method below but
		 * these need to be loaded before the models which need to be loaded before db() 
		 */
		public function database() {
			Loader::library('3rdparty/adodb/adodb.inc');
			Loader::library('3rdparty/adodb/adodb-exceptions.inc');
			Loader::library('3rdparty/adodb/adodb-active-record.inc');
			Loader::library('3rdparty/adodb/adodb-xmlschema03.inc');
			Loader::library('database');
		}
		
		/** 
		 * Returns the database object, or loads it if not yet created
		 * <code>
		 * <?php
		 * $db = Loader::db();
		 * $db->query($sql);
		 * </code>
		 */
		public function db($server = null, $username = null, $password = null, $database = null, $create = false) {
			static $_db;
			if (!isset($_db) || $create) {
				if ($server == null && defined('DB_SERVER')) {	
					$dsn = DB_TYPE . '://' . DB_USERNAME . ':' . DB_PASSWORD . '@' . DB_SERVER . '/' . DB_DATABASE;
				} else if ($server) {
					$dsn = DB_TYPE . '://' . $username . ':' . $password . '@' . $server . '/' . $database;
				}

				if ($dsn) {
					$_dba = @NewADOConnection($dsn);
					if (is_object($_dba)) {
						if (DB_CHARSET != '') {
							$names = 'SET NAMES \'' . DB_CHARSET . '\'';
							if (DB_COLLATE != '') {
								$names .= ' COLLATE \'' . DB_COLLATE . '\'';
							}
							$charset = 'SET CHARACTER SET \'' . DB_CHARSET . '\'';
							if (DB_COLLATE != '') {
								$charset .= ' COLLATE \'' . DB_COLLATE . '\'';
							}
							$_dba->Execute($names);
							$_dba->Execute($charset);
						}
						
						ADOdb_Active_Record::SetDatabaseAdapter($_dba);
						$_db = new Database();
						$_db->setDatabaseObject($_dba);
					} else if (defined('DB_SERVER')) {
						$v = View::getInstance();
						$v->renderError(t('Unable to connect to database.'), t('A database error occurred while processing this request.'));
					}
				} else {
					return false;
				}
			}
			
			return $_db;
		}
		
		/** 
		 * Loads a helper file. If the same helper file is contained in both the core concrete directory and the site's directory, it will load the site's first, which could then extend the core.
		 */
		public function helper($file, $pkgHandle = false) {
			// loads and instantiates the object
			if ($pkgHandle != false) {
				$dir = (is_dir(DIR_PACKAGES . '/' . $pkgHandle)) ? DIR_PACKAGES : DIR_PACKAGES_CORE;
				$class = Object::camelcase($file) . "Helper";
				require_once($dir . '/' . $pkgHandle . '/' . DIRNAME_HELPERS . '/' . $file . '.php');
			} else if (file_exists(DIR_HELPERS . '/' . $file . '.php')) {
				// first we check if there's an object of the SAME kind in the core. If so, then we load the core first, then, we load the second one (site)
				// and we hope the second one EXTENDS the first
				if (file_exists(DIR_HELPERS_CORE . '/' . $file . '.php')) {
					require_once(DIR_HELPERS_CORE . '/' . $file . '.php');
					require_once(DIR_HELPERS . '/' . $file . '.php');
					$class = "Site" . Object::camelcase($file) . "Helper";
				} else {
					require_once(DIR_HELPERS . '/' . $file . '.php');
					$class = Object::camelcase($file) . "Helper";
				}
			} else {
				require_once(DIR_HELPERS_CORE . '/' . $file . '.php');
				$class = Object::camelcase($file) . "Helper";
				
			}
			
			$cl = new $class;
			return $cl;
		}
		
		/**
		 * @access private
		 */
		public function package($pkgHandle) {
			// loads and instantiates the object
			$dir = (is_dir(DIR_PACKAGES . '/' . $pkgHandle)) ? DIR_PACKAGES : DIR_PACKAGES_CORE;
			if (file_exists($dir . '/' . $pkgHandle . '/' . FILENAME_PACKAGE_CONTROLLER)) {
				require_once($dir . '/' . $pkgHandle . '/' . FILENAME_PACKAGE_CONTROLLER);
				$class = Object::camelcase($pkgHandle) . "Package";
				if (class_exists($class)) {
					$cl = new $class;
					return $cl;
				}
			}
		}
		
		/**
		 * @access private
		 */
		public function dashboardModuleController($dbhHandle, $pkg = null) {
			$class = Object::camelcase($dbhHandle . 'DashboardModuleController');
			if (!class_exists($class)) {
				$file1 = DIR_FILES_CONTROLLERS . '/' . DIRNAME_DASHBOARD . '/' . DIRNAME_DASHBOARD_MODULES . '/' . $dbhHandle . '.php';
				if (is_object($pkg)) {
					$pkgHandle = $pkg->getPackageHandle();
					$dir = (is_dir(DIR_PACKAGES . '/' . $pkgHandle)) ? DIR_PACKAGES : DIR_PACKAGES_CORE;
					$file2 = $dir . '/' . $pkgHandle . '/' . DIRNAME_CONTROLLERS . '/' . DIRNAME_DASHBOARD . '/' . DIRNAME_DASHBOARD_MODULES . '/' . $dbhHandle . '.php';
				}
				$file3 = DIR_FILES_CONTROLLERS_REQUIRED . '/' . DIRNAME_DASHBOARD . '/' . DIRNAME_DASHBOARD_MODULES . '/' . $dbhHandle . '.php';
				if (file_exists($file1)) {
					include($file1);
				} else if (isset($file2) && file_exists($file2)) {
					include($file2);
				} else {
					include($file3);
				}
			}

			$controller = new $class();
			return $controller;
		}
		
		/** 
		 * @access private
		 */		
		public function dashboardModule($dbhHandle, $pkg = null) {
			$controller = Loader::dashboardModuleController($dbhHandle, $pkg);
			extract($controller->getSets());
			extract($controller->getHelperObjects());
			$this->controller = $controller;

			// now the view
			$file1 = DIR_FILES_ELEMENTS . '/' . DIRNAME_DASHBOARD . '/' . DIRNAME_DASHBOARD_MODULES . '/' . $dbhHandle . '.php';
			if (is_object($pkg)) {
				$pkgHandle = $pkg->getPackageHandle();
				$file2 = DIR_PACKAGES . '/' . $pkgHandle . '/' . DIRNAME_ELEMENTS . '/' . DIRNAME_DASHBOARD . '/' . DIRNAME_DASHBOARD_MODULES . '/' . $dbhHandle . '.php';
			}
			$file3 = DIR_FILES_ELEMENTS_CORE . '/' . DIRNAME_DASHBOARD . '/' . DIRNAME_DASHBOARD_MODULES . '/' . $dbhHandle . '.php';
			if (file_exists($file1)) {
				include($file1);
			} else if (isset($file2) && file_exists($file2)) {
				include($file2);
			} else {
				include($file3);
			}
		}
		
		/** 
		 * Gets the path to a particular page type controller
		 */
		public function pageTypeControllerPath($ctHandle) {
			
			Loader::model('collection_types');
			$ct = CollectionType::getByHandle($ctHandle);
			$pkgHandle = $ct->getPackageHandle();

			if ($pkgHandle != '') {
				$packageDir = (is_dir(DIR_PACKAGES . '/' . $pkgHandle)) ? DIR_PACKAGES : DIR_PACKAGES_CORE;
			}

			if (file_exists(DIR_FILES_CONTROLLERS . "/" . DIRNAME_PAGE_TYPES . "/{$ctHandle}.php")) {
				$path = DIR_FILES_CONTROLLERS . "/" . DIRNAME_PAGE_TYPES . "/{$ctHandle}.php";
			} else if (isset($packageDir) && (file_exists($packageDir . '/' . $pkgHandle . '/' . DIRNAME_CONTROLLERS . '/' . DIRNAME_PAGE_TYPES . '/' . $ctHandle . '.php'))) {
				$path = $packageDir . '/' . $pkgHandle . '/' . DIRNAME_CONTROLLERS . '/' . DIRNAME_PAGE_TYPES . '/' . $ctHandle . '.php';
			} else if (file_exists(DIR_FILES_CONTROLLERS_REQUIRED . "/" . DIRNAME_PAGE_TYPES . "/{$ctHandle}.php")) {
				$path = DIR_FILES_CONTROLLERS_REQUIRED . "/" . DIRNAME_PAGE_TYPES . "/{$ctHandle}.php";
			}
			
			return $path;
		}
		/** 
		 * Loads a controller for either a page or view
		 */
		public function controller($item) {
			if ($item instanceof Page) {
				$c = $item;
				if ($c->getCollectionTypeID() > 0) {					
					$ctHandle = $c->getCollectionTypeHandle();
					$path = Loader::pageTypeControllerPath($ctHandle, $item->getPackageHandle());
					if ($path != false) {
						require_once($path);
						$class = Object::camelcase($ctHandle) . 'PageTypeController';
					}
				} else if ($c->isGeneratedCollection()) {
					$file = $c->getCollectionFilename();
					if ($file != '') {
						// strip off PHP suffix for the $path variable, which needs it gone
						if (strpos($file, FILENAME_COLLECTION_VIEW) !== false) {
							$path = substr($file, 0, strpos($file, '/'. FILENAME_COLLECTION_VIEW));
						} else {
							$path = substr($file, 0, strpos($file, '.php'));
						}
					}
				}
			} else if ($item instanceof Block || $item instanceof BlockType) {
				if ($item->getPackageID() > 0 && file_exists(DIR_PACKAGES . '/' . $item->getPackageHandle() . '/' . DIRNAME_BLOCKS . '/' . $item->getBlockTypeHandle() . '/' . FILENAME_BLOCK_CONTROLLER)) {
					require_once(DIR_PACKAGES . '/' . $item->getPackageHandle() . '/' . DIRNAME_BLOCKS . '/' . $item->getBlockTypeHandle() . '/' . FILENAME_BLOCK_CONTROLLER);
				} else if (file_exists(DIR_FILES_BLOCK_TYPES . '/' . $item->getBlockTypeHandle() . '/' . FILENAME_BLOCK_CONTROLLER)) {
					require_once(DIR_FILES_BLOCK_TYPES . "/" . $item->getBlockTypeHandle() . "/" . FILENAME_BLOCK_CONTROLLER);
				} else if (file_exists(DIR_FILES_BLOCK_TYPES_CORE . '/' . $item->getBlockTypeHandle() . '/' . FILENAME_BLOCK_CONTROLLER)) {
					require_once(DIR_FILES_BLOCK_TYPES_CORE . "/" . $item->getBlockTypeHandle() . "/" . FILENAME_BLOCK_CONTROLLER);
				}
				$class = Object::camelcase($item->getBlockTypeHandle()) . 'BlockController';
				if (class_exists($class) && $item instanceof BlockType) {
					$controller = new $class($item);
				}
				
				if ($item instanceof Block) {
					$c = $item->getBlockCollectionObject();
				}
				
			} else {
				$path = $item;
			}
			
			$controllerFile = $path . '.php';

			if ($path != '') {
				if (file_exists(DIR_FILES_CONTROLLERS . $controllerFile)) {
					include(DIR_FILES_CONTROLLERS . $controllerFile);
					$include = true;
				} else if (file_exists(DIR_FILES_CONTROLLERS . $path . '/' . FILENAME_COLLECTION_CONTROLLER)) {
					include(DIR_FILES_CONTROLLERS . $path . '/' . FILENAME_COLLECTION_CONTROLLER);
					$include = true;
				} else if (file_exists(DIR_FILES_CONTROLLERS_REQUIRED . $controllerFile)) {
					include(DIR_FILES_CONTROLLERS_REQUIRED . $controllerFile);
					$include = true;
				} else if (file_exists(DIR_FILES_CONTROLLERS_REQUIRED . $path . '/' . FILENAME_COLLECTION_CONTROLLER)) {
					include(DIR_FILES_CONTROLLERS_REQUIRED . $path . '/' . FILENAME_COLLECTION_CONTROLLER);
					$include = true;

				} else if (is_object($item)) {
					if ($item->getPackageID() > 0 && (file_exists(DIR_PACKAGES . '/' . $item->getPackageHandle() . '/' . DIRNAME_CONTROLLERS . $controllerFile))) {
						include(DIR_PACKAGES . '/' . $item->getPackageHandle() . '/' . DIRNAME_CONTROLLERS . $controllerFile);
						$include = true;
					} else if ($item->getPackageID() > 0 && (file_exists(DIR_PACKAGES . '/' . $item->getPackageHandle() . '/' . DIRNAME_CONTROLLERS . $path . '/'. FILENAME_COLLECTION_CONTROLLER))) {
						include(DIR_PACKAGES . '/' . $item->getPackageHandle() . '/' . DIRNAME_CONTROLLERS . $path . '/'. FILENAME_COLLECTION_CONTROLLER);
						$include = true;
					}
				}
				
				if ($include) {
					$class = Object::camelcase($path) . 'Controller';
				}
			}
			
			if (!isset($controller)) {
				if ($class && class_exists($class)) {
					// now we get just the filename for this guy, so we can extrapolate
					// what our controller is named
					$controller = new $class($item);
				} else {
					$controller = new Controller($item);
				}
			}
			
			if (is_object($c)) {
				$controller->setCollectionObject($c);
			}
			
			$controller->setupRestrictedMethods();
			return $controller;
		}

		/**
		* Instantiates one of our Soap Client Singletons
		*/		
		public function soapClient($client) {			
			Loader::library('soap_clients');
			$client .= 'SoapClient';			
			$api = call_user_func_array($client.'::getInstance',Array());					
			$api->setup();
			return $api;
		}
	}