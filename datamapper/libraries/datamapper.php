<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Data Mapper ORM Class
 *
 * Transforms database tables into objects.
 *
 * @license     MIT License
 * @package     DataMapper ORM
 * @category    DataMapper ORM
 * @author      Harro "WanWizard" Verton
 * @link        http://datamapper.wanwizard.eu
 * @version     2.0.0
 */

// -------------------------------------------------------------------------
// Global definitions
// -------------------------------------------------------------------------

/**
 * DataMapper version
 */
define('DATAMAPPER_VERSION', '2.0.0');

/**
 * shortcut for the directory separator
 */
! defined('DS') AND define('DS', DIRECTORY_SEPARATOR);

/**
 * enable exceptions if not already set
 */
! defined('DATAMAPPER_EXCEPTIONS') AND define('DATAMAPPER_EXCEPTIONS', TRUE);

// -------------------------------------------------------------------------
// DataMapper class definition
// -------------------------------------------------------------------------

class DataMapper implements IteratorAggregate
{
	// -------------------------------------------------------------------------
	// Static class definition
	// -------------------------------------------------------------------------

	/**
	 * storage for the CI "superobject"
	 *
	 * @var object
	 */
	public static $CI = NULL;

	/**
	 * storage for the location of the DataMapper installation
	 *
	 * @var string
	 */
	protected static $dm_path = NULL;

	/**
	 * storage for additional model paths for the autoloader
	 *
	 * @var array
	 */
	protected static $dm_model_paths = array();

	/**
	 * storage for additional extension paths for the autoloader
	 *
	 * @var array
	 */
	protected static $dm_extension_paths = array();

	/**
	 * track the initialisation state of DataMapper
	 *
	 * @var boolean
	 */
	protected static $dm_initialized = FALSE;

	/**
	 * DataMapper default global configuration
	 *
	 * @var array
	 */
	protected static $dm_global_config = array(
		'prefix'					=> '',
		'join_prefix'				=> '',
		'error_prefix'				=> '<p>',
		'error_suffix'				=> '</p>',
		'model_prefix' 				=> '',
		'model_suffix' 				=> '',
		'created_field'				=> 'created',
		'updated_field'				=> 'updated',
		'delete_field'				=> 'deleted',
		'delete_uses_timestamp'		=> FALSE,
		'local_time'				=> FALSE,
		'unix_timestamp'			=> TRUE,
		'timestamp_format'			=> '',
		'lang_file_format'			=> 'model_${model}',
		'field_label_lang_format'	=> '${model}_${field}',
		'auto_transaction'			=> FALSE,
		'auto_populate_has_many'	=> FALSE,
		'auto_populate_has_one'		=> FALSE,
		'all_array_uses_keys'		=> FALSE,
		'db_params'					=> FALSE,
		'cache_path'				=> FALSE,
		'cache_expiration'			=> FALSE,
		'extensions_path'			=> array(),
		'extensions'				=> array(),
		'extension_overload'		=> FALSE,
		'cascade_delete'			=> TRUE,
		'free_result_threshold'		=> 100,
	);

	/**
	 * global object configuration
	 *
	 * This array will contain the config of all loaded DataMapper models
	 *
	 * @var array
	 */
	protected static $dm_model_config = array();

	/**
	 * storage for available DataMapper extension methods
	 *
	 * @var array
	 */
	protected static $dm_extension_methods = array(
		// core extension: array methods
		'from_array'             => 'DataMapper_Array',
		'to_array'               => 'DataMapper_Array',
		'all_to_array'           => 'DataMapper_Array',
		'all_to_single_array'    => 'DataMapper_Array',

		// core extension: csv methods
		'csv_export'               => 'DataMapper_Csv',
		'csv_import'               => 'DataMapper_Csv',

		// core extension: json methods
		'from_json'              => 'DataMapper_Json',
		'to_json'                => 'DataMapper_Json',
		'all_to_json'            => 'DataMapper_Json',
		'set_json_content_type'  => 'DataMapper_Json',

		// core extension: cache methods
		'get_cached'             => 'DataMapper_Simplecache',
		'clear_cache'            => 'DataMapper_Simplecache',

		// core extension: cache methods
		'row_index'              => 'DataMapper_Rowindex',
		'row_indices'            => 'DataMapper_Rowindex',

		// core extension: function methods
		'func'                   => 'DataMapper_Functions',
		'dm_func'                => 'DataMapper_Functions',
		'dm_field_func'          => 'DataMapper_Functions',

		// core extension: paged methods
		'get_paged'              => 'DataMapper_Paged',
		'get_paged_iterated'     => 'DataMapper_Paged',

		// core extension: transaction methods
		'trans_begin'            => 'DataMapper_Transactions',
		'trans_commit'           => 'DataMapper_Transactions',
		'trans_complete'         => 'DataMapper_Transactions',
		'trans_off'              => 'DataMapper_Transactions',
		'trans_rollback'         => 'DataMapper_Transactions',
		'trans_start'            => 'DataMapper_Transactions',
		'trans_status'           => 'DataMapper_Transactions',
		'trans_strict'           => 'DataMapper_Transactions',
		'dm_auto_trans_begin'    => 'DataMapper_Transactions',
		'dm_auto_trans_complete' => 'DataMapper_Transactions',

		// core extension: translate methods
		'translate'              => 'DataMapper_Translate',

		// core extension: validation methods
		'validate'               => 'DataMapper_Validation',
		'skip_validation'        => 'DataMapper_Validation',
		'force_validation'       => 'DataMapper_Validation',
		'run_get_rules'          => 'DataMapper_Validation',
	);

	/**
	 * storage for table aliases for loaded models
	 *
	 * @var array
	 */
	protected static $dm_table_aliases = array();

	/**
	 * storage for table aliases counter
	 *
	 * @var array
	 */
	protected static $alias_counter = 0;

	/**
	 * storage for subquery counter
	 *
	 * @var array
	 */
	protected static $subquery_counter = 0;

	// --------------------------------------------------------------------

	/**
	 * autoloads object classes that are used with DataMapper.
	 * this method will look in any model directories available to CI.
	 *
	 * Note:
	 * it is important that they are autoloaded as loading them manually with
	 * CodeIgniter's loader class will cause DataMapper's __get and __set functions
	 * to not function.
	 *
	 * @param	string	$class	Name of class to load.
	 *
	 * @return	void
	 */
	public static function dm_autoload($class)
	{
		// don't attempt to autoload before DataMapper is initialized, or any CI_ , EE_, or application prefixed classes
		if ( is_null(DataMapper::$CI) OR in_array(substr($class, 0, 3), array('CI_', 'EE_')) OR strpos($class, DataMapper::$CI->config->item('subclass_prefix')) === 0 )
		{
			return;
		}

		// prepare class
		$class = strtolower($class);

		// check for a DataMapper core extension class
		if ( strpos($class, 'datamapper_') === 0 )
		{
			foreach ( DataMapper::$dm_extension_paths as $path )
			{
				$file = $path . DS . substr($class,11) . EXT;
				if ( file_exists($file) )
				{
					require_once($file);
					break;
				}
			}
		}

		// not a support class? check for a model next
		if ( ! class_exists($class) )
		{
			// prepare the possible model paths
			$paths = array_merge( DataMapper::$CI->load->get_package_paths(false), DataMapper::$dm_model_paths );

			foreach ( $paths as $path )
			{
				// prepare file
				$file = $path . 'models' . DS . $class . EXT;

				// Check if file exists, require_once if it does
				if ( file_exists($file) )
				{
					require_once($file);
					break;
				}
			}

			// if the class is still not loaded, do a recursive search of model paths for the class
			if ( ! class_exists($class) )
			{
				foreach ( $paths as $path )
				{
					$found = DataMapper::dm_recursive_require_once($class, $path . 'models');
					if ( $found ) break;
				}
			}
		}

	}

	// --------------------------------------------------------------------

	/**
	 * Recursive Require Once
	 *
	 * Recursively searches the path for the class, require_once if found.
	 *
	 * @param	string	$class	Name of class to look for
	 * @param	string	$path	Current path to search
	 *
	 * @return	boolean	TRUE if the class was found and loaded, FALSE otherwise
	 */
	protected static function dm_recursive_require_once($class, $path)
	{
		$found = FALSE;
		if ( is_dir($path) )
		{
			if ( $handle = opendir($path) )
			{
				while ( FALSE !== ($dir = readdir($handle)) )
				{
					// If dir does not contain a dot
					if ( strpos($dir, '.') === FALSE )
					{
						// Prepare recursive path
						$recursive_path = $path . '/' . $dir;

						// Prepare file
						$file = $recursive_path . '/' . $class . EXT;

						// Check if file exists, require_once if it does
						if ( file_exists($file)  )
						{
							require_once($file);
							$found = TRUE;
							break;
						}
						elseif ( is_dir($recursive_path) )
						{
							// Do a recursive search of the path for the class
							DataMapper::dm_recursive_require_once($class, $recursive_path);
						}
					}
				}

				closedir($handle);
			}
		}
		return $found;
	}

	// --------------------------------------------------------------------

