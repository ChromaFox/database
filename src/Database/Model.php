<?php namespace Minifox\Database;

abstract class Model
{
	private $original = [];
	private $modified = [];
	
	public $db = null;
	
	// This must return the schema information
	abstract static function schema();
	
	// This should make sure the data is valid
	function validate() { return true; }
	
	function __construct(\Minifox\Database $db, $values = [])
	{
		$this->db = $db;
		
		$this->setValues($values);
	}
	
	private function setValues($values = [])
	{
		$info = static::schema();
		
		foreach($info['schema'] as $key => $type)
		{
			if(isset($values[$key]))
				$this->original[$key] = $values[$key];
			else
				$this->original[$key] = null;
		}
	}
	
	public function __get($name)
	{
		if(isset($this->modified[$name]))
			return $this->modified[$name];
		else if(isset($this->original[$name]))
			return $this->original[$name];
		else
			return;
	}
	
	public function __set($name, $value)
	{
		if(!isset($this->original[$name]) || $this->original[$name] != $value)
			$this->modified[$name] = $value;
	}
	
	public function __isset($name)
	{
		return isset($this->modified[$name]) || isset($this->original[$name]);
	}
	
	public function __unset($name)
	{
		if(isset($this->modified[$name]))
			unset($this->modified[$name]);
		unset($this->original[$name]);
	}
	
	static function find(\Minifox\Database $db, $which, $extra = [])
	{
		$info = static::schema();
		
		if(!isset($info['proxy']))
			$info['proxy'] = '';
		
		$query = $db->query($info['proxy'])->select($info['table']);
		
		if(is_array($which))
			$query->where($which);
		else
		{
			$idCol = Model::getIDColumn($info['schema']);
			
			if($which == 'first')
				$query->orderBy([$idCol => "ASC"])->limit(1);
			else if($which == 'last')
				$query->orderBy([$idCol => "DESC"])->limit(1);
			else if($which != 'all')
				$query->where([$idCol => $which]);
		}
		
		if(isset($extra['order']))
			$query->orderBy($extra['order']);
		
		if(isset($extra['limit']))
			$query->limit($extra['limit']);
		
		$results = $query->run();
		
		if(empty($results))
			return null;
		else
		{
			$out = [];
			foreach($results as $result)
				$out[] = new static($db, static::parseResult($result));
			
			return $out;
		}
	}
	
	static function findOne(\Minifox\Database $db, $which, $extra = [])
	{
		if(!isset($extra['limit']))
			$extra['limit'] = 1;
		
		$results = static::find($db, $which, $extra);
		
		if(!empty($results))
			return end($results);
		else
			return null;
	}
	
	static function count(\Minifox\Database $db, $which = [])
	{
		$info = static::schema();
		
		if(!isset($info['proxy']))
			$info['proxy'] = '';
		
		return $db->query($info['proxy'])->count($info['table'])->where($which)->run();
	}
	
	function save()
	{
		if(empty($this->modified))
			return false;
		
		if(!$this->validate())
			return false;
		
		$info = static::schema();
		
		if(!isset($info['proxy']))
			$info['proxy'] = '';
		
		$idCol = Model::getIDColumn($info['schema']);
		
		$values = [];
		
		foreach($info['schema'] as $col => $type)
		{
			if(!isset($this->modified[$col]))
				continue;
			
			if($type == 'list' && is_array($this->modified[$col]))
				$values[$col] = implode(",", $this->modified[$col]);
			else if($type == 'bool')
				$values[$col] = $this->modified[$col]? 1 : 0;
			else
				$values[$col] = $this->modified[$col];
		}
		
		if(empty($values))
			return false;
		
		if($this->original[$idCol] == null)
			$this->original[$idCol] = $this->db->query($info['proxy'])->insert($info['table'])->values($values)->run();
		else
			$this->db->query($info['proxy'])->update($info['table'])->values($values)->where([$idCol => $this->original[$idCol]])->run();
		
		return true;
	}
	
	function delete()
	{
		$info = static::schema();
		
		if(!isset($info['proxy']))
			$info['proxy'] = '';
		
		$idCol = Model::getIDColumn($info['schema']);
		if($this->original[$idCol] === null && $this->modified[$idCol] === null)
			return;
		
		$this->db->query($info['proxy'])->delete($info['table'])->where([$idCol => $this->{$idCol}])->run();
	}
	
	static function install(\Minifox\Database $db)
	{
		$info = static::schema();
		
		if(!isset($info['proxy']))
			$info['proxy'] = '';
		
		$columns = [];
		
		foreach($info['schema'] as $column => $type)
			$columns[$column] = $db->modelTypeMap($type);
		
		$db->query($info['proxy'])->createTable($info['table'], $columns)->run();
	}
	
	private static function getIDColumn($schema)
	{
		foreach($schema as $col => $type)
		{
			if(is_array($type) && isset($type['primary']))
				return $col;
		}
		
		return null;
	}
	
	private static function parseResult($result)
	{
		$info = static::schema();
		
		foreach($info['schema'] as $key => $type)
		{
			if(isset($result[$key]) && $type == 'list')
			{
				if(empty($result[$key]))
					$result[$key] = [];
				else
					$result[$key] = explode(",", $result[$key]);
			}
			else if(isset($result[$key]) && $type == 'bool')
			{
				$result[$key] = $result[$key] != 0;
			}
		}
		
		return $result;
	}
}