<?php namespace CF\Database;

class Query implements \IteratorAggregate
{
	private $db = null;
	
	private $action = null;
	private $table = null;
	private $prefix = "";
	private $columns = null;
	
	private $join = null;
	
	private $queryValues = null;
	
	private $whereValues = null;
	private $orderByValues = null;
	private $limitValues = null;
	
	private $results = null;
	
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
		
		$query = call_user_func([$this, 'format'.$this->action]);
		
		if($this->whereValues)
		{
			$where = Query::formatWhere($this->whereValues);
			$query['sql'] .= " WHERE " . $where['sql'];
			$query['values'] = array_merge($query['values'], $where['values']);
		}
		
		if($this->orderByValues)
			$query['sql'] .= " ORDER BY " . Query::formatOrderBy($this->orderByValues);
		
		if($this->limitValues)
			$query['sql'] .= " LIMIT " . Query::formatLimit($this->limitValues);
		
		$result = $this->db->run($query['sql'], $query['values']);
		
		if($this->action == "Insert")
			$result = $this->db->lastInsertId();
		else if($this->action == "Count")
		{
			if(!empty($result))
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
		$this->columns = "COUNT(*)";
		
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
	
	public function leftJoin($table, $on)
	{
		if(!is_array($this->join))
			$this->join = [];
		
		if(!isset($this->join['LEFT']))
			$this->join['LEFT'] = [];
		
		$this->join['LEFT'][$table] = $on;
		
		return $this;
	}
	
	public function values($values)
	{
		if(is_array($this->whereValues))
			$this->queryValues = array_merge_recursive($this->queryValues, $values);
		else
			$this->queryValues = $values;
		$this->queryValues = $values;
		
		return $this;
	}
	
	public function where($values)
	{
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
	
	private function formatSelect()
	{
		if(is_array($this->columns))
			$formattedCols = implode(", ", $this->columns);
		else
			$formattedCols = $this->columns;
		
		if(is_array($this->join))
		{
			// Join-y fun!
			$sql = "SELECT {$formattedCols} FROM {$this->prefix}{$this->table}";
			if(isset($this->join["LEFT"]))
			{
				$left = $this->join["LEFT"];
				foreach($left as $leftTable => $on)
					$sql .= " LEFT JOIN {$this->prefix}{$leftTable} ON {$on}";
			}
		}
		else
			$sql = "SELECT {$formattedCols} FROM {$this->prefix}{$this->table}";
		
		return ['sql' => $sql, 'values' => []];
	}
	
	private function formatUpdate()
	{
		$sql = "UPDATE {$this->prefix}{$this->table} SET ";
		$values = [];
		
		foreach($this->queryValues as $col => $val)
		{
			if(is_array($val) && isset($val['raw']))
				$columns[] = "{$col} = {$val['raw']}";
			else
			{
				$columns[] = "{$col} = ?";
				$values[] = $val;
			}
		}
		
		$sql .= implode(", ", $columns);
		
		return ['sql' => $sql, 'values' => $values];
	}
	
	private function formatInsert()
	{
		$columns = implode(', ', array_keys($this->queryValues));
		$values = array_values($this->queryValues);
		
		$placeholders = implode(', ', array_fill(0, count($this->queryValues), '?'));
		
		$sql = "INSERT INTO {$this->prefix}{$this->table} ({$columns}) VALUES ({$placeholders})";
		
		return ['sql' => $sql, 'values' => $values];
	}
	
	private function formatDelete()
	{
		$sql = "DELETE FROM {$this->prefix}{$this->table}";
		
		return ['sql' => $sql, 'values' => []];
	}
	
	private function formatCount()
	{
		return $this->formatSelect();
	}
	
	public function formatCreateTable()
	{
		$coldefs = [];
		
		foreach($this->columns as $col => $def)
		{
			if(is_string($col))
				$coldefs[] = "{$col} {$def}";
			else
				$coldefs[] = $def;
		}
		
		$coldefs = implode(", ", $coldefs);
		$sql = "CREATE TABLE {$this->prefix}{$this->table} ({$coldefs})";
		if(!empty($this->queryValues))
			$sql .= " ENGINE = {$this->queryValues}";
		
		return ['sql' => $sql, 'values' => []];
	}
	
	public function formatDropTable()
	{
		$sql = "DROP TABLE IF EXISTS {$this->prefix}{$this->table}";
		return ['sql' => $sql, 'values' => []];
	}
	
	private static function formatWhere($where, $combine = "AND")
	{
		$sql = "";
		
		$values = [];
		
		$clauses = [];
		
		foreach($where as $col => $value)
		{
			// remove comment/ID/uniquifier from column
			$commentPos = strpos($col, '#');
			if($commentPos !== false)
				$col = substr($col, 0, $commentPos);
			
			if(in_array($col, ["AND", "OR"]))
			{
				// Sub-where
				$subWhere = Query::formatWhere($value, $col);
				
				$clauses[] = "({$subWhere['sql']})";
				$values = array_merge($values, $subWhere['values']);
			}
			else
			{
				if(is_array($value))
				{
					$value = array_unique($value);
					if(count($value) == 1)
						$value = $value[0];
				}
				
				$colVals = explode(' ', $col);
				$col = $colVals[0];
				if(isset($colVals[1]))
					$op = $colVals[1];
				else if(is_array($value))
					$op = "in";
				else
					$op = "=";
				
				if(is_array($value))
				{
					$values = array_merge($values, $value);
					$clauses[] = "{$col} {$op} (" .  implode(',', array_fill(0, count($value), '?')) . ")";
				}
				else
				{
					$values[] = $value;
					$clauses[] = "{$col} {$op} ?";
				}
			}
		}
		
		$sql = implode(" {$combine} ", $clauses);
		
		return ['sql' => $sql, 'values' => $values];
	}
	
	private static function formatOrderBy($columns)
	{
		$orders = [];
		foreach($columns as $col => $dir)
			$orders[] = "{$col} {$dir}";
		
		return implode(", ", $orders);
	}
	
	private static function formatLimit($limit)
	{
		if(is_array($limit))
			return "{$limit[0]}, {$limit[1]}";
		else
			return "{$limit}";
	}
}