	/**
	 * manually add paths for the model autoloader
	 *
	 * @param	mixed	$paths	path or array of paths to search
	 */
	public static function add_model_path($paths)
	{
		// make sure $paths is an array
		! is_array($paths) AND $paths = array($paths);

		foreach($paths as $path)
		{
			$path = realpath(rtrim($path, DS) . DS);
			if ( $path AND is_dir($path.'models') AND ! in_array($path, DataMapper::$dm_model_paths))
			{
				DataMapper::$dm_model_paths[] = $path;
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * manually add paths for the extensions autoloader
	 *
	 * @param	mixed	$paths		path or array of paths to search
	 * @param	string	$position	position to insert the path (after,before)
	 */
	public static function add_extension_path($paths, $position = 'after')
	{
		// make sure $paths is an array
		! is_array($paths) AND $paths = array($paths);

		foreach($paths as $path)
		{
			// check if the path exists
			$path = realpath(rtrim($path, DS) . DS);
			if ( is_dir($path) )
			{
				// if it's present, remove it
				if ( $index = array_search($path, DataMapper::$dm_extension_paths) )
				{
					unset(DataMapper::$dm_extension_paths[$index]);
				}

				// and insert it in the required spot
				if ( $position == 'before' )
				{
					array_unshift(DataMapper::$dm_extension_paths[], $path);

				}
				elseif ( $position == 'after' )
				{
					DataMapper::$dm_extension_paths[] = $path;
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Validate a config array
	 *
	 * Validates the contents of the DataMapper configuration array passed
	 *
	 * @param	array	$config		Array with DataMapper configuration values
	 * @param	string	$context	Name of the context: 'global' or <model_name>
	 *
	 * @return	void
	 *
	 * @throws	DataMapper_Exception	in case the configuration array passed does not validate
	 */
	protected static function dm_validate_config(&$config, $context)
	{
		foreach ( $config as $name => $value )
		{
			switch ($name)
			{
				// check and validate string values
				case 'prefix':
				case 'join_prefix':
				case 'error_prefix':
				case 'error_suffix':
				case 'created_field':
				case 'updated_field':
				case 'delete_field':
				case 'model_prefix':
				case 'model_suffix':
				case 'timestamp_format':
				case 'lang_file_format':
				case 'field_label_lang_format':
					empty($value) AND $config[$name] = $value = '';
					if ( ! is_string($value) )
					{
						throw new DataMapper_Exception("DataMapper: Error in the '$context' configuration => item '$name' must be a string value");
					}
					break;

				// check and validate boolean values
				case 'local_time':
				case 'unix_timestamp':
				case 'auto_transaction':
				case 'auto_populate_has_many':
				case 'auto_populate_has_one':
				case 'all_array_uses_keys':
				case 'cascade_delete':
				case 'extension_overload':
				case 'delete_uses_timestamp':
					if ( ! is_bool($value) )
					{
						throw new DataMapper_Exception("DataMapper: Error in the '$context' configuration => item '$name' must be a boolean value");
					}
					break;

				// check and validate integer values
				case 'free_result_threshold':
					is_numeric($value) and $value = (int) $value;
					if ( ! is_int($value) )
					{
						throw new DataMapper_Exception("DataMapper: Error in the '$context' configuration => item '$name' must be a integer value");
					}
					break;

				// check and validate array values
				case 'extensions':
				case 'extensions_path':
					if ( ! is_array($value) )
					{
						throw new DataMapper_Exception("DataMapper: Error in the '$context' configuration => item '$name' must be a array");
					}
					break;

				// special cases
				case 'cache_path':
					empty($value) AND $config[$name] = $value = FALSE;
					if ( is_string($value) )
					{
						if ( ! is_dir($value) AND ! is_dir($config[$name] = $value = APPPATH.$value)  )
						{
							throw new DataMapper_Exception("DataMapper: Error in the '$context' configuration => item '$name' must be a valid directory name");
						}
						elseif ( ! is_writable($value) )
						{
							throw new DataMapper_Exception("DataMapper: Error in the '$context' configuration => item '$name' must be writeable");
						}
						$config[$name] = realpath($value) . DS;
					}
					break;

				case 'cache_expiration':
					empty($value) AND $config[$name] = $value = 0;
					if ( ! is_numeric($value) OR $value < 0 )
					{
						throw new DataMapper_Exception("DataMapper: Error in the '$context' configuration => item '$name' must be an integer value >= 0");
					}
					$config[$name] = (int) $value;
					break;

				case 'db_params':
					if ( ! is_null($value) AND $value !== FALSE AND ( ! is_string($value) OR $value == '' ) )
					{
						throw new DataMapper_Exception("DataMapper: Error in the '$context' configuration => item '$name' must be NULL, FALSE or a non-empty string");
					}
					break;

				// unknown configuration item, bail out
				default:
					throw new DataMapper_Exception("DataMapper: Invalid configuration item '$name' in the '$context' configuration");
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Returns the configuration array for a model instance
	 *
	 * If it doesn't exist, it will be setup before being returned
	 *
	 * @param	object	$object		DataMapper model instance (access by reference!)
	 *
	 * @return	array	the configuration array for the model, by reference!
	 */
	protected static function dm_configure_model(&$object)
	{
		// fetch and prep the model class name
		$model_class = singular(strtolower(get_class($object)));

		// determine the name of the model we're configuring
		if ( isset($object->model) AND is_string($object->model) AND ! empty($object->model) AND $object->model != $model_class )
		{
			$model = $object->model;
			$model_class .= '_'.$model;
		}
		else
		{
			$model = $model_class;
		}

		// this is to ensure that this is only done once per model
		if ( ! isset(DataMapper::$dm_model_config[$model_class]) )
		{
			// setup the model config
			DataMapper::$dm_model_config[$model_class] = array(
				'model'		=> $model,
				'table'		=> plural($model),
				'keys'		=> array('id' => 'integer'),
				'fields'	=> array(),
				'config'	=> DataMapper::$dm_global_config,
			);

			// assign the new model config to the object
			$object->dm_config =& DataMapper::$dm_model_config[$model_class];

			// load language file, if requested and it exists
			if ( ! empty($object->dm_config['config']['lang_file_format']) )
			{
				$lang_file = str_replace(
					array('${model}', '${table}'),
					array($object->dm_config['model'], $object->dm_config['table']),
					$object->dm_config['config']['lang_file_format']
				);

				DataMapper::dm_load_lang($lang_file);
			}

			$loaded_from_cache = FALSE;

			// load in the production cache for this model, if it exists and not expired
			if ( ! empty($object->dm_config['config']['cache_path']))
			{
				$cache_file = $object->dm_config['config']['cache_path'] . $model_class . EXT;
				if ( file_exists($cache_file) )
				{
					if ( ! empty($object->dm_config['config']['cache_expiration']) AND filemtime($cache_file) + $object->dm_config['config']['cache_expiration'] > time() )
					{
						DataMapper::$dm_model_config[$model_class] = unserialize(file_get_contents($cache_file));
						$loaded_from_cache = TRUE;
					}
				}
			}

			// not cached, construct the rest of the model configuration
			if ( ! $loaded_from_cache )
			{
				// *** start DEPRECATED, REMOVE IN v2.1
				$warn = TRUE;
				foreach ( get_object_vars($object) as $name => $value)
				{
					if ( isset($object->dm_config['config'][$name]) )
					{
						if ( $warn )
						{
							log_message('debug', "DataMapper: Using model object properties in '".$object->dm_config['model']."' for configuration is deprecated and will be removed in the next version!");
							$warn = FALSE;
						}
						$object->dm_config['config'][$name] = $value;
					}
				}
				// *** end DEPRECATED, REMOVE IN v2.1

				// merge the model config, if present
				if ( isset($object->config) AND is_array($object->config) )
				{
					foreach ( $object->config as $name => $value)
					{
						isset($object->dm_config['config'][$name]) AND $object->dm_config['config'][$name] = $value;
					}
				}

				// and validate it
				DataMapper::dm_validate_config($object->dm_config['config'], get_class($object));

				// add any extension paths to the autoloader
				DataMapper::add_extension_path($object->dm_config['config']['extensions_path']);
				unset($object->dm_config['config']['extensions_path']);

				// load and initialize model extensions
				DataMapper::dm_load_extensions($object->dm_config['config']['extensions'], $object->dm_config['config']['extension_overload']);
				unset($object->dm_config['config']['extensions']);

				// check if we have a custom table name ( needed if plural(class) fails )
				if ( isset($object->table) AND is_string($object->table) AND ! empty($object->table) )
				{
					$object->dm_config['table'] = $object->table;
				}

				// and add prefix to table
				$object->dm_config['table'] = $object->dm_config['config']['prefix'] . $object->dm_config['table'];

				// store the alias for this model / table combination
				DataMapper::$dm_table_aliases[$model] = 'DMTA_'.self::$alias_counter++;

				// check if we have a custom primary keys
				if ( isset($object->primary_key) AND is_array($object->primary_key) AND ! empty($object->primary_key) )
				{
					// replace the default 'id' key
					$object->dm_config['keys'] = $object->primary_key;
				}

				// check if we have a default order_by
				if ( isset($object->default_order_by) AND is_array($object->default_order_by) AND ! empty($object->default_order_by) )
				{
					$object->dm_config['order_by'] = $object->default_order_by;
				}
				else
				{
					$object->dm_config['order_by'] = array();
				}

				// validation information for this model
				$object->dm_config['validation'] = array(
					'save_rules' => array(),
					'get_rules' => array(),
					'matches' => array(),
				);

				// convert validation into associative array by field name
				$associative_validation = array();
				if ( isset($object->validation) AND is_array($object->validation) )
				{
					foreach ( $object->validation as $name => $validation )
					{
						// make sure we have a valid fieldname
						if ( is_string($name) )
						{
							$validation['field'] = $name;
						}
						else
						{
							$name = $validation['field'];
						}

						// clean up possibly missing or invalid validation fields
						if ( ! isset($validation['rules']) OR ! is_array($validation['rules']) )
						{
							$validation['rules'] = array();
						}

						// populate associative validation array
						$associative_validation[$name] = $validation;

						// clean up possibly missing or invalid validation fields
						if ( ! isset($validation['get_rules']) OR ! is_array($validation['get_rules']) )
						{
							$validation['get_rules'] = array();
						}

						if ( ! empty($validation['get_rules']) )
						{
							$object->dm_config['validation']['get_rules'][$name] = $validation['get_rules'];
						}

						// check if there is a "matches" validation rule
						if ( isset($validation['rules']['matches']) )
						{
							$object->dm_config['validation']['matches'][$name] = $validation['rules']['matches'];
						}
					}
				}

				// set up validations for the keys, if not present
				$get_rules =& $object->dm_config['validation']['get_rules'];
				foreach ( $object->dm_config['keys'] as $name => $value )
				{
					if ( ! isset($associative_validation[$name]) )
					{
						$associative_validation[$name] = array(
							'field' => $name,
							'rules' => array($value)
						);
						switch ($value)
						{
							case 'integer':
								$value = 'intval';
								break;

							case 'float':
								$value = 'floatval';
								break;

							default:
								continue;
						}

						if ( isset($get_rules[$name]) )
						{
							in_array($value, $get_rules[$name]) OR $get_rules[$name][] = $value;
						}
						else
						{
							$get_rules[$name] = array($value);
						}
						break;
					}
				}

				$object->dm_config['validation']['save_rules'] = $associative_validation;

				// construct the relationship definitions
				foreach ( array('has_one', 'has_many', 'belongs_to') as $rel_type )
				{
					if ( ! empty($object->{$rel_type}) AND is_array($object->{$rel_type}) )
					{
						foreach ($object->{$rel_type} as $relation => $relations )
						{
							// validate the defined relation and add optional values if needed
							if ( is_string($relation) OR is_array($relations) )
							{
								if ( empty($relations['my_key']) )
								{
									$relations['my_key'] = array();
									foreach ( $object->dm_config['keys'] as $key => $unused )
									{
										$relations['my_key'][] = $key;
									}
								}
								empty($relations['my_class']) AND $relations['my_class'] = $object->dm_config['model'];
								empty($relations['my_table']) AND $relations['my_table'] = $object->dm_config['table'];
								empty($relations['related_class']) AND $relations['related_class'] = $relation;
								empty($relations['related_model']) AND $relations['related_model'] = $object->dm_config['model'];
								if ( empty($relations['join_table']) )
								{
									$relations['join_table'] = ( $relation < $relations['my_class'] ) ? plural($relation).'_'.plural($relations['my_class']) : plural($relations['my_class']).'_'.plural($relation);
								}
								if ( $rel_type == 'belongs_to' )
								{
									$relations['related_key'] = array();
								}
								elseif ( empty($relations['related_key']) OR ! is_array($relations['related_key']) )
								{
									throw new DataMapper_Exception("DataMapper: missing 'related_key' in $rel_type relation '$relation' in model '".$object->dm_config['model']."'");
								}

								// and store it
								$object->dm_config['relations'][$rel_type][$relation] = $relations;
							}
							else
							{
								throw new DataMapper_Exception("DataMapper: invalid '$rel_type' relation detected in model '".$object->dm_config['model']."'");
							}
						}
					}
					else
					{
						$object->dm_config['relations'][$rel_type] = array();
					}
				}

				// get and store the table's field names and meta data
				$fields = $object->db->field_data($object->dm_config['table']);

				// store only the field names and ensure validation list includes all fields
				foreach ($fields as $field)
				{
					// populate fields array
					$object->dm_config['fields'][] = $field->name;

					// add validation if current field has none
					if ( ! isset($object->dm_config['validation']['rules'][$field->name]) )
					{
						// label is set below, to prevent caching language-based labels
						$object->dm_config['validation']['save_rules'][$field->name] = array('field' => $field->name, 'rules' => array());
					}
				}

				// check if all defined keys are valid fields
				foreach ( $object->dm_config['keys'] as $name => $type )
				{
					if ( ! in_array($name, $object->dm_config['fields']) )
					{
						throw new DataMapper_Exception("DataMapper: Key field '$name' is not a valid column for table '".$object->dm_config['table']."' in model '".$object->dm_config['model']."'");
					}
				}

				// write to cache if needed
				if ( ! empty($object->dm_config['config']['cache_path']) AND ! empty($object->dm_config['config']['cache_expiration']) )
				{
					$cache_file = $object->dm_config['config']['cache_path'] . $model_class . EXT;
					file_put_contents($cache_file, serialize(DataMapper::$dm_model_config[$model_class]), LOCK_EX);
				}
			}

			// record where we got this config from
			$object->dm_config['from_cache'] =  $loaded_from_cache;
		}
		else
		{
			// assign the existing model config to the object
			$object->dm_config =& DataMapper::$dm_model_config[$model_class];
		}

		// finally, localize the labels here (because they shouldn't be cached
		// this also sets any missing labels.
		foreach($object->dm_config['validation']['save_rules'] as $field => &$val)
		{
			$val['label'] = $object->dm_lang_line($field, isset($val['label']) ? $val['label'] : FALSE, $object->dm_config);
		}

		// if the model contains a post_model_init, call it now
		if ( method_exists($object, 'post_model_init') )
		{
			$object->post_model_init($object->dm_config['from_cache']);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Loads the extensions defined in the array passed
	 *
	 * @param	array	$extensions		array of extensions to load
	 *
	 * @return	void
	 */
	public static function dm_is_extension_method($method)
	{
		return isset(DataMapper::$dm_extension_methods[$method]) ? $method : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * Loads the extensions defined in the array passed
	 *
	 * @param	array	$extensions		array of extensions to load
	 *
	 * @return	void
	 */
	protected static function dm_load_extensions($extensions, $overload)
	{
		if ( ! empty($extensions) )
		{
			foreach ( $extensions as $extension )
			{
				// and extension can by an array of method->class pairs
				if ( is_array($extension) )
				{
					foreach ( $extension as $method => $class )
					{
						DataMapper::$dm_extension_methods[$method] = $class;
					}
				}

				// or a string indicating the class base name
				else
				{
					// determine the extension class name
					$class = 'DataMapper_'.ucfirst($extension);

					// trigger the autoloader
					if ( class_exists($class, TRUE) )
					{
						// register the public methods of this extension class
						foreach ( get_class_methods($class) as $method )
						{
							if ( isset(DataMapper::$dm_extension_methods[$method]) )
							{
								if ( DataMapper::$dm_extension_methods[$method] != $class AND ! $overload)
								{
									throw new DataMapper_Exception("DataMapper: duplicate method '$method' detected in extension '$extension' (also defined in '".DataMapper::$dm_extension_methods[$method]."')");
								}
							}
							else
							{
								DataMapper::$dm_extension_methods[$method] = $class;
							}
						}
					}
					else
					{
						throw new DataMapper_Exception("DataMapper: defined extension '$extension' can not be found");
					}
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * DataMapper version of $this->load->lang
	 *
	 * @param	string	$lang		name of the language file to laod
	 * @return	void
	 */
	protected static function dm_load_lang($lang)
	{
		// determine the idiom
		$default_lang = DataMapper::$CI->config->item('language');
		$idiom = ($default_lang == '' OR $default_lang == 'english') ? 'en' : $default_lang;

		// check if this language file exists, we can't catch CI's lang load errors
		$file = realpath(DataMapper::$dm_path.DS.'language'.DS.$idiom.DS.$lang.'_lang'.EXT);
		if ( $file AND is_file($file) )
		{
			DataMapper::$CI->lang->load('datamapper', $idiom);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * checks if the requested keys are all part of the fields array
	 *
	 * @param	array	$keys	array of column names
	 * @param	array	$fields	array of table field names
	 *
	 * @return	bool	TRUE if a match is found, FALSE otherwise
	 */
	protected static function dm_has_keys($keys, $fields)
	{
		$found = TRUE;

		foreach ( $keys as $key => $value )
		{
			// deal with non-associative arrays
			if ( is_numeric($key) )
			{
				$key = $value;
			}

			if ( ! in_array($key, $fields) )
			{
				$found = FALSE;
				break;
			}
		}

		return $found;
	}


	// -------------------------------------------------------------------------
	// Dynamic class definition
	// -------------------------------------------------------------------------

	/**
	 * runtime configuration for the current model instance
	 *
	 * note that this will become a reference to the global model config array!
	 *
	 * @var array
	 */
	protected $dm_config = NULL;

	/**
	 * runtime flags for the current model instance
	 *
	 * @var array
	 */
	protected $dm_flags = array(
		'validated' => FALSE,
		'valid' => FALSE,
		'where_group_started' => FALSE,
		'force_validation' => FALSE,
		'auto_transaction' => FALSE,
		'where_group_started' => FALSE,
		'group_count' => 0,
		'include_join_fields' => FALSE,
	);

	/**
	 * runtime values for the current model instance
	 *
	 * @var array
	 */
	protected $dm_values = array(
		'parent' => NULL,
		'instantiations' => array(),
	);

	/**
	 * used to keep track of the original values from the database, to
	 * prevent unecessarily changing fields.
	 *
	 * @var object
	 */
	protected $dm_original = NULL;

	/**
	 * current object field values
	 *
	 * @var object
	 */
	protected $dm_current = NULL;

	/**
	 * used to keep track of the original values from the database, to
	 * prevent unecessarily changing fields.
	 *
	 * @var object
	 */
	protected $dm_dataset_iterator = NULL;

	/**
	 * contains the result of the last query.
	 *
	 * @var array
	 */
	public $all = array();

	/**
	 * contains any errors that occur during validation, saving, or other
	 * database access.
	 *
	 * @var object	DataMapper_Errors
	 */
	public $error = NULL;

	// -------------------------------------------------------------------------

	public function __construct($param = NULL, $modelname = NULL)
	{
		// when first called, initialize DataMapper itself
		if ( ! DataMapper::$dm_initialized )
		{
			// make sure CI is up to spec
			if ( version_compare(CI_VERSION, '2.0.0') < 0 )
			{
				throw new DataMapper_Exception("DataMapper: this version only works on CodeIgniter v2.0.0 and above");
			}

			// get the CodeIgniter "superobject"
			DataMapper::$CI = get_instance();

			// check if we're bootstrapped properly
			if ( get_class(DataMapper::$CI->load) != 'DM_Loader' )
			{
				throw new DataMapper_Exception("DataMapper: bootstrap is not loaded in your index.php file");
			}

			// store the path to the DataMapper installation
			DataMapper::$dm_path = __DIR__;

			// store the path to the DataMapper extension files
			DataMapper::$dm_extension_paths = array(realpath(__DIR__.DS.'..'.DS.'core'), realpath(__DIR__.DS.'..'.DS.'extensions'));

			// load the global config
			DataMapper::$CI->config->load('datamapper', TRUE, TRUE);

			// merge it with the default config
			foreach ( DataMapper::$CI->config->item('datamapper') as $name => $item)
			{
				isset(DataMapper::$dm_global_config[$name]) and DataMapper::$dm_global_config[$name] = $item;
			}

			// and validate it
			DataMapper::dm_validate_config(DataMapper::$dm_global_config, 'global');

			// load and initialize global extensions
			DataMapper::dm_load_extensions(DataMapper::$dm_global_config['extensions'], FALSE);

			// set the initialize flag, we don't want to do this twice
			DataMapper::$dm_initialized = TRUE;

			// load the DataMapper language file
			DataMapper::dm_load_lang('datamapper');

			// load inflector helper for singular and plural functions
			DataMapper::$CI->load->helper('inflector');

			// load security helper for prepping functions
			DataMapper::$CI->load->helper('security');
		}

		// else it's a model object instantiation
		else
		{
			// do we have a custom modelname passed
			is_null($modelname) OR $this->model = $modelname;

			// setup a local copy of the model config
			DataMapper::dm_configure_model($this);

			// remove no longer needed properties
			if ( isset($this->model) ) unset($this->model);
			if ( isset($this->table) ) unset($this->table);
			if ( isset($this->has_one) ) unset($this->has_one);
			if ( isset($this->has_many) ) unset($this->has_many);
			if ( isset($this->belongs_to) ) unset($this->belongs_to);
			if ( isset($this->validation) ) unset($this->validation);
			if ( isset($this->default_order_by) ) unset($this->default_order_by);

			// initialize some object properties
			$this->dm_original = $this->dm_current = new DataMapper_Datastorage();

			// set a new error object
			$this->error = new DataMapper_Errors($this);

			// was a parameter passed?
			if ( ! is_null($param) )
			{
				// could be a parent object
				if ( $param instanceOf DataMapper )
				{
					$this->dm_values['parent'] = array(
						'relation' => $param->dm_find_relationship($this),
						'object' => $param,
					);
				}

				// else assume it's a primary key value
				else
				{
					// backwards compatibility with DataMapper v1.x
					if ( ! is_array($param) )
					{
						if ( count($this->dm_config['keys']) !== 1 )
						{
							throw new DataMapper_Exception("DataMapper: you can not pass a single key value when constructing the '".$this->dm_config['model']."' model. It requires multiple keys");
						}

						reset($this->dm_config['keys']);
						$param = array(key($this->dm_config['keys']) => $param );
					}

					// load the requested record
					$this->get_where($param);
				}
			}
		}
	}

	// -------------------------------------------------------------------------
	// PHP Magic methods
	// -------------------------------------------------------------------------

	/**
	 * returns the value of the named property
	 * if named property is a related item, instantiate it first
	 *
	 * this method also instantiates the DB object if necessary
	 *
	 * @ignore
	 *
	 * @param	string	$name	name of property to look for
	 *
	 * @return	mixed
	 */
	public function __get($name)
	{
		// we dynamically get DB when needed, and create a copy.
		// this allows multiple queries to be generated at the same time.
		if ( $name == 'db' )
		{
			// mode 1: re-use CodeIgniters existing database connection, which must be loaded before loading DataMapper
			if ( $this->dm_config['config']['db_params'] === FALSE )
			{
				// autoload the database if needed
				if ( ! class_exists('DM_DB_Driver', FALSE) )
				{
					DataMapper::$CI->load->database();

					if ( ! class_exists('DM_DB_Driver', FALSE) )
					{
						throw new DataMapper_Exception("DataMapper: CodeIgniter database library not loaded, or DataMappers index.php bootstrap not installed!");
					}

				}

				$this->db =& DataMapper::$CI->db;
			}
			else
			{
				// mode 2: clone CodeIgniters existing database connection, which must be loaded before loading DataMapper
				if ( $this->dm_config['config']['db_params'] === NULL OR $this->dm_config['config']['db_params'] === TRUE )
				{
					// autoload the database if needed
					if ( ! class_exists('DM_DB_Driver', FALSE) )
					{
						DataMapper::$CI->load->database();

						if ( ! class_exists('DM_DB_Driver', FALSE) )
						{
							throw new DataMapper_Exception("DataMapper: CodeIgniter database library not loaded, or DataMappers index.php bootstrap not installed!");
						}

					}

					// ensure the shared DB is disconnected, even if the app exits uncleanly
					if ( ! isset(DataMapper::$CI->db->_has_shutdown_hook) )
					{
						register_shutdown_function(array(DataMapper::$CI->db, 'close'));
						DataMapper::$CI->db->_has_shutdown_hook = TRUE;
					}

					// clone, so we don't create additional connections to the DB
					// NOTE: have to do it like this, for some reason assigning the clone to $this->db fails?
					$db = clone DataMapper::$CI->db;
					$this->db =& $db;
					$this->db->dm_call_method('_reset_select');
				}

				// mode 3: make a new database connection, based on the configured database name
				else
				{
					// connecting to a different database, so we *must* create additional copies.
					// It is up to the developer to close the connection!
					$this->db = DataMapper::$CI->load->database($this->dm_config['config']['db_params'], TRUE, TRUE);
				}

				// these items are shared (for debugging)
				if ( is_object(DataMapper::$CI->db) AND isset(DataMapper::$CI->db->dbdriver) )
				{
					$this->db->queries =& DataMapper::$CI->db->queries;
					$this->db->query_times =& DataMapper::$CI->db->query_times;
				}
			}

			// ensure the created DB is disconnected, even if the app exits uncleanly
			if ( ! isset($this->db->_has_shutdown_hook) )
			{
				register_shutdown_function(array($this->db, 'close'));
				$this->db->_has_shutdown_hook = TRUE;
			}

			return $this->db;
		}

		// check for requested field names
		if ( isset($this->dm_current->{$name}) )
		{
			return $this->dm_current->{$name};
		}

		// special case to load names that represent related objects
		if ( $related_object = $this->dm_find_relationship($name) )
		{
			// instantiate the related object
			$class = $related_object['related_class'];
			$this->dm_current->{$name} = new $class($this, $name);

			return $this->dm_current->{$name};
		}

		// possibly return single form of related object name
		$name_single = singular(strtolower($name));
		if ( $name_single !== $name AND isset($this->dm_current->{$name_single}) )
		{
			if ( is_object($this->dm_current->{$name_single}) )
			{
				return $this->dm_current->{$name_single};
			}
		}

		// nothing found to get
		return NULL;
	}

	// -------------------------------------------------------------------------

	/**
	 * sets the value of the named property
	 *
	 * @ignore
	 *
	 * @param	string	$name	name of property to set
	 * @param	mixed	$value	value of the property
	 *
	 * @return	mixed
	 */
	public function __set($name, $value)
	{
		$this->dm_current->{$name} = $value;
	}

	// -------------------------------------------------------------------------

	/**
	 * unsets a named property
	 *
	 * @ignore
	 *
	 * @param	string	$name	name of property to unset
	 *
	 * @return	mixed
	 */
	public function __unset($name)
	{
		$this->dm_current->__unset($name);
	}

	// -------------------------------------------------------------------------

	/**
	 * checks if the named property exists
	 *
	 * @ignore
	 *
	 * @param	string	$name	name of property to set
	 *
	 * @return	bool
	 */
	public function __isset($name)
	{
		return $this->dm_current instanceOf DataMapper_Datastorage ? $this->dm_current->__isset($name) : FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * calls special methods, or extension methods.
	 *
	 * @ignore
	 *
	 * @param	string	$method	method name called
	 * @param	array	$arguments	arguments to be passed to the method
	 * @return	mixed
	 */
	public function __call($method, $arguments)
	{
		// List of watched dynamic method names
		// NOTE: order matters: make sure more specific items are listed before
		// less specific items
		static $watched_methods = array(
			'save_', 'delete_',
			'get_by_related_', 'get_by_related', 'get_by_',
			'_related_subquery', '_subquery',
			'_related_', '_related',
			'_join_field',
			'_field_func', '_func'
		);

		// check if a watched method is called
		$new_method = FALSE;
		foreach ( $watched_methods as $watched_method )
		{
			// see if called method is a watched method
			if ( strpos($method, $watched_method) !== FALSE )
			{
				// split the method name to see what we need to call
				$pieces = explode($watched_method, $method);
				if ( ! empty($pieces[0]) AND ! empty($pieces[1]) )
				{
					// watched method is in the middle
					$new_method = 'dm_' . trim($watched_method, '_');
					$arguments = array_merge(array($pieces[0]), array(array_merge(array($pieces[1]), $arguments)));
					break;
				}
				else
				{
					// watched method is a prefix or suffix
					$new_method = 'dm_' . trim($watched_method, '_');
					$arguments = array_merge(array(str_replace($watched_method, '', $method)), array($arguments));
					break;
				}
			}
		}

		// update the method name if needed
		$new_method and $method = $new_method;

		// are we calling an extension method?
		if ( isset(DataMapper::$dm_extension_methods[$method]) )
		{
			// add our object to the top of the argument stack
			array_unshift($arguments, $this);

			// and call the extension method
			return call_user_func_array(DataMapper::$dm_extension_methods[$method].'::'.$method, $arguments);
		}

		// are we calling an internal method?
		if ( method_exists($this, $method) )
		{
			return call_user_func_array(array($this, $method), $arguments);
		}

//		echo '<hr /';
//		var_dump($method, $arguments);
//		echo '<hr /';

		// no mapping found, fail with the standard PHP error
		throw new DataMapper_Exception("Call to undefined method ".get_class($this)."::$method()");
	}

	// --------------------------------------------------------------------

	/**
	 * allows for a less shallow clone than the default PHP clone.
	 *
	 * @ignore
	 */
	public function __clone()
	{
		foreach ($this as $key => $value)
		{
			if (is_object($value) AND $key != 'db')
			{
				$this->{$key} = clone($value);
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * to string
	 *
	 * converts the current object into a string
	 * should be overridden in your model to be meaningful
	 *
	 * @return	string
	 */
	public function __toString()
	{
		return ucfirst($this->dm_config['model']);
	}


	// -------------------------------------------------------------------------
	// DataMapper public core methods
	// -------------------------------------------------------------------------

	// --------------------------------------------------------------------

	/**
	 * reloads in the configuration data for a model. this is mainly
	 * used to handle language changes. all instances will see the changes
	 */
	public function reinitialize_model()
	{
		DataMapper::dm_configure_model($this);
	}

	// --------------------------------------------------------------------

	/**
	 * convenience method to return the number of items from the last call to get
	 *
	 * @return	int
	 */
	public function result_count()
	{
		if ( isset($this->dm_dataset_iterator) )
		{
			return $this->dm_dataset_iterator->result_count();
		}
		else
		{
			return count($this->all);
		}
	}

	/**
	 * get objects from the database.
	 *
	 * @param	integer|NULL	$limit	limit the number of results
	 * @param	integer|NULL	$offset	offset the results when limiting
	 *
	 * @return	DataMapper returns self for method chaining
	 */
	public function get($limit = NULL, $offset = NULL)
	{
		// Check if this is a related object and if so, perform a related get
		if ( ! $this->dm_handle_related() )
		{
			// invalid get request, return this for chaining.
			return $this;
		}

		// check if we have selected something from the current table
		$found = FALSE;
		$alias = $this->db->_protect_identifiers(self::$dm_table_aliases[$this->dm_config['model']]);
		foreach ( $this->db->ar_select as $select )
		{
			// filter out subqueries
			if ( strpos($select, '(SELECT') !== 0 )
			{
				if ( $found = (strpos($select, $alias) !== FALSE) )
				{
					break;
				}
			}
		}

		// and add a select * if no reference was found
		$found OR $this->select();

		// storage for the query result
		$query = FALSE;

		// check if object has been validated (skipped for related items)
		if ( $this->dm_flags['validated'] AND empty($this->dm_values['parent']) )
		{
			// reset validated flag
			$this->dm_flags['validated'] = FALSE;

			// use this objects properties
			$data = $this->dm_to_array(TRUE);

			if ( ! empty($data) )
			{
				// clear this objects selection
				$this->db->dm_call_method('_reset_select');

				// clear this object to make way for new data
				$this->clear();

				// set up default order by (if available)
				$this->dm_default_order_by();

				// get by objects properties
				$query = $this->db->get_where($this->dm_config['table'].' '.$this->dm_table_alias($this->dm_config['model']), $data, $limit, $offset);
			}
			else
			{
$TODO = 'Make a decision on dealing with this or not... Version 1.x didnt';
//				throw new DataMapper_Exception('DataMapper: called get() on an empty validated object');
			}
		}
		else
		{
			// clear this object to make way for new data
			$this->clear();

			// set up default order by (if available)
			$this->dm_default_order_by();

			// get by built up query
			$query = $this->db->get($this->dm_config['table'].' '.$this->dm_table_alias($this->dm_config['model'], TRUE), $limit, $offset);
		}

		// convert the query result into DataMapper objects
		$query AND $this->dm_process_query($query);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * returns the SQL string of the current query (SELECTs ONLY)
	 *
	 * note that this also _clears_ the current query info!
	 *
	 * This can be used to generate subqueries.
	 *
	 * @param	int		$limit			limit the number of results
	 * @param	int		$offset			offset the results when limiting
	 * @param	bool	$handle_related	Internal use only. if TRUE, add related tables
	 * @param	bool	$subquery		Internal use only. if TRUE we're building a subquery
	 *
	 * @return	string	SQL as a string
	 */
	public function get_sql($limit = NULL, $offset = NULL, $handle_related = FALSE, $subquery = FALSE)
	{
		if ( $handle_related )
		{
			// Check if this is a related object and if so, perform a related get
			if ( ! $this->dm_handle_related() )
			{
				// invalid request
				return FALSE;
			}
		}

		// add a select(*) if needed
		if ( ! $subquery )
		{
			// check if we have selected something from the current table
			$found = FALSE;
			$alias = $this->db->_protect_identifiers(self::$dm_table_aliases[$this->dm_config['model']]);
			foreach ( $this->db->ar_select as $select )
			{
				// filter out subqueries
				if ( strpos($select, '(SELECT') !== 0 )
				{
					if ( $found = (strpos($select, $alias) !== FALSE) )
					{
						break;
					}
				}
			}

			// and add a select * if no reference was found
			$found OR $this->select();
		}

		$this->db->dm_call_method('_track_aliases', $this->dm_config['table']);
		$this->db->from($this->dm_config['table'].' '.$this->dm_table_alias($this->dm_config['model'], TRUE));

		$this->dm_default_order_by();

		if ( ! is_null($limit) )
		{
			$this->limit($limit, $offset);
		}

		$sql = $this->db->dm_call_method('_compile_select');

		$this->dm_clear_after_query();

		return $sql;
	}

	// --------------------------------------------------------------------

	/**
	 * runs the query, but returns the raw CodeIgniter results
	 *
	 * note that this also _clears_ the current query info!
	 *
	 * @param	integer|NULL	$limit	limit the number of results
	 * @param	integer|NULL	$offset	offset the results when limiting
	 *
	 * @return	CI_DB_result	result object
	 */
	public function get_raw($limit = NULL, $offset = NULL, $handle_related = TRUE)
	{
		// Check if this is a related object and if so, perform a related get
		if ( $handle_related AND ! $this->dm_handle_related() )
		{
			// invalid get request, return this for chaining.
			return $this;
		}

		$this->dm_default_order_by();

		$query = $this->db->get($this->dm_config['table'].' '.$this->dm_table_alias($this->dm_config['model'], TRUE), $limit, $offset);
		$this->dm_clear_after_query();

		return $query;
	}

	// --------------------------------------------------------------------

	/**
	 * get items matching the where clause
	 *
	 * @param	mixed			$where	see where()
	 * @param	integer|NULL	$limit	limit the number of results
	 * @param	integer|NULL	$offset	offset the results when limiting
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function get_where($where = array(), $limit = NULL, $offset = NULL)
	{
		$this->where($where);

		return $this->get($limit, $offset);
	}

	// --------------------------------------------------------------------

	/**
	 * runs the specified query and populates the current object with the results
	 *
	 * warning: Use at your own risk.  This will only be as reliable as your query
	 *
	 * @param	string		$sql	the query to process
	 * @param	array|bool	$binds	array of values to bind (see CodeIgniter)
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function query($sql, $binds = FALSE)
	{
		// run the custom query
		$query = $this->db->query($sql, $binds);

		// and convert it into DataMapper objects
		$this->dm_process_query($query);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Count
	 *
	 * Returns the total count of the object records from the database.
	 * If on a related object, returns the total count of related objects records.
	 *
	 * @param	array	$exclude_keys	a list of keys to exclude from the count
	 * @param	array	$column			Internal use only. If provided, use this column for the DISTINCT instead of the key column(s)
	 *
	 * @return	int Number of rows in query.
	 */
	public function count($exclude_keys = array(), $columns = array())
	{
		// is this a related count?
		if ( ! empty($this->dm_values['parent']) )
		{
			$this->dm_handle_related();
		}

		// add the table
		$this->db->from($this->dm_config['table'].' '.$this->dm_table_alias($this->dm_config['model'], TRUE));

		// any keys to exclude
		if ( ! empty($exclude_keys) )
		{
			$req_count = count($this->dm_config['keys']);
			$count = 0;
			foreach ( $this->dm_config['keys'] as $key => $unused )
			{
				// storage for the key values of this field
				$in = array();

				// add the keys to be excluded
				foreach ( $exclude_keys as $key_value )
				{
					is_array($key_value) AND count($key_value) == $req_count AND $in[] = $key_value[$count];
				}

				// add the exclusion to the query
				$this->db->where_not_in($this->add_table_name($key), $in);

				// get the next field (if any)
				$count++;
			}
		}

		// prefix any columns passed
		foreach ( $columns as $index => $column )
		{
			$columns[$index] = $this->add_table_name($column);
		}

		// this will only work for single key tables
		if ( count($columns) == 1 )
		{
			// do a COUNT DISTINCT
			$select = 'SELECT COUNT(DISTINCT ' . $this->db->_protect_identifiers(reset($columns)) . ') AS ';
		}
		else
		{
			$select = $this->db->_count_string;
		}

		// call the CI driver to compile the query
		$sql = $this->db->dm_call_method('_compile_select', $select . $this->db->_protect_identifiers('numrows'));

		// run the count query
		$query = $this->db->query($sql);

		// and reset the db driver
		$this->db->dm_call_method('_reset_select');

		// return the count result
		if ($query->num_rows() == 0)
		{
			return 0;
		}
		else
		{
			$row = $query->row();
			return intval($row->numrows);
		}
	}

	// --------------------------------------------------------------------

	/**
	 * returns the total count of distinct object records from the database
	 * if on a related object, returns the total count of related objects records.
	 *
	 * @param	array	$exclude_keys	a list of keys to exclude from the count
	 * @param	array	$column			if provided, use this column for the DISTINCT instead of the key column(s)
	 *
	 * @return	int	number of rows in query
	 */
	public function count_distinct($exclude_ids = NULL, $columns = NULL)
	{
		// get the default for the distinct keys if needed
		if ( is_null($columns) )
		{
			$columns = array();
			foreach ( $this->dm_config['keys'] as $key => $unused )
			{
				$columns[] = $key;
			}
		}

		// return the count
		return $this->count($exclude_ids, $columns);
	}

	// --------------------------------------------------------------------

	/**
	 * clears the current object
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function clear()
	{
		// clear the all list
		$this->all = array();

		// clear errors

		$this->error->clear();

		// clear this objects properties
		foreach ($this->dm_config['fields'] as $field)
		{
			$this->dm_current->{$field} = NULL;
		}

		// clear this objects related objects
		foreach ( $this->dm_config['relations'] as $relation_type )
		{
			foreach ( $relation_type as $related => $properties )
			{
				if ( isset($this->dm_current->{$related}) )
				{
					unset($this->dm_current->{$related});
				}
			}
		}

		// clear and refresh stored values
		$this->dm_original = new DataMapper_Datastorage();

		// clear the saved iterator
		$this->dm_dataset_iterator = NULL;

		$this->dm_refresh_original_values();

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * removes any empty objects in this objects all list.
	 * only needs to be used if you are looping through the all list
	 * a second time and you have deleted a record the first time through.
	 *
	 * @return	bool	FALSE	if the $all array was already empty
	 */
	public function refresh_all()
	{
		if ( ! empty($this->all) )
		{
			foreach ($this->all as $key => $item)
			{
				if ( ! $item->exists() )
				{
					unset($this->all[$key]);
				}
			}
			return TRUE;
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * returns TRUE if the current object has a database record
	 *
	 * @return	bool
	 */
	public function exists()
	{
		// returns TRUE if the keys of this object is set and not empty, OR
		// there are items in the ALL array.
		$exists = TRUE;

		foreach ( $this->dm_config['keys'] as $key => $type)
		{
			if ( empty($this->dm_current->{$key}) )
			{
				$exists = FALSE;
				break;
			}
		}

		// not all keys are set, check if we have results in the all array
		! $exists AND $exists = ($this->result_count() > 0);

		return $exists;
	}

	// --------------------------------------------------------------------

	/**
	 * if this object is related to the provided object, returns TRUE,
	 * otherwise returns FALSE
	 *
	 * optionally can be provided a related field and a key value
	 *
	 * @param	mixed	$related_field	the related object or field name
	 * @param	array	$keys			key value to compare to if $related_field is a string
	 *
	 * @return	bool	TRUE or FALSE if this object is related to $related_field
	 */
	public function is_related_to($related_field, $keys = array())
	{
		// is a DataMapper object passed?
		if ( $related_field instanceOf DataMapper )
		{
			// add a where clause for the key values if needed
			if ( ! empty($keys) )
			{
				foreach ( $related_field->dm_get_config('keys') as $key => $unused )
				{
					$this->db->where($this->add_table_name($key), $related_field->{$key});
				}
			}

			// and get the related model name
			$related_field = $related_field->dm_get_config('model');
		}

		// no, it's a relationship name
		else
		{
			// find the relation definition
			if ( ! $relation = $this->dm_find_relationship($related_field) )
			{
				throw new DataMapper_Exception("DataMapper: calling is_related_to() on an unrelated relation name");
			}

			// add a where clause for the key values if needed
			if ( ! empty($keys) )
			{
				reset($keys);
				foreach ( $relation['my_key'] as $key )
				{
					$this->db->where($this->add_table_name($key), current($keys));
					next($keys);
				}
			}
		}

		// run the count query, and return the result
		return ($this->{$related_field}->count() > 0);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the SELECT portion of the query
	 *
	 * @param	mixed	$select 	field(s) to select, array or comma separated string
	 * @param	bool	$escape		if FALSE, don't escape this field (Probably won't work)
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function select($select = '*', $escape = NULL)
	{
		if ( $escape !== FALSE )
		{
			if ( ! is_array($select) )
			{
				$select = $this->add_table_name($select);
			}
			else
			{
				$updated = array();
				foreach ( $select as $sel )
				{
					$updated[] = $this->add_table_name($sel);
				}
				$select = $updated;
			}
		}
		$this->db->select($select, $escape);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the SELECT MAX(field) portion of a query
	 *
	 * @param	string	$select	field to look at
	 * @param	string	$alias	alias of the MAX value
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function select_max($select = '', $alias = '')
	{
		// check if this is a related object
		if ( ! empty($this->dm_values['parent']) )
		{
			$alias = ($alias != '') ? $alias : $select;
		}
		$this->db->select_max($this->add_table_name($select), $alias);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the SELECT MIN(field) portion of a query
	 *
	 * @param	string	$select	field to look at
	 * @param	string	$alias	alias of the MIN value
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function select_min($select = '', $alias = '')
	{
		// check if this is a related object
		if ( ! empty($this->dm_values['parent']) )
		{
			$alias = ($alias != '') ? $alias : $select;
		}
		$this->db->select_min($this->add_table_name($select), $alias);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the SELECT AVG(field) portion of a query
	 *
	 * @param	string	$select	field to look at
	 * @param	string	$alias	alias of the AVG value
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function select_avg($select = '', $alias = '')
	{
		// Check if this is a related object
		if ( ! empty($this->parent))
		{
			$alias = ($alias != '') ? $alias : $select;
		}
		$this->db->select_avg($this->add_table_name($select), $alias);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the SELECT SUM(field) portion of a query
	 *
	 * @param	string	$select	field to look at
	 * @param	string	$alias	alias of the SUM value
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function select_sum($select = '', $alias = '')
	{
		// Check if this is a related object
		if ( ! empty($this->parent))
		{
			$alias = ($alias != '') ? $alias : $select;
		}
		$this->db->select_sum($this->add_table_name($select), $alias);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the flag to add DISTINCT to the query
	 *
	 * @param	bool	$value	set to FALSE to turn back off DISTINCT
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function distinct($value = TRUE)
	{
		$this->db->distinct($value);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Includes the number of related items using a subquery.
	 *
	 * Default alias is {$related_field}_count
	 *
	 * @param	mixed	$related_field	related records to count
	 * @param	string	$alias			alternative alias
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function include_related_count($related_field, $alias = NULL)
	{
		// make sure we have a field alias
		if(is_null($alias))
		{
			$alias = str_replace('/', '_', $related_field) . '_count';
		}

		// if a deep relationship string is passed, split it
		strpos($related_field, '/') !== FALSE AND $related_field = explode('/', $related_field);

		// make sure related field(s) is an array
		is_array($related_field) OR $related_field = array($related_field);

		// reverse the array, we need the last relation first
		$related_field = array_reverse($related_field);

		// storage for the subquery object
		$object = NULL;

		// process the passed relationships
		foreach ( $related_field as $related )
		{
			// do we have an object to start with?
			if ( is_null($object) )
			{
				if ( $related instanceOf DataMapper )
				{
					$object = $related->get_clone();
				}
				else
				{
					$object = new $related();
				}
			}
			else
			{
				is_string($related) AND $related = new $related();

				if ( $related instanceOf DataMapper )
				{
					// find out what the relation is
					$relation = $object->dm_find_relationship($related->dm_get_config('model'));

					// get the relationship definition seen from the related model
					$other_relation = $related->dm_find_relationship($object->dm_config['model']);

					if ( ! $relation OR ! $other_relation )
					{
						throw new DataMapper_Exception("DataMapper: Unable to relate '{$related->dm_config['model']}'");
					}

					// add the join to the query
					$object->dm_add_relation($relation, $other_relation);
				}
			}
		}

		// add the relation to the current model
		$relation = $related->dm_find_relationship($this->dm_get_config('model'));

		// get the relationship definition seen from the related model
		$other_relation = $this->dm_find_relationship($related->dm_config['model']);

		if ( ! $relation OR ! $other_relation )
		{
			throw new DataMapper_Exception("DataMapper: Unable to relate '{$object->dm_config['model']}'");
		}

		// add the join to the query
		$object->dm_add_relation($relation, $other_relation);

		// reset any select present, and add our count clause
		$object->db->ar_select = array();
//		$object->select('COUNT(*) AS count');
		$object->select_func('COUNT', '*', 'count');

		// add the where that determines the subquery selection
		foreach ( $this->dm_get_config('keys') as $key => $unused )
		{
			$this->db->where($this->add_table_name($key), $this->db->_protect_identifiers('${parent}.'.$key), FALSE);
		}

		// add the subquery to the select
		$this->select_subquery($object, $alias);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * if TRUE, the any extra fields on the join table will be included
	 *
	 * @param	bool	$include	if FALSE, turns back off the directive
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function include_join_fields($include = TRUE)
	{
		$this->dm_flags['include_join_fields'] = $include;

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * joins specified values of a has_one object into the current query
	 *
	 * if $fields is NULL or '*', then all columns are joined (may require instantiation of the other object)
	 * if $fields is a single string, then just that column is joined
	 * otherwise, $fields should be an array of column names
	 *
	 * $append_name can be used to override the default name to append, or set it to FALSE to prevent appending.
	 *
	 * @param	mixed	$arguments		the related object, relationship name, array of objects or a deep relationship string
	 * @param	mixed	$fields			the fields to join (NULL or '*' means all fields, or use a single field or array of fields)
	 * @param	bool	$append_name	the name to use for joining (with '_'), or FALSE to disable
	 * @param	bool	$instantiate	if TRUE, the results are instantiated into objects
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function include_related($arguments, $fields = NULL, $append_name = TRUE, $instantiate = FALSE)
	{
		// start relating from the current object
		$current = $this;

		// field prefix
		$append = '';

		// determine the related object: related by deep relationship objects
		if ( is_array($arguments) )
		{
			while ( count($arguments) > 1 )
			{
				$object = array_shift($arguments);

				// find out what the relation is
				if ( ! $relation = $current->dm_find_relationship($object->dm_get_config('model')) )
				{
					throw new DataMapper_Exception("DataMapper: Unable to relate '{$current->dm_config['model']}' with '$related_field'");
				}

				// get the relationship definition seen from the related model
				$other_relation = $object->dm_find_relationship($current->dm_config['model']);

				// add the join to the query
				$current->dm_add_relation($relation, $other_relation);

				// augument the append name
				$append_name AND $append .= ( empty($append) ? '' : '*') . $object->dm_get_config('model');

				// make the related object the new 'current'
				$current = $object;
			}

			$arguments = array_shift($arguments);
		}

		// determine the related object: related by deep relationship string
		elseif ( is_string($arguments) AND strpos($arguments, '/') !== FALSE )
		{
			// add the 'in-between' relations, one at the time
			while ( true )
			{
				// strip the first relation of the argument, and adjust the argument
				$related_field = substr( $arguments, 0, strpos($arguments, '/') );
				$arguments = substr( $arguments, strlen($related_field) + 1);

				// find out what the relation is
				if ( ! $relation = $current->dm_find_relationship($related_field) )
				{
					throw new DataMapper_Exception("DataMapper: Unable to relate '{$this->dm_config['model']}' with '$related_field'");
				}

				// instantiate the related class
				$class = $relation['related_class'];
				$object = new $class();

				// get the relationship definition seen from the related model
				$other_relation = $object->dm_find_relationship($current->dm_config['model']);

				// add the join to the query
				$current->dm_add_relation($relation, $other_relation);

				// make the related object the new 'current'
				$current = $object;

				// augument the append name
				$append_name AND $append .= ( empty($append) ? '' : '*') . $related_field;

				// bail out if none are left, the last one is added below as normal
				if ( strpos($arguments, '/') === FALSE )
				{
					break;
				}

			}
		}

		// determine the related object: related by object
		if ( $arguments instanceOf DataMapper )
		{
			$object =& $arguments;

			// find out what the relation is
			if ( ! $relation = $current->dm_find_relationship($object->dm_get_config('model')) )
			{
				throw new DataMapper_Exception("DataMapper: Unable to relate '{$current->dm_config['model']}' with '$related_field'");
			}

			// augument the append name
			$append_name AND $append .= ( empty($append) ? '' : '*') . $object->dm_get_config('model');
		}

		// determine the related object: relationship by name
		else
		{
			// find out what the relation is
			if ( ! $relation = $current->dm_find_relationship($arguments) )
			{
				throw new DataMapper_Exception("DataMapper: Unable to relate '{$current->dm_config['model']}' with '$related_field'");
			}

			$class = $relation['related_class'];

			$object = new $class();

			// augument the append name
			$append_name AND $append .= ( empty($append) ? '' : '*') . $arguments;
		}

		// get the relationship definition seen from the related model
		$other_relation = $object->dm_find_relationship($current->dm_config['model']);

$TODO = 'prevent un-needed joins when selecting on related keys only';

		// add the join to the query
		$current->dm_add_relation($relation, $other_relation);

		// in case of a wildcard, fetch the objects fields
		if ( is_null($fields) OR $fields == '*' )
		{
			$fields = $object->dm_get_config('fields');
		}

		// do we need instantiation?
		if ( $instantiate )
		{
			$instantiate = $append;
			$this->dm_values['instantiations'][$instantiate] = array();
		}

		// do we have a custom append name?
		if ( $append_name AND $append_name !== TRUE )
		{
			// use the custom name
			$append = $append_name;
		}
		else
		{
			// replace the star separator by an understore
			$append = str_replace('*', '_', $append);
		}

		// determine the selection
		$selected = array();

		// loop through the fields of this object
		foreach ( $object->dm_get_config('fields') as $field )
		{
			// do we need to add this field?
			if ( is_null($fields) OR $fields == '*' OR in_array($field, $fields) )
			{
				// append the model_name
				$append_field = $append_name ? ($append.'_'.$field) : '';

				if ( $instantiate )
				{
					$this->dm_values['instantiations'][$instantiate][] = array('field' => $field, 'result' => $append_field);
				}

				// add the field to the selection
				$selected[] = $current->dm_table_alias($other_relation['my_class']).'.'.$field.(empty($append_field)?'':' AS '.$this->db->_protect_identifiers($append_field));
			}
		}

		// add the selected fields to the query
		$this->select($selected);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * starts a query group
	 *
	 * @param	string	$not	(Internal use only)
	 * @param	string	$type	(Internal use only)
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function group_start($not = '', $type = 'AND ')
	{
		// increment group count number to make them unique
		$this->dm_flags['group_count']++;

		// in case groups are being nested
		$type = $this->dm_get_prepend_type($type);

		$this->dm_flags['where_group_started'] = TRUE;

		$prefix = (count($this->db->ar_where) == 0 AND count($this->db->ar_cache_where) == 0) ? '' : $type;

		$value =  $prefix . $not . str_repeat(' ', $this->dm_flags['group_count']) . ' (';
		$this->db->ar_where[] = $value;
		if ( $this->db->ar_caching )
		{
			$this->db->ar_cache_where[] = $value;
		}

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * starts a query group, but ORs the group
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function or_group_start()
	{
		return $this->group_start('', 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * starts a query group, but NOTs the group
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function not_group_start()
	{
		return $this->group_start('NOT ', 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * starts a query group, but OR NOTs the group
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function or_not_group_start()
	{
		return $this->group_start('NOT ', 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * ends a query group
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function group_end()
	{
		// check for an empty group
		$last = end($this->db->ar_where);
		if ( substr($last, -1) == '(' )
		{
			// remove it
			array_pop($this->db->ar_where);
			if ( $this->db->ar_caching )
			{
				array_pop($this->db->ar_cache_where);
			}
		}
		else
		{
			// close the current group
			$value = str_repeat(' ', $this->dm_flags['group_count']) . ')';

			$this->db->ar_where[] = $value;
			if ( $this->db->ar_caching )
			{
				$this->db->ar_cache_where[] = $value;
			}
		}

		$this->dm_flags['where_group_started'] = FALSE;

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE portion of the query, separates multiple calls with AND
	 *
	 * Note: called by get_where()
	 *
	 * @param	mixed 	$key	a field or array of fields to check
	 * @param	mixed	$value	for a single field, the value to compare to
	 * @param	bool	$escape	if FALSE, the field is not escaped
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function where($key, $value = NULL, $escape = TRUE)
	{
		return $this->dm_where($key, $value, 'AND ', $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE portion of the query, separates multiple calls with OR
	 *
	 * @param	mixed	$key	a field or array of fields to check
	 * @param	mixed	$value	for a single field, the value to compare to
	 * @param	bool	$escape	if FALSE, the field is not escaped
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function or_where($key, $value = NULL, $escape = TRUE)
	{
		return $this->dm_where($key, $value, 'OR ', $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE field BETWEEN 'value1' AND 'value2' SQL query joined with
	 * AND if appropriate
	 *
	 * @param	string	$key	a field to check
	 * @param	mixed	$value	value to start with
	 * @param	mixed	$value	value to end with
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function where_between($key = NULL, $value1 = NULL, $value2 = NULL)
	{
	 	return $this->dm_where_between($key, $value1, $value2);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE field BETWEEN 'value1' AND 'value2' SQL query joined with
	 * AND if appropriate
	 *
	 * @param	string	$key	a field to check
	 * @param	mixed	$value	value to start with
	 * @param	mixed	$value	value to end with
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function where_not_between($key = NULL, $value1 = NULL, $value2 = NULL)
	{
	 	return $this->dm_where_between($key, $value1, $value2, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE field BETWEEN 'value1' AND 'value2' SQL query joined with
	 * AND if appropriate
	 *
	 * @param	string	$key	a field to check
	 * @param	mixed	$value	value to start with
	 * @param	mixed	$value	value to end with
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function or_where_between($key = NULL, $value1 = NULL, $value2 = NULL)
	{
	 	return $this->dm_where_between($key, $value1, $value2, FALSE, 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE field BETWEEN 'value1' AND 'value2' SQL query joined with
	 * AND if appropriate
	 *
	 * @param	string	$key	A field to check
	 * @param	mixed	$value	value to start with
	 * @param	mixed	$value	value to end with
	 * @return	DataMapper	returns self for method chaining
	 */
	public function or_where_not_between($key = NULL, $value1 = NULL, $value2 = NULL)
	{
	 	return $this->dm_where_between($key, $value1, $value2, TRUE, 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE field IN ('item', 'item') SQL query joined with
	 * AND if appropriate
	 *
	 * @param	string	$key	a field to check
	 * @param	array	$values	an array of values to compare against
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function where_in($key = NULL, $values = NULL)
	{
	 	return $this->dm_where_in($key, $values);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE field IN ('item', 'item') SQL query joined with
	 * OR if appropriate
	 *
	 * @param	string	$key	a field to check
	 * @param	array	$values	an array of values to compare against
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function or_where_in($key = NULL, $values = NULL)
	{
	 	return $this->dm_where_in($key, $values, FALSE, 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE field NOT IN ('item', 'item') SQL query joined with
	 * AND if appropriate
	 *
	 * @param	string	$key	a field to check
	 * @param	array	$values	an array of values to compare against
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function where_not_in($key = NULL, $values = NULL)
	{
		return $this->dm_where_in($key, $values, TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the WHERE field NOT IN ('item', 'item') SQL query joined wuth
	 * OR if appropriate
	 *
	 * @param	string	$key	a field to check
	 * @param	array	$values	an array of values to compare against
	 *
	 * @return	DataMapper	Returns self for method chaining
	 */
	public function or_where_not_in($key = NULL, $values = NULL)
	{
		return $this->dm_where_in($key, $values, TRUE, 'OR ');
	}

	// --------------------------------------------------------------------

	/**
	 * sets the %LIKE% portion of the query, separates multiple calls with AND
	 *
	 * @param	mixed	$field	a field or array of fields to check
	 * @param	mixed	$match	for a single field, the value to compare to
	 * @param	string	$side	one of 'both', 'before', or 'after'
	 *
	 * @return	DataMapper	Returns self for method chaining
	 */
	public function like($field, $match = '', $side = 'both')
	{
		return $this->dm_like($field, $match, 'AND ', $side);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the NOT LIKE portion of the query, separates multiple calls with AND
	 *
	 * @param	mixed	$field	a field or array of fields to check
	 * @param	mixed	$match	for a single field, the value to compare to
	 * @param	string	$side	one of 'both', 'before', or 'after'
	 *
	 * @return	DataMapper	Returns self for method chaining.
	 */
	public function not_like($field, $match = '', $side = 'both')
	{
		return $this->dm_like($field, $match, 'AND ', $side, 'NOT');
	}

	// --------------------------------------------------------------------

	/**
	 * sets the %LIKE% portion of the query, separates multiple calls with OR
	 *
	 * @param	mixed	$field	a field or array of fields to check
	 * @param	mixed	$match	for a single field, the value to compare to
	 * @param	string	$side	one of 'both', 'before', or 'after'
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function or_like($field, $match = '', $side = 'both')
	{
		return $this->dm_like($field, $match, 'OR ', $side);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the NOT LIKE portion of the query, separates multiple calls with OR
	 *
	 * @param	mixed	$field	a field or array of fields to check
	 * @param	mixed	$match	for a single field, the value to compare to
	 * @param	string	$side	one of 'both', 'before', or 'after'
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function or_not_like($field, $match = '', $side = 'both')
	{
		return $this->dm_like($field, $match, 'OR ', $side, 'NOT');
	}

	// --------------------------------------------------------------------

	/**
	 * sets the case-insensitive %LIKE% portion of the query
	 *
	 * @param	mixed	$field	a field or array of fields to check
	 * @param	mixed	$match	for a single field, the value to compare to
	 * @param	string	$side	one of 'both', 'before', or 'after'
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function ilike($field, $match = '', $side = 'both')
	{
		return $this->dm_like($field, $match, 'AND ', $side, '', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the case-insensitive NOT LIKE portion of the query,
	 * separates multiple calls with AND
	 *
	 * @param	mixed	$field	a field or array of fields to check
	 * @param	mixed	$match	for a single field, the value to compare to
	 * @param	string	$side	one of 'both', 'before', or 'after'
	 * @return	DataMapper	returns self for method chaining
	 */
	public function not_ilike($field, $match = '', $side = 'both')
	{
		return $this->dm_like($field, $match, 'AND ', $side, 'NOT', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the case-insensitive %LIKE% portion of the query,
	 * separates multiple calls with OR
	 *
	 * @param	mixed	$field	a field or array of fields to check
	 * @param	mixed	$match	for a single field, the value to compare to
	 * @param	string	$side	one of 'both', 'before', or 'after'
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function or_ilike($field, $match = '', $side = 'both')
	{
		return $this->dm_like($field, $match, 'OR ', $side, '', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the case-insensitive NOT LIKE portion of the query,
	 * separates multiple calls with OR
	 *
	 * @param	mixed	$field	a field or array of fields to check
	 * @param	mixed	$match	for a single field, the value to compare to
	 * @param	string	$side	one of 'both', 'before', or 'after'
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function or_not_ilike($field, $match = '', $side = 'both')
	{
		return $this->dm_like($field, $match, 'OR ', $side, 'NOT', TRUE);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the GROUP BY portion of the query
	 *
	 * @param	string	$by	field to group by
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function group_by($by)
	{
		$this->db->group_by($this->add_table_name($by));

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the HAVING portion of the query, separates multiple calls with AND
	 *
	 * @param	string	$key	field to compare
	 * @param	string	$value	value to compare to
	 * @param	bool	$escape	if FALSE, don't escape the value
	 * @return	DataMapper	returns self for method chaining
	 */
	public function having($key, $value = '', $escape = TRUE)
	{
		return $this->dm_having($key, $value, 'AND ', $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the OR HAVING portion of the query, separates multiple calls with OR
	 *
	 * @param	string	$key	field to compare
	 * @param	string	$value	value to compare to
	 * @param	bool	$escape	if FALSE, don't escape the value
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function or_having($key, $value = '', $escape = TRUE)
	{
		return $this->dm_having($key, $value, 'OR ', $escape);
	}

	// --------------------------------------------------------------------

	/**
	 * sets the LIMIT portion of the query
	 *
	 * @param	integer	$limit	limit the number of results
	 * @param	integer|NULL	$offset	offset the results when limiting
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function limit($value, $offset = '')
	{
		$this->db->limit($value, $offset);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the OFFSET portion of the query
	 *
	 * @param	integer	$offset	offset the results when limiting
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function offset($offset)
	{
		$this->db->offset($offset);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * starts active record caching
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function start_cache()
	{
		$this->db->start_cache();

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * stops active record caching
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function stop_cache()
	{
		$this->db->stop_cache();

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * empties the active record cache
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function flush_cache()
	{
		$this->db->flush_cache();

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * adds the table name to a field if necessary
	 *
	 * @param	string	$field	field to add the table name to
	 *
	 * @return	string	possibly modified field name
	 */
	public function add_table_name($field)
	{
		if ( is_array($field) )
		{
			$result = array();
			foreach ( $field as $fieldname )
			{
				$result[] = $this->add_table_name($fieldname);
			}
			return $result;
		}
		else
		{
			// deal with composed strings using AND or OR
			if ( preg_match('/AND|OR/', $field) )
			{
				$field_parts = explode('OR', $field);
				if ( count($field_parts) > 1 )
				{
					$field = '';
					foreach ( $field_parts as $part )
					{
						$field .= (empty($field) ? '' : ' OR ') . $this->add_table_name(trim($part));
					}
				}
				$field_parts = explode('AND', $field);
				if ( count($field_parts) > 1 )
				{
					$field = '';
					foreach ( $field_parts as $part )
					{
						$field .= (empty($field) ? '' : ' AND ') . $this->add_table_name(trim($part));
					}
				}
			}

			// only add table if the field doesn't contain a dot (.) or open parentheses
			if ( preg_match('/[\.\(]/', $field) == 0 )
			{
				// split string into parts, add field
				$field_parts = explode(',', $field);
				$field = '';
				foreach ( $field_parts as $part )
				{
					! empty($field) AND $field .= ', ';
					$part = ltrim($part);

					// handle comparison operators on where
					$subparts = explode(' ', $part, 2);
					if ( $subparts[0] == '*' OR in_array($subparts[0], $this->dm_config['fields']) )
					{
						$field .= $this->db->protect_identifiers($this->dm_table_alias($this->dm_config['model'])  . '.' . $part);
					}
					else
					{
						$field .= $part;
					}
				}
			}

			return $field;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * sets the ORDER BY portion of the query.
	 *
	 * @param	string	$orderby	field to order by
	 * @param	string	$direction	one of 'ASC' or 'DESC'  Defaults to 'ASC'
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function order_by($orderby, $direction = '')
	{
		$this->db->order_by($this->add_table_name($orderby), $direction);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * renders the last DB query performed
	 *
	 * @param	array	$delims				delimiters for the SQL string
	 * @param	bool	$return_as_string	if TRUE, don't output automatically
	 *
	 * @return	string	last db query formatted as a string
	 */
	public function check_last_query($delims = array('<hr /><pre>', '</pre><hr />'), $return_as_string = FALSE)
	{
		$q = wordwrap($this->db->last_query(), 100, "\n\t");
		if ( ! empty($delims) )
		{
			$q = implode($q, $delims);
		}
		if ( $return_as_string === FALSE )
		{
			echo $q;
		}
		return $q;
	}

	// --------------------------------------------------------------------

	/**
	 * returns a clone of the current object
	 *
	 * @return	DataMapper	cloned copy of this object
	 */
	public function get_clone($force_db = FALSE)
	{
		$temp = clone($this);

		// This must be left in place, even with the __clone method,
		// or else the DB will not be copied over correctly.
		if ( $force_db OR (($this->dm_config['config']['db_params'] !== FALSE) AND isset($this->db)) )
		{
			// create a copy of $this->db
			$temp->db = clone($this->db);
		}

		return $temp;
	}

	// --------------------------------------------------------------------

	/**
	 * returns an unsaved copy of the current object
	 *
	 * @return	DataMapper	cloned copy of this object with an empty ID for saving as new
	 */
	public function get_copy($force_db = FALSE)
	{
		// get a clone of this object
		$copy = $this->get_clone($force_db);

		// reset the keys to make it new
		foreach ( $dm_config['keys'] as $key => $type )
		{
			$copy->dm_current->{$key} = NULL;
		}

		return $copy;
	}

	// -------------------------------------------------------------------------
	// DataMapper public support methods
	// -------------------------------------------------------------------------

	/**
	 * returns the value of a stored flag
	 *
	 * @param	string	$flag	name of the flag requested
	 *
	 * @return	mixed	the value of the flag, or NULL if not found
	 */
	public function dm_get_flag($flag)
	{
		if ( isset($this->dm_flags[$flag]) )
		{
			return $this->dm_flags[$flag];
		}

		// unknown flag
		return NULL;
	}

	// -------------------------------------------------------------------------

	/**
	 * sets the value of a stored flag
	 *
	 * @param	string	$flag	name of the flag requested
	 * @param	mixed	$flag	name of the flag requested
	 *
	 * @return	void
	 */
	public function dm_set_flag($flag, $value)
	{
		$this->dm_flags[$flag] = $value;
	}

	// -------------------------------------------------------------------------

	/**
	 * returns the value of a stored value
	 *
	 * @param	mixed	$value	name of the value requested
	 *
	 * @return	mixed	the value of the value, or NULL if not found
	 */
	public function dm_get_value($value)
	{
		if ( isset($this->dm_values[$value]) )
		{
			return $this->dm_values[$value];
		}

		// unknown flag
		return NULL;
	}

	// -------------------------------------------------------------------------

	/**
	 * returns the value of a stored config value
	 *
	 * @param	mixed	$name	name of the config value requested
	 * @param	mixed	$subkey	name of the subkey value requested
	 *
	 * @return	mixed	the value, or NULL if not found
	 */
	public function dm_get_config($name = NULL, $subkey = NULL)
	{
		if ( func_num_args() == 0 )
		{
			return $this->dm_config;
		}
		else
		{
			if ( isset($this->dm_config[$name]) )
			{
				if ( ! is_null($subkey) AND isset($this->dm_config[$name][$subkey]) )
				{
					return $this->dm_config[$name][$subkey];
				}
				return $this->dm_config[$name];
			}
		}

		// unknown flag
		return NULL;
	}

	// -------------------------------------------------------------------------

	/**
	 * returns an array with the object's key's
	 *
	 * @return	array	array of key-value pairs
	 */
	public function dm_get_keys()
	{
		$result = array();

		foreach ( $this->dm_config['keys'] as $key => $unused )
		{
			$result[$key] = $this->{$key};
		}

		return $result;
	}

	// -------------------------------------------------------------------------

	/**
	 * set or reset an error message
	 *
	 * @param	string	$field		field name to set the message on
	 * @param	string	$message	message
	 *
	 * @return	mixed	the value, or NULL if not found
	 */
	public function error_message($field, $message = '')
	{
		$this->error->message($field, $message);
	}

	// --------------------------------------------------------------------

	/**
	 * get the name of the table alias
	 *
	 * @param	string	$model		name of the model to lookup
	 * @param	bool	$protect	if true, the alias will be protected
	 *
	 * @return	string	alias, or the current models table if not found
	 */
	public function dm_table_alias($model, $protect = FALSE)
	{
		if ( ! isset(DataMapper::$dm_table_aliases[$model]) )
		{
			DataMapper::$dm_table_aliases[$model] = 'DMTA_'.self::$alias_counter++;
		}
		return $protect ? $this->db->protect_identifiers(DataMapper::$dm_table_aliases[$model]) : DataMapper::$dm_table_aliases[$model];
	}

	// --------------------------------------------------------------------

	/**
	 * converts a query result into an array of objects.
	 * also updates this object
	 *
	 * @param	CI_DB_result	$query
	 */
	public function dm_process_query($query)
	{
		if ( $query->num_rows() > 0 )
		{
			// reset the all array
			$this->all = array();

			// determine what to use for the all array index
			if ( count($this->dm_config['keys']) == 1 AND $this->dm_config['config']['all_array_uses_keys'] )
			{
				$indextype = key($this->dm_config['keys']);
			}
			else
			{
				$indextype = FALSE;
			}
			$index = 0;

			// flag to detect the first record in the result
			$first = TRUE;

			// fetch the current model class name
			$model = get_class($this);

			// loop through the results
			foreach ( $query->result() as $row )
			{
				// store the values of the record in the model object
				if ( $first )
				{
					// re-use the current object
					$this->dm_to_object($this, $row);
					$item =& $this;
					$first = FALSE;
				}
				else
				{
					// create the new model object
					$item = new $model();
					$this->dm_to_object($item, $row);
				}

				// and store it in the all array
				if ( $indextype )
				{
					$this->all[$this->{$indextype}] = $item;
				}
				else
				{
					$this->all[$index++] = $item;
				}
			}

			// clear any stored instantiations
			$this->dm_values['instantiations'] = array();

			// free large queries
			if ( $query->num_rows() > $this->dm_config['config']['free_result_threshold'] )
			{
				$query->free_result();
			}
		}
		else
		{
			// no results, reset the object data storage
			$this->dm_refresh_original_values();
		}
	}

	// --------------------------------------------------------------------

	/**
	 * copies the values from a query result row to an object
	 * also initializes that object by running get rules, and
	 *   refreshing stored values on the object.
	 *
	 * finally, if any "instantiations" are requested, those related objects
	 *   are created off of query results
	 *
	 * this is only public so that the iterator can access it.
	 *
	 * @ignore
	 *
	 * @param	DataMapper	$item	item to configure (by reference, so we can update it)
	 * @param	object		$row	query results row
	 */
	public function dm_to_object(&$item, $row)
	{
		// populate this object with values from first record
		foreach ( $row as $key => $value )
		{
			// make sure the field name is stored in lower case!
			$item->dm_current->{$key} = $value;
		}

		// make sure any columns not part of the result are set
		foreach ( $this->dm_config['fields'] as $field )
		{
			if ( ! isset($item->dm_current->{$field}) )
			{
				$item->dm_current->{$field} = NULL;
			}
		}

		// run any get rules on this record if needed
		if ( ! empty($this->dm_config['validation']['get_rules']) )
		{
			$item->run_get_rules();
		}

		// if we need to instantiate included related results, now is the time
		if ( ! empty($this->dm_values['instantiations']) )
		{
			foreach ( $this->dm_values['instantiations'] as $model => $fields )
			{
				// instantiate the objects
				$object = $item;
				foreach ( explode('*', $model) as $related_model )
				{
					$object = $object->$related_model;
				}

				// assign the fields in the query result to the objects
				foreach ( $fields as $field )
				{
					// assign the values
					$object->{$field['field']} = $item->{$field['result']};
					unset($item->{$field['result']});
				}
			}
		}

		// make sure the original values of this record are in sync
		$item->dm_refresh_original_values();
	}

	// -------------------------------------------------------------------------
	// DataMapper public helper methods
	// -------------------------------------------------------------------------

	/**
	 * DataMapper version of $this->lang->line
	 *
	 * @ignore	public because it needs to be accessable by extensions
	 *
	 * @param	string	$line		name of the language string to lookup
	 * @param	string	$value		preset value
	 *
	 * @return	string	result of the lookup
	 */
	public function dm_lang_line($line, $value = FALSE, $config = NULL)
	{
		if ( strpos($value, 'lang:') === 0 )
		{
			$line = substr($value, 5);
			$value = FALSE;
		}

		if ( $value === FALSE )
		{
			is_null($config) AND $config =& $this->dm_config;
			if ( ! empty($config['config']['field_label_lang_format']) )
			{
				$s = array('${model}', '${table}');
				$r = array($config['model'], $config['table']);
				if ( ! is_null($line) )
				{
					$s[] = '${field}';
					$r[] = $line;
				}
				$key = str_replace($s, $r, $config['config']['field_label_lang_format']);
				$value = DataMapper::$CI->lang->dm_line($key);

				if ( $value === FALSE )
				{
					$value = $line;
				}
			}
		}

		return $value;
	}

	// --------------------------------------------------------------------

	/**
	 * locate the relationship definition based on the relation name
	 *
	 * @ignore
	 *
	 * @param	string	$relation		name of the related object
	 *
	 * @return 	array|NULL
	 */
	public function dm_find_relationship($relation)
	{
		// if an object is passed, search on the objects model name
		if ( $relation instanceOf DataMapper )
		{
			$relation = $relation->dm_get_config('model');
		}

		// find the relationship definition for this relation
		foreach ( $this->dm_config['relations'] as $type => $definitions )
		{
			foreach ( $definitions as $name => $definition )
			{
				if ( $name == $relation )
				{
					$definition['type'] = $type;
					return $definition;
				}
			}
		}

		// not a valid relationship for this object
		return FALSE;
	}

	// --------------------------------------------------------------------

	/**
	 * handles specialized where_in clauses, like subqueries and functions
	 *
	 * @ignore
	 *
	 * @param	string	$query 	query function
	 * @param	string	$field	Ffield for Query function
	 * @param	mixed	$value	value for Query function
	 * @param	mixed	$extra	if included, overrides the default assumption of FALSE for the third parameter to $query
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	public function dm_alter_where_in($query, $field, $value, $extra = NULL)
	{
		// deal with where_in()
		if ( strpos($query, 'where_in') !== FALSE )
		{
			$query = str_replace('_in', '', $query);
			$field .= ' IN ';
		}

		// deal with where_not_in
		elseif ( strpos($query, 'where_not_in') !== FALSE )
		{
			$query = str_replace('_not_in', '', $query);
			$field .= ' NOT IN ';
		}

		// make sure $extra has the correct value
		is_null($extra) AND $extra = FALSE;

		// return the result
		return $this->{$query}($field, $value, $extra);
	}


	// -------------------------------------------------------------------------
	// DataMapper protected methods
	// -------------------------------------------------------------------------

	/**
	 * @ignore
	 *
	 * @param	string	$query	name of query function
	 * @param	array	$args	arguments for subquery
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	protected function dm_subquery($query, $arguments)
	{
		// make sure we have arguments
		if ( count($arguments) < 1 )
		{
			throw new DataMapper_Exception("DataMapper: invalid arguments on {$query}_subquery: there must be at least one argument");
		}

		// selects are different from other subqueries
		if($query == 'select')
		{
			// select subquery needs two arguments
			if ( count($arguments) < 2 )
			{
				throw new DataMapper_Exception("DataMapper: invalid arguments on select_subquery: there must be exactly 2 arguments.");
			}

			// get the parsed sql and the
			$sql = $this->dm_parse_subquery_sql($arguments[0]);
			$alias = $arguments[1];

			// we can't use the normal select method, because CI likes to breaky
			$this->dm_manual_select("$sql AS $alias");
		}
		else
		{
die($TODO = $query.' type subquery is not implemented yet');
		}

		// for method chaining
		return $this;
	}

	// -------------------------------------------------------------------------

	/**
	 * parses and protects a subquery
	 *
	 * automatically replaces the special ${parent} argument with a reference to
	 * this table. also makes sure all aliases are unique
	 *
	 * @ignore
	 *
	 * @param	mixed	$sql	SQL string to process, or object to extract the SQL from
	 *
	 * @return	string	processed SQL string
	 */
	protected function dm_parse_subquery_sql($sql)
	{
		// if a DataMapper object is passed, extract the SQL
		if ( $sql instanceOf DataMapper )
		{
			$sql = '(' . $sql->get_sql(NULL, NULL, FALSE, TRUE) . ')';
		}

		// determine the alias pattern
		$search = '/'.$this->db->_escape_identifiers('(DMTA_)(\d+)').'/';

		// determine the current subquery prefix
		$replace = 'DMSQ'.(self::$subquery_counter++).'_$2';

		// make the aliases used unique for this subquery
		$sql = preg_replace($search, $replace, $sql);

		// replace the placeholder by the current table name, and return the SQL
		return str_replace('${parent}', $this->dm_table_alias($this->dm_config['model']), $sql);
	}

	// --------------------------------------------------------------------

	/**
	 * manually adds an item to the SELECT column, to prevent it from
	 * being broken by AR->select
	 *
	 * @ignore
	 *
	 * @param	string	$value New SELECT value
	 */
	protected function dm_manual_select($value)
	{
		// note: copied from system/database/DB_activerecord.php
		$this->db->ar_select[] = $value;

		if ($this->db->ar_caching === TRUE)
		{
			$this->db->ar_cache_select[] = $value;
			$this->db->ar_cache_exists[] = 'select';
		}
	}

	// -------------------------------------------------------------------------

	/**
	 * converts this objects current record into an array for database queries
	 * If validate is TRUE (getting by objects properties) empty objects are ignored.
	 *
	 * @ignore
	 *
	 * @param	bool	$validate
	 *
	 * @return	array
	 */
	protected function dm_to_array($validate = FALSE)
	{
		$data = array();

		foreach ($this->dm_config['fields'] as $field)
		{
			if ( $validate AND ! isset($this->dm_current->{$field}) )
			{
				continue;
			}

			$data[$field] = $this->dm_current->{$field};
		}

		return $data;
	}

	// --------------------------------------------------------------------

	/**
	 * gets objects by specified field name and value
	 *
	 * @ignore
	 *
	 * @param	string	$field	field to look at
	 * @param	array	$value	arguments to this method
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	protected function dm_get_by($field, $value = array())
	{
		// backward compatibility
		is_array($value) OR $value = array($value);

		if ( isset($value[0]) )
		{
			$this->where($field, $value[0]);
		}

		return $this->get();
	}

	// -------------------------------------------------------------------------

	/**
	 * refreshes the orginal object values with the current values
	 *
	 * @ignore
	 */
	protected function dm_refresh_original_values()
	{
		// update stored values
		foreach ($this->dm_config['fields'] as $field)
		{
			$this->dm_original->{$field} = $this->dm_current->{$field};
		}

		// If there is a "matches" validation rule, match the field value with the other field value
		foreach ($this->dm_config['validation']['matches'] as $field_name => $match_name)
		{
			$this->dm_current->{$field_name} = $this->dm_original->{$field_name} = $this->{$match_name};
		}
	}

	// --------------------------------------------------------------------

	/**
	 * adds in the defaut order_by items, if there are any, and
	 * order_by hasn't been overridden.
	 *
	 * @ignore
	 */
	protected function dm_default_order_by()
	{
		if ( ! empty($this->dm_config['order_by']) )
		{
			$sel = $this->add_table_name('*');
			$sel_protect = $this->db->protect_identifiers($sel);

			// only add the items if there isn't an existing order_by,
			// AND the select statement is empty or includes * or table.* or `table`.*
			if ( empty($this->db->ar_orderby) AND
				(
					empty($this->db->ar_select) OR
					in_array('*', $this->db->ar_select) OR
					in_array($sel_protect, $this->db->ar_select) OR
					in_array($sel, $this->db->ar_select)

				))
			{
				foreach($this->dm_config['order_by'] as $k => $v) {
					if ( is_int($k) )
					{
						$k = $v;
						$v = '';
					}
					$k = $this->add_table_name($k);
					$this->order_by($k, $v);
				}
			}
		}
	}

	// --------------------------------------------------------------------

	/**
	 * protected function to convert the AND or OR prefix to '' when starting
	 * a group.
	 *
	 * @ignore
	 *
	 * @param	object	$type	Current type value
	 *
	 * @return	New	type value
	 */
	protected function dm_get_prepend_type($type)
	{
		if ( $this->dm_flags['where_group_started'] )
		{
			$type = '';
			$this->dm_flags['where_group_started'] = FALSE;
		}

		return $type;
	}

	// --------------------------------------------------------------------

	/**
	 * @ignore
	 *
	 * @param	string	$query Query method.
	 * @param	array	$arguments Arguments for query.
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	protected function dm_join_field($query, $arguments)
	{
		// add the relation for this join
		$this->dm_related($query, $arguments, NULL, TRUE);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Note: called by where() and or_where().
	 *
	 * @ignore
	 *
	 * @param	mixed	$key	a field or array of fields to check
	 * @param	mixed	$value	for a single field, the value to compare to
	 * @param	string	$type	type of addition (AND or OR)
	 * @param	bool	$escape	if FALSE, the field is not escaped
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	protected function dm_where($key, $value = NULL, $type = 'AND ', $escape = NULL)
	{
		// make sure we've got a key->value pair
		if ( ! is_array($key) )
		{
			$key = array($key => $value);
		}

		foreach ( $key as $k => $v )
		{
			$new_k = $this->add_table_name($k);
			$this->db->dm_call_method('_where', $new_k, $v, $this->dm_get_prepend_type($type), $escape);
		}

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the HAVING portion of the query, separates multiple calls with AND
	 *
	 * @ignore
	 *
	 * @param	string	$key	field to compare
	 * @param	string	$value	value to compare to
	 * @param	string	$type	type of connection (AND or OR)
	 * @param	bool	$escape	if FALSE, don't escape the value
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	protected function dm_having($key, $value = '', $type = 'AND ', $escape = TRUE)
	{
		$this->db->dm_call_method('_having', $this->add_table_name($key), $value, $type, $escape);

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * NOTE: this does NOT use the built-in ActiveRecord LIKE function
	 *
	 * @ignore
	 *
	 * @param	mixed	$field		a field or array of fields to check
	 * @param	mixed	$match		for a single field, the value to compare to
	 * @param	string	$type		the type of connection (AND or OR)
	 * @param	string	$side		one of 'both', 'before', or 'after'
	 * @param	string	$not		'NOT' or ''
	 * @param	bool	$no_case	if TRUE, configure to ignore case
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	protected function dm_like($field, $match = '', $type = 'AND ', $side = 'both', $not = '', $no_case = FALSE)
	{
		// make sure we have a key->value field
		if ( ! is_array($field) )
		{
			$field = array($field => $match);
		}

		foreach ( $field as $k => $v )
		{
			$new_k = $this->add_table_name($k);
			if ( $new_k != $k )
			{
				$field[$new_k] = $v;
				unset($field[$k]);
			}
		}

		// Taken from CodeIgniter's Active Record because (for some reason)
		// it is stored separately that normal where statements.

		foreach ( $field as $k => $v )
		{
			if ( $no_case )
			{
				$k = 'UPPER(' . $this->db->protect_identifiers($k) .')';
				$v = strtoupper($v);
			}
			$f = "$k $not LIKE ";

			if ( $side == 'before' )
			{
				$m = "%{$v}";
			}
			elseif ( $side == 'after' )
			{
				$m = "{$v}%";
			}
			else
			{
				$m = "%{$v}%";
			}

			$this->dm_where($f, $m, $type, TRUE);
		}

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * called by where_between(), or_where_between(), where_not_between(), or or_where_not_between().
	 *
	 * @ignore
	 *
	 * @param	string	$key	a field to check
	 * @param	mixed	$value	value to start with
	 * @param	mixed	$value	value to end with
	 * @param	bool	$not	if TRUE, use NOT IN instead of IN
	 * @param	string	$type	the type of connection (AND or OR)
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	protected function dm_where_between($key = NULL, $value1 = NULL, $value2 = NULL, $not = FALSE, $type = 'AND ')
	{
		$type = $this->dm_get_prepend_type($type);

	 	$this->db->dm_call_method('_where', "`$key` ".($not?"NOT ":"")."BETWEEN ".$value1." AND ".$value2, NULL, $type, NULL);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * called by where_in(), or_where_in(), where_not_in(), or or_where_not_in()
	 *
	 * @ignore
	 *
	 * @param	string	$key	A field to check
	 * @param	array	$values	An array of values to compare against
	 * @param	bool	$not	If TRUE, use NOT IN instead of IN
	 * @param	string	$type	The type of connection (AND or OR)
	 *
	 * @return	DataMapper	returns self for method chaining
	 */
	protected function dm_where_in($key = NULL, $values = NULL, $not = FALSE, $type = 'AND ')
	{
		$type = $this->dm_get_prepend_type($type);

		if ($values instanceOf DataMapper)
		{
			$arr = array();
			foreach ($values as $value)
			{
die($TODO = 'deal with the new keys structure');
				$arr[] = $value->id;
			}
			$values = $arr;
		}

	 	$this->db->dm_call_method('_where_in', $this->add_table_name($key), $values, $not, $type);

		// for method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * clears the db object after processing a query, or returning the
	 * SQL for a query
	 *
	 * @ignore
	 */
	protected function dm_clear_after_query()
	{
		// clear the query as if it was run
		$this->db->dm_call_method('_reset_select');

		// clear any stored object values
		$this->dm_values['instantiations'] = array();

		// Clear the saved iterator
		unset($this->dm_dataset_iterator);
	}

	// --------------------------------------------------------------------

	/**
	 * used several places to temporarily override the auto_populate setting
	 *
	 * @ignore
	 *
	 * @param	string	$related	related name
	 *
	 * @return 	DataMapper|NULL
	 */
	protected function &dm_get_without_auto_populating($related)
	{
		// save the current settings
		$b_many = $this->dm_config['config']['auto_populate_has_many'];
		$b_one = $this->dm_config['config']['auto_populate_has_one'];

		// disable auto population
		$this->dm_config['config']['auto_populate_has_one'] = FALSE;
		$this->dm_config['config']['auto_populate_has_many'] = FALSE;

		// fetch the related object
		$ret =& $this->{$related};

		// and reset the autopopulate settings
		$this->dm_config['config']['auto_populate_has_many'] = $b_many;
		$this->dm_config['config']['auto_populate_has_one'] = $b_one;

		return $ret;
	}

	// --------------------------------------------------------------------

	/**
	 * Handles the adding the related part of a query if $parent is set
	 *
	 * @ignore
	 *
	 * @return	bool	success or failure
	 */
	public function dm_handle_related()
	{
		// if no parent is present, there's nothing to relate
		if ( ! empty($this->dm_values['parent']) )
		{
			// determine the keys and key values
			$keys = array();

			// only add a where clause if we have a valid parent
			if ( $this->dm_values['parent']['object']->exists() )
			{
				foreach ( $this->dm_values['parent']['relation']['my_key'] as $key )
				{
					$keys[$key] = $this->dm_values['parent']['object']->{$key};
				}
			}
			// to ensure result integrity, group all previous queries if needed
			if ( ! empty($this->db->ar_where) AND $this->db->ar_where[0] != '( ' )
			{
				array_unshift($this->db->ar_where, '( ');
				$this->db->ar_where[] = ' )';
			}

			// add the related table selection to the query
			$this->where_related($this->dm_values['parent']['relation']['my_class'], $keys);

			// add the relations of our parent to handle nested relations
			$this->dm_values['parent']['object']->dm_handle_related();
		}

		return TRUE;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the specified related query
	 *
	 * @ignore
	 *
	 * @param	string	$query		query string
	 * @param	array	$arguments	arguments to process
	 * @param	mixed	$extra		used to prevent escaping in special circumstances
	 * @param	bool	$join_only	interal only. used when only the join table needs to be joined
	 *
	 * @return	DataMapper			returns self for method chaining
	 */
	protected function dm_related($query, $arguments = array(), $extra = NULL, $join_only = FALSE)
	{
		// start relating from the current object
		$current = $this;

		if ( ! empty($query) && ! empty($arguments) )
		{
			// determine the related object: related by deep relationship objects
			if ( is_array($arguments[0]) )
			{
				while ( count($arguments[0]) > 1 )
				{
					$object = array_shift($arguments[0]);

					// find out what the relation is
					if ( ! $relation = $current->dm_find_relationship($object->dm_get_config('model')) )
					{
						throw new DataMapper_Exception("DataMapper: Unable to relate '{$current->dm_config['model']}' with '$related_field'");
					}

					// get the relationship definition seen from the related model
					$other_relation = $object->dm_find_relationship($current->dm_config['model']);

					// add the join to the query
					$current->dm_add_relation($relation, $other_relation);

					// make the related object the new 'current'
					$current = $object;
				}

				$arguments[0] = array_shift($arguments[0]);
			}

			// determine the related object: related by deep relationship string
			elseif ( is_string($arguments[0]) AND strpos($arguments[0], '/') !== FALSE )
			{
				// add the 'in-between' relations, one at the time
				while ( true )
				{
					// strip the first relation of the argument, and adjust the argument
					$related_field = substr( $arguments[0], 0, strpos($arguments[0], '/') );
					$arguments[0] = substr( $arguments[0], strlen($related_field) + 1);

					// find out what the relation is
					if ( ! $relation = $current->dm_find_relationship($related_field) )
					{
						throw new DataMapper_Exception("DataMapper: Unable to relate '{$this->dm_config['model']}' with '$related_field'");
					}

					// instantiate the related class
					$class = $relation['related_class'];
					$object = new $class();

					// get the relationship definition seen from the related model
					$other_relation = $object->dm_find_relationship($current->dm_config['model']);

					// add the join to the query
					$current->dm_add_relation($relation, $other_relation);

					// make the related object the new 'current'
					$current = $object;

					// bail out if none are left, the last one is added below as normal
					if ( strpos($arguments[0], '/') === FALSE )
					{
						break;
					}

				}
			}

			// determine the related object: related by object
			if ( $arguments[0] instanceOf DataMapper )
			{
				$object = array_shift($arguments);

				// find out what the relation is
				if ( ! $relation = $current->dm_find_relationship($object->dm_get_config('model')) )
				{
					throw new DataMapper_Exception("DataMapper: Unable to relate '{$current->dm_config['model']}' with '$related_field'");
				}

			}

			// determine the related object: relationship by name
			else
			{
				// find out what the relation is
				$related_field = array_shift($arguments);
				if ( ! $relation = $current->dm_find_relationship($related_field) )
				{
					throw new DataMapper_Exception("DataMapper: Unable to relate '{$current->dm_config['model']}' with '$related_field'");
				}

				$class = $relation['related_class'];

				$object = new $class();
			}

			// no selection arguments present
			if ( empty($arguments) )
			{
				$selection = array();
			}

			// selection is already an array
			elseif ( is_array($arguments[0]) )
			{
				$selection = array_shift($arguments);
			}

			// selection is another object
			elseif ( $arguments[0] instanceOf DataMapper )
			{
die($TODO = 'related query based on a passed object');
			}
			else
			{
				$selection = array( array_shift($arguments) => array_shift($arguments) );
			}

			// get the relationship definition seen from the related model
			$other_relation = $object->dm_find_relationship($current->dm_config['model']);

$TODO = 'prevent un-needed joins when selecting on related keys only';

			// add the join to the query
			$current->dm_add_relation($relation, $other_relation, $join_only);

			// allow special arguments to be passed into query methods
			if ( is_null($extra) )
			{
				isset($arguments[0]) AND $extra = $arguments[0];
			}

			// prefix the keys with the related table name
			$keys = array();
			foreach ( $selection as $name => $value )
			{
				if ( $join_only )
				{
					$keys[$current->dm_table_alias($other_relation['join_table']).'.'.$name] = $value;
				}
				else
				{
					$keys[$current->dm_table_alias($other_relation['my_class']).'.'.$name] = $value;
				}
			}

			// add the selection to the query
			if ( is_null($extra) )
			{
				$this->{$query}($keys);
			}
			else
			{
				$this->{$query}($keys, NULL, $extra);
			}
		}

		// For method chaining
		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * sets the specified related query
	 *
	 * @ignore
	 *
	 * @param	array	$modela		relation definition for table A, the current model
	 * @param	array	$modelb		relation definition for table B, the joined model
	 * @param	bool	$join_only	if true, only join the join table on a many-to-many
	 *
	 * @return	void
	 */
	protected function dm_add_relation(Array $modela, Array $modelb, $join_only = FALSE)
	{
		// many-to-many relationship
		if ( $modela['type'] == 'has_many' AND $modelb['type'] == 'has_many' )
		{
//var_dump('many-to-many relationship');
			// make sure we share the same join table
			if ( $modela['join_table'] != $modela['join_table'] )
			{
				throw new DataMapper_Exception("DataMapper: '".$modela['related_class']."' and '".$modelb['my_class']."' must define the same join table");
			}

			// build the join condition
			$cond = '';
			for ( $i = 0; $i < count($modela['my_key']); $i++ )
			{
				$cond .= ( empty($cond) ? '' : ' AND ' ) . $this->dm_table_alias($modela['join_table']).'.'.$modela['related_key'][$i];
				$cond .= ' = ' . $this->dm_table_alias($modela['my_class']).'.'.$modela['my_key'][$i];
			}

			// join modela to the join table
			$this->db->join($modela['join_table'].' '.$this->dm_table_alias($modela['join_table'], TRUE), $cond, 'LEFT OUTER');

			// join modelb to the join table
			if ( $join_only === FALSE )
			{
				// build the join condition
				$cond = '';
				for ( $i = 0; $i < count($modelb['my_key']); $i++ )
				{
					$cond .= ( empty($cond) ? '' : ' AND ' ) . $this->dm_table_alias($modela['join_table']).'.'.$modelb['related_key'][$i];
					$cond .= ' = ' . $this->dm_table_alias($modelb['my_class']).'.'.$modelb['my_key'][$i];
				}

				// join modela to the join table
				$this->db->join($modelb['my_table'].' '.$this->dm_table_alias($modelb['related_model'], TRUE), $cond, 'LEFT OUTER');
			}
		}

		// many-to-one relationship, or one-to-one relationship, child -> parent
		elseif ( ( $modela['type'] == 'has_many' AND $modelb['type'] == 'belongs_to' )
			OR ( $modela['type'] == 'has_one' AND $modelb['type'] == 'belongs_to' ) )
		{
//var_dump('many-to-one relationship, or one-to-one relationship, child -> parent');
			// can't have join fields on this relation type
			$this->dm_flags['include_join_fields'] = FALSE;

			// build the join condition
			$cond = '';
			for ( $i = 0; $i < count($modelb['my_key']); $i++ )
			{
				$cond .= ( empty($cond) ? '' : ' AND ' ) . $this->dm_table_alias($modela['related_model']).'.'.$modela['my_key'][$i];
				$cond .= ' = '.$this->dm_table_alias($modelb['related_model']).'.'.$modela['related_key'][$i];
			}

			// join with modela
			$this->db->join($modelb['my_table'].' '.$this->dm_table_alias($modelb['related_model'], TRUE), $cond, 'LEFT OUTER');
		}

		// one-to-many relationship, or one-to-one relationship, parent -> child
		elseif ( ( $modela['type'] == 'belongs_to' AND $modelb['type'] == 'has_many' )
			OR ( $modela['type'] == 'belongs_to' AND $modelb['type'] == 'has_one' ) )
		{
//var_dump('one-to-many relationship, or one-to-one relationship, parent -> child');
			// can't have join fields on this relation type
			$this->dm_flags['include_join_fields'] = FALSE;

			// build the join condition
			$cond = '';
			for ( $i = 0; $i < count($modelb['my_key']); $i++ )
			{
				$cond .= ( empty($cond) ? '' : ' AND ' ) . $this->dm_table_alias($modela['my_class']).'.'.$modelb['related_key'][$i];
				$cond .= ' = '.$this->dm_table_alias($modelb['my_class']).'.'.$modelb['my_key'][$i];
			}

			// join with modelb
			$this->db->join($modelb['my_table'].' '.$this->dm_table_alias($modelb['related_model'], TRUE), $cond, 'LEFT OUTER');
		}

		// incompatible combination, bail out
		else
		{
			throw new DataMapper_Exception("DataMapper: incompatible relation detected between '".$modela['related_class']."[".$modela['type']."]' and '".$modelb['my_class']."[".$modelb['type']."]'");
		}

		// do we need to add any join fields to this query?
		if ( $this->dm_flags['include_join_fields'] )
		{
$TODO = 'cache this somehow, it is rediculous to query for it every time!';
			// get the list of fields of the join table
			$fields = $this->db->field_data($modela['join_table']);

			// drop all fields that are related keys in this many-to-many
			foreach ( $fields as $key => $field )
			{
				if ( in_array($field->name, $modela['related_key']) OR in_array($field->name, $modelb['related_key']) )
				{
					unset($fields[$key]);
				}
			}

			// add the other fields to the query
			foreach ( $fields as $key => $field )
			{
				$this->db->select($this->dm_table_alias($modela['join_table']).'.'.$field->name.' AS '.$this->db->_protect_identifiers('join_'.$field->name));
			}

			// reset the flag
			$this->dm_flags['include_join_fields'] = FALSE;
		}

	}

	// -------------------------------------------------------------------------
	// IteratorAggregate methods
	// -------------------------------------------------------------------------

	/**
	 * returns a streamable result set for large queries
	 *
	 * Usage:
	 * $rs = $object->get_iterated();
	 * $size = $rs->count;
	 * foreach($rs as $o) {
	 *	 // handle $o
	 * }
	 * $rs can be looped through more than once.
	 *
	 * @param	integer|NULL	$limit	limit the number of results
	 * @param	integer|NULL	$offset	offset the results when limiting
	 *
	 * @return	DataMapper	returns self for method chaining.
	 */
	public function get_iterated($limit = NULL, $offset = NULL)
	{
		// clone $this, so we keep track of instantiations, etc.
		// because these are cleared after the call to get_raw
		$object = $this->get_clone();

		// need to clear query from the clone
		$object->db->dm_call_method('_reset_select');

		// construct the iterator object
		$this->dm_dataset_iterator = new DataMapper_DatasetIterator($object, $this->get_raw($limit, $offset, TRUE));

		// for method chaining
		return $this;
	}

	// -------------------------------------------------------------------------

	/**
	 * allows the all array to be iterated over without having to specify it
	 *
	 * @return	Iterator	An iterator for the all array
	 */
	public function getIterator()
	{
		// do we have an iterator object defined?
		if ( $this->dm_dataset_iterator instanceOf DataMapper_DatasetIterator )
		{
			return $this->dm_dataset_iterator;
		}
		else
		{
			return new ArrayIterator($this->all);
		}
	}
}

// -------------------------------------------------------------------------
// Register the DataMapper autoloader
// -------------------------------------------------------------------------

/**
 * Autoloads object classes that are used with DataMapper.
 * Must be at end due to "implements IteratorAggregate"...
 */
spl_autoload_register('DataMapper::dm_autoload');

/* End of file datamapper.php */
/* Location: ./application/third_party/datamapper/libraries/datamapper.php */
