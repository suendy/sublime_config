<?php

/********************************************************************************
 * @author jmccoskery
 * PHP class for running SQL Queries on remote MySQL Databases over an SSH connection
 * requires openssl and SSH2 PHP modules to be installed on server
 *
 * @usage:
 * $mysql = new SSHMysql(...server creds...);
 * $result = $mysql->query('select * from passport.auth_user limit 1');
 ********************************************************************************/
class SSHMysql
{
	
	// variable definition(s)
	private $server;
	
	
	/********************************************************************************
	 * @method __construct
	 * @param $server_sshipaddress   ssh登陆地址
	 * @param $server_sshport	   	 ssh端口
	 * @param $server_sshusername	 ssh用户名
	 * @param $server_sshpassword	 ssh密码
	 * @param $server_mysqlipaddress 数据库地址
	 * @param $server_mysqlusername  数据库用户名
	 * @param $server_mysqlpassword	 数据库密码
	 * @param $server_mysqlport		 数据库端口
	 * 
	 * class construct, takes server credentials as parameters for use in subsequent
	 * queries
	 ********************************************************************************/
	function __construct( $server_sshipaddress, $server_sshport, $server_sshusername, $server_sshpassword, $server_mysqlipaddress, $server_mysqlusername, $server_mysqlpassword, $server_mysqlport )
	{
		$this->_server = array();
		$this->_server['sshipaddress'] 		= $server_sshipaddress;
		$this->_server['sshport'] 			= $server_sshport;
		$this->_server['sshusername'] 		= $server_sshusername;
		$this->_server['sshpassword'] 		= $server_sshpassword;
		$this->_server['mysqlipaddress'] 	= $server_mysqlipaddress;
		$this->_server['mysqlusername'] 	= $server_mysqlusername;
		$this->_server['mysqlpassword'] 	= $server_mysqlpassword;
		$this->_server['mysqlport'] 		= $server_mysqlport;
	}
	
	/********************************************************************************
	 * @method query
	 * @param $sql
	 * @return stdObject
	 *
	 * executes the query on the mysql server, parses it and returns the results or
	 * error details
	 ********************************************************************************/
	public function query($sql)
	{
		if (function_exists("ssh2_connect")	)
		{
			$connection = ssh2_connect($this->_server['sshipaddress'], $this->_server['sshport']);
			
			if (ssh2_auth_password($connection, $this->_server['sshusername'], $this->_server['sshpassword'])) {
				
				$ssh_query ='ssh -L 3306:'.$this->_server['sshipaddress'].':'.$this->_server['mysqlport'].'; echo "' . str_replace( '"', '\'', stripslashes( $sql ) ) . '" | mysql -u '.$this->_server['mysqlusername'].' -h '.$this->_server['mysqlipaddress'].' --password='.$this->_server['mysqlpassword'] .' --default-charact=utf8';
				
				// execute the query over a secure connection //
				$result = ssh2_exec($connection, $ssh_query);
				
				$error_result = ssh2_fetch_stream($result, SSH2_STREAM_STDERR);

				stream_set_blocking($result, true);
				stream_set_blocking($error_result, true);
				
				$arr_1 = explode( "\n", stream_get_contents($result) );
				
				$keys = explode( "\t", $arr_1[0] );  // get the column names
				$results = array();

				for($i=1;$i< ( sizeof($arr_1) -1 );$i++) // parse the results
				{
					$values = explode( "\t", $arr_1[$i] );
					$return = new stdClass;
					$index = 0;
					foreach( $values as $v )
					{
						$return->{$keys[$index]} = $v;
						$index++;
					}
					$results[] = $return;
				}

				if(sizeof($results) > 0)
				{
					return $results;
				} else {
					return (object)[];
				}
				
				
				// if(sizeof($results) > 0)
				// {
				// 	return array('status'=>'success','msg'=>'DB Query was successful.', 'dataset'=>$results, 'type'=>'ssh');
				// } else {
				// 	return array('status'=>'error', 'msg'=>'There is an error in your SQL statement, or your sql returned no results', 'errorset'=>stream_get_contents($error_result), 'dataset'=>array(), 'type'=>'ssh');
				// }

				fclose($result);
    			if ( function_exists ( 'ssh2_disconnect' ) ) {
        			ssh2_disconnect ( $connection );
    			} else {
        			fclose ( $connection );
        			$connection = false;
    			}

			} else {
				die('ssh登陆失败');
			}
		} else {
			die('没有ssh2扩展');
		}
	}	
}