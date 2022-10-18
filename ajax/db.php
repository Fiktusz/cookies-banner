<?php

unset( $COOKIES_DB );
global $COOKIES_DB;

$COOKIES_DB = new mysql();

class mysql
{
	private $dbh;

	public function __construct()
	{
		global $COOKIES_CFG;

		try {
			$this->dbh = new PDO( 'mysql:dbname='. $COOKIES_CFG['DB_NAME'] .';host='. $COOKIES_CFG['DB_HOST'] .';port='. $COOKIES_CFG['DB_PORT'], $COOKIES_CFG['DB_USER'], $COOKIES_CFG['DB_PASS'] );
		   	$this->dbh->query("SET NAMES 'utf8'");
		} catch (PDOException $e) {
		    echo 'MYSQL Connection failed: ' .$this->dbErrMess( $e->getCode() );
		    exit();
		}
	}

	public function close()
	{
		$this->dbh = NULL;
	}

	public function dbErrMess( $code )
	{
		switch( $code )
		{
			case 2002:
				return "[" .$code. "] neznámy host '" .$this->dbHost. "'";
			case 1044:
				return "[" .$code. "] neznámy user '" .$this->dbUser. "'";
			case 1045:
				return "[" .$code. "] zlé heslo";
			case 1049:
				return "[" .$code. "] zadaná databáza neexistuje - '" .$this->dbName. "'";
			default:
				return "[" .$code. "] chyba ";
		}
	}
	
	public function nums( $query, $params = array() )
	{
		$qNum = $this->dbh->prepare( $query );
		$qNum->execute( $params );
		$return = $qNum->rowCount();
		
		return $return;
	}

	public function allArrays( $query, $params = array(), $limit = array() )
	{
		$q = $this->dbh->prepare( $query );
		if( $q ){
			$q->execute( $params );
			$array = $q->fetchAll( PDO::FETCH_ASSOC );
			
			if( isset( $limit[0] ) ){
				$array2 = array();
				foreach( $array as $key => $value ){
					if( $key >= $limit[0] && $key <= $limit[1] )
						$array2[$key] = $value;
				}
				$array = $array2;
			}
			return $array;
		}
		
		return FALSE;
	}
	
	public function singleArray( $query, $params = array() )
	{
		$q = $this->dbh->prepare( $query );
		if( $q ){
			$q->execute( $params );
			$array = $q->fetchAll( PDO::FETCH_ASSOC );
			
			if( isset( $array[0] ) )
				return $array[0];
			
			return array();
		}
		
		return FALSE;
	}

	public function allColumns( $tableName )
	{
		$q = $this->dbh->query( "SHOW COLUMNS FROM " . $tableName );
		if( $q ){
			$array = $q->fetchAll(PDO::FETCH_ASSOC);
			
			return $array;
		}
		
		return FALSE;
	}
	
	public function updateData( $query, $params = array() )
	{
		$q = $this->dbh->prepare( $query );
		$r = $q->execute( $params );
		
		if( $r )
			return TRUE;
		else
			return FALSE;
	}

	public function insertData( $query, $params = array(), $getID = false )
	{
		
		$q = $this->dbh->prepare( $query );
		$r = $q->execute( $params );
		
		if( $r ){
			if( $getID ) return $this->dbh->lastInsertId();
			return TRUE;
		} else
			return FALSE;
	}

	public function deleteData( $query, $params = array() )
	{
		
		$q = $this->dbh->prepare( $query );
		$r = $q->execute( $params );
		
		if( $r )
			return TRUE;
		else
			return FALSE;
	}

	public function createSQL( $data, $type )
	{
		$data['columns'] = ( isset( $data['columns'] ) ) ? $data['columns'] : array();
		$data['join'] = ( isset( $data['join'] ) ) ? $data['join'] : array();
		$data['where']['columns'] = ( isset( $data['where']['columns'] ) ) ? $data['where']['columns'] : array();
		$data['group'] = ( isset( $data['group'] ) ) ? $data['group'] : array();
		$data['order']['columns'] = ( isset( $data['order']['columns'] ) ) ? $data['order']['columns'] : array();
		$data['order']['sort'] = ( isset( $data['order']['sort'] ) ) ? $data['order']['sort'] : array();
		$data['limit'] = ( isset( $data['limit'] ) ) ? $data['limit'] : '';
		
		$data['update']['columns'] = ( isset( $data['update']['columns'] ) ) ? $data['update']['columns'] : array();

		if( $type == 'select' ) :
		
			$sql = "SELECT";
			
			foreach( $data['columns'] as $key => $value ){
				if(!$key) $sql .= ' ';
				if($key) $sql .= ',';
				$sql .= $value;
			}
			
			$sql .= ' FROM '. $data['table'];
			
			foreach( $data['join'] as $key => $value ){
				$sql .= ' '. $value;
			}
			
			foreach( $data['where']['columns'] as $key => $value ){
				if(!$key) $sql .= ' WHERE';
				$sql .= ' '. $value;
			}
			
			foreach( $data['group'] as $key => $value ){
				if(!$key) $sql .= ' GROUP BY';
				if($key) $sql .= ',';
				$value = explode('AS', $value);
				$sql .= ' '. trim( $value[0] );
			}
			
			foreach( $data['order']['columns'] as $key => $value ){
				if(!$key) $sql .= ' ORDER BY';
				if($key) $sql .= ',';
				$sql .= ' '. $value .' '. $data['order']['sort'][$key];
			}

			if( !empty( $data['limit'] ) ) $sql .= ' LIMIT '. $data['limit'];
		
		endif;

		if( $type == 'update' ) :

			$sql = "UPDATE ". $data['table'];

			foreach( $data['update']['columns'] as $key => $value ){
				if(!$key) $sql .= ' SET ';
				if($key) $sql .= ',';
				$sql .= $value .'=?';
			}

			foreach( $data['where']['columns'] as $key => $value ){
				if(!$key) $sql .= ' WHERE';
				$sql .= ' '. $value;
			}

		endif;
		
		return $sql;
	}
	
	public function debug( $sql, $parrams )
	{
		$offset = 0;
	    $key = 0;
	    while (($pos = strpos($sql, '?', $offset)) !== FALSE) {
	    	$sql = substr_replace($sql, "'" . $parrams[$key] . "'", $pos, 1 );
	        $offset = $pos + 1;
	        $key++;
	    }

		return $sql;
	}
	
	public function getAutoIncrement( $table )
	{
		$sql = "SELECT MAX(`AUTO_INCREMENT`) AS 'AUTO_INCREMENT' FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '". $table ."'";
		$autoIncrement = $this->allArrays( $sql );
		
		if( isset( $autoIncrement[0]['AUTO_INCREMENT'] ) )
			return $autoIncrement[0]['AUTO_INCREMENT'];
		else 
			return FALSE;
	}
}