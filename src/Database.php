<?php namespace Minifox;

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
	
	public function connect()
	{
		$start = microtime(true);
		$this->db = new \PDO(
			$this->vendor.
			":dbname=".$this->dbname.
			";host=".$this->host.
			";charset=".$this->charset,
			$this->user,
			$this->pass,
			[\PDO::ATTR_PERSISTENT => $persistent]);
		
		$this->pass = null;
		
		$this->recordQuery("CONNECT", [], microtime(true) - $start);
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
		
		$query = $this->db->prepare($sql);
		$query->execute($values);
		$err = $query->errorInfo();
		if($err[0] != 0)
		{
			print_r($err);
			print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
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
		return new \Minifox\Database\Query($this, $this->prefixes[$prefix]);
	}
}
