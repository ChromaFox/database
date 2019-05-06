<?php namespace CF\Database;

class Query implements \IteratorAggregate
{
	private $db = null;
	
	public $action = null;
	public $table = null;
	public $prefix = "";
	public $columns = null;
	
	public $join = null;
	
	public $queryValues = null;
	
	public $whereValues = null;
	public $orderByValues = null;
	public $limitValues = null;
	public $groupColumn = null;
	
	public $results = null;
	
	public function __construct(\CF\Database $db, $tablePrefix = "")
	{
		$this->db = $db;
		$this->prefix = $tablePrefix;
	}
	
	public function getIterator()
	{
		if($this->results == null)
			$this->run();
		
		return new \ArrayIterator($this->results);
	}
	
	public function run()
	{
		$result = null;
		
		$query = $this->db->driver()->formatQuery($this);
		
		$result = $this->db->run($query['sql'], $query['values']);
		
		if($this->action == "Insert")
			$result = $this->db->lastInsertId();
		else if($this->action == "Count")
		{
			if($this->groupColumn)
			{
				$out = [];
				foreach($result as $i)
					$out[$i[$this->groupColumn]] = $i[0];
				
				$result = $out;
			}
			else if(!empty($result))
				$result = $result[0][0];
			else
				$result = 0;
		}
		
		$this->results = $result;
		return $result;
	}
	
	public function select($table, $columns = "*")
	{
		$this->action = "Select";
		$this->table = $table;
		if(!is_array($columns))
			$columns = array_map('trim', explode(",", $columns));
		
		$this->columns = $columns;
		
		return $this;
	}
	
	public function update($table)
	{
		$this->action = "Update";
		$this->table = $table;
		
		return $this;
	}
	
	public function insert($table)
	{
		$this->action = "Insert";
		$this->table = $table;
		
		return $this;
	}
	
	public function delete($table)
	{
		$this->action = "Delete";
		$this->table = $table;
		
		return $this;
	}
	
	public function count($table)
	{
		$this->action = "Count";
		$this->table = $table;
		$this->columns = ["COUNT(*)"];
		
		return $this;
	}
	
	public function createTable($table, $columns, $engine = "")
	{
		$this->action = "CreateTable";
		$this->table = $table;
		$this->columns = $columns;
		$this->queryValues = $engine;
		
		return $this;
	}
	
	public function dropTable($table)
	{
		$this->action = "DropTable";
		$this->table = $table;
		
		return $this;
	}
	
	public function leftJoin($table, $as, $on)
	{
		if(!is_array($this->join))
			$this->join = [];
		
		if(!isset($this->join['LEFT']))
			$this->join['LEFT'] = [];
		
		$this->join['LEFT'][$table . " AS " . $as] = $this->prefix . $this->table . "." . $on;
		
		$columns = [];
		foreach($this->columns as $col)
			$columns[] = (strpos($col, ".") === false)? $this->prefix . $this->table . "." . $col : $col;
		$this->columns = $columns;
		
		if(!empty($this->whereValues))
			$this->whereValues = $this->rebuildWhere($this->whereValues);
		
		return $this;
	}
	
	public function values($values)
	{
		if(is_array($this->queryValues))
			$this->queryValues = array_merge_recursive($this->queryValues, $values);
		else
			$this->queryValues = $values;
		
		return $this;
	}
	
	public function where($values)
	{
		if(!empty($this->join))
			$values = $this->rebuildWhere($values);
		
		if(is_array($this->whereValues))
			$this->whereValues = array_merge_recursive($this->whereValues, $values);
		else
			$this->whereValues = $values;
		
		return $this;
	}
	
	public function andWhere($values)
	{
		return $this->where($values);
	}
	
	public function orWhere($values)
	{
		$this->whereValues = ['OR' => array_merge_recursive($this->whereValues, $values)];
		
		return $this;
	}
	
	public function orderBy($values)
	{
		if(is_array($this->orderByValues))
			$this->orderByValues = array_merge($this->orderByValues, $values);
		else
			$this->orderByValues = $values;
		
		return $this;
	}
	
	public function groupBy($column)
	{
		$this->groupColumn = $column;
		if($column)
			$this->columns[] = $column;
		return $this;
	}
	
	public function limit($count, $start = null)
	{
		if($start !== null)
			$this->limitValues = [$count, $start];
		else
			$this->limitValues = $count;
		
		return $this;
	}
	
	public function page($page, $pageSize)
	{
		$pageStart = ($page - 1) * $pageSize;
		if($pageSize > 0 && $pageStart >= 0)
			$this->limit($pageSize, $pageStart);
		
		return $this;
	}
	
	private function rebuildWhere($values)
	{
		$result = [];
		foreach($values as $col => $val)
		{
			if(!is_numeric($col) && ($col != "AND") && ($col != "OR") && (strpos($col, ".") === false))
			{
				if(is_array($val))
					$val = $this->rebuildWhere($val);
				
				$result["{$this->prefix}{$this->table}.{$col}"] = $val;
			}
			else
				$result[$col] = $val;
		}
		
		return $result;
	}
}