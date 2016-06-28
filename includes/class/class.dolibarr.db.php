<?php
/*
 Copyright (C) 2003-2016 Alexis Algoud <azriel68@gmail.com>
 Copyright (C) 2013-2015 ATM Consulting <support@atm-consulting.fr>

 This program and all files within this directory and sub directory
 is free software: you can redistribute it and/or modify it under 
 the terms of the GNU General Public License as published by the 
 Free Software Foundation, either version 3 of the License, or any 
 later version.
 
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


class TDolibarrDb{
/**
* Construteur
**/
function __construct($db_type = '', $connexionString='', $DB_USER='', $DB_PASS='', $DB_OPTIONS=array()) {
		
	$this -> db = null;
	$this -> rs = null;            //RecordSet
	$this -> currentLine = null;   //ligne courante
	$this -> query = '';			//requete actuelle
	$this -> type = $db_type;
	$this -> debug = false;
	$this -> debugError = false;
	$this -> error = '';
    $this -> stopOnInsertOrUpdateError = true;

	$this -> insertMode == 'INSERT';

	global $conf, $db;
	
	$this->db = DoliDB &$db;
	
	$this -> currentLine = array();

	$this->debugError = defined('SHOW_LOG_DB_ERROR') || (ini_get('display_errors')=='On');
	
	if (isset($_REQUEST['DEBUG']) || defined('DB_SHOW_ALL_QUERY') ) {
		print "SQL DEBUG : 	<br>";
		$this -> debug = true;
	} 

}

function beginTransaction() {
	return $this->db->begin();
}
function commit() {
/*
 * Valide une transaction débuté par beginTransaction()
 * Sinon en AutoCommit
 */
	return $this->db->commit();
}
function rollBack() {
/*
 * Annule une transaction débuté par beginTransaction()
 * Sinon en AutoCommitis
 */

	return $this->db->rollback();
}

function Get_DbType() {
	return $this->db->type;
}

function num_rows(&$rs) {
	$this->db->num_rows($rs);
}

function Get_Recordcount() {
	return $this->num_rows($this->rs) ;
}
function order($sortfield=null,$sortorder=null) {
	return $this->db->order($sortfield,$sortorder);
}
function showTrace() {
        print '<pre>';
        $trace=debug_backtrace();       
      
        $log=''; 
        foreach($trace as $row) {
                if((!empty($row['class']) && $row['class']=='TDolibarrDb') 
                        || (!empty($row['function']) && $row['function']==__FUNCTION__)
                        || (!empty($row['function']) && $row['function']=='call_user_func')) continue;
                        
                $log='<strong>L. '.$row['line'].'</strong>';
                if(!empty($row['class']))$log.=' '.$row['class'];
                $log.=' <span style="color:green">'.$row['function'].'()</span> dans <span style="color:blue">'.$row['file'].'</span>';
                
                print $log.'<br>';
        }
        
        //debug_print_backtrace();
        print '</pre><hr>';
    
    
}

private function Error($message, $showTrace=true) {
	$this -> error = $message;
	
	if($this->debug ||  $this->debugError) {
		//print $this->connexionString.'<br/>';
		print "<strong>".$message."</strong>";
		
		   if($showTrace) {
                $this->showTrace();
           }
		
	}	
	else {
		$trace=debug_backtrace();       
	      
        $log=''; 
        foreach($trace as $row) {
                if((!empty($row['class']) && $row['class']=='TDolibarrDb') 
                        || (!empty($row['function']) && $row['function']==__FUNCTION__)
                        || (!empty($row['function']) && $row['function']=='call_user_func')) continue;
                        
                $log.=' < L. '.$row['line'];
                if(!empty($row['class']))$log.=' '.$row['class'];
                $log.=$row['function'].'() dans '.$row['file'];
				//print $log;
        }
		
			
		error_log($message.$log);
	}
		
}

function query($sql) {
	return $this->Execute($sql);
}

