<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Data Mapper ORM Class
 *
 * ORM unit tests : A table model
 *
 * @license     MIT License
 * @package     DataMapper ORM
 * @category    DataMapper ORM
 * @author      Harro "WanWizard" Verton
 * @link        http://datamapper.wanwizard.eu
 * @version     2.0.0
 */

class DmtestA extends DataMapper
{
	// define the model name for this model
	protected $model = 'dmtesta';

	// define the tablename for this model
	protected $table = 'dmtests_A';

	// define the primary key(s) for this model
	protected $primary_key = array('id' => 'integer');

	// insert related models that this model can have just one of
	protected $has_one = array(
		'dmtestd' => array(
			'related_key' => array('fk_id_A'),
		),
		'selfref' => array(
			'related_class' => 'dmtesta',
			'related_key' => array('fk_id_A'),
		)
	);

	// insert related models that this model can have more than one of
	protected $has_many = array(
		'dmtestb' => array(
			'related_key' => array('fk_id_A'),
			'join_table' => 'dmtests_C',
		)
	);

	// insert models that this model belongs to
	protected $belongs_to = array(
		'dmtesta' => array(
			'related_model' => 'selfref',
		),
	);

	// define validation rules for each column
	protected $validation = array(
		'id' => array(
			'get_rules' => array(
				'intval',
			),
		),
		'fk_id_A' => array(
			'get_rules' => array(
				'intval',
			),
		),
	);

	// define the default ordering for this model
	protected $default_order_by = array('id' => 'asc');

	// -------------------------------------------------------------------------
	// Dynamic class definition
	// -------------------------------------------------------------------------

	/*
	 * Constructor
	 *
	 * custom model initialisation. Do NOT forget to call the parent
	 * constructor, otherwise the model class will not be initialized!
	 *
	 * Note that if you don't need a constructor here, remove this, as it
	 * only introduces additional overhead.
	 */
	public function __construct($param = NULL, $name = NULL)
	{
		// call the parent constructor to initialize the model
		parent::__construct($param, $name);
	}

	// --------------------------------------------------------------------

	/*
	 * Post Model Initialisation
	 *
	 * add your own custom initialisation code to the model. this method is called
	 * after the configuration for this model has been processed
	 *
	 * @param	boolean	$from_cache	if true the current config came from cache
	 */
	protected function post_model_init($from_cache = FALSE)
	{
	}
}

/* End of file parent.php */
/* Location: ./application/models/parent.php */
