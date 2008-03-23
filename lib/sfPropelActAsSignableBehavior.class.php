<?php

/**
 * This file is part of the sfPropelActAsSignableBehavior package.
 * 
 * (c) 2008 Nicolas Chambrier
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
/**
 * This behavior automates the handling of "created_by" and "updated_by" columns
 *
 * @author  Nicolas Chambrier <naholyr@yahoo.fr>
 */
 
class sfPropelActAsSignableBehavior
{

	/**
	 * Is behavior enabled ?
	 *
	 * @var boolean
	 */
	protected static $_enabled = true;
	
	/**
	 * Default columns mapping
	 *
	 * @var array
	 */
	private static $default_columns = array(
		'created' => 'created_by', 
		'updated' => 'updated_by', 
		'deleted' => 'deleted_by'
	);
	
	/**
	 * Default user methods mapping
	 *
	 * @var array
	 */
	private static $default_user_methods = array(
		'id'     => 'getId', 
		'string' => '__toString'
	);
	
	/**
	 * Is behavior enabled ?
	 *
	 * @return boolean
	 */
	public static function enabled()
	{
		return self::$_enabled;
	}
	
	/**
	 * Disable behavior for the next save()
	 *
	 */
	public static function disable()
	{
		self::$_enabled = false;
	}
	
	/**
	 * Enable behavior
	 *
	 */
	public static function enable()
	{
		self::$_enabled = true;
	}
	
	/**
	 * Called before node is saved
	 *
	 * @param   BaseObject  $object
	 */
	public function preSave(BaseObject $object)
	{
		if (!self::enabled()) {
			self::enable();
			return false;
		}
		
		$user = sfContext::getInstance()->getUser();
		
		if ($object->isNew()) {
			self::setSomethingBy($object, 'created', $user);
		}
		
		if ($object->isModified()) {
			self::setSomethingBy($object, 'updated', $user);
		}
	}
	
	/**
	 * Called before node is deleted
	 *
	 * @param   BaseObject  $object
	 */
	public function preDelete(BaseObject $object)
	{
		if (!self::enabled()) {
			self::enable();
			return false;
		}
		
		$user = sfContext::getInstance()->getUser();
		
		self::setSomethingBy($object, 'deleted', $user);
	}
	
	/**
	 * Update "$what" depending on the user
	 *
	 * @param BaseObject   $object
	 * @param string       $what
	 * @param myUser       $user
	 */
	private static function setSomethingBy(BaseObject $object, $what, myUser $user)
	{
		$class = get_class($object);
		
		switch (self::getColumnType($class, $what)) {
			case null:       // Column not found : ignore
				return false;
			case 'int':      // integer : set with id
				$value = self::getUserInfo($class, $user, 'id');
				break;
			case 'string':   // string : set with string representation
				$value = self::getUserInfo($class, $user, 'string');
				break;
			default:         // other : type not supported
				throw new sfException('[sfPropelActAsSignable] column "' . $what . '" must be int or string');
		}
		
		$setter = self::forgeMethodName($object, 'set', $what);
		
		return call_user_func(array($object, $setter), $value);
	}

	/**
	 * Returns an info from user, depending on the class having registered the behavior
	 *
	 * @param string          $class             Propel model class
	 * @param myUser          $user              Current user
	 * @param string          $info              Info retrieved
	 * 
	 * @return mixed
	 */
	private static function getUserInfo($class, myUser $user, $info)
	{
		$methods = sfConfig::get('propel_behavior_sfPropelActAsSignableBehavior_' . $class . '_user_methods', self::$default_user_methods);
		$method = $methods[$info];
		
		return call_user_func(array($user, $method));
	}
	
	/**
	 * Returns the appropriate column name.
	 * 
	 * @param   string   $class                    Propel model class
	 * @param   string   $column                   Column name
	 * 
	 * @return  string   Column's name
	 */
	private static function getColumnConstant($class, $column)
	{
		$columns = sfConfig::get('propel_behavior_sfPropelActAsSignableBehavior_' . $class . '_columns', self::$default_columns);
		
		$column = $columns[$column];
		
		// Check that the column is prefixed, if not, prefix it with table name
		$table_name = constant($class . 'Peer::TABLE_NAME');
		if (substr($column, 0, strlen($table_name)+1) != $table_name . '.') {
			$column = $table_name . '.' . strtoupper($column);
		}
		
		return $column;
	}
	
	/**
	 * Returns type for one column of the given class
	 *
	 * @param string       $class                 Propel model class
	 * @param string       $column                Column name
	 * 
	 * @return string
	 */
	private static function getColumnType($class, $column)
	{
		$mapBuilderClass = $class . 'MapBuilder';
		
		$mapBuilder = new $mapBuilderClass;
		$mapBuilder->doBuild();
		$map = $mapBuilder->getDatabaseMap();
		
		$table = $map->getTable(constant($class . 'Peer::TABLE_NAME'));
		
		try {
			$column = $table->getColumn(self::getColumnConstant($class, $column));
			$type = $column->getType();
		} catch (PropelException $e) {
			$type = null;
		}
		
		return $type;
	}
		
	/**
	 * Returns getter / setter name for requested column.
	 * 
	 * @param   BaseObject  $object       Propel object
	 * @param   string      $prefix       get|set|...
	 * @param   string      $column       from|to
	 */
	private static function forgeMethodName(BaseObject $object, $prefix, $column)
	{
		$column_constant = self::getColumnConstant(get_class($object), $column);
		$method_name = $prefix . $object->getPeer()->translateFieldName($column_constant, BasePeer::TYPE_COLNAME, BasePeer::TYPE_PHPNAME);
		
		return $method_name;
	}
	
}