function Execute ($sql){
        $mt_start = microtime(true)*1000;
		 
        $this->query = $sql;
		
		if($this->debug) {
				$this->Error('Debug requête : '.$this->query);
						
		}
		
		$this->rs = $this->db->query( $this->query );	
		
        $mt_end = microtime(true)*1000;
		
		if ($this->db->errorCode) {
			if($this->debug) $this->Error("Dolibarr DB ErrorExecute : " . print_r($this ->db->errno(),true).' '.$this->query);
			//return(mysql_errno());
		}
		
		if(defined('LOG_DB_SLOW_QUERY')) {
                $diff = $mt_end - $mt_start;
                if($diff >= LOG_DB_SLOW_QUERY) {
                        $this->Error('Dolibarr DB SlowQuery('.round($diff/1000,2).' secondes) : '.$this -> query)    ;
                        
                }
                
        }
		
		return $this->rs;
}
function quote($s) {
	
	if(is_string($s)) return "'".addslashes($s)."'";
	else return $s;
	
	//return $this->db->quote($s);
}
function close() {
	$this->db->close();
}
function dbupdate($table,$value,$key){
	
	   if($this -> insertMode =='REPLACE') {
			return $this->dbinsert($table,$value);
	   }
	   
        $fmtsql = "UPDATE `$table` SET %s WHERE %s";
        foreach ($value as $k => $v) {
                if(is_string($v)) $v=stripslashes($v);
			
                if (is_array($key)){
                        $i=array_search($k , $key );
                        if ( $i !== FALSE) {
                                $where[] = "`".$key[$i]."`=" . $this->quote( $v ) ;
                            continue;
                        }
                } else {
                        if ( $k == $key) {
                                $where[] = "`$k`=" .$this->quote( $v ) ;
                                continue;
                        }
                }
		
		if(is_null($v)) $val = 'NULL';
		else if(is_int($v) || is_double($v)) $val=$v;
                else $val = $this->quote( $v );

                $tmp[] = "`$k`=$val";
        }
        $this->query = sprintf( $fmtsql, implode( ",", $tmp ) , implode(" AND ",$where) );
		
		$res = $this->db->exec( $this->query );
		
		if($res===false) {
		    $this->Error("Dolibarr DB ErrorUpdate : " . print_r($this ->db->lasterror(),true)." ".$this->query);
            if($this->stopOnInsertOrUpdateError) {
                            
                echo $this->error.'<hr />';
                $this->showTrace();
                exit('Dolibarr stop execution for caution'); 
                
            }
        }
		
		if($this->debug)$this->Error("Mise à jour (".(int)$res." ligne(s)) ".$this->query);
		
        return $res;
}
function dbinsert($table,$value){
        	
		if($this -> insertMode =='REPLACE') {
			$fmtsql = 'REPLACE INTO `'.$table.'` ( %s ) values( %s ) ';
		}
		else{
			$fmtsql = 'INSERT INTO `'.$table.'` ( %s ) values( %s ) ';	
		}
        
		
		
        foreach ($value as $k => $v) {
                
                $fields[] = $k;
                if(is_null($v)){
                	$values[] = 'NULL';
				}else{
					$v=stripslashes($v);
					$values[] =$this->quote( $v );
				}
        }
        $this->query = sprintf( $fmtsql, implode( ",", $fields ) ,  implode( ",", $values ) );

        if (!$this->db->exec( $this->query )) {
        		$this->Error("Dolibarr DB ErrorInsert : ". print_r($this ->db->lasterror(),true).'<br />'.$this->query);
            
                if($this->stopOnInsertOrUpdateError) {
                        
                    echo $this->error.'<hr />';
                    $this->showTrace();
                    exit('Dolibarr stop execution for caution'); 
                    
                }
            
                return false;
        }
		if($this->debug)$this->Error("Insertion ".$this->query);
		
        return true;
}

function dbdelete($table,$value,$key){
    if (is_array($value)){
          foreach ($value as $k => $v) {
           if (is_array($key)){
              $i=array_search($k , $key );
              if ( $i !== FALSE) {
                 $where[] = "$k=" . $this->quote( $v ) ;
                 continue;
                 }
           }
           else {
              $v=stripslashes($v);
              if( $k == $key ) {
                 $where[] = "$key=" . $this->quote( $v ) ;
                 continue;
                 }
              }
           }
    } else {
        $value=stripslashes($value);
                $where[] = "$key=" . $this->quote( $value );
    }

    $tmp=implode(" AND ",$where);
	
	$this->query = sprintf( 'DELETE FROM '.$table.' WHERE '.$tmp);


    return $this->db->exec( $this->query );
}

function ExecuteAsArray($sql) {
	
	$this->Execute($sql);
	return $this->Get_All();
		
	
}

function Get_All() {
			
	if ($this->rs === false) return array();
	else {
		$Tab = array();
		
		while($obj = $this->db->Get_line()) $Tab[] = $obj;
		
		return $Tab;
	}	
	
	
}
function Get_line(){
	if(!is_object($this->rs)){
		$this->Error("Dolibarr DB ErrorGetLine : " . print_r($this ->db->lasterror(),true).' '.$this->query);
			
		return false;
	}
	
	$this->currentLine=$this->rs->fetch_object();
	
	return $this->currentLine;
}

function Get_lineHeader(){
   $ret=array();
   
   if (!empty($this->currentLine)){
      foreach ($this->currentLine as $key=>$val){
         	$ret[]=$key;
      }
	}
   return $ret;
}


function Get_field($pField){
		
		if(isset($this->currentLine->{$pField})) return $this->currentLine->{$pField};
		else return false;

}

}
