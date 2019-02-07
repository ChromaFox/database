<?php namespace CF;

class Database
{
	private $db = null;
	private $queryList = array();
	private $prefixes = [];
	
	private $vendor;
	private $dbname;
	private $host;
	private $user;
	private $pass;
	private $charset;
	private $persistent;
	
	
	private function recordQuery($sql, $values, $time)
	{
		if($values != null)
		{
			if(strpos($sql, '?') !== false)
			{
				$pos = 0;
				foreach($values as $v)
				{
					$pos = strpos($sql, '?', $pos);
					if($pos !== false)
						$sql = substr_replace($sql, $v, $pos, 1);
					$pos += strlen($v);
				}
			}
			else
			{
				foreach($values as $k => $v)
					$sql = str_replace($k, $v, $sql);
			}
		}
		
		$this->queryList[] = array('time' => $time, 'sql' => $sql, 'prettytime' => round($time * 1000, 2) . 'ms');
	}
	
	public function __construct($vendor, $dbname, $host, $user, $pass, $charset = "utf8", $persistent = true)
	{
		$this->vendor = $vendor;
		$this->dbname = $dbname;
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->charset = $charset;
		if($vendor === "sqlite")
			$this->persistent = false;
		else
			$this->persistent = $persistent;
		
		$this->prefixes[''] = "";
	}
	
	public function setNamedPrefix($via, $prefix)
	{
		$this->prefixes[$via] = $prefix;
	}
	
	public function setNamedPrefixList($list)
	{
		$this->prefixes = array_merge($this->prefixes, $list);
	}
	
	public function getNamedPrefix($name)
	{
		return $this->prefixes[$name];
	}
	
	public function connect()
	{
		$start = microtime(true);
		$conStr = $this->vendor;
		if($this->vendor == "sqlite")
			$conStr .= ":".$this->dbname;
		else
			$conStr .= ":dbname=".$this->dbname.
				";host=".$this->host.
				";charset=".$this->charset;
		$this->db = new \PDO(
			$conStr,
			$this->user,
			$this->pass,
			[\PDO::ATTR_PERSISTENT => $this->persistent]);
		
		$this->pass = null;
		
		$this->recordQuery("CONNECT ? on ?", [$this->dbname, $this->host], microtime(true) - $start);
	}
	
	public function getQueries()
	{
		return $this->queryList;
	}

	public function run($sql, $values = null)
	{
		if(!$this->isConnected())
			$this->connect();
		
		$start = microtime(true);
		$err = [];
		
		$query = $this->db->prepare($sql);
		if($query !== false)
		{
			$query->execute($values);
			
			$err = $query->errorInfo();
			
			if($err[0] !=0)
				throw new \Exception($err[2]);
		}
		else
		{
			$err = $this->db->errorInfo();
			throw new \Exception($err[2]);
		}
		
		$results = $query->fetchAll();
		$query->closeCursor();
		
		$this->recordQuery($sql, $values, microtime(true) - $start);
		
		return $results;
	}
	
	public function lastInsertId()
	{
		return $this->db->lastInsertId();
	}
	
	public function isConnected()
	{
		return $this->db != null;
	}
	
	public function beginTransaction()
	{
		return $this->db->beginTransaction();
	}
	
	public function commitTransaction()
	{
		return $this->db->commit();
	}
	
	public function rollbackTransaction()
	{
		return $this->db->rollBack();
	}
	
	public function query($prefix = "")
	{
		return new \CF\Database\Query($this, $this->prefixes[$prefix]);
	}
	
	public function modelTypeMap($type)
	{
		$dbtypes = [
			'mysql' => [
				'int' => "INT UNSIGNED",
				'string' => "VARCHAR(120)",
				'text' => "TEXT",
				'list' => "VARCHAR(255)",
				'bool' => "TINYINT(1)",
				
				'primary' => " PRIMARY KEY",
				'auto' => " auto_increment",
				'null' => [false => " NOT NULL", true => " NULL"],
				'default' => " DEFAULT "
			],
			'sqlite' => [
				'int' => "INTEGER",
				'string' => "VARCHAR(120)",
				'text' => "TEXT",
				'list' => "VARCHAR(255)",
				'bool' => "TINYINT(1)",
				
				'primary' => " PRIMARY KEY",
				'auto' => " AUTOINCREMENT",
				'null' => [false => " NOT NULL", true => " NULL"],
				'default' => " DEFAULT "
			],
		];
		
		$types = $dbtypes[$this->vendor];
		
		$def = "";
		if(is_array($type))
		{
			$def = $types[$type[0]];
			if(isset($type['primary']))
				$def .= $types['primary'];
			if(isset($type['auto']))
				$def .= $types['auto'];
			if(isset($type['null']))
				$def .= $types['null'][$type['null']];
			if(isset($type['default']))
				$def .= $types['default']."'{$type['default']}'";
		}
		else
		{
			$def = $types[$type];
		}
		
		return $def;
	}
}
