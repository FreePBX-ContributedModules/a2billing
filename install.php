<?php
$path = dirname(__FILE__) ;

if (! function_exists("out")) {
	function out($text) {
		if (php_sapi_name() == 'cli') {
			echo $text ."\n";
		} else {
			echo $text."<br />";
		}
	}
}


//check if web dir's parent is writeable by the current apache user and
//attempt to create the dir beofre anything else - no need to go through the installation if it will fail anyway
$a2bweb = $amp_conf['AMPWEBROOT'] . '/a2b';

if (is_dir($a2bweb) && is_writable($a2bweb) || @mkdir($a2bweb, 0755)) {
	 //were good here, move along
} else {
	out(_('The a2billing web directory isn\'t wrtiable. Please execute the following commands:'));
	out(_( 'mkdir ' . $a2bweb ));
	out(_( 'chown '
		. (isset($amp_conf['AMPASTERISKWEBUSER']) ? $amp_conf['AMPASTERISKWEBUSER'] : 'asterisk')
		. ':'
		. (isset($amp_conf['AMPASTERISKWEBGROUP']) ? $amp_conf['AMPASTERISKWEBGROUP'] : 'asterisk')
		. ' ' .$a2bweb
		));
	out(_( 'chmod 0755 ' . $a2bweb));
	exit(1);
}

//ensure that were using mysql, a2billing dosent support sqlite
if ($amp_conf['AMPDBENGINE'] != 'mysql') {
	die_freepbx(_('A2Billing only support a mysql DB'));
}

//Check if there is already an a2b database, dont re-create it if it exists.
out(_("Checking if DB exists..."));

$sql = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = "mya2billing"';
$q = $db->getOne($sql);

if ($q == 'mya2billing') { //TODO: make sure this test works
	out(_("A2Billing DB already exists. Assuming proper install and using current data. ..."));
} else {
	out(_("A2Billing tables are missing..."));

	$msg = 'The database for A2Billing needs to be set up. Please log in to mysql and execture the following commands.'
		. ' When done, rerun the instalation of this module:'
		. "\n"
		. 'GRANT ALL PRIVILEGES ON mya2billing.* TO "' . $amp_conf['AMPDBUSER'] . '"@"' . $amp_conf['AMPDBHOST'] . '" IDENTIFIED BY "' . $amp_conf['AMPDBPASS'] . '";'
		. "\n"
		. 'CREATE DATABASE IF NOT EXISTS `mya2billing`;'
		. "\n"
		. 'exit'
		. "\n"
		. 'Then, do the following from the command line:'
		. "\n"
		. 'mysql -u<privlaged user> -p <password> -Dmya2billing < ' 
		. $path . '/a2b/DataBase/mysql-5.x/a2billing-mysql-schema-v1.7.0.sql'
		. "\n";
	out(_($msg));
	exit(1);


}

//install files
//agi files
system('ln -sf ' . $path . '/AGI/' . $amp_conf['AMPWEBROOT'] . '/admin/modules/a2billing/ag-bin');

//web files
system('ln -sf ' . $path . '/a2b/admin/ ' 	. $a2bweb . '/admin');
system('ln -sf ' . $path . '/a2b/agent/ ' 	. $a2bweb . '/agent');
system('ln -sf ' . $path . '/a2b/customer/ ' . $a2bweb . '/customer');
system('ln -sf ' . $path . '/a2b/common/ ' 	. $a2bweb . '/common');

//symlink soundfiles
system('ln -sf ' . $path . '/a2b/addons/sounds ' . $path . '/sounds');

//set a2billing to use freepbx's asterisk manager credentials
$dsn = array(
	    'phptype'  => $amp_conf['AMPDBENGINE'],
	    'username' => $amp_conf['AMPDBUSER'],
	    'password' => $amp_conf['AMPDBPASS'],
	    'hostspec' => $amp_conf['AMPDBHOST'],
	    'database' => 'mya2billing'
	);

$a2bdb = DB::connect($dsn);

$settings = array(
			array('id' => 8, 'val' => $amp_conf["ASTMANAGERHOST"]),
			array('id' => 9, 'val' => $amp_conf["AMPMGRUSER"]),
			array('id' => 10, 'val' => $amp_conf["AMPMGRPASS"])
		);

foreach($settings as $set) {
	$sql = 'UPDATE cc_config set config_value = ? WHERE id = ? ';
	$q = $a2bdb->query($sql, array($set['val'],$set['id']));
	if (DB::isError($q)) {
		die_freepbx($q->getMessage());
	}
}


//
$ini = parse_ini_file($path . '/a2b/a2billing.conf', true);

$ini['database']['hostname']	= $amp_conf['AMPDBHOST'];
$ini['database']['user']		= $amp_conf['AMPDBUSER'];
$ini['database']['password']	= $amp_conf['AMPDBPASS'];
$ini['database']['dbname']		= 'mya2billing';
$ini['database']['dbtype']		= $amp_conf['AMPDBENGINE'];
$ini['handler_FileHandler']['args']			= '(' . $ini['handler_FileHandler']['args'] . ')'; //fix for php ini parser oddities
$ini['handler_consoleHandler']['args']		= '(' . $ini['handler_consoleHandler']['args'] . ')';

if (!function_exists('write_ini_file')) { 
    function write_ini_file($assoc_arr, $path, $has_sections=FALSE) { 
        $content = ""; 

        if ($has_sections) { 
            foreach ($assoc_arr as $key=>$elem) { 
                $content .= "[".$key."]\n"; 
                foreach ($elem as $key2=>$elem2) 
                { 
                    if(is_array($elem2)) 
                    { 
                        for($i=0;$i<count($elem2);$i++) 
                        { 
                            //$content .= $key2."[] = \"".$elem2[$i]."\"\n"; 
 							$content .= $key2."[] = ".$elem2[$i]."\n"; 
                        } 
                    } 
                    else if($elem2=="") $content .= $key2." = \n"; 
                    //else $content .= $key2." = \"".$elem2."\"\n";
					else $content .= $key2." = ".$elem2."\n"; 
                } 
            } 
        } 
        else { 
            foreach ($assoc_arr as $key=>$elem) { 
                if(is_array($elem)) 
                { 
                    for($i=0;$i<count($elem);$i++) 
                    { 
                        //$content .= $key2."[] = \"".$elem[$i]."\"\n"; 
						$content .= $key2."[] = ".$elem[$i]."\n"; 
                    } 
                } 
                else if($elem=="") $content .= $key2." = \n"; 
                //else $content .= $key2." = \"".$elem."\"\n"; 
				else $content .= $key2." = ".$elem."\n"; 
            } 
        } 

        if (!$handle = fopen($path, 'w')) { 
            return false; 
        } 
        if (!fwrite($handle, $content)) { 
            return false; 
        } 
        fclose($handle); 

        return true; 
    } 
}

write_ini_file($ini, '/etc/asterisk/a2billing.conf', true);

?>